<?php

use App\Controllers\InventoryController;
use CodeIgniter\Test\CIUnitTestCase;

final class InventoryProductFormTest extends CIUnitTestCase
{
    private array $tables = [];

    protected function setUp(): void
    {
        parent::setUp();
        helper(['app', 'url', 'form', 'auth']);
        foreach ([
            'inventory_products' => 'id TEXT PRIMARY KEY, company_id TEXT, sku TEXT, name TEXT, category TEXT, brand TEXT, barcode TEXT, product_type TEXT, unit TEXT, min_stock REAL, max_stock REAL, cost_price REAL, sale_price REAL, description TEXT, lot_control INTEGER, serial_control INTEGER, expiration_control INTEGER, active INTEGER, image TEXT, created_at TEXT, updated_at TEXT',
            'inventory_stock_levels' => 'id TEXT PRIMARY KEY, company_id TEXT, product_id TEXT, warehouse_id TEXT, quantity REAL, reserved_quantity REAL, min_stock REAL, created_at TEXT, updated_at TEXT',
            'inventory_kit_items' => 'id TEXT PRIMARY KEY, product_id TEXT, component_product_id TEXT, quantity REAL, created_at TEXT, updated_at TEXT',
            'inventory_warehouses' => 'id TEXT PRIMARY KEY, company_id TEXT, code TEXT, name TEXT, active INTEGER, is_default INTEGER',
            'inventory_movements' => 'id TEXT PRIMARY KEY, company_id TEXT, product_id TEXT, movement_type TEXT, quantity REAL, unit_cost REAL, total_cost REAL, source_warehouse_id TEXT, destination_warehouse_id TEXT, performed_by TEXT, occurred_at TEXT, reason TEXT, notes TEXT, created_at TEXT, updated_at TEXT',
            'inventory_reservations' => 'id TEXT PRIMARY KEY, company_id TEXT, product_id TEXT, warehouse_id TEXT, status TEXT, reserved_by TEXT, reserved_at TEXT',
            'users' => 'id TEXT PRIMARY KEY, name TEXT',
        ] as $table => $schema) {
            db_connect()->query('CREATE TABLE ' . db_connect()->prefixTable($table) . ' (' . $schema . ')');
            $this->tables[] = $table;
        }
        db_connect()->table('inventory_warehouses')->insert(['id'=>'w','company_id'=>'a','code'=>'W','name'=>'Central','active'=>1,'is_default'=>1]);
        db_connect()->table('users')->insert(['id'=>'operator','name'=>'Operador']);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->tables) as $table) db_connect()->query('DROP TABLE ' . db_connect()->prefixTable($table));
        parent::tearDown();
    }

    private function controller(array $post = []): InventoryController
    {
        $controller = new class extends InventoryController {
            protected function inventoryContext(string $requiredAccess = 'view') { return ['company'=>['id'=>'a'], 'canConfigure'=>true]; }
            protected function currentUser(): ?array { return ['id'=>'operator','company_id'=>'a']; }
        };
        $request = service('request');
        $request->setGlobal('get', []);
        $request->setGlobal('post', $post + ['ajax'=>'1','sku'=>'PACK','name'=>'Pack','cost_price'=>'25.50','sale_price'=>'40.75','active'=>'1']);
        $controller->initController($request, service('response'), service('logger'));
        return $controller;
    }

    private function create(string $type = 'kit'): array
    {
        $response = $this->controller(['product_type'=>$type,'unit'=>'manipulada','min_stock'=>'8','max_stock'=>'100','initial_stock'=>$type==='kit'?'999':'0','initial_warehouse_id'=>'w'])->storeProduct();
        $this->assertSame(200, $response->getStatusCode());
        return db_connect()->table('inventory_products')->get()->getRowArray();
    }

    public function testKitCreationIgnoresInjectedStockAndKeepsEditablePrices(): void
    {
        $product = $this->create();
        $this->assertSame('pack', $product['unit']);
        $this->assertEquals(0, $product['min_stock']);
        $this->assertEquals(0, $product['max_stock']);
        $this->assertEquals(25.50, $product['cost_price']);
        $this->assertEquals(40.75, $product['sale_price']);
        $this->assertSame(0, db_connect()->table('inventory_stock_levels')->countAllResults());
        $this->assertSame(0, db_connect()->table('inventory_movements')->countAllResults());
    }

    public function testEditingLegacyKitPreservesStockAndReservations(): void
    {
        $p = $this->create();
        db_connect()->table('inventory_stock_levels')->insert(['id'=>'s','company_id'=>'a','product_id'=>$p['id'],'warehouse_id'=>'w','quantity'=>7,'reserved_quantity'=>2]);
        $response = $this->controller(['product_type'=>'kit','initial_stock'=>'500','initial_warehouse_id'=>'w','min_stock'=>'10','max_stock'=>'20','sale_price'=>'99'])->updateProduct($p['id']);
        $this->assertSame(200, $response->getStatusCode());
        $stock = db_connect()->table('inventory_stock_levels')->get()->getRowArray();
        $this->assertEquals(7, $stock['quantity']);
        $this->assertEquals(2, $stock['reserved_quantity']);
        $this->assertEquals(99, db_connect()->table('inventory_products')->get()->getRowArray()['sale_price']);
        $this->assertSame(0, db_connect()->table('inventory_movements')->countAllResults());
    }

    public function testSimpleAcceptsZeroAndNormalizesUnit(): void
    {
        $p = $this->create('simple');
        $this->assertSame('unidad', $p['unit']);
        $this->assertEquals(8, $p['min_stock']);
        $this->assertEquals(100, $p['max_stock']);
        $response = $this->controller(['product_type'=>'simple','initial_stock'=>'0','min_stock'=>'0','max_stock'=>'0'])->updateProduct($p['id']);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertEquals(0, db_connect()->table('inventory_products')->get()->getRowArray()['min_stock']);
        $this->assertSame(0, db_connect()->table('inventory_movements')->countAllResults());
    }

    public function testCannotConvertSimpleWithStockOrReservationsEvenWhenTotalIsZero(): void
    {
        $p = $this->create('simple');
        foreach ([[5,0],[0,2],[-5,0]] as [$qty,$reserved]) {
            db_connect()->table('inventory_stock_levels')->where('company_id','a')->delete();
            db_connect()->table('inventory_stock_levels')->insertBatch([
                ['id'=>'s1','company_id'=>'a','product_id'=>$p['id'],'warehouse_id'=>'w','quantity'=>$qty,'reserved_quantity'=>$reserved],
                ['id'=>'s2','company_id'=>'a','product_id'=>$p['id'],'warehouse_id'=>'other','quantity'=>-$qty,'reserved_quantity'=>0],
            ]);
            $response = $this->controller(['product_type'=>'kit'])->updateProduct($p['id']);
            $this->assertSame(422, $response->getStatusCode());
            $this->assertSame('simple', db_connect()->table('inventory_products')->get()->getRowArray()['product_type']);
        }
    }

    public function testNegativeSimpleStockAndUnknownTypeRejectedBeforeInsert(): void
    {
        foreach ([['product_type'=>'simple','initial_stock'=>'-1'],['product_type'=>'simple','min_stock'=>'-2'],['product_type'=>'invalid']] as $input) {
            $this->assertSame(422, $this->controller($input)->storeProduct()->getStatusCode());
            $this->assertSame(0, db_connect()->table('inventory_products')->countAllResults());
        }
    }

    public function testAlertsExcludeKitsAndInactiveProductsBeforeLimitsWithoutRemovingThemFromCatalog(): void
    {
        $kit = $this->create();
        db_connect()->table('inventory_products')->where('id',$kit['id'])->update(['name'=>'000 Kit','min_stock'=>100,'max_stock'=>1]);
        $base = $kit; $base['id']='base'; $base['sku']='BASE'; $base['name']='ZZZ Base'; $base['product_type']='simple'; $base['min_stock']=1; $base['max_stock']=1;
        db_connect()->table('inventory_products')->insert($base);
        $inactive = $base; $inactive['id']='inactive'; $inactive['sku']='INACTIVE'; $inactive['active']=0;
        db_connect()->table('inventory_products')->insert($inactive);
        foreach ([$kit['id'],'inactive','base'] as $id) {
            for ($i=0;$i<7;$i++) {
                db_connect()->table('inventory_movements')->insert(['id'=>$id.$i,'company_id'=>'a','product_id'=>$id,'quantity'=>200,'occurred_at'=>'2000-01-01','movement_type'=>'ingreso']);
                db_connect()->table('inventory_reservations')->insert(['id'=>$id.$i,'company_id'=>'a','product_id'=>$id,'warehouse_id'=>'w','status'=>'active','reserved_by'=>'operator','reserved_at'=>'2000-01-01']);
            }
        }
        $controller=$this->controller();
        $method=new ReflectionMethod(InventoryController::class,'alerts'); $method->setAccessible(true);
        foreach ([0, 20] as $quantity) {
            db_connect()->table('inventory_stock_levels')->where('company_id','a')->delete();
            foreach ([$kit['id'],'inactive','base'] as $id) db_connect()->table('inventory_stock_levels')->insert(['id'=>$id,'company_id'=>'a','product_id'=>$id,'warehouse_id'=>'w','quantity'=>$quantity,'reserved_quantity'=>0]);
            $alerts=$method->invoke($controller,'a',[]);
            foreach ($alerts as $rows) foreach ($rows as $row) $this->assertSame('BASE',$row['sku']);
            $this->assertCount(5,$alerts['unusual']);
            $this->assertCount(6,$alerts['reservations']);
            $this->assertCount(1,$alerts[$quantity===0?'out_of_stock':'overstock']);
        }
        $catalog=new ReflectionMethod(InventoryController::class,'productStockRows'); $catalog->setAccessible(true);
        $this->assertCount(3,$catalog->invoke($controller,'a'));
    }

    public function testSharedFormRendersKitRulesForBothInventoryEntryPoints(): void
    {
        $p = $this->create();
        $request=service('request'); $request->setGlobal('get',['popup'=>'1']); $request->setGlobal('post',[]);
        $html=view('inventory/forms/product',['product'=>$p,'formAction'=>site_url('inventario/productos'),'companyId'=>'a','isPopup'=>true,'componentOptions'=>[],'warehouses'=>[],'currentStock'=>7]);
        $this->assertStringContainsString('x-data="inventoryProductForm"',$html);
        $this->assertStringContainsString('data-product-type="kit"',$html);
        $this->assertStringContainsString('name="initial_stock"',$html);
        $this->assertStringNotContainsString('type="text" name="unit"',$html);
        $this->assertSame(1,substr_count($html,'csp-3.17.4.min.js'));
        if (getenv('INVENTORY_FORM_PREVIEW') === '1') file_put_contents(WRITEPATH.'inventory-product-preview.html',$html);
        foreach (['inventory/index.php','inventory/configuration.php'] as $view) $this->assertStringContainsString('inventario/productos/nuevo',file_get_contents(APPPATH.'Views/'.$view));
    }
}
