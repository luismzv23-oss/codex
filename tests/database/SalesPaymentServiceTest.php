<?php
use App\Libraries\{SalesPaymentService, PaymentIntegrityService};
use CodeIgniter\Test\CIUnitTestCase;

final class SalesPaymentServiceTest extends CIUnitTestCase
{
    private array $tables = [];
    protected function setUp(): void
    {
        parent::setUp(); helper('app'); $this->db=db_connect(); $this->db->resetDataCache();
        $schemas=[
            'currencies'=>'id TEXT PRIMARY KEY, company_id TEXT, code TEXT, active INTEGER',
            'branches'=>'id TEXT PRIMARY KEY, company_id TEXT, active INTEGER',
            'sales_points_of_sale'=>'id TEXT PRIMARY KEY, company_id TEXT, branch_id TEXT, active INTEGER',
            'company_payment_methods'=>'id TEXT PRIMARY KEY, company_id TEXT, code TEXT, name TEXT, type TEXT, active INTEGER, percentage REAL, currency_ids TEXT, branch_ids TEXT, point_of_sale_ids TEXT, required_fields TEXT, requires_confirmation INTEGER, allows_installments INTEGER, show_on_receipt INTEGER, funds_destination TEXT, deleted_at TEXT',
            'sales'=>'id TEXT PRIMARY KEY, company_id TEXT, status TEXT',
            'cash_payment_gateways'=>'id TEXT PRIMARY KEY, company_id TEXT, active INTEGER', 'cash_checks'=>'id TEXT PRIMARY KEY, company_id TEXT, status TEXT',
        ];
        $model=new \App\Models\SalePaymentModel(); $property=new ReflectionProperty($model,'allowedFields');$property->setAccessible(true);
        $columns=array_unique(array_merge($property->getValue($model),['created_at','updated_at']));
        $schemas['sale_payments']=implode(',',array_map(static fn($field)=>'"'.$field.'" '.($field==='id'?'TEXT PRIMARY KEY':'TEXT'),$columns));
        foreach($schemas as $table=>$schema){$this->db->query('CREATE TABLE '.$this->db->prefixTable($table).' ('.$schema.')');$this->tables[]=$table;}
        foreach(['a','b'] as $company){
            $this->db->table('currencies')->insert(['id'=>$company.'-ars','company_id'=>$company,'code'=>'ARS','active'=>1]);
            $this->db->table('branches')->insert(['id'=>$company.'-branch','company_id'=>$company,'active'=>1]);
            $this->db->table('sales_points_of_sale')->insert(['id'=>$company.'-pos','company_id'=>$company,'branch_id'=>$company.'-branch','active'=>1]);
        }
        $this->db->table('sales')->insert(['id'=>'draft','company_id'=>'a','status'=>'draft']);
        $this->db->table('company_payment_methods')->insert(['id'=>'card','company_id'=>'a','code'=>'NARANJA','name'=>'Tarjeta Naranja','type'=>'card','active'=>1,'percentage'=>7,'currency_ids'=>'["a-ars"]','branch_ids'=>'["a-branch"]','point_of_sale_ids'=>'["a-pos"]','required_fields'=>'[]','requires_confirmation'=>0]);
    }
    protected function tearDown(): void
    {
        foreach(array_reverse($this->tables) as $table)$this->db->query('DROP TABLE '.$this->db->prefixTable($table));parent::tearDown();
    }
    private function context(): array {return (new SalesPaymentService())->context('a','ARS','a-branch','a-pos');}
    private function row(array $overrides=[]): array {return array_merge(['payment_method_id'=>'card','base_amount'=>'100.00'],$overrides);}
    public function testCatalogAndServerCalculateOnlyConfiguredRate(): void
    {
        $service=new SalesPaymentService();$catalog=$service->catalog('a');$this->assertCount(1,$catalog);$this->assertSame(['ARS'],$catalog[0]['currency_codes']);
        $payment=$service->prepare('a',[$this->row(['surcharge_amount'=>900,'surcharge_rate'=>99,'status'=>'confirmed'])],$this->context())[0];
        $this->assertEquals(107,$payment['amount']);$this->assertEquals(7,$payment['surcharge_amount']);$this->assertSame('registered',$payment['status']);
        $this->assertSame('Tarjeta Naranja',$payment['payment_method_name']);$this->assertEquals(7,json_decode($payment['payment_method_snapshot'],true)['percentage']);
    }
    public function testCompanyCurrencyBranchAndPointRestrictionsCannotBeBypassed(): void
    {
        $service=new SalesPaymentService();
        foreach([['currency_id'=>'b-ars'],['branch_id'=>'b-branch'],['point_of_sale_id'=>'b-pos']] as $override){
            try{$service->prepare('a',[$this->row()],array_replace($this->context(),$override));$this->fail('Must enforce scope');}catch(RuntimeException $e){$this->assertStringContainsString('habilitado',$e->getMessage());}
        }
        try{$service->prepare('b',[$this->row()],$service->context('b','ARS','b-branch','b-pos'));$this->fail('Must isolate');}catch(RuntimeException $e){$this->assertStringContainsString('habilitado',$e->getMessage());}
    }
    public function testInactiveAndDeletedMethodsCannotBeUsedForNewPayments(): void
    {
        $this->db->table('company_payment_methods')->update(['active'=>0]);
        $this->expectException(RuntimeException::class);(new SalesPaymentService())->prepare('a',[$this->row()],$this->context());
    }
    public function testArbitraryRequiredFieldsMustBeProvidedAndAreStored(): void
    {
        $this->db->table('company_payment_methods')->update(['required_fields'=>'["referencia","entidad"]']);$service=new SalesPaymentService();
        try{$service->prepare('a',[$this->row()],$this->context());$this->fail('Missing required field');}catch(RuntimeException $e){$this->assertStringContainsString('obligatorio',$e->getMessage());}
        $row=$service->prepare('a',[$this->row(['reference'=>'123','fields'=>['entidad'=>'Banco X']])],$this->context())[0];
        $this->assertSame('Banco X',json_decode($row['payment_data'],true)['entidad']);
    }
    public function testAnyConfiguredMethodRequiringConfirmationRemainsPending(): void
    {
        $this->db->table('company_payment_methods')->update(['requires_confirmation'=>1]);
        $row=(new SalesPaymentService())->prepare('a',[$this->row(['status'=>'confirmed'])],$this->context())[0];
        $this->assertSame('pending',$row['status']);$this->assertEquals(0,PaymentIntegrityService::paid([$row]));
    }
    public function testEmptyAndPartialDraftPaymentsAreAllowed(): void
    {
        $service=new SalesPaymentService();$this->assertSame([],$service->prepare('a',[],$this->context()));
        $row=$service->prepare('a',[$this->row(['base_amount'=>'25.00'])],$this->context())[0];$this->assertEquals(26.75,$row['amount']);
    }
    public function testReceiptsDoNotAddASecondSurchargeToIssuedInvoice(): void
    {
        $row=(new SalesPaymentService())->prepare('a',[$this->row()],$this->context(),null,true)[0];
        $this->assertEquals(100,$row['amount']);$this->assertEquals(0,$row['surcharge_amount']);$this->assertEquals(7,json_decode($row['payment_method_snapshot'],true)['percentage']);
    }
    public function testDraftReopenKeepsSnapshotAmountAndPaymentIdAfterCatalogChanges(): void
    {
        $service=new SalesPaymentService();$row=$service->prepare('a',[$this->row()],$this->context())[0];
        $service->replaceDraft('a','draft',[$row]);$saved=$this->db->table('sale_payments')->get()->getRowArray();
        $this->db->table('company_payment_methods')->update(['percentage'=>20,'name'=>'Nuevo nombre','active'=>0]);
        $rows=$service->prepare('a',[$this->row(['saved_payment_id'=>$saved['id']])],$this->context(),'draft');
        $service->replaceDraft('a','draft',$rows);$after=$this->db->table('sale_payments')->get()->getRowArray();
        $this->assertSame($saved['id'],$after['id']);$this->assertEquals(107,$after['amount']);$this->assertEquals(7,$after['surcharge_rate']);$this->assertSame('Tarjeta Naranja',$after['payment_method_name']);
        $this->assertSame(1,$this->db->table('sale_payments')->countAllResults());
    }
    public function testLegacyPaymentIsPreservedWithoutGuessingConfiguredCard(): void
    {
        $this->db->table('sale_payments')->insert(['id'=>'legacy','sale_id'=>'draft','payment_method'=>'card','amount'=>50,'status'=>'registered']);
        $service=new SalesPaymentService();$rows=$service->prepare('a',[['saved_payment_id'=>'legacy','base_amount'=>'50.00']],$this->context(),'draft');
        $this->assertEmpty($rows[0]['payment_method_id']);$this->assertSame('legacy',$rows[0]['id']);$this->assertEquals(50,$rows[0]['amount']);
        $this->expectException(RuntimeException::class);$service->prepare('a',[['payment_method'=>'card','amount'=>50]],$this->context());
    }
    public function testForeignAndRepeatedSavedPaymentIdsAreRejected(): void
    {
        $this->expectException(RuntimeException::class);(new SalesPaymentService())->prepare('a',[$this->row(['saved_payment_id'=>'foreign'])],$this->context(),'draft');
    }
    public function testInstallmentsAreNotSilentlySimulated(): void
    {
        $this->expectException(RuntimeException::class);(new SalesPaymentService())->prepare('a',[$this->row(['installments'=>3])],$this->context());
    }
    public function testConfirmationRejectsMethodDisabledAfterSavingWithoutRepricing(): void
    {
        $service=new SalesPaymentService();$rows=$service->prepare('a',[$this->row()],$this->context());$this->db->table('company_payment_methods')->update(['active'=>0]);
        $this->expectException(RuntimeException::class);$service->validatePersisted('a',['currency_code'=>'ARS','branch_id'=>'a-branch','point_of_sale_id'=>'a-pos'],$rows);
    }
    public function testWebAndApiPayloadsShareConfiguredPartialAndPendingPayments(): void
    {
        helper(['auth','url']);
        $extra=[
            'sales_document_types'=>'id TEXT PRIMARY KEY, company_id TEXT, code TEXT, category TEXT, channel TEXT, active INTEGER, requires_customer INTEGER',
            'inventory_products'=>'id TEXT PRIMARY KEY, company_id TEXT, sku TEXT, name TEXT, active INTEGER, sale_price REAL, cost_price REAL, product_type TEXT, unit TEXT',
            'inventory_warehouses'=>'id TEXT PRIMARY KEY, company_id TEXT, active INTEGER',
            'taxes'=>'id TEXT PRIMARY KEY, company_id TEXT, active INTEGER, is_default INTEGER, rate REAL, name TEXT',
            'sales_promotions'=>'id TEXT PRIMARY KEY, company_id TEXT, name TEXT, active INTEGER, start_date TEXT, end_date TEXT',
            'sales_promotion_items'=>'product_id TEXT, promotion_id TEXT',
            'sales_discount_policies'=>'id TEXT PRIMARY KEY, company_id TEXT, policy_type TEXT, active INTEGER, payment_method TEXT, discount_rate REAL',
            'cash_sessions'=>'id TEXT PRIMARY KEY, company_id TEXT, cash_register_id TEXT, status TEXT, opened_at TEXT',
            'cash_registers'=>'id TEXT PRIMARY KEY, company_id TEXT, name TEXT, code TEXT, register_type TEXT, active INTEGER',
        ];
        $model=new \App\Models\SalesSettingModel();$prop=new ReflectionProperty($model,'allowedFields');$prop->setAccessible(true);
        $extra['sales_settings']=implode(',',array_map(static fn($field)=>'"'.$field.'" '.($field==='id'?'TEXT PRIMARY KEY':'TEXT'),array_unique(array_merge(['id','created_at','updated_at'],$prop->getValue($model)))));
        foreach($extra as $table=>$schema){$this->db->query('CREATE TABLE '.$this->db->prefixTable($table).' ('.$schema.')');$this->tables[]=$table;}
        foreach(['name','symbol','exchange_rate'] as $field)$this->db->query('ALTER TABLE '.$this->db->prefixTable('currencies').' ADD COLUMN '.$field.' TEXT');
        $this->db->table('currencies')->update(['name'=>'Pesos','symbol'=>'$','exchange_rate'=>1]);
        $this->db->table('sales_document_types')->insert(['id'=>'invoice','company_id'=>'a','code'=>'FACTURA_B','category'=>'invoice','channel'=>'standard','active'=>1,'requires_customer'=>0]);
        $this->db->table('inventory_products')->insert(['id'=>'product','company_id'=>'a','sku'=>'SKU','name'=>'Producto','active'=>1,'sale_price'=>100,'cost_price'=>50,'product_type'=>'simple']);
        $this->db->table('inventory_warehouses')->insert(['id'=>'warehouse','company_id'=>'a','active'=>1]);
        $this->db->table('sales_settings')->insert(['id'=>'settings','company_id'=>'a','default_currency_code'=>'ARS','strict_company_currencies'=>1]);
        $this->db->table('sales_points_of_sale')->where('id','a-pos')->update(['active'=>1]);
        $this->db->table('company_payment_methods')->where('id','card')->update(['requires_confirmation'=>1]);
        $input=['warehouse_id'=>'warehouse','currency_code'=>'ARS','document_type_id'=>'invoice','point_of_sale_id'=>'a-pos','items'=>[['product_id'=>'product','quantity'=>1,'unit_price'=>100]],'payments'=>[$this->row(['base_amount'=>'40.00'])]];
        $web=new class extends \App\Controllers\SalesController {protected function currentUser(): ?array{return ['id'=>'operator','branch_id'=>'a-branch'];}};
        $api=new class extends \App\Controllers\Api\V1\SalesController {protected function apiUser(): ?array{return ['id'=>'operator','branch_id'=>'a-branch'];}protected function payload(): array{return [];}};
        foreach([$web,$api] as $controller){
            $controller->initController(service('request'),service('response'),service('logger'));
            $method=new ReflectionMethod($controller,'salePayload');$method->setAccessible(true);
            $payload=$method->invoke($controller,'a',$input);
            $this->assertIsArray($payload);$this->assertArrayNotHasKey('error',$payload);
            $this->assertEquals(102.8,$payload['sale']['total']);$this->assertEquals(0,$payload['sale']['paid_total']);
            $this->assertSame('card',$payload['payments'][0]['payment_method_id']);$this->assertSame('pending',$payload['payments'][0]['status']);
            $this->assertEquals(42.8,$payload['payments'][0]['amount']);
        }
        $this->assertSame(0,$this->db->table('sale_payments')->countAllResults());
    }

    public function testDraftFormRendersCompanyMethodsAndRestoresSavedPayments(): void
    {
        helper(['auth','url']);
        $data=['context'=>['company'=>['id'=>'a'],'canManage'=>true],'companyId'=>'a','pageTitle'=>'Editar venta','isPopup'=>true,
            'useConfiguredPayments'=>true,'configuredPaymentsDraft'=>true,'paymentMethods'=>(new SalesPaymentService())->catalog('a'),
            'sale'=>['id'=>'draft','status'=>'draft'], 'saleItems'=>[], 'salePayments'=>[['id'=>'legacy','payment_method'=>'card','amount'=>50,'status'=>'registered']],
            'customers'=>[], 'warehouses'=>[], 'products'=>[], 'taxes'=>[], 'priceLists'=>[], 'promotions'=>[], 'agents'=>[], 'zones'=>[], 'conditions'=>[],
            'documentTypes'=>[], 'pointsOfSale'=>[], 'currencyOptions'=>['ARS'=>'Pesos'], 'currencyCode'=>'ARS','salesSettings'=>[], 'formAction'=>'/ventas/draft/actualizar','activeSessions'=>[], 'selectedRegisterId'=>''];
        $html=view('sales/forms/sale',$data,['saveData'=>false]);
        $this->assertStringContainsString('const configuredPaymentsDraft = true',$html);
        $this->assertStringContainsString('NARANJA',$html);$this->assertStringContainsString('Tarjeta Naranja',$html);
        $this->assertStringContainsString('"id":"legacy"',$html);
        $this->assertStringContainsString('paymentRows.load(existingPayments)',$html);
        $this->assertStringContainsString('company-payments.css',$html);
        $this->assertStringContainsString('{compact:true,',$html);
        $this->assertStringContainsString('id="sale-payments-body" class="company-payments"',$html);
        $this->assertStringNotContainsString('id="sale-payments-body" class="overflow-auto"',$html);
        $this->assertStringNotContainsString('<th>Metodo</th>',$html);
        $this->assertStringNotContainsString('<nav class="navbar',$html);
        preg_match_all('~<script\b[^>]*>(.*?)</script>~s', $html, $scripts);
        file_put_contents(ROOTPATH.'tmp/company-payment-form.js',implode("\n",$scripts[1]));
    }

}
