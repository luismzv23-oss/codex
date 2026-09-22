<?php

use App\Libraries\{SettingsService, VoucherSequenceService};
use CodeIgniter\Test\CIUnitTestCase;

final class SettingsIntegrityTest extends CIUnitTestCase
{
    private array $tables = [];
    private SettingsService $settings;

    protected function setUp(): void
    {
        parent::setUp(); helper(['app', 'url']); $this->db = db_connect('tests'); $this->settings = new SettingsService();
        $schemas = [
            'companies' => 'id TEXT PRIMARY KEY, name TEXT, legal_name TEXT, tax_id TEXT, email TEXT, phone TEXT, address TEXT, currency_code TEXT, active INTEGER, created_at TEXT, updated_at TEXT',
            'branches' => 'id TEXT PRIMARY KEY, company_id TEXT, name TEXT, code TEXT, address TEXT, phone TEXT, active INTEGER, created_at TEXT, updated_at TEXT, UNIQUE(company_id, code)',
            'currencies' => 'id TEXT PRIMARY KEY, company_id TEXT, code TEXT, name TEXT, symbol TEXT, exchange_rate REAL, is_default INTEGER, active INTEGER, created_at TEXT, updated_at TEXT, UNIQUE(company_id, code)',
            'taxes' => 'id TEXT PRIMARY KEY, company_id TEXT, code TEXT, name TEXT, rate REAL, afip_code INTEGER, is_default INTEGER, active INTEGER, created_at TEXT, updated_at TEXT, UNIQUE(company_id, code)',
            'voucher_sequences' => 'id TEXT PRIMARY KEY, company_id TEXT, branch_id TEXT, document_type TEXT, prefix TEXT, current_number INTEGER, active INTEGER, created_at TEXT, updated_at TEXT',
            'company_settings' => 'id TEXT PRIMARY KEY, company_id TEXT, "key" TEXT, value TEXT, created_at TEXT, updated_at TEXT, UNIQUE(company_id, "key")',
            'user_systems' => 'id TEXT PRIMARY KEY, company_id TEXT, user_id TEXT, system_id TEXT, access_level TEXT, active INTEGER',
        ];
        foreach ($schemas as $table => $columns) { $this->db->query('CREATE TABLE ' . $this->db->prefixTable($table) . ' (' . $columns . ')'); $this->tables[] = $table; }
        foreach (['a', 'b'] as $id) { $this->db->table('companies')->insert(['id' => $id, 'name' => $id, 'currency_code' => 'ARS', 'active' => 1]); }
        $this->db->table('branches')->insert(['id' => 'foreign', 'company_id' => 'b', 'name' => 'Foreign', 'code' => 'OTHER', 'active' => 1]);
        $this->settings->currency('a', ['code' => 'ARS', 'name' => 'Peso', 'exchange_rate' => 1]);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->tables) as $table) { $this->db->query('DROP TABLE ' . $this->db->prefixTable($table)); }
        parent::tearDown();
    }

    private function rejected(callable $call): void
    {
        try { $call(); $this->fail('Expected rejection'); } catch (RuntimeException $e) { $this->assertNotSame('', $e->getMessage()); }
        $this->assertSame(0, $this->db->transDepth);
    }

    private function api(array $input): \App\Controllers\Api\V1\SettingsController
    {
        $controller = new class extends \App\Controllers\Api\V1\SettingsController {
            public array $input;
            protected function apiCompanyId(): ?string { return 'a'; }
            protected function apiIsSuperadmin(): bool { return false; }
            protected function payload(): array { return $this->input; }
        };
        $controller->input = $input; $controller->initController(service('request'), service('response'), service('logger')); return $controller;
    }

    private function web(array $input): \App\Controllers\SettingsController
    {
        $controller = new class extends \App\Controllers\SettingsController {
            protected function companyId(): ?string { return 'a'; }
            protected function isSuperadmin(): bool { return false; }
            protected function currentUser(): ?array { return ['company_id' => 'a', 'role_slug' => 'custom']; }
        };
        $request = service('request'); $request->setGlobal('post', $input);
        $controller->initController($request, service('response'), service('logger')); return $controller;
    }

    public function testForeignDuplicateAndInvalidSequenceRejected(): void
    {
        $input = ['document_type' => 'INV', 'prefix' => 'I', 'current_number' => 1];
        $this->assertSame(422, $this->api($input + ['branch_id' => 'foreign'])->storeVoucherSequence()->getStatusCode());
        $this->assertSame(201, $this->api($input)->storeVoucherSequence()->getStatusCode());
        $this->assertSame(422, $this->api($input)->storeVoucherSequence()->getStatusCode());
        $input['document_type'] = 'OTHER'; $input['prefix'] = 'O'; $input['current_number'] = -1;
        $this->assertSame(422, $this->api($input)->storeVoucherSequence()->getStatusCode());
        $this->assertSame(1, $this->db->table('voucher_sequences')->countAllResults());
    }

    public function testSequenceRespectsBranchPrefixStateAndGlobalFallback(): void
    {
        $branch = $this->settings->branch('a', ['name' => 'Sucursal', 'code' => 'LOCAL']);
        $global = $this->settings->sequence('a', ['document_type' => 'INV', 'prefix' => 'GLOBAL', 'current_number' => 7]);
        $local = $this->settings->sequence('a', ['document_type' => 'INV', 'prefix' => 'LOCAL', 'branch_id' => $branch['id'], 'current_number' => 40]);
        $service = new VoucherSequenceService();
        $this->assertSame('LOCAL-00000040', $service->next('a', 'INV', 'IGNORED', $branch['id']));
        $this->assertSame('GLOBAL-00000007', $service->next('a', 'INV', 'IGNORED'));
        $this->db->table('voucher_sequences')->where('id', $local['id'])->update(['active' => 0]);
        $this->rejected(fn() => $service->next('a', 'INV', 'IGNORED', $branch['id']));
        $this->assertEquals(8, $this->db->table('voucher_sequences')->where('id', $global['id'])->get()->getRowArray()['current_number']);
        $this->assertSame('NEW-00000001', $service->next('a', 'NEW', 'NEW', null, true));
        $this->assertSame(2, $this->db->table('voucher_sequences')->countAllResults());
    }

    public function testFailedTaxSaveKeepsPreviousDefaultAndApiPreservesFields(): void
    {
        $input = ['name' => 'IVA', 'code' => 'IVA', 'rate' => 21, 'afip_code' => 5, 'is_default' => 1];
        $this->assertSame(201, $this->api($input)->storeTax()->getStatusCode());
        $this->web($input)->storeTax();
        $tax = $this->db->table('taxes')->get()->getRowArray();
        $this->assertEquals(1, $tax['is_default']); $this->assertEquals(5, $tax['afip_code']);
        $this->assertSame(1, $this->db->table('taxes')->countAllResults());
        $this->web([])->toggleTax($tax['id']); $this->web([])->deleteTax($tax['id']);
        $this->assertEquals(1, $this->db->table('taxes')->get()->getRowArray()['active']);
        $replacement = $this->settings->tax('a', ['name' => 'Replacement', 'code' => 'R', 'rate' => 10, 'is_default' => 1]);
        $this->assertSame($replacement['id'], (new \App\Models\TaxModel())->getDefaultTax('a')['id']);
        $this->settings->taxAction('a', $tax['id'], 'toggle');
        $this->rejected(fn() => (new \App\Models\TaxModel())->setDefault($tax['id'], 'a'));
        $this->rejected(fn() => (new \App\Models\TaxModel())->setDefault($replacement['id'], 'b'));
    }

    public function testCurrencyAndCompanyShareRulesAcrossTransports(): void
    {
        $this->assertSame(422, $this->api(['currency_code' => 'XYZ'])->updateCompany()->getStatusCode());
        $this->assertSame(422, $this->api(['code' => 'USD', 'name' => 'Dollar', 'exchange_rate' => 0])->storeCurrency()->getStatusCode());
        $this->assertSame(201, $this->api(['code' => 'USD', 'name' => 'Dollar', 'exchange_rate' => 1000, 'is_default' => 1])->storeCurrency()->getStatusCode());
        $this->assertSame(1, $this->db->table('currencies')->where('is_default', 1)->countAllResults());
        $this->assertSame('USD', $this->db->table('companies')->where('id', 'a')->get()->getRowArray()['currency_code']);
        $this->web(['currency_code' => 'ARS'])->updateCompany();
        $this->assertSame('ARS', $this->db->table('currencies')->where('is_default', 1)->get()->getRowArray()['code']);
    }

    public function testCompanyOmittedLimitPreservedAndInvalidSaveIsAtomic(): void
    {
        $this->settings->company('a', ['max_cash_registers' => 3]);
        $this->settings->company('a', ['name' => 'New']);
        $this->assertSame('3', $this->db->table('company_settings')->get()->getRowArray()['value']);
        $this->rejected(fn() => $this->settings->company('a', ['name' => 'Broken', 'currency_code' => 'ARS', 'max_cash_registers' => -1]));
        $this->assertSame('New', $this->db->table('companies')->where('id', 'a')->get()->getRowArray()['name']);
    }

    public function testTicketsPermissionAtomicityAndPartialUpdates(): void
    {
        $this->settings->tickets('a', ['ticket_pos_show_sku' => '1', 'ticket_pos_custom_text_bottom_left' => 'Original'], true);
        $this->web(['ticket_pos_header_title' => 'Changed', 'ticket_pos_custom_text_bottom_left' => 'Forbidden'])->updateTicketSettings();
        $this->assertSame(2, $this->db->table('company_settings')->countAllResults());
        $this->assertSame('Original', $this->db->table('company_settings')->where('key', 'ticket_pos_custom_text_bottom_left')->get()->getRowArray()['value']);
        $this->settings->tickets('a', ['ticket_pos_header_title' => 'Allowed'], false);
        $this->assertSame('1', $this->db->table('company_settings')->where('key', 'ticket_pos_show_sku')->get()->getRowArray()['value']);
        $this->settings->tickets('a', ['ticket_pos_show_sku' => '0'], false);
        $this->assertSame('0', $this->db->table('company_settings')->where('key', 'ticket_pos_show_sku')->get()->getRowArray()['value']);
        $this->rejected(fn() => $this->settings->tickets('a', ['ticket_pos_header_title' => 'Broken', 'ticket_pos_paper_width' => 'invalid'], true));
        $this->assertSame('Allowed', $this->db->table('company_settings')->where('key', 'ticket_pos_header_title')->get()->getRowArray()['value']);
    }

    public function testCreatingBranchesDoesNotChangePermissions(): void
    {
        $before = ['id' => 'assignment', 'company_id' => 'a', 'user_id' => 'admin', 'system_id' => 'sales', 'access_level' => 'view', 'active' => 0];
        $this->db->table('user_systems')->insert($before);
        $this->web(['name' => 'Branch', 'code' => 'WEB'])->storeBranch();
        $this->assertSame(201, $this->api(['name' => 'Branch API', 'code' => 'API'])->storeBranch()->getStatusCode());
        $this->assertEquals($before, $this->db->table('user_systems')->get()->getRowArray());
        $this->assertSame(2, $this->db->table('branches')->where('company_id', 'a')->countAllResults());
    }

    public function testInvalidMasterDataRejected(): void
    {
        $this->assertSame(422, $this->api(['name' => '', 'code' => 'B'])->storeBranch()->getStatusCode());
        $this->assertSame(422, $this->api(['name' => 'Tax', 'code' => 'T', 'rate' => -21])->storeTax()->getStatusCode());
        $this->assertSame(422, $this->api(['email' => 'invalid'])->updateCompany()->getStatusCode());
        $this->assertSame(422, $this->api(['name' => str_repeat('x', 151)])->updateCompany()->getStatusCode());
        $this->assertSame(422, $this->api(['name' => 'Tax', 'code' => 'T', 'rate' => 21, 'is_default' => 1, 'active' => 0])->storeTax()->getStatusCode());
    }
}
