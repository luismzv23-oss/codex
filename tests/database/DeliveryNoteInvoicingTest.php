<?php
use App\Libraries\{DeliveryNoteService, DeliveryNoteInvoicingService, DeliveryNoteCancellationService};
use App\Models\{SaleModel, SaleItemModel, InventoryMovementModel, InventoryStockLevelModel};
use CodeIgniter\Test\CIUnitTestCase;

final class DeliveryNoteInvoicingTest extends CIUnitTestCase
{
    private array $tables = [];

    protected function setUp(): void
    {
        parent::setUp(); helper(['app', 'url', 'auth']); $this->db = db_connect();
        $schemas = [
            'company_payment_methods' => 'id TEXT PRIMARY KEY, company_id TEXT, show_on_receipt INTEGER',
            'company_settings' => 'id TEXT PRIMARY KEY, company_id TEXT, key TEXT, value TEXT',
            'inventory_settings' => 'id TEXT PRIMARY KEY, company_id TEXT, allow_negative_stock INTEGER',
            'event_log' => 'id TEXT PRIMARY KEY, event TEXT, payload TEXT, listeners_count INTEGER, created_at TEXT',
            'users' => 'id TEXT PRIMARY KEY, name TEXT',
            'companies' => 'id TEXT PRIMARY KEY', 'branches' => 'id TEXT PRIMARY KEY, company_id TEXT, active INTEGER, code TEXT',
            'sales_document_types' => 'id TEXT PRIMARY KEY, company_id TEXT, name TEXT, code TEXT, category TEXT, active INTEGER, impacts_stock INTEGER, impacts_receivable INTEGER, channel TEXT, requires_customer INTEGER, sequence_key TEXT, default_prefix TEXT',
            'sales_points_of_sale' => 'id TEXT PRIMARY KEY, company_id TEXT, document_type_id TEXT, active INTEGER, channel TEXT',
            'customers' => 'id TEXT PRIMARY KEY, company_id TEXT, name TEXT, document_number TEXT, tax_profile TEXT',
            'inventory_products' => 'id TEXT PRIMARY KEY, company_id TEXT, product_type TEXT, brand TEXT, unit TEXT, sale_price REAL, cost_price REAL, min_stock REAL',
            'inventory_warehouses' => 'id TEXT PRIMARY KEY, company_id TEXT, name TEXT',
            'inventory_period_closures' => 'id TEXT PRIMARY KEY, company_id TEXT, warehouse_id TEXT, status TEXT, start_date TEXT, end_date TEXT',
            'sales_delivery_notes' => 'id TEXT PRIMARY KEY, company_id TEXT, customer_id TEXT, warehouse_id TEXT, sales_order_id TEXT, sale_id TEXT, delivery_number TEXT, delivery_date TEXT, shipping_address TEXT, carrier TEXT, tracking_number TEXT, customer_name_snapshot TEXT, created_by TEXT, created_at TEXT, status TEXT, dispatched_at TEXT, delivered_at TEXT, updated_at TEXT',
            'sales_delivery_note_items' => 'id TEXT PRIMARY KEY, sales_delivery_note_id TEXT, product_id TEXT, quantity REAL, product_name TEXT, sku TEXT, sort_order INTEGER, sales_order_item_id TEXT',
            'sales_orders' => 'id TEXT PRIMARY KEY, company_id TEXT, status TEXT, approved_at TEXT, order_number TEXT, converted_to_sale_id TEXT, updated_at TEXT',
            'sales_order_items' => 'id TEXT PRIMARY KEY, sales_order_id TEXT, quantity REAL, quantity_delivered REAL, quantity_invoiced REAL',
            'sale_payments' => 'id TEXT PRIMARY KEY, sale_id TEXT, amount REAL, status TEXT, payment_method_id TEXT, sales_receipt_id TEXT',
            'sales_receivables' => 'id TEXT PRIMARY KEY, company_id TEXT, sale_id TEXT, balance_amount REAL',
            'sales_receipts' => 'id TEXT PRIMARY KEY, company_id TEXT, status TEXT',
            'sales_receipt_items' => 'id TEXT PRIMARY KEY, sale_id TEXT, sales_receipt_id TEXT',
            'sale_returns' => 'id TEXT PRIMARY KEY, sale_id TEXT',
            'journal_entries' => 'id TEXT PRIMARY KEY, company_id TEXT, reference_type TEXT, reference_id TEXT, status TEXT',
            'sales_price_lists' => 'id TEXT PRIMARY KEY, company_id TEXT, name TEXT, active INTEGER',
            'sales_price_list_items' => 'id TEXT PRIMARY KEY, price_list_id TEXT, product_id TEXT, price REAL',
            'taxes' => 'id TEXT PRIMARY KEY, company_id TEXT, name TEXT, rate REAL, active INTEGER, is_default INTEGER',
        ];
        foreach ([ 'sales' => new SaleModel(), 'sale_items' => new SaleItemModel(), 'inventory_movements' => new InventoryMovementModel(), 'inventory_stock_levels' => new InventoryStockLevelModel(), 'voucher_sequences' => new \App\Models\VoucherSequenceModel(), 'cash_sessions' => new \App\Models\CashSessionModel(), 'sales_commissions' => new \App\Models\SalesCommissionModel(), 'sales_receivables' => new \App\Models\SalesReceivableModel()] as $table => $model) {
            $fields = (new ReflectionProperty($model, 'allowedFields')); $fields->setAccessible(true);
            $columns = array_unique(array_merge($fields->getValue($model), ['id', 'created_at', 'updated_at']));
            $schemas[$table] = implode(', ', array_map(static function ($column) {
                $numeric = preg_match('/quantity|total|price|cost|amount|rate|balance|number$|active|stock|level/', $column) && !in_array($column, ['sale_number', 'lot_number', 'serial_number'], true);
                return '"' . $column . '" ' . ($column === 'id' ? 'TEXT PRIMARY KEY' : ($numeric ? 'REAL' : 'TEXT'));
            }, $columns));
        }
        foreach ($schemas as $table => $schema) { $this->db->query('CREATE TABLE ' . $this->db->prefixTable($table) . ' (' . $schema . ')'); $this->tables[] = $table; }
        // Journal tables use the actual accounting insert contract.
        $this->db->query('DROP TABLE ' . $this->db->prefixTable('journal_entries'));
        $this->db->query('CREATE TABLE ' . $this->db->prefixTable('journal_entries') . ' (id TEXT PRIMARY KEY, company_id TEXT, entry_number INTEGER, entry_date TEXT, description TEXT, reference_type TEXT, reference_id TEXT, status TEXT, total_debit REAL, total_credit REAL, user_id TEXT, posted_at TEXT, created_at TEXT)');
        $this->db->query('CREATE TABLE ' . $this->db->prefixTable('journal_entry_lines') . ' (id TEXT PRIMARY KEY, journal_entry_id TEXT, account_id TEXT, description TEXT, debit REAL, credit REAL, created_at TEXT)');
        $this->tables[] = 'journal_entry_lines';
        require_once APPPATH . 'Database/Migrations/2026-10-15-120000_AddDeliveryNoteInvoicing.php';
        (new \App\Database\Migrations\AddDeliveryNoteInvoicing())->up();
        $this->tables[] = 'sales_delivery_invoices'; $this->tables[] = 'sales_delivery_invoice_items';
        $this->db->table('companies')->insert(['id' => 'a']);
        $this->db->table('customers')->insert(['id' => 'customer', 'company_id' => 'a', 'name' => 'Cliente']);
        $this->db->table('sales_document_types')->insertBatch([
            ['id' => 'remito', 'company_id' => 'a', 'code' => 'REMITO', 'category' => 'delivery_note', 'active' => 1, 'impacts_stock' => 1, 'channel' => 'standard', 'sequence_key' => 'REMITO', 'default_prefix' => 'RTO'],
            ['id' => 'invoice', 'company_id' => 'a', 'code' => 'FACTURA_B', 'category' => 'invoice', 'active' => 1, 'impacts_stock' => 1, 'channel' => 'standard', 'sequence_key' => 'FACTURA_B', 'default_prefix' => 'FCB'],
        ]);
        $this->db->table('sales_points_of_sale')->insert(['id' => 'pos', 'company_id' => 'a', 'document_type_id' => 'invoice', 'active' => 1, 'channel' => 'standard']);
        $this->db->table('inventory_products')->insert(['id' => 'p', 'company_id' => 'a', 'product_type' => 'simple', 'unit' => 'un', 'sale_price' => 121, 'cost_price' => 40]);
        $this->db->table('inventory_warehouses')->insert(['id' => 'w', 'company_id' => 'a']);
        $this->db->table('sales')->insert(['id' => 'source', 'company_id' => 'a', 'document_type_id' => 'remito', 'sale_number' => 'RTO-1', 'status' => 'confirmed', 'delivered_at' => '2026-01-01', 'customer_id' => 'customer', 'warehouse_id' => 'w', 'currency_code' => 'ARS', 'total' => 1210]);
        $this->db->table('sale_items')->insert(['id' => 'line', 'sale_id' => 'source', 'product_id' => 'p', 'product_name' => 'Producto', 'quantity' => 10, 'line_total' => 1210, 'tax_rate' => 21, 'unit_price' => 121, 'line_number' => 1]);
        $this->db->table('voucher_sequences')->insert(['id' => 'seq', 'company_id' => 'a', 'document_type' => 'FACTURA_B', 'prefix' => 'FCB', 'current_number' => 1, 'active' => 1]);
        $this->db->table('taxes')->insert(['id' => 'iva', 'company_id' => 'a', 'name' => 'IVA 21', 'rate' => 21, 'active' => 1, 'is_default' => 1]);
    }
    protected function tearDown(): void
    {
        while ($this->db->transDepth) $this->db->transRollback();
        $this->db->query('PRAGMA foreign_keys=OFF');
        foreach (array_reverse($this->tables) as $table) $this->db->query('DROP TABLE ' . $this->db->prefixTable($table));
        $this->db->query('PRAGMA foreign_keys=ON');
        parent::tearDown();
    }
    private function input(string $mode = 'parcial', float $quantity = 4, string $origin = 'sale', string $id = 'source'): array
    {
        $note = (new DeliveryNoteService())->summary('a', $origin, $id);
        $note = (new DeliveryNoteInvoicingService())->prices('a', $note);
        return ['request_key' => app_uuid(), 'price_version' => DeliveryNoteInvoicingService::priceVersion($note), 'mode' => $mode,
            'quantities' => [$note['items'][0]['id'] => $quantity], 'document_type_id' => 'invoice', 'point_of_sale_id' => 'pos', 'tax_id' => 'iva'];
    }
    private function save(array $input, string $origin = 'sale', string $id = 'source'): array
    {
        return (new DeliveryNoteService())->lock('a', $origin, $id, static fn() => (new DeliveryNoteInvoicingService())->save('a', $origin, $id, 'operator', $input));
    }
    private function confirm(string $invoiceId): void
    {
        (new DeliveryNoteService())->lock('a', 'sale', 'source', function () use ($invoiceId) {
            $sale = $this->db->table('sales')->where('id', $invoiceId)->get()->getRowArray();
            $items = $this->db->table('sale_items')->where('sale_id', $invoiceId)->get()->getResultArray();
            $this->assertTrue((new DeliveryNoteInvoicingService())->validateConfirmation('a', $sale, $items));
            $this->db->table('sales')->where('id', $invoiceId)->update(['status' => 'confirmed']);
        });
    }
    public function testPartialThenTotalAndIdempotentReplay(): void
    {
        $input = $this->input(); $first = $this->save($input);
        $note = (new DeliveryNoteService())->summary('a', 'sale', 'source');
        $this->assertSame('Sin facturar', $note['billing_status']);
        $this->assertEquals(10, $note['items'][0]['pending_quantity']);
        $this->confirm($first['invoice_id']);
        $this->assertTrue($this->save($input)['already_confirmed']);
        $note = (new DeliveryNoteService())->summary('a', 'sale', 'source');
        $this->assertSame('Facturado parcialmente', $note['billing_status']);
        $this->assertEquals(6, $note['items'][0]['pending_quantity']);
        $last = $this->save($this->input('total')); $this->confirm($last['invoice_id']);
        $note = (new DeliveryNoteService())->summary('a', 'sale', 'source');
        $this->assertSame('Facturado totalmente', $note['billing_status']);
        $this->assertEquals(1210, $this->db->table('sales')->where('id !=', 'source')->selectSum('total', 'total')->get()->getRowArray()['total']);
        $this->assertSame(2, $this->db->table('sales_delivery_invoices')->countAllResults());
        $this->assertSame(0, $this->db->table('inventory_movements')->countAllResults());
        $this->assertSame(0, $this->db->table('sales_receivables')->countAllResults());
    }
    public function testCompetingDraftCannotOverinvoice(): void
    {
        $first = $this->save($this->input('parcial', 7)); $second = $this->save($this->input('parcial', 7));
        $this->confirm($first['invoice_id']);
        $this->expectException(RuntimeException::class); $this->expectExceptionMessage('consumió cantidades');
        $this->confirm($second['invoice_id']);
    }
    public function testRepeatedProductUsesOriginalLineIdentity(): void
    {
        $this->db->table('sale_items')->insert(['id' => 'other-line', 'sale_id' => 'source', 'product_id' => 'p', 'product_name' => 'Mismo producto', 'quantity' => 2, 'line_total' => 242, 'tax_rate' => 21, 'line_number' => 2]);
        $this->db->table('sales')->where('id', 'source')->update(['total' => 1452]);
        $first = $this->save($this->input()); $this->confirm($first['invoice_id']);
        $items = array_column((new DeliveryNoteService())->summary('a', 'sale', 'source')['items'], null, 'id');
        $this->assertEquals(6, $items['line']['pending_quantity']); $this->assertEquals(2, $items['other-line']['pending_quantity']);
    }
    public function testLegacyConversionBlocksNewBillingWithoutGuessing(): void
    {
        $this->db->table('sales')->insert(['id' => 'old', 'company_id' => 'a', 'source_sale_id' => 'source', 'document_type_id' => 'invoice', 'sale_number' => 'FC-OLD', 'status' => 'confirmed']);
        $note = (new DeliveryNoteService())->summary('a', 'sale', 'source');
        $this->assertCount(1, $note['legacy']); $this->assertNotEmpty($note['invoice_reason']); $this->assertNotEmpty($note['cancel_reason']);
        $this->expectException(RuntimeException::class); $this->save($this->input());
    }
    public function testForeignCompanyCannotReadOrInvoice(): void
    {
        $this->expectException(RuntimeException::class); (new DeliveryNoteService())->summary('foreign', 'sale', 'source');
    }
    public function testTamperedRowsRollBackInvoiceAndSequence(): void
    {
        $input = $this->input(); $input['quantities']['foreign-line'] = 1;
        try { $this->save($input); $this->fail('Expected invalid line'); } catch (RuntimeException $e) { $this->assertStringContainsString('ajenos', $e->getMessage()); }
        $this->assertSame(0, $this->db->table('sales_delivery_invoices')->countAllResults());
        $this->assertEquals(1, $this->db->table('voucher_sequences')->get()->getRowArray()['current_number']);
    }
    public function testDecimalQuantitiesAndFinalCentArePreserved(): void
    {
        $this->db->table('sales')->where('id', 'source')->update(['total' => 1]);
        $this->db->table('sale_items')->where('id', 'line')->update(['quantity' => 0.3, 'line_total' => 1]);
        foreach ([0.1, 0.1] as $qty) { $r = $this->save($this->input('parcial', $qty)); $this->confirm($r['invoice_id']); }
        $r = $this->save($this->input('total')); $this->confirm($r['invoice_id']);
        $this->assertEquals(1, $this->db->table('sales')->where('id !=', 'source')->selectSum('total', 'total')->get()->getRowArray()['total']);
    }
    public function testCycleDeliveryCanBeInvoicedAtCurrentPrice(): void
    {
        $this->cycle();
        $result = $this->save($this->input('total', 0, 'cycle', 'cycle'), 'cycle', 'cycle');
        $sale = $this->db->table('sales')->where('id', $result['invoice_id'])->get()->getRowArray();
        $items = $this->db->table('sale_items')->where('sale_id', $sale['id'])->get()->getResultArray();
        $this->assertEquals(363, $sale['total']);
        $this->assertTrue((new DeliveryNoteInvoicingService())->validateConfirmation('a', $sale, $items));
        $this->assertSame(0, $this->db->table('inventory_movements')->countAllResults());
    }
    private function cycle(): void
    {
        $this->db->table('sales_delivery_notes')->insert(['id' => 'cycle', 'company_id' => 'a', 'customer_id' => 'customer', 'warehouse_id' => 'w', 'delivery_number' => 'RM-1', 'status' => 'dispatched', 'dispatched_at' => '2026-01-01']);
        $this->db->table('sales_delivery_note_items')->insert(['id' => 'cycle-line', 'sales_delivery_note_id' => 'cycle', 'product_id' => 'p', 'product_name' => 'Producto', 'quantity' => 3]);
    }
    public function testCancellationRequiresPhysicalReturnAndRestocksOnlyOnce(): void
    {
        $this->cycle();
        $this->db->table('inventory_stock_levels')->insert(['id' => 'stock', 'company_id' => 'a', 'product_id' => 'p', 'warehouse_id' => 'w', 'quantity' => 7, 'reserved_quantity' => 0]);
        $this->db->table('inventory_movements')->insert(['id' => 'out', 'company_id' => 'a', 'product_id' => 'p', 'source_document' => 'RM-1', 'reason' => 'REMITO', 'movement_type' => 'egreso', 'source_warehouse_id' => 'w', 'quantity' => 3]);
        $cancel = fn($returned) => (new DeliveryNoteService())->lock('a', 'cycle', 'cycle', fn() => (new DeliveryNoteCancellationService())->cancel('a', 'cycle', 'cycle', 'operator', 'Retorno confirmado', $returned));
        try { $cancel(false); $this->fail('Expected physical return'); } catch (RuntimeException $e) { $this->assertStringContainsString('retorno físico', $e->getMessage()); }
        $this->assertEquals(7, $this->db->table('inventory_stock_levels')->get()->getRowArray()['quantity']);
        $cancel(true); $cancel(true);
        $this->assertEquals(10, $this->db->table('inventory_stock_levels')->get()->getRowArray()['quantity']);
        $this->assertSame(2, $this->db->table('inventory_movements')->countAllResults());
        $this->assertSame('cancelled', $this->db->table('sales_delivery_notes')->get()->getRowArray()['status']);
    }
    public function testLinkedDraftBlocksCancellation(): void
    {
        $this->save($this->input());
        $this->expectException(RuntimeException::class); $this->expectExceptionMessage('vinculados');
        (new DeliveryNoteService())->lock('a', 'sale', 'source', fn() => (new DeliveryNoteCancellationService())->cancel('a', 'sale', 'source', 'operator', 'Anular', true));
    }
    public function testChangedPriceRequiresReview(): void
    {
        $this->cycle(); $input = $this->input('total', 0, 'cycle', 'cycle');
        $this->db->table('inventory_products')->update(['sale_price' => 200]);
        $this->expectException(RuntimeException::class); $this->expectExceptionMessage('Cambiaron los precios'); $this->save($input, 'cycle', 'cycle');
    }
    public function testFiscalAuthorizationCannotReleaseAllocation(): void
    {
        $r = $this->save($this->input()); $sale = $this->db->table('sales')->where('id', $r['invoice_id'])->get()->getRowArray(); $sale['cae'] = '123';
        $this->expectException(RuntimeException::class); (new DeliveryNoteInvoicingService())->assertInvoiceCanCancel('a', $sale);
    }
    public function testActualWebConfirmationPostsOnlyInvoiceDebtAndNeverStock(): void
    {
        $this->db->table('sales_document_types')->where('id', 'invoice')->update(['impacts_receivable' => 1]);
        foreach (['receivable', 'revenue', 'iva_debito'] as $account) $this->db->table('company_settings')->insert(['id' => $account, 'company_id' => 'a', 'key' => 'account_' . $account, 'value' => $account]);
        $this->db->table('cash_sessions')->insert(['id' => 'session', 'company_id' => 'a', 'cash_register_id' => 'register', 'status' => 'open']);
        $result = $this->save($this->input());
        $this->db->table('sales')->where('id', $result['invoice_id'])->update(['cash_session_id' => 'session', 'cash_register_id' => 'register']);
        $controller = new class extends \App\Controllers\SalesController {
            protected function currentUser(): ?array { return ['id' => 'operator']; }
        };
        $controller->initController(service('request'), service('response'), service('logger'));
        $method = new ReflectionMethod(\App\Controllers\SalesController::class, 'confirmSaleTransaction'); $method->setAccessible(true);
        // Keep unrelated automation listeners out of this isolated transaction test.
        $listeners = new ReflectionProperty(\App\Libraries\EventBus::class, 'listeners'); $listeners->setAccessible(true);
        $initialized = new ReflectionProperty(\App\Libraries\EventBus::class, 'initialized'); $initialized->setAccessible(true);
        $oldListeners = $listeners->getValue(); $oldInitialized = $initialized->getValue();
        $listeners->setValue([]); $initialized->setValue(true);
        try { $confirmed = $method->invoke($controller, 'a', $result['invoice_id']); }
        finally { $listeners->setValue($oldListeners); $initialized->setValue($oldInitialized); }
        $this->assertTrue($confirmed, is_string($confirmed) ? $confirmed : '');
        $this->assertSame('confirmed', $this->db->table('sales')->where('id', $result['invoice_id'])->get()->getRowArray()['status']);
        $debt = $this->db->table('sales_receivables')->get()->getRowArray();
        $this->assertSame($result['invoice_id'], $debt['sale_id']); $this->assertEquals(484, $debt['balance_amount']);
        $this->assertSame(1, $this->db->table('journal_entries')->countAllResults());
        $this->assertEquals(484, $this->db->table('journal_entries')->get()->getRowArray()['total_debit']);
        $this->assertSame(0, $this->db->table('inventory_movements')->countAllResults());
    }
    public function testInvoiceAndCancellationFormsRenderWithPopupFields(): void
    {
        $note = (new DeliveryNoteInvoicingService())->prices('a', (new DeliveryNoteService())->summary('a', 'sale', 'source'));
        $data = ['pageTitle' => 'Remito', 'note' => $note, 'companyId' => 'a', 'context' => ['company' => ['id' => 'a'], 'canManage' => true], 'input' => [], 'error' => null, 'draft' => null, 'mode' => 'parcial', 'documentTypes' => [], 'pointsOfSale' => [], 'priceLists' => [], 'taxes' => [], 'formAction' => '/remito-test', 'isPopup' => true];
        $html = view('sales/forms/delivery_invoice', $data, ['saveData' => false]);
        $this->assertStringContainsString('data-sales-live="0"', $html);
        $this->assertStringContainsString('name="quantities[line]"', $html);
        $this->assertStringContainsString('Confirmar factura', $html);
        $this->assertStringContainsString('name="price_version"', $html);
        $html = view('sales/forms/delivery_cancel', $data, ['saveData' => false]);
        $this->assertStringContainsString('name="return_confirmed"', $html);
        $this->assertStringContainsString('name="cancellation_reason"', $html);
    }
    public function testRemitoDoesNotProduceFinancialAutomation(): void
    {
        $sale = $this->db->table('sales')->where('id', 'source')->get()->getRowArray();
        $this->assertSame([], \App\Libraries\AutomationService::onSaleConfirmed('sale.confirmed', ['sale' => $sale, 'company_id' => 'a']));
        $this->assertTrue((new \App\Libraries\AccountingService())->syncSale('a', 'source', 'operator')['ok']);
        $this->assertSame(0, $this->db->table('journal_entries')->countAllResults());
    }

    public function testOrderQuantitiesFollowLinkedInvoicesAndCancellation(): void
    {
        $this->cycle();
        $this->db->table('sales_orders')->insert(['id' => 'order', 'company_id' => 'a', 'status' => 'partial']);
        $this->db->table('sales_order_items')->insert(['id' => 'order-line', 'sales_order_id' => 'order', 'quantity' => 3, 'quantity_delivered' => 3, 'quantity_invoiced' => 0]);
        $this->db->table('sales_delivery_notes')->where('id', 'cycle')->update(['sales_order_id' => 'order']);
        $this->db->table('sales_delivery_note_items')->where('id', 'cycle-line')->update(['sales_order_item_id' => 'order-line']);
        $r = $this->save($this->input('parcial', 1, 'cycle', 'cycle'), 'cycle', 'cycle');
        $sale = $this->db->table('sales')->where('id', $r['invoice_id'])->get()->getRowArray();
        $items = $this->db->table('sale_items')->where('sale_id', $sale['id'])->get()->getResultArray();
        $service = new DeliveryNoteInvoicingService();
        $this->assertTrue($service->validateConfirmation('a', $sale, $items));
        $this->db->table('sales')->where('id', $sale['id'])->update(['status' => 'confirmed']);
        $service->syncOrder('a', $sale['id']);
        $this->assertEquals(1, $this->db->table('sales_order_items')->get()->getRowArray()['quantity_invoiced']);
        $this->assertSame('partial', $this->db->table('sales_orders')->get()->getRowArray()['status']);
        $note = (new DeliveryNoteService())->summary('a', 'cycle', 'cycle');
        $this->assertEmpty($note['legacy']); $this->assertEquals(2, $note['items'][0]['pending_quantity']);
        $this->db->table('sales')->where('id', $sale['id'])->update(['status' => 'cancelled']);
        $service->syncOrder('a', $sale['id']);
        $this->assertEquals(0, $this->db->table('sales_order_items')->get()->getRowArray()['quantity_invoiced']);
        $this->assertEquals(3, (new DeliveryNoteService())->summary('a', 'cycle', 'cycle')['items'][0]['pending_quantity']);
    }
    public function testBothControllersSkipStockForLinkedCycleInvoice(): void
    {
        $this->cycle(); $r = $this->save($this->input('total', 0, 'cycle', 'cycle'), 'cycle', 'cycle');
        $sale = $this->db->table('sales')->where('id', $r['invoice_id'])->get()->getRowArray();
        $items = $this->db->table('sale_items')->where('sale_id', $sale['id'])->get()->getResultArray();
        foreach ([\App\Controllers\SalesController::class, \App\Controllers\Api\V1\SalesController::class] as $class) {
            $controller = new $class();
            $method = new ReflectionMethod($class, 'deliverSaleStock'); $method->setAccessible(true);
            $this->assertTrue($method->invoke($controller, 'a', $sale, $items, false, 'VENTA'));
            $method = new ReflectionMethod($class, 'restockDeliveredSale'); $method->setAccessible(true);
            $method->invoke($controller, 'a', $sale, $items, 'ANULACION');
        }
        $this->assertSame(0, $this->db->table('inventory_movements')->countAllResults());
    }
    public function testClosedPeriodRollsBackCancellationAndReturnStock(): void
    {
        $this->cycle();
        $this->db->table('inventory_stock_levels')->insert(['id' => 'stock', 'company_id' => 'a', 'product_id' => 'p', 'warehouse_id' => 'w', 'quantity' => 7, 'reserved_quantity' => 0]);
        $this->db->table('inventory_movements')->insert(['id' => 'out', 'company_id' => 'a', 'product_id' => 'p', 'source_document' => 'RM-1', 'reason' => 'REMITO', 'movement_type' => 'egreso', 'source_warehouse_id' => 'w', 'quantity' => 3]);
        $this->db->table('inventory_period_closures')->insert(['id' => 'closed', 'company_id' => 'a', 'warehouse_id' => 'w', 'status' => 'closed', 'start_date' => '2000-01-01', 'end_date' => '2099-12-31']);
        try {
            (new DeliveryNoteService())->lock('a', 'cycle', 'cycle', fn() => (new DeliveryNoteCancellationService())->cancel('a', 'cycle', 'cycle', 'operator', 'Retorno', true));
            $this->fail('Expected closed period');
        } catch (RuntimeException $e) { $this->assertStringContainsString('periodo cerrado', $e->getMessage()); }
        $this->assertEquals(7, $this->db->table('inventory_stock_levels')->get()->getRowArray()['quantity']);
        $this->assertSame('dispatched', $this->db->table('sales_delivery_notes')->get()->getRowArray()['status']);
        $this->assertSame(1, $this->db->table('inventory_movements')->countAllResults());
    }
    public function testInvalidAndOverPendingQuantitiesAreRejected(): void
    {
        foreach ([11, -1, 0.12345] as $invalid) {
            $input = $this->input(); $input['quantities']['line'] = $invalid;
            try { $this->save($input); $this->fail('Expected invalid quantity'); }
            catch (RuntimeException $e) { $this->assertNotEmpty($e->getMessage()); }
        }
        $this->assertSame(0, $this->db->table('sales_delivery_invoices')->countAllResults());
    }

    public function testDeliveryListHydratesBillingStatusAndShowsOnlyIconsInActionButtons(): void
    {
        $method = new ReflectionMethod(\App\Controllers\SalesController::class, 'deliveryNoteRows'); $method->setAccessible(true);
        $rows = $method->invoke(new \App\Controllers\SalesController(), 'a', []);
        $this->assertCount(1, $rows);
        $this->assertSame('Sin facturar', $rows[0]['billing_status']);
        $this->assertSame('', $rows[0]['invoice_reason']);
        $rows[0]['delivery_date'] = '2026-10-15';
        $data = ['pageTitle' => 'Diarios', 'context' => ['company' => ['id' => 'a', 'name' => 'Demo'], 'canManage' => true], 'user' => ['role_slug' => 'admin'], 'companies' => [], 'selectedCompanyId' => 'a', 'summary' => array_fill_keys(['drafts', 'confirmed', 'returned', 'total_amount'], 0), 'filters' => [], 'customers' => [], 'sales' => [], 'quotes' => [], 'orders' => [], 'deliveryNotes' => $rows];
        $html = view('sales/daily', $data, ['saveData' => false]);
        $dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">' . $html); $xpath = new DOMXPath($dom);
        foreach (['Facturar parcialmente', 'Facturar totalmente', 'Anular'] as $label) {
            $nodes = $xpath->query('//*[@aria-label="' . $label . '"]');
            $this->assertSame(1, $nodes->length);
            $this->assertSame('', trim($nodes->item(0)->textContent));
            $this->assertSame('true', $nodes->item(0)->getAttribute('data-popup'));
            $this->assertSame(1, $xpath->query('.//i', $nodes->item(0))->length);
        }
    }
    public function testNewPopupButtonsHaveIconsAndAccessibleNamesWithoutVisibleText(): void
    {
        $note = (new DeliveryNoteInvoicingService())->prices('a', (new DeliveryNoteService())->summary('a', 'sale', 'source'));
        $data = ['pageTitle' => 'Remito', 'note' => $note, 'companyId' => 'a', 'context' => ['company' => ['id' => 'a'], 'canManage' => true], 'input' => [], 'error' => null, 'draft' => null, 'mode' => 'parcial', 'documentTypes' => [], 'pointsOfSale' => [], 'priceLists' => [], 'taxes' => [], 'formAction' => '/remito-test', 'isPopup' => true];
        foreach (['delivery_invoice', 'delivery_cancel'] as $form) {
            $html = view('sales/forms/' . $form, $data, ['saveData' => false]);
            $dom = new DOMDocument(); @$dom->loadHTML($html); $xpath = new DOMXPath($dom);
            foreach ($xpath->query('//form//button') as $button) {
                $this->assertSame('', trim($button->textContent));
                $this->assertNotSame('', $button->getAttribute('aria-label'));
                $this->assertNotSame('', $button->getAttribute('title'));
                $this->assertSame(1, $xpath->query('.//i', $button)->length);
            }
        }
    }

    private function popupData(): array
    {
        return ['pageTitle' => 'Facturar remito', 'note' => (new DeliveryNoteInvoicingService())->prices('a', (new DeliveryNoteService())->summary('a', 'sale', 'source')),
            'companyId' => 'a', 'context' => ['company' => ['id' => 'a'], 'canManage' => true], 'input' => ['document_type_id' => 'invoice', 'point_of_sale_id' => 'kiosk'],
            'error' => 'El punto de venta no corresponde al comprobante seleccionado.', 'draft' => null, 'mode' => 'parcial',
            'documentTypes' => [['id' => 'invoice', 'name' => 'Factura B', 'channel' => 'standard']],
            'pointsOfSale' => [['id' => 'pos', 'code' => 'PV-STD', 'afip_pos_number' => 12, 'name' => 'Punto de Venta Principal', 'channel' => 'standard', 'document_type_id' => 'invoice'], ['id' => 'kiosk', 'code' => 'PV-KIOSCO', 'afip_pos_number' => 24, 'name' => 'Punto de Venta Kiosco', 'channel' => 'kiosk', 'document_type_id' => 'ticket']],
            'priceLists' => [], 'taxes' => [], 'formAction' => '/remito-test', 'isPopup' => true, 'popupWide' => true];
    }
    public function testPostValidationErrorKeepsPopupWithoutNavigationOrAssistant(): void
    {
        $request = service('request'); $oldGet = $request->getGet(); $oldPost = $request->getPost();
        try {
            $request->setGlobal('get', []); $request->setGlobal('post', ['popup' => '1']);
            $data = $this->popupData(); $data['isPopup'] = false;
            $html = view('sales/forms/delivery_invoice', $data, ['saveData' => false]);
            $this->assertStringContainsString('<body class="popup-mode">', $html);
            $this->assertStringContainsString('popup-shell popup-shell-wide', $html);
            $this->assertStringNotContainsString('<nav class="navbar', $html);
            $this->assertStringNotContainsString('codex-assist-widget.js', $html);
            $this->assertStringContainsString('El punto de venta no corresponde', $html);
        } finally { $request->setGlobal('get', $oldGet); $request->setGlobal('post', $oldPost); }
    }
    public function testStandardInvoicePreselectsPrincipalAndDisablesKiosk(): void
    {
        $html = view('sales/forms/delivery_invoice', $this->popupData(), ['saveData' => false]);
        $dom = new DOMDocument(); @$dom->loadHTML($html); $xpath = new DOMXPath($dom);
        $principal = $xpath->query('//select[@name="point_of_sale_id"]/option[@value="pos"]')->item(0);
        $kiosk = $xpath->query('//select[@name="point_of_sale_id"]/option[@value="kiosk"]')->item(0);
        $this->assertTrue($principal->hasAttribute('selected'));
        $this->assertFalse($principal->hasAttribute('disabled'));
        $this->assertFalse($kiosk->hasAttribute('hidden'));
        $this->assertStringContainsString('PV-STD', $principal->textContent);
        $this->assertStringContainsString('PV 00012', $principal->textContent);
        $this->assertStringContainsString('PV 00024', $kiosk->textContent);
        $this->assertStringContainsString('No compatible con esta factura', $kiosk->textContent);
        $this->assertTrue($kiosk->hasAttribute('disabled'));
        $this->assertStringNotContainsString('<nav class="navbar', $html);
    }
    public function testServerRejectsKioskPointForStandardInvoice(): void
    {
        $this->db->table('sales_points_of_sale')->insert(['id' => 'kiosk', 'company_id' => 'a', 'document_type_id' => 'ticket', 'active' => 1, 'channel' => 'kiosk']);
        $input = $this->input(); $input['point_of_sale_id'] = 'kiosk';
        $this->expectException(RuntimeException::class); $this->expectExceptionMessage('El punto de venta no corresponde');
        $this->save($input);
    }
    public function testBothChannelPointIsAcceptedButDocumentBindingIsPreserved(): void
    {
        $this->db->table('sales_points_of_sale')->where('id', 'pos')->update(['channel' => 'both']);
        $r = $this->save($this->input());
        $this->assertNotEmpty($r['invoice_id']);
        $this->assertTrue(DeliveryNoteInvoicingService::pointSupportsDocument(['document_type_id' => null, 'channel' => 'kiosk'], ['id' => 'invoice', 'channel' => 'both']));
        $this->assertFalse(DeliveryNoteInvoicingService::pointSupportsDocument(['document_type_id' => 'ticket', 'channel' => 'both'], ['id' => 'invoice', 'channel' => 'both']));
        $this->assertFalse(DeliveryNoteInvoicingService::pointSupportsDocument(['channel' => 'invalid'], ['id' => 'invoice', 'channel' => 'standard']));
    }

    public function testSharedDocumentUsesSelectedKioskChannel(): void
    {
        $this->db->table('sales_document_types')->where('id', 'invoice')->update(['channel' => 'both']);
        $this->db->table('sales_points_of_sale')->where('id', 'pos')->update(['channel' => 'kiosk']);
        $r = $this->save($this->input());
        $sale = $this->db->table('sales')->where('id', $r['invoice_id'])->get()->getRowArray();
        $this->assertEquals(1, $sale['pos_mode']);
    }

}
