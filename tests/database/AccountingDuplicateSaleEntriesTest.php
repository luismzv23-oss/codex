<?php
use App\Libraries\AccountingDuplicateSaleEntries;
use CodeIgniter\Test\CIUnitTestCase;

final class AccountingDuplicateSaleEntriesTest extends CIUnitTestCase
{
    private array $tables = [];
    private string $backup;

    protected function setUp(): void
    {
        parent::setUp();
        $db = db_connect();
        foreach ([
            'journal_entries' => 'id TEXT, company_id TEXT, entry_number INTEGER, entry_date TEXT, description TEXT, reference_type TEXT, reference_id TEXT, status TEXT, total_debit REAL, total_credit REAL',
            'journal_entry_lines' => 'id TEXT, journal_entry_id TEXT, account_id TEXT, debit REAL, credit REAL',
            'fiscal_periods' => 'id TEXT, company_id TEXT, start_date TEXT, end_date TEXT, status TEXT',
            'sales' => 'id TEXT, company_id TEXT, sale_number TEXT',
            'accounts' => 'id TEXT, code TEXT, name TEXT',
        ] as $t => $schema) {
            $db->query('CREATE TABLE ' . $db->prefixTable($t) . ' (' . $schema . ')');
            $this->tables[] = $t;
        }
        $db->table('accounts')->insertBatch([['id' => 'rec', 'code' => '1.1.04', 'name' => 'Deudores'], ['id' => 'rev', 'code' => '4.1.01', 'name' => 'Ventas'], ['id' => 'iva', 'code' => '2.1.03', 'name' => 'IVA']]);
        $db->table('fiscal_periods')->insertBatch([
            ['id' => 'fa', 'company_id' => 'a', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open'],
            ['id' => 'fc', 'company_id' => 'c', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'closed'],
        ]);
        // s1: exact duplicate pair. s2: second entry differs. s3: single entry. s4: other company, exact pair. s5: closed period.
        $this->sale('a', 's1', [[1, 121], [2, 121]]);
        $this->sale('a', 's2', [[3, 121], [4, 100]]);
        $this->sale('a', 's3', [[5, 121]]);
        $this->sale('b', 's4', [[1, 50], [2, 50]]);
        $this->sale('c', 's5', [[1, 30], [2, 30]]);
        $this->backup = WRITEPATH . 'backups/test-duplicados-' . uniqid() . '.json';
    }

    private function sale(string $company, string $saleId, array $entries): void
    {
        $db = db_connect();
        $db->table('sales')->insert(['id' => $saleId, 'company_id' => $company, 'sale_number' => strtoupper($saleId)]);
        foreach ($entries as [$number, $total]) {
            $id = $company . '-' . $saleId . '-' . $number;
            $net = round($total / 1.21, 2);
            $db->table('journal_entries')->insert(['id' => $id, 'company_id' => $company, 'entry_number' => $number, 'entry_date' => '2026-10-01', 'reference_type' => 'sale', 'reference_id' => $saleId, 'status' => 'posted', 'total_debit' => $total, 'total_credit' => $total]);
            $db->table('journal_entry_lines')->insertBatch([
                ['id' => $id . '-1', 'journal_entry_id' => $id, 'account_id' => 'rec', 'debit' => $total, 'credit' => 0],
                ['id' => $id . '-2', 'journal_entry_id' => $id, 'account_id' => 'rev', 'debit' => 0, 'credit' => $net],
                ['id' => $id . '-3', 'journal_entry_id' => $id, 'account_id' => 'iva', 'debit' => 0, 'credit' => round($total - $net, 2)],
            ]);
        }
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->tables) as $t) {
            db_connect()->query('DROP TABLE ' . db_connect()->prefixTable($t));
        }
        if (is_file($this->backup)) {
            unlink($this->backup);
        }
        parent::tearDown();
    }

    private function entryCount(): int
    {
        return db_connect()->table('journal_entries')->countAllResults();
    }

    public function testAnalyzeOnlyReportsExactCopiesAndChangesNothing(): void
    {
        $analysis = (new AccountingDuplicateSaleEntries())->analyze();

        $this->assertSame(['a-s1-2', 'b-s4-2'], array_column($analysis['duplicates'], 'remove_id'));
        $this->assertSame([1, 1], array_column($analysis['duplicates'], 'keep_number'));
        $this->assertSame(['S2'], array_column($analysis['manual'], 'sale'));
        $this->assertSame(['S5'], array_column($analysis['skipped'], 'sale'));
        $this->assertSame(121.0, $analysis['impact']['a']['rec']['debit']);
        $this->assertSame('1.1.04', $analysis['impact']['a']['rec']['code']);
        $this->assertSame(9, $this->entryCount());
    }

    public function testApplyRemovesCopiesWritesBackupAndKeepsBooksBalanced(): void
    {
        $cleaner = new AccountingDuplicateSaleEntries();
        $done = $cleaner->apply($cleaner->analyze(), $this->backup);

        $this->assertSame(['entries' => 2, 'lines' => 6, 'backup' => $this->backup], $done);
        $this->assertSame(7, $this->entryCount());
        $db = db_connect();
        $this->assertSame(0, $db->table('journal_entries')->whereIn('id', ['a-s1-2', 'b-s4-2'])->countAllResults());
        $this->assertSame(0, $db->table('journal_entry_lines')->whereIn('journal_entry_id', ['a-s1-2', 'b-s4-2'])->countAllResults());
        // Untouched: non-identical pair, single entry, closed period.
        $this->assertSame(2, $db->table('journal_entries')->where('reference_id', 's2')->countAllResults());
        $this->assertSame(2, $db->table('journal_entries')->where('reference_id', 's5')->countAllResults());

        $backup = json_decode((string) file_get_contents($this->backup), true);
        $this->assertSame(['a-s1-2', 'b-s4-2'], array_column($backup['journal_entries'], 'id'));
        $this->assertCount(6, $backup['journal_entry_lines']);
        $this->assertSame([], $cleaner->analyze()['duplicates']);
    }

    public function testCompanyFilterLeavesOtherCompaniesUntouched(): void
    {
        $cleaner = new AccountingDuplicateSaleEntries();
        $cleaner->apply($cleaner->analyze('b'), $this->backup);

        $this->assertSame(2, db_connect()->table('journal_entries')->where('reference_id', 's1')->countAllResults());
        $this->assertSame(1, db_connect()->table('journal_entries')->where('reference_id', 's4')->countAllResults());
    }

    public function testNothingToApplyDoesNotWriteBackup(): void
    {
        $done = (new AccountingDuplicateSaleEntries())->apply(['duplicates' => []], $this->backup);

        $this->assertSame(['entries' => 0, 'lines' => 0, 'backup' => null], $done);
        $this->assertFileDoesNotExist($this->backup);
    }
}
