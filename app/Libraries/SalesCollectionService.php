<?php
namespace App\Libraries;

use App\Models\SalesReceiptModel;
use App\Models\SalesReceiptItemModel;
use App\Models\SalePaymentModel;
use RuntimeException;

class SalesCollectionService
{
    private $accounting;
    private $cash;
    public function __construct(?AccountingService $accounting = null, ?CashService $cash = null)
    {
        $this->accounting = $accounting ?? new AccountingService();
        $this->cash = $cash ?? new CashService();
    }

    private function transaction(string $companyId, callable $operation)
    {
        $db = db_connect(); $depth = $db->transDepth;
        $db->transBegin();
        try {
            $this->lock('companies', $companyId, $companyId, false);
            $result = $operation();
            if (! $db->transStatus() || $db->transDepth !== $depth + 1) { throw new RuntimeException('No se pudo completar la cobranza.'); }
            if (! $db->transCommit()) { throw new RuntimeException('No se pudo confirmar la cobranza.'); }
            return $result;
        } catch (\Throwable $e) {
            while ($db->transDepth > $depth) { if (! $db->transRollback()) { break; } }
            throw $e;
        }
    }

    private function lock(string $table, string $id, string $companyId, bool $scoped = true): array
    {
        $db = db_connect();
        $sql = 'SELECT * FROM ' . $db->protectIdentifiers($db->prefixTable($table)) . ' WHERE id = ?' . ($scoped ? ' AND company_id = ?' : '');
        $row = $db->query($sql . ($db->DBDriver === 'SQLite3' ? '' : ' FOR UPDATE'), $scoped ? [$id, $companyId] : [$id])->getRowArray();
        if (! $row) { throw new RuntimeException('Registro no disponible en esta empresa.'); }
        return $row;
    }

    private function update(string $table, string $id, array $data): void
    {
        if (! db_connect()->table($table)->where('id', $id)->update($data)) { throw new RuntimeException('No se pudo actualizar el cobro.'); }
    }

    /** Normalize before any writes; reject duplicate destinations instead of silently clipping. */
    public function applications(string $companyId, array $items, string $currency): array
    {
        $seen = []; $rows = []; $validator = new PaymentIntegrityService();
        foreach ($items as $item) {
            $amount = $validator->cents($item['applied_amount'] ?? 0);
            if (! $amount) { continue; }
            $id = (string) ($item['receivable_id'] ?? $item['sales_receivable_id'] ?? '');
            if (isset($seen[$id])) { throw new RuntimeException('No se puede repetir un comprobante en el recibo.'); }
            $seen[$id] = true;
            $rows[$id] = ['receivable_id' => $id, 'applied_amount' => $amount / 100];
        }
        if (! $rows) { throw new RuntimeException('Selecciona al menos un comprobante y un importe.'); }
        ksort($rows);
        $customer = null;
        foreach ($rows as $id => &$row) {
            // Sales are locked first, matching the order used by sale transitions.
            $initial = db_connect()->table('sales_receivables')->where('id', $id)->where('company_id', $companyId)->get()->getRowArray();
            if (! $initial) { throw new RuntimeException('Cuenta por cobrar no disponible.'); }
            $sale = $this->lock('sales', $initial['sale_id'], $companyId);
            $receivable = $this->lock('sales_receivables', $id, $companyId);
            if (($sale['currency_code'] ?? '') !== $currency) { throw new RuntimeException('No se pueden aplicar cobros entre monedas distintas.'); }
            if (! in_array($sale['status'], ['confirmed', 'returned_partial'], true) || ! in_array($receivable['status'], ['pending', 'partial'], true)) { throw new RuntimeException('El comprobante no admite cobros.'); }
            if ($row['applied_amount'] > (float) $receivable['balance_amount']) { throw new RuntimeException('El importe supera el saldo pendiente del comprobante.'); }
            if (empty($receivable['customer_id']) || ($customer !== null && $customer !== $receivable['customer_id'])) { throw new RuntimeException('Los comprobantes deben pertenecer al mismo cliente.'); }
            $customer = $receivable['customer_id'];
            $row += ['sale_id' => $sale['id'], 'customer_id' => $customer, 'document_number' => $receivable['document_number']];
        }
        unset($row);
        return array_values($rows);
    }

    public function create(string $companyId, string $userId, array $payload, callable $number): array
    {
        return $this->transaction($companyId, function () use ($companyId, $userId, $payload, $number) {
            $company = db_connect()->table('companies')->where('id', $companyId)->get()->getRowArray();
            $currency = trim((string) ($payload['currency_code'] ?? $company['currency_code']));
            if ($currency !== $company['currency_code']) { throw new RuntimeException('Este flujo admite cobros en la moneda base; no aplica conversiones implícitas.'); }
            $rows = $this->applications($companyId, (array) ($payload['items'] ?? []), $currency);
            $total = round(array_sum(array_column($rows, 'applied_amount')), 2);
            $validator = new PaymentIntegrityService();
            $payments = $validator->parse((array) ($payload['payments'] ?? [array_merge($payload, ['amount' => $total])]));
            $validator->validateReferences($companyId, $payments);
            if ($validator->cents($total) !== array_sum(array_map(fn($p) => $validator->cents($p['amount']), $payments))) { throw new RuntimeException('El desglose de medios debe coincidir con el importe aplicado.'); }
            foreach ($payments as &$p) { $p['id'] = app_uuid(); }
            unset($p);
            $pending = count(array_filter($payments, static fn($p) => $p['status'] === 'pending')) > 0;
            $date = trim((string) ($payload['issue_date'] ?? '')) ?: date('Y-m-d H:i:s');
            if (strtotime($date) === false) { throw new RuntimeException('Fecha de recibo inválida.'); }
            $receipt = ['company_id' => $companyId, 'customer_id' => $rows[0]['customer_id'], 'receipt_number' => $number(),
                'issue_date' => date('Y-m-d H:i:s', strtotime($date)), 'currency_code' => $currency,
                'payment_method' => count($payments) > 1 ? 'mixed' : $payments[0]['payment_method'],
                'payment_details' => json_encode($payments, JSON_THROW_ON_ERROR), 'total_amount' => $total,
                'reference' => trim((string) ($payload['reference'] ?? '')), 'notes' => trim((string) ($payload['notes'] ?? '')),
                'status' => 'pending', 'created_by' => $userId];
            $id = (new SalesReceiptModel())->insert($receipt, true);
            if (! $id) { throw new RuntimeException('No se pudo guardar el recibo.'); }
            foreach ($rows as $row) {
                if (! (new SalesReceiptItemModel())->insert(['sales_receipt_id' => $id, 'sales_receivable_id' => $row['receivable_id'], 'sale_id' => $row['sale_id'], 'document_number' => $row['document_number'], 'applied_amount' => $row['applied_amount']])) { throw new RuntimeException('No se pudo guardar la aplicación.'); }
            }
            if (! $pending) { $this->apply($companyId, $id, $userId, 'Confirmación de medios inmediatos'); }
            return (new SalesReceiptModel())->find($id);
        });
    }

    public function confirm(string $companyId, string $id, string $userId, string $note): bool
    {
        if (trim($note) === '') { throw new RuntimeException('Indica la evidencia de verificación de la transferencia.'); }
        return $this->transaction($companyId, function () use ($companyId, $id, $userId, $note) { return $this->apply($companyId, $id, $userId, $note); });
    }

    private function session(string $companyId, ?string $registerId = null): array
    {
        $query = db_connect()->table('cash_sessions')->where('company_id', $companyId)->where('status', 'open');
        if ($registerId) { $query->where('cash_register_id', $registerId); }
        else {
            $active = $this->cash->activeSessionForChannel($companyId, 'general');
            if (! $active) { throw new RuntimeException('Se requiere una sesión abierta para registrar fondos.'); }
            $query->where('id', $active['id']);
        }
        $candidate = $query->get()->getRowArray();
        if (! $candidate) { throw new RuntimeException('Abre una sesión de la caja original para registrar el reverso.'); }
        $session = $this->lock('cash_sessions', $candidate['id'], $companyId);
        if ($session['status'] !== 'open') { throw new RuntimeException('La sesión ya no está abierta.'); }
        return $session;
    }

    private function movement(array $session, array $payment, string $type, string $referenceId, string $userId, float $sign = 1): void
    {
        $result = $this->cash->registerMovement(['company_id' => $session['company_id'], 'cash_session_id' => $session['id'], 'cash_register_id' => $session['cash_register_id'],
            'movement_type' => $sign > 0 ? 'customer_receipt' : 'receipt_reversal', 'payment_method' => $payment['payment_method'],
            'gateway_id' => $payment['gateway_id'] ?? null, 'cash_check_id' => $payment['cash_check_id'] ?? null,
            'external_reference' => $payment['external_reference'] ?? null, 'amount' => $sign * (float) $payment['amount'],
            'reference_type' => $type, 'reference_id' => $referenceId, 'created_by' => $userId]);
        if (! $result) { throw new RuntimeException('No se pudo registrar el movimiento de fondos.'); }
    }

    private function apply(string $companyId, string $id, string $userId, string $note): bool
    {
        $receipt = $this->lock('sales_receipts', $id, $companyId);
        if ($receipt['status'] === 'applied') { return false; }
        if ($receipt['status'] !== 'pending') { throw new RuntimeException('El recibo no está pendiente de confirmación.'); }
        $items = db_connect()->table('sales_receipt_items')->where('sales_receipt_id', $id)->get()->getResultArray();
        $rows = $this->applications($companyId, $items, $receipt['currency_code']);
        $payments = json_decode($receipt['payment_details'], true, 512, JSON_THROW_ON_ERROR);
        (new PaymentIntegrityService())->validateReferences($companyId, $payments);
        $session = $this->session($companyId);
        $remaining = array_map(static fn($p) => (int) round($p['amount'] * 100), $payments);
        foreach ($rows as $row) {
            $amount = (int) round($row['applied_amount'] * 100);
            foreach ($payments as $i => $payment) {
                $part = min($amount, $remaining[$i]);
                if (! $part) { continue; }
                unset($payment['id']);
                $payment['status'] = 'confirmed'; $payment['amount'] = $part / 100;
                $payment['reference'] = $receipt['receipt_number']; $payment['paid_at'] = date('Y-m-d H:i:s');
                $payment['sale_id'] = $row['sale_id']; $payment['sales_receipt_id'] = $id;
                if (! (new SalePaymentModel())->insert($payment)) { throw new RuntimeException('No se pudo aplicar el pago.'); }
                $amount -= $part; $remaining[$i] -= $part;
            }
            if ($amount !== 0) { throw new RuntimeException('Desglose insuficiente para aplicar el recibo.'); }
            $this->refresh($row['sale_id']);
        }
        foreach ($payments as &$payment) {
            $this->movement($session, $payment, 'sales_receipt_line', $payment['id'], $userId);
            $payment['status'] = 'confirmed';
        }
        unset($payment);
        $this->update('sales_receipts', $id, ['status' => 'applied', 'cash_session_id' => $session['id'], 'cash_register_id' => $session['cash_register_id'],
            'payment_details' => json_encode($payments, JSON_THROW_ON_ERROR), 'confirmed_by' => $userId, 'confirmed_at' => date('Y-m-d H:i:s'), 'confirmation_note' => $note]);
        $this->accountingResult($this->accounting->syncSalesReceipt($companyId, $id, $userId));
        return true;
    }

    public function refresh(string $saleId): void
    {
        $db = db_connect();
        $sale = $db->table('sales')->where('id', $saleId)->get()->getRowArray();
        $paid = PaymentIntegrityService::paid((new SalePaymentModel())->where('sale_id', $saleId)->findAll());
        $this->update('sales', $saleId, ['paid_total' => $paid, 'payment_status' => $paid <= 0 ? 'pending' : ($paid < (float) $sale['total'] ? 'partial' : 'paid')]);
        $receivable = $db->table('sales_receivables')->where('sale_id', $saleId)->get()->getRowArray();
        if ($receivable) {
            $balance = max(0, round((float) $receivable['total_amount'] - $paid, 2));
            $this->update('sales_receivables', $receivable['id'], ['paid_amount' => $paid, 'balance_amount' => $balance,
                'status' => $receivable['status'] === 'cancelled' ? 'cancelled' : ($balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'pending'))]);
        }
    }

    public function reverse(string $companyId, string $id, string $userId, string $reason): void
    {
        if (trim($reason) === '') { throw new RuntimeException('Indica el motivo de anulación.'); }
        $this->transaction($companyId, function () use ($companyId, $id, $userId, $reason) {
            $receipt = $this->lock('sales_receipts', $id, $companyId);
            if ($receipt['status'] === 'voided') { return; }
            if (! in_array($receipt['status'], ['pending', 'applied'], true)) { throw new RuntimeException('Estado de recibo incompatible.'); }
            $db = db_connect();
            $items = $db->table('sales_receipt_items')->where('sales_receipt_id', $id)->orderBy('sale_id')->get()->getResultArray();
            foreach ($items as $item) {
                $this->lock('sales', $item['sale_id'], $companyId);
                $this->lock('sales_receivables', $item['sales_receivable_id'], $companyId);
            }
            if ($receipt['status'] === 'applied') {
                $details = json_decode($receipt['payment_details'] ?? 'null', true) ?: [];
                $query = $db->table('cash_movements')->where('company_id', $companyId)->groupStart()
                    ->groupStart()->where('reference_type', 'sales_receipt')->where('reference_id', $id)->groupEnd();
                if ($details) { $query->orGroupStart()->where('reference_type', 'sales_receipt_line')->whereIn('reference_id', array_column($details, 'id'))->groupEnd(); }
                $movements = $query->groupEnd()->get()->getResultArray();
                foreach ($movements as $movement) {
                    $session = $this->session($companyId, $movement['cash_register_id']);
                    $this->movement($session, $movement, 'receipt_reversal', $movement['id'], $userId, -1);
                }
                $this->accountingResult($this->accounting->reverseSalesReceipt($companyId, $id, $userId, $reason));
                foreach ($items as $item) {
                    $q = $db->table('sale_payments')->where('sale_id', $item['sale_id']);
                    if ($details) { $q->where('sales_receipt_id', $id); }
                    else { $q->where('reference', $receipt['receipt_number']); }
                    if (! $q->update(['status' => 'reversed'])) { throw new RuntimeException('No se pudo revertir el pago.'); }
                    $this->refresh($item['sale_id']);
                }
            }
            $this->update('sales_receipts', $id, ['status' => 'voided', 'reversed_by' => $userId, 'reversed_at' => date('Y-m-d H:i:s'), 'reversal_reason' => $reason]);
        });
    }

    private function accountingResult(array $result): void
    {
        if (empty($result['ok'])) { throw new RuntimeException($result['error'] ?? 'No se pudo registrar la contabilidad del cobro.'); }
    }

    public function confirmSalePayment(string $companyId, string $saleId, string $paymentId, string $userId, string $evidence): void
    {
        if (trim($evidence) === '') { throw new RuntimeException('Indica la evidencia de la transferencia.'); }
        $this->transaction($companyId, function () use ($companyId, $saleId, $paymentId, $userId, $evidence) {
            $sale = $this->lock('sales', $saleId, $companyId);
            if (! in_array($sale['status'], ['confirmed', 'returned_partial'], true)) { throw new RuntimeException('Confirma primero la venta.'); }
            $payment = db_connect()->table('sale_payments')->where('id', $paymentId)->where('sale_id', $saleId)->get()->getRowArray();
            if (! $payment || ! empty($payment['sales_receipt_id'])) { throw new RuntimeException('Pago no disponible.'); }
            if ($payment['status'] === 'confirmed') { return; }
            if ($payment['status'] !== 'pending' || $payment['payment_method'] !== 'transfer') { throw new RuntimeException('El pago no está pendiente.'); }
            $paid = PaymentIntegrityService::paid((new SalePaymentModel())->where('sale_id', $saleId)->findAll());
            $items = db_connect()->table('sale_items')->where('sale_id', $saleId)->get()->getResultArray();
            $net = (new SalesIntegrityService())->netReceivableTotal($sale, $items);
            if (round($paid + (float) $payment['amount'], 2) > $net) { throw new RuntimeException('La transferencia supera el saldo actual.'); }
            (new PaymentIntegrityService())->validateReferences($companyId, [$payment]);
            $session = $this->session($companyId);
            $this->movement($session, $payment, 'sale_payment', $paymentId, $userId);
            $this->update('sale_payments', $paymentId, ['status' => 'confirmed', 'notes' => ($payment['notes'] ?? '') . '\nVerificado por ' . $userId . ': ' . $evidence, 'paid_at' => date('Y-m-d H:i:s')]);
            $this->refresh($saleId);
        });
    }
}
