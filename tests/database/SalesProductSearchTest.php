<?php

use App\Controllers\SalesController;
use CodeIgniter\Test\CIUnitTestCase;

final class SalesProductSearchTest extends CIUnitTestCase
{
    private array $tables = [];

    protected function setUp(): void
    {
        parent::setUp();
        $db = db_connect('tests');
        foreach ([
            'sales_promotions' => 'id TEXT PRIMARY KEY, company_id TEXT, name TEXT, promotion_type TEXT, scope TEXT, value REAL, active INTEGER, start_date TEXT, end_date TEXT',
            'sales_promotion_items' => 'promotion_id TEXT, product_id TEXT',
            'inventory_products' => 'id TEXT PRIMARY KEY, company_id TEXT, sku TEXT, name TEXT, brand TEXT, active INTEGER, sale_price REAL, product_type TEXT',
            'inventory_kit_items' => 'product_id TEXT, component_product_id TEXT, quantity REAL',
            'inventory_warehouses' => 'id TEXT PRIMARY KEY, company_id TEXT, active INTEGER',
            'inventory_stock_levels' => 'id TEXT PRIMARY KEY, company_id TEXT, product_id TEXT, warehouse_id TEXT, quantity REAL, reserved_quantity REAL',
        ] as $table => $schema) {
            $db->query('CREATE TABLE ' . $db->prefixTable($table) . ' (' . $schema . ')');
            $this->tables[] = $table;
        }
        foreach ([['a', 1], ['b', 1], ['inactive', 0]] as [$id, $active]) {
            $db->table('inventory_products')->insert(['id' => $id, 'company_id' => $id === 'b' ? 'b' : 'a', 'sku' => $id, 'name' => 'Producto', 'brand' => 'Marca', 'active' => $active, 'sale_price' => 100]);
        }
        $db->table('inventory_warehouses')->insert(['id' => 'warehouse', 'company_id' => 'a', 'active' => 1]);
        $db->table('inventory_stock_levels')->insert(['id' => 'stock', 'company_id' => 'a', 'product_id' => 'a', 'warehouse_id' => 'warehouse', 'quantity' => 10, 'reserved_quantity' => 2]);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            db_connect('tests')->query('DROP TABLE ' . db_connect('tests')->prefixTable($table));
        }
        parent::tearDown();
    }

    public function testRepeatedSearchReadsCurrentPriceAndAvailableStock(): void
    {
        $controller = new class extends SalesController {
            protected function salesContext(string $requiredAccess = 'view')
            {
                return ['company' => ['id' => 'a']];
            }
        };
        $request = service('request');
        $request->setGlobal('get', ['q' => 'Producto']);
        $controller->initController($request, service('response'), service('logger'));
        $response = $controller->productSearch();
        $products = json_decode($response->getBody(), true)['products'];
        $this->assertCount(1, $products);
        $this->assertSame('a', $products[0]['id']);
        $this->assertEquals(8, $products[0]['stocks']['warehouse']['available']);
        $this->assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));

        $db = db_connect('tests');
        $db->table('inventory_products')->where('id', 'a')->update(['sale_price' => 125]);
        $db->table('inventory_stock_levels')->where('id', 'stock')->update(['quantity' => 6]);
        $products = json_decode($controller->productSearch()->getBody(), true)['products'];
        $this->assertEquals(125, $products[0]['sale_price']);
        $this->assertEquals(4, $products[0]['stocks']['warehouse']['available']);

        $request->setGlobal('get', ['q' => 'No existe']);
        $this->assertSame([], json_decode($controller->productSearch()->getBody(), true)['products']);
    }

    public function testPackOnlySearchLoadsBaseStockWithinCompany(): void
    {
        $db = db_connect('tests');
        $db->table('inventory_products')->insert(['id'=>'pack','company_id'=>'a','sku'=>'pack','name'=>'Cajon','active'=>1,'sale_price'=>500,'product_type'=>'kit']);
        $db->table('inventory_kit_items')->insert(['product_id'=>'pack','component_product_id'=>'a','quantity'=>4]);
        // A foreign tenant balance must never contribute to availability.
        $db->table('inventory_stock_levels')->insert(['id'=>'foreign','company_id'=>'b','product_id'=>'a','warehouse_id'=>'warehouse','quantity'=>100,'reserved_quantity'=>0]);
        $product = $db->table('inventory_products')->where('id','pack')->get()->getRowArray();
        $catalog = (new App\Libraries\SalesPackAvailability())->catalog('a', [$product]);
        $this->assertSame(2.0, $catalog[0]['stocks']['warehouse']['available']);
        $this->assertSame(['a'=>4.0], $catalog[0]['stock_requirements']);
    }
}
