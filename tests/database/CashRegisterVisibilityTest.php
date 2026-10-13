<?php

use App\Libraries\CashService;
use CodeIgniter\Test\CIUnitTestCase;

final class CashRegisterVisibilityTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = db_connect('tests');
        $this->assertSame('SQLite3', $db->DBDriver);
        $this->assertSame(':memory:', $db->database);
        foreach ([
            'cash_registers' => 'id TEXT, company_id TEXT, name TEXT, code TEXT, register_type TEXT, active INTEGER',
            'cash_sessions' => 'id TEXT, company_id TEXT, cash_register_id TEXT, status TEXT, opened_by TEXT, opened_at TEXT',
            'users' => 'id TEXT, name TEXT',
        ] as $table => $schema) {
            $db->query('CREATE TABLE '.$db->prefixTable($table).' ('.$schema.')');
        }
        $db->table('users')->insertBatch([['id'=>'seller','name'=>'Vendedor QA'], ['id'=>'other','name'=>'Otro vendedor']]);
        foreach (['a', 'b'] as $company) {
            $db->table('cash_registers')->insert(['id'=>$company, 'company_id'=>$company, 'name'=>'Caja QA', 'code'=>$company, 'register_type'=>'general', 'active'=>1]);
            $db->table('cash_sessions')->insert(['id'=>$company, 'company_id'=>$company, 'cash_register_id'=>$company, 'status'=>'open', 'opened_by'=>'seller', 'opened_at'=>'2026-10-13 08:00:00']);
        }
    }

    protected function tearDown(): void
    {
        $db = db_connect('tests');
        foreach (['cash_sessions', 'cash_registers', 'users'] as $table) $db->query('DROP TABLE '.$db->prefixTable($table));
        parent::tearDown();
    }

    public function testAdministratorSeesSellerSessionAndOperatorOnlyInSelectedCompany(): void
    {
        $service = new CashService();
        $map = array_column($service->activeSessions('a'), null, 'cash_register_id');
        $this->assertSame(['a'], array_keys($map));
        $this->assertSame('Vendedor QA', $map['a']['opened_by_name']);
        $this->assertSame('open', $map[$service->registerRows('a')[0]['id']]['status']);
        db_connect('tests')->table('cash_sessions')->where('id', 'a')->update(['status'=>'closed']);
        $this->assertSame([], $service->activeSessions('a'));
        $this->assertCount(1, $service->activeSessions('b'));
    }

    public function testSellerCannotSeeAnotherSellersOpenSession(): void
    {
        $this->assertCount(1, (new CashService('seller'))->activeSessions('a'));
        $this->assertSame([], (new CashService('other'))->activeSessions('a'));
        $this->assertSame([], (new CashService('other'))->registerRows('a'));
    }
}
