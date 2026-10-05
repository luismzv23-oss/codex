<?php
use App\Libraries\AccountingService;
use App\Libraries\LibroIvaDigitalService;
use CodeIgniter\Test\CIUnitTestCase;

final class SaleAccountingTest extends CIUnitTestCase
{
    private array $tables = [];
    protected function setUp(): void
    {
        parent::setUp(); helper('app');
        $schemas = [
            'sales'=>'id TEXT PRIMARY KEY, company_id TEXT, status TEXT, sale_number TEXT, issue_date TEXT, document_type_id TEXT, subtotal REAL, tax_total REAL, total REAL, payment_surcharge_amount REAL, created_by TEXT',
            'sale_items'=>'id TEXT, sale_id TEXT, line_number INTEGER, quantity REAL, subtotal REAL, tax_total REAL, tax_rate REAL, line_total REAL',
            'sales_document_types'=>'id TEXT, name TEXT, code TEXT',
            'journal_entries'=>'id TEXT PRIMARY KEY, company_id TEXT, entry_number INTEGER, entry_date TEXT, description TEXT, reference_type TEXT, reference_id TEXT, status TEXT, total_debit REAL, total_credit REAL, user_id TEXT, posted_at TEXT, created_at TEXT',
            'journal_entry_lines'=>'id TEXT, journal_entry_id TEXT, account_id TEXT, description TEXT, debit REAL, credit REAL, created_at TEXT',
            'accounts'=>'id TEXT, company_id TEXT, code TEXT, name TEXT, account_type TEXT, is_group INTEGER, level INTEGER, active INTEGER',
        ];
        $db=db_connect();
        foreach($schemas as $name=>$schema){$db->query('CREATE TABLE '.$db->prefixTable($name).' ('.$schema.')');$this->tables[]=$name;}
        $db->table('sales')->insert(['id'=>'sale','company_id'=>'demo','status'=>'confirmed','sale_number'=>'FCB-5','issue_date'=>'2026-10-03 09:56:26','subtotal'=>727.27,'tax_total'=>152.73,'total'=>885.76,'payment_surcharge_amount'=>5.76,'created_by'=>'seller']);
        $db->table('sale_items')->insert(['id'=>'item','sale_id'=>'sale','line_number'=>1,'quantity'=>1,'subtotal'=>727.27,'tax_total'=>152.73,'tax_rate'=>21,'line_total'=>880]);
        foreach(['receivable'=>'asset','revenue'=>'revenue','iva_debito'=>'liability'] as $id=>$type){$db->table('accounts')->insert(['id'=>$id,'company_id'=>'demo','code'=>$id,'name'=>$id,'account_type'=>$type,'is_group'=>0,'level'=>1,'active'=>1]);}
    }
    protected function tearDown(): void
    {
        foreach(array_reverse($this->tables) as $t){db_connect()->query('DROP TABLE '.db_connect()->prefixTable($t));}
        parent::tearDown();
    }
    private function post(): array
    {
        return (new AccountingService())->journalFromSale('demo',db_connect()->table('sales')->where('id','sale')->get()->getRowArray(),['receivable'=>'receivable','revenue'=>'revenue','iva_debito'=>'iva_debito']);
    }
    public function testRepeatedConfirmationCreatesOneJournalWithFiscalAmounts(): void
    {
        $this->assertTrue($this->post()['ok']);
        $this->assertTrue($this->post()['already_synced']);
        $db=db_connect();$this->assertSame(1,$db->table('journal_entries')->countAllResults());
        $lines=$db->table('journal_entry_lines')->get()->getResultArray();
        $credits=array_column($lines,'credit','account_id');
        $this->assertSame(732.03,(float)$credits['revenue']);$this->assertSame(153.73,(float)$credits['iva_debito']);
        $this->assertEquals(885.76,array_sum(array_column($lines,'debit')));
    }
    public function testCompanyCannotPostForeignSale(): void
    {
        $sale=db_connect()->table('sales')->get()->getRowArray();
        $this->assertFalse((new AccountingService())->journalFromSale('other',$sale,[])['ok']);
        $this->assertSame(0,db_connect()->table('journal_entries')->countAllResults());
    }
    public function testVatReportIncludesLastDayAndMatchesReceipt(): void
    {
        $r=(new LibroIvaDigitalService())->ventasReport('demo','2026-10-03','2026-10-03');
        $this->assertSame(1,$r['count']);$s=$r['records'][0];
        $this->assertSame(732.03,$s['neto_gravado']);$this->assertSame(153.73,$s['iva_21']);$this->assertSame(885.76,$s['total']);
        $this->assertSame(153.73,$s['alicuotas'][0]['iva']);
    }
    public function testReportsExcludeDraftsAndFutureEntries(): void
    {
        $this->post();$service=new AccountingService();
        $lines=[['account_id'=>'receivable','debit'=>100,'credit'=>0],['account_id'=>'revenue','debit'=>0,'credit'=>100]];
        $service->createJournalEntry('demo',['status'=>'draft','entry_date'=>'2026-10-03','user_id'=>'seller'],$lines);
        $service->createJournalEntry('demo',['status'=>'posted','entry_date'=>'2027-01-01','user_id'=>'seller'],$lines);
        $trial=$service->trialBalance('demo','2026-10-03');
        $this->assertSame(885.76,$trial['total_debit']);$this->assertTrue($trial['balanced']);
        $income=$service->incomeStatement('demo','2026-10-01','2026-10-03');
        $this->assertSame(732.03,$income['revenue']['total']);
    }
    public function testMixedTaxRatesMatchAccountingBookAndArca(): void
    {
        $db = db_connect();
        $db->table('sales')->where('id', 'sale')->update(['subtotal'=>200, 'tax_total'=>31.50, 'total'=>241.50, 'payment_surcharge_amount'=>10]);
        $db->table('sale_items')->where('id', 'item')->update(['subtotal'=>100, 'tax_total'=>21, 'tax_rate'=>21, 'line_total'=>121]);
        $db->table('sale_items')->insert(['id'=>'second', 'sale_id'=>'sale', 'line_number'=>2, 'quantity'=>1, 'subtotal'=>100, 'tax_total'=>10.5, 'tax_rate'=>10.5, 'line_total'=>110.50]);
        $this->assertTrue($this->post()['ok']);
        $book = (new LibroIvaDigitalService())->ventasReport('demo', '2026-10-03', '2026-10-03')['records'][0];
        $credits = array_column($db->table('journal_entry_lines')->get()->getResultArray(), 'credit', 'account_id');
        $this->assertEquals($book['neto_gravado'], $credits['revenue']);
        $this->assertEquals($book['iva_21'], $credits['iva_debito']);
        $this->assertEquals(241.50, round($book['neto_gravado'] + $book['iva_21'], 2));
        $this->assertCount(2, $book['alicuotas']);
        $sale = $db->table('sales')->get()->getRowArray();
        $items = $db->table('sale_items')->orderBy('line_number')->get()->getResultArray();
        [$sale, $items] = \App\Libraries\PaymentFiscalPolicy::forSale($sale, $items);
        $builder = new ReflectionMethod(\App\Libraries\ArcaService::class, 'buildPayloadPreview');
        $builder->setAccessible(true);
        foreach (['wsfev1', 'wsmtxca'] as $service) {
            $payload = $builder->invoke(new \App\Libraries\ArcaService(), $sale, ['afip_code'=>6], [], [], $items, [], ['slug'=>$service]);
            $vat = $payload['imp_iva'] ?? array_sum(array_column($payload['iva_subtotals'], 'importe'));
            $this->assertEquals($book['neto_gravado'], $payload['imp_neto']);
            $this->assertEquals($book['iva_21'], $vat);
            $this->assertEquals($book['total'], $payload['imp_total']);
        }
    }

    public function testAccountingFailureLeavesNoPartialJournal(): void
    {
        $sale = db_connect()->table('sales')->get()->getRowArray();
        $result = (new AccountingService())->journalFromSale('demo', $sale, ['receivable'=>'receivable', 'revenue'=>'revenue']);
        $this->assertFalse($result['ok']);
        $this->assertSame(0, db_connect()->table('journal_entries')->countAllResults());
        $this->assertSame(0, db_connect()->table('journal_entry_lines')->countAllResults());
    }

    public function testOuterRollbackAlsoRollsBackSuccessfulAccounting(): void
    {
        $db = db_connect();
        $db->transBegin();
        $this->assertTrue($this->post()['ok']);
        $this->assertSame(1, $db->table('journal_entries')->countAllResults());
        $db->transRollback();
        $this->assertSame(0, $db->table('journal_entries')->countAllResults());
        $this->assertSame(0, $db->table('journal_entry_lines')->countAllResults());
        $this->assertTrue($this->post()['ok']);
    }

}
