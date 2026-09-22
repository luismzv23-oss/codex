<?php

use App\Controllers\Api\V1\PurchasesController;
use App\Libraries\AccountingService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

final class PurchaseIntegrityTest extends CIUnitTestCase
{
    private array $tables = [];

    protected function setUp(): void
    {
        parent::setUp();
        helper(['app', 'url']);
        $this->db = db_connect('tests');
        $models = ['Company', 'Supplier', 'PurchaseOrder', 'PurchaseOrderItem', 'PurchaseReceipt', 'PurchaseReceiptItem',
            'PurchaseReturn', 'PurchaseReturnItem', 'PurchaseInvoice', 'PurchaseInvoiceItem', 'PurchaseCreditNote',
            'PurchasePayable', 'PurchasePayment', 'SupplierCostHistory', 'SupplierExchangeDifference', 'VoucherSequence',
            'InventoryProduct', 'InventoryWarehouse', 'InventoryLocation', 'InventoryStockLevel', 'InventoryMovement',
            'InventoryLot', 'InventorySerial', 'InventoryCostLayer', 'InventorySetting', 'InventoryPeriodClosure',
            'CashRegister', 'CashSession', 'CashMovement', 'CashPaymentGateway', 'CashCheck'];
        foreach ($models as $name) {
            $class = 'App\\Models\\' . $name . 'Model';
            $model = new $class();
            $ref = new ReflectionClass($model);
            $property = $ref->getProperty('allowedFields'); $property->setAccessible(true);
            $fields = array_unique(array_merge(['id', 'created_at', 'updated_at'], $property->getValue($model)));
            $table = $ref->getProperty('table'); $table->setAccessible(true); $table = $table->getValue($model);
            $columns = [];
            foreach ($fields as $field) {
                $numeric = preg_match('/quantity|amount|cost|price|rate|subtotal|tax_total|^total$|^active$|^current_number$|stock|control|payment_terms_days/', $field);
                $columns[] = '"' . $field . '" ' . ($field === 'id' ? 'TEXT PRIMARY KEY' : ($numeric ? 'REAL DEFAULT 0' : 'TEXT'));
            }
            $this->db->query('CREATE TABLE ' . $this->db->prefixTable($table) . ' (' . implode(', ', $columns) . ')');
            $this->tables[] = $table;
        }
        $this->insert('companies', ['id' => 'a']);
        $this->insert('suppliers', ['id' => 's', 'company_id' => 'a', 'active' => 1]);
        $this->insert('inventory_products', ['id' => 'p', 'company_id' => 'a', 'active' => 1, 'cost_price' => 10]);
        $this->insert('inventory_warehouses', ['id' => 'w', 'company_id' => 'a', 'active' => 1]);
        $this->insert('inventory_settings', ['id' => 'setting', 'company_id' => 'a', 'valuation_method' => 'fifo']);
        $this->insert('purchase_orders', ['id' => 'o', 'company_id' => 'a', 'supplier_id' => 's', 'warehouse_id' => 'w', 'status' => 'approved', 'currency_code' => 'ARS']);
        $this->insert('purchase_order_items', ['id' => 'oi', 'purchase_order_id' => 'o', 'product_id' => 'p', 'quantity' => 10, 'received_quantity' => 0, 'unit_cost' => 10]);
        foreach (['RCOMPRA', 'PAGCP', 'DEVPROV', 'PAGPROV'] as $type) {
            $this->insert('voucher_sequences', ['id' => $type, 'company_id' => 'a', 'document_type' => $type, 'current_number' => 1, 'active' => 1]);
        }
        $accounting = $this->createMock(AccountingService::class);
        foreach (['syncPurchaseReceipt', 'syncPurchaseReturn', 'syncPurchasePayment'] as $name) {
            $accounting->method($name)->willReturn(['ok' => true]);
        }
        Services::injectMock('accounting', $accounting);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            $this->db->query('DROP TABLE ' . $this->db->prefixTable($table));
        }
        parent::tearDown();
    }

    private function insert(string $table, array $data): void { $this->db->table($table)->insert($data); }
    private function first(string $table): array { return $this->db->table($table)->get()->getRowArray() ?? []; }
    private function api(array $input): PurchasesController
    {
        $controller = new class extends PurchasesController {
            public array $input;
            protected function purchaseContext(string $requiredAccess = 'view'): array { return ['company' => ['id' => 'a']]; }
            protected function apiUser(): ?array { return ['id' => 'operator', 'company_id' => 'a']; }
            protected function payload(): array { return $this->input; }
        };
        $controller->input = $input;
        $controller->initController(service('request'), service('response'), service('logger'));
        return $controller;
    }
    private function receive(float $quantity = 10, array $extra = []): array
    {
        $response = $this->api(['purchase_order_id' => 'o', 'items' => [array_merge(['purchase_order_item_id' => 'oi', 'quantity' => $quantity], $extra)]])->storeReceipt();
        $this->assertSame(201, $response->getStatusCode(), $response->getBody());
        return $this->first('purchase_receipts');
    }
    private function invoice(?string $receipt, float $quantity = 10, float $cost = 10): array
    {
        $response = $this->api(['supplier_id' => 's', 'invoice_number' => app_uuid(), 'purchase_receipt_id' => $receipt,
            'items' => [['product_id' => 'p', 'description' => 'Producto', 'quantity' => $quantity, 'unit_cost' => $cost]]])->storeInvoice();
        $this->assertSame(201, $response->getStatusCode(), $response->getBody());
        return $this->db->table('purchase_invoices')->orderBy('created_at', 'DESC')->get()->getRowArray();
    }
    private function returnInput(float $quantity): array
    {
        return ['purchase_receipt_id' => $this->first('purchase_receipts')['id'], 'items' => [[
            'purchase_receipt_item_id' => $this->first('purchase_receipt_items')['id'], 'quantity' => $quantity]]];
    }

    public function testDuplicateReceiptLinesAndDraftOrderAreRejected(): void
    {
        $row = ['purchase_order_item_id' => 'oi', 'quantity' => 6];
        $response = $this->api(['purchase_order_id' => 'o', 'items' => [$row, $row]])->storeReceipt();
        $this->assertSame(422, $response->getStatusCode());
        $this->db->table('purchase_orders')->update(['status' => 'draft']);
        $response = $this->api(['purchase_order_id' => 'o', 'items' => [$row]])->storeReceipt();
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, $this->db->table('purchase_receipts')->countAllResults());
    }

    public function testReceiptAndReturnSynchronizeStockLotsSerialsAndCosts(): void
    {
        $this->db->table('inventory_products')->update(['lot_control' => 1, 'serial_control' => 1]);
        $this->receive(1, ['lot_number' => 'LOT', 'serial_number' => 'SER']);
        $this->assertEquals(1, $this->first('inventory_lots')['quantity_balance']);
        $this->assertSame('available', $this->first('inventory_serials')['status']);
        $this->assertEquals(10, $this->first('inventory_cost_layers')['total_cost']);
        $response = $this->api($this->returnInput(1))->storeReturn();
        $this->assertSame(201, $response->getStatusCode(), $response->getBody());
        $this->assertEquals(0, $this->first('inventory_stock_levels')['quantity']);
        $this->assertEquals(0, $this->first('inventory_lots')['quantity_balance']);
        $this->assertSame('consumed', $this->first('inventory_serials')['status']);
        $this->assertEquals(0, $this->first('inventory_cost_layers')['remaining_quantity']);
    }

    public function testMissingTraceRollsBackWholeReceipt(): void
    {
        $this->db->table('inventory_products')->update(['serial_control' => 1]);
        $response = $this->api(['purchase_order_id' => 'o', 'items' => [['purchase_order_item_id' => 'oi', 'quantity' => 1]]])->storeReceipt();
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, $this->db->table('purchase_receipts')->countAllResults());
        $this->assertSame(0, $this->db->table('inventory_stock_levels')->countAllResults());
        $this->assertEquals(0, $this->first('purchase_order_items')['received_quantity']);
        $this->assertSame(0, $this->db->transDepth);
    }

    public function testTracedReturnConsumesTheReceiptLocationOnly(): void
    {
        $this->insert('inventory_locations', ['id' => 'l1', 'company_id' => 'a', 'warehouse_id' => 'w', 'active' => 1]);
        $this->insert('inventory_stock_levels', ['id' => '0', 'company_id' => 'a', 'product_id' => 'p', 'warehouse_id' => 'w', 'location_id' => 'l1', 'quantity' => 9]);
        $this->receive(5, ['lot_number' => 'LOT']);
        $response = $this->api($this->returnInput(3))->storeReturn();
        $this->assertSame(201, $response->getStatusCode(), $response->getBody());
        $this->assertEquals(9, $this->db->table('inventory_stock_levels')->where('location_id', 'l1')->get()->getRowArray()['quantity']);
        $this->assertEquals(2, $this->db->table('inventory_stock_levels')->where('location_id', null)->get()->getRowArray()['quantity']);
        $this->assertEquals(2, $this->first('inventory_lots')['quantity_balance']);
    }

    public function testCumulativeReturnsAndReservedStockAreEnforced(): void
    {
        $this->receive();
        $this->db->table('inventory_stock_levels')->update(['reserved_quantity' => 4]);
        $this->assertSame(422, $this->api($this->returnInput(8))->storeReturn()->getStatusCode());
        $this->db->table('inventory_stock_levels')->update(['reserved_quantity' => 0]);
        $this->assertSame(201, $this->api($this->returnInput(8))->storeReturn()->getStatusCode());
        // Replenishment must not authorize returning more than the original receipt.
        $this->db->table('inventory_stock_levels')->update(['quantity' => 100]);
        $this->assertSame(422, $this->api($this->returnInput(5))->storeReturn()->getStatusCode());
        $input = $this->returnInput(2); $input['items'][] = $input['items'][0];
        $this->assertSame(422, $this->api($input)->storeReturn()->getStatusCode());
        $this->assertSame(1, $this->db->table('purchase_returns')->countAllResults());
    }

    public function testPaidReturnPreservesPaymentsAndCreatesVisibleCredit(): void
    {
        $this->receive();
        $this->db->table('purchase_payables')->update(['paid_amount' => 100, 'balance_amount' => 0, 'status' => 'paid']);
        $this->assertSame(201, $this->api($this->returnInput(4))->storeReturn()->getStatusCode());
        $payable = $this->first('purchase_payables');
        $this->assertEquals(100, $payable['paid_amount']);
        $this->assertEquals(60, $payable['total_amount']);
        $this->assertEquals(-40, $payable['balance_amount']);
        $this->assertSame('credit', $payable['status']);
    }

    public function testPartialInvoicesReplaceProvisionalCostWithoutDuplicatingDebt(): void
    {
        $receipt = $this->receive();
        $this->invoice($receipt['id'], 4, 12);
        $this->assertSame(1, $this->db->table('purchase_payables')->countAllResults());
        $this->assertEquals(108, $this->first('purchase_payables')['total_amount']);
        $this->assertSame('invoiced_partial', $this->first('purchase_receipts')['status']);
        $this->invoice($receipt['id'], 6, 15);
        $this->assertEquals(138, $this->first('purchase_payables')['total_amount']);
        $this->assertSame('invoiced_total', $this->first('purchase_receipts')['status']);
    }

    public function testDirectInvoiceAndCreditUpdatePayable(): void
    {
        $invoice = $this->invoice(null);
        $this->assertEquals(100, $this->first('purchase_payables')['balance_amount']);
        $response = $this->api(['supplier_id' => 's', 'purchase_invoice_id' => $invoice['id'], 'credit_note_number' => 'NC1', 'amount' => 40])->storeCreditNote();
        $this->assertSame(201, $response->getStatusCode(), $response->getBody());
        $this->assertEquals(60, $this->first('purchase_payables')['balance_amount']);
        $this->assertSame(1, $this->db->table('purchase_payables')->countAllResults());
    }

    public function testCreditDocumentingReturnDoesNotDiscountTwice(): void
    {
        $receipt = $this->receive(); $invoice = $this->invoice($receipt['id']);
        $this->assertSame(201, $this->api($this->returnInput(4))->storeReturn()->getStatusCode());
        $input = ['supplier_id' => 's', 'purchase_invoice_id' => $invoice['id'], 'purchase_return_id' => $this->first('purchase_returns')['id'], 'credit_note_number' => 'NC1', 'amount' => 40];
        $response = $this->api($input)->storeCreditNote();
        $this->assertSame(201, $response->getStatusCode(), $response->getBody());
        $this->assertEquals(60, $this->first('purchase_payables')['balance_amount']);
        $input['credit_note_number'] = 'NC2';
        $this->assertSame(422, $this->api($input)->storeCreditNote()->getStatusCode());
    }

    public function testForeignReferencesAndInvoiceOverbillingAreRejected(): void
    {
        $receipt = $this->receive();
        $input = ['supplier_id' => 's', 'invoice_number' => 'I1', 'purchase_receipt_id' => $receipt['id'],
            'items' => array_fill(0, 2, ['product_id' => 'p', 'description' => 'P', 'quantity' => 6, 'unit_cost' => 10])];
        $this->assertSame(422, $this->api($input)->storeInvoice()->getStatusCode());
        $this->db->table('purchase_receipts')->update(['company_id' => 'b']);
        $input['items'][0]['quantity'] = 1; unset($input['items'][1]);
        $this->assertSame(422, $this->api($input)->storeInvoice()->getStatusCode());
        $invoice = $this->invoice(null);
        $this->db->table('purchase_invoices')->update(['supplier_id' => 'other']);
        $this->assertSame(422, $this->api(['supplier_id' => 's', 'purchase_invoice_id' => $invoice['id'], 'amount' => 10, 'credit_note_number' => 'NC'])->storeCreditNote()->getStatusCode());
        $this->assertSame(0, $this->db->table('purchase_credit_notes')->countAllResults());
    }

    public function testPaymentsRequireCashAndUpdateDebtWithCashAtomically(): void
    {
        $this->invoice(null);
        $input = ['purchase_payable_id' => $this->first('purchase_payables')['id'], 'amount' => 60];
        $this->assertSame(422, $this->api($input)->storePayment()->getStatusCode());
        $this->assertSame(0, $this->db->table('purchase_payments')->countAllResults());
        $this->insert('cash_registers', ['id' => 'reg', 'company_id' => 'a', 'active' => 1, 'register_type' => 'general']);
        $this->insert('cash_sessions', ['id' => 'sess', 'company_id' => 'a', 'cash_register_id' => 'reg', 'status' => 'open', 'opening_amount' => 200]);
        $response = $this->api($input)->storePayment();
        $this->assertSame(201, $response->getStatusCode(), $response->getBody());
        $this->assertEquals(40, $this->first('purchase_payables')['balance_amount']);
        $this->assertEquals(-60, $this->first('cash_movements')['amount']);
        $this->assertSame(422, $this->api($input)->storePayment()->getStatusCode());
        $this->assertSame(1, $this->db->table('purchase_payments')->countAllResults());
    }

    public function testAccountingFailureRollsBackPaymentCashAndPayable(): void
    {
        $this->invoice(null);
        $this->insert('cash_registers', ['id' => 'reg', 'company_id' => 'a', 'active' => 1, 'register_type' => 'general']);
        $this->insert('cash_sessions', ['id' => 'sess', 'company_id' => 'a', 'cash_register_id' => 'reg', 'status' => 'open', 'opening_amount' => 200, 'expected_closing_amount' => 200]);
        $accounting = $this->createMock(AccountingService::class);
        $accounting->method('syncPurchasePayment')->willThrowException(new RuntimeException('Accounting unavailable'));
        Services::injectMock('accounting', $accounting);
        $response = $this->api(['purchase_payable_id' => $this->first('purchase_payables')['id'], 'amount' => 60])->storePayment();
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, $this->db->table('purchase_payments')->countAllResults());
        $this->assertSame(0, $this->db->table('cash_movements')->countAllResults());
        $this->assertEquals(100, $this->first('purchase_payables')['balance_amount']);
        $this->assertEquals(200, $this->first('cash_sessions')['expected_closing_amount']);
    }

    public function testWebInvoiceCreatesPayableAndRejectsForeignReceipt(): void
    {
        $controller = new class extends \App\Controllers\PurchasesController {
            protected function purchaseContext(string $requiredAccess = 'view') { return ['company' => ['id' => 'a']]; }
            protected function currentUser(): ?array { return ['id' => 'operator', 'company_id' => 'a']; }
        };
        $request = service('request');
        $input = ['supplier_id' => 's', 'invoice_number' => 'WEB1', 'items_product_id' => ['p'],
            'items_description' => ['Producto'], 'items_quantity' => [5], 'items_unit_cost' => [10], 'items_tax_rate' => [0]];
        $request->setGlobal('post', $input);
        $controller->initController($request, service('response'), service('logger'));
        $controller->storeInvoice();
        $this->assertSame(1, $this->db->table('purchase_invoices')->countAllResults());
        $this->assertEquals(50, $this->first('purchase_payables')['balance_amount']);
        $receipt = $this->receive();
        $this->db->table('purchase_receipts')->update(['company_id' => 'b']);
        $input['invoice_number'] = 'WEB2'; $input['purchase_receipt_id'] = $receipt['id'];
        $request->setGlobal('post', $input);
        $controller->storeInvoice();
        $this->assertSame(1, $this->db->table('purchase_invoices')->countAllResults());
        $this->assertSame(0, $this->db->transDepth);
    }
}
