<?php
use CodeIgniter\Test\CIUnitTestCase;

final class AccountingDashboardViewTest extends CIUnitTestCase
{
    public function testAllScreensRenderWithTheirOriginalDataAndActions(): void
    {
        helper(['app', 'auth', 'url']);
        $company = ['id'=>'demo', 'name'=>'Empresa Demo', 'currency_code'=>'ARS'];
        $account = ['id'=>'cash', 'code'=>'1.1', 'name'=>'Caja', 'account_type'=>'asset', 'is_group'=>0, 'level'=>1, 'accepts_entries'=>1, 'opening_balance'=>0, 'balance'=>1234.56, 'total_debit'=>1234.56, 'total_credit'=>0];
        $part = ['accounts'=>[$account], 'total'=>1234.56];
        $statement = ['revenue'=>$part, 'expenses'=>['accounts'=>[], 'total'=>0], 'net_income'=>1234.56, 'profit_margin'=>100];
        $base = ['pageTitle'=>'Contabilidad', 'context'=>['company'=>$company], 'companies'=>[$company], 'selectedCompanyId'=>'demo', 'companyId'=>'demo', 'filters'=>['from'=>'2026-10-01','to'=>'2026-10-03']];
        $views = [
            'index'=>['accounts'=>[$account], 'overview'=>$statement+['posted'=>1, 'draft'=>2, 'difference'=>0]],
            'journal'=>['entries'=>[['id'=>'draft', 'entry_number'=>3, 'status'=>'draft', 'description'=>'Revisar', 'total_debit'=>1234.56]]],
            'ledger'=>['account'=>$account, 'ledger'=>['entries'=>[['entry_number'=>1, 'debit'=>1234.56, 'running_balance'=>1234.56]], 'final_balance'=>1234.56]],
            'trial_balance'=>['trial'=>['accounts'=>[$account], 'total_debit'=>1234.56, 'total_credit'=>1234.56, 'balanced'=>true], 'filters'=>['date'=>'2026-10-03']],
            'balance_sheet'=>['balance'=>['assets'=>$part,'liabilities'=>$part,'equity'=>$part+['net_income'=>0,'total_with_income'=>1234.56],'balanced'=>true], 'filters'=>['date'=>'2026-10-03']],
            'income_statement'=>['statement'=>$statement],
            'forms/account'=>['parents'=>[], 'formAction'=>'/contabilidad/cuentas'],
            'forms/entry'=>['accounts'=>[$account], 'formAction'=>'/contabilidad/asientos'],
        ];
        foreach ($views as $name=>$data) {
            $html = view('accounting/'.$name, array_merge($base,$data), ['saveData'=>false]);
            $this->assertStringContainsString('accounting-shell', $html, $name);
            $this->assertStringContainsString('Empresa Demo', $html, $name);
            $this->assertStringContainsString('accounting-dashboard.js', $html, $name);
            if (strpos($name,'forms/') !== 0) {
                $this->assertStringContainsString('id="accounting-filters"', $html, $name);
                $this->assertStringContainsString('1.234,56', $html, $name);
                $this->assertStringContainsString('data-accounting-table', $html, $name);
            } else {
                $this->assertStringContainsString('method="post"', $html, $name);
            }
            if ($name==='journal') $this->assertStringContainsString('draft/contabilizar', $html);
            if ($name==='forms/entry') $this->assertStringContainsString('lines[0][account_id]', $html);
        }
    }
}
