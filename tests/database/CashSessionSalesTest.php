<?php

use App\Libraries\CashService;
use CodeIgniter\Test\CIUnitTestCase;

final class CashSessionSalesTest extends CIUnitTestCase
{
    private array $tables = ['cash_sessions', 'sales', 'customers', 'sales_document_types', 'sale_payments'];

    protected function setUp(): void
    {
        parent::setUp();
        $db = db_connect();
        $this->assertSame('SQLite3', $db->DBDriver);
        $this->assertSame(':memory:', $db->database);
        $schemas = [
            'id TEXT, company_id TEXT, opened_by TEXT',
            'id TEXT, company_id TEXT, cash_session_id TEXT, customer_id TEXT, document_type_id TEXT, status TEXT, created_at TEXT, sale_number TEXT, total REAL',
            'id TEXT, name TEXT', 'id TEXT, name TEXT',
            'sale_id TEXT, payment_method TEXT, amount REAL, status TEXT',
        ];
        foreach ($this->tables as $i => $table) $db->query('CREATE TABLE '.$db->prefixTable($table).' ('.$schemas[$i].')');
        $db->table('cash_sessions')->insert(['id'=>'shift','company_id'=>'demo','opened_by'=>'seller']);
        foreach (['demo', 'other'] as $company) $db->table('sales')->insert([
            'id'=>$company, 'company_id'=>$company, 'cash_session_id'=>'shift', 'status'=>'confirmed',
            'created_at'=>'2026-10-13', 'sale_number'=>'FCB-00000005', 'total'=>17520,
        ]);
        $db->table('sale_payments')->insertBatch([
            ['sale_id'=>'demo','payment_method'=>'cash','amount'=>1200,'status'=>'registered'],
            ['sale_id'=>'demo','payment_method'=>'qr','amount'=>16320,'status'=>'registered'],
            ['sale_id'=>'demo','payment_method'=>'transfer','amount'=>100,'status'=>'pending'],
        ]);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->tables) as $table) db_connect()->query('DROP TABLE '.db_connect()->prefixTable($table));
        parent::tearDown();
    }

    public function testMixedPaymentsProduceOneInvoiceWithItsRealTotal(): void
    {
        $rows = (new CashService())->sessionSales('demo', 'shift');
        $this->assertCount(1, $rows);
        $this->assertSame('FCB-00000005', $rows[0]['sale_number']);
        $this->assertEquals(17520, array_sum(array_column($rows, 'total')));
        $this->assertSame('Efectivo + QR', $rows[0]['payment_methods_label']);
        $this->assertCount(1, (new CashService('seller'))->sessionSales('demo', 'shift'));
        $this->assertSame([], (new CashService('another'))->sessionSales('demo', 'shift'));
        $this->assertSame([], (new CashService())->sessionSales('other', 'shift'));
    }
}
