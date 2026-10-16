<?php
namespace App\Libraries;
use App\Models\{PurchaseInvoiceModel,PurchaseInvoiceItemModel,SupplierCostHistoryModel};
use RuntimeException;

/** Single registration path for manual, API and document imports. Never receives stock. */
class PurchaseInvoiceService
{
    public static function text($value,int $max=180): string
    {
        if (!is_scalar($value) && $value!==null) throw new RuntimeException('Dato de texto inválido.');
        $value=trim((string)$value);
        if (mb_strlen($value)>$max) throw new RuntimeException('Un dato supera la longitud admitida.');
        return $value;
    }
    public static function number($value,int $scale=2,float $max=999999999999.99): float
    {
        if (!is_scalar($value) || !preg_match('/^\d+(?:\.\d{1,'.$scale.'})?$/D',(string)$value) || !is_finite((float)$value) || (float)$value>$max) throw new RuntimeException('Importe o cantidad inválido; utiliza decimales sin separadores de miles.');
        return round((float)$value,$scale);
    }
    public static function taxId($value): string {return preg_replace('/\D/','',self::text($value,40));}
    public static function date($value,bool $required=false): ?string
    {
        $value=self::text($value,30);
        if ($value==='') {if ($required) throw new RuntimeException('Completa la fecha de emisión.');return null;}
        foreach (['!Y-m-d','!Y-m-d\TH:i','!Y-m-d\TH:i:s','!Y-m-d H:i:s'] as $format) {
            $date=\DateTimeImmutable::createFromFormat($format,$value);
            if ($date && $date->format(substr($format,1))===$value) return $date->format('Y-m-d H:i:s');
        }
        throw new RuntimeException('Fecha inválida.');
    }
    public function normalize(string $companyId,array $input,bool $strict=false): array
    {
        $db=db_connect();
        $supplierId=self::text($input['supplier_id'] ?? '',36);
        $supplier=$db->table('suppliers')->where('company_id',$companyId)->where('id',$supplierId)->get()->getRowArray();
        if (!$supplier || !(int)$supplier['active']) throw new RuntimeException('Selecciona un proveedor activo de esta empresa.');
        $currency=self::text($input['currency_code'] ?? 'ARS',10);
        if (!$db->table('currencies')->where('company_id',$companyId)->where('code',$currency)->where('active',1)->countAllResults()) throw new RuntimeException('La moneda no está activa en esta empresa.');
        $rate=self::number($input['exchange_rate'] ?? 1,6,999999999);
        if ($rate<=0) throw new RuntimeException('La cotización debe ser mayor que cero.');
        $type=self::text($input['document_type'] ?? 'LEGACY',20);
        if (!in_array($type,['FACTURA_A','FACTURA_B','FACTURA_C','FACTURA_M','FACTURA_E','OTRO','LEGACY'],true)) throw new RuntimeException('Tipo de comprobante inválido.');
        if ($strict && $type==='LEGACY') throw new RuntimeException('Selecciona el tipo de comprobante.');
        $point=null;$sequence=null;
        if ($type!=='LEGACY') {
            $point=self::text($input['point_of_sale'] ?? '',5);$sequence=self::text($input['invoice_sequence'] ?? '',8);
            if (!preg_match('/^\d{1,5}$/D',$point) || !preg_match('/^\d{1,8}$/D',$sequence) || (int)$sequence<=0) throw new RuntimeException('Completa punto de venta y número del comprobante.');
            $point=str_pad($point,5,'0',STR_PAD_LEFT);$sequence=str_pad($sequence,8,'0',STR_PAD_LEFT);
            $number=$point.'-'.$sequence;
        } else $number=self::text($input['invoice_number'] ?? '',80);
        if ($number==='') throw new RuntimeException('Completa el número de factura.');
        $kind=self::text($input['purchase_kind'] ?? 'goods',20);
        if (!in_array($kind,['goods','pending_receipt','service'],true)) throw new RuntimeException('Selecciona el destino de la compra.');
        $receiptId=self::text($input['purchase_receipt_id'] ?? '',36) ?: null;
        if ($strict && $kind==='goods' && !$receiptId) throw new RuntimeException('Selecciona la recepción o indica mercadería pendiente de recibir.');
        if ($kind==='pending_receipt' && $receiptId) throw new RuntimeException('Una compra pendiente de recibir no se vincula a una recepción existente.');
        if ($kind==='service' && $receiptId) throw new RuntimeException('Una factura de servicios no se vincula a una recepción de mercadería.');
        $items=$input['items'] ?? [];
        if (!is_array($items) || !$items || count($items)>300) throw new RuntimeException('Agrega entre 1 y 300 conceptos.');
        $rows=[];$net=0;$tax=0;
        foreach ($items as $item) {
            if (!is_array($item)) throw new RuntimeException('Concepto inválido.');
            $description=self::text($item['description'] ?? '',500);
            $quantity=self::number($item['quantity'] ?? 0,6,999999999);
            $cost=self::number($item['unit_cost'] ?? 0,6,999999999);
            $taxRate=self::number($item['tax_rate'] ?? 0,4,100);
            if ($description==='' && $quantity==0) continue;
            if (!$description || $quantity<=0) throw new RuntimeException('Cada concepto requiere descripción y cantidad mayor que cero.');
            $productId=self::text($item['product_id'] ?? '',36) ?: null;
            if ($productId && !$db->table('inventory_products')->where('company_id',$companyId)->where('id',$productId)->where('active',1)->countAllResults()) throw new RuntimeException('Producto no disponible en esta empresa.');
            if ($kind==='service' && $productId) throw new RuntimeException('Los servicios deben cargarse sin producto de inventario.');
            $lineNet=round($quantity*$cost,2);
            if ($lineNet>999999999999.99) throw new RuntimeException('El concepto supera el importe admitido.');
            $lineTax=array_key_exists('tax_amount',$item) && $item['tax_amount']!=='' ? self::number($item['tax_amount']) : round($lineNet*$taxRate/100,2);
            $rows[]=['product_id'=>$productId,'description'=>$description,'quantity'=>$quantity,'unit_cost'=>$cost,'tax_rate'=>$taxRate,'tax_amount'=>$lineTax,'line_total'=>round($lineNet+$lineTax,2)];
            $net+=(int)round($lineNet*100);$tax+=(int)round($lineTax*100);
        }
        if (!$rows) throw new RuntimeException('Agrega al menos un concepto válido.');
        $adjustments=$input['adjustments'] ?? [];
        if (!is_array($adjustments) || count($adjustments)>40) throw new RuntimeException('Desglose de importes inválido.');
        $normalized=[];$delta=0;$extraVat=0;
        foreach ($adjustments as $adjustment) {
            $adjustmentKind=$adjustment['kind'] ?? '';
            if (!in_array($adjustmentKind,['vat','tax','charge','discount'],true)) throw new RuntimeException('Tipo de ajuste inválido.');
            $amount=self::number($adjustment['amount'] ?? 0);
            $label=self::text($adjustment['label'] ?? '',180);
            if (!$label || $amount<=0) throw new RuntimeException('Cada ajuste requiere descripción e importe positivo.');
            $signed=(int)round($amount*100)*($adjustmentKind==='discount'?-1:1);
            $delta+=$signed;
            if ($adjustmentKind==='vat') $extraVat+=$signed;
            $normalized[]=['kind'=>$adjustmentKind,'label'=>$label,'amount'=>$amount];
        }
        $total=$net+$tax+$delta;
        if ($net+$delta-$extraVat<0) throw new RuntimeException('Los descuentos no pueden generar un neto negativo.');
        if ($total<=0 || $total>99999999999999) throw new RuntimeException('El total de la factura debe ser positivo y estar dentro del rango admitido.');
        $reviewed=$input['reviewed_total'] ?? null;
        if ($strict && ($reviewed===null || $reviewed==='')) throw new RuntimeException('Ingresa el total que figura en el documento.');
        if ($reviewed!==null && $reviewed!=='' && (int)round(self::number($reviewed)*100)!==$total) throw new RuntimeException('El total calculado no coincide con el documento. Revisa conceptos e impuestos.');
        $issue=self::date($input['issue_date'] ?? date('Y-m-d'),true);$due=self::date($input['due_date'] ?? '');
        if ($due && $due<$issue) throw new RuntimeException('El vencimiento no puede ser anterior a la emisión.');
        $supplierTax=self::taxId($supplier['tax_id'] ?? '');
        if ($strict) {
            $issuerTax=self::taxId($input['supplier_tax_id'] ?? '');
            if ($supplierTax && $issuerTax!==$supplierTax) throw new RuntimeException('El CUIT del documento no coincide con el proveedor seleccionado.');
            $receiver=self::taxId($input['receiver_tax_id'] ?? '');
            $companyTax=self::taxId($db->table('companies')->where('id',$companyId)->get()->getRowArray()['tax_id'] ?? '');
            if ($receiver && $companyTax && $receiver!==$companyTax) throw new RuntimeException('La factura está emitida a otro destinatario.');
            if (!in_array($input['identity_reviewed'] ?? false,[true,1,'1'],true)) throw new RuntimeException('Confirma que revisaste emisor, destinatario y numeración.');
        }
        (new PurchaseIntegrityService())->validateInvoice($companyId,$supplierId,$receiptId,$currency,$rows);
        return ['invoice'=>[
            'company_id'=>$companyId,'supplier_id'=>$supplierId,'purchase_receipt_id'=>$receiptId,'invoice_number'=>$number,
            'document_type'=>$type,'point_of_sale'=>$point,'invoice_sequence'=>$sequence,'supplier_tax_id'=>$supplierTax,
            'identity_hash'=>$type==='LEGACY'?null:hash('sha256',($supplierTax?:$supplierId).'|'.$type.'|'.$point.'|'.$sequence),
            'currency_code'=>$currency,'exchange_rate'=>$rate,'purchase_kind'=>$kind,'subtotal'=>($net+$delta-$extraVat)/100,
            'tax_total'=>($tax+$extraVat)/100,'total'=>$total/100,'issue_date'=>$issue,'due_date'=>$due,
            'notes'=>self::text($input['notes'] ?? '',2000),'adjustments_json'=>json_encode($normalized,JSON_THROW_ON_ERROR),
            'adjustment_total'=>$delta/100,'reviewed_total'=>$reviewed!==null && $reviewed!==''?self::number($reviewed):null,
        ],'items'=>$rows];
    }

    public function register(string $companyId,array $input,string $userId,bool $strict=false,?string $importId=null): array
    {
        return (new InventoryIntegrityService())->transaction($companyId,function() use($companyId,$input,$userId,$strict,$importId) {
            $model=new PurchaseInvoiceModel();
            $key=self::text($input['request_key'] ?? '',80);
            $hashInput=$input;unset($hashInput[config('Security')->tokenName],$hashInput['popup'],$hashInput['company_id']);
            ksort($hashInput);$hash=hash('sha256',json_encode($hashInput,JSON_THROW_ON_ERROR));
            if ($key) {
                if (!preg_match('/^[a-zA-Z0-9_-]{16,80}$/D',$key)) throw new RuntimeException('Identificador de operación inválido.');
                $existing=$model->where('company_id',$companyId)->where('request_key',$key)->first();
                if ($existing) {
                    if (!hash_equals((string)$existing['request_hash'],$hash)) throw new RuntimeException('La clave de operación ya se utilizó con otros datos.');
                    return $existing;
                }
            }
            $prepared=$this->normalize($companyId,$input,$strict);$invoice=$prepared['invoice'];
            $candidates=$model->where('company_id',$companyId)->groupStart()
                ->where('supplier_id',$invoice['supplier_id']);
            if ($invoice['supplier_tax_id']) $candidates->orWhere('supplier_tax_id',$invoice['supplier_tax_id']);
            foreach ($candidates->groupEnd()->findAll() as $old) {
                if ($invoice['identity_hash'] && $old['identity_hash']===$invoice['identity_hash']) throw new RuntimeException('Esta factura ya está registrada.');
                $canonical=static function(string $number): string {
                    if (preg_match('/^(\d{1,5})\s*[-\/]\s*(\d{1,8})$/D',trim($number),$parts)) return str_pad($parts[1],5,'0',STR_PAD_LEFT).str_pad($parts[2],8,'0',STR_PAD_LEFT);
                    return preg_replace('/[^A-Z0-9]/','',strtoupper($number));
                };
                $oldNumber=$canonical($old['invoice_number']);$newNumber=$canonical($invoice['invoice_number']);
                if ($oldNumber===$newNumber && (empty($old['document_type']) || $old['document_type']==='LEGACY' || $invoice['document_type']==='LEGACY' || $old['document_type']===$invoice['document_type'])) throw new RuntimeException('Existe un comprobante con la misma numeración; revisa el registro anterior.');
            }
            $id=$model->insert($invoice+['created_by'=>$userId,'status'=>'registered','request_key'=>$key?:null,'request_hash'=>$hash,'import_id'=>$importId],true);
            if (!$id) throw new RuntimeException('No se pudo registrar la factura.');
            foreach ($prepared['items'] as $row) {
                if (!(new PurchaseInvoiceItemModel())->insert($row+['purchase_invoice_id'=>$id])) throw new RuntimeException('No se pudo registrar el detalle.');
                if ($row['product_id'] && !(new SupplierCostHistoryModel())->insert([
                    'company_id'=>$companyId,'supplier_id'=>$invoice['supplier_id'],'product_id'=>$row['product_id'],'purchase_invoice_id'=>$id,
                    'currency_code'=>$invoice['currency_code'],'exchange_rate'=>$invoice['exchange_rate'],'unit_cost'=>$row['unit_cost'],'observed_at'=>$invoice['issue_date'],
                ])) throw new RuntimeException('No se pudo conservar el historial de costos.');
            }
            (new PurchaseIntegrityService())->syncInvoice($companyId,(string)$id);
            return $model->find($id);
        });
    }
}
