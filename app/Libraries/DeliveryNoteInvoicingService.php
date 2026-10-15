<?php
namespace App\Libraries;

use App\Models\{SaleModel, SaleItemModel};
use RuntimeException;

class DeliveryNoteInvoicingService
{
    /** The same document binding and channel intersection are enforced in the form and POST. */
    public static function pointSupportsDocument(array $point, array $document): bool
    {
        if (!empty($point['document_type_id']) && $point['document_type_id'] !== ($document['id'] ?? '')) return false;
        $pointChannel = $point['channel'] ?? 'standard';
        $documentChannel = $document['channel'] ?? 'standard';
        if (!in_array($pointChannel, ['standard', 'kiosk', 'both'], true) || !in_array($documentChannel, ['standard', 'kiosk', 'both'], true)) return false;
        return $pointChannel === 'both' || $documentChannel === 'both' || $pointChannel === $documentChannel;
    }

    public function prices(string $companyId, array $note, ?string $listId = null, ?string $taxId = null): array
    {
        $db = db_connect();
        $list = null; $tax = null;
        if ($note['origin'] === 'cycle') {
            if ($listId) {
                $list = $db->table('sales_price_lists')->where('company_id', $companyId)->where('id', $listId)->where('active', 1)->get()->getRowArray();
                if (!$list) throw new RuntimeException('Lista de precios no disponible.');
            }
            if ($taxId) $tax = $db->table('taxes')->where('company_id', $companyId)->where('id', $taxId)->where('active', 1)->get()->getRowArray();
            else $tax = $db->table('taxes')->where('company_id', $companyId)->where('active', 1)->where('is_default', 1)->get()->getRowArray();
            if (!$tax) throw new RuntimeException('Selecciona un impuesto activo para valorizar el remito.');
        }
        $effective = [];
        if ($note['origin'] === 'sale') {
            // Allocate header discounts and surcharges using the existing fiscal policy.
            [, $effective] = PaymentFiscalPolicy::forSale($note, $note['items']);
            $effective = array_column($effective, null, 'id');
        }
        foreach ($note['items'] as &$item) {
            $product = $db->table('inventory_products')->where('company_id', $companyId)->where('id', $item['product_id'])->get()->getRowArray();
            if (!$product) throw new RuntimeException('Un producto del remito no pertenece a la empresa.');
            $item['product_type'] = $product['product_type'] ?? 'simple';
            $item['unit'] = $product['unit'] ?? 'un';
            $item['unit_cost'] = (float) ($item['unit_cost'] ?? $product['cost_price'] ?? 0);
            if ($note['origin'] === 'sale') {
                $full = $effective[$item['id']];
                $item['billing_line_total'] = (float) $full['line_total'];
                $item['billing_price'] = (float) $full['unit_price'];
            } else {
                $price = (float) $product['sale_price'];
                if ($list) {
                    $entry = $db->table('sales_price_list_items')->where('price_list_id', $list['id'])->where('product_id', $item['product_id'])->get()->getRowArray();
                    if ($entry) $price = (float) $entry['price'];
                }
                if (!is_finite($price) || $price < 0) throw new RuntimeException('Precio vigente inválido.');
                $item['billing_price'] = $price;
                $item['billing_line_total'] = round($price * (float) $item['quantity'], 2);
                $item['tax_id'] = $tax['id']; $item['tax_rate'] = (float) $tax['rate'];
            }
        }
        unset($item);
        $note['price_list_id'] = $note['origin'] === 'sale' ? ($note['price_list_id'] ?? null) : ($list['id'] ?? null);
        $note['price_list_name'] = $note['origin'] === 'sale' ? ($note['price_list_name'] ?? null) : ($list['name'] ?? null);
        return $note;
    }

    public static function priceVersion(array $note): string
    {
        return hash('sha256', json_encode(array_map(static fn($line) => [$line['id'], $line['billing_line_total'] ?? null, $line['tax_rate'] ?? null, $line['invoiced_quantity'], $line['pending_quantity']], $note['items'])));
    }

    public function quote(array $note, string $mode, array $quantities): array
    {
        if (!in_array($mode, ['parcial', 'total'], true)) throw new RuntimeException('Modalidad de facturación inválida.');
        $known = array_column($note['items'], null, 'id');
        if (array_diff(array_keys($quantities), array_keys($known))) throw new RuntimeException('Se enviaron renglones ajenos al remito.');
        $lines = []; $all = true;
        foreach ($known as $id => $item) {
            $pending = DeliveryNoteService::units($item['pending_quantity']);
            $qty = $mode === 'total' ? $pending : DeliveryNoteService::units($quantities[$id] ?? 0);
            if ($qty < 0 || $qty > $pending) throw new RuntimeException('La cantidad a facturar supera el pendiente o es negativa.');
            if ($qty !== $pending) $all = false;
            if (!$qty) continue;
            $original = DeliveryNoteService::units($item['quantity']);
            $used = DeliveryNoteService::units($item['invoiced_quantity']);
            $cents = (int) round($item['billing_line_total'] * 100);
            // Cumulative apportionment keeps successive partial invoices balanced to cents.
            $gross = (int) round($cents * ($used + $qty) / $original) - (int) round($cents * $used / $original);
            $rate = (float) ($item['tax_rate'] ?? 0);
            if (!is_finite($rate) || $rate < 0) throw new RuntimeException('Alícuota inválida.');
            $net = (int) round($gross / (1 + $rate / 100));
            $lines[] = [
                'source_item_id' => $id, 'product_id' => $item['product_id'], 'sku' => $item['sku'] ?? null,
                'product_name' => $item['product_name'], 'product_type' => $item['product_type'], 'unit' => $item['unit'],
                'quantity' => $qty / 10000, 'unit_price' => $item['billing_price'], 'unit_cost' => $item['unit_cost'],
                'tax_id' => $item['tax_id'] ?? null, 'tax_rate' => $rate, 'discount_rate' => 0, 'discount_amount' => 0,
                'subtotal' => $net / 100, 'tax_total' => ($gross - $net) / 100, 'line_total' => $gross / 100, 'returned_quantity' => 0,
            ];
        }
        if (!$lines) throw new RuntimeException('Selecciona al menos una cantidad pendiente.');
        if ($mode === 'parcial' && $all) throw new RuntimeException('Seleccionaste todo el pendiente. Utiliza Facturar totalmente.');
        return $lines;
    }

    /** Caller holds the company and source document locks. No side effects until explicit POST. */
    public function save(string $companyId, string $origin, string $id, string $userId, array $input): array
    {
        $notes = new DeliveryNoteService(); $db = db_connect();
        if (!$notes->ready()) throw new RuntimeException('Falta aplicar la migración de remitos.');
        $key = (string) ($input['request_key'] ?? '');
        if (!preg_match('/^[a-f0-9-]{36}$/i', $key)) throw new RuntimeException('Identificador de operación inválido.');
        $link = $db->table('sales_delivery_invoices')->where('company_id', $companyId)->where('request_key', $key)->get()->getRowArray();
        $invoice = null;
        if ($link) {
            if (($link[$origin === 'sale' ? 'source_sale_id' : 'delivery_note_id'] ?? '') !== $id) throw new RuntimeException('La operación pertenece a otro remito.');
            $invoice = $db->table('sales')->where('company_id', $companyId)->where('id', $link['invoice_id'])->get()->getRowArray();
            if (!$invoice || $invoice['status'] === 'cancelled') throw new RuntimeException('La factura vinculada ya no está disponible.');
            if ($invoice['status'] !== 'draft') return ['invoice_id' => $invoice['id'], 'already_confirmed' => true];
        }
        $note = $notes->summary($companyId, $origin, $id);
        if ($note['invoice_reason']) throw new RuntimeException($note['invoice_reason']);
        $this->assertSourceFinancials($companyId, $note);
        $note = $this->prices($companyId, $note, $input['price_list_id'] ?? null, $input['tax_id'] ?? null);
        if (($input['price_version'] ?? '') !== self::priceVersion($note)) throw new RuntimeException('Cambiaron los precios, impuestos o cantidades pendientes. Revisa los importes actualizados antes de confirmar.');
        $mode = (string) ($input['mode'] ?? '');
        $lines = $this->quote($note, $mode, (array) ($input['quantities'] ?? []));
        $doc = $db->table('sales_document_types')->where('company_id', $companyId)->where('id', (string) ($input['document_type_id'] ?? ''))->where('category', 'invoice')->where('active', 1)->get()->getRowArray();
        if (!$doc) throw new RuntimeException('Selecciona un tipo de factura activo.');
        $point = $db->table('sales_points_of_sale')->where('company_id', $companyId)->where('id', (string) ($input['point_of_sale_id'] ?? ''))->where('active', 1)->get()->getRowArray();
        if (!$point || !self::pointSupportsDocument($point, $doc)) throw new RuntimeException('El punto de venta no corresponde al comprobante seleccionado.');
        if (!empty($doc['requires_customer']) && empty($note['customer_id'])) throw new RuntimeException('Este comprobante requiere un cliente identificado en el remito.');
        $customer = !empty($note['customer_id']) ? $db->table('customers')->where('company_id', $companyId)->where('id', $note['customer_id'])->get()->getRowArray() : null;
        if (!empty($note['customer_id']) && !$customer) throw new RuntimeException('Cliente no disponible en esta empresa.');
        $subtotal = round(array_sum(array_column($lines, 'subtotal')), 2);
        $taxTotal = round(array_sum(array_column($lines, 'tax_total')), 2);
        $sale = [
            'company_id' => $companyId, 'branch_id' => $note['branch_id'] ?? null,
            'customer_id' => $note['customer_id'] ?: null, 'warehouse_id' => $note['warehouse_id'] ?: null,
            'document_type_id' => $doc['id'], 'document_code' => $doc['code'], 'point_of_sale_id' => $point['id'],
            'source_sale_id' => $origin === 'sale' ? $id : null,
            'issue_date' => date('Y-m-d H:i:s'), 'due_date' => date('Y-m-d'), 'status' => 'draft',
            'payment_status' => 'pending', 'reservation_status' => 'none', 'paid_total' => 0,
            'currency_code' => $note['currency_code'] ?? 'ARS', 'fiscal_profile' => $note['fiscal_profile'] ?? null, 'pos_mode' => (($doc['channel'] ?? '') === 'kiosk' || (($doc['channel'] ?? '') === 'both' && ($point['channel'] ?? '') === 'kiosk')) ? 1 : 0,
            'customer_name_snapshot' => $note['customer_name_snapshot'] ?? $customer['name'] ?? 'Consumidor Final',
            'customer_document_snapshot' => $note['customer_document_snapshot'] ?? $customer['document_number'] ?? null,
            'customer_tax_profile' => $note['customer_tax_profile'] ?? $customer['tax_profile'] ?? null,
            'price_list_id' => $note['price_list_id'], 'price_list_name' => $note['price_list_name'],
            'sales_agent_id' => $note['sales_agent_id'] ?? null, 'sales_zone_id' => $note['sales_zone_id'] ?? null,
            'sales_condition_id' => $note['sales_condition_id'] ?? null,
            'subtotal' => $subtotal, 'tax_total' => $taxTotal, 'total' => round($subtotal + $taxTotal, 2),
            'item_discount_total' => 0, 'global_discount_total' => 0, 'payment_surcharge_rate' => 0, 'payment_surcharge_amount' => 0,
            'margin_total' => round($subtotal - array_sum(array_map(static fn($l) => $l['quantity'] * $l['unit_cost'], $lines)), 2),
            'notes' => 'Facturación de remito ' . $note['number'],
        ];
        if ($invoice) {
            if ($invoice['document_type_id'] !== $doc['id']) throw new RuntimeException('Conserva el tipo de comprobante del borrador; su numeración ya fue asignada.');
            if ($db->table('sale_payments')->where('sale_id', $invoice['id'])->countAllResults()) throw new RuntimeException('El borrador tiene pagos asociados; requiere revisión antes de modificarlo.');
            $invoiceId = $invoice['id'];
            $db->table('sales_delivery_invoice_items')->where('delivery_invoice_id', $link['id'])->delete();
            $db->table('sale_items')->where('sale_id', $invoiceId)->delete();
            if (!(new SaleModel())->update($invoiceId, $sale)) throw new RuntimeException('No se pudo actualizar el borrador.');
            $db->table('sales_delivery_invoices')->where('id', $link['id'])->update(['mode' => $mode]);
        } else {
            $sale['sale_number'] = (new VoucherSequenceService())->next($companyId, $doc['sequence_key'], $doc['default_prefix'] ?: 'FC', $sale['branch_id']);
            $sale['created_by'] = $userId;
            $invoiceId = (new SaleModel())->insert($sale, true);
            if (!$invoiceId) throw new RuntimeException('No se pudo crear la factura.');
            $link = ['id' => app_uuid(), 'company_id' => $companyId, 'source_sale_id' => $origin === 'sale' ? $id : null,
                'delivery_note_id' => $origin === 'cycle' ? $id : null, 'invoice_id' => $invoiceId,
                'mode' => $mode, 'request_key' => $key, 'created_by' => $userId, 'created_at' => date('Y-m-d H:i:s')];
            $db->table('sales_delivery_invoices')->insert($link);
        }
        foreach ($lines as $index => $line) {
            $sourceId = $line['source_item_id']; unset($line['source_item_id']);
            $line['sale_id'] = $invoiceId; $line['line_number'] = $index + 1;
            $lineId = (new SaleItemModel())->insert($line, true);
            if (!$lineId) throw new RuntimeException('No se pudo guardar un renglón de factura.');
            $db->table('sales_delivery_invoice_items')->insert(['id' => app_uuid(), 'delivery_invoice_id' => $link['id'],
                'source_sale_item_id' => $origin === 'sale' ? $sourceId : null, 'delivery_note_item_id' => $origin === 'cycle' ? $sourceId : null,
                'invoice_item_id' => $lineId, 'quantity' => $line['quantity']]);
        }
        return ['invoice_id' => $invoiceId, 'already_confirmed' => false];
    }

    public function assertSourceFinancials(string $companyId, array $note): void
    {
        if ($note['origin'] !== 'sale') return;
        $db = db_connect();
        $issues = [];
        if ((float) ($note['paid_total'] ?? 0) > 0 || $db->table('sale_payments')->where('sale_id', $note['id'])->countAllResults()) $issues[] = 'cobros asociados';
        if ($db->table('sales_receivables')->where('company_id', $companyId)->where('sale_id', $note['id'])->where('balance_amount >', 0)->countAllResults()) $issues[] = 'saldo de cuenta corriente';
        if ($db->table('journal_entries')->where('company_id', $companyId)->where('reference_type', 'sale')->where('reference_id', $note['id'])->where('status', 'posted')->countAllResults()) $issues[] = 'asientos contables de venta';
        if ($issues) throw new RuntimeException('El remito ya registra ' . implode(', ', $issues) . '. Requiere conciliación antes de facturarse para evitar duplicaciones.');
    }

    /** Runs inside every invoice confirmation transaction (web and API). */
    public function validateConfirmation(string $companyId, array $sale, array $items): bool
    {
        $notes = new DeliveryNoteService();
        $link = $notes->link($companyId, $sale['id']);
        if (!$link) {
            if ($notes->ready()) {
                $order = db_connect()->table('sales_orders')->where('company_id', $companyId)->where('converted_to_sale_id', $sale['id'])->get()->getRowArray();
                if ($order && db_connect()->table('sales_delivery_notes')->where('company_id', $companyId)->where('sales_order_id', $order['id'])->where('status !=', 'cancelled')->countAllResults()) {
                    throw new RuntimeException('El pedido tiene remitos vigentes. Factura desde sus remitos para evitar duplicar cantidades.');
                }
            }
            if (!empty($sale['source_sale_id'])) {
                $source = db_connect()->table('sales s')->select('d.category, d.code')->join('sales_document_types d', 'd.id=s.document_type_id')
                    ->where('s.company_id', $companyId)->where('s.id', $sale['source_sale_id'])->get()->getRowArray();
                if ($source && DeliveryNoteService::isDelivery($source)) throw new RuntimeException('Factura originada en remito sin trazabilidad. Revisa el documento anterior y utiliza los botones de facturación del remito.');
            }
            return false;
        }
        $origin = $link['source_sale_id'] ? 'sale' : 'cycle'; $id = $link['source_sale_id'] ?: $link['delivery_note_id'];
        $note = $notes->summary($companyId, $origin, $id);
        if ($note['invoice_reason']) throw new RuntimeException($note['invoice_reason']);
        $this->assertSourceFinancials($companyId, $note);
        $lines = db_connect()->table('sales_delivery_invoice_items')->where('delivery_invoice_id', $link['id'])->get()->getResultArray();
        if (!$lines || count($lines) !== count($items)) throw new RuntimeException('La factura no conserva los renglones vinculados al remito.');
        $source = array_column($note['items'], null, 'id'); $target = array_column($items, null, 'id'); $requested = [];
        foreach ($lines as $line) {
            $sourceId = $line[$origin === 'sale' ? 'source_sale_item_id' : 'delivery_note_item_id'];
            $from = $source[$sourceId] ?? null; $to = $target[$line['invoice_item_id']] ?? null;
            $qty = DeliveryNoteService::units($line['quantity']);
            if (!$from || !$to || $qty <= 0 || $qty !== DeliveryNoteService::units($to['quantity']) || $to['product_id'] !== $from['product_id']) throw new RuntimeException('Vínculo de renglón de factura inválido.');
            $requested[$sourceId] = ($requested[$sourceId] ?? 0) + $qty;
        }
        foreach ($requested as $sourceId => $qty) {
            if ($qty > DeliveryNoteService::units($source[$sourceId]['pending_quantity'])) throw new RuntimeException('Otro comprobante consumió cantidades del remito. Reabre el popup para actualizar el pendiente.');
        }
        if ($link['mode'] === 'total') {
            foreach ($source as $sourceId => $item) {
                if (($requested[$sourceId] ?? 0) !== DeliveryNoteService::units($item['pending_quantity'])) throw new RuntimeException('Cambió el saldo pendiente de la facturación total. Reabre el popup para actualizarlo.');
            }
        }
        if ($sale['customer_id'] !== $note['customer_id'] || $sale['warehouse_id'] !== $note['warehouse_id']) throw new RuntimeException('La factura debe conservar el cliente y depósito del remito.');
        return true;
    }

    /** Recompute the originating order only from explicit item allocations, under the company lock. */
    public function syncOrder(string $companyId, string $invoiceId): void
    {
        $notes = new DeliveryNoteService(); $link = $notes->link($companyId, $invoiceId);
        if (!$link || empty($link['delivery_note_id'])) return;
        $note = $notes->get($companyId, 'cycle', $link['delivery_note_id']);
        if (empty($note['sales_order_id'])) return;
        $db = db_connect();
        $order = $db->table('sales_orders')->where('company_id', $companyId)->where('id', $note['sales_order_id'])->get()->getRowArray();
        if (!$order) throw new RuntimeException('Pedido de origen no disponible.');
        $totals = $notes->orderAllocations($companyId, $order['id']);
        $lines = $db->table('sales_order_items')->where('sales_order_id', $order['id'])->get()->getResultArray();
        $any = false; $complete = !empty($lines);
        foreach ($lines as $line) {
            $qty = $totals[$line['id']] ?? 0;
            if (DeliveryNoteService::units($qty) > DeliveryNoteService::units($line['quantity'])) throw new RuntimeException('La facturación acumulada supera la cantidad del pedido.');
            $db->table('sales_order_items')->where('id', $line['id'])->where('sales_order_id', $order['id'])->update(['quantity_invoiced' => $qty]);
            $any = $any || (float) $qty > 0 || (float) $line['quantity_delivered'] > 0;
            $complete = $complete && DeliveryNoteService::units($qty) === DeliveryNoteService::units($line['quantity']) && (float) $line['quantity_delivered'] >= (float) $line['quantity'];
        }
        if ($order['status'] !== 'cancelled') $db->table('sales_orders')->where('company_id', $companyId)->where('id', $order['id'])->update(['status' => $complete ? 'fulfilled' : ($any ? 'partial' : (!empty($order['approved_at']) ? 'approved' : 'pending')), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function assertInvoiceCanCancel(string $companyId, array $sale): void
    {
        if (!(new DeliveryNoteService())->link($companyId, $sale['id'])) return;
        if (in_array($sale['status'] ?? '', ['returned_partial', 'returned_total'], true)) throw new RuntimeException('La factura tiene devoluciones. Conserva su trazabilidad y resuelve la devolución antes de liberar el remito.');
        if (!empty($sale['cae']) || (!empty($sale['arca_status']) && !in_array(strtolower($sale['arca_status']), ['not_applicable', 'no aplica', 'not_required'], true))) {
            throw new RuntimeException('La factura tiene autorización o una gestión fiscal pendiente de resolución. No se puede liberar el remito mediante anulación directa.');
        }
    }
}
