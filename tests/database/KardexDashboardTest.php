<?php
use App\Libraries\KardexDashboard;
use CodeIgniter\Test\CIUnitTestCase;

final class KardexDashboardTest extends CIUnitTestCase
{
    public function testDailyGapsTypesAndCounts(): void {
        $d=KardexDashboard::summarize([['day'=>'2026-10-01','movement_type'=>'ingreso','total'=>410],['day'=>'2026-10-03','movement_type'=>'transferencia','total'=>2],['day'=>'2026-10-03','movement_type'=>'ajuste','total'=>1]],4);
        $this->assertSame(413,$d['total']);$this->assertSame(0,$d['days']['2026-10-02']);$this->assertSame(4,$d['products']);$this->assertSame(2,$d['transferencia']);$this->assertSame(1,$d['ajuste']);
    }
    public function testLongPeriodsUseMonthlyBuckets(): void {
        $d=KardexDashboard::summarize([['day'=>'2020-01-01','movement_type'=>'egreso','total'=>2],['day'=>'2026-10-03','movement_type'=>'egreso','total'=>4]],1);
        $this->assertTrue($d['monthly']);$this->assertSame(6,$d['total']);$this->assertSame(0,$d['days']['2020-02']);
    }
    public function testRenderEmptyAndPopulatedPanel(): void {
        foreach([[],[['day'=>'2026-10-03','movement_type'=>'egreso','total'=>1]]] as $groups){
            $html=view('inventory/kardex_dashboard',['dashboard'=>KardexDashboard::summarize($groups,count($groups))]);
            $this->assertStringContainsString($groups?'polyline':'Sin movimientos',$html);
            $this->assertStringContainsString('#kardex-movements',$html);
        }
    }
    public function testAggregatesAllFilteredRecordsAndIsolatesCompanies(): void {
        $db=db_connect();$table=$db->prefixTable('inventory_movements');
        $db->query('CREATE TABLE '.$table.' (company_id TEXT, product_id TEXT, occurred_at TEXT, movement_type TEXT, source_warehouse_id TEXT, destination_warehouse_id TEXT, source_document TEXT, reason TEXT)');
        try {
            for($i=0;$i<405;$i++){$db->table('inventory_movements')->insert(['company_id'=>'a','product_id'=>'p','occurred_at'=>'2026-10-03 23:59:59','movement_type'=>'ingreso','destination_warehouse_id'=>'w','source_document'=>'TEST']);}
            $db->table('inventory_movements')->insert(['company_id'=>'b','product_id'=>'private','occurred_at'=>'2026-10-03','movement_type'=>'ingreso','destination_warehouse_id'=>'w']);
            $method=new ReflectionMethod(\App\Controllers\InventoryController::class,'kardexDashboard');$method->setAccessible(true);
            $d=$method->invoke(new \App\Controllers\InventoryController(),'a',['start_date'=>'2026-10-03','end_date'=>'2026-10-03','destination_warehouse_id'=>'w','source_document'=>'TEST']);
            $this->assertSame(405,$d['total']);$this->assertSame(1,$d['products']);
            $d=$method->invoke(new \App\Controllers\InventoryController(),'a',['movement_type'=>'egreso']);$this->assertSame(0,$d['total']);
        } finally {$db->query('DROP TABLE '.$table);}
    }
}
