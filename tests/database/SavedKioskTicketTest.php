<?php

use CodeIgniter\Test\CIUnitTestCase;

final class SavedKioskTicketTest extends CIUnitTestCase
{
    private array $tables = [];
    protected function setUp(): void
    {
        parent::setUp(); helper('url');
        $db = db_connect('tests');
        $schemas = [
            'sales'=>'id TEXT, company_id TEXT, sale_number TEXT, document_code TEXT, status TEXT, currency_code TEXT, subtotal REAL, total REAL, tax_total REAL, payment_surcharge_amount REAL, issue_date TEXT, customer_name_snapshot TEXT',
            'company_settings'=>'company_id TEXT, key TEXT, value TEXT',
            'sale_items'=>'id TEXT, sale_id TEXT, product_id TEXT, product_name TEXT, line_number INTEGER, quantity REAL, unit_price REAL, line_total REAL, tax_rate REAL, tax_total REAL',
            'inventory_products'=>'id TEXT, brand TEXT',
            'sale_payments'=>'id TEXT, sale_id TEXT, payment_method_id TEXT, payment_method TEXT, payment_method_code TEXT, status TEXT, amount REAL, surcharge_amount REAL',
            'company_payment_methods'=>'id TEXT, company_id TEXT, show_on_receipt INTEGER',
        ];
        foreach ($schemas as $name=>$columns) { $db->query('CREATE TABLE '.$db->prefixTable($name).' ('.$columns.')'); $this->tables[]=$name; }
        $db->table('sales')->insert(['id'=>'saved','company_id'=>'a','sale_number'=>'T-0001','document_code'=>'TICKET','status'=>'confirmed','currency_code'=>'ARS','subtotal'=>100,'total'=>121,'issue_date'=>'2026-09-30','customer_name_snapshot'=>'Stored customer']);
        $db->table('sale_items')->insert(['id'=>'item','sale_id'=>'saved','product_id'=>'product','product_name'=>'Stored product','line_number'=>1,'quantity'=>1,'unit_price'=>121,'line_total'=>121,'tax_rate'=>21,'tax_total'=>21]);
        $db->table('company_payment_methods')->insert(['id'=>'method','company_id'=>'a','show_on_receipt'=>0]);
        $db->table('sale_payments')->insert(['id'=>'payment','sale_id'=>'saved','payment_method_id'=>'method','payment_method'=>'cash','payment_method_code'=>'EFECTIVO','status'=>'confirmed','amount'=>121]);
    }
    protected function tearDown(): void
    {
        foreach (array_reverse($this->tables) as $table) db_connect('tests')->query('DROP TABLE '.db_connect('tests')->prefixTable($table));
        parent::tearDown();
    }
    private function controller(string $company): \App\Controllers\SalesController
    {
        $controller = new class extends \App\Controllers\SalesController {
            public string $company;
            protected function salesContext(string $requiredAccess='view') { return ['company'=>['id'=>$this->company,'name'=>'Demo','legal_name'=>'Demo','tax_id'=>'123']]; }
        };
        $controller->company=$company;
        $controller->initController(service('request'),service('response'),service('logger'));
        return $controller;
    }
    public function testLoadsPersistedSaleAndPaymentVisibility(): void
    {
        $response=$this->controller('a')->ticket('saved');
        $body=$response->getBody();
        $this->assertStringContainsString('Stored product',$body);
        $this->assertStringContainsString('T-0001',$body);
        $this->assertStringContainsString('"total":121',$body);
        $this->assertStringContainsString('"show_on_receipt":0',$body);
        $this->assertStringContainsString('"qrUrl":null',$body);
        $this->assertStringContainsString('"draft":false',$body);
    }
    public function testCannotReadAnotherCompanySale(): void
    {
        $this->assertSame(404,$this->controller('b')->ticket('saved')->getStatusCode());
    }

    public function testSavedTicketUsesRecalculatedFiscalAmounts(): void
    {
        $db = db_connect('tests');
        $db->table('sales')->where('id','saved')->update(['total'=>124.03,'tax_total'=>21,'payment_surcharge_amount'=>3.03]);
        $db->table('sale_payments')->where('id','payment')->update(['amount'=>124.03,'surcharge_amount'=>3.03]);
        $db->table('company_payment_methods')->where('id','method')->update(['show_on_receipt'=>1]);
        $body = $this->controller('a')->ticket('saved')->getBody();
        $this->assertStringContainsString('"unit_price":124.03', $body);
        $this->assertStringContainsString('"line_total":124.03', $body);
        $this->assertStringContainsString('"subtotal":102.5', $body);
        $this->assertStringContainsString('"amount":21.53', $body);
        $this->assertStringContainsString('"total":124.03', $body);
    }

    public function testReceiptMatchesScreenWhenPaymentIsHidden(): void
    {
        $db = db_connect('tests');
        $db->table('sales')->where('id','saved')->update(['subtotal'=>24793.39,'total'=>30360,'tax_total'=>5206.61,'payment_surcharge_amount'=>360]);
        $db->table('sale_items')->where('id','item')->update(['unit_price'=>30000,'line_total'=>30000,'tax_total'=>5206.61]);
        $db->table('sale_payments')->where('id','payment')->update(['amount'=>30360,'surcharge_amount'=>360]);
        $body = $this->controller('a')->ticket('saved')->getBody();
        $this->assertStringContainsString('"unit_price":30360', $body);
        $this->assertStringContainsString('"line_total":30360', $body);
        $this->assertStringContainsString('"subtotal":25090.91', $body);
        $this->assertStringContainsString('"amount":5269.09', $body);
        $this->assertStringContainsString('"total":30360', $body);
        $this->assertStringContainsString('"show_on_receipt":0', $body);
    }
}
