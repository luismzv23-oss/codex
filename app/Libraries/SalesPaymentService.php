<?php
namespace App\Libraries;

use App\Models\CompanyPaymentMethodModel;
use RuntimeException;

/** Shared catalog and authoritative preparation for sales, receipts and advances. */
class SalesPaymentService
{
    public static function list($value): array
    {
        if (is_array($value)) return $value;
        return $value ? (json_decode($value, true, 512, JSON_THROW_ON_ERROR) ?: []) : [];
    }

    public function catalog(string $companyId): array
    {
        $methods = (new CompanyPaymentMethodModel())->where('company_id', $companyId)->where('active', 1)->orderBy('name')->findAll();
        $currencies = db_connect()->table('currencies')->select('id, code')->where('company_id', $companyId)->where('active', 1)->get()->getResultArray();
        $codes = array_column($currencies, 'code', 'id');
        foreach ($methods as &$method) {
            foreach (['currency_ids','branch_ids','point_of_sale_ids','required_fields'] as $key) $method[$key] = self::list($method[$key] ?? []);
            $method['currency_codes'] = array_values(array_intersect_key($codes, array_flip($method['currency_ids'])));
        }
        unset($method);
        return $methods;
    }

    public function context(string $companyId, string $currencyCode, ?string $branchId = null, ?string $pointId = null): array
    {
        $db = db_connect();
        $currency = $db->table('currencies')->where('company_id', $companyId)->where('code', $currencyCode)->where('active', 1)->get()->getRowArray();
        if (!$currency) throw new RuntimeException('La moneda no está activa en esta empresa.');
        if ($pointId) {
            $point = $db->table('sales_points_of_sale')->where('company_id', $companyId)->where('id', $pointId)->where('active', 1)->get()->getRowArray();
            if (!$point) throw new RuntimeException('Punto de venta no disponible.');
            if (!empty($point['branch_id'])) {
                if ($branchId && $branchId !== $point['branch_id']) throw new RuntimeException('El punto de venta pertenece a otra sucursal.');
                $branchId = $point['branch_id'];
            }
        }
        if ($branchId && !$db->table('branches')->where('company_id', $companyId)->where('id', $branchId)->where('active', 1)->countAllResults()) throw new RuntimeException('Sucursal no disponible.');
        return ['currency_id'=>$currency['id'], 'currency_code'=>$currencyCode, 'branch_id'=>$branchId, 'point_of_sale_id'=>$pointId];
    }

    public static function eligible(array $method, array $context): bool
    {
        $operation = $context['operation'] ?? 'collection';
        if ($operation === 'supplier' && empty($method['allow_supplier_payments'])) return false;
        if ($operation === 'collection' && array_key_exists('allow_collections',$method) && !$method['allow_collections']) return false;
        foreach (['currency_ids'=>'currency_id','branch_ids'=>'branch_id','point_of_sale_ids'=>'point_of_sale_id'] as $field=>$key) {
            $ids = self::list($method[$field] ?? []);
            if ($ids && !in_array($context[$key] ?? null, $ids, true)) return false;
        }
        return in_array($method['type'], ['cash','card','transfer','check','wallet'], true);
    }

    public function prepare(string $companyId, array $input, array $context, ?string $saleId = null, bool $collection = false): array
    {
        if (count($input) > 20) throw new RuntimeException('Se admiten hasta 20 medios de pago.');
        $old = [];
        if ($saleId) {
            $sale = db_connect()->table('sales')->where('company_id', $companyId)->where('id', $saleId)->where('status', 'draft')->get()->getRowArray();
            if (!$sale) throw new RuntimeException('Borrador no disponible.');
            $old = array_column(db_connect()->table('sale_payments')->where('sale_id', $saleId)->get()->getResultArray(), null, 'id');
        }
        $rows = []; $seen = []; $integrity = new PaymentIntegrityService();
        foreach ($input as $line) {
            if (!is_array($line)) throw new RuntimeException('Detalle de pago inválido.');
            $savedId = (string) ($line['saved_payment_id'] ?? '');
            if ($savedId && (isset($seen[$savedId]) || !isset($old[$savedId]))) throw new RuntimeException('Pago histórico repetido o ajeno al borrador.');
            $seen[$savedId] = true;
            $original = $savedId ? $old[$savedId] : null;
            $base = $integrity->cents($line['base_amount'] ?? $line['amount'] ?? 0);
            if (!$base && empty($line['payment_method_id']) && !$savedId) continue;
            if (!$base) throw new RuntimeException('Cada pago debe tener un importe mayor que cero.');
            $methodId = (string) ($line['payment_method_id'] ?? '');
            // An unchanged persisted line retains its identity and policy, even if the catalog changed.
            if ($original && $this->unchanged($original, $line, $base, $methodId)) {
                unset($original['created_at'], $original['updated_at']);
                $original['base_amount'] = self::base($original);
                $rows[] = $original;
                continue;
            }
            if (!$methodId) throw new RuntimeException('Selecciona un medio de pago configurado en la empresa; no se asignan medios por su tipo genérico.');
            $method = (new CompanyPaymentMethodModel())->where('company_id', $companyId)->where('active', 1)->find($methodId);
            if (!$method || !self::eligible($method, $context)) throw new RuntimeException('El medio de pago no está habilitado para esta empresa, moneda, sucursal o punto de venta.');
            if ((int) ($line['installments'] ?? 1) !== 1) throw new RuntimeException('La financiación en cuotas requiere un circuito específico y no está habilitada en este formulario.');
            $custom = $line['fields'] ?? [];
            if (!is_array($custom)) throw new RuntimeException('Datos del medio inválidos.');
            $aliases = ['referencia'=>'reference','reference'=>'reference','external_reference'=>'external_reference','gateway_id'=>'gateway_id','cash_check_id'=>'cash_check_id','paid_at'=>'paid_at','fecha'=>'paid_at'];
            foreach (self::list($method['required_fields'] ?? []) as $field) {
                $value = $custom[$field] ?? $line[$aliases[$field] ?? $field] ?? '';
                if (in_array($field, ['referencia','reference','external_reference'], true) && trim((string)$value) === '') $value = ($line['reference'] ?? '') ?: ($line['external_reference'] ?? '');
                if (!is_scalar($value) || trim((string) $value) === '') throw new RuntimeException('Completa el dato obligatorio del medio: ' . $field . '.');
                $custom[$field] = trim((string) $value);
            }
            foreach ($custom as $key=>$value) {
                if (!preg_match('/^[a-z][a-z0-9_]{0,49}$/D', (string) $key) || !is_scalar($value) || mb_strlen((string) $value) > 500) throw new RuntimeException('Dato adicional del medio inválido.');
            }
            $type = $method['type'] === 'wallet' ? 'qr' : $method['type'];
            $fee = $collection ? ['rate'=>0,'amount'=>0,'total'=>$base/100] : PaymentSurcharge::calculate($base/100, (float) ($method['percentage'] ?? 0));
            $row = $integrity->parse([array_merge($line, ['payment_method'=>$type, 'amount'=>number_format($fee['total'], 2, '.', '')])], false)[0];
            $pending = $type === 'transfer' || !empty($method['requires_confirmation']);
            $received = isset($line['received_amount']) ? $integrity->cents($line['received_amount']) : (int) round($fee['total']*100);
            if ($received < (int) round($fee['total']*100)) throw new RuntimeException('El importe entregado no cubre el pago.');
            $row = array_merge($row, ['payment_method_id'=>$method['id'], 'payment_method_code'=>$method['code'],
                'payment_method_name'=>$method['name'], 'payment_method_snapshot'=>json_encode($method, JSON_THROW_ON_ERROR),
                'payment_data'=>json_encode($custom, JSON_THROW_ON_ERROR), 'base_amount'=>$base/100,
                'surcharge_rate'=>$fee['rate'], 'surcharge_amount'=>$fee['amount'], 'received_amount'=>$received/100,
                'change_amount'=>max(0, round($received/100-$fee['total'],2)), 'status'=>$pending?'pending':'registered']);
            if ($savedId) $row['id'] = $savedId;
            $rows[] = $row;
        }
        $integrity->validateReferences($companyId, $rows);
        return $rows;
    }

    public static function base(array $payment): float
    {
        return (float) ($payment['base_amount'] ?? 0) > 0 ? (float) $payment['base_amount'] : max(0, round((float) $payment['amount']-(float)($payment['surcharge_amount'] ?? 0),2));
    }

    private function unchanged(array $old, array $input, int $base, string $methodId): bool
    {
        if ($methodId !== (string) ($old['payment_method_id'] ?? '') || $base !== (int) round(self::base($old)*100)) return false;
        foreach (['reference','external_reference','gateway_id','cash_check_id','notes'] as $field) {
            if ((string) ($input[$field] ?? '') !== (string) ($old[$field] ?? '')) return false;
        }
        if (!empty($input['fields']) && self::list($old['payment_data'] ?? null) !== $input['fields']) return false;
        return empty($old['sales_receipt_id']);
    }

    /** Draft payments never affect the ledger; keep IDs so references and audit remain stable. */
    public function replaceDraft(string $companyId, string $saleId, array $rows): void
    {
        if (!db_connect()->table('sales')->where('company_id',$companyId)->where('id',$saleId)->where('status','draft')->countAllResults()) throw new RuntimeException('Borrador no disponible en esta empresa.');
        $model = new \App\Models\SalePaymentModel();
        $existing = array_column($model->where('sale_id', $saleId)->findAll(), null, 'id');
        foreach ($existing as $old) {
            if (!empty($old['sales_receipt_id'])) throw new RuntimeException('El borrador tiene cobranzas aplicadas; requiere revisión.');
        }
        $keep = [];
        foreach ($rows as $row) {
            $id = $row['id'] ?? null; unset($row['sale_id']);
            if ($id) {
                if (!isset($existing[$id])) throw new RuntimeException('Pago ajeno al borrador.');
                $keep[] = $id;
                if (!$model->update($id, $row)) throw new RuntimeException('No se pudo conservar el pago.');
            } else {
                $id = $model->insert($row+['sale_id'=>$saleId],true);
                if (!$id) throw new RuntimeException('No se pudo guardar el pago.');
                $keep[] = $id;
            }
        }
        foreach ($existing as $id=>$row) if (!in_array($id,$keep,true)) $model->delete($id);
    }

    public function validatePersisted(string $companyId, array $sale, array $payments): void
    {
        $context = null;
        foreach ($payments as $payment) {
            if (empty($payment['payment_method_id'])) continue; // Explicit legacy types remain legacy.
            $context ??= $this->context($companyId,$sale['currency_code'],$sale['branch_id'] ?? null,$sale['point_of_sale_id'] ?? null);
            $method = (new CompanyPaymentMethodModel())->where('company_id',$companyId)->where('active',1)->find($payment['payment_method_id']);
            if (!$method || !self::eligible($method,$context)) throw new RuntimeException('Un medio guardado ya no está habilitado. Revisa los pagos antes de confirmar.');
        }
    }
}
