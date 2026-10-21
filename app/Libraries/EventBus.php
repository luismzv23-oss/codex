<?php

namespace App\Libraries;

/**
 * EventBus — Central event dispatcher for process automation.
 * Modules emit events, listeners execute automated actions.
 */
class EventBus
{
    private static array $listeners = [];
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) return;
        self::$initialized = true;

        // ── Sales events ──
        self::on('sale.confirmed', [AutomationService::class, 'onSaleConfirmed']);
        self::on('sale.cancelled', [AutomationService::class, 'onSaleCancelled']);
        self::on('sale.payment_received', [AutomationService::class, 'onPaymentReceived']);

        // ── Purchase events ──
        self::on('purchase.order_confirmed', [AutomationService::class, 'onPurchaseOrderConfirmed']);
        self::on('purchase.invoice_received', [AutomationService::class, 'onPurchaseInvoiceReceived']);
        self::on('purchase.payment_made', [AutomationService::class, 'onPurchasePaymentMade']);

        // ── Inventory events ──
        self::on('inventory.stock_low', [AutomationService::class, 'onStockLow']);
        self::on('inventory.stock_received', [AutomationService::class, 'onStockReceived']);

        // ── Fiscal events ──
        self::on('fiscal.cae_authorized', [AutomationService::class, 'onCaeAuthorized']);
        self::on('fiscal.cae_rejected', [AutomationService::class, 'onCaeRejected']);
    }

    public static function on(string $event, callable $listener): void
    {
        self::$listeners[$event][] = $listener;
    }

    public static function emit(string $event, array $data = []): array
    {
        // Migrated events have exactly one dispatch path. Never also run legacy listeners.
        $companyId=(string)($data['company_id'] ?? '');
        $id=match ($event) {
            'sale.confirmed','sale.cancelled' => \App\Libraries\Core\Events\BusinessEvents::sale($companyId,(string)($data['sale']['id'] ?? ''),$event==='sale.confirmed' ? 'confirmed' : 'cancelled'),
            'inventory.stock_received' => \App\Libraries\Core\Events\BusinessEvents::stockReceived($companyId,(string)($data['receipt_id'] ?? '')),
            'purchase.payment_made' => \App\Libraries\Core\Events\BusinessEvents::supplierPayment($companyId,(string)($data['payment_id'] ?? '')),
            'sale.payment_received' => \App\Libraries\Core\Events\BusinessEvents::receiptPayment($companyId,(string)($data['receipt_id'] ?? ''),(array)($data['payment'] ?? [])),
            default => null,
        };
        if ($id!==null) return [['event_id'=>$id,'status'=>'pending']];
        self::init();
        $results = [];

        foreach (self::$listeners[$event] ?? [] as $listener) {
            try {
                $results[] = ['listener' => is_array($listener) ? implode('::', $listener) : 'closure', 'result' => call_user_func($listener, $event, $data)];
            } catch (\Throwable $e) {
                log_message('error', "EventBus: {$event} listener failed: " . $e->getMessage());
                $results[] = ['listener' => 'error', 'error' => $e->getMessage()];
            }
        }

        // Log event
        try {
            db_connect()->table('event_log')->insert([
                'id' => app_uuid(), 'event' => $event, 'payload' => json_encode($data),
                'listeners_count' => count(self::$listeners[$event] ?? []),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) { /* table may not exist yet */ }

        return $results;
    }

    public static function listRegistered(): array
    {
        self::init();
        $list = [];
        foreach (self::$listeners as $event => $listeners) {
            $list[$event] = count($listeners);
        }
        return $list;
    }
}
