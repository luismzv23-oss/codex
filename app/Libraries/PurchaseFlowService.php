<?php

namespace App\Libraries;

use App\Models\{InventoryMovementModel, InventoryProductModel, InventoryWarehouseModel, PurchaseOrderItemModel, PurchaseOrderModel,
    PurchasePayableModel, PurchaseReceiptItemModel, PurchaseReceiptModel, SupplierCostHistoryModel, SupplierModel};
use RuntimeException;

/**
 * Single implementation of the purchase circuit: order -> approval -> receipt (stock, cost layer, payable, accounting).
 * Used by the Compras screens, the API and the invoice assistant. receive() must run inside
 * InventoryIntegrityService::transaction, like every stock mutation.
 */
class PurchaseFlowService
{
    public static function line(float $quantity, float $unitCost, float $taxRate): array
    {
        $net = round($quantity * $unitCost, 2);
        $tax = round($net * ($taxRate / 100), 2);

        return ['net' => $net, 'tax_amount' => $tax, 'line_total' => round($net + $tax, 2)];
    }

    public static function totals(array $items): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;
        foreach ($items as $item) {
            $subtotal += ((float) $item['line_total']) - ((float) $item['tax_amount']);
            $taxTotal += (float) $item['tax_amount'];
        }

        return ['subtotal' => round($subtotal, 2), 'tax_total' => round($taxTotal, 2), 'total' => round($subtotal + $taxTotal, 2)];
    }

    /** Builds order lines from [product_id, description, quantity, unit_cost, tax_rate]; invalid products or quantities are skipped. */
    public function orderLines(string $companyId, array $items): array
    {
        $rows = [];
        foreach ($items as $item) {
            $productId = trim((string) ($item['product_id'] ?? ''));
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitCost = round((float) ($item['unit_cost'] ?? 0), 4);
            $taxRate = (float) ($item['tax_rate'] ?? 0);
            if ($productId === '' || ! $this->product($companyId, $productId) || $quantity <= 0 || ! is_finite($quantity)) {
                continue;
            }
            if (! is_finite($unitCost) || ! is_finite($taxRate) || $unitCost < 0 || $taxRate < 0) {
                throw new RuntimeException('Costo o impuesto invalido.');
            }
            $line = self::line($quantity, $unitCost, $taxRate);
            $rows[] = ['product_id' => $productId, 'description' => trim((string) ($item['description'] ?? '')), 'quantity' => $quantity, 'received_quantity' => 0,
                'unit_cost' => $unitCost, 'tax_rate' => $taxRate, 'tax_amount' => $line['tax_amount'], 'line_total' => $line['line_total']];
        }

        return $rows;
    }

    /** $header: supplier_id, warehouse_id, currency_code, issued_at, expected_at, notes, branch_id. */
    public function createOrder(string $companyId, array $header, array $lines, string $userId): array
    {
        $supplierId = trim((string) ($header['supplier_id'] ?? ''));
        $warehouseId = trim((string) ($header['warehouse_id'] ?? ''));
        if (! $this->owned(new SupplierModel(), $companyId, $supplierId) || ! $this->owned(new InventoryWarehouseModel(), $companyId, $warehouseId)) {
            throw new RuntimeException('Debes seleccionar proveedor y deposito validos.');
        }
        if ($lines === []) {
            throw new RuntimeException('Debes agregar al menos un producto a la orden.');
        }
        $branchId = $header['branch_id'] ?? null;
        $totals = self::totals($lines);
        $orderId = (new PurchaseOrderModel())->insert([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'supplier_id' => $supplierId,
            'warehouse_id' => $warehouseId,
            'order_number' => $this->sequence($companyId, 'OCOMPRA', 'OC', $branchId),
            'status' => 'draft',
            'currency_code' => trim((string) ($header['currency_code'] ?? '')) ?: 'ARS',
            'subtotal' => $totals['subtotal'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'issued_at' => trim((string) ($header['issued_at'] ?? '')) ?: date('Y-m-d H:i:s'),
            'expected_at' => trim((string) ($header['expected_at'] ?? '')) ?: null,
            'notes' => trim((string) ($header['notes'] ?? '')),
            'created_by' => $userId,
        ], true);
        if (! $orderId) {
            throw new RuntimeException('No se pudo registrar la orden de compra.');
        }
        $itemModel = new PurchaseOrderItemModel();
        foreach ($lines as $line) {
            if (! $itemModel->insert(array_merge($line, ['purchase_order_id' => $orderId]))) {
                throw new RuntimeException('No se pudo registrar el detalle de la orden.');
            }
        }

        return (new PurchaseOrderModel())->find($orderId);
    }

    public function approveOrder(string $companyId, string $orderId, string $userId): array
    {
        $order = $this->owned(new PurchaseOrderModel(), $companyId, $orderId);
        if (! $order || ($order['status'] ?? '') !== 'draft') {
            throw new RuntimeException('La orden no esta disponible para aprobacion.');
        }
        if (! (new PurchaseOrderModel())->update($orderId, ['status' => 'approved', 'approved_at' => date('Y-m-d H:i:s'), 'approved_by' => $userId])) {
            throw new RuntimeException('No se pudo aprobar la orden.');
        }

        return (new PurchaseOrderModel())->find($orderId);
    }

    /**
     * Builds receipt lines from [purchase_order_item_id, quantity, unit_cost?, tax_rate?, lot_number?, serial_number?, expiration_date?].
     * Lines with quantity 0 are skipped.
     */
    public function receiptLines(string $orderId, array $items): array
    {
        $rows = [];
        $orderItemModel = new PurchaseOrderItemModel();
        foreach ($items as $item) {
            $orderItemId = trim((string) ($item['purchase_order_item_id'] ?? ''));
            $quantity = (float) ($item['quantity'] ?? 0);
            if ($quantity == 0) {
                continue;
            }
            if (! is_finite($quantity) || $quantity < 0) {
                throw new RuntimeException('Cantidad invalida.');
            }
            $orderItem = $orderItemId !== '' ? $orderItemModel->find($orderItemId) : null;
            if (! $orderItem || (string) ($orderItem['purchase_order_id'] ?? '') !== $orderId) {
                throw new RuntimeException('La linea o cantidad solicitada no es valida.');
            }
            $pending = (float) ($orderItem['quantity'] ?? 0) - (float) ($orderItem['received_quantity'] ?? 0);
            if ($quantity > $pending + 0.000001) {
                throw new RuntimeException('La linea o cantidad solicitada no es valida.');
            }
            $unitCost = round((float) (($item['unit_cost'] ?? '') !== '' ? $item['unit_cost'] : ($orderItem['unit_cost'] ?? 0)), 4);
            $taxRate = (float) (($item['tax_rate'] ?? '') !== '' ? $item['tax_rate'] : ($orderItem['tax_rate'] ?? 0));
            if (! is_finite($unitCost) || ! is_finite($taxRate) || $unitCost < 0 || $taxRate < 0) {
                throw new RuntimeException('Costo o impuesto invalido.');
            }
            $line = self::line($quantity, $unitCost, $taxRate);
            $rows[] = ['purchase_order_item_id' => $orderItemId, 'product_id' => $orderItem['product_id'], 'quantity' => $quantity, 'unit_cost' => $unitCost,
                'tax_rate' => $taxRate, 'tax_amount' => $line['tax_amount'], 'line_total' => $line['line_total'],
                'lot_number' => trim((string) ($item['lot_number'] ?? '')), 'serial_number' => trim((string) ($item['serial_number'] ?? '')),
                'expiration_date' => trim((string) ($item['expiration_date'] ?? '')) ?: null];
        }

        return $rows;
    }

    /** $header: supplier_document, issued_at, notes, branch_id (numbering fallback). Adds stock, cost layers, payable and accounting. */
    public function receive(string $companyId, string $orderId, array $rows, array $header, string $userId): array
    {
        $order = $this->owned(new PurchaseOrderModel(), $companyId, $orderId);
        if (! $order) {
            throw new RuntimeException('La orden seleccionada no es valida.');
        }
        if ($rows === []) {
            throw new RuntimeException('Debes recepcionar al menos una linea de producto.');
        }
        (new PurchaseIntegrityService())->validateReceipt($companyId, $order, $rows);

        $totals = self::totals($rows);
        // Numbering falls back to the operator's branch when the order has none (previous controller behaviour).
        $sequenceBranch = $order['branch_id'] ?? ($header['branch_id'] ?? null);
        $receiptNumber = $this->sequence($companyId, 'RCOMPRA', 'REC', $sequenceBranch);
        $receiptId = (new PurchaseReceiptModel())->insert([
            'company_id' => $companyId,
            'branch_id' => $order['branch_id'] ?? null,
            'supplier_id' => $order['supplier_id'],
            'purchase_order_id' => $orderId,
            'warehouse_id' => $order['warehouse_id'],
            'receipt_number' => $receiptNumber,
            'supplier_document' => trim((string) ($header['supplier_document'] ?? '')),
            'status' => 'registered',
            'currency_code' => $order['currency_code'],
            'subtotal' => $totals['subtotal'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'issued_at' => trim((string) ($header['issued_at'] ?? '')) ?: date('Y-m-d H:i:s'),
            'received_at' => date('Y-m-d H:i:s'),
            'notes' => trim((string) ($header['notes'] ?? '')),
            'created_by' => $userId,
        ], true);
        if (! $receiptId) {
            throw new RuntimeException('No se pudo registrar la recepcion.');
        }

        $receiptItemModel = new PurchaseReceiptItemModel();
        $orderItemModel = new PurchaseOrderItemModel();
        $integrity = new InventoryIntegrityService();
        foreach ($rows as $row) {
            if (! $receiptItemModel->insert(array_merge($row, ['purchase_receipt_id' => $receiptId]))) {
                throw new RuntimeException('No se pudo registrar el detalle de la recepcion.');
            }
            if (! empty($row['purchase_order_item_id'])) {
                $orderItem = $orderItemModel->find($row['purchase_order_item_id']);
                if ($orderItem) {
                    $orderItemModel->update($orderItem['id'], ['received_quantity' => (float) ($orderItem['received_quantity'] ?? 0) + (float) $row['quantity']]);
                }
            }

            $integrity->validatePlace($companyId, $order['warehouse_id'], null);
            $integrity->changeStock($companyId, (string) $row['product_id'], (string) $order['warehouse_id'], (float) $row['quantity']);
            $movement = [
                'company_id' => $companyId,
                'product_id' => $row['product_id'],
                'movement_type' => 'ingreso',
                'quantity' => $row['quantity'],
                'unit_cost' => $row['unit_cost'],
                'total_cost' => $row['line_total'] - $row['tax_amount'],
                'destination_warehouse_id' => $order['warehouse_id'],
                'performed_by' => $userId,
                'reason' => 'recepcion_compra',
                'source_document' => $receiptNumber,
                'lot_number' => $row['lot_number'] ?? null,
                'serial_number' => $row['serial_number'] ?? null,
                'expiration_date' => $row['expiration_date'] ?? null,
                'notes' => 'Recepcion de compra',
                'occurred_at' => date('Y-m-d H:i:s'),
            ];
            $integrity->validateTrace($companyId, (string) $row['product_id'], $movement);
            $movementId = (new InventoryMovementModel())->insert($movement, true);
            if (! $movementId) {
                throw new RuntimeException('No se pudo registrar el movimiento de inventario.');
            }
            (new InventoryArtifactService())->sync($companyId, (string) $row['product_id'], (string) $movementId, $movement);

            (new SupplierCostHistoryModel())->insert([
                'company_id' => $companyId,
                'supplier_id' => $order['supplier_id'],
                'product_id' => $row['product_id'],
                'purchase_receipt_id' => $receiptId,
                'currency_code' => $order['currency_code'],
                'exchange_rate' => 1,
                'unit_cost' => (float) $row['unit_cost'],
                'observed_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $receivedTotal = 0.0;
        $orderedTotal = 0.0;
        foreach ($orderItemModel->where('purchase_order_id', $orderId)->findAll() as $item) {
            $receivedTotal += (float) ($item['received_quantity'] ?? 0);
            $orderedTotal += (float) ($item['quantity'] ?? 0);
        }
        (new PurchaseOrderModel())->update($orderId, ['status' => $receivedTotal >= $orderedTotal && $orderedTotal > 0 ? 'received_total' : 'received_partial']);

        if (! (new PurchasePayableModel())->insert([
            'company_id' => $companyId,
            'supplier_id' => $order['supplier_id'],
            'purchase_receipt_id' => $receiptId,
            'payable_number' => $this->sequence($companyId, 'PAGCP', 'CXP', $sequenceBranch),
            'status' => $totals['total'] > 0 ? 'pending' : 'paid',
            'currency_code' => $order['currency_code'],
            'total_amount' => $totals['total'],
            'paid_amount' => 0,
            'balance_amount' => $totals['total'],
            'due_date' => $this->dueDate((string) $order['supplier_id']),
        ])) {
            throw new RuntimeException('No se pudo generar la cuenta a pagar.');
        }

        service('accounting')->syncPurchaseReceipt($companyId, (string) $receiptId, $userId);
        \App\Libraries\Core\Events\BusinessEvents::stockReceived($companyId, (string) $receiptId);

        return (new PurchaseReceiptModel())->find($receiptId);
    }

    /** Approved orders of a supplier/currency that still have quantities to receive, with their pending lines. */
    public function pendingOrders(string $companyId, string $supplierId, string $currency): array
    {
        $orders = (new PurchaseOrderModel())->where('company_id', $companyId)->where('supplier_id', $supplierId)->where('currency_code', $currency)
            ->whereIn('status', ['approved', 'received_partial'])->orderBy('created_at', 'DESC')->findAll(50);
        $result = [];
        foreach ($orders as $order) {
            $lines = [];
            foreach ((new PurchaseOrderItemModel())->where('purchase_order_id', $order['id'])->findAll() as $item) {
                $pending = round((float) $item['quantity'] - (float) $item['received_quantity'], 2);
                if ($pending > 0) {
                    $lines[] = $item + ['pending_quantity' => $pending];
                }
            }
            if ($lines) {
                $result[] = $order + ['lines' => $lines];
            }
        }

        return $result;
    }

    private function product(string $companyId, string $productId): ?array
    {
        return (new InventoryProductModel())->where('company_id', $companyId)->where('id', $productId)->first();
    }

    private function owned($model, string $companyId, string $id): ?array
    {
        return $id === '' ? null : $model->where('company_id', $companyId)->where('id', $id)->first();
    }

    private function sequence(string $companyId, string $type, string $prefix, ?string $branchId): string
    {
        return (new VoucherSequenceService())->next($companyId, $type, $prefix, $branchId, false);
    }

    private function dueDate(string $supplierId): ?string
    {
        $supplier = (new SupplierModel())->find($supplierId);
        $days = (int) ($supplier['payment_terms_days'] ?? 0);

        return $days > 0 ? date('Y-m-d H:i:s', strtotime('+' . $days . ' days')) : date('Y-m-d H:i:s');
    }
}
