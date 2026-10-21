<?php
use App\Libraries\{CashService,CashDashboard,TreasuryPaymentService,SupplierPaymentService,AccountingService};
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

final class TreasuryPaymentTest extends CIUnitTestCase
{
    use \Tests\Support\IntegrationEventsFixture;
    private array $tables=[];
    protected function setUp(): void
    {
        parent::setUp(); $this->installEventFixture(); helper(['app','url']); $this->db=db_connect(); $this->db->resetDataCache();
        foreach (['Company','CompanyPaymentMethod','Currency','Branch','SalesPointOfSale','CashRegister','CashSession','CashMovement','CashReconciliation','CashPaymentGateway','CashCheck','PurchasePayable','PurchasePayment','SupplierExchangeDifference','VoucherSequence'] as $name) {
            $class='App\\Models\\'.$name.'Model'; $model=new $class();
            $ref=new ReflectionClass($model); $prop=$ref->getProperty('allowedFields'); $prop->setAccessible(true);
            $fields=array_unique(array_merge(['id','created_at','updated_at'], $prop->getValue($model)));
            if ($name==='CompanyPaymentMethod') $fields[]='deleted_at';
            $table=$ref->getProperty('table'); $table->setAccessible(true); $table=$table->getValue($model);
            $columns=array_map(static fn($field)=>'"'.$field.'" '.($field==='id'?'TEXT PRIMARY KEY':'TEXT'),array_unique($fields));
            $this->db->query('CREATE TABLE '.$this->db->prefixTable($table).' ('.implode(',',$columns).')'); $this->tables[]=$table;
        }
        foreach ([
            'accounts'=>'id TEXT PRIMARY KEY, company_id TEXT, active INTEGER, is_group INTEGER, accepts_entries INTEGER',
            'company_settings'=>'company_id TEXT, key TEXT, value TEXT',
            'journal_entries'=>'id TEXT PRIMARY KEY, company_id TEXT, entry_number INTEGER, entry_date TEXT, description TEXT, reference_type TEXT, reference_id TEXT, status TEXT, total_debit REAL, total_credit REAL, user_id TEXT, posted_at TEXT, created_at TEXT',
            'journal_entry_lines'=>'id TEXT PRIMARY KEY, journal_entry_id TEXT, account_id TEXT, description TEXT, debit REAL, credit REAL, created_at TEXT',
            'automation_log'=>'id TEXT PRIMARY KEY, company_id TEXT, event TEXT, actions TEXT, actions_count INTEGER, created_at TEXT',
            'event_log'=>'id TEXT PRIMARY KEY, event TEXT, payload TEXT, listeners_count INTEGER, created_at TEXT',
        ] as $table=>$schema) {$this->db->query('CREATE TABLE '.$this->db->prefixTable($table).' ('.$schema.')');$this->tables[]=$table;}
        foreach (['a','b'] as $company) {
            $this->insert('companies',['id'=>$company,'currency_code'=>'ARS']);
            $this->insert('currencies',['id'=>$company.'-ars','company_id'=>$company,'code'=>'ARS','active'=>1]);
            $this->insert('branches',['id'=>$company.'-branch','company_id'=>$company,'active'=>1]);
            $this->insert('sales_points_of_sale',['id'=>$company.'-point','company_id'=>$company,'branch_id'=>$company.'-branch','active'=>1]);
        }
        $this->insert('currencies',['id'=>'usd','company_id'=>'a','code'=>'USD','active'=>1]);
        $this->insert('cash_registers',['id'=>'register','company_id'=>'a','branch_id'=>'a-branch','name'=>'Caja','active'=>1,'register_type'=>'general']);
        $this->insert('cash_sessions',['id'=>'session','company_id'=>'a','cash_register_id'=>'register','status'=>'open','opened_by'=>'owner','opening_amount'=>100,'expected_closing_amount'=>100]);
        $this->insert('cash_sessions',['id'=>'other','company_id'=>'b','cash_register_id'=>'register','status'=>'open','opened_by'=>'other','opening_amount'=>900]);
        foreach (['cash','card','transfer'] as $type) $this->insert('company_payment_methods',[
            'id'=>$type,'company_id'=>'a','code'=>strtoupper($type),'name'=>$type,'type'=>$type,'active'=>1,'percentage'=>7,
            'currency_ids'=>'[]','branch_ids'=>'[]','point_of_sale_ids'=>'[]','required_fields'=>'[]',
            'allow_collections'=>1,'allow_supplier_payments'=>$type==='card'?0:1,'requires_confirmation'=>0,'funds_destination'=>$type==='cash'?'cash':'bank',
        ]);
        $this->insert('purchase_payables',['id'=>'debt','company_id'=>'a','supplier_id'=>'supplier','currency_code'=>'ARS','total_amount'=>100,'paid_amount'=>0,'balance_amount'=>100,'status'=>'pending']);
        $this->insert('voucher_sequences',['id'=>'seq','company_id'=>'a','document_type'=>'PAGPROV','prefix'=>'PP','current_number'=>1,'active'=>1]);
        $accounting=$this->createMock(AccountingService::class); $accounting->method('syncPurchasePayment')->willReturn(['ok'=>true]);
        Services::injectMock('accounting',$accounting);
    }
    protected function tearDown(): void
    {
        $this->removeEventFixture();
        foreach (array_reverse($this->tables) as $table) $this->db->query('DROP TABLE '.$this->db->prefixTable($table));
        $this->db->resetDataCache(); parent::tearDown();
    }
    private function insert(string $table,array $row): void {$this->db->table($table)->insert($row);}
    private function input(string $method='cash',string $amount='40.00'): array
    {
        return ['request_key'=>app_uuid(),'cash_session_id'=>'session','movement_type'=>'manual_income',
            'purchase_payable_id'=>'debt','payment_method_id'=>$method,'currency_code'=>'ARS','amount'=>$amount,'reference'=>'REF-1'];
    }
    private function cash(): CashService {return new CashService('owner');}

    public function testManualIncomeKeepsSnapshotAndNoSalesSurchargeAndReplaysOnce(): void
    {
        $input=$this->input(); $policy=new TreasuryPaymentService();
        $id=$policy->manual($this->cash(),'a',$input,'owner'); $again=$policy->manual($this->cash(),'a',$input,'owner');
        $this->assertSame($id,$again); $this->assertSame(1,$this->db->table('cash_movements')->countAllResults());
        $row=$this->db->table('cash_movements')->get()->getRowArray();
        $this->assertSame('cash',$row['payment_method_id']); $this->assertEquals(40,$row['amount']);
        $this->assertEquals(7,json_decode($row['payment_method_snapshot'],true)['percentage']);
        $this->assertEquals(140,$this->cash()->expectedBalance('session',100));
        $input['amount']='41.00'; $this->expectException(RuntimeException::class); $policy->manual($this->cash(),'a',$input,'owner');
    }
    public function testDisabledSupplierMethodCannotBeUsedForManualExpense(): void
    {
        $input=$this->input('card'); $input['movement_type']='manual_expense';
        $this->expectException(RuntimeException::class); (new TreasuryPaymentService())->manual($this->cash(),'a',$input,'owner');
    }
    public function testManualAdjustmentSupportsNegativeDirection(): void
    {
        $input=$this->input(); $input['movement_type']='adjustment';$input['adjustment_direction']='expense';
        (new TreasuryPaymentService())->manual($this->cash(),'a',$input,'owner');
        $this->assertEquals(60,$this->cash()->expectedBalance('session',100));
    }
    public function testPendingManualConfirmationChangesBalanceExactlyOnce(): void
    {
        $policy=new TreasuryPaymentService();$id=$policy->manual($this->cash(),'a',$this->input('transfer'),'owner');
        $this->assertEquals(100,$this->cash()->expectedBalance('session',100));
        $this->assertSame([],$this->cash()->sessionPaymentBreakdown('a','session'));
        try {$this->cash()->closeSession('a','session','owner',100);$this->fail('Pending instruction must not be trapped in a closed session');}
        catch(RuntimeException $e){$this->assertStringContainsString('pendientes',$e->getMessage());}
        $policy->confirmManual($this->cash(),'a',$id,'owner','BANK-123');$policy->confirmManual($this->cash(),'a',$id,'owner','BANK-123');
        $this->assertEquals(140,$this->cash()->expectedBalance('session',100));
        $this->assertSame(1,$this->db->table('cash_movements')->countAllResults());
    }
    public function testManualOperationRejectsForeignSessionAndCompanyMethod(): void
    {
        $input=$this->input();$input['cash_session_id']='other';
        $this->expectException(RuntimeException::class);(new TreasuryPaymentService())->manual($this->cash(),'a',$input,'owner');
    }
    public function testPointAndCurrencyRestrictionsAreValidatedOnServer(): void
    {
        $this->db->table('company_payment_methods')->where('id','cash')->update(['point_of_sale_ids'=>'["a-point"]','currency_ids'=>'["a-ars"]']);
        $input=$this->input();$input['point_of_sale_id']='b-point';
        try {(new TreasuryPaymentService())->manual($this->cash(),'a',$input,'owner');$this->fail('Foreign point');}catch(RuntimeException $e){$this->assertNotEmpty($e->getMessage());}
        $input['point_of_sale_id']='a-point';$input['currency_code']='USD';
        $this->expectException(RuntimeException::class);(new TreasuryPaymentService())->manual($this->cash(),'a',$input,'owner');
    }
    public function testRequiredFieldsCannotBeSkippedByApi(): void
    {
        $this->db->table('company_payment_methods')->where('id','cash')->update(['required_fields'=>'["entidad"]']);
        $this->expectException(RuntimeException::class);(new TreasuryPaymentService())->manual($this->cash(),'a',$this->input(),'owner');
    }
    public function testReconciliationSeparatesMethodsCurrencyAndOpeningAndIgnoresPostedExpected(): void
    {
        $policy=new TreasuryPaymentService();$cash=$this->cash();
        $policy->manual($cash,'a',$this->input('cash','40.00'),'owner');
        $policy->manual($cash,'a',$this->input('card','20.00'),'owner');
        $usd=$this->input('cash','5.00');$usd['currency_code']='USD';$usd['exchange_rate']='1000';
        $policy->manual($cash,'a',$usd,'owner');
        $policy->manual($cash,'a',$this->input('transfer','80.00'),'owner');
        $balances=$cash->reconciliationBalances('a','session');$this->assertCount(4,$balances);
        $this->assertEquals(5160,$cash->expectedBalance('session',100));
        $id=$cash->createReconciliation(['company_id'=>'a','cash_session_id'=>'session','reconciliation_key'=>'method:cash','currency_code'=>'ARS','expected_amount'=>'9999','actual_amount'=>'38.00','created_by'=>'owner']);
        $row=$this->db->table('cash_reconciliations')->where('id',$id)->get()->getRowArray();
        $this->assertEquals(40,$row['expected_amount']);$this->assertEquals(-2,$row['difference_amount']);
        $this->assertSame('cash',$row['payment_method_id']);$this->assertSame(4,$this->db->table('cash_movements')->countAllResults());
        $this->assertEquals(100,array_values(array_filter($balances,static fn($b)=>$b['reconciliation_key']==='opening'))[0]['total']);
    }
    public function testDashboardExcludesPendingAndConvertsOnlyAggregateToBase(): void
    {
        $policy=new TreasuryPaymentService();$policy->manual($this->cash(),'a',$this->input(),'owner');
        $policy->manual($this->cash(),'a',$this->input('transfer','90.00'),'owner');
        $row=$this->input('card','2.00');$row['currency_code']='USD';$row['exchange_rate']='1000';$policy->manual($this->cash(),'a',$row,'owner');
        $data=(new CashDashboard())->load('a',['register'],date('Y-m-d'),date('Y-m-d'),'owner');
        $this->assertEquals(2040,$data['income']);$this->assertEquals(2,$data['count']);
        $this->assertCount(2,$this->cash()->paymentMethodBreakdown('a'));
    }
    public function testSupplierPendingReservesDebtButDoesNotPayUntilConfirmed(): void
    {
        $input=$this->input('transfer','60.00');$service=new SupplierPaymentService();
        $payment=$service->create('a',$input,'owner','a-branch');
        $this->assertSame('pending',$payment['status']);$this->assertEquals(100,$this->db->table('purchase_payables')->where('id','debt')->get()->getRowArray()['balance_amount']);
        $this->assertSame(0,$this->db->table('cash_movements')->countAllResults());
        $this->assertSame($payment['id'],$service->create('a',$input,'owner','a-branch')['id']);
        try {$service->create('a',$this->input('cash','50.00'),'owner','a-branch');$this->fail('Pending amount must reserve debt');}catch(RuntimeException $e){$this->assertStringContainsString('saldo',$e->getMessage());}
        $service->confirm('a',$payment['id'],'owner','BANK-1');$service->confirm('a',$payment['id'],'owner','BANK-1');
        $this->assertEquals(40,$this->db->table('purchase_payables')->where('id','debt')->get()->getRowArray()['balance_amount']);
        $this->assertSame(1,$this->db->table('cash_movements')->countAllResults());$this->assertEquals(40,$this->cash()->expectedBalance('session',100));
    }
    public function testSupplierConfirmationUsesSnapshotAfterCatalogChange(): void
    {
        $service=new SupplierPaymentService();$payment=$service->create('a',$this->input('transfer'),'owner','a-branch');
        $this->db->table('company_payment_methods')->where('id','transfer')->update(['name'=>'RENAMED','active'=>0,'percentage'=>99]);
        $service->confirm('a',$payment['id'],'owner','BANK-2');
        $row=$this->db->table('cash_movements')->get()->getRowArray();
        $this->assertSame('transfer',$row['payment_method_name']);$this->assertEquals(-40,$row['amount']);
    }
    public function testSupplierAccountingFailureRollsBackConfirmationCompletely(): void
    {
        $service=new SupplierPaymentService();$payment=$service->create('a',$this->input('transfer'),'owner','a-branch');
        $accounting=$this->createMock(AccountingService::class);$accounting->method('syncPurchasePayment')->willReturn(['ok'=>false,'error'=>'Accounting failed']);Services::injectMock('accounting',$accounting);
        try {$service->confirm('a',$payment['id'],'owner','BANK-3');$this->fail('Must roll back');}catch(RuntimeException $e){$this->assertSame('Accounting failed',$e->getMessage());}
        $this->assertSame('pending',$this->db->table('purchase_payments')->get()->getRowArray()['status']);
        $this->assertSame(0,$this->db->table('cash_movements')->countAllResults());$this->assertEquals(100,$this->db->table('purchase_payables')->where('id','debt')->get()->getRowArray()['balance_amount']);
    }
    public function testSupplierPaymentRejectsDisabledMethodAndForeignCurrency(): void
    {
        $service=new SupplierPaymentService();
        try {$service->create('a',$this->input('card'),'owner','a-branch');$this->fail('Card is collections only');}catch(RuntimeException $e){$this->assertNotEmpty($e->getMessage());}
        $input=$this->input();$input['currency_code']='USD';$this->expectException(RuntimeException::class);$service->create('a',$input,'owner','a-branch');
    }
    public function testAccountingUsesConfiguredBankAndPostsOnlyOnce(): void
    {
        foreach (['cash','bank','payable'] as $key) {
            $this->insert('accounts',['id'=>$key,'company_id'=>'a','active'=>1,'is_group'=>0,'accepts_entries'=>1]);
            $this->insert('company_settings',['company_id'=>'a','key'=>'account_'.$key,'value'=>$key]);
        }
        $service=new SupplierPaymentService();$payment=$service->create('a',$this->input('transfer'),'owner','a-branch');
        $accounting=new AccountingService();
        $this->assertSame('pending',$accounting->syncPurchasePayment('a',$payment['id'],'owner')['skipped']);
        $this->assertSame(0,$this->db->table('journal_entries')->countAllResults());
        $service->confirm('a',$payment['id'],'owner','BANK-44');
        $this->assertTrue($accounting->syncPurchasePayment('a',$payment['id'],'owner')['ok']);
        $this->assertTrue($accounting->syncPurchasePayment('a',$payment['id'],'owner')['already_synced']);
        $this->assertSame(1,$this->db->table('journal_entries')->countAllResults());
        $credit=$this->db->table('journal_entry_lines')->where('credit >',0)->get()->getRowArray();
        $this->assertSame('bank',$credit['account_id']);$this->assertEquals(40,$credit['credit']);
        $this->assertFalse($accounting->syncPurchasePayment('b',$payment['id'],'owner')['ok']);
    }
    public function testTreasuryPopupsRenderWithoutNavigationAndWithSpecificMethods(): void
    {
        helper('auth');
        $common=['pageTitle'=>'Tesorería','companyId'=>'a','isPopup'=>true,'formAction'=>'/test',
            'sessions'=>[['id'=>'session','cash_register_id'=>'register','register_name'=>'Caja','opened_at'=>'2026-10-16 10:00:00']],
            'paymentCatalog'=>(new \App\Libraries\SalesPaymentService())->catalog('a')] + (new TreasuryPaymentService())->formOptions('a');
        foreach (['cash/forms/movement','cash/forms/reconciliation'] as $view) {
            $html=view($view,$common,['saveData'=>false]);
            $this->assertStringNotContainsString('<nav class="navbar',$html);
            $this->assertStringContainsString('name="popup"',$html);
        }
        $payable=$this->db->table('purchase_payables')->where('id','debt')->get()->getRowArray();
        $html=view('purchases/forms/payment',$common+['context'=>['company'=>['id'=>'a'],'canManage'=>true],
            'payable'=>$payable,'supplier'=>['name'=>'Proveedor'],'gateways'=>[],'checks'=>[]],['saveData'=>false]);
        $this->assertStringContainsString('name="payment_method_id"',$html);
        $this->assertStringContainsString('CASH · cash',$html);
        $this->assertStringNotContainsString('<nav class="navbar',$html);
        $this->assertStringNotContainsString('purchases-hero-actions',$html);
        $this->assertStringNotContainsString('value="transferencia"',$html);
    }

    public function testChequeCannotBePromisedTwiceAndIsEndorsedOnceOnConfirmation(): void
    {
        $this->insert('company_payment_methods',['id'=>'check','company_id'=>'a','code'=>'CHEQUE','name'=>'Cheque de tercero','type'=>'check','active'=>1,
            'currency_ids'=>'[]','branch_ids'=>'[]','point_of_sale_ids'=>'[]','required_fields'=>'[]','allow_collections'=>1,'allow_supplier_payments'=>1,'requires_confirmation'=>1,'funds_destination'=>'clearing']);
        $this->insert('cash_checks',['id'=>'check-one','company_id'=>'a','amount'=>40,'status'=>'portfolio']);
        $service=new SupplierPaymentService();$input=$this->input('check');$input['cash_check_id']='check-one';
        $payment=$service->create('a',$input,'owner','a-branch');
        $input['request_key']=app_uuid();
        try {$service->create('a',$input,'owner','a-branch');$this->fail('A cheque cannot be reserved twice');}catch(RuntimeException $e){$this->assertStringContainsString('cheque',$e->getMessage());}
        $service->confirm('a',$payment['id'],'owner','Endoso recibido');$service->confirm('a',$payment['id'],'owner','Endoso recibido');
        $this->assertSame('endorsed',$this->db->table('cash_checks')->where('id','check-one')->get()->getRowArray()['status']);
        $this->assertSame(1,$this->db->table('cash_movements')->countAllResults());
    }
    public function testReconciliationRetainsLegacyTypeInsteadOfAssigningItToConfiguredCash(): void
    {
        $this->insert('cash_movements',['id'=>'legacy','company_id'=>'a','cash_session_id'=>'session','cash_register_id'=>'register',
            'amount'=>50,'payment_method'=>'cash','status'=>'posted','exchange_rate'=>1]);
        (new TreasuryPaymentService())->manual($this->cash(),'a',$this->input(),'owner');
        $balances=$this->cash()->reconciliationBalances('a','session');
        $legacy=array_values(array_filter($balances,static fn($row)=>$row['reconciliation_key']==='legacy:cash'))[0];
        $specific=array_values(array_filter($balances,static fn($row)=>$row['reconciliation_key']==='method:cash'))[0];
        $this->assertEquals(50,$legacy['total']);$this->assertEquals(40,$specific['total']);
        $this->assertEmpty($legacy['payment_method_id']);$this->assertSame('ARS',$legacy['currency_code']);
    }

}
