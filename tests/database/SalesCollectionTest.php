<?php

use App\Libraries\SalesCollectionService;
use App\Libraries\PaymentIntegrityService;
use App\Libraries\CashService;
use App\Libraries\AccountingService;
use CodeIgniter\Test\CIUnitTestCase;

final class SalesCollectionTest extends CIUnitTestCase
{
    use \Tests\Support\IntegrationEventsFixture;
    private array $tables = [];
    protected function setUp(): void
    {
        parent::setUp(); $this->installEventFixture(); helper('app');
        $this->db = db_connect('tests');
        $this->db->resetDataCache();
        $schemas = [
            'companies' => 'id, currency_code',
            'customers' => 'id, company_id, active',
            'accounts' => 'id, company_id, active, is_group, accepts_entries, account_type',
            'sales' => 'id, company_id, currency_code, status, total, paid_total, payment_status, customer_id, subtotal, tax_total, created_by, sale_number, issue_date',
            'sale_returns' => 'id, sale_id, status, total, return_number, return_date',
            'sale_return_items' => 'id, sale_return_id, sale_item_id, line_total',
            'sale_items' => 'id, sale_id, quantity, line_total, returned_quantity, line_number, subtotal, tax_total, tax_rate',
            'sales_receivables' => 'id, company_id, sale_id, customer_id, document_number, total_amount, paid_amount, balance_amount, status',
            'sales_receipts' => 'id, company_id, customer_id, receipt_number, issue_date, currency_code, payment_method, payment_details, total_amount, reference, notes, status, created_by, created_at, updated_at, cash_register_id, cash_session_id, confirmed_by, confirmed_at, confirmation_note, reversed_by, reversed_at, reversal_reason',
            'sales_receipt_items' => 'id, sales_receipt_id, sales_receivable_id, sale_id, document_number, applied_amount, created_at, updated_at',
            'sale_payments' => 'id, sale_id, payment_method, amount, reference, status, paid_at, notes, created_at, updated_at, gateway_id, cash_check_id, external_reference, sales_receipt_id',
            'cash_sessions' => 'id, company_id, cash_register_id, status, opened_by, opening_amount, expected_closing_amount, created_at, updated_at',
            'cash_movements' => 'id, company_id, cash_session_id, cash_register_id, movement_type, payment_method, gateway_id, cash_check_id, amount, reference_type, reference_id, reference_number, external_reference, reconciliation_status, occurred_at, notes, created_by, created_at, updated_at',
            'cash_payment_gateways' => 'id, company_id, active', 'cash_checks' => 'id, company_id, status',
            'company_settings' => 'id, company_id, key, value',
            'journal_entries' => 'id, company_id, entry_number, entry_date, description, reference_type, reference_id, status, total_debit, total_credit, user_id, posted_at, created_at',
            'journal_entry_lines' => 'id, journal_entry_id, account_id, description, debit, credit, created_at',
        ];
        foreach ($schemas as $table => $fields) {
            $columns = array_map(static fn($f) => '"' . $f . '" ' . ($f === 'id' ? 'TEXT PRIMARY KEY' : 'TEXT'), explode(', ', $fields));
            $this->db->query('CREATE TABLE ' . $this->db->prefixTable($table) . ' (' . implode(',', $columns) . ')');
            $this->tables[] = $table;
        }
        $this->db->query('CREATE TABLE ' . $this->db->prefixTable('company_payment_methods') . ' (id TEXT PRIMARY KEY, company_id TEXT, code TEXT, name TEXT, type TEXT, active INTEGER, percentage REAL, currency_ids TEXT, branch_ids TEXT, point_of_sale_ids TEXT, required_fields TEXT, requires_confirmation INTEGER, deleted_at TEXT)');
        $this->tables[] = 'company_payment_methods';
        $this->db->query('CREATE TABLE ' . $this->db->prefixTable('currencies') . ' (id TEXT PRIMARY KEY, company_id TEXT, code TEXT, active INTEGER)'); $this->tables[] = 'currencies';
        $this->db->table('currencies')->insert(['id'=>'ars','company_id'=>'a','code'=>'ARS','active'=>1]);
        foreach (['cash','card','check','transfer','qr'] as $type) $this->db->table('company_payment_methods')->insert(['id'=>$type,'company_id'=>'a','code'=>strtoupper($type),'name'=>strtoupper($type),'type'=>$type==='qr'?'wallet':$type,'active'=>1,'percentage'=>0]);
        foreach (['payment_method_id','payment_method_code','payment_method_name','payment_method_snapshot','payment_data','base_amount','surcharge_rate','surcharge_amount','received_amount','change_amount'] as $field) $this->db->query('ALTER TABLE '.$this->db->prefixTable('sale_payments').' ADD COLUMN '.$field.' TEXT');
        foreach (array_merge(\App\Libraries\TreasuryPaymentService::METADATA,['currency_code','exchange_rate']) as $field) $this->db->query('ALTER TABLE '.$this->db->prefixTable('cash_movements').' ADD COLUMN '.$field.' TEXT');
        require_once APPPATH . 'Database/Migrations/2026-10-10-120000_ExtendCollections.php';
        $migration = new \App\Database\Migrations\ExtendCollections(\Config\Database::forge('tests'));
        $migration->up();
        $this->tables[] = 'collection_requests';
        $this->db->table('customers')->insert(['id'=>'customer','company_id'=>'a','active'=>1]);
        $this->db->table('companies')->insert(['id'=>'a','currency_code'=>'ARS']);
        foreach (['one','two'] as $id) {
            $this->db->table('sales')->insert(['id'=>$id,'company_id'=>'a','currency_code'=>'ARS','status'=>'confirmed','customer_id'=>'customer','total'=>100,'paid_total'=>0,'payment_status'=>'pending']);
            $this->db->table('sales_receivables')->insert(['id'=>$id,'company_id'=>'a','sale_id'=>$id,'customer_id'=>'customer','document_number'=>$id,'total_amount'=>100,'paid_amount'=>0,'balance_amount'=>100,'status'=>'pending']);
        }
        $this->db->table('cash_sessions')->insert(['id'=>'session','company_id'=>'a','cash_register_id'=>'register','status'=>'open','opened_by'=>'owner','opening_amount'=>0]);
        foreach (['cash','bank','receivable','card_clearing','check_clearing','qr_clearing','customer_advances'] as $account) { $this->db->table('accounts')->insert(['id'=>$account,'company_id'=>'a','active'=>1,'is_group'=>0,'accepts_entries'=>1,'account_type'=>$account==='customer_advances'?'liability':'asset']); $this->db->table('company_settings')->insert(['id'=>$account,'company_id'=>'a','key'=>'account_'.$account,'value'=>$account]); }
    }
    protected function tearDown(): void
    {
        $this->removeEventFixture();
        foreach (array_reverse($this->tables) as $table) { $this->db->query('DROP TABLE '.$this->db->prefixTable($table)); }
        parent::tearDown();
    }
    private function service(bool $fail = false, ?string $owner = null): SalesCollectionService
    {
        $cash = new class($fail, $owner) extends CashService {
            private bool $fail;
            public function __construct(bool $fail, ?string $owner) { parent::__construct($owner); $this->fail = $fail; }
            public function activeSessionForChannel(string $companyId, string $channel = 'general'): ?array { return ['id'=>'session']; }
            public function registerMovement(array $data): ?string { return $this->fail ? null : parent::registerMovement($data); }
        };
        return new SalesCollectionService(new AccountingService(), $cash);
    }
    private function payload(array $payments = [], array $items = []): array
    {
        $payments = array_map(static fn($payment) => $payment + ['payment_method_id'=>$payment['payment_method'],'base_amount'=>$payment['amount']], $payments ?: [['payment_method'=>'cash','amount'=>100]]);
        return ['request_key'=>app_uuid(), 'payments'=>$payments, 'items'=>$items ?: [['receivable_id'=>'one','applied_amount'=>100]]];
    }
    public function testMixedReceiptAndReversalPreservePaymentsAndReverseLedger(): void
    {
        $payments = [['payment_method'=>'cash','amount'=>40], ['payment_method'=>'card','amount'=>20], ['payment_method'=>'check','amount'=>20], ['payment_method'=>'qr','amount'=>20]];
        $r = $this->service()->create('a','user',$this->payload($payments),fn()=>'REC-1');
        $this->assertSame('applied',$r['status']);
        try {$this->service()->assertSaleCanCancel('a','one');$this->fail('Collected sale must not be cancelled');}catch(RuntimeException $e){$this->assertStringContainsString('cobros',$e->getMessage());}
        $this->assertSame(4,$this->db->table('cash_movements')->countAllResults());
        foreach (['cash'=>40,'card_clearing'=>20,'check_clearing'=>20,'qr_clearing'=>20,'bank'=>0] as $account=>$amount) {
            $this->assertEquals($amount,$this->db->table('journal_entry_lines')->where('account_id',$account)->selectSum('debit')->get()->getRowArray()['debit'] ?? 0);
        }
        foreach (json_decode($r['payment_details'],true) as $payment) {
            if ($payment['payment_method']==='cash') continue;
            $this->service()->settleReceiptPayment('a',$r['id'],$payment['id'],'user','BANK-SETTLE');
            $this->service()->settleReceiptPayment('a',$r['id'],$payment['id'],'user','BANK-SETTLE');
        }
        $this->assertEquals(60,$this->db->table('journal_entry_lines')->where('account_id','bank')->selectSum('debit')->get()->getRowArray()['debit']);
        $this->assertSame(4,$this->db->table('cash_movements')->countAllResults(), 'Accreditation must not collect again');
        $this->service()->reverse('a',$r['id'],'user','Error de registro');
        $this->service()->reverse('a',$r['id'],'user','Reintento');
        $this->service()->assertSaleCanCancel('a','one');
        $this->assertSame(8,$this->db->table('cash_movements')->countAllResults());
        $this->assertEquals(0,$this->db->table('cash_movements')->selectSum('amount')->get()->getRowArray()['amount']);
        $this->assertEquals(100,$this->db->table('sales_receivables')->where('id','one')->get()->getRowArray()['balance_amount']);
        $this->assertSame(4,$this->db->table('sale_payments')->where('status','reversed')->countAllResults());
        $this->assertSame(8,$this->db->table('journal_entries')->countAllResults());
        foreach (['cash','bank','card_clearing','check_clearing','qr_clearing','receivable'] as $account) {
            $lines=$this->db->table('journal_entry_lines')->where('account_id',$account)->get()->getResultArray();
            $this->assertEquals(array_sum(array_column($lines,'debit')),array_sum(array_column($lines,'credit')));
        }
        $this->db->table('company_settings')->insert(['id'=>'revenue','company_id'=>'a','key'=>'account_revenue','value'=>'revenue']);
        $this->db->table('sales')->where('id','one')->update(['subtotal'=>100,'tax_total'=>0,'sale_number'=>'F-1','issue_date'=>'2026-10-10']);
        $accounting=new AccountingService();$accounting->syncSale('a','one','user');
        $this->db->table('sales')->where('id','one')->update(['status'=>'cancelled']);
        $accounting->reverseCancelledSale('a','one','user');$accounting->reverseCancelledSale('a','one','user');
        $this->service()->refresh('one');
        $this->assertSame(1,$this->db->table('journal_entries')->where('reference_type','sale_cancellation')->countAllResults());
        $this->assertEquals(0,$this->db->table('sales_receivables')->where('id','one')->get()->getRowArray()['balance_amount']);
        $lines=$this->db->table('journal_entry_lines')->where('account_id','receivable')->get()->getResultArray();
        $this->assertEquals(array_sum(array_column($lines,'debit')),array_sum(array_column($lines,'credit')));

    }
    public function testTransferDoesNotApplyBeforeExplicitConfirmationAndRetryIsSafe(): void
    {
        $r=$this->service()->create('a','user',$this->payload([['payment_method'=>'cash','amount'=>40],['payment_method'=>'transfer','amount'=>30,'external_reference'=>'BANK-1'],['payment_method'=>'transfer','amount'=>30,'external_reference'=>'BANK-2']]),fn()=>'REC-1');
        $this->assertSame('partial',$r['status']);
        $this->assertSame(1,$this->db->table('integration_outbox')->countAllResults());
        $this->assertSame(1,$this->db->table('sale_payments')->countAllResults());
        $this->assertEquals(60,$this->db->table('sales_receivables')->where('id','one')->get()->getRowArray()['balance_amount']);
        $lines=json_decode($r['payment_details'],true);
        $this->service()->confirm('a',$r['id'],'verifier','Verified BANK-1',$lines[1]['id']);
        $this->service()->confirm('a',$r['id'],'verifier','Retry BANK-1',$lines[1]['id']);
        $this->assertSame(2,$this->db->table('cash_movements')->countAllResults());
        $this->assertSame(2,$this->db->table('integration_outbox')->countAllResults());
        $this->assertEquals(30,$this->db->table('sales_receivables')->where('id','one')->get()->getRowArray()['balance_amount']);
        $this->service()->confirm('a',$r['id'],'verifier','Verified BANK-2',$lines[2]['id']);
        $this->assertSame(3,$this->db->table('sale_payments')->countAllResults());
        $events=$this->db->table('integration_outbox')->get()->getResultArray();
        $this->assertCount(3,$events);
        $this->assertSame(3,count(array_unique(array_column($events,'operation_key'))));
        $this->assertEquals(100,array_sum(array_map(static fn($event)=>json_decode($event['payload'],true)['amount'],$events)));
        $this->assertEquals(0,$this->db->table('sales_receivables')->where('id','one')->get()->getRowArray()['balance_amount']);
        $pending=$this->service()->create('a','user',$this->payload([['payment_method'=>'cash','amount'=>10],['payment_method'=>'transfer','amount'=>90,'reference'=>'BANK-3']],[['receivable_id'=>'two','applied_amount'=>100]]),fn()=>'REC-2');
        $this->service()->reverse('a',$pending['id'],'user','Cancel partial receipt');
        $this->assertEquals(100,$this->db->table('sales_receivables')->where('id','two')->get()->getRowArray()['balance_amount']);
        $this->assertEquals(100,$this->db->table('cash_movements')->selectSum('amount')->get()->getRowArray()['amount']);
    }
    public function testRepeatedApplicationIsRejectedAtomically(): void
    {
        try { $this->service()->create('a','user',$this->payload([['payment_method'=>'cash','amount'=>160]],[['receivable_id'=>'one','applied_amount'=>80],['receivable_id'=>'one','applied_amount'=>80]]),fn()=>'REC-1'); $this->fail('Must reject duplicates'); }
        catch (RuntimeException $e) { $this->assertStringContainsString('repetir',$e->getMessage()); }
        $this->assertSame(0,$this->db->table('sales_receipts')->countAllResults());
    }
    public function testConfirmationRechecksBalanceAfterAnotherCollection(): void
    {
        $r=$this->service()->create('a','user',$this->payload([['payment_method'=>'transfer','amount'=>100,'reference'=>'BANK']]),fn()=>'REC-1');
        $this->service()->create('a','user',$this->payload(),fn()=>'REC-2');
        try { $this->service()->confirm('a',$r['id'],'user','Verified'); $this->fail('Must reject paid document'); } catch (RuntimeException $e) { $this->assertStringContainsString('admite',$e->getMessage()); }
        $this->assertSame('pending',$this->db->table('sales_receipts')->where('id',$r['id'])->get()->getRowArray()['status']);
    }
    public function testCurrencyMismatchRejected(): void
    {
        $this->db->table('sales')->where('id','one')->update(['currency_code'=>'USD']);
        $this->expectExceptionMessage('monedas distintas');
        $this->service()->create('a','user',$this->payload(),fn()=>'REC-1');
    }
    public function testMovementFailureRollsBackEveryWrite(): void
    {
        try { $this->service(true)->create('a','user',$this->payload(),fn()=>'REC-1'); $this->fail('Must rollback'); } catch (RuntimeException $e) { $this->assertStringContainsString('movimiento',$e->getMessage()); }
        $this->assertSame(0,$this->db->table('sales_receipts')->countAllResults());
        $this->assertSame(0,$this->db->table('sale_payments')->countAllResults());
        $this->assertEquals(100,$this->db->table('sales_receivables')->where('id','one')->get()->getRowArray()['balance_amount']);
    }
    public function testLaterPaymentFailureAlsoRollsBackEarlierPaymentEvent(): void
    {
        $this->db->table('company_settings')->where('key','account_card_clearing')->delete();
        try {
            $this->service()->create('a','user',$this->payload([['payment_method'=>'cash','amount'=>50],['payment_method'=>'card','amount'=>50]]),fn()=>'REC-FAIL');
            $this->fail('The second payment must fail');
        } catch (RuntimeException $e) { $this->assertStringContainsString('cuenta',$e->getMessage()); }
        foreach (['cash_movements','sales_receipts','integration_outbox','integration_deliveries'] as $table) $this->assertSame(0,$this->db->table($table)->countAllResults());
        $this->assertEquals(100,$this->db->table('sales_receivables')->where('id','one')->get()->getRowArray()['balance_amount']);
    }

    public function testAccountingFailureRollsBackMovementsAndBalances(): void
    {
        $this->db->table('company_settings')->where('key','account_receivable')->delete();
        try { $this->service()->create('a','user',$this->payload(),fn()=>'REC-1'); $this->fail('Must rollback'); } catch (RuntimeException $e) { $this->assertStringContainsString('cuenta',$e->getMessage()); }
        $this->assertSame(0,$this->db->table('cash_movements')->countAllResults());
        $this->assertSame(0,$this->db->table('sale_payments')->countAllResults());
        $this->assertSame(0,$this->db->table('sales_receipts')->countAllResults());
        $this->assertSame(0,$this->db->table('collection_requests')->countAllResults());
        $this->db->table('company_settings')->insert(['id'=>'receivable','company_id'=>'a','key'=>'account_receivable','value'=>'receivable']);
        foreach ([true,false] as $movementFailure) {
            try {$this->service($movementFailure,$movementFailure ? null : 'other-owner')->create('a','user',$this->payload(),fn()=>'FAIL');$this->fail('Must not collect');}
            catch(RuntimeException $e) {$this->assertNotEmpty($e->getMessage());}
        }
        $this->assertSame(0,$this->db->table('cash_movements')->countAllResults());
        $this->db->table('companies')->insert(['id'=>'b','currency_code'=>'ARS']);
        $this->db->table('customers')->insert(['id'=>'foreign','company_id'=>'b','active'=>1]);
        $this->db->table('cash_payment_gateways')->insert(['id'=>'foreign','company_id'=>'b','active'=>1]);
        foreach ([
            $this->payload([['payment_method'=>'card','amount'=>100,'gateway_id'=>'foreign']]),
            ['request_key'=>app_uuid(),'receipt_kind'=>'advance','customer_id'=>'foreign','payments'=>[['payment_method'=>'cash','amount'=>10]]],
            array_merge($this->payload(),['currency_code'=>'USD']),
            $this->payload([['payment_method'=>'cash','amount'=>'-10']]),
            ['request_key'=>app_uuid(),'payments'=>[['payment_method'=>'transfer','amount'=>100]],'items'=>[['receivable_id'=>'one','applied_amount'=>100]]],
        ] as $invalid) {
            try {$this->service()->create('a','user',$invalid,fn()=>'BAD');$this->fail('Must reject');} catch(RuntimeException $e) {$this->assertNotEmpty($e->getMessage());}
        }
        try {$this->service()->create('b','user',$this->payload(),fn()=>'FOREIGN');$this->fail('Must isolate companies');} catch(RuntimeException $e) {$this->assertStringContainsString('disponible',$e->getMessage());}
        $mapping=array_combine(array_keys(\App\Libraries\CollectionAccountingService::ACCOUNTS),array_keys(\App\Libraries\CollectionAccountingService::ACCOUNTS));
        $this->service()->configureAccounts('a',$mapping);
        try {$this->service()->configureAccounts('a',array_merge($mapping,['bank'=>'cash']));$this->fail('Accounts must be distinct');}catch(RuntimeException $e){$this->assertStringContainsString('diferente',$e->getMessage());}
        $this->assertSame('bank',$this->db->table('company_settings')->where('key','account_bank')->get()->getRowArray()['value']);
        // Exercise the actual migration against an applied historical receipt.
        $this->db->table('sales_receipts')->insert(['id'=>'legacy','company_id'=>'a','customer_id'=>'customer','total_amount'=>10,'status'=>'applied']);
        $this->db->table('sales_receipt_items')->insert(['id'=>'legacy-item','sales_receipt_id'=>'legacy','applied_amount'=>10]);
        $migration=new \App\Database\Migrations\ExtendCollections(\Config\Database::forge('tests'));
        $migration->down();$migration->up();
        $this->assertEquals(10,$this->db->table('sales_receipts')->where('id','legacy')->get()->getRowArray()['received_amount']);
        $this->assertEquals(10,$this->db->table('sales_receipt_items')->where('id','legacy-item')->get()->getRowArray()['settled_amount']);
        $this->assertEquals(1,$this->db->table('sales_receipts')->where('id','legacy')->get()->getRowArray()['processing_version']);

        helper(['auth','url']);
        $html=view('sales/forms/receipt',[
            'pageTitle'=>'Nuevo recibo','companyId'=>'a','isPopup'=>true,'formAction'=>'/ventas/cobranzas',
            'currencyCode'=>'ARS','customers'=>[['id'=>'customer','name'=>'Cliente']],
            'receivables'=>[['id'=>'one','customer_id'=>'customer','customer_name'=>'Cliente','document_number'=>'F-1','balance_amount'=>100]],
            'gateways'=>[],'checks'=>[],
        ],['saveData'=>false]);
        $this->assertStringContainsString('name="request_key"',$html);
        $this->assertStringContainsString('data-collection-items',$html);
        $this->assertStringContainsString('value="advance"',$html);

    }
    public function testGroupedPartialCollectionKeepsCorrectBalances(): void
    {
        $payload=['request_key'=>app_uuid(),'receipt_kind'=>'advance','customer_id'=>'customer','payments'=>[['payment_method_id'=>'cash','base_amount'=>100]]];
        $receipt=$this->service()->create('a','user',$payload,fn()=>'ADV-1');
        $retry=$this->service()->create('a','user',$payload,fn()=>'MUST-NOT-BE-USED');
        $this->assertSame($receipt['id'],$retry['id']);
        $this->assertTrue($retry['_replayed']);
        $this->assertEquals(100,$this->service()->availableAdvance($receipt));
        $this->assertSame(0,$this->db->table('sale_payments')->countAllResults());
        $application=['request_key'=>app_uuid(),'items'=>[['receivable_id'=>'one','applied_amount'=>20],['receivable_id'=>'two','applied_amount'=>40]]];
        $this->service()->applyAdvance('a',$receipt['id'],'user',$application);
        $this->service()->applyAdvance('a',$receipt['id'],'user',$application);
        $this->assertEquals(80,$this->db->table('sales_receivables')->where('id','one')->get()->getRowArray()['balance_amount']);
        $this->assertEquals(60,$this->db->table('sales_receivables')->where('id','two')->get()->getRowArray()['balance_amount']);
        $this->assertSame(1,$this->db->table('cash_movements')->countAllResults());
        $this->assertEquals(40,$this->service()->availableAdvance($receipt));
        foreach ([
            ['request_key'=>app_uuid(),'items'=>[['receivable_id'=>'one','applied_amount'=>90]]],
            ['request_key'=>app_uuid(),'items'=>[['receivable_id'=>'one','applied_amount'=>41]]],
            ['request_key'=>app_uuid(),'items'=>[]],
            ['request_key'=>app_uuid(),'items'=>[['receivable_id'=>'foreign','applied_amount'=>10]]],
            ['request_key'=>$application['request_key'],'items'=>[['receivable_id'=>'one','applied_amount'=>1]]],
        ] as $invalid) {
            try {$this->service()->applyAdvance('a',$receipt['id'],'user',$invalid);$this->fail('Must reject invalid application');}catch(RuntimeException $e){$this->assertNotEmpty($e->getMessage());}
        }
        $refund=['request_key'=>app_uuid(),'amount'=>40,'payment_method'=>'cash','reason'=>'Customer refund'];
        $this->service()->refundAdvance('a',$receipt['id'],'user',$refund);
        $this->service()->refundAdvance('a',$receipt['id'],'user',$refund);
        $fresh=$this->db->table('sales_receipts')->where('id',$receipt['id'])->get()->getRowArray();
        $this->assertEquals(0,$this->service()->availableAdvance($fresh));
        $this->assertEquals(60,$this->db->table('cash_movements')->selectSum('amount')->get()->getRowArray()['amount']);
        $lines=$this->db->table('journal_entry_lines')->where('account_id','customer_advances')->get()->getResultArray();
        $this->assertEquals(array_sum(array_column($lines,'debit')),array_sum(array_column($lines,'credit')));
        $this->assertSame(1,$this->db->table('sales_receipts')->countAllResults());
    }
    public function testMetadataSurvivesModelAndPendingDoesNotCountAsPaid(): void
    {
        $parser=new PaymentIntegrityService();
        $rows=$parser->parse([['payment_method'=>'transfer','amount'=>10,'gateway_id'=>'gateway','external_reference'=>'reference']]);
        $id=(new \App\Models\SalePaymentModel())->insert($rows[0]+['sale_id'=>'one'],true);
        $row=(new \App\Models\SalePaymentModel())->find($id);
        $this->assertSame('gateway',$row['gateway_id']);$this->assertSame('reference',$row['external_reference']);
        $this->assertEquals(0,PaymentIntegrityService::paid([$row]));
        $this->expectExceptionMessage('Desglosa');$parser->parse([['payment_method'=>'mixed','amount'=>10]]);
    }
    public function testForeignCompanyCannotReverseReceipt(): void
    {
        $r=$this->service()->create('a','user',$this->payload(),fn()=>'REC-1');
        $this->db->table('companies')->insert(['id'=>'b','currency_code'=>'ARS']);
        $this->expectExceptionMessage('esta empresa');$this->service()->reverse('b',$r['id'],'user','Invalid');
    }
    public function testPendingReceiptCanBeCancelledWithoutMovingFunds(): void
    {
        $r=$this->service()->create('a','user',$this->payload([['payment_method'=>'transfer','amount'=>100,'reference'=>'BANK']]),fn()=>'REC-1');
        $this->service()->reverse('a',$r['id'],'user','Transferencia no recibida');
        $this->assertSame(0,$this->db->table('cash_movements')->countAllResults());
        $this->assertEquals(100,$this->db->table('sales_receivables')->where('id','one')->get()->getRowArray()['balance_amount']);
        $this->expectExceptionMessage('pendiente');$this->service()->confirm('a',$r['id'],'user','Too late');
    }
    public function testSaleTransferConfirmationUpdatesBalanceExactlyOnce(): void
    {
        $this->db->table('sales')->where('id','one')->update(['status'=>'returned_partial']);
        $this->db->table('sale_items')->insert(['id'=>'item','sale_id'=>'one','quantity'=>10,'returned_quantity'=>6,'line_total'=>100]);
        $row=(new PaymentIntegrityService())->parse([['payment_method'=>'transfer','amount'=>40,'reference'=>'BANK']])[0];
        $id=(new \App\Models\SalePaymentModel())->insert($row+['sale_id'=>'one'],true);
        $this->service()->confirmSalePayment('a','one',$id,'verifier','Bank verified');
        $this->service()->confirmSalePayment('a','one',$id,'verifier','Retry');
        $this->assertSame(1,$this->db->table('cash_movements')->countAllResults());
        $this->assertEquals(0,$this->db->table('sales_receivables')->where('id','one')->get()->getRowArray()['balance_amount']);
        $this->assertSame('paid',$this->db->table('sales')->where('id','one')->get()->getRowArray()['payment_status']);
        $this->assertSame(1,$this->db->table('journal_entries')->where('reference_type','sale_payment')->countAllResults());
        $this->assertEquals(40,$this->db->table('journal_entry_lines')->where('account_id','bank')->selectSum('debit')->get()->getRowArray()['debit']);
        $this->db->table('company_settings')->insert(['id'=>'revenue','company_id'=>'a','key'=>'account_revenue','value'=>'revenue']);
        $this->db->table('sales')->where('id','two')->update(['subtotal'=>100,'tax_total'=>0,'sale_number'=>'F-2','issue_date'=>'2026-10-10','created_by'=>'user']);
        $cash=(new PaymentIntegrityService())->parse([['payment_method'=>'card','amount'=>100]])[0];
        $cashId=(new \App\Models\SalePaymentModel())->insert($cash+['sale_id'=>'two'],true);
        $accounting=new AccountingService();
        $accounting->syncSale('a','two','user');$accounting->syncSale('a','two','user');
        $this->assertSame(1,$this->db->table('journal_entries')->where('reference_type','sale')->where('reference_id','two')->countAllResults());
        $this->assertSame(1,$this->db->table('journal_entries')->where('reference_type','sale_payment')->where('reference_id',$cashId)->countAllResults());

        $this->service()->settleSalePayment('a','two',$cashId,'user','BANK-100');
        $this->service()->settleSalePayment('a','two',$cashId,'user','BANK-100');
        $this->assertSame(1,$this->db->table('journal_entries')->where('reference_type','payment_settlement')->countAllResults());
        $this->db->table('sale_items')->insert(['id'=>'return-item','sale_id'=>'two','quantity'=>10,'returned_quantity'=>4,'line_total'=>100,'tax_rate'=>0]);
        $this->db->table('sales')->where('id','two')->update(['status'=>'returned_partial']);
        $this->db->table('sale_returns')->insert(['id'=>'return-1','sale_id'=>'two','status'=>'confirmed','total'=>40,'return_number'=>'NC-1']);
        $this->db->table('sale_return_items')->insert(['id'=>'ri-1','sale_return_id'=>'return-1','sale_item_id'=>'return-item','line_total'=>40]);
        $accounting->syncSaleReturn('a','return-1','user');
        $accounting->syncSaleReturn('a','return-1','user');
        $this->service()->creditSaleReturn('a','two','return-1','user');
        $this->service()->creditSaleReturn('a','two','return-1','user');
        $credit=$this->db->table('sales_receipts')->where('receipt_kind','return_credit')->get()->getRowArray();
        $this->assertEquals(40,$this->service()->availableAdvance($credit));
        $this->assertEquals(60,$this->db->table('sales')->where('id','two')->get()->getRowArray()['paid_total']);
        $this->assertSame(1,$this->db->table('sales_receipts')->where('receipt_kind','return_credit')->countAllResults());
        $this->assertSame(1,$this->db->table('journal_entries')->where('reference_type','sale_return')->countAllResults());
        $this->assertSame(1,$this->db->table('cash_movements')->countAllResults(), 'Return credit must not move funds');
        $refund=['request_key'=>app_uuid(),'amount'=>40,'payment_method'=>'transfer','reason'=>'BANK-REFUND'];
        $this->service()->refundAdvance('a',$credit['id'],'user',$refund);
        $this->service()->refundAdvance('a',$credit['id'],'user',$refund);
        $fresh=$this->db->table('sales_receipts')->where('id',$credit['id'])->get()->getRowArray();
        $this->assertEquals(0,$this->service()->availableAdvance($fresh));
        $this->assertSame(2,$this->db->table('cash_movements')->countAllResults());

    }
    public function testWebAndApiParsersPreserveSameMetadataAndIgnoreForgedStatus(): void
    {
        $payments=[['payment_method'=>'transfer','amount'=>10,'gateway_id'=>'gateway','external_reference'=>'bank','status'=>'confirmed']];
        $web=new ReflectionMethod(\App\Controllers\SalesController::class,'parseSalePayments');$web->setAccessible(true);
        $api=new ReflectionMethod(\App\Controllers\Api\V1\SalesController::class,'parsePayments');$api->setAccessible(true);
        $a=$web->invoke(new \App\Controllers\SalesController(),['payments'=>$payments]);
        $b=$api->invoke(new \App\Controllers\Api\V1\SalesController(),$payments);
        $this->assertSame($a,$b);$this->assertSame('pending',$a[0]['status']);$this->assertSame('gateway',$a[0]['gateway_id']);
    }
    public function testForeignGatewayIsRejected(): void
    {
        $this->db->table('cash_payment_gateways')->insert(['id'=>'gateway','company_id'=>'b','active'=>1]);
        $this->expectExceptionMessage('pasarela');
        $this->service()->create('a','user',$this->payload([['payment_method'=>'card','amount'=>100,'gateway_id'=>'gateway']]),fn()=>'REC-1');
    }
    public function testReversalFailureDoesNotRemovePaymentHistory(): void
    {
        $r=$this->service()->create('a','user',$this->payload(),fn()=>'REC-1');
        $this->db->table('cash_sessions')->where('id','session')->update(['status'=>'closed']);
        try {$this->service()->reverse('a',$r['id'],'user','Test');$this->fail('Needs open session');}
        catch(RuntimeException $e){$this->assertStringContainsString('sesión',$e->getMessage());}
        $this->assertSame('applied',$this->db->table('sales_receipts')->where('id',$r['id'])->get()->getRowArray()['status']);
        $this->assertSame(1,$this->db->table('sale_payments')->where('status','confirmed')->countAllResults());
    }
    public function testConfiguredCardRequiringConfirmationDoesNotCollectUntilVerified(): void
    {
        $this->db->table('company_payment_methods')->where('id','card')->update(['requires_confirmation'=>1,'name'=>'Tarjeta Naranja','percentage'=>7]);
        $receipt=$this->service()->create('a','user',$this->payload([['payment_method'=>'card','amount'=>100]]),fn()=>'REC-CARD');
        $this->assertSame('pending',$receipt['status']);$this->assertEquals(100,$receipt['total_amount']);
        $this->assertSame(0,$this->db->table('cash_movements')->countAllResults());$this->assertSame(0,$this->db->table('sale_payments')->countAllResults());
        $line=json_decode($receipt['payment_details'],true)[0];$this->assertSame('Tarjeta Naranja',$line['payment_method_name']);
        $this->db->table('company_payment_methods')->where('id','card')->update(['name'=>'Otro nombre','percentage'=>20]);
        $this->service()->confirm('a',$receipt['id'],'verifier','Verificado con comprobante',$line['id']);
        $this->service()->confirm('a',$receipt['id'],'verifier','Reintento',$line['id']);
        $this->assertSame(1,$this->db->table('cash_movements')->countAllResults());
        $paid=$this->db->table('sale_payments')->get()->getRowArray();$this->assertEquals(100,$paid['amount']);$this->assertSame('Tarjeta Naranja',$paid['payment_method_name']);
        $this->assertEquals(0,$this->db->table('sales_receivables')->where('id','one')->get()->getRowArray()['balance_amount']);
    }
    public function testConfiguredAdvanceRetainsItsMethodAndNeverCreatesASale(): void
    {
        $payload=['request_key'=>app_uuid(),'receipt_kind'=>'advance','customer_id'=>'customer','payments'=>[['payment_method_id'=>'card','base_amount'=>50]]];
        $receipt=$this->service()->create('a','user',$payload,fn()=>'ADV-CARD');
        $this->assertEquals(50,$this->service()->availableAdvance($receipt));
        $this->assertSame(2,$this->db->table('sales')->countAllResults());$this->assertSame(0,$this->db->table('sale_payments')->countAllResults());
        $this->assertSame('card',json_decode($receipt['payment_details'],true)[0]['payment_method_id']);
        $this->assertSame(1,$this->db->table('cash_movements')->countAllResults());
    }

}
