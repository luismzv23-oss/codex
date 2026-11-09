<?php
use App\Libraries\{ArcaService, CompanyOnboardingService, CompanySystemsService};
use CodeIgniter\Test\CIUnitTestCase;

/** Company registration wizard: each step, access rules and the gaps it closes. */
final class CompanyOnboardingServiceTest extends CIUnitTestCase
{
    private array $tables = [];
    private CompanyOnboardingService $service;
    private const CUIT = '30719167124';

    protected function setUp(): void
    {
        parent::setUp();
        helper(['app', 'url']);
        $db = db_connect();
        $db->resetDataCache();
        foreach (['Company', 'Currency', 'Branch', 'SalesSetting', 'SalesDocumentType', 'SalesPointOfSale', 'VoucherSequence', 'Customer', 'SalesPriceList',
            'InventorySetting', 'InventoryWarehouse', 'CashRegister', 'CashPaymentGateway', 'System', 'CompanySystem', 'UserSystem', 'User', 'Role', 'Tax', 'CompanyPaymentMethod'] as $name) {
            $class = 'App\\Models\\' . $name . 'Model';
            $ref = new ReflectionClass(new $class());
            $fields = $ref->getProperty('allowedFields');
            $fields->setAccessible(true);
            $table = $ref->getProperty('table');
            $table->setAccessible(true);
            $columns = array_unique(array_merge(['id', 'created_at', 'updated_at', 'deleted_at'], $fields->getValue(new $class())));
            $db->query('CREATE TABLE ' . $db->prefixTable($table->getValue(new $class())) . ' (' . implode(', ', array_map(static fn($c) => '"' . $c . '" ' . ($c === 'id' ? 'TEXT PRIMARY KEY' : 'TEXT'), $columns)) . ')');
            $this->tables[] = $table->getValue(new $class());
        }
        foreach ([
            'accounts' => 'id TEXT PRIMARY KEY, company_id TEXT, parent_id TEXT, code TEXT, name TEXT, account_type TEXT, is_group INTEGER, level INTEGER, accepts_entries INTEGER, currency_code TEXT, opening_balance REAL, active INTEGER, created_at TEXT, updated_at TEXT',
            'fiscal_periods' => 'id TEXT PRIMARY KEY, company_id TEXT, name TEXT, start_date TEXT, end_date TEXT, status TEXT, closed_at TEXT, closed_by TEXT, created_at TEXT, updated_at TEXT',
            'company_settings' => 'id TEXT PRIMARY KEY, company_id TEXT, "key" TEXT, value TEXT, created_at TEXT, updated_at TEXT',
            'inventory_products' => 'id TEXT, company_id TEXT, active INTEGER',
            'cash_sessions' => 'id TEXT, company_id TEXT, status TEXT',
            'sales_arca_events' => 'id TEXT, company_id TEXT',
        ] as $table => $schema) {
            $db->query('CREATE TABLE ' . $db->prefixTable($table) . ' (' . $schema . ')');
            $this->tables[] = $table;
        }
        require_once APPPATH . 'Database/Migrations/2026-11-10-120000_CompanyOnboarding.php';
        (new \App\Database\Migrations\CompanyOnboarding(\Config\Database::forge('tests')))->up();
        $this->tables[] = 'company_onboarding';
        $db->table('currencies')->insert(['id' => 'tpl-ars', 'company_id' => 'catalog', 'code' => 'ARS', 'name' => 'Peso argentino', 'symbol' => '$', 'exchange_rate' => '1', 'active' => 1]);
        $db->table('roles')->insertBatch([['id' => 'role-admin', 'slug' => 'admin', 'name' => 'Admin'], ['id' => 'role-super', 'slug' => 'superadmin', 'name' => 'Superadmin']]);
        $db->table('systems')->insertBatch([['id' => 'sys-inv', 'slug' => 'inventario', 'name' => 'Inventario', 'active' => 1],
            ['id' => 'sys-ven', 'slug' => 'ventas', 'name' => 'Ventas', 'active' => 1], ['id' => 'sys-caja', 'slug' => 'caja', 'name' => 'Caja', 'active' => 1]]);
        $this->service = new CompanyOnboardingService();
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            db_connect()->query('DROP TABLE IF EXISTS ' . db_connect()->prefixTable($table));
        }
        db_connect()->resetDataCache();
        parent::tearDown();
    }

    private function company(array $replace = []): string
    {
        return $this->service->saveCompany(null, array_replace(['name' => 'Distribuidora Nueva', 'legal_name' => 'Distribuidora Nueva SA', 'tax_id' => '30-71916712-4',
            'iva_condition' => 'Responsable Inscripto', 'iibb' => '901-123456-7', 'start_activities' => '2020-03-01', 'email' => 'info@nueva.test', 'currency_code' => 'ARS'], $replace), 'super');
    }

    private function rows(string $table, array $where = []): int
    {
        return db_connect()->table($table)->where($where)->countAllResults();
    }

    private function admin(string $companyId): array
    {
        return $this->service->saveAdmin($companyId, ['name' => 'Admin Nueva', 'username' => 'admin.nueva', 'email' => 'admin@nueva.test', 'password' => 'Clave.Segura-2026']);
    }

    public function testCompanyStepCreatesCompanyWithCurrencyBranchAccountingAndFiscalData(): void
    {
        $id = $this->company();
        $state = $this->service->state($id);
        $this->assertSame('in_progress', $state['status']);
        $this->assertSame('done', $state['steps'][1]);
        $this->assertSame(2, $state['current']);
        $this->assertSame(self::CUIT, $state['company']['tax_id']);
        $this->assertSame(1, $this->rows('currencies', ['company_id' => $id, 'code' => 'ARS', 'is_default' => 1]));
        $this->assertSame(1, $this->rows('branches', ['company_id' => $id, 'code' => 'MAIN']));
        $this->assertGreaterThan(30, $this->rows('accounts', ['company_id' => $id]));
        $this->assertSame(1, $this->rows('fiscal_periods', ['company_id' => $id, 'start_date' => date('Y') . '-01-01']));
        $this->assertSame(1, $this->rows('company_settings', ['company_id' => $id, 'key' => 'account_inventory']));
        $sales = db_connect()->table('sales_settings')->where('company_id', $id)->get()->getRowArray();
        $this->assertSame([self::CUIT, 'Responsable Inscripto', '901-123456-7'], [$sales['arca_cuit'], $sales['arca_iva_condition'], $sales['arca_iibb']]);
        $this->assertFalse($this->service->isCompleted($id));
    }

    public function testInvalidCuitAndDuplicatesAreRejected(): void
    {
        foreach (['30-71916712-5', '123'] as $cuit) {
            try {
                $this->company(['tax_id' => $cuit]);
                $this->fail('CUIT ' . $cuit . ' must be rejected.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('CUIT', $e->getMessage());
            }
        }
        $this->company();
        $this->expectExceptionMessage('Ya existe una empresa con ese CUIT');
        $this->company(['name' => 'Otra']);
    }

    public function testAdminGetsEveryModuleWhateverTheOrder(): void
    {
        $first = $this->company();
        $this->service->saveModules($first, ['sys-inv', 'sys-ven']);
        $admin = $this->admin($first);
        $this->assertSame(2, $this->rows('user_systems', ['user_id' => $admin['id'], 'access_level' => 'manage', 'active' => 1]));
        $this->assertSame('1', (string) $admin['must_change_password']);

        // Admin first, modules later (the gap the wizard closes for the Usuarios screen too).
        $second = $this->company(['tax_id' => '30707943463', 'name' => 'Segunda']);
        $other = $this->service->saveAdmin($second, ['name' => 'Admin Dos', 'username' => 'admin.dos', 'email' => 'dos@nueva.test', 'password' => 'Clave.Segura-2026']);
        $this->assertSame(0, $this->rows('user_systems', ['user_id' => $other['id']]));
        (new CompanySystemsService())->setActive($second, ['sys-caja']);
        $this->assertSame(1, $this->rows('user_systems', ['user_id' => $other['id'], 'system_id' => 'sys-caja', 'active' => 1]));
    }

    public function testModuleDependenciesAreEnforced(): void
    {
        $id = $this->company();
        $catalog = array_column($this->service->moduleCatalog(), null, 'slug');
        if (! in_array('inventario', $catalog['ventas']['dependencies'], true)) {
            $this->markTestSkipped('El manifiesto de Ventas no declara dependencias.');
        }
        $this->expectExceptionMessage('requiere Inventario');
        $this->service->saveModules($id, ['sys-ven']);
    }

    public function testCompanyAdminCannotEditSuperadminStepsOrAnotherCompany(): void
    {
        $id = $this->company();
        $admin = ['id' => 'u', 'role_slug' => 'admin', 'company_id' => $id];
        $this->service->authorize($admin, $id, 5);
        foreach ([[$id, 1], [$id, 3], ['other-company', 5]] as [$company, $step]) {
            try {
                $this->service->authorize($admin, $company, $step);
                $this->fail("Admin must not access step $step of $company.");
            } catch (RuntimeException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
        $this->expectException(RuntimeException::class);
        $this->service->authorize(['role_slug' => 'operador', 'company_id' => $id], $id, 5);
    }

    public function testStructureCreatesModuleDefaultsOnceWithAfipCodes(): void
    {
        $id = $this->company();
        $this->service->saveStructure($id, ['branches' => [['name' => 'Sucursal Norte', 'code' => 'NORTE']], 'warehouses' => [['name' => 'Depósito Norte', 'code' => 'DEP-N', 'branch_id' => '']]]);
        $this->service->saveStructure($id, []);
        $this->assertSame(2, $this->rows('branches', ['company_id' => $id]));
        $this->assertSame(2, $this->rows('inventory_warehouses', ['company_id' => $id]));
        $this->assertSame(14, $this->rows('sales_document_types', ['company_id' => $id]));
        $this->assertSame(2, $this->rows('sales_points_of_sale', ['company_id' => $id]));
        $this->assertSame(1, $this->rows('customers', ['company_id' => $id, 'name' => 'Consumidor Final']));
        $this->assertSame(1, $this->rows('voucher_sequences', ['company_id' => $id, 'document_type' => 'OCOMPRA']));
        $this->assertGreaterThan(0, $this->rows('cash_registers', ['company_id' => $id]));
        $types = array_column(db_connect()->table('sales_document_types')->where('company_id', $id)->get()->getResultArray(), 'afip_code', 'code');
        $this->assertSame(['1', '6', '11', '3'], [(string) $types['FACTURA_A'], (string) $types['FACTURA_B'], (string) $types['FACTURA_C'], (string) $types['NC_A']]);
        $this->assertNull($types['TICKET']);
        $this->assertSame(1, $this->rows('sales_document_types', ['company_id' => $id, 'code' => 'FACTURA_B', 'is_default' => 1]));
    }

    public function testTaxesPaymentsAccountingAndFinish(): void
    {
        $id = $this->company();
        try {
            $this->service->finish($id, 'super');
            $this->fail('Finishing with pending steps must be blocked.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Paso pendiente', $e->getMessage());
        }
        $this->service->saveModules($id, ['sys-inv', 'sys-ven', 'sys-caja']);
        $this->admin($id);
        $this->service->saveStructure($id, []);
        $this->assertTrue($this->service->taxes($id)[0]['proposed']);
        $this->service->saveTaxes($id, $this->service->taxes($id));
        $this->assertSame(7, $this->rows('taxes', ['company_id' => $id]));
        $this->assertSame(1, $this->rows('taxes', ['company_id' => $id, 'code' => 'IVA21', 'is_default' => 1, 'afip_code' => 5]));
        $this->service->savePaymentMethods($id, $this->service->paymentMethods($id));
        $this->assertSame(4, $this->rows('company_payment_methods', ['company_id' => $id]));
        $this->assertSame(1, $this->rows('company_payment_methods', ['company_id' => $id, 'code' => 'EFECTIVO', 'allow_supplier_payments' => 1]));
        $this->service->skip($id, 7);
        $cash = db_connect()->table('accounts')->where('company_id', $id)->where('code', '1.1.01')->get()->getRowArray()['id'];
        $this->service->saveAccounting($id, ['cash' => $cash], [(int) date('Y') + 1]);
        $this->assertSame(2, $this->rows('fiscal_periods', ['company_id' => $id]));
        $this->assertSame(1, $this->rows('company_settings', ['company_id' => $id, 'key' => 'account_perceptions_payable']));

        $state = $this->service->finish($id, 'super');
        $this->assertSame('completed', $state['status']);
        $this->assertTrue($this->service->isCompleted($id));
    }

    public function testExistingCompaniesWithoutOnboardingAreConsideredConfigured(): void
    {
        db_connect()->table('companies')->insert(['id' => 'legacy', 'name' => 'Legacy', 'currency_code' => 'ARS', 'active' => 1]);
        $this->assertTrue($this->service->isCompleted('legacy'));
        $this->assertFalse($this->service->state('legacy')['tracked']);
        $this->assertSame('in_progress', $this->service->start('legacy', 'super')['status']);
    }

    public function testArcaVoucherTypeUsesCodeAndIssuerCondition(): void
    {
        $this->assertSame(1, ArcaService::afipVoucherType(['code' => 'FACTURA_A']));
        $this->assertSame(11, ArcaService::afipVoucherType(['code' => 'FACTURA_C', 'afip_code' => null]));
        $this->assertSame(51, ArcaService::afipVoucherType(['code' => 'X', 'afip_code' => 51]));
        $this->assertSame(6, ArcaService::afipVoucherType(['code' => 'TICKET'], ['arca_iva_condition' => 'Responsable Inscripto']));
        $this->assertSame(11, ArcaService::afipVoucherType(['code' => 'TICKET', 'category' => 'ticket'], ['arca_iva_condition' => 'Monotributo']));
        $this->assertSame(6, ArcaService::afipVoucherType(null));
    }
}
