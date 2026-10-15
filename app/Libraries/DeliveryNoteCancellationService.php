<?php
namespace App\Libraries;

use App\Models\InventoryMovementModel;
use RuntimeException;

class DeliveryNoteCancellationService
{
    /** Called with the source and company locked. Reverse recorded movements, not today's kit composition. */
    public function cancel(string $companyId, string $origin, string $id, string $userId, string $reason, bool $returned): array
    {
        $notes = new DeliveryNoteService(); $db = db_connect();
        $note = $notes->summary($companyId, $origin, $id);
        if ($note['status'] === 'cancelled') return $note;
        if ($note['cancel_reason']) throw new RuntimeException($note['cancel_reason']);
        if (trim($reason) === '' || mb_strlen($reason) > 1000) throw new RuntimeException('Indica un motivo de anulación de hasta 1000 caracteres.');
        if ($origin === 'sale') {
            (new SalesCollectionService())->assertSaleCanCancel($companyId, $id);
            if ($db->table('sale_returns')->where('sale_id', $id)->countAllResults()) throw new RuntimeException('El remito tiene devoluciones previas. Requiere revisión antes de anular.');
        }
        if ($note['stock_recorded'] && !$returned) throw new RuntimeException('Confirma el retorno físico de toda la mercadería para reingresarla al stock.');
        if ($note['stock_recorded']) {
            // Both legacy stores can share a number. Refuse to guess which movement belongs to which document.
            $other = $origin === 'sale' ? $db->table('sales_delivery_notes')->where('company_id', $companyId)->where('delivery_number', $note['number'])->countAllResults()
                : $db->table('sales')->where('company_id', $companyId)->where('sale_number', $note['number'])->countAllResults();
            if ($other) throw new RuntimeException('El número de remito aparece en ambos circuitos. Revisa sus movimientos antes de anular.');
            $movements = $db->table('inventory_movements')->where('company_id', $companyId)->where('source_document', $note['number'])->where('reason', 'REMITO')->where('movement_type', 'egreso')->get()->getResultArray();
            if (!$movements) throw new RuntimeException('No se encontraron las salidas originales del remito; no es seguro reconstruir el stock.');
            foreach ($movements as $movement) {
                $warehouse = $movement['source_warehouse_id'];
                if (!$warehouse || (float) $movement['quantity'] <= 0) throw new RuntimeException('Movimiento original de remito inválido.');
                $owned = $db->table('inventory_warehouses')->where('company_id', $companyId)->where('id', $warehouse)->countAllResults();
                if (!$owned) throw new RuntimeException('El depósito original no pertenece a la empresa.');
                (new InventoryIntegrityService())->changeStock($companyId, $movement['product_id'], $warehouse, (float) $movement['quantity'], $movement['source_location_id'] ?? null);
                $entry = ['company_id' => $companyId, 'product_id' => $movement['product_id'], 'movement_type' => 'ingreso',
                    'quantity' => $movement['quantity'], 'unit_cost' => $movement['unit_cost'] ?? null, 'total_cost' => $movement['total_cost'] ?? null,
                    'destination_warehouse_id' => $warehouse, 'destination_location_id' => $movement['source_location_id'] ?? null,
                    'lot_number' => $movement['lot_number'] ?? null, 'serial_number' => $movement['serial_number'] ?? null,
                    'expiration_date' => $movement['expiration_date'] ?? null, 'reason' => 'ANULACION REMITO', 'source_document' => $note['number'],
                    'notes' => 'Reversión de movimiento ' . $movement['id'] . '. Motivo: ' . $reason, 'performed_by' => $userId, 'occurred_at' => date('Y-m-d H:i:s')];
                if (!(new InventoryMovementModel())->insert($entry)) throw new RuntimeException('No se pudo registrar el retorno de mercadería.');
            }
        }
        if ($origin === 'cycle' && !empty($note['sales_order_id'])) {
            $order = $db->table('sales_orders')->where('company_id', $companyId)->where('id', $note['sales_order_id'])->get()->getRowArray();
            if (!$order) throw new RuntimeException('Pedido de origen no disponible.');
            foreach ($note['items'] as $item) {
                if (empty($item['sales_order_item_id'])) continue;
                $orderItem = $db->table('sales_order_items')->where('sales_order_id', $order['id'])->where('id', $item['sales_order_item_id'])->get()->getRowArray();
                if (!$orderItem || DeliveryNoteService::units($orderItem['quantity_delivered']) < DeliveryNoteService::units($item['quantity'])) throw new RuntimeException('Las cantidades entregadas del pedido requieren revisión.');
                $db->table('sales_order_items')->where('id', $orderItem['id'])->update(['quantity_delivered' => (DeliveryNoteService::units($orderItem['quantity_delivered']) - DeliveryNoteService::units($item['quantity'])) / 10000]);
            }
            if ($order['status'] !== 'cancelled') {
                $lines = $db->table('sales_order_items')->where('sales_order_id', $order['id'])->get()->getResultArray();
                $any = array_filter($lines, static fn($l) => (float) $l['quantity_delivered'] > 0 || (float) $l['quantity_invoiced'] > 0);
                $db->table('sales_orders')->where('id', $order['id'])->where('company_id', $companyId)->update(['status' => $any ? 'partial' : (!empty($order['approved_at']) ? 'approved' : 'pending'), 'updated_at' => date('Y-m-d H:i:s')]);
            }
        }
        $update = ['status' => 'cancelled', 'cancelled_by' => $userId, 'cancelled_at' => date('Y-m-d H:i:s'), 'cancellation_reason' => trim($reason), 'updated_at' => date('Y-m-d H:i:s')];
        if ($origin === 'cycle') $update['return_confirmed_at'] = $note['stock_recorded'] ? date('Y-m-d H:i:s') : null;
        $db->table($origin === 'sale' ? 'sales' : 'sales_delivery_notes')->where('company_id', $companyId)->where('id', $id)->update($update);
        if ($origin === 'sale') {
            (new AccountingService())->reverseCancelledSale($companyId, $id, $userId);
        }
        return $note;
    }
}
