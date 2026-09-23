<?php

use App\Libraries\SalesCollectionService;
use App\Libraries\PaymentIntegrityService;
use App\Libraries\CashService;
use App\Libraries\AccountingService;
use CodeIgniter\Test\CIUnitTestCase;

final class SalesCollectionTest extends CIUnitTestCase
{
    private array $tables = [];
    protected function setUp(): void
    {
        parent::setUp(); helper('app');
        $this->db = db_connect('tests');
        $schemas = [
            'companies' => 'id, currency_code',
            'sales' => 'id, company_id, currency_code, status, total, paid_total, payment_status',
            'sale_items' => 'id, sale_id, quantity, line_total, returned_quantity',
            'sales_receivables' => 'id, company_id, sale_id, customer_id, document_number, total_amount, paid_amount, balance_amount, status',
            'sales_receipts' => 'id, company_id, customer_id, receipt_number, issue_date, currency_code, payment_method, payment_details, total_amount, reference, notes, status, created_by, created_at, updated_at, cash_register_id, cash_session_id, confirmed_by, confirmed_at, confirmation_note, reversed_by, reversed_at, reversal_reason',
            'sales_receipt_items' => 'id, sales_receipt_id, sales_receivable_id, sale_id, document_number, applied_amount, created_at, updated_at',
            'sale_payments' => 'id, sale_id, payment_method, amount, reference, status, paid_at, notes, created_at, updated_at, gateway_id, cash_check_id, external_reference, sales_receipt_id',
            'cash_sessions' => 'id, company_id, cash_register_id, status, opening_amount, expected_closing_amount, created_at, updated_at',
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
        $this->db->table('companies')->insert(['id'=>'a','currency_code'=>'ARS']);
        foreach (['one','two'] as $id) {
            $this->db->table('sales')->insert(['id'=>$id,'company_id'=>'a','currency_code'=>'ARS','status'=>'confirmed','total'=>100,'paid_total'=>0,'payment_status'=>'pending']);
            $this->db->table('sales_receivables')->insert(['id'=>$id,'company_id'=>'a','sale_id'=>$id,'customer_id'=>'customer','document_number'=>$id,'total_amount'=>100,'paid_amount'=>0,'balance_amount'=>100,'status'=>'pending']);
        }
        $this->db->table('cash_sessions')->insert(['id'=>'session','company_id'=>'a','cash_register_id'=>'register','status'=>'open','opening_amount'=>0]);
        foreach (['cash','receivable'] as $account) { $this->db->table('company_settings')->insert(['id'=>$account,'company_id'=>'a','key'=>'account_'.$account,'value'=>$account]); }
    }
    protected function tearDown(): void
    {
        foreach (array_reverse($this->tables) as $table) { $this->db->query('DROP TABLE '.$this->db->prefixTable($table)); }
        parent::tearDown();
    }
    private function service(bool $fail = false): SalesCollectionService
    {
        $cash = new class($fail) extends CashService {
            private bool $fail;
            public function __construct(bool $fail) { $this->fail = $fail; }
            public function activeSessionForChannel(string $companyId, string $channel = 'general'): ?array { return ['id'=>'session']; }
            public function registerMovement(array $data): ?string { return $this->fail ? null : parent::registerMovement($data); }
        };
        return new SalesCollectionService(new AccountingService(), $cash);
    }
    private function payload(array $payments = [], array $items = []): array
    {
        return ['payments'=>$payments ?: [['payment_method'=>'cash','amount'=>100]], 'items'=>$items ?: [['receivable_id'=>'one','applied_amount'=>100]]];
    }
    public function testMixedReceiptAndReversalPreservePaymentsAndReverseLedger(): void
    {
        $r = $this->service()->create('a','user',$this->payload([['payment_method'=>'cash','amount'=>40],['payment_method'=>'card','amount'=>60]]),fn()=>'REC-1');
        $this->assertSame('applied',$r['status']);
        $this->assertSame(2,$this->db->table('cash_movements')->countAllResults());
        $this->assertEquals(100,$this->db->table('journal_entries')->get()->getRowArray()['total_debit']);
        $this->service()->reverse('a',$r['id'],'user','Error de registro');
        $this->service()->reverse('a',$r['id'],'user','Reintento');
        $this->assertSame(4,$this->db->table('cash_movements')->countAllResults());
        $this->assertEquals(0,$this->db->table('cash_movements')->selectSum('amount')->get()->getRowArray()['amount']);
        $this->assertEquals(100,$this->db->table('sales_receivables')->where('id','one')->get()->getRowArray()['balance_amount']);
        $this->assertSame(2,$this->db->table('sale_payments')->where('status','reversed')->countAllResults());
        $this->assertSame(2,$this->db->table('journal_entries')->countAllResults());
        foreach (['cash','receivable'] as $account) {
            $lines=$this->db->table('journal_entry_lines')->where('account_id',$account)->get()->getResultArray();
            $this->assertEquals(array_sum(array_column($lines,'debit')),array_sum(array_column($lines,'credit')));
        }
    }
    public function testTransferDoesNotApplyBeforeExplicitConfirmationAndRetryIsSafe(): void
    {
        $r=$this->service()->create('a','user',$this->payload([['payment_method'=>'transfer','amount'=>100,'external_reference'=>'BANK-1']]),fn()=>'REC-1');
        $this->assertSame('pending',$r['status']);
        $this->assertSame(0,$this->db->table('sale_payments')->countAllResults());
        $this->assertSame(0,$this->db->table('cash_movements')->countAllResults());
        $this->service()->confirm('a',$r['id'],'verifier','Verificado en banco');
        $this->service()->confirm('a',$r['id'],'verifier','Reintento');
        $this->assertSame(1,$this->db->table('sale_payments')->countAllResults());
        $this->assertSame(1,$this->db->table('cash_movements')->countAllResults());
        $this->assertEquals(0,$this->db->table('sales_receivables')->where('id','one')->get()->getRowArray()['balance_amount']);
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
    public function testAccountingFailureRollsBackMovementsAndBalances(): void
    {
        $this->db->table('company_settings')->where('key','account_receivable')->delete();
        try { $this->service()->create('a','user',$this->payload(),fn()=>'REC-1'); $this->fail('Must rollback'); } catch (RuntimeException $e) { $this->assertStringContainsString('cuenta',$e->getMessage()); }
        $this->assertSame(0,$this->db->table('cash_movements')->countAllResults());
        $this->assertSame(0,$this->db->table('sale_payments')->countAllResults());
    }
    public function testGroupedPartialCollectionKeepsCorrectBalances(): void
    {
        $this->service()->create('a','user',$this->payload([['payment_method'=>'cash','amount'=>60]],[['receivable_id'=>'one','applied_amount'=>20],['receivable_id'=>'two','applied_amount'=>40]]),fn()=>'REC-1');
        $this->assertEquals(80,$this->db->table('sales_receivables')->where('id','one')->get()->getRowArray()['balance_amount']);
        $this->assertEquals(60,$this->db->table('sales_receivables')->where('id','two')->get()->getRowArray()['balance_amount']);
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
        $row=(new PaymentIntegrityService())->parse([['payment_method'=>'transfer','amount'=>40,'reference'=>'BANK']])[0];
        $id=(new \App\Models\SalePaymentModel())->insert($row+['sale_id'=>'one'],true);
        $this->service()->confirmSalePayment('a','one',$id,'verifier','Bank verified');
        $this->service()->confirmSalePayment('a','one',$id,'verifier','Retry');
        $this->assertSame(1,$this->db->table('cash_movements')->countAllResults());
        $this->assertEquals(60,$this->db->table('sales_receivables')->where('id','one')->get()->getRowArray()['balance_amount']);
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
}
