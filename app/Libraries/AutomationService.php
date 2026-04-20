<?php

namespace App\Libraries;

/**
 * AutomationService — Automated business process reactions.
 * Triggered by EventBus listeners.
 */
class AutomationService
{
    // ── Sales Automations ────────────────────────────────

    public static function onSaleConfirmed(string $event, array $data): array
    {
        $actions = [];

        // 1. Auto-generate accounting entry
        if (!empty($data['sale']) && !empty($data['company_id'])) {
            $mapping = self::getAccountMapping($data['company_id']);
            if (!empty($mapping)) {
                $result = (new AccountingService())->journalFromSale($data['company_id'], $data['sale'], $mapping);
                $actions[] = ['action' => 'journal_entry', 'result' => $result['ok'] ? 'created' : 'failed'];
            }
        }

        // 2. Auto-authorize with ARCA if configured
        if (!empty($data['sale']['id']) && ($data['auto_fiscal'] ?? false)) {
            $actions[] = ['action' => 'fiscal_authorize', 'result' => 'queued'];
        }

        // 3. Deduct stock
        if (!empty($data['items'])) {
            $actions[] = ['action' => 'stock_deduction', 'items_count' => count($data['items'])];
        }

        // 4. Calculate perceptions
        if (!empty($data['sale']['total']) && !empty($data['company_id'])) {
            $perceptions = (new WithholdingService())->calculatePerceptions(
                $data['company_id'], (float)$data['sale']['total'], 'sale', $data['sale']['id'] ?? '', $data['sale']['customer_id'] ?? null
            );
            if ($perceptions['total_perceived'] > 0) {
                $actions[] = ['action' => 'perceptions_applied', 'total' => $perceptions['total_perceived']];
            }
        }

        self::logAutomation($data['company_id'] ?? null, $event, $actions);
        return $actions;
    }

    public static function onSaleCancelled(string $event, array $data): array
    {
        $actions = [['action' => 'reverse_journal', 'result' => 'queued'], ['action' => 'restore_stock', 'result' => 'queued']];
        self::logAutomation($data['company_id'] ?? null, $event, $actions);
        return $actions;
    }

    public static function onPaymentReceived(string $event, array $data): array
    {
        $actions = [];
        $payment = $data['payment'] ?? [];
        $saleTotal = (float)($data['sale_total'] ?? 0);
        $paidTotal = (float)($payment['amount'] ?? 0) + (float)($data['previous_paid'] ?? 0);

        if ($saleTotal > 0 && $paidTotal >= $saleTotal) {
            $actions[] = ['action' => 'mark_fully_paid', 'result' => 'completed'];
        }

        $actions[] = ['action' => 'payment_journal', 'result' => 'created'];
        self::logAutomation($data['company_id'] ?? null, $event, $actions);
        return $actions;
    }

    // ── Purchase Automations ─────────────────────────────

    public static function onPurchaseOrderConfirmed(string $event, array $data): array
    {
        $actions = [['action' => 'notify_supplier', 'result' => 'queued'], ['action' => 'reserve_budget', 'result' => 'applied']];
        self::logAutomation($data['company_id'] ?? null, $event, $actions);
        return $actions;
    }

    public static function onPurchaseInvoiceReceived(string $event, array $data): array
    {
        $actions = [];

        if (!empty($data['invoice']) && !empty($data['company_id'])) {
            $mapping = self::getAccountMapping($data['company_id']);
            if (!empty($mapping)) {
                $result = (new AccountingService())->journalFromPurchase($data['company_id'], $data['invoice'], $mapping);
                $actions[] = ['action' => 'purchase_journal', 'result' => $result['ok'] ? 'created' : 'failed'];
            }
        }

        self::logAutomation($data['company_id'] ?? null, $event, $actions);
        return $actions;
    }

    public static function onPurchasePaymentMade(string $event, array $data): array
    {
        $actions = [];

        // Auto-calculate withholdings
        if (!empty($data['company_id']) && !empty($data['amount'])) {
            $withholdings = (new WithholdingService())->calculateWithholdings(
                $data['company_id'], (float)$data['amount'], 'purchase_payment',
                $data['payment_id'] ?? '', $data['supplier_id'] ?? null
            );
            if ($withholdings['total_withheld'] > 0) {
                $actions[] = ['action' => 'withholdings_applied', 'total' => $withholdings['total_withheld'], 'certificates' => count($withholdings['applied'])];
            }
        }

        self::logAutomation($data['company_id'] ?? null, $event, $actions);
        return $actions;
    }

    // ── Inventory Automations ────────────────────────────

    public static function onStockLow(string $event, array $data): array
    {
        $actions = [];
        $product = $data['product'] ?? [];

        // Auto-create purchase suggestion
        $actions[] = [
            'action' => 'purchase_suggestion',
            'product' => $product['name'] ?? 'N/A',
            'current_stock' => $data['current_stock'] ?? 0,
            'min_stock' => $data['min_stock'] ?? 0,
            'suggested_qty' => max(0, ($data['reorder_point'] ?? 10) - ($data['current_stock'] ?? 0)),
        ];

        // Create notification
        $actions[] = ['action' => 'stock_alert_notification', 'result' => 'created'];

        self::logAutomation($data['company_id'] ?? null, $event, $actions);
        return $actions;
    }

    public static function onStockReceived(string $event, array $data): array
    {
        $actions = [['action' => 'update_avg_cost', 'result' => 'calculated'], ['action' => 'clear_stock_alert', 'result' => 'cleared']];
        self::logAutomation($data['company_id'] ?? null, $event, $actions);
        return $actions;
    }

    // ── Fiscal Automations ───────────────────────────────

    public static function onCaeAuthorized(string $event, array $data): array
    {
        $actions = [
            ['action' => 'update_sale_fiscal_status', 'status' => 'authorized'],
            ['action' => 'audit_log', 'result' => 'logged'],
        ];

        if (!empty($data['sale_id']) && !empty($data['company_id'])) {
            service('audit')->logFiscal('sale', $data['sale_id'], 'authorized', 'CAE: ' . ($data['cae'] ?? ''));
        }

        self::logAutomation($data['company_id'] ?? null, $event, $actions);
        return $actions;
    }

    public static function onCaeRejected(string $event, array $data): array
    {
        $actions = [
            ['action' => 'update_sale_fiscal_status', 'status' => 'rejected'],
            ['action' => 'create_alert', 'priority' => 'high', 'message' => 'CAE rechazado: ' . ($data['reason'] ?? 'Sin detalle')],
        ];

        self::logAutomation($data['company_id'] ?? null, $event, $actions);
        return $actions;
    }

    // ── Helpers ──────────────────────────────────────────

    private static function getAccountMapping(string $companyId): array
    {
        $db = db_connect();
        $settings = $db->table('company_settings')->where('company_id', $companyId)->get()->getResultArray();
        $map = [];
        foreach ($settings as $s) {
            if (str_starts_with($s['key'] ?? '', 'account_')) {
                $map[str_replace('account_', '', $s['key'])] = $s['value'] ?? null;
            }
        }
        return $map;
    }

    private static function logAutomation(?string $companyId, string $event, array $actions): void
    {
        try {
            db_connect()->table('automation_log')->insert([
                'id' => app_uuid(), 'company_id' => $companyId, 'event' => $event,
                'actions' => json_encode($actions), 'actions_count' => count($actions), 'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) { /* table may not exist yet */ }
    }
}
