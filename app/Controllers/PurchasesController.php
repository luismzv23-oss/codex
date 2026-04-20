<?php

namespace App\Controllers;

use App\Libraries\AccountingService;
use App\Libraries\EventBus;
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
use CodeIgniter\HTTP\RedirectResponse;

class PurchasesController extends BaseController
{
    public function index()
    {
        $context = $this->purchaseContext('view');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $companyId = $context['company']['id'];

        return view('purchases/index', [
            'pageTitle' => 'Compras',
            'context' => $context,
            'companies' => $this->purchaseCompanies(),
            'selectedCompanyId' => $companyId,
            'summary' => $this->purchaseSummary($companyId),
            'suppliers' => $this->supplierRows($companyId),
            'orders' => $this->orderRows($companyId),
            'receipts' => $this->receiptRows($companyId),
            'payables' => $this->payableRows($companyId),
            'invoices' => $this->invoiceRows($companyId),
            'creditNotes' => $this->creditNoteRows($companyId),
            'costHistory' => $this->supplierCostRows($companyId),
        ]);
    }

    public function createSupplierForm()
    {
        $context = $this->purchaseContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('purchases/forms/supplier', [
            'pageTitle' => 'Proveedor',
            'companyId' => $context['company']['id'],
            'formAction' => site_url('compras/proveedores'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeSupplier()
    {
        $context = $this->purchaseContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $companyId = $context['company']['id'];
        $name = trim((string) $this->request->getPost('name'));

        if ($name === '') {
            return redirect()->back()->withInput()->with('error', 'Debes indicar el nombre del proveedor.');
        }

        (new SupplierModel())->insert([
            'company_id' => $companyId,
            'branch_id' => $this->currentUser()['branch_id'] ?? null,
            'name' => $name,
            'legal_name' => trim((string) $this->request->getPost('legal_name')),
            'tax_id' => trim((string) $this->request->getPost('tax_id')),
            'email' => trim((string) $this->request->getPost('email')),
            'phone' => trim((string) $this->request->getPost('phone')),
            'address' => trim((string) $this->request->getPost('address')),
            'vat_condition' => trim((string) $this->request->getPost('vat_condition')),
            'payment_terms_days' => max(0, (int) $this->request->getPost('payment_terms_days')),
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ]);

        return $this->popupOrRedirect($this->purchaseRoute('compras', $companyId), 'Proveedor registrado correctamente.');
    }

    public function createOrderForm()
    {
        $context = $this->purchaseContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('purchases/forms/order', [
            'pageTitle' => 'Orden de compra',
            'companyId' => $context['company']['id'],
            'suppliers' => $this->supplierOptions($context['company']['id']),
            'warehouses' => $this->warehouseOptions($context['company']['id']),
            'products' => $this->productCatalog($context['company']['id']),
            'currencyOptions' => $this->companyCurrencyOptions($context['company']['id'], 'ARS'),
            'formAction' => site_url('compras/ordenes'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeOrder()
    {
        $context = $this->purchaseContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $companyId = $context['company']['id'];
        $supplierId = trim((string) $this->request->getPost('supplier_id'));
        $warehouseId = trim((string) $this->request->getPost('warehouse_id'));
        $items = $this->purchaseItemsPayload($companyId);

        if (! $this->ownedSupplier($companyId, $supplierId) || ! $this->ownedWarehouse($companyId, $warehouseId)) {
            return redirect()->back()->withInput()->with('error', 'Debes seleccionar proveedor y deposito validos.');
        }

        if ($items === []) {
            return redirect()->back()->withInput()->with('error', 'Debes agregar al menos un producto a la orden.');
        }

        $totals = $this->purchaseTotals($items);
        $orderId = (new PurchaseOrderModel())->insert([
            'company_id' => $companyId,
            'branch_id' => $this->currentUser()['branch_id'] ?? null,
            'supplier_id' => $supplierId,
            'warehouse_id' => $warehouseId,
            'order_number' => $this->nextSequenceNumber($companyId, 'OCOMPRA', 'OC'),
            'status' => 'draft',
            'currency_code' => trim((string) $this->request->getPost('currency_code')) ?: 'ARS',
            'subtotal' => $totals['subtotal'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'issued_at' => trim((string) $this->request->getPost('issued_at')) ?: date('Y-m-d H:i:s'),
            'expected_at' => trim((string) $this->request->getPost('expected_at')) ?: null,
            'notes' => trim((string) $this->request->getPost('notes')),
            'created_by' => $this->currentUser()['id'],
        ], true);

        $itemModel = new PurchaseOrderItemModel();
        foreach ($items as $item) {
            $itemModel->insert(array_merge($item, ['purchase_order_id' => $orderId]));
        }

        return $this->popupOrRedirect($this->purchaseRoute('compras', $companyId), 'Orden de compra registrada correctamente.');
    }

    public function confirmOrder(string $id)
    {
        $context = $this->purchaseContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $order = $this->ownedOrder($context['company']['id'], $id);
        if (! $order || ($order['status'] ?? '') !== 'draft') {
            return redirect()->to($this->purchaseRoute('compras', $context['company']['id']))->with('error', 'La orden no esta disponible para aprobacion.');
        }

        (new PurchaseOrderModel())->update($id, [
            'status' => 'approved',
            'approved_at' => date('Y-m-d H:i:s'),
            'approved_by' => $this->currentUser()['id'],
        ]);

        return redirect()->to($this->purchaseRoute('compras', $context['company']['id']))->with('message', 'Orden aprobada correctamente.');
    }

    public function createReceiptForm(string $orderId)
    {
        $context = $this->purchaseContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $order = $this->ownedOrder($context['company']['id'], $orderId);
        if (! $order) {
            return redirect()->to($this->purchaseRoute('compras', $context['company']['id']))->with('error', 'La orden de compra no existe.');
        }

        return view('purchases/forms/receipt', [
            'pageTitle' => 'Recepcion de compra',
            'companyId' => $context['company']['id'],
            'order' => $order,
            'supplier' => $this->ownedSupplier($context['company']['id'], (string) $order['supplier_id']),
            'warehouse' => $this->ownedWarehouse($context['company']['id'], (string) $order['warehouse_id']),
            'items' => $this->orderItems($orderId),
            'formAction' => site_url('compras/recepciones'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeReceipt()
    {
        $context = $this->purchaseContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $companyId = $context['company']['id'];
        $orderId = trim((string) $this->request->getPost('purchase_order_id'));
        $order = $this->ownedOrder($companyId, $orderId);

        if (! $order) {
            return redirect()->back()->withInput()->with('error', 'La orden seleccionada no es valida.');
        }

        $rows = $this->receiptItemsPayload($companyId, $orderId);
        if ($rows === []) {
            return redirect()->back()->withInput()->with('error', 'Debes recepcionar al menos una linea de producto.');
        }

        $db = db_connect();
        $db->transStart();

        $totals = $this->purchaseTotals($rows);
        $receiptNumber = $this->nextSequenceNumber($companyId, 'RCOMPRA', 'REC');
        $receiptId = (new PurchaseReceiptModel())->insert([
            'company_id' => $companyId,
            'branch_id' => $order['branch_id'] ?? null,
            'supplier_id' => $order['supplier_id'],
            'purchase_order_id' => $orderId,
            'warehouse_id' => $order['warehouse_id'],
            'receipt_number' => $receiptNumber,
            'supplier_document' => trim((string) $this->request->getPost('supplier_document')),
            'status' => 'registered',
            'currency_code' => $order['currency_code'],
            'subtotal' => $totals['subtotal'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'issued_at' => trim((string) $this->request->getPost('issued_at')) ?: date('Y-m-d H:i:s'),
            'received_at' => date('Y-m-d H:i:s'),
            'notes' => trim((string) $this->request->getPost('notes')),
            'created_by' => $this->currentUser()['id'],
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
                'performed_by' => $this->currentUser()['id'],
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
            'payable_number' => $this->nextSequenceNumber($companyId, 'PAGCP', 'CXP'),
            'status' => $totals['total'] > 0 ? 'pending' : 'paid',
            'currency_code' => $order['currency_code'],
            'total_amount' => $totals['total'],
            'paid_amount' => 0,
            'balance_amount' => $totals['total'],
            'due_date' => $this->payableDueDate((string) $order['supplier_id']),
        ]);

        (new AccountingService())->syncPurchaseReceipt($companyId, (string) $receiptId, $this->currentUser()['id']);
        EventBus::emit('inventory.stock_received', ['company_id' => $companyId, 'receipt_id' => $receiptId]);

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'No se pudo registrar la recepcion.');
        }

        return $this->popupOrRedirect($this->purchaseRoute('compras', $companyId), 'Recepcion registrada correctamente.');
    }

    public function createReturnForm(string $receiptId)
    {
        $context = $this->purchaseContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $receipt = $this->ownedReceipt($context['company']['id'], $receiptId);
        if (! $receipt) {
            return redirect()->to($this->purchaseRoute('compras', $context['company']['id']))->with('error', 'La recepcion no existe.');
        }

        return view('purchases/forms/return', [
            'pageTitle' => 'Devolucion a proveedor',
            'companyId' => $context['company']['id'],
            'receipt' => $receipt,
            'supplier' => $this->ownedSupplier($context['company']['id'], (string) $receipt['supplier_id']),
            'items' => $this->receiptItems($receiptId),
            'formAction' => site_url('compras/devoluciones'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeReturn()
    {
        $context = $this->purchaseContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $companyId = $context['company']['id'];
        $receiptId = trim((string) $this->request->getPost('purchase_receipt_id'));
        $receipt = $this->ownedReceipt($companyId, $receiptId);
        if (! $receipt) {
            return redirect()->back()->withInput()->with('error', 'La recepcion no es valida.');
        }

        $rows = $this->returnItemsPayload($companyId, $receiptId);
        if ($rows === []) {
            return redirect()->back()->withInput()->with('error', 'Debes indicar al menos una linea para devolver.');
        }

        $db = db_connect();
        $db->transStart();

        $totals = $this->purchaseTotals($rows);
        $returnNumber = $this->nextSequenceNumber($companyId, 'DEVPROV', 'DVP');
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
            'reason' => trim((string) $this->request->getPost('reason')),
            'notes' => trim((string) $this->request->getPost('notes')),
            'created_by' => $this->currentUser()['id'],
        ], true);

        $returnItemModel = new PurchaseReturnItemModel();
        foreach ($rows as $row) {
            $returnItemModel->insert(array_merge($row, ['purchase_return_id' => $returnId]));
            $this->applyStockDelta($companyId, (string) $row['product_id'], (string) $receipt['warehouse_id'], ((float) $row['quantity']) * -1);
            $this->registerInventoryMovement([
                'company_id' => $companyId,
                'product_id' => $row['product_id'],
                'movement_type' => 'egreso',
                'quantity' => $row['quantity'],
                'unit_cost' => $row['unit_cost'],
                'total_cost' => $row['line_total'] - $row['tax_amount'],
                'source_warehouse_id' => $receipt['warehouse_id'],
                'performed_by' => $this->currentUser()['id'],
                'reason' => 'devolucion_proveedor',
                'source_document' => $returnNumber,
                'notes' => trim((string) $this->request->getPost('reason')) ?: 'Devolucion a proveedor',
            ]);
        }

        $this->applyReturnToPayable($companyId, $receiptId, $totals['total']);

        (new AccountingService())->syncPurchaseReturn($companyId, (string) $returnId, $this->currentUser()['id']);

        $db->transComplete();
        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'No se pudo registrar la devolucion.');
        }

        return $this->popupOrRedirect($this->purchaseRoute('compras', $companyId), 'Devolucion registrada correctamente.');
    }

    public function createPaymentForm(string $payableId)
    {
        $context = $this->purchaseContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $payable = $this->ownedPayable($context['company']['id'], $payableId);
        if (! $payable) {
            return redirect()->to($this->purchaseRoute('compras', $context['company']['id']))->with('error', 'La cuenta a pagar no existe.');
        }

        return view('purchases/forms/payment', [
            'pageTitle' => 'Pago a proveedor',
            'companyId' => $context['company']['id'],
            'payable' => $payable,
            'supplier' => $this->ownedSupplier($context['company']['id'], (string) $payable['supplier_id']),
            'gateways' => (new CashPaymentGatewayModel())->where('company_id', $context['company']['id'])->where('active', 1)->orderBy('name', 'ASC')->findAll(),
            'checks' => (new CashCheckModel())->where('company_id', $context['company']['id'])->orderBy('created_at', 'DESC')->findAll(),
            'formAction' => site_url('compras/pagos'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function createInvoiceForm()
    {
        $context = $this->purchaseContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('purchases/forms/invoice', [
            'pageTitle' => 'Factura proveedor',
            'companyId' => $context['company']['id'],
            'suppliers' => $this->supplierOptions($context['company']['id']),
            'receipts' => $this->receiptRows($context['company']['id']),
            'products' => $this->productCatalog($context['company']['id']),
            'currencyOptions' => $this->companyCurrencyOptions($context['company']['id'], 'ARS'),
            'formAction' => site_url('compras/facturas'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeInvoice()
    {
        $context = $this->purchaseContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $companyId = $context['company']['id'];
        $supplierId = trim((string) $this->request->getPost('supplier_id'));
        $invoiceNumber = trim((string) $this->request->getPost('invoice_number'));
        if (! $this->ownedSupplier($companyId, $supplierId) || $invoiceNumber === '') {
            return redirect()->back()->withInput()->with('error', 'Debes seleccionar proveedor y numero de factura.');
        }

        $rows = $this->purchaseInvoiceItemsPayload($companyId);
        if ($rows === []) {
            return redirect()->back()->withInput()->with('error', 'Debes agregar al menos una linea en la factura.');
        }

        $currencyCode = trim((string) $this->request->getPost('currency_code')) ?: 'ARS';
        $exchangeRate = max(0.000001, (float) $this->request->getPost('exchange_rate'));
        $totals = $this->purchaseTotals($rows);
        $invoiceId = (new PurchaseInvoiceModel())->insert([
            'company_id' => $companyId,
            'supplier_id' => $supplierId,
            'purchase_receipt_id' => trim((string) $this->request->getPost('purchase_receipt_id')) ?: null,
            'invoice_number' => $invoiceNumber,
            'currency_code' => $currencyCode,
            'exchange_rate' => $exchangeRate,
            'subtotal' => $totals['subtotal'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'issue_date' => trim((string) $this->request->getPost('issue_date')) ?: date('Y-m-d H:i:s'),
            'due_date' => trim((string) $this->request->getPost('due_date')) ?: null,
            'status' => 'registered',
            'notes' => trim((string) $this->request->getPost('notes')),
            'created_by' => $this->currentUser()['id'],
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
                    'observed_at' => trim((string) $this->request->getPost('issue_date')) ?: date('Y-m-d H:i:s'),
                ]);
            }
        }

        return $this->popupOrRedirect($this->purchaseRoute('compras', $companyId), 'Factura proveedor registrada correctamente.');
    }

    public function createCreditNoteForm()
    {
        $context = $this->purchaseContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('purchases/forms/credit_note', [
            'pageTitle' => 'Nota de credito proveedor',
            'companyId' => $context['company']['id'],
            'suppliers' => $this->supplierOptions($context['company']['id']),
            'invoices' => $this->invoiceRows($context['company']['id']),
            'formAction' => site_url('compras/notas-credito'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeCreditNote()
    {
        $context = $this->purchaseContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $companyId = $context['company']['id'];
        $supplierId = trim((string) $this->request->getPost('supplier_id'));
        $invoiceId = trim((string) $this->request->getPost('purchase_invoice_id')) ?: null;
        $amount = (float) $this->request->getPost('amount');
        $creditNoteNumber = trim((string) $this->request->getPost('credit_note_number'));
        if (! $this->ownedSupplier($companyId, $supplierId) || $amount <= 0 || $creditNoteNumber === '') {
            return redirect()->back()->withInput()->with('error', 'Debes indicar proveedor, monto y numero de nota.');
        }

        (new PurchaseCreditNoteModel())->insert([
            'company_id' => $companyId,
            'supplier_id' => $supplierId,
            'purchase_invoice_id' => $invoiceId,
            'credit_note_number' => $creditNoteNumber,
            'amount' => $amount,
            'issue_date' => trim((string) $this->request->getPost('issue_date')) ?: date('Y-m-d H:i:s'),
            'status' => 'issued',
            'notes' => trim((string) $this->request->getPost('notes')),
            'created_by' => $this->currentUser()['id'],
        ]);

        return $this->popupOrRedirect($this->purchaseRoute('compras', $companyId), 'Nota de credito registrada correctamente.');
    }

    public function storePayment()
    {
        $context = $this->purchaseContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $companyId = $context['company']['id'];
        $payableId = trim((string) $this->request->getPost('purchase_payable_id'));
        $payable = $this->ownedPayable($companyId, $payableId);
        $amount = (float) $this->request->getPost('amount');

        if (! $payable) {
            return redirect()->back()->withInput()->with('error', 'La cuenta a pagar no existe.');
        }

        if ($amount <= 0 || $amount > (float) ($payable['balance_amount'] ?? 0)) {
            return redirect()->back()->withInput()->with('error', 'El pago debe ser mayor a cero y no puede superar el saldo pendiente.');
        }

        $db = db_connect();
        $db->transStart();

        $paymentMethod = trim((string) $this->request->getPost('payment_method')) ?: 'transferencia';
        $gatewayId = trim((string) $this->request->getPost('gateway_id')) ?: null;
        $cashCheckId = trim((string) $this->request->getPost('cash_check_id')) ?: null;
        $currencyCode = trim((string) $this->request->getPost('currency_code')) ?: 'ARS';
        $exchangeRate = max(0.000001, (float) $this->request->getPost('exchange_rate'));
        $externalReference = trim((string) $this->request->getPost('external_reference')) ?: null;

        $paymentId = (new PurchasePaymentModel())->insert([
            'company_id' => $companyId,
            'supplier_id' => $payable['supplier_id'],
            'purchase_payable_id' => $payableId,
            'payment_number' => $this->nextSequenceNumber($companyId, 'PAGPROV', 'PP'),
            'payment_method' => $paymentMethod,
            'gateway_id' => $gatewayId,
            'cash_check_id' => $cashCheckId,
            'currency_code' => $currencyCode,
            'exchange_rate' => $exchangeRate,
            'amount' => $amount,
            'reference' => trim((string) $this->request->getPost('reference')),
            'external_reference' => $externalReference,
            'paid_at' => trim((string) $this->request->getPost('paid_at')) ?: date('Y-m-d H:i:s'),
            'notes' => trim((string) $this->request->getPost('notes')),
            'created_by' => $this->currentUser()['id'],
        ], true);

        $cashSession = (new CashService())->activeSessionForChannel($companyId, 'general');
        if ($cashSession) {
            (new CashService())->registerMovement([
                'company_id' => $companyId,
                'cash_register_id' => $cashSession['cash_register_id'],
                'cash_session_id' => $cashSession['id'],
                'movement_type' => 'purchase_payment',
                'payment_method' => $paymentMethod,
                'gateway_id' => $gatewayId,
                'cash_check_id' => $cashCheckId,
                'amount' => -1 * $amount,
                'reference_type' => 'purchase_payment',
                'reference_id' => $paymentId,
                'reference_number' => $payable['document_number'] ?? null,
                'external_reference' => $externalReference,
                'occurred_at' => trim((string) $this->request->getPost('paid_at')) ?: date('Y-m-d H:i:s'),
                'notes' => 'Pago a proveedor',
                'created_by' => $this->currentUser()['id'],
            ]);
        }

        if ($currencyCode !== 'ARS' && abs($exchangeRate - 1) > 0.000001) {
            (new SupplierExchangeDifferenceModel())->insert([
                'company_id' => $companyId,
                'purchase_payment_id' => $paymentId,
                'currency_code' => $currencyCode,
                'base_rate' => 1,
                'settlement_rate' => $exchangeRate,
                'difference_amount' => round(($exchangeRate - 1) * $amount, 2),
                'notes' => 'Diferencia de cambio al pago',
            ]);
        }

        $paidAmount = (float) ($payable['paid_amount'] ?? 0) + $amount;
        $balance = max(0, (float) ($payable['total_amount'] ?? 0) - $paidAmount);
        (new PurchasePayableModel())->update($payableId, [
            'paid_amount' => $paidAmount,
            'balance_amount' => $balance,
            'status' => $balance <= 0 ? 'paid' : 'partial',
        ]);

        (new AccountingService())->syncPurchasePayment($companyId, (string) $paymentId, $this->currentUser()['id']);
        EventBus::emit('purchase.payment_made', ['company_id' => $companyId, 'payment_id' => $paymentId, 'amount' => $amount, 'supplier_id' => $payable['supplier_id']]);

        $db->transComplete();
        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'No se pudo registrar el pago.');
        }

        return $this->popupOrRedirect($this->purchaseRoute('compras', $companyId), 'Pago registrado correctamente.');
    }

    private function purchaseContext(string $requiredAccess = 'view')
    {
        $companyId = $this->resolvePurchaseCompanyId();
        if (! $companyId) {
            return redirect()->to('/sistemas')->with('error', 'Debes seleccionar una empresa para operar Compras.');
        }

        $company = (new CompanyModel())->find($companyId);
        if (! $company) {
            return redirect()->to('/sistemas')->with('error', 'La empresa seleccionada no existe.');
        }

        $system = (new SystemModel())->where('slug', 'compras')->first();
        if (! $system || (int) ($system['active'] ?? 0) !== 1) {
            return redirect()->to('/sistemas')->with('error', 'El sistema Compras no esta disponible.');
        }

        $accessLevel = 'view';
        $companyAssignment = (new CompanySystemModel())->where('company_id', $companyId)->where('system_id', $system['id'])->where('active', 1)->first();
        if (! $this->isSuperadmin()) {
            if (! $companyAssignment) {
                return redirect()->to('/sistemas')->with('error', 'La empresa no tiene Compras asignado.');
            }

            $userAssignment = (new UserSystemModel())->where('company_id', $companyId)->where('user_id', $this->currentUser()['id'] ?? '')->where('system_id', $system['id'])->where('active', 1)->first();
            if (! $userAssignment) {
                return redirect()->to('/sistemas')->with('error', 'Tu usuario no tiene acceso activo a Compras.');
            }

            $accessLevel = $userAssignment['access_level'] ?? 'view';
        }

        if ($requiredAccess === 'manage' && ! $this->isSuperadmin() && $accessLevel !== 'manage') {
            return redirect()->to($this->purchaseRoute('compras', $companyId))->with('error', 'Tu usuario solo tiene acceso de consulta en Compras.');
        }

        $this->ensurePurchaseDefaults($companyId);

        return [
            'company' => $company,
            'system' => $system,
            'access_level' => $accessLevel,
            'canManage' => $this->isSuperadmin() || $accessLevel === 'manage',
        ];
    }

    private function resolvePurchaseCompanyId(): ?string
    {
        if ($this->isSuperadmin()) {
            $companyId = trim((string) ($this->request->getGet('company_id') ?: $this->request->getPost('company_id')));
            if ($companyId !== '') {
                return $companyId;
            }
            $company = (new CompanyModel())->orderBy('name', 'ASC')->first();
            return $company['id'] ?? null;
        }

        return $this->companyId();
    }

    private function purchaseCompanies(): array
    {
        return $this->isSuperadmin() ? (new CompanyModel())->orderBy('name', 'ASC')->findAll() : [];
    }

    private function purchaseRoute(string $path, ?string $companyId): string
    {
        if (! $this->isSuperadmin() || ! $companyId) {
            return site_url($path);
        }
        return site_url($path . '?company_id=' . $companyId);
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

    private function supplierOptions(string $companyId): array
    {
        return (new SupplierModel())->where('company_id', $companyId)->where('active', 1)->orderBy('name', 'ASC')->findAll();
    }

    private function warehouseOptions(string $companyId): array
    {
        return (new InventoryWarehouseModel())->where('company_id', $companyId)->where('active', 1)->orderBy('is_default', 'DESC')->orderBy('name', 'ASC')->findAll();
    }

    private function productCatalog(string $companyId): array
    {
        return (new InventoryProductModel())->where('company_id', $companyId)->where('active', 1)->orderBy('name', 'ASC')->findAll();
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
        return db_connect()->table('purchase_payables pp')->select('pp.*, s.name AS supplier_name, pr.receipt_number')->join('suppliers s', 's.id = pp.supplier_id')->join('purchase_receipts pr', 'pr.id = pp.purchase_receipt_id')->where('pp.company_id', $companyId)->orderBy('pp.created_at', 'DESC')->get()->getResultArray();
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

    private function purchaseItemsPayload(string $companyId): array
    {
        $productIds = (array) $this->request->getPost('items_product_id');
        $quantities = (array) $this->request->getPost('items_quantity');
        $costs = (array) $this->request->getPost('items_unit_cost');
        $rates = (array) $this->request->getPost('items_tax_rate');
        $descriptions = (array) $this->request->getPost('items_description');
        $rows = [];
        foreach ($productIds as $index => $productId) {
            $productId = trim((string) $productId);
            $quantity = (float) ($quantities[$index] ?? 0);
            $unitCost = (float) ($costs[$index] ?? 0);
            $taxRate = (float) ($rates[$index] ?? 0);
            if (! $this->validCompanyProduct($companyId, $productId) || $quantity <= 0) {
                continue;
            }
            $net = round($quantity * $unitCost, 2);
            $taxAmount = round($net * ($taxRate / 100), 2);
            $rows[] = ['product_id' => $productId, 'description' => trim((string) ($descriptions[$index] ?? '')), 'quantity' => $quantity, 'received_quantity' => 0, 'unit_cost' => $unitCost, 'tax_rate' => $taxRate, 'tax_amount' => $taxAmount, 'line_total' => round($net + $taxAmount, 2)];
        }
        return $rows;
    }

    private function purchaseInvoiceItemsPayload(string $companyId): array
    {
        $productIds = (array) $this->request->getPost('items_product_id');
        $descriptions = (array) $this->request->getPost('items_description');
        $quantities = (array) $this->request->getPost('items_quantity');
        $costs = (array) $this->request->getPost('items_unit_cost');
        $rates = (array) $this->request->getPost('items_tax_rate');
        $rows = [];
        foreach ($descriptions as $index => $description) {
            $productId = trim((string) ($productIds[$index] ?? '')) ?: null;
            $quantity = (float) ($quantities[$index] ?? 0);
            $unitCost = (float) ($costs[$index] ?? 0);
            $taxRate = (float) ($rates[$index] ?? 0);
            $description = trim((string) $description);
            if ($description === '' || $quantity <= 0) {
                continue;
            }
            if ($productId && ! $this->validCompanyProduct($companyId, $productId)) {
                $productId = null;
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

    private function receiptItemsPayload(string $companyId, string $orderId): array
    {
        $itemIds = (array) $this->request->getPost('items_order_item_id');
        $quantities = (array) $this->request->getPost('items_quantity');
        $costs = (array) $this->request->getPost('items_unit_cost');
        $rates = (array) $this->request->getPost('items_tax_rate');
        $lots = (array) $this->request->getPost('items_lot_number');
        $serials = (array) $this->request->getPost('items_serial_number');
        $expirations = (array) $this->request->getPost('items_expiration_date');
        $rows = [];
        $orderItemModel = new PurchaseOrderItemModel();
        foreach ($itemIds as $index => $orderItemId) {
            $orderItemId = trim((string) $orderItemId);
            $quantity = (float) ($quantities[$index] ?? 0);
            $orderItem = $orderItemId !== '' ? $orderItemModel->find($orderItemId) : null;
            if (! $orderItem || (string) ($orderItem['purchase_order_id'] ?? '') !== $orderId || $quantity <= 0) {
                continue;
            }
            $pending = (float) ($orderItem['quantity'] ?? 0) - (float) ($orderItem['received_quantity'] ?? 0);
            if ($quantity > $pending) {
                continue;
            }
            $unitCost = (float) ($costs[$index] ?? $orderItem['unit_cost'] ?? 0);
            $taxRate = (float) ($rates[$index] ?? $orderItem['tax_rate'] ?? 0);
            $net = round($quantity * $unitCost, 2);
            $taxAmount = round($net * ($taxRate / 100), 2);
            $rows[] = ['purchase_order_item_id' => $orderItemId, 'product_id' => $orderItem['product_id'], 'quantity' => $quantity, 'unit_cost' => $unitCost, 'tax_rate' => $taxRate, 'tax_amount' => $taxAmount, 'line_total' => round($net + $taxAmount, 2), 'lot_number' => trim((string) ($lots[$index] ?? '')), 'serial_number' => trim((string) ($serials[$index] ?? '')), 'expiration_date' => trim((string) ($expirations[$index] ?? '')) ?: null];
        }
        return $rows;
    }

    private function returnItemsPayload(string $companyId, string $receiptId): array
    {
        $itemIds = (array) $this->request->getPost('items_receipt_item_id');
        $quantities = (array) $this->request->getPost('items_quantity');
        $rows = [];
        $receiptItemModel = new PurchaseReceiptItemModel();
        foreach ($itemIds as $index => $receiptItemId) {
            $receiptItemId = trim((string) $receiptItemId);
            $quantity = (float) ($quantities[$index] ?? 0);
            $receiptItem = $receiptItemId !== '' ? $receiptItemModel->find($receiptItemId) : null;
            if (! $receiptItem || (string) ($receiptItem['purchase_receipt_id'] ?? '') !== $receiptId || $quantity <= 0 || $quantity > (float) ($receiptItem['quantity'] ?? 0)) {
                continue;
            }
            $net = round($quantity * (float) $receiptItem['unit_cost'], 2);
            $taxAmount = round($net * (((float) $receiptItem['tax_rate']) / 100), 2);
            $rows[] = ['purchase_receipt_item_id' => $receiptItemId, 'product_id' => $receiptItem['product_id'], 'quantity' => $quantity, 'unit_cost' => (float) $receiptItem['unit_cost'], 'tax_rate' => (float) $receiptItem['tax_rate'], 'tax_amount' => $taxAmount, 'line_total' => round($net + $taxAmount, 2)];
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

    private function nextSequenceNumber(string $companyId, string $documentType, string $prefix): string
    {
        $sequenceModel = new VoucherSequenceModel();
        $sequence = $sequenceModel->where('company_id', $companyId)->where('document_type', $documentType)->first();
        if (! $sequence) {
            $this->ensurePurchaseDefaults($companyId);
            $sequence = $sequenceModel->where('company_id', $companyId)->where('document_type', $documentType)->first();
        }
        $current = (int) ($sequence['current_number'] ?? 1);
        $formatted = strtoupper($prefix) . '-' . str_pad((string) $current, 8, '0', STR_PAD_LEFT);
        if (! empty($sequence['id'])) {
            $sequenceModel->update($sequence['id'], ['current_number' => $current + 1]);
        }
        return $formatted;
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

    private function orderItems(string $orderId): array
    {
        return db_connect()->table('purchase_order_items poi')->select('poi.*, p.sku, p.name AS product_name')->join('inventory_products p', 'p.id = poi.product_id')->where('poi.purchase_order_id', $orderId)->orderBy('p.name', 'ASC')->get()->getResultArray();
    }

    private function receiptItems(string $receiptId): array
    {
        return db_connect()->table('purchase_receipt_items pri')->select('pri.*, p.sku, p.name AS product_name')->join('inventory_products p', 'p.id = pri.product_id')->where('pri.purchase_receipt_id', $receiptId)->orderBy('p.name', 'ASC')->get()->getResultArray();
    }

    private function payableDueDate(string $supplierId): ?string
    {
        $supplier = (new SupplierModel())->find($supplierId);
        $days = (int) ($supplier['payment_terms_days'] ?? 0);
        return $days > 0 ? date('Y-m-d H:i:s', strtotime('+' . $days . ' days')) : date('Y-m-d H:i:s');
    }

    private function applyReturnToPayable(string $companyId, string $receiptId, float $returnTotal): void
    {
        $payableModel = new PurchasePayableModel();
        $payable = $payableModel->where('company_id', $companyId)->where('purchase_receipt_id', $receiptId)->first();
        if (! $payable) {
            return;
        }
        $newTotal = max(0, (float) ($payable['total_amount'] ?? 0) - $returnTotal);
        $paid = min((float) ($payable['paid_amount'] ?? 0), $newTotal);
        $balance = max(0, $newTotal - $paid);
        $payableModel->update($payable['id'], ['total_amount' => $newTotal, 'paid_amount' => $paid, 'balance_amount' => $balance, 'status' => $newTotal <= 0 ? 'cancelled' : ($balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'pending'))]);
    }

    private function applyStockDelta(string $companyId, string $productId, ?string $warehouseId, float $delta): void
    {
        if ($warehouseId === null) {
            return;
        }
        $stockLevelModel = new InventoryStockLevelModel();
        $product = (new InventoryProductModel())->find($productId);
        $existing = $stockLevelModel->where('company_id', $companyId)->where('product_id', $productId)->where('warehouse_id', $warehouseId)->first();
        if ($existing) {
            $stockLevelModel->update($existing['id'], ['quantity' => ((float) $existing['quantity']) + $delta]);
            return;
        }
        $stockLevelModel->insert(['company_id' => $companyId, 'product_id' => $productId, 'warehouse_id' => $warehouseId, 'quantity' => $delta, 'reserved_quantity' => 0, 'min_stock' => $product['min_stock'] ?? 0]);
    }

    private function registerInventoryMovement(array $payload): void
    {
        (new InventoryMovementModel())->insert(['company_id' => $payload['company_id'], 'product_id' => $payload['product_id'], 'movement_type' => $payload['movement_type'], 'quantity' => $payload['quantity'], 'unit_cost' => $payload['unit_cost'] ?? null, 'total_cost' => $payload['total_cost'] ?? null, 'adjustment_mode' => $payload['adjustment_mode'] ?? null, 'source_warehouse_id' => $payload['source_warehouse_id'] ?? null, 'destination_warehouse_id' => $payload['destination_warehouse_id'] ?? null, 'performed_by' => $payload['performed_by'], 'occurred_at' => $payload['occurred_at'] ?? date('Y-m-d H:i:s'), 'reason' => $payload['reason'] ?? null, 'source_document' => $payload['source_document'] ?? null, 'lot_number' => $payload['lot_number'] ?? null, 'serial_number' => $payload['serial_number'] ?? null, 'expiration_date' => $payload['expiration_date'] ?? null, 'notes' => $payload['notes'] ?? null]);
    }
}
