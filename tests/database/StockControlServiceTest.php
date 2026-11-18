<?php

use App\Libraries\StockControlService;
use CodeIgniter\Test\CIUnitTestCase;

/** Weekly cycle count: prioritized selection, blind count snapshot, approval adjustments and the alert report. */
final class StockControlServiceTest extends CIUnitTestCase
{
    protected $db;
    private array $tables = [];

    protected function setUp(): void
    {
        parent::setUp();
        helper(['app', 'url']);
        $this->db = db_connect('tests');
        // Earlier suites can leave an open/failed transaction on the shared connection.
        while ($this->db->transDepth > 0) {
            $this->db->transRollback();
        }
        $status = new ReflectionProperty($this->db, 'transStatus');
        $status->setAccessible(true);
        $status->setValue($this->db, true);
        $schemas = [
            'companies' => 'id TEXT PRIMARY KEY, name TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT',
            'users' => 'id TEXT PRIMARY KEY, name TEXT',
            'branches' => 'id TEXT PRIMARY KEY, company_id TEXT, code TEXT, active INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT',
            'voucher_sequences' => 'id TEXT PRIMARY KEY, company_id TEXT, branch_id TEXT, document_type TEXT, prefix TEXT, current_number INTEGER, active INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT',
            'inventory_products' => "id TEXT PRIMARY KEY, company_id TEXT, sku TEXT, name TEXT, unit TEXT, product_type TEXT DEFAULT 'simple', active INTEGER, lot_control INTEGER DEFAULT 0, serial_control INTEGER DEFAULT 0, min_stock REAL, cost_price REAL",
            'inventory_warehouses' => 'id TEXT PRIMARY KEY, company_id TEXT, name TEXT, is_default INTEGER, active INTEGER',
            'inventory_locations' => 'id TEXT PRIMARY KEY, company_id TEXT, warehouse_id TEXT, active INTEGER',
            'inventory_settings' => 'id TEXT PRIMARY KEY, company_id TEXT, allow_negative_stock INTEGER, valuation_method TEXT, no_rotation_days INTEGER, cycle_count_size INTEGER, cycle_count_threshold_pct REAL',
            'inventory_stock_levels' => 'id TEXT PRIMARY KEY, company_id TEXT, product_id TEXT, warehouse_id TEXT, location_id TEXT, quantity REAL, reserved_quantity REAL, min_stock REAL, created_at TEXT, updated_at TEXT',
            'inventory_period_closures' => 'id TEXT PRIMARY KEY, company_id TEXT, warehouse_id TEXT, status TEXT, start_date TEXT, end_date TEXT',
            'inventory_movements' => 'id TEXT PRIMARY KEY, company_id TEXT, product_id TEXT, movement_type TEXT, quantity REAL, unit_cost REAL, total_cost REAL, adjustment_mode TEXT, source_warehouse_id TEXT, source_location_id TEXT, destination_warehouse_id TEXT, destination_location_id TEXT, performed_by TEXT, occurred_at TEXT, reason TEXT, source_document TEXT, lot_number TEXT, serial_number TEXT, expiration_date TEXT, notes TEXT, created_at TEXT, updated_at TEXT',
            'inventory_lots' => 'id TEXT PRIMARY KEY, company_id TEXT, product_id TEXT, warehouse_id TEXT, location_id TEXT, lot_number TEXT, expiration_date TEXT, quantity_balance REAL, status TEXT, created_at TEXT, updated_at TEXT',
            'inventory_serials' => 'id TEXT PRIMARY KEY, company_id TEXT, product_id TEXT, warehouse_id TEXT, location_id TEXT, serial_number TEXT, lot_number TEXT, expiration_date TEXT, status TEXT, last_movement_id TEXT, created_at TEXT, updated_at TEXT',
            'inventory_cost_layers' => 'id TEXT PRIMARY KEY, company_id TEXT, product_id TEXT, warehouse_id TEXT, location_id TEXT, movement_id TEXT, layer_type TEXT, quantity REAL, remaining_quantity REAL, unit_cost REAL, total_cost REAL, occurred_at TEXT, created_at TEXT, updated_at TEXT',
            'sales' => 'id TEXT PRIMARY KEY, company_id TEXT, sale_number TEXT, issue_date TEXT, status TEXT, created_by TEXT, created_at TEXT',
            'sale_items' => 'id TEXT PRIMARY KEY, sale_id TEXT, product_id TEXT',
            'sale_payments' => 'id TEXT PRIMARY KEY, sale_id TEXT, amount REAL, status TEXT',
            'inventory_counts' => 'id TEXT PRIMARY KEY, company_id TEXT, warehouse_id TEXT, count_number TEXT, week TEXT, status TEXT, items_count INTEGER, difference_value REAL, created_by TEXT, approved_by TEXT, approved_at TEXT, notes TEXT, created_at TEXT, updated_at TEXT',
            'inventory_count_items' => 'id TEXT PRIMARY KEY, count_id TEXT, product_id TEXT, selection_reason TEXT, system_quantity REAL, counted_quantity REAL, difference REAL, unit_cost REAL, difference_value REAL, counted_at TEXT, moved_after_count INTEGER, reason TEXT, approved INTEGER, movement_id TEXT, created_at TEXT, updated_at TEXT',
            'inventory_count_reports' => 'id TEXT PRIMARY KEY, company_id TEXT, week TEXT, report_json TEXT, created_by TEXT, created_at TEXT, updated_at TEXT',
        ];
        foreach ($schemas as $table => $columns) {
            $this->db->query('CREATE TABLE ' . $this->db->prefixTable($table) . ' (' . $columns . ')');
            $this->tables[] = $table;
        }
        $this->db->query('CREATE UNIQUE INDEX test_stock_control_location ON ' . $this->db->prefixTable('inventory_stock_levels') . " (company_id, product_id, warehouse_id, COALESCE(location_id, ''))");
        $this->db->table('companies')->insertBatch([['id' => 'a', 'name' => 'A'], ['id' => 'b', 'name' => 'B']]);
        $this->db->table('users')->insert(['id' => 'u', 'name' => 'Operador']);
        $this->db->table('voucher_sequences')->insert(['id' => 'seq', 'company_id' => 'a', 'document_type' => 'CTRLINV', 'prefix' => 'CTRL', 'current_number' => 1, 'active' => 1]);
        $this->db->table('inventory_warehouses')->insertBatch([['id' => 'w', 'company_id' => 'a', 'name' => 'Central', 'is_default' => 1, 'active' => 1],
            ['id' => 'wb', 'company_id' => 'b', 'name' => 'Otro', 'is_default' => 1, 'active' => 1]]);
        $this->db->table('inventory_settings')->insert(['id' => 's', 'company_id' => 'a', 'allow_negative_stock' => 0, 'valuation_method' => 'fifo', 'no_rotation_days' => 30]);
        foreach (range(1, 8) as $n) {
            $this->product("p{$n}", 10);
        }
        $this->db->table('inventory_products')->insertBatch([
            ['id' => 'kit', 'company_id' => 'a', 'sku' => 'KIT', 'name' => 'Kit', 'product_type' => 'kit', 'active' => 1, 'cost_price' => 1],
            ['id' => 'srv', 'company_id' => 'a', 'sku' => 'SRV', 'name' => 'Servicio', 'product_type' => 'service', 'active' => 1, 'cost_price' => 1],
            ['id' => 'bx', 'company_id' => 'b', 'sku' => 'BX', 'name' => 'Ajeno', 'product_type' => 'simple', 'active' => 1, 'cost_price' => 1],
        ]);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            $this->db->query('DROP TABLE IF EXISTS ' . $this->db->prefixTable($table));
        }
        parent::tearDown();
    }

    private function product(string $id, float $stock): void
    {
        $this->db->table('inventory_products')->insert(['id' => $id, 'company_id' => 'a', 'sku' => strtoupper($id), 'name' => 'Producto ' . $id, 'product_type' => 'simple', 'active' => 1, 'cost_price' => 5]);
        $this->db->table('inventory_stock_levels')->insert(['id' => 'sl-' . $id, 'company_id' => 'a', 'product_id' => $id, 'warehouse_id' => 'w', 'quantity' => $stock, 'reserved_quantity' => 0]);
    }

    private function move(string $product, string $type, float $qty, string $reason = 'Venta', ?string $mode = null, string $when = 'now'): void
    {
        $this->db->table('inventory_movements')->insert(['id' => app_uuid(), 'company_id' => 'a', 'product_id' => $product, 'movement_type' => $type,
            'adjustment_mode' => $mode, 'quantity' => $qty, 'source_warehouse_id' => $type === 'ingreso' ? null : 'w', 'destination_warehouse_id' => $type === 'ingreso' ? 'w' : null, 'reason' => $reason, 'performed_by' => 'u', 'occurred_at' => date('Y-m-d H:i:s', strtotime($when))]);
    }

    private function stock(string $product): float
    {
        return (float) $this->db->table('inventory_stock_levels')->selectSum('quantity', 'q')->where('product_id', $product)->get()->getRowArray()['q'];
    }

    public function testSelectionPrioritizesAlertsThenMovementAndExcludesKitsAndServices(): void
    {
        $this->move('p5', 'ingreso', 100, 'Ajuste manual de stock');
        $this->move('p3', 'egreso', 1);
        $this->move('p3', 'egreso', 1);
        $this->move('p7', 'egreso', 1);
        $ids = array_column((new StockControlService())->select('a', 'w'), 'id');

        $this->assertCount(2, $ids, 'Default size is a quarter of the 8 countable products.');
        $this->assertSame(['p5', 'p3'], $ids);
        $all = array_column((new StockControlService())->select('a', 'w', 50), 'id');
        $this->assertNotContains('kit', $all);
        $this->assertNotContains('srv', $all);
        $this->assertNotContains('bx', $all);
    }

    public function testWholeCatalogIsCoveredInFourWeeklyCounts(): void
    {
        $service = new StockControlService();
        $seen = [];
        for ($week = 0; $week < 4; $week++) {
            $count = $service->start('a', 'w', 'u');
            foreach ($count['items'] as $item) {
                $service->record('a', $count['id'], $item['product_id'], $this->stock($item['product_id']));
                $seen[$item['product_id']] = true;
            }
            $service->close('a', $count['id']);
            $service->approve('a', $count['id'], [], 'u');
            $this->db->table('inventory_counts')->where('id', $count['id'])->update(['week' => 'old-' . $week]);
        }
        $this->assertCount(8, $seen);
    }

    public function testDifferenceUsesSnapshotAndFlagsLaterMovements(): void
    {
        $service = new StockControlService();
        $count = $service->start('a', 'w', 'u');
        $item = $count['items'][0];
        $service->record('a', $count['id'], $item['product_id'], 7);
        $this->move($item['product_id'], 'egreso', 1, 'Venta', null, '+1 minute');
        $closed = $service->close('a', $count['id']);
        $line = array_values(array_filter($closed['items'], static fn($i) => $i['id'] === $item['id']))[0];

        $this->assertEquals(10, $line['system_quantity']);
        $this->assertEquals(-3, $line['difference']);
        $this->assertEquals(-15, $line['difference_value']);
        $this->assertSame(1, (int) $line['moved_after_count']);
        $this->assertSame('counted', $closed['status']);
    }

    public function testApprovalAdjustsStockToCountedOnlyForApprovedLines(): void
    {
        $service = new StockControlService();
        $count = $service->start('a', 'w', 'u');
        [$first, $second] = $count['items'];
        $service->record('a', $count['id'], $first['product_id'], 36);
        $service->record('a', $count['id'], $second['product_id'], 6);
        $service->close('a', $count['id']);

        try {
            $service->approve('a', $count['id'], [$first['id'] => ['approved' => true]], 'u');
            $this->fail('A reason is required.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('motivo', $e->getMessage());
        }
        $approved = $service->approve('a', $count['id'], [$first['id'] => ['approved' => true, 'reason' => 'Error de carga'], $second['id'] => ['approved' => false]], 'u');

        $this->assertSame('approved', $approved['status']);
        $this->assertEquals(36, $this->stock($first['product_id']));
        $this->assertEquals(10, $this->stock($second['product_id']), 'Unapproved line does not move stock.');
        $movement = $this->db->table('inventory_movements')->where('reason', StockControlService::CONTROL_REASON)->get()->getResultArray();
        $this->assertCount(1, $movement);
        $this->assertSame('CTRL-00000001', $movement[0]['source_document']);
        $this->assertSame('increase', $movement[0]['adjustment_mode']);
        $this->assertEquals(26, $movement[0]['quantity']);

        $this->expectException(RuntimeException::class);
        $service->record('a', $count['id'], $first['product_id'], 1);
    }

    /** Arma un control cerrado con un solo producto contado. */
    private function closedCountFor(string $product, float $counted): array
    {
        $service = new StockControlService();
        $count = $service->start('a', 'w', 'u');
        $service->record('a', $count['id'], $product, $counted);
        $closed = $service->close('a', $count['id']);
        $item = array_values(array_filter($closed['items'], static fn($i) => $i['product_id'] === $product))[0];

        return [$service, $closed, $item];
    }

    private function lot(string $product, string $number, float $balance, ?string $expiration): void
    {
        $this->db->table('inventory_lots')->insert(['id' => app_uuid(), 'company_id' => 'a', 'product_id' => $product, 'warehouse_id' => 'w', 'location_id' => null,
            'lot_number' => $number, 'expiration_date' => $expiration, 'quantity_balance' => $balance, 'status' => 'active']);
    }

    private function lotBalance(string $product, string $number): float
    {
        return (float) ($this->db->table('inventory_lots')->where('product_id', $product)->where('lot_number', $number)->get()->getRowArray()['quantity_balance'] ?? -1);
    }

    public function testLotShortageIsTakenFromTheLotThatExpiresFirst(): void
    {
        $this->db->table('inventory_products')->where('id', 'p1')->update(['lot_control' => 1]);
        $this->lot('p1', 'L-TARDE', 6, '2027-06-01');
        $this->lot('p1', 'L-PRONTO', 4, '2027-01-01');
        [$service, $count, $item] = $this->closedCountFor('p1', 4);

        $service->approve('a', $count['id'], [$item['id'] => ['approved' => true, 'reason' => 'Faltante / robo']], 'u');

        $this->assertEquals(4, $this->stock('p1'));
        $this->assertEquals(0, $this->lotBalance('p1', 'L-PRONTO'), 'First the lot that expires first.');
        $this->assertEquals(4, $this->lotBalance('p1', 'L-TARDE'));
        $lots = array_column($this->db->table('inventory_movements')->where('source_document', $count['count_number'])->get()->getResultArray(), 'quantity', 'lot_number');
        $this->assertEquals(['L-PRONTO' => 4, 'L-TARDE' => 2], $lots);
    }

    public function testSerialsAreRequiredOnlyWhenEnoughAreAvailable(): void
    {
        $this->db->table('inventory_products')->where('id', 'p2')->update(['serial_control' => 1]);
        foreach (['S1', 'S2', 'S3'] as $serial) {
            $this->db->table('inventory_serials')->insert(['id' => app_uuid(), 'company_id' => 'a', 'product_id' => 'p2', 'warehouse_id' => 'w', 'serial_number' => $serial, 'status' => 'available']);
        }
        [$service, $count, $item] = $this->closedCountFor('p2', 8);
        try {
            $service->approve('a', $count['id'], [$item['id'] => ['approved' => true, 'reason' => 'Rotura', 'serials' => ['S1']]], 'u');
            $this->fail('Two serials must be chosen.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Elige 2', $e->getMessage());
        }
        $service->approve('a', $count['id'], [$item['id'] => ['approved' => true, 'reason' => 'Rotura', 'serials' => ['S1', 'S3']]], 'u');

        $this->assertEquals(8, $this->stock('p2'));
        $states = array_column($this->db->table('inventory_serials')->where('product_id', 'p2')->get()->getResultArray(), 'status', 'serial_number');
        $this->assertSame(['S1' => 'consumed', 'S2' => 'available', 'S3' => 'consumed'], $states);
    }

    public function testLotAndSerialProductWithoutUnitSerialsStillAdjusts(): void
    {
        // Caso VINO BLANCO: una sola serie para todo el lote y el lote con más saldo que el stock.
        $this->db->table('inventory_products')->where('id', 'p3')->update(['lot_control' => 1, 'serial_control' => 1]);
        $this->lot('p3', 'LOTE-01', 12, null);
        $this->db->table('inventory_serials')->insert(['id' => app_uuid(), 'company_id' => 'a', 'product_id' => 'p3', 'warehouse_id' => 'w', 'serial_number' => '00000011', 'lot_number' => 'LOTE-01', 'status' => 'available']);
        [$service, $count, $item] = $this->closedCountFor('p3', 2);

        $approved = $service->approve('a', $count['id'], [$item['id'] => ['approved' => true, 'reason' => 'Faltante / robo']], 'u');

        $this->assertEquals(2, $this->stock('p3'));
        $this->assertEquals(4, $this->lotBalance('p3', 'LOTE-01'));
        $movement = $this->db->table('inventory_movements')->where('source_document', $count['count_number'])->get()->getRowArray();
        $this->assertStringContainsString('series no cargadas por unidad', $movement['notes']);
        $this->assertEquals(-40, $approved['difference_value']);
    }

    public function testLotSurplusEntersANewLotNamedAfterTheControl(): void
    {
        $this->db->table('inventory_products')->where('id', 'p4')->update(['lot_control' => 1]);
        [$service, $count, $item] = $this->closedCountFor('p4', 13);

        $service->approve('a', $count['id'], [$item['id'] => ['approved' => true, 'reason' => 'Compra sin registrar']], 'u');

        $this->assertEquals(13, $this->stock('p4'));
        $this->assertEquals(3, $this->lotBalance('p4', $count['count_number']));
    }

    public function testOtherCompanyCannotSeeOrTouchTheCount(): void
    {
        $service = new StockControlService();
        $count = $service->start('a', 'w', 'u');
        $this->assertSame([], $service->history('b'));
        $this->expectException(RuntimeException::class);
        $service->get('b', $count['id']);
    }

    public function testReportDetectsPaidDraftsManualEntriesAndNoRotation(): void
    {
        $this->move('p1', 'ingreso', 50, 'Ajuste manual de stock');
        $this->move('p2', 'egreso', 1);
        $this->db->table('sales')->insert(['id' => 's1', 'company_id' => 'a', 'sale_number' => 'V-1', 'issue_date' => date('Y-m-d'), 'status' => 'draft', 'created_by' => 'u', 'created_at' => date('Y-m-d H:i:s')]);
        $this->db->table('sale_items')->insert(['id' => 'si', 'sale_id' => 's1', 'product_id' => 'p4']);
        $this->db->table('sale_payments')->insert(['id' => 'sp', 'sale_id' => 's1', 'amount' => 1500, 'status' => 'registered']);
        $report = (new StockControlService())->report('a', 7, 'u');

        $this->assertSame(['P1'], array_column($report['manual_entries'], 'sku'));
        $this->assertSame('V-1', $report['paid_drafts'][0]['sale_number']);
        $this->assertEquals(1500, $report['paid_drafts'][0]['paid']);
        $this->assertNotContains('P2', array_column($report['no_rotation'], 'sku'));
        $this->assertContains('P1', array_column($report['no_rotation'], 'sku'));
        $this->assertSame(8, $report['coverage']['catalog']);
        $this->assertArrayHasKey('p4', (new StockControlService())->alertProducts('a'));
        $this->assertSame(1, $this->db->table('inventory_count_reports')->where('company_id', 'a')->countAllResults());
        $this->assertEquals(50, $report['summary']['manual_units']);
        $this->assertEquals(1500, $report['summary']['paid_total']);
        $this->assertSame('p1', $report['manual_entries'][0]['product_id']);
    }

    public function testReportFiltersDraftsByPeriodAndExcludesKits(): void
    {
        $old = date('Y-m-d H:i:s', strtotime('-40 days'));
        $this->db->table('sales')->insert(['id' => 'old', 'company_id' => 'a', 'sale_number' => 'V-OLD', 'issue_date' => substr($old, 0, 10), 'status' => 'draft', 'created_by' => 'u', 'created_at' => $old]);
        $this->db->table('sale_payments')->insert(['id' => 'op', 'sale_id' => 'old', 'amount' => 900, 'status' => 'registered']);
        $this->db->table('inventory_stock_levels')->insertBatch([
            ['id' => 'sl-kit', 'company_id' => 'a', 'product_id' => 'kit', 'warehouse_id' => 'w', 'location_id' => null, 'quantity' => -4, 'reserved_quantity' => 0],
            ['id' => 'sl-neg', 'company_id' => 'a', 'product_id' => 'p8', 'warehouse_id' => 'w', 'location_id' => 'x', 'quantity' => -12, 'reserved_quantity' => 0],
        ]);
        $service = new StockControlService();

        $this->assertSame([], $service->report('a', 7)['paid_drafts']);
        $this->assertSame(['V-OLD'], array_column($service->report('a', 60)['paid_drafts'], 'sale_number'));
        $report = $service->report('a', 7);
        $this->assertSame(['P8'], array_column($report['negative_stock'], 'sku'), 'Kits never appear as negative stock.');
        $this->assertEquals(-10, $report['negative_stock'][0]['value']);
        $this->assertNotContains('KIT', array_column($report['no_rotation'], 'sku'));
    }

    public function testItemsPopupKeepsCountBlindUntilClosedAndShowsAdjustments(): void
    {
        helper('auth');
        $service = new StockControlService();
        $count = $service->start('a', 'w', 'u');
        $item = $count['items'][0];
        $service->record('a', $count['id'], $item['product_id'], 4);
        $open = view('inventory/control/items', ['count' => $service->get('a', $count['id']), 'companyId' => 'a']);
        $this->assertStringNotContainsString('<th>Sistema</th>', $open, 'Open count is blind.');
        $this->assertStringContainsString('data-codex-pagination="5"', $open);

        $service->close('a', $count['id']);
        $service->approve('a', $count['id'], [$item['id'] => ['approved' => true, 'reason' => 'Rotura']], 'u');
        $approved = view('inventory/control/items', ['count' => $service->get('a', $count['id']), 'companyId' => 'a']);
        $this->assertStringContainsString('<th>Sistema</th>', $approved);
        $this->assertStringContainsString('Ajustado', $approved);
        $this->assertStringContainsString('Rotura', $approved);
    }

    public function testControlScreenRendersEveryStage(): void
    {
        helper('auth');
        $service = new StockControlService();
        $count = $service->start('a', 'w', 'u');
        $item = $count['items'][0];
        $render = fn() => view('inventory/control/show', ['count' => $service->get('a', $count['id']), 'companyId' => 'a', 'threshold' => 10.0,
            'products' => [], 'csrfHeader' => 'X-CSRF-TOKEN', 'company' => ['name' => 'A']]);
        $service->record('a', $count['id'], $item['product_id'], 4);
        $open = $render();
        $this->assertStringContainsString('¿Qué hay en el depósito?', $open);
        $this->assertStringNotContainsString('<th>Sistema</th>', $open, 'Counting stays blind.');

        $service->close('a', $count['id']);
        $review = $render();
        $this->assertStringContainsString('¿Qué diferencias aprobamos?', $review);
        $this->assertStringContainsString('bulk-reason', $review);

        $service->approve('a', $count['id'], [$item['id'] => ['approved' => true, 'reason' => 'Rotura']], 'u');
        $approved = $render();
        $this->assertStringContainsString('Ajustado', $approved);
        $this->assertStringNotContainsString('bulk-reason', $approved, 'Approved controls are read-only.');
    }

    public function testReportViewPaginatesEveryList(): void
    {
        $this->move('p1', 'ingreso', 5, 'Ajuste manual de stock');
        $html = view('inventory/control/_report_panels', ['report' => (new StockControlService())->report('a', 7), 'companyId' => 'a']);
        $this->assertStringContainsString('¿Qué entró sin comprobante?', $html);
        $this->assertSame(substr_count($html, '<table'), substr_count($html, 'data-codex-pagination="5"'));
        $this->assertStringContainsString('insight-empty', $html, 'Empty sections show the empty state.');
    }
}
