<?php
namespace App\Libraries;

use App\Models\PurchasePayableModel;
use App\Models\PurchasePaymentModel;
use RuntimeException;

/** Shared web/API write path. The company lock serializes debt and confirmation changes. */
class SupplierPaymentService
{
    /** Keep every pending instruction accessible, plus the latest posted payments. */
    public function history(string $companyId): array
    {
        return array_merge(
            (new PurchasePaymentModel())->where('company_id',$companyId)->where('status','pending')->orderBy('created_at','DESC')->findAll(),
            (new PurchasePaymentModel())->where('company_id',$companyId)->where('status','posted')->orderBy('created_at','DESC')->findAll(50)
        );
    }

    public function create(string $companyId,array $input,string $userId,?string $branchId=null): array
    {
        return (new InventoryIntegrityService())->transaction($companyId,function() use($companyId,$input,$userId,$branchId) {
            $policy=new TreasuryPaymentService();
            [$old,$key,$hash]=$policy->replay('purchase_payments',$companyId,$input);
            if ($old) return $old;
            $payable=(new PurchasePayableModel())->where('company_id',$companyId)->find((string)($input['purchase_payable_id'] ?? ''));
            if (!$payable) throw new RuntimeException('Cuenta a pagar no disponible.');
            $input['currency_code']=$input['currency_code'] ?? $payable['currency_code'];
            if ($input['currency_code']!==$payable['currency_code']) throw new RuntimeException('El pago debe expresarse en la moneda de la deuda.');
            $initialSession=(new PurchaseIntegrityService())->paymentSession($companyId,$payable,$input['currency_code'],trim((string)($input['gateway_id'] ?? '')) ?: null,trim((string)($input['cash_check_id'] ?? '')) ?: null);
            $register=db_connect()->table('cash_registers')->where('company_id',$companyId)->where('id',$initialSession['cash_register_id'])->get()->getRowArray();
            $row=$policy->prepare($companyId,$input,'supplier',($register['branch_id'] ?? null) ?: $branchId);
            $reserved=(float)(db_connect()->table('purchase_payments')->selectSum('amount','amount')->where('company_id',$companyId)
                ->where('purchase_payable_id',$payable['id'])->where('status','pending')->get()->getRowArray()['amount'] ?? 0);
            if (round($row['amount']+$reserved,2)>(float)$payable['balance_amount']) throw new RuntimeException('El pago supera el saldo disponible, descontando pagos pendientes de confirmar.');
            // Validate ownership and an open session before accepting the instruction.
            (new PurchaseIntegrityService())->paymentSession($companyId,$payable,$row['currency_code'],$row['gateway_id'],$row['cash_check_id']);
            $this->validateCheck($companyId,$row);
            $data=TreasuryPaymentService::metadata($row)+array_intersect_key($row,array_flip([
                'payment_method','amount','gateway_id','cash_check_id','currency_code','exchange_rate','reference','external_reference','paid_at','notes','status','branch_id','point_of_sale_id',
            ]))+[
                'company_id'=>$companyId,'supplier_id'=>$payable['supplier_id'],'purchase_payable_id'=>$payable['id'],
                'payment_number'=>(new VoucherSequenceService())->next($companyId,'PAGPROV','PP',$branchId,false),
                'request_key'=>$key,'request_hash'=>$hash,'created_by'=>$userId,
            ];
            $data['paid_at']=$data['paid_at'] ?: date('Y-m-d H:i:s');
            $id=(new PurchasePaymentModel())->insert($data,true);
            if (!$id) throw new RuntimeException('No se pudo guardar el pago.');
            $payment=(new PurchasePaymentModel())->find($id);
            if ($payment['status']==='posted') $this->post($companyId,$payment,$payable,$userId);
            return (new PurchasePaymentModel())->find($id);
        });
    }

    public function confirm(string $companyId,string $id,string $userId,string $evidence): array
    {
        return (new InventoryIntegrityService())->transaction($companyId,function() use($companyId,$id,$userId,$evidence) {
            if (trim($evidence)==='' || mb_strlen($evidence)>2000) throw new RuntimeException('Indica una evidencia verificable del pago.');
            $model=new PurchasePaymentModel();
            $payment=$model->where('company_id',$companyId)->find($id);
            if (!$payment) throw new RuntimeException('Pago no disponible.');
            if ($payment['status']==='posted') return $payment;
            if ($payment['status']!=='pending') throw new RuntimeException('El pago no está pendiente.');
            $payable=(new PurchasePayableModel())->where('company_id',$companyId)->find($payment['purchase_payable_id']);
            if (!$payable || (float)$payment['amount']>(float)$payable['balance_amount']) throw new RuntimeException('El saldo cambió y ya no cubre este pago.');
            $this->post($companyId,$payment,$payable,$userId);
            if (!$model->update($id,['status'=>'posted','confirmed_at'=>date('Y-m-d H:i:s'),'confirmed_by'=>$userId,'confirmation_evidence'=>trim($evidence)])) throw new RuntimeException('No se pudo confirmar el pago.');
            return $model->find($id);
        });
    }

    private function validateCheck(string $companyId,array $payment): void
    {
        if (empty($payment['cash_check_id'])) return;
        $base=db_connect()->table('companies')->where('id',$companyId)->get()->getRowArray()['currency_code'] ?? 'ARS';
        if ($payment['currency_code']!==$base) throw new RuntimeException('El cheque en cartera se registra en moneda base; no admite una conversión implícita.');
        $check=db_connect()->table('cash_checks')->where('company_id',$companyId)->where('id',$payment['cash_check_id'])->get()->getRowArray();
        if (!$check || !in_array($check['status'],['portfolio','received','cartera'],true) || round((float)$check['amount'],2)!==round((float)$payment['amount'],2)) throw new RuntimeException('El cheque debe estar disponible y coincidir con el importe del pago.');
        if (db_connect()->table('purchase_payments')->where('company_id',$companyId)->where('cash_check_id',$check['id'])->whereIn('status',['pending','posted'])->countAllResults()) throw new RuntimeException('El cheque ya está comprometido en otro pago.');
    }

    private function post(string $companyId,array $payment,array $payable,string $userId): void
    {
        $session=(new PurchaseIntegrityService())->paymentSession($companyId,$payable,$payment['currency_code'],$payment['gateway_id'],$payment['cash_check_id']);
        $register=db_connect()->table('cash_registers')->where('company_id',$companyId)->where('id',$session['cash_register_id'])->get()->getRowArray();
        $snapshot=json_decode($payment['payment_method_snapshot'],true,512,JSON_THROW_ON_ERROR);
        $branches=SalesPaymentService::list($snapshot['branch_ids'] ?? []);
        if ($branches && !in_array($register['branch_id'] ?? null,$branches,true)) throw new RuntimeException('Confirma el pago desde una caja de la sucursal habilitada en el medio original.');
        if (!empty($payment['cash_check_id'])) {
            $check=db_connect()->table('cash_checks')->where('company_id',$companyId)->where('id',$payment['cash_check_id'])->get()->getRowArray();
            if (!$check || !in_array($check['status'],['portfolio','received','cartera'],true)) throw new RuntimeException('El cheque ya no está disponible.');
            if (!db_connect()->table('cash_checks')->where('company_id',$companyId)->where('id',$check['id'])->update(['status'=>'endorsed','supplier_id'=>$payable['supplier_id']])) throw new RuntimeException('No se pudo aplicar el cheque.');
        }
        $id=(new CashService())->registerMovement(TreasuryPaymentService::metadata($payment)+[
            'company_id'=>$companyId,'cash_register_id'=>$session['cash_register_id'],'cash_session_id'=>$session['id'],
            'movement_type'=>'purchase_payment','payment_method'=>$payment['payment_method'],'amount'=>-(float)$payment['amount'],
            'currency_code'=>$payment['currency_code'],'exchange_rate'=>$payment['exchange_rate'],'status'=>'posted',
            'gateway_id'=>$payment['gateway_id'],'cash_check_id'=>$payment['cash_check_id'],
            'reference_type'=>'purchase_payment','reference_id'=>$payment['id'],'reference_number'=>$payment['payment_number'],
            'external_reference'=>$payment['external_reference'],'occurred_at'=>$payment['paid_at'],'notes'=>'Pago a proveedor','created_by'=>$userId,
        ]);
        if (!$id) throw new RuntimeException('No se pudo registrar el egreso.');
        $paid=round((float)$payable['paid_amount']+(float)$payment['amount'],2);
        $balance=round((float)$payable['total_amount']-$paid,2);
        if (!(new PurchasePayableModel())->update($payable['id'],['paid_amount'=>$paid,'balance_amount'=>$balance,'status'=>$balance<=0?'paid':'partial'])) throw new RuntimeException('No se pudo actualizar la deuda.');
        $baseRate=(float)($payable['exchange_rate'] ?? 1);
        $difference=round(((float)$payment['exchange_rate']-$baseRate)*(float)$payment['amount'],2);
        if ($difference!=0 && !(new \App\Models\SupplierExchangeDifferenceModel())->insert([
            'company_id'=>$companyId,'purchase_payment_id'=>$payment['id'],'currency_code'=>$payment['currency_code'],
            'base_rate'=>$baseRate,'settlement_rate'=>$payment['exchange_rate'],'difference_amount'=>$difference,'notes'=>'Diferencia de cambio al pago',
        ])) throw new RuntimeException('No se pudo registrar la diferencia de cambio.');
        if (($payment['status'] ?? '')!=='posted' && !(new PurchasePaymentModel())->update($payment['id'],['status'=>'posted'])) throw new RuntimeException('No se pudo actualizar el pago.');
        $result=service('accounting')->syncPurchasePayment($companyId,$payment['id'],$userId);
        if (empty($result['ok'])) throw new RuntimeException($result['error'] ?? 'No se pudo contabilizar el pago.');
        EventBus::emit('purchase.payment_made',['company_id'=>$companyId,'payment_id'=>$payment['id'],'amount'=>$payment['amount'],'supplier_id'=>$payable['supplier_id']]);
    }
}
