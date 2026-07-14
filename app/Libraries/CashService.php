<?php

namespace App\Libraries;

use App\Libraries\AccountingService;
use App\Models\BranchModel;
use App\Models\CashCheckModel;
use App\Models\CashClosureModel;
use App\Models\CashMovementModel;
use App\Models\CashPaymentGatewayModel;
use App\Models\CashReconciliationModel;
use App\Models\CashRegisterModel;
use App\Models\CashSessionModel;

class CashService
{
    public function ensureDefaults(string $companyId, ?string $branchId = null): void
    {
        $registerModel = new CashRegisterModel();

        $branchId ??= (new BranchModel())->where('company_id', $companyId)->where('active', 1)->orderBy('code', 'ASC')->first()['id'] ?? null;
        $defaults = [
            ['name' => 'Caja Principal', 'code' => 'CAJA-GRAL', 'register_type' => 'general', 'is_default' => 1],
            ['name' => 'Caja POS', 'code' => 'CAJA-POS', 'register_type' => 'pos', 'is_default' => 0],
            ['name' => 'Caja Kiosco', 'code' => 'CAJA-KIOSCO', 'register_type' => 'kiosk', 'is_default' => 0],
        ];

        foreach ($defaults as $row) {
            if (! $registerModel->where('company_id', $companyId)->where('code', $row['code'])->first()) {
                $registerModel->insert(array_merge($row, [
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'active' => 1,
                ]));
            }
        }

        $gatewayModel = new CashPaymentGatewayModel();
        $gateways = [
            ['name' => 'Mercado Pago', 'code' => 'MP', 'gateway_type' => 'qr', 'provider' => 'mercadopago'],
            ['name' => 'POSNet', 'code' => 'POSNET', 'gateway_type' => 'card', 'provider' => 'posnet'],
            ['name' => 'Transferencia Bancaria', 'code' => 'TRANSFER', 'gateway_type' => 'transfer', 'provider' => 'bank'],
        ];
        foreach ($gateways as $gateway) {
            if (! $gatewayModel->where('company_id', $companyId)->where('code', $gateway['code'])->first()) {
                $gatewayModel->insert(array_merge($gateway, [
                    'company_id' => $companyId,
                    'active' => 1,
                ]));
            }
        }
    }

    // ── Multi-register: limits & CRUD ──────────────────────────────────

    public function getMaxRegisters(string $companyId): int
    {
        $row = db_connect()->table('company_settings')
            ->where('company_id', $companyId)
            ->where('key', 'max_cash_registers')
            ->get()->getRowArray();

        return (int) ($row['value'] ?? 10);
    }

    public function canCreateRegister(string $companyId): bool
    {
        $current = (new CashRegisterModel())->where('company_id', $companyId)->where('active', 1)->countAllResults();
        return $current < $this->getMaxRegisters($companyId);
    }

    public function createRegister(array $data): ?string
    {
        $companyId = (string) $data['company_id'];

        if (! $this->canCreateRegister($companyId)) {
            return null; // limit reached
        }

        $model = new CashRegisterModel();
        $code  = trim((string) ($data['code'] ?? ''));
        if ($code !== '' && $model->where('company_id', $companyId)->where('code', $code)->first()) {
            return null; // duplicate code
        }

        return $model->insert([
            'company_id'              => $companyId,
            'branch_id'               => $data['branch_id'] ?? null,
            'name'                    => trim((string) ($data['name'] ?? 'Caja')),
            'code'                    => $code,
            'register_type'           => $data['register_type'] ?? 'general',
            'is_default'              => (int) ($data['is_default'] ?? 0),
            'active'                  => 1,
            'account_id'              => $data['account_id'] ?? null,
            'sales_point_of_sale_id'  => $data['sales_point_of_sale_id'] ?? null,
        ], true);
    }

    public function updateRegister(string $companyId, string $registerId, array $data): bool
    {
        $model    = new CashRegisterModel();
        $register = $model->where('company_id', $companyId)->find($registerId);
        if (! $register) {
            return false;
        }

        $code = trim((string) ($data['code'] ?? $register['code']));
        if ($code !== $register['code']) {
            $dup = $model->where('company_id', $companyId)->where('code', $code)->where('id !=', $registerId)->first();
            if ($dup) {
                return false;
            }
        }

        return (bool) $model->update($registerId, [
            'name'                    => trim((string) ($data['name'] ?? $register['name'])),
            'code'                    => $code,
            'register_type'           => $data['register_type'] ?? $register['register_type'],
            'branch_id'               => array_key_exists('branch_id', $data) ? $data['branch_id'] : $register['branch_id'],
            'account_id'              => array_key_exists('account_id', $data) ? $data['account_id'] : $register['account_id'],
            'sales_point_of_sale_id'  => array_key_exists('sales_point_of_sale_id', $data) ? $data['sales_point_of_sale_id'] : $register['sales_point_of_sale_id'],
            'active'                  => (int) ($data['active'] ?? $register['active']),
        ]);
    }

    public function deactivateRegister(string $companyId, string $registerId): bool
    {
        $model = new CashRegisterModel();
        $register = $model->where('company_id', $companyId)->find($registerId);
        if (! $register) {
            return false;
        }

        // Cannot deactivate if there's an open session
        $openSession = (new CashSessionModel())
            ->where('company_id', $companyId)
            ->where('cash_register_id', $registerId)
            ->where('status', 'open')
            ->first();
        if ($openSession) {
            return false;
        }

        return (bool) $model->update($registerId, ['active' => 0]);
    }

    // ── End CRUD ───────────────────────────────────────────────────────

    public function registerRows(string $companyId): array
    {
        return db_connect()->table('cash_registers cr')
            ->select('cr.*, a.name AS account_name, a.code AS account_code, pos.name AS pos_name')
            ->join('accounting_accounts a', 'a.id = cr.account_id', 'left')
            ->join('sales_points_of_sale pos', 'pos.id = cr.sales_point_of_sale_id', 'left')
            ->where('cr.company_id', $companyId)
            ->where('cr.active', 1)
            ->orderBy('cr.register_type', 'ASC')
            ->orderBy('cr.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function activeSessions(string $companyId): array
    {
        return db_connect()->table('cash_sessions cs')
            ->select('cs.*, cr.name AS register_name, cr.code AS register_code, cr.register_type')
            ->join('cash_registers cr', 'cr.id = cs.cash_register_id')
            ->where('cs.company_id', $companyId)
            ->where('cs.status', 'open')
            ->orderBy('cs.opened_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    public function recentMovements(string $companyId, int $limit = 20): array
    {
        return db_connect()->table('cash_movements cm')
            ->select('cm.*, cr.name AS register_name, g.name AS gateway_name, ch.check_number')
            ->join('cash_registers cr', 'cr.id = cm.cash_register_id')
            ->join('cash_payment_gateways g', 'g.id = cm.gateway_id', 'left')
            ->join('cash_checks ch', 'ch.id = cm.cash_check_id', 'left')
            ->where('cm.company_id', $companyId)
            ->orderBy('cm.occurred_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    public function summary(string $companyId): array
    {
        $registerModel = new CashRegisterModel();
        $sessionModel = new CashSessionModel();
        $movementModel = new CashMovementModel();

        $today = date('Y-m-d');
        $incomes = (float) (($movementModel->selectSum('amount', 'amount')->where('company_id', $companyId)->where('DATE(occurred_at) >=', $today)->where('amount >=', 0)->first()['amount'] ?? 0));
        $expenses = abs((float) (($movementModel->selectSum('amount', 'amount')->where('company_id', $companyId)->where('DATE(occurred_at) >=', $today)->where('amount <', 0)->first()['amount'] ?? 0)));

        return [
            'registers' => $registerModel->where('company_id', $companyId)->where('active', 1)->countAllResults(),
            'sessions_open' => $sessionModel->where('company_id', $companyId)->where('status', 'open')->countAllResults(),
            'today_income' => round($incomes, 2),
            'today_expense' => round($expenses, 2),
            'today_balance' => round($incomes - $expenses, 2),
            'checks_portfolio' => (new CashCheckModel())->where('company_id', $companyId)->whereIn('status', ['portfolio', 'received'])->countAllResults(),
            'reconciliations_pending' => (new CashReconciliationModel())->where('company_id', $companyId)->where('status', 'pending')->countAllResults(),
        ];
    }

    public function paymentMethodBreakdown(string $companyId): array
    {
        return db_connect()->table('cash_movements')
            ->select('payment_method, SUM(amount) AS total', false)
            ->where('company_id', $companyId)
            ->groupBy('payment_method')
            ->orderBy('payment_method', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function gatewayRows(string $companyId): array
    {
        return (new CashPaymentGatewayModel())
            ->where('company_id', $companyId)
            ->where('active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function checkRows(string $companyId, int $limit = 20): array
    {
        return db_connect()->table('cash_checks cc')
            ->select('cc.*, s.name AS supplier_name, c.name AS customer_name')
            ->join('suppliers s', 's.id = cc.supplier_id', 'left')
            ->join('customers c', 'c.id = cc.customer_id', 'left')
            ->where('cc.company_id', $companyId)
            ->orderBy('cc.created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    public function reconciliationRows(string $companyId, int $limit = 20): array
    {
        return db_connect()->table('cash_reconciliations cr')
            ->select('cr.*, cs.opened_at, reg.name AS register_name')
            ->join('cash_sessions cs', 'cs.id = cr.cash_session_id')
            ->join('cash_registers reg', 'reg.id = cs.cash_register_id')
            ->where('cr.company_id', $companyId)
            ->orderBy('cr.created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    public function createCheck(array $data): ?string
    {
        return (new CashCheckModel())->insert([
            'company_id' => $data['company_id'],
            'supplier_id' => $data['supplier_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'check_type' => $data['check_type'] ?? 'received',
            'check_number' => $data['check_number'],
            'bank_name' => $data['bank_name'],
            'issuer_name' => $data['issuer_name'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'amount' => round((float) ($data['amount'] ?? 0), 2),
            'status' => $data['status'] ?? 'portfolio',
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ], true);
    }

    public function createReconciliation(array $data): ?string
    {
        $expected = round((float) ($data['expected_amount'] ?? 0), 2);
        $actual = round((float) ($data['actual_amount'] ?? 0), 2);
        return (new CashReconciliationModel())->insert([
            'company_id' => $data['company_id'],
            'cash_session_id' => $data['cash_session_id'],
            'payment_method' => $data['payment_method'],
            'expected_amount' => $expected,
            'actual_amount' => $actual,
            'difference_amount' => round($actual - $expected, 2),
            'status' => abs($actual - $expected) < 0.01 ? 'balanced' : 'pending',
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ], true);
    }

    /**
     * Resolve the active cash session with priority:
     *  1. Explicit cash_register_id if provided.
     *  2. Session opened by the given userId on the requested channel.
     *  3. Legacy fallback: first open session matching channel, then general.
     */
    public function resolveActiveSession(
        string  $companyId,
        string  $channel = 'general',
        ?string $userId = null,
        ?string $cashRegisterId = null
    ): ?array {
        $db = db_connect();

        // 1. Explicit register requested
        if ($cashRegisterId) {
            $row = $db->table('cash_sessions cs')
                ->select('cs.*, cr.name AS register_name, cr.code AS register_code, cr.register_type')
                ->join('cash_registers cr', 'cr.id = cs.cash_register_id')
                ->where('cs.company_id', $companyId)
                ->where('cs.cash_register_id', $cashRegisterId)
                ->where('cs.status', 'open')
                ->where('cr.active', 1)
                ->orderBy('cs.opened_at', 'DESC')
                ->get()
                ->getRowArray();
            if ($row) {
                return $row;
            }
        }

        // 2. Session opened by this specific user on the channel
        if ($userId) {
            $row = $db->table('cash_sessions cs')
                ->select('cs.*, cr.name AS register_name, cr.code AS register_code, cr.register_type')
                ->join('cash_registers cr', 'cr.id = cs.cash_register_id')
                ->where('cs.company_id', $companyId)
                ->where('cs.opened_by', $userId)
                ->where('cs.status', 'open')
                ->where('cr.active', 1)
                ->where('cr.register_type', $channel)
                ->orderBy('cs.opened_at', 'DESC')
                ->get()
                ->getRowArray();
            if ($row) {
                return $row;
            }
        }

        // 3. Legacy fallback: any open session matching channel, then general
        $rows = $db->table('cash_sessions cs')
            ->select('cs.*, cr.name AS register_name, cr.code AS register_code, cr.register_type')
            ->join('cash_registers cr', 'cr.id = cs.cash_register_id')
            ->where('cs.company_id', $companyId)
            ->where('cs.status', 'open')
            ->where('cr.active', 1)
            ->orderBy('cs.opened_at', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            if (($row['register_type'] ?? '') === $channel) {
                return $row;
            }
        }
        foreach ($rows as $row) {
            if (($row['register_type'] ?? '') === 'general') {
                return $row;
            }
        }

        return $rows[0] ?? null;
    }

    /**
     * Backward-compatible wrapper. Existing callers that only pass
     * (companyId, channel) continue working without changes.
     */
    public function activeSessionForChannel(string $companyId, string $channel = 'general'): ?array
    {
        return $this->resolveActiveSession($companyId, $channel);
    }

    public function openSession(string $companyId, string $registerId, string $userId, float $openingAmount, ?string $notes = null): ?string
    {
        $sessionModel = new CashSessionModel();
        if ($sessionModel->where('company_id', $companyId)->where('cash_register_id', $registerId)->where('status', 'open')->first()) {
            return null;
        }

        return $sessionModel->insert([
            'company_id' => $companyId,
            'cash_register_id' => $registerId,
            'status' => 'open',
            'opened_by' => $userId,
            'opened_at' => date('Y-m-d H:i:s'),
            'opening_amount' => $openingAmount,
            'expected_closing_amount' => $openingAmount,
            'difference_amount' => 0,
            'notes' => $notes,
        ], true);
    }

    public function closeSession(string $companyId, string $sessionId, string $userId, float $actualAmount, ?string $notes = null, bool $isAdmin = false): bool
    {
        $session = $this->ownedSession($companyId, $sessionId, $isAdmin ? null : $userId);
        if (! $session || ($session['status'] ?? '') !== 'open') {
            return false;
        }

        $expected = $this->expectedBalance($sessionId, (float) ($session['opening_amount'] ?? 0));
        $difference = round($actualAmount - $expected, 2);

        $db = db_connect();
        $db->transStart();

        (new CashSessionModel())->update($sessionId, [
            'status' => 'closed',
            'expected_closing_amount' => $expected,
            'actual_closing_amount' => $actualAmount,
            'difference_amount' => $difference,
            'closed_by' => $userId,
            'closed_at' => date('Y-m-d H:i:s'),
            'notes' => $notes ?: ($session['notes'] ?? null),
        ]);

        $closureModel = new CashClosureModel();
        $closureModel->insert([
            'company_id' => $companyId,
            'cash_session_id' => $sessionId,
            'closed_by' => $userId,
            'closed_at' => date('Y-m-d H:i:s'),
            'opening_amount' => (float) ($session['opening_amount'] ?? 0),
            'expected_amount' => $expected,
            'actual_amount' => $actualAmount,
            'difference_amount' => $difference,
            'notes' => $notes,
        ]);

        $closureId = (string) $closureModel->getInsertID();
        if ($closureId !== '') {
            (new AccountingService())->syncCashClosure($companyId, $closureId, $userId);
        }

        $db->transComplete();
        return $db->transStatus();
    }

    public function registerMovement(array $data): ?string
    {
        $session = $this->ownedSession((string) $data['company_id'], (string) $data['cash_session_id']);
        if (! $session || ($session['status'] ?? '') !== 'open') {
            return null;
        }

        $existing = null;
        if (! empty($data['reference_type']) && ! empty($data['reference_id'])) {
            $existing = (new CashMovementModel())
                ->where('cash_session_id', $data['cash_session_id'])
                ->where('reference_type', $data['reference_type'])
                ->where('reference_id', $data['reference_id'])
                ->first();
        }
        if ($existing) {
            return $existing['id'];
        }

        $id = (new CashMovementModel())->insert([
            'company_id' => $data['company_id'],
            'cash_register_id' => $data['cash_register_id'],
            'cash_session_id' => $data['cash_session_id'],
            'movement_type' => $data['movement_type'],
            'payment_method' => $data['payment_method'] ?? null,
            'gateway_id' => $data['gateway_id'] ?? null,
            'cash_check_id' => $data['cash_check_id'] ?? null,
            'amount' => round((float) $data['amount'], 2),
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'external_reference' => $data['external_reference'] ?? null,
            'reconciliation_status' => $data['reconciliation_status'] ?? 'pending',
            'occurred_at' => $data['occurred_at'] ?? date('Y-m-d H:i:s'),
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'],
        ], true);

        $sessionModel = new CashSessionModel();
        $sessionModel->update($data['cash_session_id'], [
            'expected_closing_amount' => $this->expectedBalance((string) $data['cash_session_id'], (float) ($session['opening_amount'] ?? 0)),
        ]);

        return $id;
    }

    public function expectedBalance(string $sessionId, float $openingAmount = 0): float
    {
        $sum = (float) (((new CashMovementModel())->selectSum('amount', 'amount')->where('cash_session_id', $sessionId)->first()['amount']) ?? 0);
        return round($openingAmount + $sum, 2);
    }

    public function ownedSession(string $companyId, string $sessionId, ?string $userId = null): ?array
    {
        $query = clone (new CashSessionModel())->where('company_id', $companyId)->where('id', $sessionId);
        if ($userId !== null) {
            $query->where('opened_by', $userId);
        }
        return $query->first();
    }

    /**
     * Auto-open a kiosk cash session if none exists.
     *
     * Ensures CAJA-KIOSCO register exists, then opens a new session with
     * $0 initial amount. Returns the session array ready for use.
     */
    /**
     * Auto-open a kiosk cash session if none exists.
     *
     * Multi-register aware: tries to find any active kiosk register,
     * prioritising one already opened by this user, then one without
     * an open session. Falls back to creating a default CAJA-KIOSCO.
     */
    public function autoOpenKioskSession(string $companyId, string $userId, ?string $branchId = null): ?array
    {
        $registerModel = new CashRegisterModel();
        $sessionModel  = new CashSessionModel();

        // 1. Find kiosk registers
        $registers = $registerModel
            ->where('company_id', $companyId)
            ->where('register_type', 'kiosk')
            ->where('active', 1)
            ->orderBy('is_default', 'DESC')
            ->orderBy('name', 'ASC')
            ->findAll();

        // 1b. No kiosk register at all? Create the default one
        if (empty($registers)) {
            $branchId ??= (new BranchModel())
                ->where('company_id', $companyId)
                ->where('active', 1)
                ->orderBy('code', 'ASC')
                ->first()['id'] ?? null;

            $registerId = $registerModel->insert([
                'company_id'    => $companyId,
                'branch_id'     => $branchId,
                'name'          => 'Caja Kiosco',
                'code'          => 'CAJA-KIOSCO',
                'register_type' => 'kiosk',
                'is_default'    => 0,
                'active'        => 1,
            ], true);

            $register = $registerModel->find($registerId);
            if (! $register) {
                return null;
            }
            $registers = [$register];
        }

        // 2. Try to find a session already opened by this user
        foreach ($registers as $reg) {
            $existing = $sessionModel
                ->where('company_id', $companyId)
                ->where('cash_register_id', $reg['id'])
                ->where('opened_by', $userId)
                ->where('status', 'open')
                ->first();
            if ($existing) {
                return array_merge($existing, [
                    'register_name' => $reg['name'],
                    'register_code' => $reg['code'],
                    'register_type' => $reg['register_type'],
                ]);
            }
        }

        // 3. Try to find any open kiosk session on any register
        foreach ($registers as $reg) {
            $existing = $sessionModel
                ->where('company_id', $companyId)
                ->where('cash_register_id', $reg['id'])
                ->where('status', 'open')
                ->first();
            if ($existing) {
                return array_merge($existing, [
                    'register_name' => $reg['name'],
                    'register_code' => $reg['code'],
                    'register_type' => $reg['register_type'],
                ]);
            }
        }

        // 4. Open a new session on the first available register
        $register  = $registers[0];
        $sessionId = $this->openSession($companyId, $register['id'], $userId, 0.00, 'Apertura automatica desde Kiosco');

        if (! $sessionId) {
            return null;
        }

        $session = $sessionModel->find($sessionId);
        if (! $session) {
            return null;
        }

        return array_merge($session, [
            'register_name' => $register['name'],
            'register_code' => $register['code'],
            'register_type' => $register['register_type'],
        ]);
    }

    public function endorseCheck(string $companyId, string $checkId, string $supplierId, string $sessionId, string $userId, ?string $notes = null): bool
    {
        $checkModel = new CashCheckModel();
        $check = $checkModel->where('company_id', $companyId)->find($checkId);
        if (!$check || !in_array($check['status'], ['portfolio', 'received'], true)) {
            return false;
        }

        $session = (new CashSessionModel())->where('company_id', $companyId)->where('status', 'open')->find($sessionId);
        if (!$session) {
            return false;
        }

        $db = db_connect();
        $db->transStart();

        $checkModel->update($checkId, [
            'status' => 'endorsed',
            'supplier_id' => $supplierId,
            'notes' => trim(($check['notes'] ?? '') . "\n" . ($notes ?? '')),
        ]);

        $this->registerMovement([
            'company_id' => $companyId,
            'cash_register_id' => $session['cash_register_id'],
            'cash_session_id' => $sessionId,
            'movement_type' => 'check_endorsement',
            'payment_method' => 'check',
            'cash_check_id' => $checkId,
            'amount' => -1 * (float) $check['amount'],
            'reference_type' => 'check_endorsement',
            'reference_id' => $checkId,
            'reference_number' => $check['check_number'],
            'notes' => $notes ?: 'Endoso de cheque de terceros',
            'created_by' => $userId,
        ]);

        $db->transComplete();
        return $db->transStatus();
    }

    public function depositCheck(string $companyId, string $checkId, string $sessionId, string $userId, ?string $notes = null): bool
    {
        $checkModel = new CashCheckModel();
        $check = $checkModel->where('company_id', $companyId)->find($checkId);
        if (!$check || !in_array($check['status'], ['portfolio', 'received'], true)) {
            return false;
        }

        $session = (new CashSessionModel())->where('company_id', $companyId)->where('status', 'open')->find($sessionId);
        if (!$session) {
            return false;
        }

        $db = db_connect();
        $db->transStart();

        $checkModel->update($checkId, [
            'status' => 'deposited',
            'notes' => trim(($check['notes'] ?? '') . "\n" . ($notes ?? '')),
        ]);

        $this->registerMovement([
            'company_id' => $companyId,
            'cash_register_id' => $session['cash_register_id'],
            'cash_session_id' => $sessionId,
            'movement_type' => 'check_deposit',
            'payment_method' => 'check',
            'cash_check_id' => $checkId,
            'amount' => -1 * (float) $check['amount'],
            'reference_type' => 'check_deposit',
            'reference_id' => $checkId,
            'reference_number' => $check['check_number'],
            'notes' => $notes ?: 'Depósito de cheque de terceros',
            'created_by' => $userId,
        ]);

        $db->transComplete();
        return $db->transStatus();
    }

    public function rejectCheck(string $companyId, string $checkId, string $sessionId, string $userId, ?string $notes = null): bool
    {
        $checkModel = new CashCheckModel();
        $check = $checkModel->where('company_id', $companyId)->find($checkId);
        if (!$check || !in_array($check['status'], ['deposited', 'endorsed'], true)) {
            return false;
        }

        $session = (new CashSessionModel())->where('company_id', $companyId)->where('status', 'open')->find($sessionId);
        if (!$session) {
            return false;
        }

        $db = db_connect();
        $db->transStart();

        $checkModel->update($checkId, [
            'status' => 'rejected',
            'notes' => trim(($check['notes'] ?? '') . "\n" . ($notes ?? '')),
        ]);

        $this->registerMovement([
            'company_id' => $companyId,
            'cash_register_id' => $session['cash_register_id'],
            'cash_session_id' => $sessionId,
            'movement_type' => 'check_rejection',
            'payment_method' => 'check',
            'cash_check_id' => $checkId,
            'amount' => -1 * (float) $check['amount'],
            'reference_type' => 'check_rejection',
            'reference_id' => $checkId,
            'reference_number' => $check['check_number'],
            'notes' => $notes ?: 'Rechazo de cheque de terceros',
            'created_by' => $userId,
        ]);

        $db->transComplete();
        return $db->transStatus();
    }
}
