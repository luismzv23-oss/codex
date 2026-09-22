<?php

use App\Controllers\Api\V1\InventoryController;
use App\Libraries\AccountingService;
use App\Libraries\InventoryIntegrityService;
use App\Models\InventoryMovementModel;
use App\Models\InventoryPeriodClosureModel;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

final class InventoryIntegrityTest extends CIUnitTestCase
{
    private array $tables = [];

    protected function setUp(): void
    {
        parent::setUp();
        helper(['app', 'url']);
        $this->db = db_connect('tests');
        $schemas = [
            'companies' => 'id TEXT PRIMARY KEY',
            'inventory_products' => 'id TEXT PRIMARY KEY, company_id TEXT, active INTEGER, lot_control INTEGER DEFAULT 0, serial_control INTEGER DEFAULT 0, min_stock REAL, cost_price REAL',
            'inventory_warehouses' => 'id TEXT PRIMARY KEY, company_id TEXT, active INTEGER',
            'inventory_locations' => 'id TEXT PRIMARY KEY, company_id TEXT, warehouse_id TEXT, active INTEGER',
            'inventory_settings' => 'id TEXT PRIMARY KEY, company_id TEXT, allow_negative_stock INTEGER, valuation_method TEXT',
            'inventory_stock_levels' => 'id TEXT PRIMARY KEY, company_id TEXT, product_id TEXT, warehouse_id TEXT, location_id TEXT, quantity REAL, reserved_quantity REAL, min_stock REAL, created_at TEXT, updated_at TEXT',
            'inventory_reservations' => 'id TEXT PRIMARY KEY, company_id TEXT, product_id TEXT, warehouse_id TEXT, quantity REAL, reference TEXT, notes TEXT, status TEXT, reserved_by TEXT, reserved_at TEXT, released_by TEXT, released_at TEXT, created_at TEXT, updated_at TEXT',
            'inventory_period_closures' => 'id TEXT PRIMARY KEY, company_id TEXT, warehouse_id TEXT, status TEXT, start_date TEXT, end_date TEXT',
            'inventory_movements' => 'id TEXT PRIMARY KEY, company_id TEXT, product_id TEXT, movement_type TEXT, quantity REAL, unit_cost REAL, total_cost REAL, adjustment_mode TEXT, source_warehouse_id TEXT, source_location_id TEXT, destination_warehouse_id TEXT, destination_location_id TEXT, performed_by TEXT, occurred_at TEXT, reason TEXT, source_document TEXT, lot_number TEXT, serial_number TEXT, expiration_date TEXT, notes TEXT, created_at TEXT, updated_at TEXT',
            'inventory_lots' => 'id TEXT PRIMARY KEY, company_id TEXT, product_id TEXT, warehouse_id TEXT, location_id TEXT, lot_number TEXT, expiration_date TEXT, quantity_balance REAL, status TEXT, created_at TEXT, updated_at TEXT',
            'inventory_serials' => 'id TEXT PRIMARY KEY, company_id TEXT, product_id TEXT, warehouse_id TEXT, location_id TEXT, serial_number TEXT, lot_number TEXT, expiration_date TEXT, status TEXT, last_movement_id TEXT, created_at TEXT, updated_at TEXT',
            'inventory_cost_layers' => 'id TEXT PRIMARY KEY, company_id TEXT, product_id TEXT, warehouse_id TEXT, location_id TEXT, movement_id TEXT, layer_type TEXT, quantity REAL, remaining_quantity REAL, unit_cost REAL, total_cost REAL, occurred_at TEXT, created_at TEXT, updated_at TEXT',
            'inventory_revaluations' => 'id TEXT PRIMARY KEY, company_id TEXT, product_id TEXT, warehouse_id TEXT, previous_unit_cost REAL, new_unit_cost REAL, quantity_snapshot REAL, difference_amount REAL, issued_at TEXT, notes TEXT, created_by TEXT, created_at TEXT, updated_at TEXT',
        ];
        foreach ($schemas as $table => $columns) {
            $this->db->query('CREATE TABLE ' . $this->db->prefixTable($table) . ' (' . $columns . ')');
            $this->tables[] = $table;
        }
        $this->db->query('CREATE UNIQUE INDEX test_inventory_location ON ' . $this->db->prefixTable('inventory_stock_levels') . " (company_id, product_id, warehouse_id, COALESCE(location_id, ''))");
        $this->db->table('companies')->insert(['id' => 'a']);
        $this->db->table('inventory_products')->insert(['id' => 'p', 'company_id' => 'a', 'active' => 1, 'cost_price' => 10]);
        foreach (['w' => 'a', 'w2' => 'a', 'foreign' => 'b'] as $id => $company) {
            $this->db->table('inventory_warehouses')->insert(['id' => $id, 'company_id' => $company, 'active' => 1]);
        }
        foreach (['l1' => 'w', 'l2' => 'w', 'l3' => 'w2'] as $id => $warehouse) {
            $this->db->table('inventory_locations')->insert(['id' => $id, 'company_id' => 'a', 'warehouse_id' => $warehouse, 'active' => 1]);
        }
        $this->db->table('inventory_settings')->insert(['id' => 's', 'company_id' => 'a', 'allow_negative_stock' => 0, 'valuation_method' => 'fifo']);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            $this->db->query('DROP TABLE ' . $this->db->prefixTable($table));
        }
        parent::tearDown();
    }

    private function api(array $input): InventoryController
    {
        $controller = new class extends InventoryController {
            public array $input;
            protected function inventoryContext(string $requiredAccess): array { return ['company' => ['id' => 'a']]; }
            protected function apiUser(): ?array { return ['id' => 'operator', 'company_id' => 'a']; }
            protected function payload(): array { return $this->input; }
        };
        $controller->input = $input;
        $controller->initController(service('request'), service('response'), service('logger'));
        return $controller;
    }

    private function movement(array $override = []): array
    {
        return array_merge(['product_id' => 'p', 'movement_type' => 'ingreso', 'quantity' => 5,
            'destination_warehouse_id' => 'w', 'destination_location_id' => 'l1', 'unit_cost' => 10, 'occurred_at' => '2026-09-21 12:00:00'], $override);
    }

    private function enter(array $override = []): void
    {
        $response = $this->api($this->movement($override))->storeMovement();
        $this->assertSame(201, $response->getStatusCode(), $response->getBody());
    }

    public function testLocationsKeepIndependentBalancesAndWarehouseTotal(): void
    {
        $this->enter();
        $this->enter(['destination_location_id' => 'l2', 'quantity' => 3]);
        $rows = $this->db->table('inventory_stock_levels')->orderBy('location_id')->get()->getResultArray();
        $this->assertCount(2, $rows);
        $this->assertEquals(5, $rows[0]['quantity']);
        $this->assertEquals(3, $rows[1]['quantity']);
        $integrity = new InventoryIntegrityService();
        $integrity->transaction('a', function () use ($integrity) {
            $this->assertSame(8.0, $integrity->available('a', 'p', 'w'));
            $integrity->changeStock('a', 'p', 'w', -6);
            $this->assertSame(2.0, $integrity->quantity('a', 'p', 'w'));
        });
    }

    public function testApiRejectsForeignWarehouseAndMismatchedLocation(): void
    {
        foreach ([['destination_warehouse_id' => 'foreign', 'destination_location_id' => null], ['destination_location_id' => 'l3']] as $override) {
            $response = $this->api($this->movement($override))->storeMovement();
            $this->assertSame(422, $response->getStatusCode());
        }
        $this->assertSame(0, $this->db->table('inventory_stock_levels')->countAllResults());
        $this->assertSame(0, $this->db->table('inventory_movements')->countAllResults());
    }

    public function testReservationCannotExceedBalanceAndReleaseIsIdempotent(): void
    {
        $this->enter(['quantity' => 10]);
        $input = ['product_id' => 'p', 'warehouse_id' => 'w', 'quantity' => 6];
        $response = $this->api($input)->storeReservation();
        $this->assertSame(201, $response->getStatusCode(), $response->getBody());
        $id = json_decode($response->getBody(), true)['data']['id'];
        $this->assertSame(422, $this->api($input)->storeReservation()->getStatusCode());
        $this->assertSame(201, $this->api(array_merge($input, ['quantity' => 2]))->storeReservation()->getStatusCode());
        $this->assertSame(200, $this->api([])->releaseReservation($id)->getStatusCode());
        $this->assertSame(404, $this->api([])->releaseReservation($id)->getStatusCode());
        $this->assertEquals(2, $this->db->table('inventory_stock_levels')->selectSum('reserved_quantity', 'qty')->get()->getRowArray()['qty']);
    }

    public function testLotOverdrawRollsBackStockAndMovement(): void
    {
        $this->enter(['lot_number' => 'lot']);
        $this->enter(['quantity' => 10]);
        $response = $this->api($this->movement(['movement_type' => 'egreso', 'quantity' => 6,
            'source_warehouse_id' => 'w', 'source_location_id' => 'l1', 'lot_number' => 'lot']))->storeMovement();
        $this->assertSame(422, $response->getStatusCode());
        $this->assertEquals(15, $this->db->table('inventory_stock_levels')->get()->getRowArray()['quantity']);
        $this->assertEquals(5, $this->db->table('inventory_lots')->get()->getRowArray()['quantity_balance']);
        $this->assertSame(2, $this->db->table('inventory_movements')->countAllResults());
    }

    public function testSerialCannotBeDuplicatedConsumedTwiceOrMovedFromWrongOrigin(): void
    {
        $this->enter(['quantity' => 1, 'serial_number' => 'SN']);
        $this->assertSame(422, $this->api($this->movement(['quantity' => 1, 'serial_number' => 'SN']))->storeMovement()->getStatusCode());
        $out = $this->movement(['movement_type' => 'egreso', 'quantity' => 1, 'serial_number' => 'SN', 'source_warehouse_id' => 'w', 'source_location_id' => 'l2']);
        $this->assertSame(422, $this->api($out)->storeMovement()->getStatusCode());
        $out['source_location_id'] = 'l1';
        $this->assertSame(201, $this->api($out)->storeMovement()->getStatusCode());
        $this->assertSame(422, $this->api($out)->storeMovement()->getStatusCode());
        $this->assertSame('consumed', $this->db->table('inventory_serials')->get()->getRowArray()['status']);
    }

    public function testWarehouseClosureDoesNotBlockAnotherWarehouseAndIncludesLastDay(): void
    {
        $this->db->table('inventory_period_closures')->insert(['id' => 'closed', 'company_id' => 'a', 'warehouse_id' => 'w2', 'start_date' => '2026-09-01', 'end_date' => '2026-09-21', 'status' => 'closed']);
        $this->enter();
        $response = $this->api($this->movement(['destination_warehouse_id' => 'w2', 'destination_location_id' => 'l3']))->storeMovement();
        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse(InventoryPeriodClosureModel::isPeriodClosed('a', '2026-09-21 23:59:59', 'w'));
        $this->assertTrue(InventoryPeriodClosureModel::isPeriodClosed('a', '2026-09-21 23:59:59', 'w2'));
        $this->db->table('inventory_period_closures')->where('id', 'closed')->update(['warehouse_id' => null]);
        $this->assertTrue(InventoryPeriodClosureModel::isPeriodClosed('a', '2026-09-21 23:59:59', 'w'));
        $movement = $this->db->table('inventory_movements')->get()->getRowArray();
        $this->expectException(RuntimeException::class);
        (new InventoryMovementModel())->update($movement['id'], ['unit_cost' => 999]);
    }

    public function testRevaluationRollsBackOnAccountingFailure(): void
    {
        $this->enter();
        $accounting = $this->createMock(AccountingService::class);
        $accounting->expects($this->once())->method('syncRevaluation')->willThrowException(new RuntimeException('Fallo contable simulado'));
        Services::injectMock('accounting', $accounting);
        $response = $this->api(['product_id' => 'p', 'warehouse_id' => 'w', 'new_unit_cost' => 20])->storeRevaluation();
        $this->assertSame(422, $response->getStatusCode(), $response->getBody());
        $this->assertSame(0, $this->db->table('inventory_revaluations')->countAllResults());
        $this->assertEquals(10, $this->db->table('inventory_cost_layers')->get()->getRowArray()['unit_cost']);
    }

    public function testRevaluationAggregatesLocationsAndCallsAccounting(): void
    {
        $this->enter();
        $this->enter(['destination_location_id' => 'l2', 'quantity' => 3]);
        $accounting = $this->createMock(AccountingService::class);
        $accounting->expects($this->once())->method('syncRevaluation')->willReturn(['ok' => true]);
        Services::injectMock('accounting', $accounting);
        $response = $this->api(['product_id' => 'p', 'warehouse_id' => 'w', 'new_unit_cost' => 20])->storeRevaluation();
        $this->assertSame(201, $response->getStatusCode(), $response->getBody());
        $row = $this->db->table('inventory_revaluations')->get()->getRowArray();
        $this->assertEquals(8, $row['quantity_snapshot']);
        $this->assertEquals(80, $row['difference_amount']);
        foreach ($this->db->table('inventory_cost_layers')->get()->getResultArray() as $layer) {
            $this->assertEquals(20, $layer['unit_cost']);
        }
    }

    public function testClosedRevaluationDoesNotCallAccounting(): void
    {
        $this->enter();
        $this->db->table('inventory_period_closures')->insert(['id' => 'closed', 'company_id' => 'a', 'warehouse_id' => 'w', 'start_date' => '2026-09-01', 'end_date' => '2026-09-21', 'status' => 'closed']);
        $accounting = $this->createMock(AccountingService::class);
        $accounting->expects($this->never())->method('syncRevaluation');
        Services::injectMock('accounting', $accounting);
        $response = $this->api(['product_id' => 'p', 'warehouse_id' => 'w', 'new_unit_cost' => 20, 'issued_at' => '2026-09-21 23:00:00'])->storeRevaluation();
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, $this->db->table('inventory_revaluations')->countAllResults());
    }

    public function testTransferMovesLotSerialAndCostTogether(): void
    {
        $this->enter(['quantity' => 1, 'lot_number' => 'lot', 'serial_number' => 'SN']);
        $response = $this->api($this->movement(['movement_type' => 'transferencia', 'quantity' => 1,
            'source_warehouse_id' => 'w', 'source_location_id' => 'l1',
            'destination_warehouse_id' => 'w2', 'destination_location_id' => 'l3',
            'lot_number' => 'lot', 'serial_number' => 'SN']))->storeMovement();
        $this->assertSame(201, $response->getStatusCode(), $response->getBody());
        $this->assertEquals(0, $this->db->table('inventory_stock_levels')->where('warehouse_id', 'w')->get()->getRowArray()['quantity']);
        $this->assertEquals(1, $this->db->table('inventory_stock_levels')->where('warehouse_id', 'w2')->get()->getRowArray()['quantity']);
        $this->assertEquals(1, $this->db->table('inventory_lots')->where('warehouse_id', 'w2')->get()->getRowArray()['quantity_balance']);
        $this->assertSame('w2', $this->db->table('inventory_serials')->get()->getRowArray()['warehouse_id']);
    }
}
