<?php

namespace App\Controllers\Api\V1;

use App\Libraries\AccountingService;
use App\Libraries\PurchaseIntegrityService;
use App\Libraries\InventoryIntegrityService;
use App\Libraries\CashService;
use App\Models\BranchModel;
use App\Models\CashCheckModel;
use App\Models\CashPaymentGatewayModel;
use App\Models\CompanyModel;
use App\Models\CompanySystemModel;
use App\Models\InventoryMovementModel;
use App\Models\InventoryProductModel;
use App\Models\InventoryStockLevelModel;
use App\Models\InventoryWarehouseModel;
use App\Models\PurchaseCreditNoteModel;
use App\Models\PurchaseInvoiceItemModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\PurchaseOrderItemModel;
use App\Models\PurchaseOrderModel;
use App\Models\PurchasePayableModel;
use App\Models\PurchasePaymentModel;
use App\Models\PurchaseReceiptItemModel;
use App\Models\PurchaseReceiptModel;
use App\Models\PurchaseReturnItemModel;
use App\Models\PurchaseReturnModel;
use App\Models\SupplierModel;
use App\Models\SupplierCostHistoryModel;
use App\Models\SupplierExchangeDifferenceModel;
use App\Models\SystemModel;
use App\Models\UserSystemModel;
use App\Models\VoucherSequenceModel;

class PurchasesController extends BaseApiController
{
    public function index()
    {
        $context = $this->purchaseContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $companyId = $context['company']['id'];

        return $this->success([
            'company' => $context['company'],
            'access_level' => $context['access_level'],
            'summary' => $this->purchaseSummary($companyId),
            'suppliers' => $this->supplierRows($companyId),
            'orders' => $this->orderRows($companyId),
            'receipts' => $this->receiptRows($companyId),
            'payables' => $this->payableRows($companyId),
            'invoices' => $this->invoiceRows($companyId),
            'credit_notes' => $this->creditNoteRows($companyId),
            'cost_history' => $this->supplierCostRows($companyId),
        ]);
    }

    public function suppliers()
    {
        $context = $this->purchaseContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->supplierRows($context['company']['id']));
    }

    public function storeSupplier()
    {
        $context = $this->purchaseContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return $this->fail('Debes indicar el nombre del proveedor.', 422);
        }

        $id = (new SupplierModel())->insert([
            'company_id' => $context['company']['id'],
            'branch_id' => $this->apiUser()['branch_id'] ?? null,
            'name' => $name,
            'legal_name' => trim((string) ($payload['legal_name'] ?? '')),
            'tax_id' => trim((string) ($payload['tax_id'] ?? '')),
            'email' => trim((string) ($payload['email'] ?? '')),
            'phone' => trim((string) ($payload['phone'] ?? '')),
            'address' => trim((string) ($payload['address'] ?? '')),
            'vat_condition' => trim((string) ($payload['vat_condition'] ?? '')),
            'payment_terms_days' => max(0, (int) ($payload['payment_terms_days'] ?? 0)),
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : 1,
        ], true);

        return $this->success((new SupplierModel())->find($id), 201);
    }

    public function orders()
    {
        $context = $this->purchaseContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->orderRows($context['company']['id']));
    }

    public function storeOrder()
    {
        $context = $this->purchaseContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $companyId = $context['company']['id'];
        $payload = $this->payload();
        $supplierId = trim((string) ($payload['supplier_id'] ?? ''));
        $warehouseId = trim((string) ($payload['warehouse_id'] ?? ''));
        $items = $this->purchaseItemsPayload($companyId, (array) ($payload['items'] ?? []));

        if (! $this->ownedSupplier($companyId, $supplierId) || ! $this->ownedWarehouse($companyId, $warehouseId)) {
            return $this->fail('Debes seleccionar proveedor y deposito validos.', 422);
        }

        if ($items === []) {
            return $this->fail('Debes agregar al menos un producto a la orden.', 422);
        }

        $totals = $this->purchaseTotals($items);
        $orderId = (new PurchaseOrderModel())->insert([
            'company_id' => $companyId,
            'branch_id' => $this->apiUser()['branch_id'] ?? null,
            'supplier_id' => $supplierId,
            'warehouse_id' => $warehouseId,
            'order_number' => $this->nextSequenceNumber($companyId, 'OCOMPRA', 'OC'),
            'status' => 'draft',
            'currency_code' => trim((string) ($payload['currency_code'] ?? 'ARS')) ?: 'ARS',
            'subtotal' => $totals['subtotal'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'issued_at' => trim((string) ($payload['issued_at'] ?? '')) ?: date('Y-m-d H:i:s'),
            'expected_at' => trim((string) ($payload['expected_at'] ?? '')) ?: null,
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'created_by' => $this->apiUser()['id'],
        ], true);

        $itemModel = new PurchaseOrderItemModel();
        foreach ($items as $item) {
            $itemModel->insert(array_merge($item, ['purchase_order_id' => $orderId]));
        }

        return $this->success((new PurchaseOrderModel())->find($orderId), 201);
    }

    public function confirmOrder(string $id)
    {
        $context = $this->purchaseContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $order = $this->ownedOrder($context['company']['id'], $id);
        if (! $order || ($order['status'] ?? '') !== 'draft') {
            return $this->fail('La orden no esta disponible para aprobacion.', 422);
        }

        (new PurchaseOrderModel())->update($id, [
            'status' => 'approved',
            'approved_at' => date('Y-m-d H:i:s'),
            'approved_by' => $this->apiUser()['id'],
        ]);

        return $this->success((new PurchaseOrderModel())->find($id));
    }

    public function receipts()
    {
        $context = $this->purchaseContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->receiptRows($context['company']['id']));
    }

    public function invoices()
    {
        $context = $this->purchaseContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->invoiceRows($context['company']['id']));
    }

    public function creditNotes()
    {
        $context = $this->purchaseContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->creditNoteRows($context['company']['id']));
    }

    public function storeReceipt()
    {
        $context = $this->purchaseContext('manage');
        if (isset($context['error'])) { return $this->fail($context['error'], $context['status']); }
        try {
            return (new InventoryIntegrityService())->transaction($context['company']['id'], fn() => $this->storeReceiptLocked());
        } catch (\Throwable $e) {
            log_message('error', 'Compras: {message}', ['message' => $e->getMessage()]);
            return $this->fail($e->getMessage(), 422);
        }
    }

    private function storeReceiptLocked()
    {
        $context = $this->purchaseContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $companyId = $context['company']['id'];
        $payload = $this->payload();
        $orderId = trim((string) ($payload['purchase_order_id'] ?? ''));
        $order = $this->ownedOrder($companyId, $orderId);
        if (! $order) {
            return $this->fail('La orden seleccionada no es valida.', 422);
        }

        $rows = $this->receiptItemsPayload($companyId, $orderId, (array) ($payload['items'] ?? []));
        if ($rows === []) {
            return $this->fail('Debes recepcionar al menos una linea de producto.', 422);
        }

        (new PurchaseIntegrityService())->validateReceipt($companyId, $order, $rows);

        $db = db_connect();
        $db->transStart();

        $totals = $this->purchaseTotals($rows);
        $receiptNumber = $this->nextSequenceNumber($companyId, 'RCOMPRA', 'REC', $order['branch_id'] ?? null);
        $receiptId = (new PurchaseReceiptModel())->insert([
            'company_id' => $companyId,
            'branch_id' => $order['branch_id'] ?? null,
            'supplier_id' => $order['supplier_id'],
            'purchase_order_id' => $orderId,
            'warehouse_id' => $order['warehouse_id'],
            'receipt_number' => $receiptNumber,
            'supplier_document' => trim((string) ($payload['supplier_document'] ?? '')),
            'status' => 'registered',
            'currency_code' => $order['currency_code'],
            'subtotal' => $totals['subtotal'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'issued_at' => trim((string) ($payload['issued_at'] ?? '')) ?: date('Y-m-d H:i:s'),
            'received_at' => date('Y-m-d H:i:s'),
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'created_by' => $this->apiUser()['id'],
        ], true);

        $receiptItemModel = new PurchaseReceiptItemModel();
        $orderItemModel = new PurchaseOrderItemModel();

        foreach ($rows as $row) {
            $receiptItemModel->insert(array_merge($row, ['purchase_receipt_id' => $receiptId]));
            if (! empty($row['purchase_order_item_id'])) {
                $orderItem = $orderItemModel->find($row['purchase_order_item_id']);
                if ($orderItem) {
                    $orderItemModel->update($orderItem['id'], [
                        'received_quantity' => (float) ($orderItem['received_quantity'] ?? 0) + (float) $row['quantity'],
                    ]);
                }
            }

            $this->applyStockDelta($companyId, (string) $row['product_id'], (string) $order['warehouse_id'], (float) $row['quantity']);
            $this->registerInventoryMovement([
                'company_id' => $companyId,
                'product_id' => $row['product_id'],
                'movement_type' => 'ingreso',
                'quantity' => $row['quantity'],
                'unit_cost' => $row['unit_cost'],
                'total_cost' => $row['line_total'] - $row['tax_amount'],
                'destination_warehouse_id' => $order['warehouse_id'],
                'performed_by' => $this->apiUser()['id'],
                'reason' => 'recepcion_compra',
                'source_document' => $receiptNumber,
                'lot_number' => $row['lot_number'] ?? null,
                'serial_number' => $row['serial_number'] ?? null,
                'expiration_date' => $row['expiration_date'] ?? null,
                'notes' => 'Recepcion de compra',
            ]);

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

        $pending = $orderItemModel->where('purchase_order_id', $orderId)->findAll();
        $receivedTotal = 0.0;
        $orderedTotal = 0.0;
        foreach ($pending as $item) {
            $receivedTotal += (float) ($item['received_quantity'] ?? 0);
            $orderedTotal += (float) ($item['quantity'] ?? 0);
        }

        (new PurchaseOrderModel())->update($orderId, [
            'status' => $receivedTotal >= $orderedTotal && $orderedTotal > 0 ? 'received_total' : 'received_partial',
        ]);

        (new PurchasePayableModel())->insert([
            'company_id' => $companyId,
            'supplier_id' => $order['supplier_id'],
            'purchase_receipt_id' => $receiptId,
            'payable_number' => $this->nextSequenceNumber($companyId, 'PAGCP', 'CXP', $order['branch_id'] ?? null),
            'status' => $totals['total'] > 0 ? 'pending' : 'paid',
            'currency_code' => $order['currency_code'],
            'total_amount' => $totals['total'],
            'paid_amount' => 0,
            'balance_amount' => $totals['total'],
            'due_date' => $this->payableDueDate((string) $order['supplier_id']),
        ]);

        service('accounting')->syncPurchaseReceipt($companyId, (string) $receiptId, $this->apiUser()['id']);

        $db->transComplete();
        if (! $db->transStatus()) {
            return $this->fail('No se pudo registrar la recepcion.', 500);
        }

        return $this->success((new PurchaseReceiptModel())->find($receiptId), 201);
    }

    public function storeInvoice()
    {
        $context = $this->purchaseContext('manage');
        if (isset($context['error'])) { return $this->fail($context['error'], $context['status']); }
        try {
            return (new InventoryIntegrityService())->transaction($context['company']['id'], fn() => $this->storeInvoiceLocked());
        } catch (\Throwable $e) {
            log_message('error', 'Compras: {message}', ['message' => $e->getMessage()]);
            return $this->fail($e->getMessage(), 422);
        }
    }

    private function storeInvoiceLocked()
    {
        $context = $this->purchaseContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $companyId = $context['company']['id'];
        $supplierId = trim((string) ($payload['supplier_id'] ?? ''));
        $invoiceNumber = trim((string) ($payload['invoice_number'] ?? ''));
        if (! $this->ownedSupplier($companyId, $supplierId) || $invoiceNumber === '') {
            return $this->fail('Debes seleccionar proveedor y numero de factura.', 422);
        }

        $rows = $this->purchaseInvoiceItemsPayload($companyId, (array) ($payload['items'] ?? []));
        if ($rows === []) {
            return $this->fail('Debes agregar al menos una linea en la factura.', 422);
        }

        $purchaseReceiptId = trim((string) ($payload['purchase_receipt_id'] ?? '')) ?: null;
        $currencyCode = trim((string) ($payload['currency_code'] ?? '')) ?: 'ARS';
        $exchangeRate = (float) ($payload['exchange_rate'] ?? 1);
        if (! is_finite($exchangeRate) || $exchangeRate <= 0) { throw new \RuntimeException('Tipo de cambio invalido.'); }
        (new PurchaseIntegrityService())->validateInvoice($companyId, $supplierId, $purchaseReceiptId, $currencyCode, $rows);
        $totals = $this->purchaseTotals($rows);
        $invoiceId = (new PurchaseInvoiceModel())->insert([
            'company_id' => $companyId,
            'supplier_id' => $supplierId,
            'purchase_receipt_id' => trim((string) ($payload['purchase_receipt_id'] ?? '')) ?: null,
            'invoice_number' => $invoiceNumber,
            'currency_code' => $currencyCode,
            'exchange_rate' => $exchangeRate,
            'subtotal' => $totals['subtotal'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'issue_date' => trim((string) ($payload['issue_date'] ?? '')) ?: date('Y-m-d H:i:s'),
            'due_date' => trim((string) ($payload['due_date'] ?? '')) ?: null,
            'status' => 'registered',
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'created_by' => $this->apiUser()['id'] ?? null,
        ], true);

        $itemModel = new PurchaseInvoiceItemModel();
        foreach ($rows as $row) {
            $itemModel->insert(array_merge($row, ['purchase_invoice_id' => $invoiceId]));
            if (! empty($row['product_id'])) {
                (new SupplierCostHistoryModel())->insert([
                    'company_id' => $companyId,
                    'supplier_id' => $supplierId,
                    'product_id' => $row['product_id'],
                    'purchase_invoice_id' => $invoiceId,
                    'currency_code' => $currencyCode,
                    'exchange_rate' => $exchangeRate,
                    'unit_cost' => (float) $row['unit_cost'],
                    'observed_at' => trim((string) ($payload['issue_date'] ?? '')) ?: date('Y-m-d H:i:s'),
                ]);
            }
        }

        (new PurchaseIntegrityService())->syncInvoice($companyId, (string) $invoiceId);

        return $this->success((new PurchaseInvoiceModel())->find($invoiceId), 201);
    }

    public function storeCreditNote()
    {
        $context = $this->purchaseContext('manage');
        if (isset($context['error'])) { return $this->fail($context['error'], $context['status']); }
        try {
            return (new InventoryIntegrityService())->transaction($context['company']['id'], fn() => $this->storeCreditNoteLocked());
        } catch (\Throwable $e) {
            log_message('error', 'Compras: {message}', ['message' => $e->getMessage()]);
            return $this->fail($e->getMessage(), 422);
        }
    }

    private function storeCreditNoteLocked()
    {
        $context = $this->purchaseContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $companyId = $context['company']['id'];
        $supplierId = trim((string) ($payload['supplier_id'] ?? ''));
        $invoiceId = trim((string) ($payload['purchase_invoice_id'] ?? '')) ?: null;
        $amount = round((float) ($payload['amount'] ?? 0), 2);
        $creditNoteNumber = trim((string) ($payload['credit_note_number'] ?? ''));
        if (! $this->ownedSupplier($companyId, $supplierId) || $amount <= 0 || $creditNoteNumber === '') {
            return $this->fail('Debes indicar proveedor, monto y numero de nota.', 422);
        }

        $returnId = trim((string) ($payload['purchase_return_id'] ?? '')) ?: null;
        (new PurchaseIntegrityService())->validateCredit($companyId, $supplierId, $invoiceId, $amount, $returnId);

        $id = (new PurchaseCreditNoteModel())->insert([
            'company_id' => $companyId,
            'supplier_id' => $supplierId,
            'purchase_invoice_id' => trim((string) ($payload['purchase_invoice_id'] ?? '')) ?: null,
            'purchase_return_id' => $returnId,
            'credit_note_number' => $creditNoteNumber,
            'amount' => $amount,
            'issue_date' => trim((string) ($payload['issue_date'] ?? '')) ?: date('Y-m-d H:i:s'),
            'status' => 'issued',
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'created_by' => $this->apiUser()['id'] ?? null,
        ], true);

        (new PurchaseIntegrityService())->syncInvoice($companyId, (string) $invoiceId);

        return $this->success((new PurchaseCreditNoteModel())->find($id), 201);
    }

    public function storeReturn()
    {
        $context = $this->purchaseContext('manage');
        if (isset($context['error'])) { return $this->fail($context['error'], $context['status']); }
        try {
            return (new InventoryIntegrityService())->transaction($context['company']['id'], fn() => $this->storeReturnLocked());
        } catch (\Throwable $e) {
            log_message('error', 'Compras: {message}', ['message' => $e->getMessage()]);
            return $this->fail($e->getMessage(), 422);
        }
    }

    private function storeReturnLocked()
    {
        $context = $this->purchaseContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $companyId = $context['company']['id'];
        $payload = $this->payload();
        $receiptId = trim((string) ($payload['purchase_receipt_id'] ?? ''));
        $receipt = $this->ownedReceipt($companyId, $receiptId);
        if (! $receipt) {
            return $this->fail('La recepcion no es valida.', 422);
        }

        $rows = $this->returnItemsPayload($companyId, $receiptId, (array) ($payload['items'] ?? []));
        if ($rows === []) {
            return $this->fail('Debes indicar al menos una linea para devolver.', 422);
        }

        (new PurchaseIntegrityService())->validateReturn($companyId, $receipt, $rows);

        $db = db_connect();
        $db->transStart();

        $totals = $this->purchaseTotals($rows);
        $returnNumber = $this->nextSequenceNumber($companyId, 'DEVPROV', 'DVP', $receipt['branch_id'] ?? null);
        $returnId = (new PurchaseReturnModel())->insert([
            'company_id' => $companyId,
            'branch_id' => $receipt['branch_id'] ?? null,
            'supplier_id' => $receipt['supplier_id'],
            'purchase_receipt_id' => $receiptId,
            'warehouse_id' => $receipt['warehouse_id'],
            'return_number' => $returnNumber,
            'status' => 'issued',
            'subtotal' => $totals['subtotal'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'issued_at' => date('Y-m-d H:i:s'),
            'reason' => trim((string) ($payload['reason'] ?? '')),
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'created_by' => $this->apiUser()['id'],
        ], true);

        $returnItemModel = new PurchaseReturnItemModel();
        foreach ($rows as $row) {
            $returnItemModel->insert(array_merge($row, ['purchase_return_id' => $returnId]));
            $this->applyStockDelta($companyId, (string) $row['product_id'], (string) $receipt['warehouse_id'], ((float) $row['quantity']) * -1, ! empty($row['lot_number']) || ! empty($row['serial_number']));
            $this->registerInventoryMovement([
                'company_id' => $companyId,
                'product_id' => $row['product_id'],
                'movement_type' => 'egreso',
                'quantity' => $row['quantity'],
                'unit_cost' => $row['unit_cost'],
                'total_cost' => $row['line_total'] - $row['tax_amount'],
                'source_warehouse_id' => $receipt['warehouse_id'],
                'performed_by' => $this->apiUser()['id'],
                'reason' => 'devolucion_proveedor',
                'lot_number' => $row['lot_number'] ?? null,
                'serial_number' => $row['serial_number'] ?? null,
                'expiration_date' => $row['expiration_date'] ?? null,
                'source_document' => $returnNumber,
                'notes' => trim((string) ($payload['reason'] ?? '')) ?: 'Devolucion a proveedor',
            ]);
        }

        $this->applyReturnToPayable($companyId, $receiptId, $totals['total']);

        service('accounting')->syncPurchaseReturn($companyId, (string) $returnId, $this->apiUser()['id']);

        $db->transComplete();
        if (! $db->transStatus()) {
            return $this->fail('No se pudo registrar la devolucion.', 500);
        }

        return $this->success((new PurchaseReturnModel())->find($returnId), 201);
    }

    public function payables()
    {
        $context = $this->purchaseContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->payableRows($context['company']['id']));
    }

    public function storePayment()
    {
        $context = $this->purchaseContext('manage');
        if (isset($context['error'])) { return $this->fail($context['error'], $context['status']); }
        try {
            return (new InventoryIntegrityService())->transaction($context['company']['id'], fn() => $this->storePaymentLocked());
        } catch (\Throwable $e) {
            log_message('error', 'Compras: {message}', ['message' => $e->getMessage()]);
            return $this->fail($e->getMessage(), 422);
        }
    }

    private function storePaymentLocked()
    {
        $context = $this->purchaseContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $companyId = $context['company']['id'];
        $payload = $this->payload();
        $payableId = trim((string) ($payload['purchase_payable_id'] ?? ''));
        $payable = $this->ownedPayable($companyId, $payableId);
        $amount = round((float) ($payload['amount'] ?? 0), 2);
        $gatewayId = trim((string) ($payload['gateway_id'] ?? ''));
        $cashCheckId = trim((string) ($payload['cash_check_id'] ?? ''));
        $currencyCode = strtoupper(trim((string) ($payload['currency_code'] ?? ($payable['currency_code'] ?? 'ARS')))) ?: 'ARS';
        $exchangeRate = (float) ($payload['exchange_rate'] ?? 1);
        if (! is_finite($exchangeRate) || $exchangeRate <= 0) { throw new \RuntimeException('Tipo de cambio invalido.'); }
        $externalReference = trim((string) ($payload['external_reference'] ?? ''));

        if (! $payable) {
            return $this->fail('La cuenta a pagar no existe.', 404);
        }

        if (! is_finite($amount) || $amount <= 0 || $amount > (float) ($payable['balance_amount'] ?? 0)) {
            return $this->fail('El pago debe ser mayor a cero y no puede superar el saldo pendiente.', 422);
        }

        if ($gatewayId !== '' && ! (new CashPaymentGatewayModel())->where('company_id', $companyId)->where('id', $gatewayId)->where('active', 1)->first()) {
            return $this->fail('La pasarela seleccionada no existe.', 422);
        }

        if ($cashCheckId !== '' && ! (new CashCheckModel())->where('company_id', $companyId)->where('id', $cashCheckId)->first()) {
            return $this->fail('El cheque seleccionado no existe.', 422);
        }

        $db = db_connect();
        $db->transStart();

        $cashSession = (new PurchaseIntegrityService())->paymentSession($companyId, $payable, $currencyCode, $gatewayId, $cashCheckId);

        $paymentId = (new PurchasePaymentModel())->insert([
            'company_id' => $companyId,
            'supplier_id' => $payable['supplier_id'],
            'purchase_payable_id' => $payableId,
            'payment_number' => $this->nextSequenceNumber($companyId, 'PAGPROV', 'PP'),
            'payment_method' => trim((string) ($payload['payment_method'] ?? 'transferencia')) ?: 'transferencia',
            'amount' => $amount,
            'reference' => trim((string) ($payload['reference'] ?? '')),
            'gateway_id' => $gatewayId ?: null,
            'cash_check_id' => $cashCheckId ?: null,
            'currency_code' => $currencyCode,
            'exchange_rate' => $exchangeRate,
            'external_reference' => $externalReference,
            'paid_at' => trim((string) ($payload['paid_at'] ?? '')) ?: date('Y-m-d H:i:s'),
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'created_by' => $this->apiUser()['id'],
        ], true);

        if ($cashSession) {
            $cashMovementId = (new CashService())->registerMovement([
                'company_id' => $companyId,
                'cash_register_id' => $cashSession['cash_register_id'],
                'cash_session_id' => $cashSession['id'],
                'movement_type' => 'purchase_payment',
                'payment_method' => trim((string) ($payload['payment_method'] ?? 'transferencia')) ?: 'transferencia',
                'amount' => -1 * $amount,
                'reference_type' => 'purchase_payment',
                'reference_id' => $paymentId,
                'reference_number' => $payable['payable_number'],
                'gateway_id' => $gatewayId ?: null,
                'cash_check_id' => $cashCheckId ?: null,
                'external_reference' => $externalReference,
                'occurred_at' => trim((string) ($payload['paid_at'] ?? '')) ?: date('Y-m-d H:i:s'),
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'notes' => 'Pago a proveedor',
                'created_by' => $this->apiUser()['id'],
            ]);
            if (! $cashMovementId) { throw new \RuntimeException('No se pudo registrar el egreso de caja.'); }
        }

        $paidAmount = (float) ($payable['paid_amount'] ?? 0) + $amount;
        $balance = max(0, (float) ($payable['total_amount'] ?? 0) - $paidAmount);
        (new PurchasePayableModel())->update($payableId, [
            'paid_amount' => $paidAmount,
            'balance_amount' => $balance,
            'status' => $balance <= 0 ? 'paid' : 'partial',
        ]);

        if ($currencyCode !== 'ARS' || abs($exchangeRate - 1) > 0.000001) {
            (new SupplierExchangeDifferenceModel())->insert([
                'company_id' => $companyId,
                'supplier_id' => $payable['supplier_id'],
                'purchase_payment_id' => $paymentId,
                'currency_code' => $currencyCode,
                'base_rate' => (float) ($payable['exchange_rate'] ?? 1),
                'settlement_rate' => $exchangeRate,
                'difference_amount' => $amount * ($exchangeRate - (float) ($payable['exchange_rate'] ?? 1)),
                'observed_at' => trim((string) ($payload['paid_at'] ?? '')) ?: date('Y-m-d H:i:s'),
            ]);
        }

        service('accounting')->syncPurchasePayment($companyId, (string) $paymentId, $this->apiUser()['id']);

        $db->transComplete();
        if (! $db->transStatus()) {
            return $this->fail('No se pudo registrar el pago.', 500);
        }

        return $this->success((new PurchasePaymentModel())->find($paymentId), 201);
    }

    protected function purchaseContext(string $requiredAccess = 'view'): array
    {
        $companyId = $this->resolvePurchaseCompanyId();
        if (! $companyId) {
            return ['error' => 'Debes seleccionar una empresa para operar Compras.', 'status' => 422];
        }

        $company = (new CompanyModel())->find($companyId);
        if (! $company) {
            return ['error' => 'La empresa seleccionada no existe.', 'status' => 404];
        }

        $system = (new SystemModel())->where('slug', 'compras')->first();
        if (! $system || (int) ($system['active'] ?? 0) !== 1) {
            return ['error' => 'El sistema Compras no esta disponible.', 'status' => 404];
        }

        $accessLevel = 'view';
        $companyAssignment = (new CompanySystemModel())->where('company_id', $companyId)->where('system_id', $system['id'])->where('active', 1)->first();
        if (! $this->apiIsSuperadmin()) {
            if (! $companyAssignment) {
                return ['error' => 'La empresa no tiene Compras asignado.', 'status' => 403];
            }

            $userAssignment = (new UserSystemModel())->where('company_id', $companyId)->where('user_id', $this->apiUser()['id'] ?? '')->where('system_id', $system['id'])->where('active', 1)->first();
            if (! $userAssignment) {
                return ['error' => 'Tu usuario no tiene acceso activo a Compras.', 'status' => 403];
            }

            $accessLevel = $userAssignment['access_level'] ?? 'view';
        }

        if ($requiredAccess === 'manage' && ! $this->apiIsSuperadmin() && $accessLevel !== 'manage') {
            return ['error' => 'Tu usuario solo tiene acceso de consulta en Compras.', 'status' => 403];
        }

        $this->ensurePurchaseDefaults($companyId);

        return [
            'company' => $company,
            'system' => $system,
            'access_level' => $accessLevel,
        ];
    }

    private function resolvePurchaseCompanyId(): ?string
    {
        if ($this->apiIsSuperadmin()) {
            $companyId = trim((string) ($this->request->getGet('company_id') ?: ($this->payload()['company_id'] ?? '')));
            if ($companyId !== '') {
                return $companyId;
            }

            $company = (new CompanyModel())->orderBy('name', 'ASC')->first();
            return $company['id'] ?? null;
        }

        return $this->apiCompanyId();
    }

    private function ensurePurchaseDefaults(string $companyId): void
    {
        $branch = (new BranchModel())->where('company_id', $companyId)->where('code', 'MAIN')->first();
        $sequenceModel = new VoucherSequenceModel();
        foreach ([['OCOMPRA', 'OC'], ['RCOMPRA', 'REC'], ['DEVPROV', 'DVP'], ['PAGCP', 'CXP'], ['PAGPROV', 'PP']] as [$type, $prefix]) {
            if (! $sequenceModel->where('company_id', $companyId)->where('document_type', $type)->first()) {
                $sequenceModel->insert(['company_id' => $companyId, 'branch_id' => $branch['id'] ?? null, 'document_type' => $type, 'prefix' => $prefix, 'current_number' => 1, 'active' => 1]);
            }
        }

        $system = (new SystemModel())->where('slug', 'compras')->first();
        if ($system && ($system['entry_url'] ?? '') !== 'compras') {
            (new SystemModel())->update($system['id'], ['entry_url' => 'compras']);
        }
    }

    private function supplierRows(string $companyId): array
    {
        return (new SupplierModel())->where('company_id', $companyId)->orderBy('name', 'ASC')->findAll();
    }

    private function orderRows(string $companyId): array
    {
        return db_connect()->table('purchase_orders po')->select('po.*, s.name AS supplier_name, w.name AS warehouse_name')->join('suppliers s', 's.id = po.supplier_id')->join('inventory_warehouses w', 'w.id = po.warehouse_id', 'left')->where('po.company_id', $companyId)->orderBy('po.issued_at', 'DESC')->get()->getResultArray();
    }

    private function receiptRows(string $companyId): array
    {
        return db_connect()->table('purchase_receipts pr')->select('pr.*, s.name AS supplier_name, w.name AS warehouse_name, po.order_number')->join('suppliers s', 's.id = pr.supplier_id')->join('inventory_warehouses w', 'w.id = pr.warehouse_id')->join('purchase_orders po', 'po.id = pr.purchase_order_id', 'left')->where('pr.company_id', $companyId)->orderBy('pr.received_at', 'DESC')->get()->getResultArray();
    }

    private function payableRows(string $companyId): array
    {
        return db_connect()->table('purchase_payables pp')->select('pp.*, s.name AS supplier_name, pr.receipt_number')->join('suppliers s', 's.id = pp.supplier_id')->join('purchase_receipts pr', 'pr.id = pp.purchase_receipt_id', 'left')->where('pp.company_id', $companyId)->orderBy('pp.created_at', 'DESC')->get()->getResultArray();
    }

    private function invoiceRows(string $companyId): array
    {
        return db_connect()->table('purchase_invoices pi')
            ->select('pi.*, s.name AS supplier_name, pr.receipt_number')
            ->join('suppliers s', 's.id = pi.supplier_id')
            ->join('purchase_receipts pr', 'pr.id = pi.purchase_receipt_id', 'left')
            ->where('pi.company_id', $companyId)
            ->orderBy('pi.issue_date', 'DESC')
            ->get()
            ->getResultArray();
    }

    private function creditNoteRows(string $companyId): array
    {
        return db_connect()->table('purchase_credit_notes pcn')
            ->select('pcn.*, s.name AS supplier_name, pi.invoice_number')
            ->join('suppliers s', 's.id = pcn.supplier_id')
            ->join('purchase_invoices pi', 'pi.id = pcn.purchase_invoice_id', 'left')
            ->where('pcn.company_id', $companyId)
            ->orderBy('pcn.issue_date', 'DESC')
            ->get()
            ->getResultArray();
    }

    private function supplierCostRows(string $companyId): array
    {
        return db_connect()->table('supplier_cost_history sch')
            ->select('sch.*, s.name AS supplier_name, p.name AS product_name, p.sku')
            ->join('suppliers s', 's.id = sch.supplier_id')
            ->join('inventory_products p', 'p.id = sch.product_id')
            ->where('sch.company_id', $companyId)
            ->orderBy('sch.observed_at', 'DESC')
            ->limit(20)
            ->get()
            ->getResultArray();
    }

    private function purchaseSummary(string $companyId): array
    {
        $orderModel = new PurchaseOrderModel();
        $receiptModel = new PurchaseReceiptModel();
        $payableModel = new PurchasePayableModel();

        return [
            'suppliers' => (new SupplierModel())->where('company_id', $companyId)->where('active', 1)->countAllResults(),
            'orders_draft' => $orderModel->where('company_id', $companyId)->where('status', 'draft')->countAllResults(),
            'orders_approved' => $orderModel->where('company_id', $companyId)->where('status', 'approved')->countAllResults(),
            'receipts' => $receiptModel->where('company_id', $companyId)->countAllResults(),
            'payables_pending' => $payableModel->where('company_id', $companyId)->whereIn('status', ['pending', 'partial'])->countAllResults(),
            'payables_balance' => (float) (($payableModel->selectSum('balance_amount', 'balance')->where('company_id', $companyId)->whereIn('status', ['pending', 'partial'])->first()['balance'] ?? 0)),
        ];
    }

    private function purchaseItemsPayload(string $companyId, array $items): array
    {
        $rows = [];
        foreach ($items as $item) {
            $productId = trim((string) ($item['product_id'] ?? ''));
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitCost = (float) ($item['unit_cost'] ?? 0);
            $taxRate = (float) ($item['tax_rate'] ?? 0);
            if (! $this->validCompanyProduct($companyId, $productId) || $quantity <= 0) {
                continue;
            }
            $net = round($quantity * $unitCost, 2);
            $taxAmount = round($net * ($taxRate / 100), 2);
            $rows[] = ['product_id' => $productId, 'description' => trim((string) ($item['description'] ?? '')), 'quantity' => $quantity, 'received_quantity' => 0, 'unit_cost' => $unitCost, 'tax_rate' => $taxRate, 'tax_amount' => $taxAmount, 'line_total' => round($net + $taxAmount, 2)];
        }
        return $rows;
    }

    private function purchaseInvoiceItemsPayload(string $companyId, array $items): array
    {
        $rows = [];
        foreach ($items as $item) {
            $productId = trim((string) ($item['product_id'] ?? '')) ?: null;
            $description = trim((string) ($item['description'] ?? ''));
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitCost = (float) ($item['unit_cost'] ?? 0);
            $taxRate = (float) ($item['tax_rate'] ?? 0);
            if ($description === '' && $quantity == 0) { continue; }
            if ($description === '' || ! is_finite($quantity) || ! is_finite($unitCost) || ! is_finite($taxRate) || $quantity <= 0 || $unitCost < 0 || $taxRate < 0) {
                throw new \RuntimeException('La linea de factura contiene cantidad, costo o impuesto invalido.');
            }
            if ($productId && ! $this->validCompanyProduct($companyId, $productId)) {
                throw new \RuntimeException('El producto no pertenece a la empresa.');
            }
            $net = round($quantity * $unitCost, 2);
            $taxAmount = round($net * ($taxRate / 100), 2);
            $rows[] = [
                'product_id' => $productId,
                'description' => $description,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'line_total' => round($net + $taxAmount, 2),
            ];
        }

        return $rows;
    }

    private function receiptItemsPayload(string $companyId, string $orderId, array $items): array
    {
        $rows = [];
        $orderItemModel = new PurchaseOrderItemModel();
        foreach ($items as $item) {
            $orderItemId = trim((string) ($item['purchase_order_item_id'] ?? ''));
            $quantity = (float) ($item['quantity'] ?? 0);
            if ($quantity == 0) { continue; }
            if (! is_finite($quantity) || $quantity < 0) { throw new \RuntimeException('Cantidad invalida.'); }
            $orderItem = $orderItemId !== '' ? $orderItemModel->find($orderItemId) : null;
            if (! $orderItem || (string) ($orderItem['purchase_order_id'] ?? '') !== $orderId || $quantity <= 0) {
                throw new \RuntimeException('La linea o cantidad solicitada no es valida.');
            }
            $pending = (float) ($orderItem['quantity'] ?? 0) - (float) ($orderItem['received_quantity'] ?? 0);
            if ($quantity > $pending) {
                throw new \RuntimeException('La linea o cantidad solicitada no es valida.');
            }
            $unitCost = (float) ($item['unit_cost'] ?? $orderItem['unit_cost'] ?? 0);
            $taxRate = (float) ($item['tax_rate'] ?? $orderItem['tax_rate'] ?? 0);
            if (! is_finite($unitCost) || ! is_finite($taxRate) || $unitCost < 0 || $taxRate < 0) { throw new \RuntimeException('Costo o impuesto invalido.'); }
            $net = round($quantity * $unitCost, 2);
            $taxAmount = round($net * ($taxRate / 100), 2);
            $rows[] = ['purchase_order_item_id' => $orderItemId, 'product_id' => $orderItem['product_id'], 'quantity' => $quantity, 'unit_cost' => $unitCost, 'tax_rate' => $taxRate, 'tax_amount' => $taxAmount, 'line_total' => round($net + $taxAmount, 2), 'lot_number' => trim((string) ($item['lot_number'] ?? '')), 'serial_number' => trim((string) ($item['serial_number'] ?? '')), 'expiration_date' => trim((string) ($item['expiration_date'] ?? '')) ?: null];
        }
        return $rows;
    }

    private function returnItemsPayload(string $companyId, string $receiptId, array $items): array
    {
        $rows = [];
        $receiptItemModel = new PurchaseReceiptItemModel();
        foreach ($items as $item) {
            $receiptItemId = trim((string) ($item['purchase_receipt_item_id'] ?? ''));
            $quantity = (float) ($item['quantity'] ?? 0);
            if ($quantity == 0) { continue; }
            if (! is_finite($quantity) || $quantity < 0) { throw new \RuntimeException('Cantidad invalida.'); }
            $receiptItem = $receiptItemId !== '' ? $receiptItemModel->find($receiptItemId) : null;
            if (! $receiptItem || (string) ($receiptItem['purchase_receipt_id'] ?? '') !== $receiptId || $quantity <= 0 || $quantity > (float) ($receiptItem['quantity'] ?? 0)) {
                throw new \RuntimeException('La linea o cantidad solicitada no es valida.');
            }
            $net = round($quantity * (float) $receiptItem['unit_cost'], 2);
            $taxAmount = round($net * (((float) $receiptItem['tax_rate']) / 100), 2);
            $rows[] = ['purchase_receipt_item_id' => $receiptItemId, 'product_id' => $receiptItem['product_id'], 'quantity' => $quantity, 'unit_cost' => (float) $receiptItem['unit_cost'], 'tax_rate' => (float) $receiptItem['tax_rate'], 'tax_amount' => $taxAmount, 'line_total' => round($net + $taxAmount, 2), 'lot_number' => $receiptItem['lot_number'] ?? null, 'serial_number' => $receiptItem['serial_number'] ?? null, 'expiration_date' => $receiptItem['expiration_date'] ?? null];
        }
        return $rows;
    }

    private function purchaseTotals(array $items): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;
        foreach ($items as $item) {
            $subtotal += ((float) $item['line_total']) - ((float) $item['tax_amount']);
            $taxTotal += (float) $item['tax_amount'];
        }
        return ['subtotal' => round($subtotal, 2), 'tax_total' => round($taxTotal, 2), 'total' => round($subtotal + $taxTotal, 2)];
    }
    private function nextSequenceNumber(string $companyId, string $documentType, string $defaultPrefix, ?string $branchId = null): string
    {
        $user = $this->apiUser();
        $branchId = $branchId ?? (($user['company_id'] ?? null) === $companyId ? ($user['branch_id'] ?? null) : null);
        return (new \App\Libraries\VoucherSequenceService())->next($companyId, $documentType, $defaultPrefix, $branchId, false);
    }

    private function validCompanyProduct(string $companyId, string $productId): bool
    {
        return (new InventoryProductModel())->where('company_id', $companyId)->where('id', $productId)->first() !== null;
    }

    private function ownedSupplier(string $companyId, string $supplierId): ?array
    {
        return (new SupplierModel())->where('company_id', $companyId)->where('id', $supplierId)->first();
    }

    private function ownedWarehouse(string $companyId, string $warehouseId): ?array
    {
        return (new InventoryWarehouseModel())->where('company_id', $companyId)->where('id', $warehouseId)->first();
    }

    private function ownedOrder(string $companyId, string $id): ?array
    {
        return (new PurchaseOrderModel())->where('company_id', $companyId)->where('id', $id)->first();
    }

    private function ownedReceipt(string $companyId, string $id): ?array
    {
        return (new PurchaseReceiptModel())->where('company_id', $companyId)->where('id', $id)->first();
    }

    private function ownedPayable(string $companyId, string $id): ?array
    {
        return (new PurchasePayableModel())->where('company_id', $companyId)->where('id', $id)->first();
    }

    private function payableDueDate(string $supplierId): ?string
    {
        $supplier = (new SupplierModel())->find($supplierId);
        $days = (int) ($supplier['payment_terms_days'] ?? 0);
        return $days > 0 ? date('Y-m-d H:i:s', strtotime('+' . $days . ' days')) : date('Y-m-d H:i:s');
    }

    private function applyReturnToPayable(string $companyId, string $receiptId, float $returnTotal): void
    {
        (new PurchaseIntegrityService())->syncReceipt($companyId, $receiptId);
    }

    private function applyStockDelta(string $companyId, string $productId, ?string $warehouseId, float $delta, bool $unlocatedTrace = false): void
    {
        $integrity = new InventoryIntegrityService();
        $integrity->validatePlace($companyId, $warehouseId, null);
        if (! $warehouseId || ($delta < 0 && $integrity->available($companyId, $productId, $warehouseId) + 0.000001 < -$delta)) {
            throw new \RuntimeException('Stock disponible insuficiente para devolver al proveedor.');
        }
        if ($delta < 0 && $unlocatedTrace) {
            $integrity->withdrawUnlocated($companyId, $productId, $warehouseId, -$delta);
        } else {
            $integrity->changeStock($companyId, $productId, $warehouseId, $delta);
        }
    }

    private function registerInventoryMovement(array $payload): void
    {
        $payload['occurred_at'] = $payload['occurred_at'] ?? date('Y-m-d H:i:s');
        (new InventoryIntegrityService())->validateTrace($payload['company_id'], $payload['product_id'], $payload);
        $id = (new InventoryMovementModel())->insert($payload, true);
        if (! $id) { throw new \RuntimeException('No se pudo registrar el movimiento de inventario.'); }
        (new \App\Libraries\InventoryArtifactService())->sync($payload['company_id'], $payload['product_id'], (string) $id, $payload);
    }

}
