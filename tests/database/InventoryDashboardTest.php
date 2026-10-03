<?php
use App\Libraries\InventoryDashboard;
use CodeIgniter\Test\CIUnitTestCase;

final class InventoryDashboardTest extends CIUnitTestCase
{
    private array $tables=[];
    protected function setUp(): void {
        parent::setUp();
        $db=db_connect();
        foreach([
            'inventory_warehouses'=>'id TEXT, company_id TEXT, name TEXT',
            'inventory_products'=>'id TEXT, company_id TEXT, sku TEXT, name TEXT, category TEXT, unit TEXT, min_stock REAL, cost_price REAL, active INTEGER, product_type TEXT',
            'inventory_stock_levels'=>'product_id TEXT, company_id TEXT, warehouse_id TEXT, quantity REAL, reserved_quantity REAL',
            'inventory_movements'=>'company_id TEXT, product_id TEXT, occurred_at TEXT, movement_type TEXT, source_warehouse_id TEXT, destination_warehouse_id TEXT',
        ] as $t=>$schema){$db->query('CREATE TABLE '.$db->prefixTable($t).' ('.$schema.')');$this->tables[]=$t;}
        $db->table('inventory_warehouses')->insertBatch([['id'=>'w1','company_id'=>'a','name'=>'Uno'],['id'=>'w2','company_id'=>'a','name'=>'Dos'],['id'=>'foreign','company_id'=>'b','name'=>'Privado']]);
        foreach([['p1','a',1,'simple'],['p2','a',1,'simple'],['p3','a',1,'simple'],['inactive','a',0,'simple'],['service','a',1,'service'],['foreign','b',1,'simple']] as [$id,$company,$active,$type]){$db->table('inventory_products')->insert(['id'=>$id,'company_id'=>$company,'sku'=>$id,'name'=>$id,'category'=>'Bebidas','unit'=>'unidad','min_stock'=>3,'cost_price'=>10,'active'=>$active,'product_type'=>$type]);}
        $db->table('inventory_stock_levels')->insertBatch([
            ['product_id'=>'p1','company_id'=>'a','warehouse_id'=>'w1','quantity'=>2.5,'reserved_quantity'=>1],
            ['product_id'=>'p1','company_id'=>'a','warehouse_id'=>'w1','quantity'=>1.5,'reserved_quantity'=>0],
            ['product_id'=>'p1','company_id'=>'a','warehouse_id'=>'w2','quantity'=>5,'reserved_quantity'=>0],
            ['product_id'=>'p2','company_id'=>'a','warehouse_id'=>'w1','quantity'=>2,'reserved_quantity'=>2],
            ['product_id'=>'foreign','company_id'=>'b','warehouse_id'=>'foreign','quantity'=>999,'reserved_quantity'=>0],
        ]);
        foreach([['a','p1','2026-10-03 23:59:59','ingreso',null,'w1'],['a','p1','2026-10-04 00:00:00','egreso','w1',null],['a','p1','2026-10-03 12:00:00','transferencia','w1','w2'],['b','foreign','2026-10-03 12:00:00','ingreso',null,'foreign']] as [$c,$p,$date,$type,$src,$dst]){$db->table('inventory_movements')->insert(['company_id'=>$c,'product_id'=>$p,'occurred_at'=>$date,'movement_type'=>$type,'source_warehouse_id'=>$src,'destination_warehouse_id'=>$dst]);}
    }
    protected function tearDown(): void {foreach(array_reverse($this->tables) as $t){db_connect()->query('DROP TABLE '.db_connect()->prefixTable($t));}parent::tearDown();}
    public function testCompanyScopeReservationsAndMultipleLocations(): void {
        $d=(new InventoryDashboard())->build('a',['from'=>'2026-10-03','to'=>'2026-10-03']);
        $this->assertSame(3,$d['products']);$this->assertSame(2,$d['out']);$this->assertSame(0,$d['low']);$this->assertSame(2,$d['reserved']);$this->assertSame(110.0,$d['value']);
        $this->assertSame(2,$d['movement_count']);$this->assertSame(['ingreso'=>1,'egreso'=>0,'otros'=>1],$d['days']['2026-10-03']);$this->assertCount(2,$d['warehouses']);
    }
    public function testWarehouseFilterAndTransfersCountOnce(): void {
        $d=(new InventoryDashboard())->build('a',['warehouse'=>'w1','from'=>'2026-10-03','to'=>'2026-10-03']);
        $this->assertSame(1,$d['low']);$this->assertSame(60.0,$d['value']);$this->assertSame(2,$d['movement_count']);
        $d=(new InventoryDashboard())->build('a',['warehouse'=>'w2','from'=>'2026-10-03','to'=>'2026-10-03']);$this->assertSame(1,$d['movement_count']);$this->assertSame(50.0,$d['value']);
    }
    public function testEmptyCompanyAndDateNormalization(): void {
        $d=(new InventoryDashboard())->build('empty',['from'=>'2000-01-01','to'=>'2026-10-03','warehouse'=>'foreign']);
        $this->assertSame(0,$d['products']);$this->assertSame(0,$d['movement_count']);$this->assertSame('',$d['filters']['warehouse']);$this->assertCount(90,$d['days']);
        $d=(new InventoryDashboard())->build('a',['from'=>'2026-12-01','to'=>'2026-10-03']);$this->assertCount(1,$d['days']);
    }
    public function testSummaryRendersWithActivityAndWithNoProducts(): void {
        helper('url');
        foreach(['a','empty'] as $company) {
            $d=(new InventoryDashboard())->build($company,['from'=>'2026-10-03','to'=>'2026-10-03']);
            $html=view('inventory/dashboard',['dashboard'=>$d,'context'=>['company'=>['name'=>'Demo','currency_code'=>'ARS']],'selectedCompanyId'=>$company]);
            $this->assertStringContainsString('inventory-activity',$html);
            $this->assertStringContainsString('inventory-priorities',$html);
            $this->assertStringContainsString($company==='a'?'polyline':'Sin movimientos',$html);
        }
    }

}
