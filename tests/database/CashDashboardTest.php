<?php
use App\Libraries\CashDashboard;
use CodeIgniter\Test\CIUnitTestCase;

final class CashDashboardTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db=db_connect('tests');
        $db->query('CREATE TABLE '.$db->prefixTable('cash_movements').' (company_id TEXT, cash_register_id TEXT, occurred_at TEXT, amount REAL, payment_method TEXT, reconciliation_status TEXT)');
        $db->query('CREATE TABLE '.$db->prefixTable('cash_sessions').' (company_id TEXT, cash_register_id TEXT, status TEXT)');
        foreach ([['a','r1','2026-10-01 12:00:00',100,'cash','pending'],['a','r1','2026-10-02 12:00:00',-30,'cash','confirmed'],['a','r2','2026-10-01 12:00:00',500,'card','confirmed'],['b','r1','2026-10-01 12:00:00',900,'cash','pending'],['a','r1','2026-09-30 12:00:00',700,'cash','confirmed']] as $row) {
            $db->table('cash_movements')->insert(array_combine(['company_id','cash_register_id','occurred_at','amount','payment_method','reconciliation_status'],$row));
        }
        $db->table('cash_sessions')->insert(['company_id'=>'a','cash_register_id'=>'r1','status'=>'open']);
    }
    protected function tearDown(): void
    {
        $db=db_connect('tests');
        foreach(['cash_movements','cash_sessions'] as $table) $db->query('DROP TABLE '.$db->prefixTable($table));
        parent::tearDown();
    }
    public function testScopePeriodAndSignedMovements(): void
    {
        $d=(new CashDashboard())->load('a',['r1'],'2026-10-01','2026-10-03');
        $this->assertSame(100.0,$d['income']);
        $this->assertSame(30.0,$d['expense']);
        $this->assertSame(70.0,$d['net']);
        $this->assertEquals(2,$d['count']);
        $this->assertEquals(1,$d['pending']);
        $this->assertEquals(1,$d['open']);
        $this->assertCount(3,$d['days']);
        $this->assertEquals(0,$d['days']['2026-10-03']['income']);
        $empty=(new CashDashboard())->load('a',[],'2026-10-01','2026-10-03');
        $this->assertEquals(0,$empty['count']);
        $this->assertEquals(0,$empty['open']);
        $html=view('cash/dashboard',['dashboard'=>$d]);
        $this->assertStringContainsString('Flujo neto',$html);
        $this->assertStringContainsString('70,00',$html);
    }
    public function testInvalidAndReversedPeriodsAreNormalized(): void
    {
        $this->assertSame(['2026-10-01','2026-10-03'],CashDashboard::period('2026-10-03','2026-10-01'));
        [$from,$to]=CashDashboard::period('2020-01-01','2026-10-01');
        $this->assertLessThanOrEqual(366*86400,strtotime($to)-strtotime($from));
        $this->assertSame(['2026-09-25','2026-10-01'],CashDashboard::period('2026-02-31','2026-10-01'));
    }
}
