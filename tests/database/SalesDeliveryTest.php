<?php

use App\Controllers\SalesController;
use CodeIgniter\Test\CIUnitTestCase;

final class SalesDeliveryTest extends CIUnitTestCase
{
    private array $tables = [];

    protected function setUp(): void
    {
        parent::setUp();
        helper(['app', 'url']);
        $this->db = db_connect('tests');
        $schemas = [
            'sales_quotes' => 'id TEXT PRIMARY KEY, company_id TEXT, status TEXT, approved_by TEXT, approved_at TEXT',
            'sales_orders' => 'id TEXT PRIMARY KEY, company_id TEXT, status TEXT, approved_by TEXT, approved_at TEXT',
            'sales_delivery_notes' => 'id TEXT PRIMARY KEY, company_id TEXT, status TEXT, warehouse_id TEXT, delivery_number TEXT, dispatched_at TEXT, delivered_at TEXT, updated_at TEXT',
            'sales_delivery_note_items' => 'id TEXT, sales_delivery_note_id TEXT, product_id TEXT, quantity REAL, warehouse_id TEXT',
            'inventory_products' => 'id TEXT PRIMARY KEY, company_id TEXT, product_type TEXT, min_stock REAL',
            'inventory_warehouses' => 'id TEXT PRIMARY KEY, company_id TEXT',
            'inventory_settings' => 'id TEXT, company_id TEXT, allow_negative_stock INTEGER',
            'inventory_stock_levels' => 'id TEXT PRIMARY KEY, company_id TEXT, product_id TEXT, warehouse_id TEXT, quantity REAL, reserved_quantity REAL, updated_at TEXT',
            'inventory_period_closures' => 'id TEXT, company_id TEXT, warehouse_id TEXT, status TEXT, start_date TEXT, end_date TEXT',
            'inventory_movements' => 'id TEXT PRIMARY KEY, company_id TEXT, product_id TEXT, movement_type TEXT, quantity REAL, unit_cost REAL, total_cost REAL, adjustment_mode TEXT, source_warehouse_id TEXT, destination_warehouse_id TEXT, performed_by TEXT, occurred_at TEXT, reason TEXT, source_document TEXT, lot_number TEXT, serial_number TEXT, expiration_date TEXT, notes TEXT, created_at TEXT, updated_at TEXT',
        ];
        foreach ($schemas as $table => $columns) {
            $this->db->query('CREATE TABLE ' . $this->db->prefixTable($table) . ' (' . $columns . ')');
            $this->tables[] = $table;
        }
        $this->db->table('sales_delivery_notes')->insert(['id' => 'note', 'company_id' => 'a', 'status' => 'pending', 'warehouse_id' => 'warehouse', 'delivery_number' => 'RM-1']);
        $this->db->table('sales_delivery_note_items')->insert(['id' => 'line', 'sales_delivery_note_id' => 'note', 'product_id' => 'product', 'quantity' => 3, 'warehouse_id' => 'warehouse']);
        $this->db->table('inventory_products')->insert(['id' => 'product', 'company_id' => 'a', 'product_type' => 'simple']);
        $this->db->table('inventory_warehouses')->insert(['id' => 'warehouse', 'company_id' => 'a']);
        $this->db->table('inventory_settings')->insert(['id' => 'settings', 'company_id' => 'a', 'allow_negative_stock' => 0]);
        $this->db->table('inventory_stock_levels')->insert(['id' => 'stock', 'company_id' => 'a', 'product_id' => 'product', 'warehouse_id' => 'warehouse', 'quantity' => 10, 'reserved_quantity' => 0]);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            $this->db->query('DROP TABLE ' . $this->db->prefixTable($table));
        }
        parent::tearDown();
    }

    private function controller(): SalesController
    {
        $controller = new class extends SalesController {
            protected function salesContext(string $requiredAccess = 'view')
            {
                return ['company' => ['id' => 'a']];
            }

            protected function currentUser(): ?array
            {
                return ['id' => 'operator', 'company_id' => 'a'];
            }
        };
        $controller->initController(service('request'), service('response'), service('logger'));
        return $controller;
    }

    public function testRealDispatchRecordsMovementAndCannotRunTwice(): void
    {
        $controller = $this->controller();
        $controller->dispatchDeliveryNote('note');
        $this->assertSame('dispatched', $this->db->table('sales_delivery_notes')->get()->getRowArray()['status']);
        $this->assertEquals(7, $this->db->table('inventory_stock_levels')->get()->getRowArray()['quantity']);
        $movement = $this->db->table('inventory_movements')->get()->getRowArray();
        $this->assertSame('operator', $movement['performed_by']);
        $this->assertSame('warehouse', $movement['source_warehouse_id']);
        $this->assertSame('egreso', $movement['movement_type']);
        $controller->dispatchDeliveryNote('note');
        $this->assertEquals(7, $this->db->table('inventory_stock_levels')->get()->getRowArray()['quantity']);
        $this->assertSame(1, $this->db->table('inventory_movements')->countAllResults());
        $controller->deliverDeliveryNote('note');
        $this->assertSame('delivered', $this->db->table('sales_delivery_notes')->get()->getRowArray()['status']);
    }

    public function testClosedPeriodRollsBackRealDispatch(): void
    {
        $this->db->table('inventory_period_closures')->insert(['id' => 'closed', 'company_id' => 'a', 'warehouse_id' => 'warehouse', 'status' => 'closed', 'start_date' => '2000-01-01', 'end_date' => '2099-12-31']);
        $this->controller()->dispatchDeliveryNote('note');
        $this->assertSame('pending', $this->db->table('sales_delivery_notes')->get()->getRowArray()['status']);
        $this->assertEquals(10, $this->db->table('inventory_stock_levels')->get()->getRowArray()['quantity']);
        $this->assertSame(0, $this->db->table('inventory_movements')->countAllResults());
    }

    public function testPendingAndForeignRemitosAreNotDelivered(): void
    {
        $controller = $this->controller();
        $controller->deliverDeliveryNote('note');
        $this->assertSame('pending', $this->db->table('sales_delivery_notes')->get()->getRowArray()['status']);
        $this->db->table('sales_delivery_notes')->where('id', 'note')->update(['company_id' => 'b', 'status' => 'dispatched']);
        $controller->deliverDeliveryNote('note');
        $this->assertSame('dispatched', $this->db->table('sales_delivery_notes')->get()->getRowArray()['status']);
    }

    public function testApprovalsCannotModifyAnotherCompanyDocuments(): void
    {
        $this->db->table('sales_quotes')->insert(['id' => 'quote', 'company_id' => 'b', 'status' => 'draft']);
        $this->db->table('sales_orders')->insert(['id' => 'order', 'company_id' => 'b', 'status' => 'pending']);
        $controller = $this->controller();
        $controller->approveQuote('quote');
        $controller->approveOrder('order');
        $this->assertSame('draft', $this->db->table('sales_quotes')->get()->getRowArray()['status']);
        $this->assertSame('pending', $this->db->table('sales_orders')->get()->getRowArray()['status']);
        $this->db->table('sales_quotes')->where('id', 'quote')->update(['company_id' => 'a']);
        $this->db->table('sales_orders')->where('id', 'order')->update(['company_id' => 'a']);
        $controller->approveQuote('quote');
        $controller->approveOrder('order');
        $this->assertSame('approved', $this->db->table('sales_quotes')->get()->getRowArray()['status']);
        $this->assertSame('approved', $this->db->table('sales_orders')->get()->getRowArray()['status']);
    }

    public function testInsufficientStockRejectsDispatchWithoutMovements(): void
    {
        $this->db->table('inventory_stock_levels')->update(['quantity' => 2]);
        $this->controller()->dispatchDeliveryNote('note');
        $this->assertSame('pending', $this->db->table('sales_delivery_notes')->get()->getRowArray()['status']);
        $this->assertEquals(2, $this->db->table('inventory_stock_levels')->get()->getRowArray()['quantity']);
        $this->assertSame(0, $this->db->table('inventory_movements')->countAllResults());
    }
}
