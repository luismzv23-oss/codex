<?php

use App\Libraries\ArcaService;
use App\Libraries\PaymentFiscalPolicy;
use CodeIgniter\Test\CIUnitTestCase;

final class PaymentFiscalPolicyTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = db_connect('tests');
        foreach (['sales'=>'id TEXT, company_id TEXT', 'sale_payments'=>'sale_id TEXT, payment_method_id TEXT, surcharge_amount REAL, status TEXT', 'company_payment_methods'=>'id TEXT, company_id TEXT, show_on_receipt INTEGER'] as $table=>$columns) {
            $db->query('CREATE TABLE '.$db->prefixTable($table).' ('.$columns.')');
        }
        $db->table('sales')->insert(['id'=>'sale','company_id'=>'company']);
        $db->table('company_payment_methods')->insert(['id'=>'card','company_id'=>'company','show_on_receipt'=>0]);
        $db->table('sale_payments')->insert(['sale_id'=>'sale','payment_method_id'=>'card','surcharge_amount'=>55,'status'=>'confirmed']);
    }

    protected function tearDown(): void
    {
        $db = db_connect('tests');
        foreach (['sale_payments','company_payment_methods','sales'] as $table) $db->query('DROP TABLE '.$db->prefixTable($table));
        parent::tearDown();
    }

    public function testReceiptAmountsDoNotDependOnPaymentVisibility(): void
    {
        $sale = ['id'=>'sale','company_id'=>'company','total'=>2255,'subtotal'=>1818.18,'tax_total'=>381.82,'payment_surcharge_amount'=>55];
        [$fiscal] = PaymentFiscalPolicy::forSale($sale, []);
        $this->assertSame(2255.0, ArcaService::fiscalTotal($fiscal));
        db_connect('tests')->table('company_payment_methods')->where('id','card')->update(['show_on_receipt'=>1]);
        [$fiscal] = PaymentFiscalPolicy::forSale($sale, []);
        $this->assertSame(2255.0, ArcaService::fiscalTotal($fiscal));
        db_connect('tests')->table('company_payment_methods')->where('id','card')->update(['company_id'=>'other']);
        [$fiscal] = PaymentFiscalPolicy::forSale($sale, []);
        $this->assertSame(2255.0, ArcaService::fiscalTotal($fiscal));
    }
}
