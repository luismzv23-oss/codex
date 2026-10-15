<?php
namespace App\Libraries;

use RuntimeException;

/** A read model for both commercial-cycle and sales delivery notes. */
class DeliveryNoteService
{
    public function ready(): bool
    {
        return db_connect()->tableExists('sales_delivery_invoices');
    }

    public function get(string $companyId, string $origin, string $id): array
    {
        if (!in_array($origin, ['sale', 'cycle'], true)) throw new RuntimeException('Origen de remito inválido.');
        $db = db_connect();
        $note = $db->table($origin === 'sale' ? 'sales' : 'sales_delivery_notes')
            ->where('company_id', $companyId)->where('id', $id)->get()->getRowArray();
        if (!$note) throw new RuntimeException('Remito no disponible para esta empresa.');
        if ($origin === 'sale') {
            $doc = $db->table('sales_document_types')->where('company_id', $companyId)->where('id', $note['document_type_id'])->get()->getRowArray();
            if (!$doc || !self::isDelivery($doc)) throw new RuntimeException('El documento no es un remito.');
            $note['stock_recorded'] = !empty($note['delivered_at']) && (int) $doc['impacts_stock'] === 1;
        } else {
            $note['stock_recorded'] = !empty($note['dispatched_at']);
        }
        $note['number'] = $note['sale_number'] ?? $note['delivery_number'];
        // Configuration may have changed since dispatch; actual outgoing movements take precedence.
        $note['stock_recorded'] = $note['stock_recorded'] || $db->table('inventory_movements')->where('company_id', $companyId)
            ->where('source_document', $note['number'])->where('reason', 'REMITO')->where('movement_type', 'egreso')->countAllResults() > 0;
        $note['origin'] = $origin;
        $note['items'] = $db->table($origin === 'sale' ? 'sale_items' : 'sales_delivery_note_items')
            ->where($origin === 'sale' ? 'sale_id' : 'sales_delivery_note_id', $id)
            ->orderBy($origin === 'sale' ? 'line_number' : 'sort_order')->get()->getResultArray();
        return $note;
    }

    public static function isDelivery(array $doc): bool
    {
        return ($doc['category'] ?? '') === 'delivery_note' || in_array(strtoupper($doc['code'] ?? ''), ['REMITO', 'RTO', 'RM'], true);
    }

    public function link(string $companyId, string $invoiceId): ?array
    {
        if (!$this->ready()) return null;
        return db_connect()->table('sales_delivery_invoices')->where('company_id', $companyId)->where('invoice_id', $invoiceId)->get()->getRowArray();
    }

    public function summary(string $companyId, string $origin, string $id): array
    {
        $note = $this->get($companyId, $origin, $id);
        $invoices = []; $legacy = []; $applied = [];
        if ($this->ready()) {
            $invoices = db_connect()->table('sales_delivery_invoices l')->select('l.*, s.sale_number, s.status, s.cae, s.arca_status')
                ->join('sales s', 's.id=l.invoice_id AND s.company_id=l.company_id')
                ->where('l.company_id', $companyId)->where($origin === 'sale' ? 'l.source_sale_id' : 'l.delivery_note_id', $id)
                ->orderBy('l.created_at')->get()->getResultArray();
            foreach ($invoices as $invoice) {
                // Returns and fiscal errors do not erase the original invoice allocation.
                if (in_array($invoice['status'], ['draft', 'cancelled'], true)) continue;
                $lines = db_connect()->table('sales_delivery_invoice_items')->where('delivery_invoice_id', $invoice['id'])->get()->getResultArray();
                foreach ($lines as $line) {
                    $key = $line[$origin === 'sale' ? 'source_sale_item_id' : 'delivery_note_item_id'];
                    $applied[$key] = ($applied[$key] ?? 0) + self::units($line['quantity']);
                }
            }
        }
        // Never guess historical allocations from a product match.
        if ($origin === 'sale') {
            $legacy = db_connect()->table('sales s')->select('s.id, s.sale_number, s.status')
                ->join('sales_document_types d', 'd.id=s.document_type_id')
                ->where('s.company_id', $companyId)->where('s.source_sale_id', $id)->where('d.category', 'invoice')->where('s.status !=', 'cancelled')->get()->getResultArray();
            $linked = array_column($invoices, 'invoice_id');
            $legacy = array_values(array_filter($legacy, static fn($row) => !in_array($row['id'], $linked, true)));
        } elseif (!empty($note['sale_id'])) {
            $old = db_connect()->table('sales')->where('company_id', $companyId)->where('id', $note['sale_id'])->where('status !=', 'cancelled')->get()->getRowArray();
            if ($old && !in_array($old['id'], array_column($invoices, 'invoice_id'), true)) $legacy[] = $old;
        }
        // Distinguish legacy order invoices from allocations made by this circuit.
        if ($origin === 'cycle' && !empty($note['sales_order_id'])) {
            $known = $this->ready() ? $this->orderAllocations($companyId, $note['sales_order_id']) : [];
            $orderLines = db_connect()->table('sales_order_items')->where('sales_order_id', $note['sales_order_id'])->get()->getResultArray();
            foreach ($orderLines as $orderLine) {
                if (self::units($orderLine['quantity_invoiced']) !== self::units($known[$orderLine['id']] ?? 0)) {
                    $legacy[] = ['id' => $note['sales_order_id'], 'sale_number' => 'Pedido con facturación anterior o cantidades a conciliar', 'status' => 'review'];
                    break;
                }
            }
        }
        $total = 0; $billed = 0;
        foreach ($note['items'] as &$item) {
            $qty = self::units($item['quantity']) - self::units($item['returned_quantity'] ?? 0);
            $used = $applied[$item['id']] ?? 0;
            $item['billable_quantity'] = max(0, $qty) / 10000;
            $item['invoiced_quantity'] = $used / 10000;
            $item['pending_quantity'] = max(0, $qty - $used) / 10000;
            $total += max(0, $qty); $billed += $used;
        }
        unset($item);
        $reason = !$this->ready() ? 'Falta aplicar la migración de facturación de remitos.' : '';
        if ($legacy) $reason = 'Existen documentos anteriores sin trazabilidad por renglón. Requieren revisión antes de facturar o anular.';
        $financialReason = '';
        if ($origin === 'sale' && $note['status'] !== 'cancelled') {
            try { (new DeliveryNoteInvoicingService())->assertSourceFinancials($companyId, $note); }
            catch (RuntimeException $e) { $financialReason = $e->getMessage(); }
        }
        $active = $origin === 'sale' ? $note['status'] === 'confirmed' : in_array($note['status'], ['dispatched', 'delivered'], true);
        $remaining = max(0, $total - $billed);
        $note['invoices'] = $invoices;
        $note['legacy'] = $legacy;
        $note['billing_status'] = $billed <= 0 ? 'Sin facturar' : ($remaining > 0 ? 'Facturado parcialmente' : 'Facturado totalmente');
        $note['invoice_reason'] = $reason ?: ($financialReason ?: (!$active ? 'Primero confirma o despacha el remito.' : ($remaining <= 0 ? 'No quedan cantidades pendientes.' : '')));
        $live = array_filter($invoices, static fn($i) => $i['status'] !== 'cancelled');
        $note['cancel_reason'] = $reason ?: ($note['status'] === 'cancelled' ? 'El remito ya está anulado.' : ($live ? 'Hay facturas o borradores vinculados vigentes. Resuélvelos antes de anular el remito.' : ''));
        return $note;
    }

    public function orderAllocations(string $companyId, string $orderId): array
    {
        $rows = db_connect()->table('sales_delivery_invoice_items li')->select('ni.sales_order_item_id')->selectSum('li.quantity', 'quantity')
            ->join('sales_delivery_invoices l', 'l.id=li.delivery_invoice_id')
            ->join('sales_delivery_notes n', 'n.id=l.delivery_note_id AND n.company_id=l.company_id')
            ->join('sales_delivery_note_items ni', 'ni.id=li.delivery_note_item_id AND ni.sales_delivery_note_id=n.id')
            ->join('sales s', 's.id=l.invoice_id AND s.company_id=l.company_id')
            ->where('l.company_id', $companyId)->where('n.sales_order_id', $orderId)->whereNotIn('s.status', ['draft', 'cancelled'])
            ->groupBy('ni.sales_order_item_id')->get()->getResultArray();
        return array_column($rows, 'quantity', 'sales_order_item_id');
    }

    public static function units($quantity): int
    {
        if (!is_numeric($quantity) || !is_finite((float) $quantity) || abs((float) $quantity) > 99999999) throw new RuntimeException('Cantidad inválida.');
        $scaled = (float) $quantity * 10000;
        if (abs($scaled - round($scaled)) > 0.0001) throw new RuntimeException('La cantidad admite hasta cuatro decimales.');
        return (int) round($scaled);
    }

    public function lock(string $companyId, string $origin, string $id, callable $operation)
    {
        if (!in_array($origin, ['sale', 'cycle'], true)) throw new RuntimeException('Origen inválido.');
        return (new SalesIntegrityService())->locked(db_connect(), $origin === 'sale' ? 'sales' : 'sales_delivery_notes', $companyId, $id,
            ['draft', 'pending', 'confirmed', 'dispatched', 'delivered', 'cancelled'], $operation);
    }
}
