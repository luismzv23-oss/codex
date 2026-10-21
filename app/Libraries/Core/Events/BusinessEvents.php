<?php
namespace App\Libraries\Core\Events;

use RuntimeException;

/** Minimal, company-validated envelopes. Call only from the business transaction. */
final class BusinessEvents
{
    private static function owned(string $table,string $companyId,string $id): array
    {
        $row=db_connect()->table($table)->where('company_id',$companyId)->where('id',$id)->get()->getRowArray();
        if (!$row) throw new RuntimeException('Origen del evento no disponible para esta empresa.');
        return $row;
    }

    public static function sale(string $companyId,string $id,string $transition): string
    {
        if (!in_array($transition,['confirmed','cancelled'],true)) throw new RuntimeException('Transición de evento inválida.');
        $sale=self::owned('sales',$companyId,$id);
        if ($sale['status']!==$transition) throw new RuntimeException('La venta no alcanzó el estado del evento.');
        $payload=['sale_id'=>$id];
        if ($transition==='confirmed') {
            $document=self::owned('sales_document_types',$companyId,$sale['document_type_id']);
            $payload+=['total'=>(float)$sale['total'],'customer_id'=>$sale['customer_id'] ?? null,'category'=>$document['category']];
            $payload['tax_configs']=$document['category']==='delivery_note' ? [] : self::taxSnapshot('tax_perceptions',$companyId);
        }
        return (new OutboxPublisher())->publish($companyId,'sale.'.$transition,$transition.':'.$id,'sale',$id,$payload);
    }

    public static function stockReceived(string $companyId,string $id): string
    {
        self::owned('purchase_receipts',$companyId,$id);
        return (new OutboxPublisher())->publish($companyId,'inventory.stock_received','received:'.$id,'purchase_receipt',$id,['receipt_id'=>$id]);
    }

    public static function supplierPayment(string $companyId,string $id): string
    {
        $payment=self::owned('purchase_payments',$companyId,$id);
        if ($payment['status']!=='posted') throw new RuntimeException('El pago aún no fue contabilizado.');
        return (new OutboxPublisher())->publish($companyId,'purchase.payment_made','posted:'.$id,'purchase_payment',$id,[
            'payment_id'=>$id,'amount'=>(float)$payment['amount'],'supplier_id'=>$payment['supplier_id'],
            'tax_configs'=>self::taxSnapshot('tax_withholdings',$companyId),
        ]);
    }

    public static function receiptPayment(string $companyId,string $receiptId,array $payment): string
    {
        $receipt=self::owned('sales_receipts',$companyId,$receiptId);
        $lines=json_decode($receipt['payment_details'],true,512,JSON_THROW_ON_ERROR);
        $line=null;
        foreach ($lines as $candidate) if (($candidate['id'] ?? null)===($payment['id'] ?? null)) $line=$candidate;
        if (!$line || empty($payment['posted_at']) || (float)$line['amount']!==(float)$payment['amount']) throw new RuntimeException('Medio de cobro no disponible para el evento.');
        return (new OutboxPublisher())->publish($companyId,'sale.payment_received','posted:'.$payment['id'],'receipt_line',$payment['id'],[
            'receipt_id'=>$receiptId,'payment_id'=>$payment['id'],'amount'=>(float)$payment['amount'],'currency_code'=>$receipt['currency_code'],
        ]);
    }

    private static function taxSnapshot(string $table,string $companyId): array
    {
        $q=db_connect()->table($table)->select('id, company_id, name, tax_type, rate'.($table==='tax_withholdings' ? ', min_amount' : ''))
            ->where('company_id',$companyId)->where('active',1);
        if ($table==='tax_withholdings') $q->whereIn('applies_to',['purchases','both']);
        $rows=$q->orderBy('id')->get()->getResultArray();
        foreach ($rows as &$row) { $row['rate']=(float)$row['rate']; if (isset($row['min_amount'])) $row['min_amount']=(float)$row['min_amount']; }
        return $rows;
    }
}
