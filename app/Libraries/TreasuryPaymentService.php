<?php
namespace App\Libraries;

use RuntimeException;

/** The company catalog is shared; each operation keeps its own financial semantics. */
class TreasuryPaymentService
{
    public const METADATA = ['payment_method_id','payment_method_code','payment_method_name','payment_method_snapshot','payment_data'];
    public static function metadata(array $row): array
    {
        return array_intersect_key($row, array_flip(self::METADATA));
    }

    public function formOptions(string $companyId): array
    {
        $db=db_connect();
        return [
            'paymentCatalog'=>(new SalesPaymentService())->catalog($companyId),
            'paymentCurrencies'=>$db->table('currencies')->where('company_id',$companyId)->where('active',1)->orderBy('code')->get()->getResultArray(),
            'paymentPoints'=>$db->table('sales_points_of_sale')->where('company_id',$companyId)->where('active',1)->orderBy('name')->get()->getResultArray(),
            'registerBranches'=>array_column($db->table('cash_registers')->select('id,branch_id')->where('company_id',$companyId)->get()->getResultArray(),'branch_id','id'),
            'baseCurrency'=>$db->table('companies')->where('id',$companyId)->get()->getRowArray()['currency_code'] ?? 'ARS',
        ];
    }

    public function prepare(string $companyId, array $input, string $operation, ?string $branchId = null): array
    {
        $service = new SalesPaymentService();
        $currency = strtoupper(trim((string)($input['currency_code'] ?? '')));
        $context = $service->context($companyId,$currency,$branchId,trim((string)($input['point_of_sale_id'] ?? '')) ?: null);
        $context['operation'] = $operation;
        $rows = $service->prepare($companyId,[$input],$context,null,true);
        if (count($rows)!==1) throw new RuntimeException('Selecciona un medio y un importe mayor que cero.');
        $row = $rows[0];
        $row['currency_code']=$currency;
        $row['branch_id']=$context['branch_id'];
        $row['point_of_sale_id']=$context['point_of_sale_id'];
        $rate=$input['exchange_rate'] ?? 1;
        if (!is_scalar($rate) || !is_numeric($rate) || !is_finite((float)$rate) || (float)$rate<=0 || (float)$rate>999999999999) throw new RuntimeException('Cotización inválida.');
        $base=db_connect()->table('companies')->select('currency_code')->where('id',$companyId)->get()->getRowArray()['currency_code'] ?? 'ARS';
        $row['exchange_rate']=$currency===$base ? 1 : round((float)$rate,6);
        if ($row['exchange_rate']<=0) throw new RuntimeException('Cotización inválida.');
        $converted=round($row['amount']*$row['exchange_rate'],2);
        if (!is_finite($converted) || $converted<=0 || $converted>999999999999.99) throw new RuntimeException('El importe convertido queda fuera del rango admitido.');
        $row['status']=$row['status']==='pending'?'pending':'posted';
        return $row;
    }

    /** Request keys prevent a double click or an API retry from duplicating funds. */
    public function replay(string $table, string $companyId, array $input): array
    {
        $key=$input['request_key'] ?? '';
        if (!is_string($key) || !preg_match('/^[a-zA-Z0-9_-]{16,80}$/D',$key)) throw new RuntimeException('Actualiza el formulario: falta el identificador de operación.');
        unset($input['csrf_test_name'],$input['popup'],$input['company_id']);
        // Ignore transport-specific CSRF fields in the operation fingerprint.
        unset($input[config('Security')->tokenName]);
        ksort($input);
        $hash=hash('sha256',json_encode($input,JSON_THROW_ON_ERROR));
        $old=db_connect()->table($table)->where('company_id',$companyId)->where('request_key',$key)->get()->getRowArray();
        if ($old && !hash_equals((string)$old['request_hash'],$hash)) throw new RuntimeException('El identificador ya se utilizó con otros datos.');
        return [$old,$key,$hash];
    }

    public function manual(CashService $cash, string $companyId, array $input, string $userId): string
    {
        return (new InventoryIntegrityService())->transaction($companyId,function() use($cash,$companyId,$input,$userId) {
            [$old,$key,$hash]=$this->replay('cash_movements',$companyId,$input);
            if ($old) return $old['id'];
            $session=$cash->ownedSession($companyId,(string)($input['cash_session_id'] ?? ''));
            if (!$session || $session['status']!=='open') throw new RuntimeException('Selecciona una sesión abierta propia.');
            $type=$input['movement_type'] ?? 'manual_income';
            if (!in_array($type,['manual_income','manual_expense','withdrawal','adjustment'],true)) throw new RuntimeException('Tipo de movimiento inválido.');
            $out=in_array($type,['manual_expense','withdrawal'],true) || ($type==='adjustment' && ($input['adjustment_direction'] ?? 'income')==='expense');
            if ($type==='adjustment' && !in_array($input['adjustment_direction'] ?? 'income',['income','expense'],true)) throw new RuntimeException('Sentido de ajuste inválido.');
            $register=db_connect()->table('cash_registers')->where('company_id',$companyId)->where('id',$session['cash_register_id'])->get()->getRowArray();
            $row=$this->prepare($companyId,$input,$out?'supplier':'collection',$register['branch_id'] ?? null);
            $id=$cash->registerMovement(self::metadata($row)+[
                'company_id'=>$companyId,'cash_session_id'=>$session['id'],'cash_register_id'=>$session['cash_register_id'],
                'movement_type'=>$type,'payment_method'=>$row['payment_method'],'amount'=>($out?-1:1)*$row['amount'],
                'gateway_id'=>$row['gateway_id'],'cash_check_id'=>$row['cash_check_id'],
                'currency_code'=>$row['currency_code'],'exchange_rate'=>$row['exchange_rate'],'status'=>$row['status'],
                'branch_id'=>$row['branch_id'],'point_of_sale_id'=>$row['point_of_sale_id'],
                'reference_type'=>'cash_manual','request_key'=>$key,'request_hash'=>$hash,
                'reference_number'=>$row['reference'],'external_reference'=>$row['external_reference'],
                'occurred_at'=>$row['paid_at'] ?: date('Y-m-d H:i:s'),'notes'=>$row['notes'],'created_by'=>$userId,
            ]);
            if (!$id) throw new RuntimeException('No se pudo registrar el movimiento.');
            return $id;
        });
    }

    public function confirmManual(CashService $cash,string $companyId,string $id,string $userId,string $evidence): void
    {
        (new InventoryIntegrityService())->transaction($companyId,function() use($cash,$companyId,$id,$userId,$evidence) {
            if (trim($evidence)==='' || mb_strlen($evidence)>2000) throw new RuntimeException('Indica la evidencia de confirmación.');
            $model=new \App\Models\CashMovementModel();
            $row=$model->where('company_id',$companyId)->find($id);
            if (!$row || $row['reference_type']!=='cash_manual') throw new RuntimeException('Movimiento no disponible.');
            $session=$cash->ownedSession($companyId,$row['cash_session_id']);
            if (!$session || $session['status']!=='open') throw new RuntimeException('La sesión debe estar abierta y ser propia.');
            if ($row['status']==='posted') return;
            if ($row['status']!=='pending') throw new RuntimeException('Estado de movimiento inválido.');
            if (!$model->update($id,['status'=>'posted','confirmed_by'=>$userId,'confirmed_at'=>date('Y-m-d H:i:s'),'confirmation_evidence'=>trim($evidence)])) throw new RuntimeException('No se pudo confirmar.');
            if (!(new \App\Models\CashSessionModel())->update($session['id'],['expected_closing_amount'=>$cash->expectedBalance($session['id'],(float)$session['opening_amount'])])) throw new RuntimeException('No se pudo actualizar la sesión.');
        });
    }
}
