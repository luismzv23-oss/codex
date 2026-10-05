<?php
use App\Libraries\CashService;
use App\Libraries\CashDashboard;
use CodeIgniter\Test\CIUnitTestCase;

final class CashOwnershipTest extends CIUnitTestCase
{
    private array $tables = [];
    protected function setUp(): void
    {
        parent::setUp(); helper('app');
        $schemas = [
            'cash_registers'=>'id TEXT PRIMARY KEY, company_id TEXT, name TEXT, code TEXT, register_type TEXT, active INTEGER',
            'cash_sessions'=>'id TEXT PRIMARY KEY, company_id TEXT, cash_register_id TEXT, status TEXT, opened_by TEXT, opened_at TEXT, opening_amount REAL, expected_closing_amount REAL, actual_closing_amount REAL, difference_amount REAL, closed_by TEXT, closed_at TEXT, notes TEXT, created_at TEXT, updated_at TEXT',
            'cash_movements'=>'id TEXT PRIMARY KEY, company_id TEXT, cash_register_id TEXT, cash_session_id TEXT, gateway_id TEXT, cash_check_id TEXT, occurred_at TEXT, amount REAL, payment_method TEXT, reconciliation_status TEXT, movement_type TEXT, reference_type TEXT, reference_id TEXT, created_by TEXT, notes TEXT, created_at TEXT, updated_at TEXT',
            'cash_payment_gateways'=>'id TEXT, name TEXT',
            'cash_checks'=>'id TEXT, check_number TEXT',
            'cash_closures'=>'id TEXT PRIMARY KEY, company_id TEXT, cash_session_id TEXT, closed_by TEXT, closed_at TEXT, opening_amount REAL, expected_amount REAL, actual_amount REAL, difference_amount REAL, notes TEXT, created_at TEXT, updated_at TEXT',
            'journal_entries'=>'id TEXT, company_id TEXT, reference_type TEXT, reference_id TEXT',
            'company_settings'=>'company_id TEXT, key TEXT, value TEXT',
        ];
        $db=db_connect();
        foreach($schemas as $table=>$schema){$db->query('CREATE TABLE '.$db->prefixTable($table).' ('.$schema.')');$this->tables[]=$table;}
        foreach(['r1','r2','free'] as $id) $db->table('cash_registers')->insert(['id'=>$id,'company_id'=>'demo','name'=>$id,'code'=>$id,'register_type'=>'general','active'=>1]);
        foreach([['mine','r1','seller','open'],['old-other','r1','other','closed'],['other','r2','other','open']] as [$id,$register,$owner,$status]) {
            $db->table('cash_sessions')->insert(['id'=>$id,'company_id'=>'demo','cash_register_id'=>$register,'opened_by'=>$owner,'status'=>$status,'opened_at'=>$id==='old-other'?'2026-09-30':'2026-10-04','opening_amount'=>0]);
            $db->table('cash_movements')->insert(['id'=>$id,'company_id'=>'demo','cash_register_id'=>$register,'cash_session_id'=>$id,'amount'=>$id==='mine'?100:900,'occurred_at'=>'2026-10-04 10:00:00','payment_method'=>'cash','reconciliation_status'=>'confirmed']);
        }
        $db->table('company_settings')->insert(['company_id'=>'demo','key'=>'account_cash','value'=>'cash']);
    }
    protected function tearDown(): void
    {
        foreach(array_reverse($this->tables) as $table) db_connect()->query('DROP TABLE '.db_connect()->prefixTable($table));
        parent::tearDown();
    }
    public function testSellerReadsOnlyOwnSessionsEvenOnSharedRegister(): void
    {
        $cash=new CashService('seller');
        $this->assertSame(['mine'],array_column($cash->activeSessions('demo'),'id'));
        $this->assertSame(['r1'],array_column($cash->registerRows('demo'),'id'));
        $this->assertSame([], $cash->activeSessions('demo','r2'));
        $this->assertSame(['mine'],array_column($cash->recentSessions('demo'),'id'));
        $this->assertSame(['mine'],array_column($cash->recentMovements('demo'),'id'));
        $this->assertEquals(100,$cash->paymentMethodBreakdown('demo')[0]['total']);
        $this->assertNull($cash->ownedSession('demo','other'));
        $this->assertNull($cash->ownedSession('wrong-company','mine'));
        $dashboard=(new CashDashboard())->load('demo',['r1','r2'],'2026-10-04','2026-10-04','seller');
        $this->assertEquals(100,$dashboard['income']);
        $this->assertEquals(1,$dashboard['open']);
        $this->assertCount(2,(new CashService())->activeSessions('demo'));
    }
    public function testSellerCannotMutateForeignSessionOrOpenSecondSession(): void
    {
        $cash=new CashService('seller');
        $this->assertFalse($cash->closeSession('demo','other','seller',900,null,true));
        $this->assertNull($cash->registerMovement(['company_id'=>'demo','cash_register_id'=>'r2','cash_session_id'=>'other','amount'=>20]));
        $this->assertNull($cash->openSession('demo','free','seller',0));
        $this->assertSame('open',db_connect()->table('cash_sessions')->where('id','other')->get()->getRowArray()['status']);
        $this->expectException(RuntimeException::class);
        $cash->createReconciliation(['company_id'=>'demo','cash_session_id'=>'other']);
    }
    public function testSellerClosesOwnSessionThenOpensAvailableRegister(): void
    {
        $cash=new CashService('seller');
        $this->assertTrue($cash->closeSession('demo','mine','seller',100));
        $this->assertSame([], $cash->activeSessions('demo'));
        $this->assertSame(['free','r1'],array_column($cash->registerRows('demo'),'id'));
        $this->assertNull($cash->openSession('demo','r2','seller',0));
        $id=$cash->openSession('demo','free','seller',10);
        $this->assertNotEmpty($id);
        $this->assertSame('seller',$cash->ownedSession('demo',$id)['opened_by']);
        $this->assertSame(['free'],array_column($cash->registerRows('demo'),'id'));
        $this->assertNull($cash->openSession('demo','r1','seller',0));
    }
    public function testSellerWithoutSessionCannotUseAnotherCashChannel(): void
    {
        $cash=new CashService('new-seller');
        session()->set('active_cash_register_id','r2');
        try {
            $this->assertSame([], $cash->activeSessions('demo'));
            $this->assertSame([], $cash->recentMovements('demo'));
            $this->assertSame(['free'],array_column($cash->registerRows('demo'),'id'));
            $this->assertNull($cash->activeSessionForChannel('demo','general'));
            $this->assertNull($cash->autoOpenKioskSession('demo','new-seller'));
            $this->assertSame('mine',(new CashService('seller'))->activeSessionForChannel('demo','general')['id']);
        } finally { session()->remove('active_cash_register_id'); }
    }
}
