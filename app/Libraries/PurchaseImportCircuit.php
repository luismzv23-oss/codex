<?php

namespace App\Libraries;

use App\Models\{PurchaseImportModel, PurchaseOrderItemModel, PurchaseOrderModel, PurchaseReceiptModel};
use RuntimeException;

/**
 * Guides an invoice import through the whole purchase circuit, one confirmed document at a time:
 * purchase order -> approval -> receipt (stock) -> invoice. Every document is produced by
 * PurchaseFlowService, the same rules used by the Compras screens. Each step is idempotent and
 * records its audit entry; the import keeps the ids so the assistant resumes where it stopped.
 */
class PurchaseImportCircuit
{
    private PurchaseImportService $imports;
    private PurchaseFlowService $flow;

    public function __construct(?PurchaseImportService $imports = null, ?PurchaseFlowService $flow = null)
    {
        $this->imports = $imports ?? new PurchaseImportService();
        $this->flow = $flow ?? new PurchaseFlowService();
    }

    /** Invoice lines expressed in stock units: packages x units, cost per unit with 4 decimals. */
    public static function stockLines(array $data): array
    {
        $lines = [];
        foreach (array_values($data['items'] ?? []) as $index => $item) {
            $productId = trim((string) ($item['product_id'] ?? ''));
            $quantity = (float) ($item['quantity'] ?? 0);
            $units = (float) (($item['units_per_package'] ?? '') !== '' ? $item['units_per_package'] : 1);
            if ($productId === '') {
                throw new RuntimeException('Vincula el concepto ' . ($index + 1) . ' con un producto del inventario antes de generar la orden.');
            }
            if ($quantity <= 0 || floor($quantity) !== $quantity) {
                throw new RuntimeException('El concepto ' . ($index + 1) . ' requiere una cantidad entera mayor que cero.');
            }
            if ($units <= 0) {
                throw new RuntimeException('Las unidades por bulto del concepto ' . ($index + 1) . ' deben ser mayores que cero.');
            }
            $lines[] = [
                'product_id' => $productId,
                'description' => trim((string) ($item['description'] ?? '')),
                'packages' => $quantity,
                'units_per_package' => $units,
                'quantity' => round($quantity * $units, 2),
                'unit_cost' => round((float) ($item['unit_cost'] ?? 0) / $units, 4),
                'tax_rate' => (float) ($item['tax_rate'] ?? 0),
            ];
        }
        if ($lines === []) {
            throw new RuntimeException('La factura no tiene conceptos para generar la orden de compra.');
        }

        return $lines;
    }

    public function state(string $companyId, string $id): array
    {
        $row = $this->imports->owned($companyId, $id);
        $draft = $this->imports->get($companyId, $id);
        $data = $draft['data'];
        $db = db_connect();
        $order = null;
        if (! empty($row['purchase_order_id'])) {
            $order = (new PurchaseOrderModel())->where('company_id', $companyId)->find($row['purchase_order_id']);
            if ($order) {
                $order['lines'] = $db->table('purchase_order_items poi')->select('poi.*, p.sku, p.name AS product_name, p.lot_control, p.serial_control, p.expiration_control')
                    ->join('inventory_products p', 'p.id = poi.product_id')->where('poi.purchase_order_id', $order['id'])->orderBy('poi.created_at')->get()->getResultArray();
                foreach ($order['lines'] as &$line) {
                    $line['pending_quantity'] = round((float) $line['quantity'] - (float) $line['received_quantity'], 2);
                }
                unset($line);
                $order['warehouse_name'] = $db->table('inventory_warehouses')->select('name')->where('id', $order['warehouse_id'])->get()->getRowArray()['name'] ?? '';
            }
        }
        $receipt = null;
        if (! empty($row['purchase_receipt_id'])) {
            $receipt = (new PurchaseReceiptModel())->where('company_id', $companyId)->find($row['purchase_receipt_id']);
            if ($receipt) {
                $receipt['payable'] = $db->table('purchase_payables')->select('id, payable_number, total_amount, balance_amount, status')
                    ->where('company_id', $companyId)->where('purchase_receipt_id', $receipt['id'])->get()->getRowArray();
            }
        }
        $preview = [];
        $previewError = null;
        try {
            $preview = self::stockLines($data);
        } catch (RuntimeException $e) {
            $previewError = $e->getMessage();
        }
        $supplier = (string) ($data['supplier_id'] ?? '');
        $currency = (string) ($data['currency_code'] ?? '');
        $pendingOrders = (! $order && ! $receipt && $supplier !== '' && $currency !== '') ? $this->flow->pendingOrders($companyId, $supplier, $currency) : [];
        $receipts = (! $order && ! $receipt && $supplier !== '' && $currency !== '')
            ? $db->table('purchase_receipts')->select('id, receipt_number, received_at, total')->where('company_id', $companyId)->where('supplier_id', $supplier)
                ->where('currency_code', $currency)->whereIn('status', ['registered', 'invoiced_partial'])->orderBy('created_at', 'DESC')->limit(50)->get()->getResultArray()
            : [];

        return [
            'kind' => $data['purchase_kind'] ?? 'goods',
            'order' => $order,
            'receipt' => $receipt,
            'preview' => $preview,
            'preview_error' => $previewError,
            'pending_orders' => $pendingOrders,
            'receipts' => $receipts,
            'warehouses' => $db->table('inventory_warehouses')->select('id, name, is_default')->where('company_id', $companyId)->where('active', 1)->orderBy('name')->get()->getResultArray(),
            'steps' => [
                'order' => (bool) $order || (bool) $receipt,
                'approval' => ($order && ! in_array($order['status'], ['draft'], true)) || (bool) $receipt,
                'receipt' => (bool) $receipt,
                'invoice' => $draft['review_status'] === 'registered',
            ],
        ];
    }

    /** Creates the purchase order from the invoice lines, or links an existing pending order of the supplier. */
    public function createOrder(string $companyId, string $id, array $input, string $userId, ?string $branchId): array
    {
        return $this->step($companyId, $id, $input, function (array $row, array $data) use ($companyId, $id, $input, $userId, $branchId): array {
            if (! empty($row['purchase_order_id']) || ! empty($row['purchase_receipt_id'])) {
                return [];
            }
            $this->requireGoods($data);
            $lines = self::stockLines($data);
            $existing = trim((string) ($input['order_id'] ?? ''));
            if ($existing !== '') {
                $order = (new PurchaseOrderModel())->where('company_id', $companyId)->find($existing);
                if (! $order || $order['supplier_id'] !== $data['supplier_id'] || $order['currency_code'] !== $data['currency_code']
                    || ! in_array($order['status'], ['draft', 'approved', 'received_partial'], true)) {
                    throw new RuntimeException('La orden elegida no corresponde a este proveedor y moneda, o ya no tiene pendientes.');
                }
                $this->imports->audit($companyId, $id, $userId, 'order_linked', ['purchase_order_id' => $order['id'], 'order_number' => $order['order_number']]);
            } else {
                $number = trim(($data['point_of_sale'] ?? '') . '-' . ($data['invoice_sequence'] ?? ''), '-') ?: ($data['invoice_number'] ?? '');
                $order = $this->flow->createOrder($companyId, [
                    'supplier_id' => $data['supplier_id'],
                    'warehouse_id' => trim((string) ($input['warehouse_id'] ?? '')),
                    'currency_code' => $data['currency_code'],
                    'notes' => 'Generada desde el asistente de facturas' . ($number !== '' ? ' (factura ' . $number . ')' : '') . '.',
                    'branch_id' => $branchId,
                ], $this->flow->orderLines($companyId, $lines), $userId);
                $this->imports->audit($companyId, $id, $userId, 'order_created', ['purchase_order_id' => $order['id'], 'order_number' => $order['order_number']]);
            }

            return ['purchase_order_id' => $order['id']];
        });
    }

    public function approveOrder(string $companyId, string $id, array $input, string $userId): array
    {
        return $this->step($companyId, $id, $input, function (array $row) use ($companyId, $id, $userId): array {
            $order = $this->linkedOrder($companyId, $row);
            if ($order['status'] !== 'draft') {
                return [];
            }
            $this->flow->approveOrder($companyId, $order['id'], $userId);
            $this->imports->audit($companyId, $id, $userId, 'order_approved', ['purchase_order_id' => $order['id']]);

            return ['version_only' => true];
        });
    }

    /** $input lines: [purchase_order_item_id, quantity, lot_number?, serial_number?, expiration_date?]. Adds the stock. */
    public function receive(string $companyId, string $id, array $input, string $userId, ?string $branchId): array
    {
        return $this->step($companyId, $id, $input, function (array $row, array $data) use ($companyId, $id, $input, $userId, $branchId): array {
            if (! empty($row['purchase_receipt_id'])) {
                return [];
            }
            $order = $this->linkedOrder($companyId, $row);
            $rows = $this->flow->receiptLines($order['id'], array_map(static fn($line): array => is_array($line) ? array_intersect_key($line,
                array_flip(['purchase_order_item_id', 'quantity', 'lot_number', 'serial_number', 'expiration_date'])) : [], (array) ($input['lines'] ?? [])));
            $number = trim(($data['point_of_sale'] ?? '') . '-' . ($data['invoice_sequence'] ?? ''), '-') ?: ($data['invoice_number'] ?? '');
            $receipt = $this->flow->receive($companyId, $order['id'], $rows, [
                'supplier_document' => trim((string) ($input['supplier_document'] ?? '')) ?: $number,
                'notes' => 'Recepcion desde el asistente de facturas.',
                'branch_id' => $branchId,
            ], $userId);
            $this->imports->audit($companyId, $id, $userId, 'received', ['purchase_receipt_id' => $receipt['id'], 'receipt_number' => $receipt['receipt_number']]);

            return ['purchase_receipt_id' => $receipt['id']];
        });
    }

    /** Links a receipt already registered from Compras (the goods entered the stock outside the assistant). */
    public function linkReceipt(string $companyId, string $id, array $input, string $userId): array
    {
        return $this->step($companyId, $id, $input, function (array $row, array $data) use ($companyId, $id, $input, $userId): array {
            if (! empty($row['purchase_order_id']) || ! empty($row['purchase_receipt_id'])) {
                throw new RuntimeException('Esta factura ya tiene una orden o recepción asociada.');
            }
            $this->requireGoods($data);
            $receipt = (new PurchaseReceiptModel())->where('company_id', $companyId)->find(trim((string) ($input['receipt_id'] ?? '')));
            if (! $receipt || $receipt['supplier_id'] !== $data['supplier_id'] || $receipt['currency_code'] !== $data['currency_code'] || $receipt['status'] === 'cancelled') {
                throw new RuntimeException('La recepción elegida no corresponde a este proveedor y moneda.');
            }
            $this->imports->audit($companyId, $id, $userId, 'receipt_linked', ['purchase_receipt_id' => $receipt['id']]);

            return ['purchase_receipt_id' => $receipt['id']];
        });
    }

    private function requireGoods(array $data): void
    {
        if (($data['purchase_kind'] ?? 'goods') !== 'goods') {
            throw new RuntimeException('Solo la mercadería con ingreso al stock genera orden de compra y recepción.');
        }
        if (empty($data['supplier_id']) || empty($data['currency_code'])) {
            throw new RuntimeException('Completa proveedor y moneda antes de generar la orden de compra.');
        }
    }

    private function linkedOrder(string $companyId, array $row): array
    {
        $order = ! empty($row['purchase_order_id']) ? (new PurchaseOrderModel())->where('company_id', $companyId)->find($row['purchase_order_id']) : null;
        if (! $order) {
            throw new RuntimeException('Primero genera la orden de compra.');
        }

        return $order;
    }

    /**
     * Runs one circuit step under the company lock, checks the draft version, stores the produced ids,
     * mirrors the receipt into the draft data and bumps the version. Returns draft + circuit state.
     */
    private function step(string $companyId, string $id, array $input, callable $operation): array
    {
        (new InventoryIntegrityService())->transaction($companyId, function () use ($companyId, $id, $input, $operation): void {
            $row = $this->imports->owned($companyId, $id);
            if ($row['review_status'] === 'registered') {
                throw new RuntimeException('La factura ya está registrada.');
            }
            if ((string) ($input['version'] ?? '') !== (string) $row['version']) {
                throw new RuntimeException('El borrador cambió en otra sesión. Recarga antes de continuar.');
            }
            $data = json_decode($row['data_json'] ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            $data['items'] = $this->imports->get($companyId, $id)['data']['items'];
            $changes = $operation($row, $data);
            if ($changes === []) {
                return;
            }
            $update = ['version' => (int) $row['version'] + 1];
            foreach (['purchase_order_id', 'purchase_receipt_id'] as $key) {
                if (isset($changes[$key])) {
                    $update[$key] = $changes[$key];
                }
            }
            if (isset($changes['purchase_receipt_id'])) {
                unset($data['items']);
                $data['purchase_kind'] = 'goods';
                $data['purchase_receipt_id'] = $changes['purchase_receipt_id'];
                $update['data_json'] = json_encode($data, JSON_THROW_ON_ERROR);
            }
            if (! (new PurchaseImportModel())->update($id, $update)) {
                throw new RuntimeException('No se pudo actualizar el borrador.');
            }
        });

        return ['draft' => $this->imports->get($companyId, $id), 'circuit' => $this->state($companyId, $id)];
    }
}
