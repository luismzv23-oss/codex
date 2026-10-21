<?php

use App\Libraries\{AccountingService, ArcaAsyncWorker, ArcaService, FieldAuditService, NotificationService, PasswordRecoveryService};
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

final class CoreIntegrationRepairTest extends CIUnitTestCase
{
    private array $tables = [];

    protected function setUp(): void
    {
        parent::setUp();
        helper(['app', 'auth', 'url']);
        // Deliberately omit the obsolete settings_json, description, status_code and retry columns.
        foreach ([
            'companies'=>'id TEXT PRIMARY KEY, name TEXT, active INTEGER',
            'users'=>'id TEXT PRIMARY KEY, company_id TEXT, email TEXT, active INTEGER, password_hash TEXT, must_change_password INTEGER, created_at TEXT, updated_at TEXT',
            'password_reset_tokens'=>'id TEXT PRIMARY KEY, user_id TEXT, selector TEXT, token_hash TEXT, expires_at TEXT, used_at TEXT, created_at TEXT, updated_at TEXT',
            'notifications'=>'id TEXT PRIMARY KEY, company_id TEXT, user_id TEXT, read_at TEXT, created_at TEXT',
            'accounts'=>'id TEXT PRIMARY KEY, company_id TEXT',
            'journal_entries'=>'id TEXT PRIMARY KEY, company_id TEXT, entry_number INTEGER, entry_date TEXT, description TEXT, status TEXT, posted_at TEXT',
            'journal_entry_lines'=>'id TEXT PRIMARY KEY, journal_entry_id TEXT, account_id TEXT, debit REAL, credit REAL',
            'audit_logs'=>'id TEXT PRIMARY KEY, company_id TEXT, user_id TEXT, module TEXT, action TEXT, entity_type TEXT, entity_id TEXT, before_payload TEXT, after_payload TEXT, notes TEXT, ip_address TEXT, created_at TEXT, updated_at TEXT',
            'sales'=>'id TEXT PRIMARY KEY, company_id TEXT, status TEXT, document_type_id TEXT, point_of_sale_id TEXT, sale_number TEXT, arca_status TEXT, cae TEXT, cae_due_date TEXT, arca_service TEXT, arca_operation_mode TEXT, arca_result_code TEXT, arca_result_message TEXT, arca_authorized_at TEXT, arca_last_checked_at TEXT, arca_request_id TEXT, created_at TEXT, updated_at TEXT',
            'sale_items'=>'id TEXT PRIMARY KEY, sale_id TEXT, line_number INTEGER',
            'sales_settings'=>'id TEXT PRIMARY KEY, company_id TEXT, arca_environment TEXT, arca_last_sync_at TEXT, arca_last_error TEXT, created_at TEXT, updated_at TEXT',
            'sales_document_types'=>'id TEXT PRIMARY KEY, company_id TEXT',
            'sales_points_of_sale'=>'id TEXT PRIMARY KEY, company_id TEXT',
            'sales_arca_events'=>'id TEXT PRIMARY KEY, company_id TEXT, sale_id TEXT, service_slug TEXT, event_type TEXT, environment TEXT, status TEXT, result_code TEXT, message TEXT, request_payload TEXT, response_payload TEXT, performed_at TEXT, created_at TEXT, updated_at TEXT',
            'integration_logs'=>'id TEXT PRIMARY KEY, company_id TEXT, provider TEXT, service TEXT, reference_type TEXT, reference_id TEXT, status TEXT, request_payload TEXT, response_payload TEXT, message TEXT, created_at TEXT, updated_at TEXT',
        ] as $table=>$schema) {
            db_connect()->query('CREATE TABLE '.db_connect()->prefixTable($table).' ('.$schema.')');
            $this->tables[]=$table;
        }
        foreach (['a','b'] as $id) {
            db_connect()->table('companies')->insert(['id'=>$id,'name'=>$id,'active'=>1]);
            db_connect()->table('accounts')->insert(['id'=>$id,'company_id'=>$id]);
            db_connect()->table('sales_settings')->insert(['id'=>$id,'company_id'=>$id,'arca_environment'=>'homologacion']);
            db_connect()->table('sales_document_types')->insert(['id'=>$id,'company_id'=>$id]);
            db_connect()->table('sales')->insert(['id'=>$id,'company_id'=>$id,'document_type_id'=>$id,'status'=>'confirmed','arca_status'=>'not_requested','sale_number'=>$id]);
        }
        db_connect()->table('users')->insert(['id'=>'operator','company_id'=>'a','email'=>'operator@example.test','active'=>1,'password_hash'=>password_hash('OldPassword1!', PASSWORD_DEFAULT)]);
        Services::injectMock('auth', new \App\Libraries\AuthService());
    }

    protected function tearDown(): void
    {
        Services::resetSingle('passwordRecovery');
        Services::resetSingle('auth');
        foreach (array_reverse($this->tables) as $table) db_connect()->query('DROP TABLE '.db_connect()->prefixTable($table));
        parent::tearDown();
    }

    private function api(string $class, array $input = [])
    {
        if ($class==='auth') {
            $controller=new class extends \App\Controllers\Api\V1\AuthController {
                public array $input=[];
                protected function payload(): array { return $this->input; }
            };
            $controller->input=$input;
        } elseif ($class==='accounting') {
            $controller=new class extends \App\Controllers\Api\V1\AccountingController {
                protected function apiCompanyId(): ?string { return 'a'; }
            };
        } else {
            $controller=new class extends \App\Controllers\Api\V1\NotificationsController {
                protected function apiUser(): ?array { return ['id'=>'operator','company_id'=>'a']; }
            };
        }
        $controller->initController(service('request'), service('response'), service('logger'));
        return $controller;
    }

    public function testNotificationCannotBeMarkedForAnotherUserOrCompany(): void
    {
        foreach ([['own','a','operator'],['foreign-user','a','other'],['foreign-company','b','operator']] as [$id,$company,$user]) {
            db_connect()->table('notifications')->insert(['id'=>$id,'company_id'=>$company,'user_id'=>$user]);
        }
        $controller=$this->api('notifications');
        $this->assertSame(404,$controller->markRead('foreign-user')->getStatusCode());
        $this->assertSame(404,$controller->markRead('foreign-company')->getStatusCode());
        $this->assertSame(200,$controller->markRead('own')->getStatusCode());
        $this->assertSame(1,db_connect()->table('notifications')->where('read_at !=',null)->countAllResults());
        $this->assertCount(1,json_decode($controller->index()->getBody(),true)['data']['notifications']);
        $controller->markAllRead();
        $this->assertNull(db_connect()->table('notifications')->where('id','foreign-company')->get()->getRowArray()['read_at']);
    }

    public function testPostingAndLedgerRequireCompanyAndDoNotLeakForeignLines(): void
    {
        foreach (['a','b'] as $id) db_connect()->table('journal_entries')->insert(['id'=>$id,'company_id'=>$id,'status'=>'draft','entry_date'=>'2026-10-01','entry_number'=>1]);
        $api=$this->api('accounting');
        $this->assertSame(404,$api->postEntry('b')->getStatusCode());
        $this->assertSame(200,$api->postEntry('a')->getStatusCode());
        $this->assertSame(404,$api->postEntry('a')->getStatusCode());
        $this->assertSame('draft',db_connect()->table('journal_entries')->where('id','b')->get()->getRowArray()['status']);
        $this->assertSame(404,$api->accountLedger('b')->getStatusCode());
        db_connect()->table('journal_entries')->where('id','b')->update(['status'=>'posted']);
        foreach (['a','b'] as $id) db_connect()->table('journal_entry_lines')->insert(['id'=>$id,'journal_entry_id'=>$id,'account_id'=>'a','debit'=>10,'credit'=>0]);
        $ledger=(new AccountingService())->accountLedger('a','a','2026-01-01','2026-12-31');
        $this->assertCount(1,$ledger['entries']);
        $this->assertEquals(10,$ledger['final_balance']);
    }

    public function testRecoveryDoesNotExposeAccountExistenceOrTokensAndResetIsSingleUse(): void
    {
        $sent=[];
        Services::injectMock('passwordRecovery',new PasswordRecoveryService(function($email,$url)use(&$sent){$sent[]=[$email,$url];return true;}));
        $known=$this->api('auth',['email'=>'operator@example.test'])->forgotPassword();
        $body=$known->getBody();
        $this->assertSame(202,$known->getStatusCode());
        $unknown=$this->api('auth',['email'=>'unknown@example.test'])->forgotPassword();
        $this->assertSame($body,$unknown->getBody());
        $this->assertCount(1,$sent);
        $this->assertStringNotContainsString('reset-password/',$body);
        preg_match('~/reset-password/([a-f0-9]+)/([a-f0-9]+)~',$sent[0][1],$match);
        $payload=['selector'=>$match[1],'token'=>$match[2],'password'=>'NewPassword2!','password_confirm'=>'NewPassword2!'];
        $this->assertSame(200,$this->api('auth',$payload)->resetPassword()->getStatusCode());
        $this->assertTrue(password_verify($payload['password'],db_connect()->table('users')->get()->getRowArray()['password_hash']));
        $this->assertSame(422,$this->api('auth',$payload)->resetPassword()->getStatusCode());
        $this->assertSame(0,db_connect()->transDepth);
    }

    public function testRecoveryRejectsBadInputAndExpiredOrInactiveTokens(): void
    {
        $this->assertSame(422,$this->api('auth',['email'=>['bad']])->forgotPassword()->getStatusCode());
        $reset=service('auth')->createPasswordReset('operator@example.test');
        db_connect()->table('password_reset_tokens')->update(['expires_at'=>'2000-01-01']);
        $this->assertFalse(service('auth')->resetPassword($reset['selector'],$reset['token'],'NewPassword2!'));
        $reset=service('auth')->createPasswordReset('operator@example.test');
        db_connect()->table('users')->where('id','operator')->update(['active'=>0]);
        $this->assertFalse(service('auth')->resetPassword($reset['selector'],$reset['token'],'NewPassword2!'));
        $this->assertNull(db_connect()->table('password_reset_tokens')->get()->getRowArray()['used_at']);
    }

    public function testUnconfiguredRecoveryIsUnavailableForEveryAddressWithoutIssuingTokens(): void
    {
        $config=config('Email'); $previous=$config->fromEmail;
        $config->fromEmail='';
        try {
            Services::injectMock('passwordRecovery',new PasswordRecoveryService());
            $api=$this->api('auth',['email'=>'operator@example.test']);
            $this->assertSame(503,$api->forgotPassword()->getStatusCode());
            $body=service('response')->getBody();
            $other=$this->api('auth',['email'=>'unknown@example.test'])->forgotPassword();
            $this->assertSame(503,$other->getStatusCode());
            $this->assertSame($body,$other->getBody());
            $this->assertSame(0,db_connect()->table('password_reset_tokens')->countAllResults());
        } finally { $config->fromEmail=$previous; }
    }

    public function testFieldAuditUsesCanonicalColumnsAndCannotExposeWatchedSecrets(): void
    {
        $service=new FieldAuditService();
        $this->assertTrue($service->logEntityDiff('product','p',['name'=>'old','secret'=>'before'],['name'=>'new','secret'=>'after'],'a','operator',['name','secret']));
        $audit=db_connect()->table('audit_logs')->get()->getRowArray();
        $this->assertSame('product',$audit['entity_type']);
        $this->assertSame(['name'=>'new'],json_decode($audit['after_payload'],true));
        $this->assertSame('a',$audit['company_id']);
        $this->assertFalse($service->logEntityDiff('user','p',['password_hash'=>'a'],['password_hash'=>'b'],'a','operator',['password_hash']));
    }

    private function worker(array $response, ?callable $observer = null): ArcaAsyncWorker
    {
        $engine=new class($response,$observer) extends ArcaService {
            private array $result; private $observer;
            public function __construct(array $result,?callable $observer){$this->result=$result;$this->observer=$observer;}
            public function authorizeSale(array $sale,array $documentType,array $company,array $settings,array $items,array $pointOfSale=[]): array {
                if ($this->observer) ($this->observer)($sale,$settings);
                return $this->result;
            }
        };
        return new ArcaAsyncWorker($engine);
    }

    public function testFiscalWorkerUsesSalesEngineAndCurrentSchemaAndOnlySelectedCompany(): void
    {
        $calls=0;
        $worker=$this->worker(['status'=>'Authorizado','cae'=>'TEST-ONLY','cae_due_date'=>'2026-11-01','service_slug'=>'wsfev1'],function($sale,$settings)use(&$calls){$calls++;$this->assertSame('a',$sale['company_id']);$this->assertSame('homologacion',$settings['arca_environment']);});
        $this->assertFalse($worker->queueInvoice('a','b'));
        $this->assertTrue($worker->queueInvoice('a','a'));
        $this->assertTrue($worker->queueInvoice('a','a'));
        $this->assertSame(1,db_connect()->table('integration_logs')->countAllResults());
        db_connect()->table('sales')->where('id','b')->update(['arca_status'=>'queued']);
        $this->assertSame(1,$worker->processBatch('a')['successes']);
        $this->assertSame(1,$calls);
        $this->assertSame(0,$worker->processBatch('a')['processed']);
        $this->assertSame('queued',db_connect()->table('sales')->where('id','b')->get()->getRowArray()['arca_status']);
        $sale=db_connect()->table('sales')->where('id','a')->get()->getRowArray();
        $this->assertSame('TEST-ONLY',$sale['cae']);
        $this->assertSame('2026-11-01',$sale['cae_due_date']);
        $this->assertSame(1,db_connect()->table('sales_arca_events')->countAllResults());
    }

    public function testAmbiguousFiscalResultIsNotAutomaticallyReissued(): void
    {
        $worker=$this->worker(['status'=>'error','message'=>'Timeout']);
        $worker->queueInvoice('a','a');
        $this->assertSame(1,$worker->processBatch('a')['errors']);
        $this->assertSame('contingency',db_connect()->table('sales')->where('id','a')->get()->getRowArray()['arca_status']);
        $this->assertFalse($worker->queueInvoice('a','a'));
        $this->assertSame(0,$worker->processBatch('a')['processed']);
    }

    public function testFiscalConfigurationAndDocumentReferencesCannotCrossCompanies(): void
    {
        $worker=$this->worker(['cae'=>'MUST-NOT-BE-USED'],function(){ $this->fail('No debe invocar al proveedor con referencias ajenas.'); });
        db_connect()->table('sales')->where('id','a')->update(['document_type_id'=>'b']);
        $worker->queueInvoice('a','a');
        $this->assertSame(1,$worker->processBatch('a')['errors']);
        $sale=db_connect()->table('sales')->where('id','a')->get()->getRowArray();
        $this->assertSame('arca_error',$sale['arca_status']);
        $this->assertNull($sale['cae']);
        $this->assertSame(0,db_connect()->transDepth);
    }

    public function testOnlyConfirmedSalesCanBeQueuedAndNoFiscalReissueAfterAuthorization(): void
    {
        $worker=$this->worker([]);
        foreach (['draft','cancelled'] as $status) {
            db_connect()->table('sales')->where('id','a')->update(['status'=>$status]);
            $this->assertFalse($worker->queueInvoice('a','a'));
        }
        db_connect()->table('sales')->where('id','a')->update(['status'=>'confirmed','cae'=>'EXISTING']);
        $this->assertFalse($worker->queueInvoice('a','a'));
        $this->assertSame(0,db_connect()->table('integration_logs')->countAllResults());
    }
}
