<?php

use App\Libraries\DashboardInsights;
use CodeIgniter\Test\CIUnitTestCase;

final class DashboardInsightsTest extends CIUnitTestCase
{
    private array $tables = [];
    private DashboardInsights $insights;

    protected function setUp(): void
    {
        parent::setUp();
        helper('url');
        $db = db_connect('tests');
        foreach ([
            'companies' => 'id TEXT PRIMARY KEY,name TEXT',
            'branches' => 'id TEXT PRIMARY KEY,company_id TEXT,name TEXT',
            'sales' => 'id TEXT PRIMARY KEY,company_id TEXT,branch_id TEXT,currency_code TEXT,status TEXT,document_code TEXT,issue_date TEXT,total REAL,cae TEXT,arca_status TEXT,sale_number TEXT',
            'sales_receivables' => 'id TEXT,company_id TEXT,sale_id TEXT,status TEXT,balance_amount REAL,due_date TEXT',
            'inventory_products' => 'id TEXT PRIMARY KEY,company_id TEXT,name TEXT,sku TEXT,active INTEGER,min_stock REAL',
            'inventory_warehouses' => 'id TEXT PRIMARY KEY,company_id TEXT,name TEXT,active INTEGER',
            'inventory_stock_levels' => 'id TEXT,company_id TEXT,product_id TEXT,warehouse_id TEXT,quantity REAL,reserved_quantity REAL,min_stock REAL',
        ] as $table => $schema) {
            $db->query('CREATE TABLE ' . $db->prefixTable($table) . ' (' . $schema . ')');
            $this->tables[] = $table;
        }
        foreach (['a', 'b'] as $id) {
            $db->table('companies')->insert(['id' => $id, 'name' => 'Empresa ' . $id]);
            $db->table('branches')->insert(['id' => $id, 'company_id' => $id, 'name' => 'Sucursal ' . $id]);
            $db->table('inventory_products')->insert(['id' => $id, 'company_id' => $id, 'name' => 'Producto ' . $id, 'sku' => $id, 'active' => 1, 'min_stock' => 5]);
            $db->table('inventory_warehouses')->insert(['id' => $id, 'company_id' => $id, 'name' => 'Depósito ' . $id, 'active' => 1]);
        }
        foreach ([['s1','a','ARS',100,'2026-09-15','confirmed'], ['s2','b','ARS',900,'2026-09-15','confirmed'], ['s3','a','USD',700,'2026-09-15','confirmed'], ['s4','a','ARS',500,'2026-09-15','draft'], ['s5','a','ARS',50,'2026-09-14','confirmed']] as [$id,$company,$currency,$total,$date,$status]) {
            $db->table('sales')->insert(['id'=>$id,'company_id'=>$company,'branch_id'=>$company,'currency_code'=>$currency,'total'=>$total,'issue_date'=>$date,'status'=>$status,'document_code'=>'TICKET','sale_number'=>$id,'arca_status'=>'pending','cae'=>'']);
        }
        $db->table('sales_receivables')->insert(['id'=>'r1','company_id'=>'a','sale_id'=>'s1','status'=>'partial','balance_amount'=>30,'due_date'=>'2000-01-01']);
        $db->table('sales_receivables')->insert(['id'=>'r2','company_id'=>'b','sale_id'=>'s2','status'=>'pending','balance_amount'=>900,'due_date'=>'2000-01-01']);
        // Two locations must be aggregated before checking the minimum.
        foreach ([['l1','a',4],['l2','a',4],['l3','b',1]] as [$id,$company,$quantity]) {
            $db->table('inventory_stock_levels')->insert(['id'=>$id,'company_id'=>$company,'product_id'=>$company,'warehouse_id'=>$company,'quantity'=>$quantity,'reserved_quantity'=>0,'min_stock'=>5]);
        }
        $this->insights = new DashboardInsights($db);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->tables) as $table) db_connect('tests')->query('DROP TABLE ' . db_connect('tests')->prefixTable($table));
        parent::tearDown();
    }

    public function testAdministratorCannotOverrideCompanyAndCurrenciesAreNotMixed(): void
    {
        $f = $this->insights->filters(['company_id'=>'b','from'=>'2026-09-15','to'=>'2026-09-15'], false, 'a');
        $this->assertSame('a', $f['company_id']);
        $data = $this->insights->load($f, false);
        $this->assertEquals(100, $data['summary']['amount']);
        $this->assertEquals(1, $data['summary']['documents']);
        $this->assertEquals(50, $data['previous']);
        $this->assertEquals(30, $data['balance']);
        $this->assertCount(0, $data['critical']);
        $this->assertEquals(2, $data['fiscal']);
        $html = view('dashboard/insights_panel', ['insights'=>$data,'filters'=>$f,'superadmin'=>false]);
        $this->assertStringContainsString('Ventas por sucursal', $html);
        $this->assertStringNotContainsString('Empresa b', $html);
    }

    public function testGlobalScopeAndEmptyPeriodRenderTruthfulValues(): void
    {
        $f = $this->insights->filters(['from'=>'2026-09-15','to'=>'2026-09-16'], true, null);
        $data = $this->insights->load($f, true);
        $this->assertEquals(1000, $data['summary']['amount']);
        $this->assertCount(2, $data['trend']);
        $this->assertEquals(0, $data['trend'][1]['amount']);
        $this->assertCount(1, $data['critical']);
        $this->assertCount(2, $data['ranking']);
        $f = $this->insights->filters(['from'=>'2020-01-01','to'=>'2020-01-02'], true, null);
        $data = $this->insights->load($f, true);
        $html = view('dashboard/insights_panel', ['insights'=>$data,'filters'=>$f,'superadmin'=>true]);
        $this->assertStringContainsString('Un período sin ventas registradas', $html);
        $this->assertStringContainsString('Sin base de comparación anterior', $html);
    }

    public function testAdministratorWithoutCompanyFailsClosed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->insights->filters([], false, null);
    }

    public function testInvalidDateIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->insights->filters(['from'=>'2026-02-30','to'=>'2026-03-01'], true, null);
    }

    public function testExcessiveRangeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->insights->filters(['from'=>'2025-01-01','to'=>'2026-01-01'], true, null);
    }
}
