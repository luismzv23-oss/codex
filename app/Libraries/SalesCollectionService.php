<?php
namespace App\Libraries;

use App\Models\SalesReceiptModel;
use App\Models\SalesReceiptItemModel;
use App\Models\SalePaymentModel;
use RuntimeException;

class SalesCollectionService
{
    private AccountingService $accounting;
    private CashService $cash;
    public function __construct(?AccountingService $accounting = null, ?CashService $cash = null)
    {
        $this->accounting = $accounting ?? new AccountingService();
        $this->cash = $cash ?? new CashService();
    }

    private function transaction(string $companyId, callable $operation)
    {
        $db = db_connect(); $depth = $db->transDepth;
        if (!$db->transBegin()) throw new RuntimeException('No se pudo iniciar la cobranza.');
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
            $this->refresh($sale['id']);
            $receivable = db_connect()->table('sales_receivables')->where('id', $id)->get()->getRowArray();
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
            $replay = $this->requestReplay($companyId, $payload, 'create');
            if ($replay !== null) {
                $saved = (new SalesReceiptModel())->where('company_id', $companyId)->find($replay['id']);
                if (!$saved) throw new RuntimeException('El recibo original ya no esta disponible.');
                return $saved + ['_replayed' => true];
            }
            $company = db_connect()->table('companies')->where('id', $companyId)->get()->getRowArray();
            $currency = trim((string) ($payload['currency_code'] ?? $company['currency_code']));
            if ($currency !== $company['currency_code']) { throw new RuntimeException('Este flujo admite cobros en la moneda base; no aplica conversiones implícitas.'); }
            $kind = (string) ($payload['receipt_kind'] ?? 'invoice');
            if (!in_array($kind, ['invoice', 'advance'], true)) throw new RuntimeException('Tipo de recibo invalido.');
            $rows = $kind === 'invoice' ? $this->applications($companyId, (array) ($payload['items'] ?? []), $currency) : [];
            $customerId = $rows[0]['customer_id'] ?? (string) ($payload['customer_id'] ?? '');
            if ($kind === 'advance' && !db_connect()->table('customers')->where('company_id', $companyId)->where('id', $customerId)->where('active', 1)->countAllResults()) {
                throw new RuntimeException('Selecciona un cliente activo de esta empresa para el anticipo.');
            }
            if ($kind === 'advance' && array_filter((array) ($payload['items'] ?? []), static fn($item) => (float) ($item['applied_amount'] ?? 0) != 0)) throw new RuntimeException('El anticipo se aplica a facturas despues de registrarlo.');
            $total = round(array_sum(array_column($rows, 'applied_amount')), 2);
            $validator = new PaymentIntegrityService();
            $payments = $validator->parse((array) ($payload['payments'] ?? [array_merge($payload, ['amount' => $total])]));
            $validator->validateReferences($companyId, $payments);
            if (!$payments) throw new RuntimeException('Indica un importe de cobro.');
            if ($kind === 'advance') $total = round(array_sum(array_column($payments, 'amount')), 2);
            if ($validator->cents($total) !== array_sum(array_map(fn($p) => $validator->cents($p['amount']), $payments))) { throw new RuntimeException('El desglose de medios debe coincidir con el importe aplicado.'); }
            foreach ($payments as &$p) { $p['id'] = app_uuid(); }
            unset($p);
            $date = trim((string) ($payload['issue_date'] ?? '')) ?: date('Y-m-d H:i:s');
            if (strtotime($date) === false) { throw new RuntimeException('Fecha de recibo inválida.'); }
            $receipt = ['processing_version' => 2, 'receipt_kind' => $kind, 'received_amount' => 0, 'company_id' => $companyId, 'customer_id' => $customerId, 'receipt_number' => $number(),
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
            $this->apply($companyId, $id, $userId, 'Registro de medios inmediatos', false);
            $result = (new SalesReceiptModel())->find($id);
            $this->rememberRequest($companyId, $payload, 'create', $result);
            return $result;
        });
    }

    public function confirm(string $companyId, string $id, string $userId, string $note, ?string $paymentId = null): bool
    {
        if (trim($note) === '') { throw new RuntimeException('Indica la evidencia de verificación de la transferencia.'); }
        return $this->transaction($companyId, function () use ($companyId, $id, $userId, $note, $paymentId) { return $this->apply($companyId, $id, $userId, $note, true, $paymentId); });
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
        if (! $candidate || !$this->cash->ownedSession($companyId, $candidate['id'])) { throw new RuntimeException('Abre una sesión de la caja original para registrar el reverso.'); }
        $session = $this->lock('cash_sessions', $candidate['id'], $companyId);
        if ($session['status'] !== 'open') { throw new RuntimeException('La sesión ya no está abierta.'); }
        return $session;
    }

    private function movement(array $session, array $payment, string $type, string $referenceId, string $userId, float $sign = 1): void
    {
        $result = $this->cash->registerMovement(['company_id' => $session['company_id'], 'cash_session_id' => $session['id'], 'cash_register_id' => $session['cash_register_id'],
            'movement_type' => $sign > 0 ? 'customer_receipt' : ($type === 'advance_refund' ? 'customer_refund' : 'receipt_reversal'), 'payment_method' => $payment['payment_method'],
            'gateway_id' => $payment['gateway_id'] ?? null, 'cash_check_id' => $payment['cash_check_id'] ?? null,
            'external_reference' => isset($payment['external_reference']) ? mb_substr($payment['external_reference'], 0, 120) : null, 'amount' => $sign * (float) $payment['amount'],
            'notes' => $payment['external_reference'] ?? null,
            'reference_type' => $type, 'reference_id' => $referenceId, 'created_by' => $userId]);
        if (! $result) { throw new RuntimeException('No se pudo registrar el movimiento de fondos.'); }
    }

    private function requestReplay(string $companyId, array $payload, string $operation): ?array
    {
        $key = (string) ($payload['request_key'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_-]{16,80}$/D', $key)) throw new RuntimeException('Falta un identificador valido de operacion. Actualiza el formulario.');
        $hash = $this->requestHash($payload, $operation);
        $row = db_connect()->table('collection_requests')->where('company_id', $companyId)->where('request_key', $key)->get()->getRowArray();
        if (!$row) return null;
        if (!hash_equals($row['payload_hash'], $hash)) throw new RuntimeException('El identificador ya se utilizo con otros datos.');
        return json_decode($row['result_json'], true, 512, JSON_THROW_ON_ERROR);
    }

    public function configureAccounts(string $companyId, array $mapping): void
    {
        $this->transaction($companyId, function () use ($companyId, $mapping) {
            $seen = [];
            foreach (CollectionAccountingService::ACCOUNTS as $key => $label) {
                $id = (string) ($mapping[$key] ?? '');
                $account = db_connect()->table('accounts')->where('id', $id)->where('company_id', $companyId)->where('active', 1)->where('is_group', 0)->where('accepts_entries', 1)->get()->getRowArray();
                if (!$account || $account['account_type'] !== ($key === 'customer_advances' ? 'liability' : 'asset')) throw new RuntimeException('Selecciona una cuenta de ' . ($key === 'customer_advances' ? 'pasivo' : 'activo') . ' para ' . $label . '.');
                if (isset($seen[$id])) throw new RuntimeException('Cada concepto debe tener una cuenta diferente.');
                $seen[$id] = true;
                $query = db_connect()->table('company_settings')->where('company_id', $companyId)->where('key', 'account_' . $key);
                $existing = $query->get()->getRowArray();
                if ($existing && $existing['value'] !== $id) {
                    $balance = db_connect()->table('journal_entry_lines l')->select('SUM(l.debit - l.credit) AS balance', false)->join('journal_entries e', 'e.id=l.journal_entry_id')->where('e.company_id', $companyId)->where('e.status', 'posted')->where('l.account_id', $existing['value'])->get()->getRowArray();
                    if (round((float) ($balance['balance'] ?? 0), 2) !== 0.0) throw new RuntimeException('La cuenta de ' . $label . ' tiene saldo. Regulariza su saldo antes de cambiarla.');
                }
                if ($existing) $this->update('company_settings', $existing['id'], ['value' => $id]);
                elseif (!db_connect()->table('company_settings')->insert(['id' => app_uuid(), 'company_id' => $companyId, 'key' => 'account_' . $key, 'value' => $id])) throw new RuntimeException('No se pudo guardar la cuenta.');
            }
        });
    }

    private function requestHash(array $payload, string $operation): string
    {
        unset($payload[config('Security')->tokenName], $payload['popup']);
        ksort($payload);
        return hash('sha256', $operation . json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function rememberRequest(string $companyId, array $payload, string $operation, array $result): void
    {
        if (!db_connect()->table('collection_requests')->insert([
            'id' => app_uuid(), 'company_id' => $companyId, 'request_key' => $payload['request_key'],
            'payload_hash' => $this->requestHash($payload, $operation), 'result_json' => json_encode($result, JSON_THROW_ON_ERROR),
        ])) throw new RuntimeException('No se pudo registrar la operacion.');
    }

    private function apply(string $companyId, string $id, string $userId, string $note, bool $confirmPending = true, ?string $paymentId = null): bool
    {
        $receipt = $this->lock('sales_receipts', $id, $companyId);
        if ($receipt['status'] === 'applied') return false;
        if (!in_array($receipt['status'], ['pending', 'partial'], true)) throw new RuntimeException('El recibo no esta pendiente.');
        $payments = json_decode($receipt['payment_details'] ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        if ((int) ($receipt['processing_version'] ?? 1) < 2) {
            if (!$payments) throw new RuntimeException('El recibo historico no tiene desglose verificable. Revisa su origen antes de confirmarlo.');
            $this->update('sales_receipts', $id, ['processing_version' => 2]);
        }
        if ($paymentId !== null && !in_array($paymentId, array_column($payments, 'id'), true)) throw new RuntimeException('Medio no encontrado en el recibo.');
        $changed = false;
        foreach ($payments as &$payment) {
            if ($paymentId !== null && $paymentId !== $payment['id']) continue;
            if (!empty($payment['posted_at']) || (!$confirmPending && $payment['status'] === 'pending')) continue;
            (new PaymentIntegrityService())->validateReferences($companyId, [$payment]);
            $session = $this->session($companyId);
            if ($receipt['receipt_kind'] === 'invoice') $this->allocatePayment($companyId, $receipt, $payment);
            $this->movement($session, $payment, 'sales_receipt_line', $payment['id'], $userId);
            (new CollectionAccountingService())->post($companyId, 'receipt_line', $id, $userId,
                CollectionAccountingService::paymentAccount($payment['payment_method']),
                $receipt['receipt_kind'] === 'advance' ? 'customer_advances' : 'receivable',
                (float) $payment['amount'], 'Cobro ' . $receipt['receipt_number']);
            $payment['ledger_account_id'] = (new CollectionAccountingService())->account($companyId, CollectionAccountingService::paymentAccount($payment['payment_method']));
            $payment['status'] = 'confirmed';
            $payment['posted_at'] = date('Y-m-d H:i:s');
            $payment['confirmed_by'] = $userId;
            $payment['confirmation_note'] = $note;
            $receipt['cash_session_id'] = $session['id'];
            $receipt['cash_register_id'] = $session['cash_register_id'];
            $changed = true;
        }
        unset($payment);
        if (!$changed) return false;
        $received = round(array_sum(array_map(static fn($p) => !empty($p['posted_at']) ? (float) $p['amount'] : 0.0, $payments)), 2);
        $this->update('sales_receipts', $id, [
            'status' => $received >= (float) $receipt['total_amount'] ? 'applied' : 'partial',
            'received_amount' => $received, 'payment_details' => json_encode($payments, JSON_THROW_ON_ERROR),
            'cash_session_id' => $receipt['cash_session_id'], 'cash_register_id' => $receipt['cash_register_id'],
            'confirmed_by' => $userId, 'confirmed_at' => date('Y-m-d H:i:s'), 'confirmation_note' => $note,
        ]);
        return true;
    }

    private function allocatePayment(string $companyId, array $receipt, array $payment): void
    {
        $validator = new PaymentIntegrityService();
        $left = $validator->cents($payment['amount']);
        $items = db_connect()->table('sales_receipt_items')->where('sales_receipt_id', $receipt['id'])->orderBy('sale_id')->get()->getResultArray();
        foreach ($items as $item) {
            $part = min($left, $validator->cents($item['applied_amount']) - $validator->cents($item['settled_amount'] ?? 0));
            if ($part <= 0) continue;
            $row = $this->applications($companyId, [['receivable_id' => $item['sales_receivable_id'], 'applied_amount' => $part / 100]], $receipt['currency_code'])[0];
            if ($row['customer_id'] !== $receipt['customer_id']) throw new RuntimeException('El cliente del comprobante ha cambiado.');
            $this->insertAppliedPayment($receipt, $row, $payment['payment_method'], $part / 100, $payment);
            $this->update('sales_receipt_items', $item['id'], ['settled_amount' => round((float) $item['settled_amount'] + $part / 100, 2)]);
            $this->refresh($row['sale_id']);
            $left -= $part;
            if ($left === 0) break;
        }
        if ($left !== 0) throw new RuntimeException('El cobro supera las aplicaciones pendientes.');
    }

    private function insertAppliedPayment(array $receipt, array $row, string $method, float $amount, array $metadata = []): void
    {
        unset($metadata['id']);
        $data = array_merge($metadata, [
            'sale_id' => $row['sale_id'], 'sales_receipt_id' => $receipt['id'], 'payment_method' => $method,
            'amount' => $amount, 'status' => 'confirmed', 'reference' => $receipt['receipt_number'], 'paid_at' => date('Y-m-d H:i:s'),
        ]);
        if (!(new SalePaymentModel())->insert($data)) throw new RuntimeException('No se pudo aplicar el pago.');
    }

    public function availableAdvance(array $receipt): float
    {
        if (!in_array($receipt['receipt_kind'] ?? '', ['advance', 'return_credit'], true) || $receipt['status'] === 'voided') return 0;
        $row = db_connect()->table('sales_receipt_items')->where('sales_receipt_id', $receipt['id'])->selectSum('settled_amount')->get()->getRowArray();
        return max(0, round((float) $receipt['received_amount'] - (float) ($receipt['refunded_amount'] ?? 0) - (float) ($row['settled_amount'] ?? 0), 2));
    }

    public function applyAdvance(string $companyId, string $id, string $userId, array $payload): array
    {
        return $this->transaction($companyId, function () use ($companyId, $id, $userId, $payload) {
            $operation = 'advance:' . $id;
            $replay = $this->requestReplay($companyId, $payload, $operation);
            if ($replay !== null) return $replay;
            $receipt = $this->lock('sales_receipts', $id, $companyId);
            if (!in_array($receipt['receipt_kind'] ?? '', ['advance', 'return_credit'], true) || !in_array($receipt['status'], ['applied', 'partial'], true)) throw new RuntimeException('Anticipo no disponible.');
            $rows = $this->applications($companyId, (array) ($payload['items'] ?? []), $receipt['currency_code']);
            $amount = round(array_sum(array_column($rows, 'applied_amount')), 2);
            if ($amount > $this->availableAdvance($receipt)) throw new RuntimeException('El importe supera el saldo a favor disponible.');
            foreach ($rows as $row) {
                if ($row['customer_id'] !== $receipt['customer_id']) throw new RuntimeException('El anticipo pertenece a otro cliente.');
                if (!(new SalesReceiptItemModel())->insert([
                    'sales_receipt_id' => $id, 'sales_receivable_id' => $row['receivable_id'], 'sale_id' => $row['sale_id'],
                    'document_number' => $row['document_number'], 'applied_amount' => $row['applied_amount'], 'settled_amount' => $row['applied_amount'],
                ])) throw new RuntimeException('No se pudo aplicar el anticipo.');
                $this->insertAppliedPayment($receipt, $row, 'credit', $row['applied_amount']);
                $this->refresh($row['sale_id']);
            }
            (new CollectionAccountingService())->post($companyId, 'advance_application', $id, $userId, 'customer_advances', 'receivable', $amount, 'Aplicacion de anticipo ' . $receipt['receipt_number']);
            $result = ['receipt_id' => $id, 'available_amount' => $this->availableAdvance($receipt)];
            $this->rememberRequest($companyId, $payload, $operation, $result);
            return $result;
        });
    }

    public function settleReceiptPayment(string $companyId, string $id, string $paymentId, string $userId, string $evidence): void
    {
        if (trim($evidence) === '') throw new RuntimeException('Indica la referencia de acreditacion bancaria.');
        $this->transaction($companyId, function () use ($companyId, $id, $paymentId, $userId, $evidence) {
            $receipt = $this->lock('sales_receipts', $id, $companyId);
            if ($receipt['status'] === 'voided' || (int) $receipt['processing_version'] < 2) throw new RuntimeException('Recibo no disponible para acreditar.');
            $payments = json_decode($receipt['payment_details'], true, 512, JSON_THROW_ON_ERROR);
            foreach ($payments as &$payment) {
                if ($payment['id'] !== $paymentId) continue;
                if (!empty($payment['bank_settled_at'])) return;
                if (empty($payment['posted_at']) || !in_array($payment['payment_method'], ['card', 'check', 'qr'], true)) throw new RuntimeException('Medio no disponible para acreditar.');
                if (($payment['ledger_account_id'] ?? '') !== (new CollectionAccountingService())->account($companyId, CollectionAccountingService::paymentAccount($payment['payment_method']))) throw new RuntimeException('La cuenta del medio cambio desde el cobro. Revisa su configuracion.');
                (new CollectionAccountingService())->post($companyId, 'receipt_settlement', $id, $userId, 'bank', CollectionAccountingService::paymentAccount($payment['payment_method']), (float) $payment['amount'], 'Acreditacion: ' . $evidence);
                $payment['bank_settled_at'] = date('Y-m-d H:i:s');
                $payment['bank_settled_by'] = $userId;
                $payment['bank_reference'] = $evidence;
                $this->update('sales_receipts', $id, ['payment_details' => json_encode($payments, JSON_THROW_ON_ERROR)]);
                return;
            }
            throw new RuntimeException('Medio no encontrado en el recibo.');
        });
    }

    public function settleSalePayment(string $companyId, string $saleId, string $paymentId, string $userId, string $evidence): void
    {
        if (trim($evidence) === '') throw new RuntimeException('Indica la referencia bancaria.');
        $this->transaction($companyId, function () use ($companyId, $saleId, $paymentId, $userId, $evidence) {
            $sale = $this->lock('sales', $saleId, $companyId);
            $payment = db_connect()->table('sale_payments')->where('id', $paymentId)->where('sale_id', $saleId)->get()->getRowArray();
            if (!$payment || !empty($payment['sales_receipt_id']) || !PaymentIntegrityService::settled($payment)
                || !in_array($payment['payment_method'], ['card', 'check', 'qr'], true) || $sale['status'] === 'cancelled') throw new RuntimeException('Cobro no disponible.');
            if (db_connect()->table('journal_entries')->where('company_id', $companyId)->where('reference_type', 'payment_settlement')->where('reference_id', $paymentId)->countAllResults()) return;
            $ledger = new CollectionAccountingService();
            $key = CollectionAccountingService::paymentAccount($payment['payment_method']);
            $account = $ledger->account($companyId, $key);
            $debit = db_connect()->table('journal_entry_lines l')->selectSum('l.debit', 'amount')->join('journal_entries e', 'e.id=l.journal_entry_id')
                ->where('e.company_id', $companyId)->where('e.reference_type', 'sale_payment')->where('e.reference_id', $paymentId)->where('e.status', 'posted')->where('l.account_id', $account)->get()->getRowArray();
            if (round((float) ($debit['amount'] ?? 0), 2) !== round((float) $payment['amount'], 2)) throw new RuntimeException('El cobro no esta registrado en la cuenta pendiente de acreditacion. Revisa su asiento original.');
            $ledger->post($companyId, 'payment_settlement', $paymentId, $userId, 'bank', $key, (float) $payment['amount'], 'Acreditacion: ' . $evidence);
            $this->update('sale_payments', $paymentId, ['notes' => ($payment['notes'] ?? '') . "\nAcreditado por " . $userId . ': ' . $evidence]);
        });
    }

    public function refundAdvance(string $companyId, string $id, string $userId, array $payload): array
    {
        return $this->transaction($companyId, function () use ($companyId, $id, $userId, $payload) {
            $operation = 'refund:' . $id;
            $replay = $this->requestReplay($companyId, $payload, $operation);
            if ($replay !== null) return $replay;
            $receipt = $this->lock('sales_receipts', $id, $companyId);
            $amount = (new PaymentIntegrityService())->cents($payload['amount'] ?? 0) / 100;
            if ($amount <= 0 || $amount > $this->availableAdvance($receipt)) throw new RuntimeException('El reintegro supera el saldo disponible o es invalido.');
            $method = (string) ($payload['payment_method'] ?? '');
            $reason = trim((string) ($payload['reason'] ?? ''));
            if (!in_array($method, ['cash', 'transfer'], true) || $reason === '') throw new RuntimeException('Selecciona efectivo o transferencia e indica motivo y comprobante del reintegro.');
            $session = $this->session($companyId);
            $this->movement($session, ['payment_method' => $method, 'amount' => $amount, 'external_reference' => $reason], 'advance_refund', app_uuid(), $userId, -1);
            (new CollectionAccountingService())->post($companyId, 'advance_refund', $id, $userId, 'customer_advances', CollectionAccountingService::paymentAccount($method), $amount, 'Reintegro: ' . $reason);
            $this->update('sales_receipts', $id, ['refunded_amount' => round((float) $receipt['refunded_amount'] + $amount, 2)]);
            $result = ['receipt_id' => $id, 'refunded_amount' => $amount];
            $this->rememberRequest($companyId, $payload, $operation, $result);
            return $result;
        });
    }

    /** Move only the overpaid part of a confirmed return into reusable customer credit. */
    public function creditSaleReturn(string $companyId, string $saleId, string $returnId, string $userId): void
    {
        $this->transaction($companyId, function () use ($companyId, $saleId, $returnId, $userId) {
            $sale = $this->lock('sales', $saleId, $companyId);
            $return = db_connect()->table('sale_returns')->where('id', $returnId)->where('sale_id', $saleId)->where('status', 'confirmed')->get()->getRowArray();
            if (!$return) throw new RuntimeException('Devolucion no disponible.');
            if (db_connect()->table('sales_receipts')->where('company_id', $companyId)->where('receipt_kind', 'return_credit')->where('reference', $returnId)->countAllResults()) return;
            $net = (new SalesIntegrityService())->netReceivableTotal($sale, db_connect()->table('sale_items')->where('sale_id', $saleId)->get()->getResultArray());
            $paid = PaymentIntegrityService::paid((new SalePaymentModel())->where('sale_id', $saleId)->findAll());
            $credit = round($paid - $net, 2);
            if ($credit <= 0) return;
            if (empty($sale['customer_id']) || !db_connect()->table('customers')->where('company_id', $companyId)->where('id', $sale['customer_id'])->countAllResults()) throw new RuntimeException('Asigna un cliente de esta empresa para conservar el saldo a favor de la devolucion.');
            $company = db_connect()->table('companies')->where('id', $companyId)->get()->getRowArray();
            if ($sale['currency_code'] !== $company['currency_code']) throw new RuntimeException('El saldo a favor requiere la moneda base de la empresa.');
            $receipt = [
                'company_id' => $companyId, 'customer_id' => $sale['customer_id'], 'receipt_number' => 'SF-' . $returnId,
                'issue_date' => date('Y-m-d H:i:s'), 'currency_code' => $sale['currency_code'], 'receipt_kind' => 'return_credit',
                'processing_version' => 2, 'received_amount' => $credit, 'total_amount' => $credit, 'payment_method' => 'credit',
                'payment_details' => '[]', 'reference' => $returnId, 'status' => 'applied', 'created_by' => $userId,
            ];
            $id = (new SalesReceiptModel())->insert($receipt, true);
            if (!$id) throw new RuntimeException('No se pudo registrar el saldo a favor.');
            if (!(new SalePaymentModel())->insert(['sale_id' => $saleId, 'sales_receipt_id' => $id, 'payment_method' => 'credit_transfer', 'amount' => -$credit, 'status' => 'confirmed', 'reference' => $returnId, 'paid_at' => date('Y-m-d H:i:s')])) throw new RuntimeException('No se pudo trasladar el saldo a favor.');
            (new CollectionAccountingService())->post($companyId, 'return_credit', $id, $userId, 'receivable', 'customer_advances', $credit, 'Saldo a favor por devolucion ' . $returnId);
            $this->refresh($saleId);
        });
    }

    public function refresh(string $saleId): void
    {
        $db = db_connect();
        $sale = $db->table('sales')->where('id', $saleId)->get()->getRowArray();
        $paid = PaymentIntegrityService::paid((new SalePaymentModel())->where('sale_id', $saleId)->findAll());
        if (!$sale) throw new RuntimeException('Venta no disponible.');
        $net = ($sale['status'] ?? '') === 'cancelled' ? 0.0 : (new SalesIntegrityService())->netReceivableTotal($sale, $db->table('sale_items')->where('sale_id',$saleId)->get()->getResultArray());
        $this->update('sales', $saleId, ['paid_total' => $paid, 'payment_status' => $net <= $paid ? 'paid' : ($paid > 0 ? 'partial' : 'pending')]);
        $receivable = $db->table('sales_receivables')->where('sale_id', $saleId)->get()->getRowArray();
        if ($receivable) {
            $balance = max(0, round($net - $paid, 2));
            $this->update('sales_receivables', $receivable['id'], ['total_amount' => $net, 'paid_amount' => $paid, 'balance_amount' => $balance,
                'status' => $receivable['status'] === 'cancelled' ? 'cancelled' : ($balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'pending'))]);
        }
    }

    public function assertSaleCanCancel(string $companyId, string $saleId): void
    {
        $sale=db_connect()->table('sales')->where('company_id',$companyId)->where('id',$saleId)->get()->getRowArray();
        if (!$sale) throw new RuntimeException('Venta no disponible.');
        if (PaymentIntegrityService::paid((new SalePaymentModel())->where('sale_id',$saleId)->findAll()) > 0) {
            throw new RuntimeException('La venta tiene cobros aplicados. Anula los recibos correspondientes o utiliza el circuito de devoluciones antes de cancelar.');
        }
        $pending=db_connect()->table('sales_receipt_items i')->join('sales_receipts r','r.id=i.sales_receipt_id')->where('r.company_id',$companyId)->where('i.sale_id',$saleId)->whereIn('r.status',['pending', 'partial'])->countAllResults();
        if ($pending) throw new RuntimeException('Anula primero los recibos pendientes vinculados a esta venta.');
    }

    public function reverse(string $companyId, string $id, string $userId, string $reason): void
    {
        if (trim($reason) === '') { throw new RuntimeException('Indica el motivo de anulación.'); }
        $this->transaction($companyId, function () use ($companyId, $id, $userId, $reason) {
            $receipt = $this->lock('sales_receipts', $id, $companyId);
            if ($receipt['status'] === 'voided') { return; }
            if (($receipt['receipt_kind'] ?? '') === 'return_credit' || (float) ($receipt['refunded_amount'] ?? 0) > 0) throw new RuntimeException('Este saldo tiene origen en una devolucion o reintegros. Conserva su historial; aplica o reintegra el saldo disponible.');
            if (! in_array($receipt['status'], ['pending', 'partial', 'applied'], true)) { throw new RuntimeException('Estado de recibo incompatible.'); }
            $db = db_connect();
            $items = $db->table('sales_receipt_items')->where('sales_receipt_id', $id)->orderBy('sale_id')->get()->getResultArray();
            foreach ($items as $item) {
                $this->lock('sales', $item['sale_id'], $companyId);
                $this->lock('sales_receivables', $item['sales_receivable_id'], $companyId);
                if ($db->table('sale_payments')->where('sale_id', $item['sale_id'])->where('payment_method', 'credit_transfer')->where('status', 'confirmed')->countAllResults()) throw new RuntimeException('El cobro tiene una devolucion con saldo trasladado al cliente. Gestiona el reintegro desde ese saldo a favor.');
            }
            if ($receipt['status'] === 'applied' || (float) ($receipt['received_amount'] ?? 0) > 0) {
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
            $company = db_connect()->table('companies')->where('id',$companyId)->get()->getRowArray();
            if ($sale['currency_code'] !== $company['currency_code']) throw new RuntimeException('Este flujo admite cobros en la moneda base.');
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
            $this->accountingResult($this->accounting->syncSalePayment($companyId, $paymentId, $userId));
        });
    }
}
