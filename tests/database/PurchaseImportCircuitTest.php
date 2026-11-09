<?php

use App\Libraries\{AccountingService, PurchaseFlowService, PurchaseImportCircuit, PurchaseImportService};
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/** The invoice assistant drives order -> approval -> receipt (stock) -> invoice with the real purchase rules. */
final class PurchaseImportCircuitTest extends CIUnitTestCase
{
    use \Tests\Support\IntegrationEventsFixture;

    private array $tables = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->installEventFixture();
        helper(['app', 'url']);
        $this->db = db_connect('tests');
        $this->db->resetDataCache();
        foreach (['Company', 'Supplier', 'PurchaseOrder', 'PurchaseOrderItem', 'PurchaseReceipt', 'PurchaseReceiptItem', 'PurchaseReturn', 'PurchaseReturnItem',
            'PurchaseInvoice', 'PurchaseInvoiceItem', 'PurchaseCreditNote', 'PurchasePayable', 'SupplierCostHistory', 'VoucherSequence',
            'InventoryProduct', 'InventoryWarehouse', 'InventoryLocation', 'InventoryStockLevel', 'InventoryMovement', 'InventoryLot', 'InventorySerial',
            'InventoryCostLayer', 'InventorySetting', 'InventoryPeriodClosure'] as $name) {
            $class = 'App\\Models\\' . $name . 'Model';
            $ref = new ReflectionClass(new $class());
            $property = $ref->getProperty('allowedFields');
            $property->setAccessible(true);
            $fields = array_unique(array_merge(['id', 'created_at', 'updated_at'], $property->getValue(new $class())));
            $table = $ref->getProperty('table');
            $table->setAccessible(true);
            $table = $table->getValue(new $class());
            $columns = array_map(static fn($field) => '"' . $field . '" ' . ($field === 'id' ? 'TEXT PRIMARY KEY'
                : (preg_match('/quantity|amount|cost|price|rate|subtotal|tax_total|^total$|^active$|^current_number$|stock|control|payment_terms_days|units_per_package/', $field) ? 'REAL DEFAULT 0' : 'TEXT')), $fields);
            $this->db->query('CREATE TABLE ' . $this->db->prefixTable($table) . ' (' . implode(', ', $columns) . ')');
            $this->tables[] = $table;
        }
        $this->db->query('CREATE TABLE ' . $this->db->prefixTable('currencies') . ' (id TEXT PRIMARY KEY, company_id TEXT, code TEXT, name TEXT, active INTEGER)');
        $this->tables[] = 'currencies';
        foreach (['CreatePurchaseInvoiceImports' => '2026-10-16-150000', 'KeepPurchaseImportExtractionHistory' => '2026-10-16-152000',
            'RecordPurchaseImportProvider' => '2026-10-16-153000', 'PurchaseAssistantCircuit' => '2026-11-07-120000'] as $migration => $stamp) {
            require_once APPPATH . 'Database/Migrations/' . $stamp . '_' . $migration . '.php';
            $class = '\\App\\Database\\Migrations\\' . $migration;
            (new $class(\Config\Database::forge('tests')))->up();
        }
        $this->tables = array_merge($this->tables, ['purchase_documents', 'purchase_imports', 'purchase_import_items', 'purchase_import_jobs', 'purchase_import_audit', 'supplier_product_links']);

        $this->put('companies', ['id' => 'a', 'currency_code' => 'ARS', 'tax_id' => '30719167124']);
        $this->put('currencies', ['id' => 'ars', 'company_id' => 'a', 'code' => 'ARS', 'name' => 'Pesos', 'active' => 1]);
        $this->put('suppliers', ['id' => 's', 'company_id' => 'a', 'name' => 'Talca', 'tax_id' => '30707943463', 'active' => 1]);
        $this->put('inventory_products', ['id' => 'cola', 'company_id' => 'a', 'sku' => '5670', 'name' => 'Talca Cola 3L', 'active' => 1, 'cost_price' => 0]);
        $this->put('inventory_products', ['id' => 'lima', 'company_id' => 'a', 'sku' => '5056', 'name' => 'Talca Lima 500', 'active' => 1, 'cost_price' => 0]);
        $this->put('inventory_warehouses', ['id' => 'w', 'company_id' => 'a', 'name' => 'Central', 'active' => 1]);
        $this->put('inventory_settings', ['id' => 'setting', 'company_id' => 'a', 'valuation_method' => 'fifo']);
        foreach (['OCOMPRA', 'RCOMPRA', 'PAGCP'] as $type) {
            $this->put('voucher_sequences', ['id' => $type, 'company_id' => 'a', 'document_type' => $type, 'current_number' => 1, 'active' => 1]);
        }
        $accounting = $this->createMock(AccountingService::class);
        $accounting->method('syncPurchaseReceipt')->willReturn(['ok' => true]);
        Services::injectMock('accounting', $accounting);
    }

    protected function tearDown(): void
    {
        $this->removeEventFixture();
        foreach (array_reverse($this->tables) as $table) {
            $this->db->query('DROP TABLE IF EXISTS ' . $this->db->prefixTable($table));
        }
        $this->db->resetDataCache();
        parent::tearDown();
    }

    private function put(string $table, array $row): void
    {
        $this->db->table($table)->insert($row);
    }

    /** Talca-like invoice: 15 packs x6 of cola and 2 packs x12 of lima, 21% VAT and a VAT perception. */
    private function draft(array $replace = []): array
    {
        $this->put('purchase_documents', ['id' => 'doc', 'company_id' => 'a', 'sha256' => str_repeat('a', 64), 'storage_path' => 'a/doc.pdf', 'original_name' => 'Talca.pdf',
            'mime_type' => 'application/pdf', 'byte_size' => 10, 'page_count' => 1, 'created_by' => 'admin']);
        $data = array_replace(['supplier_id' => 's', 'supplier_tax_id' => '30707943463', 'receiver_tax_id' => '30719167124', 'document_type' => 'FACTURA_A',
            'point_of_sale' => '01002', 'invoice_sequence' => '00118817', 'currency_code' => 'ARS', 'exchange_rate' => '1', 'purchase_kind' => 'goods',
            'issue_date' => '2026-04-30', 'reviewed_total' => '169790.56', 'identity_reviewed' => true,
            'adjustments' => [['kind' => 'tax', 'label' => 'Perc. IVA', 'amount' => '23102.89']]], $replace);
        $this->put('purchase_imports', ['id' => 'imp', 'company_id' => 'a', 'document_id' => 'doc', 'processing_status' => 'done', 'review_status' => 'ready',
            'version' => 1, 'generation' => 1, 'data_json' => json_encode($data), 'created_by' => 'admin']);
        foreach ([['description' => '5670-TALCA COLA PET 3L *6U', 'quantity' => '15', 'unit_cost' => '7149.58', 'tax_rate' => '21', 'product_id' => 'cola', 'supplier_code' => '5670', 'units_per_package' => '6'],
            ['description' => '5056-TALCA LIMA LIMON 1/2L *12U', 'quantity' => '2', 'unit_cost' => '6992.89', 'tax_rate' => '21', 'product_id' => 'lima', 'supplier_code' => '5056', 'units_per_package' => '12']] as $index => $item) {
            $this->put('purchase_import_items', ['id' => 'item' . $index, 'import_id' => 'imp', 'line_number' => $index + 1, 'data_json' => json_encode($item)]);
        }

        return (new PurchaseImportService())->get('a', 'imp');
    }

    private function stock(string $product): float
    {
        return (float) ($this->db->table('inventory_stock_levels')->selectSum('quantity', 'q')->where('product_id', $product)->get()->getRowArray()['q'] ?? 0);
    }

    private function receiveAll(PurchaseImportCircuit $circuit, array $result): array
    {
        $lines = array_map(static fn($line) => ['purchase_order_item_id' => $line['id'], 'quantity' => $line['pending_quantity']], $result['circuit']['order']['lines']);

        return $circuit->receive('a', 'imp', ['version' => $result['draft']['version'], 'lines' => $lines], 'admin', null);
    }

    public function testFullCircuitConvertsPackagesReceivesStockAndRegistersLinkedInvoice(): void
    {
        $draft = $this->draft();
        $circuit = new PurchaseImportCircuit();

        $state = $circuit->state('a', 'imp');
        $this->assertSame([90.0, 24.0], array_column($state['preview'], 'quantity'));
        $this->assertSame([1191.5967, 582.7408], array_column($state['preview'], 'unit_cost'));
        $this->assertFalse($state['steps']['order']);

        $result = $circuit->createOrder('a', 'imp', ['version' => $draft['version'], 'warehouse_id' => 'w'], 'admin', null);
        $order = $result['circuit']['order'];
        $this->assertSame('draft', $order['status']);
        $this->assertSame('w', $order['warehouse_id']);
        $this->assertSame([90.0, 24.0], array_map('floatval', array_column($order['lines'], 'quantity')));
        $this->assertSame(0.0, $this->stock('cola'), 'An order must not move stock.');

        $result = $circuit->approveOrder('a', 'imp', ['version' => $result['draft']['version']], 'admin');
        $this->assertSame('approved', $result['circuit']['order']['status']);
        $this->assertTrue($result['circuit']['steps']['approval']);

        $result = $this->receiveAll($circuit, $result);
        $this->assertTrue($result['circuit']['steps']['receipt']);
        $this->assertSame(90.0, $this->stock('cola'));
        $this->assertSame(24.0, $this->stock('lima'));
        $receipt = $result['circuit']['receipt'];
        $this->assertSame('01002-00118817', $receipt['supplier_document']);
        $this->assertEqualsWithDelta(146687.67, (float) $receipt['total'], 0.001);
        $this->assertSame(2, $this->db->table('inventory_movements')->where('reason', 'recepcion_compra')->where('source_document', $receipt['receipt_number'])->countAllResults());
        $this->assertSame($receipt['id'], $result['draft']['data']['purchase_receipt_id']);

        $registered = (new PurchaseImportService())->confirm('a', 'imp', ['version' => $result['draft']['version'], 'request_key' => 'circuit-request-key-0001'], 'admin');
        $this->assertSame('registered', $registered['review_status']);
        $invoice = $this->db->table('purchase_invoices')->get()->getRowArray();
        $this->assertSame($receipt['id'], $invoice['purchase_receipt_id']);
        $this->assertEqualsWithDelta(169790.56, (float) $invoice['total'], 0.001);
        $this->assertSame([6.0, 12.0], array_map('floatval', array_column($this->db->table('purchase_invoice_items')->orderBy('unit_cost', 'DESC')->get()->getResultArray(), 'units_per_package')));
        $payable = $this->db->table('purchase_payables')->get()->getResultArray();
        $this->assertCount(1, $payable, 'Receipt and invoice must share a single payable.');
        $this->assertEqualsWithDelta(169790.56, (float) $payable[0]['total_amount'], 0.001);
        $link = $this->db->table('supplier_product_links')->where('supplier_code', '5670')->get()->getRowArray();
        $this->assertSame('cola', $link['product_id']);
        $this->assertEqualsWithDelta(6.0, (float) $link['units_per_package'], 0.0001);
        $this->assertSame(['order_created', 'order_approved', 'received', 'confirmed'],
            array_values(array_intersect(array_column($this->db->table('purchase_import_audit')->orderBy('created_at')->get()->getResultArray(), 'action'), ['order_created', 'order_approved', 'received', 'confirmed'])));
    }

    public function testStepsAreIdempotentOrderedAndLockTheLines(): void
    {
        $draft = $this->draft();
        $circuit = new PurchaseImportCircuit();
        $created = $circuit->createOrder('a', 'imp', ['version' => $draft['version'], 'warehouse_id' => 'w'], 'admin', null);
        $again = $circuit->createOrder('a', 'imp', ['version' => $created['draft']['version'], 'warehouse_id' => 'w'], 'admin', null);
        $this->assertSame($created['circuit']['order']['id'], $again['circuit']['order']['id']);
        $this->assertSame(1, $this->db->table('purchase_orders')->countAllResults());

        try {
            $this->receiveAll($circuit, $again);
            $this->fail('Receiving before approval must be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('aprobadas', $e->getMessage());
        }
        $this->assertSame(0.0, $this->stock('cola'));

        try {
            $circuit->approveOrder('a', 'imp', ['version' => 999], 'admin');
            $this->fail('A stale version must be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('otra sesión', $e->getMessage());
        }

        $items = $again['draft']['data']['items'];
        $items[0]['quantity'] = '99';
        $saved = (new PurchaseImportService())->save('a', 'imp', ['version' => $again['draft']['version'], 'data' => array_merge($again['draft']['data'], ['items' => $items])], 'admin');
        $this->assertSame('15', $saved['data']['items'][0]['quantity'], 'Lines are fixed once the order exists.');

        try {
            (new PurchaseImportService())->confirm('a', 'imp', ['version' => $saved['version'], 'request_key' => 'circuit-request-key-0002'], 'admin');
            $this->fail('The invoice cannot be registered before the receipt.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('recepción', $e->getMessage());
        }
    }

    public function testExistingPendingOrderCanBeUsed(): void
    {
        $this->put('purchase_orders', ['id' => 'old', 'company_id' => 'a', 'supplier_id' => 's', 'warehouse_id' => 'w', 'order_number' => 'OC-OLD', 'status' => 'approved', 'currency_code' => 'ARS']);
        $this->put('purchase_order_items', ['id' => 'old-cola', 'purchase_order_id' => 'old', 'product_id' => 'cola', 'quantity' => 90, 'received_quantity' => 0, 'unit_cost' => 1191.5967, 'tax_rate' => 21]);
        $this->put('purchase_order_items', ['id' => 'old-lima', 'purchase_order_id' => 'old', 'product_id' => 'lima', 'quantity' => 24, 'received_quantity' => 0, 'unit_cost' => 582.7408, 'tax_rate' => 21]);
        $draft = $this->draft();
        $circuit = new PurchaseImportCircuit();
        $this->assertSame(['old'], array_column($circuit->state('a', 'imp')['pending_orders'], 'id'));

        $linked = $circuit->createOrder('a', 'imp', ['version' => $draft['version'], 'order_id' => 'old'], 'admin', null);
        $this->assertSame('old', $linked['circuit']['order']['id']);
        $this->assertTrue($linked['circuit']['steps']['approval'], 'An approved order needs no second approval.');
        $this->assertSame(1, $this->db->table('purchase_orders')->countAllResults());

        $received = $this->receiveAll($circuit, $linked);
        $this->assertSame(90.0, $this->stock('cola'));
        $this->assertSame('received_total', $this->db->table('purchase_orders')->where('id', 'old')->get()->getRowArray()['status']);
        $this->assertNotNull($received['circuit']['receipt']);
    }

    public function testNonGoodsAndUnlinkedLinesCannotStartTheCircuit(): void
    {
        $draft = $this->draft(['purchase_kind' => 'pending_receipt']);
        try {
            (new PurchaseImportCircuit())->createOrder('a', 'imp', ['version' => $draft['version'], 'warehouse_id' => 'w'], 'admin', null);
            $this->fail('Only goods generate an order.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('mercadería', $e->getMessage());
        }
        $this->assertSame(0, $this->db->table('purchase_orders')->countAllResults());

        $this->expectExceptionMessage('Vincula el concepto 1');
        PurchaseImportCircuit::stockLines(['items' => [['description' => 'Sin producto', 'quantity' => '1', 'unit_cost' => '10']]]);
    }

    public function testFlowServiceRoundsCostsToFourDecimalsAndSkipsForeignProducts(): void
    {
        $lines = (new PurchaseFlowService())->orderLines('a', [
            ['product_id' => 'cola', 'quantity' => 90, 'unit_cost' => 1191.596666, 'tax_rate' => 21],
            ['product_id' => 'not-in-company', 'quantity' => 5, 'unit_cost' => 1],
        ]);
        $this->assertCount(1, $lines);
        $this->assertSame(1191.5967, $lines[0]['unit_cost']);
        $this->assertSame(129764.88, $lines[0]['line_total']);
    }
}
