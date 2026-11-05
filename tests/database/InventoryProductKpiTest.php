<?php
use App\Libraries\InventoryProductKpi;
use CodeIgniter\Test\CIUnitTestCase;

final class InventoryProductKpiTest extends CIUnitTestCase
{
    private array $tables = [];
    private array $product = ['id' => 'p1', 'sku' => 'P1', 'name' => 'Producto', 'min_stock' => 3, 'max_stock' => 50, 'cost_price' => 10];

    protected function setUp(): void
    {
        parent::setUp();
        $db = db_connect();
        foreach ([
            'inventory_warehouses' => 'id TEXT, company_id TEXT, name TEXT, code TEXT',
            'inventory_stock_levels' => 'company_id TEXT, product_id TEXT, warehouse_id TEXT, quantity REAL, reserved_quantity REAL',
            'inventory_cost_layers' => 'company_id TEXT, product_id TEXT, warehouse_id TEXT, remaining_quantity REAL, unit_cost REAL',
            'inventory_movements' => 'company_id TEXT, product_id TEXT, movement_type TEXT, adjustment_mode TEXT, quantity REAL, total_cost REAL, source_warehouse_id TEXT, destination_warehouse_id TEXT, occurred_at TEXT',
        ] as $t => $schema) {
            $db->query('CREATE TABLE ' . $db->prefixTable($t) . ' (' . $schema . ')');
            $this->tables[] = $t;
        }
        $db->table('inventory_warehouses')->insertBatch([
            ['id' => 'w1', 'company_id' => 'a', 'name' => 'Central', 'code' => 'C'],
            ['id' => 'w2', 'company_id' => 'a', 'name' => 'Sucursal', 'code' => 'S'],
            ['id' => 'wx', 'company_id' => 'b', 'name' => 'Ajeno', 'code' => 'X'],
        ]);
        $db->table('inventory_stock_levels')->insertBatch([
            ['company_id' => 'a', 'product_id' => 'p1', 'warehouse_id' => 'w1', 'quantity' => 8, 'reserved_quantity' => 2],
            ['company_id' => 'a', 'product_id' => 'p1', 'warehouse_id' => 'w2', 'quantity' => 4, 'reserved_quantity' => 0],
            ['company_id' => 'b', 'product_id' => 'p1', 'warehouse_id' => 'wx', 'quantity' => 999, 'reserved_quantity' => 0],
        ]);
        $db->table('inventory_cost_layers')->insertBatch([
            ['company_id' => 'a', 'product_id' => 'p1', 'warehouse_id' => 'w1', 'remaining_quantity' => 8, 'unit_cost' => 12],
            ['company_id' => 'a', 'product_id' => 'p1', 'warehouse_id' => 'w1', 'remaining_quantity' => 0, 'unit_cost' => 99],
        ]);
        foreach ([
            ['a', 'ingreso', null, 100, null, null, 'w1', '2026-09-01 10:00:00'],
            ['a', 'ingreso', null, 10, null, null, 'w1', '2026-10-02 10:00:00'],
            ['a', 'egreso', null, 3, 36, 'w1', null, '2026-10-05 10:00:00'],
            ['a', 'transferencia', null, 4, 48, 'w1', 'w2', '2026-10-06 10:00:00'],
            ['a', 'ajuste', 'decrease', 1, 12, 'w1', null, '2026-10-10 23:59:59'],
            ['a', 'ingreso', null, 2, null, null, 'w1', '2026-10-15 10:00:00'],
            ['b', 'egreso', null, 50, 500, 'wx', null, '2026-10-05 10:00:00'],
        ] as [$company, $type, $mode, $qty, $cost, $src, $dst, $at]) {
            $db->table('inventory_movements')->insert(['company_id' => $company, 'product_id' => 'p1', 'movement_type' => $type, 'adjustment_mode' => $mode, 'quantity' => $qty, 'total_cost' => $cost, 'source_warehouse_id' => $src, 'destination_warehouse_id' => $dst, 'occurred_at' => $at]);
        }
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->tables) as $t) {
            db_connect()->query('DROP TABLE ' . db_connect()->prefixTable($t));
        }
        parent::tearDown();
    }

    public function testTotalsFlowAndIndicatorsAreScopedToCompanyAndPeriod(): void
    {
        $k = (new InventoryProductKpi())->build('a', $this->product, '2026-10-01', '2026-10-10');

        $this->assertSame(['from' => '2026-10-01', 'to' => '2026-10-10', 'days' => 10], $k['period']);
        $this->assertSame(12.0, $k['stock']['quantity']);
        $this->assertSame(2.0, $k['stock']['reserved']);
        $this->assertSame(10.0, $k['stock']['available']);
        $this->assertSame(2, $k['stock']['warehouses_with_stock']);
        // w1 from open layers (8 x 12), w2 falls back to cost_price (4 x 10).
        $this->assertSame(136.0, $k['valuation']['value']);
        $this->assertEqualsWithDelta(136 / 12, $k['valuation']['average_cost'], 1e-9);

        // Transfers do not count as product entries/exits.
        $this->assertSame(10.0, $k['flow']['in']);
        $this->assertSame(4.0, $k['flow']['out']);
        $this->assertSame(4.0, $k['flow']['transfer']);
        $this->assertSame(6.0, $k['flow']['net']);
        $this->assertSame(4, $k['flow']['movements']);
        $this->assertSame(48.0, $k['flow']['out_cost']);
        // Closing excludes the +2 entry after the period; opening = closing - net.
        $this->assertSame(10.0, $k['flow']['closing']);
        $this->assertSame(4.0, $k['flow']['opening']);

        $this->assertEqualsWithDelta(4 / 7, $k['indicators']['rotation'], 1e-9);
        $this->assertEqualsWithDelta(0.4, $k['indicators']['daily_out'], 1e-9);
        $this->assertEqualsWithDelta(25.0, $k['indicators']['coverage_days'], 1e-9);
        $this->assertEqualsWithDelta(2 / 12 * 100, $k['indicators']['reserved_pct'], 1e-9);
        $this->assertSame('Normal', $k['indicators']['status']);
        $this->assertSame('2026-10-15 10:00:00', $k['indicators']['last_movement_at']);

        $byId = array_column($k['warehouses'], null, 'warehouse_id');
        $this->assertSame(['w1', 'w2'], array_keys($byId));
        $this->assertSame([10.0, 8.0], [$byId['w1']['in'], $byId['w1']['out']]);
        $this->assertSame([4.0, 0.0], [$byId['w2']['in'], $byId['w2']['out']]);
        $this->assertSame(136.0, $k['totals']['value']);
    }

    public function testProductWithoutDataReturnsNullRatios(): void
    {
        $k = (new InventoryProductKpi())->build('a', ['id' => 'none', 'min_stock' => 0, 'max_stock' => 0, 'cost_price' => 5], '2026-10-01', '2026-10-10');

        $this->assertSame([], $k['warehouses']);
        $this->assertSame(0.0, $k['stock']['quantity']);
        $this->assertNull($k['valuation']['average_cost']);
        $this->assertNull($k['indicators']['rotation']);
        $this->assertNull($k['indicators']['coverage_days']);
        $this->assertNull($k['indicators']['reserved_pct']);
        $this->assertNull($k['indicators']['days_since_last_movement']);
        $this->assertSame('Sin stock', $k['indicators']['status']);
        $this->assertSame('-', InventoryProductKpi::format(null, 'ratio'));
    }

    public function testPeriodValidation(): void
    {
        $this->assertSame(['2026-10-01', '2026-10-10'], InventoryProductKpi::period('2026-10-10', '2026-10-01'));
        $this->assertSame([date('Y-m-d', strtotime('-29 days')), date('Y-m-d')], InventoryProductKpi::period('basura', '2026-02-30'));
    }

    public function testStatusThresholds(): void
    {
        $kpi = new InventoryProductKpi();
        $this->assertSame('Bajo minimo', $kpi->build('a', ['min_stock' => 12] + $this->product, '2026-10-01', '2026-10-10')['indicators']['status']);
        $this->assertSame('Sobrestock', $kpi->build('a', ['max_stock' => 11] + $this->product, '2026-10-01', '2026-10-10')['indicators']['status']);
    }
}
