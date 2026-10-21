<?php

use App\Libraries\Core\{ModuleRegistry,ModuleInstallationService,ModuleNavigation};
use CodeIgniter\Test\CIUnitTestCase;

final class ModuleRegistryTest extends CIUnitTestCase
{
    private array $tables = [];
    private string $root;
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        helper(['app','url']);
        $this->root = sys_get_temp_dir().'/codex-modules-'.bin2hex(random_bytes(6));
        mkdir($this->root);
        foreach ([
            'systems'=>'id TEXT PRIMARY KEY,slug TEXT UNIQUE,name TEXT,description TEXT,entry_url TEXT,icon TEXT,active INTEGER,created_at TEXT,updated_at TEXT',
            'company_systems'=>'id TEXT PRIMARY KEY,system_id TEXT,company_id TEXT,active INTEGER',
            'user_systems'=>'id TEXT PRIMARY KEY,system_id TEXT,user_id TEXT,company_id TEXT,access_level TEXT,active INTEGER',
            'module_documents'=>'id TEXT PRIMARY KEY,company_id TEXT',
        ] as $table=>$schema) {
            db_connect()->query('CREATE TABLE '.db_connect()->prefixTable($table).' ('.$schema.')');
            $this->tables[]=$table;
        }
        require_once APPPATH.'Modules/Core/Database/Migrations/2026-10-21-120000_CreateSystemInstallations.php';
        (new \CodexCore\Database\Migrations\CreateSystemInstallations())->up();
        $this->tables[]='system_installations';
        db_connect()->resetDataCache();
        db_connect()->table('systems')->insert(['id'=>'existing','slug'=>'documentos','name'=>'Nombre elegido','entry_url'=>'ruta-elegida','icon'=>'bi-file','active'=>1]);
        db_connect()->table('company_systems')->insert(['id'=>'company-grant','system_id'=>'existing','company_id'=>'a','active'=>1]);
        db_connect()->table('user_systems')->insert(['id'=>'user-grant','system_id'=>'existing','company_id'=>'a','user_id'=>'u','access_level'=>'manage','active'=>1]);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->tables) as $table) db_connect()->query('DROP TABLE '.db_connect()->prefixTable($table));
        foreach ($this->files as $file) { unlink($file); rmdir(dirname($file)); }
        rmdir($this->root);
        parent::tearDown();
    }

    private function manifest(string $folder='Documents',array $changes=[]): void
    {
        $m=array_replace([
            'slug'=>'documentos','name'=>'Documentos','version'=>'1.0.0','owner'=>'Equipo documental',
            'core_min'=>'1.0.0','core_max'=>'1.99.99','entry'=>'sistemas','icon'=>'bi-file','company_scoped'=>true,
            'dependencies'=>[],'schema'=>['module_documents'=>['id','company_id']],'settings'=>[],
        ],$changes);
        mkdir($this->root.'/'.$folder);
        $file=$this->root.'/'.$folder.'/module.php';
        file_put_contents($file,'<?php return '.var_export($m,true).';');
        $this->files[]=$file;
    }

    private function snapshot(): array
    {
        return array_map(static fn($table)=>db_connect()->table($table)->orderBy('id')->get()->getResultArray(),['systems','company_systems','user_systems']);
    }

    public function testExistingRegistrationIsIdempotentAndPreservesEveryCatalogFieldAndGrant(): void
    {
        $this->manifest();
        $service=new ModuleInstallationService(new ModuleRegistry($this->root));
        $before=$this->snapshot();
        $this->assertFalse($service->register('documentos')['applied']);
        $this->assertSame(0,db_connect()->table('system_installations')->countAllResults());
        $this->assertTrue($service->register('documentos',true)['applied']);
        $service->register('documentos',true);
        $this->assertSame($before,$this->snapshot());
        $this->assertSame(1,db_connect()->table('system_installations')->countAllResults());
        $this->assertTrue($service->report()['systems'][0]['current']);
    }

    public function testNewRegistrationRemainsInactiveAndDoesNotGrantAccess(): void
    {
        $this->manifest('Other',['slug'=>'nuevo']);
        $service=new ModuleInstallationService(new ModuleRegistry($this->root));
        $before=$this->snapshot();
        $this->assertSame('create_inactive_catalog_entry',$service->register('nuevo')['operation']);
        $this->assertSame($before,$this->snapshot());
        $service->register('nuevo',true);
        $row=db_connect()->table('systems')->where('slug','nuevo')->get()->getRowArray();
        $this->assertSame(0,(int)$row['active']);
        $this->assertNotEmpty($row['id']);
        $this->assertSame($before[1],$this->snapshot()[1]);
        $this->assertSame($before[2],$this->snapshot()[2]);
    }

    public function testFailedContractCannotRegisterOrChangeCatalog(): void
    {
        $this->manifest('Bad',['entry'=>'ruta-inexistente','schema'=>['module_documents'=>['missing']]]);
        $registry=new ModuleRegistry($this->root);
        $check=$registry->check('documentos');
        $this->assertFalse($check['ok']);
        $this->assertContains('Ruta GET ausente: ruta-inexistente',$check['issues']);
        $this->assertContains('Columna ausente: module_documents.missing',$check['issues']);
        $before=$this->snapshot();
        try { (new ModuleInstallationService($registry))->register('documentos',true); $this->fail('Expected rejection'); }
        catch (RuntimeException $e) { $this->assertNotEmpty($e->getMessage()); }
        $this->assertSame($before,$this->snapshot());
    }

    public function testInvalidManifestDoesNotHideUnrelatedOrLegacySystems(): void
    {
        $this->manifest('Bad',['entry'=>'https://outside.test']);
        $this->manifest('Good',['slug'=>'nuevo']);
        $registry=new ModuleRegistry($this->root);
        $this->assertCount(1,$registry->errors());
        $this->assertNotNull($registry->get('nuevo'));
        $report=(new ModuleInstallationService($registry))->report();
        $this->assertCount(2,$report['systems']);
        $this->assertSame('documentos',$report['systems'][0]['slug']);
        $this->assertFalse($report['systems'][0]['technical']['ok']);
        $this->assertSame(site_url('ruta-elegida'),(new ModuleNavigation($registry))->entry($report['systems'][0]+['entry_url'=>'ruta-elegida'],'a'));
    }

    public function testDuplicateSlugsRejectAllContenders(): void
    {
        $this->manifest('A'); $this->manifest('B'); $this->manifest('C');
        $registry=new ModuleRegistry($this->root);
        $this->assertNull($registry->get('documentos'));
        $this->assertArrayHasKey('documentos',$registry->errors());
    }

    public function testDependenciesCyclesAndVersionMismatchAreReported(): void
    {
        $this->manifest('A',['dependencies'=>['other'=>'1.0.0']]);
        $this->manifest('B',['slug'=>'other','dependencies'=>['documentos'=>'1.0.0']]);
        $this->assertFalse((new ModuleRegistry($this->root))->check('documentos')['ok']);
    }

    public function testCoreIncompatibilityAndMissingDependencyAreReported(): void
    {
        $this->manifest('A',['core_min'=>'2.0.0','core_max'=>'2.99.99','dependencies'=>['absent'=>'1.0.0']]);
        $issues=(new ModuleRegistry($this->root))->check('documentos')['issues'];
        $this->assertContains('Versión de Core incompatible.',$issues);
        $this->assertContains('Dependencia ausente o incompatible: absent',$issues);
    }

    public function testUrlBuilderPreservesQueryFragmentCatalogOverrideAndExternalBoundary(): void
    {
        $this->manifest(); $nav=new ModuleNavigation(new ModuleRegistry($this->root));
        $this->assertSame(site_url('custom').'?tab=one&company_id=b#section',$nav->entry(['slug'=>'documentos','entry_url'=>'custom?tab=one&company_id=a#section'],'b'));
        $this->assertSame(site_url('custom').'?tag=a&tag=b&tab.name=c&company_id=b',$nav->url('custom?tag=a&company_id%5B%5D=a&tag=b&tab.name=c','b'));
        $this->assertSame('https://external.test/path',$nav->entry(['slug'=>'documentos','entry_url'=>'https://external.test/path'],'secret-company'));
        foreach (['javascript:alert(1)','//external.test','\\external.test','#',''] as $path) $this->assertSame('#',$nav->url($path,'a'));
    }

    public function testTrackingAbsentDoesNotBreakReadOnlyReport(): void
    {
        db_connect()->query('DROP TABLE '.db_connect()->prefixTable('system_installations'));
        array_pop($this->tables);
        db_connect()->resetDataCache();
        $this->manifest();
        $service=new ModuleInstallationService(new ModuleRegistry($this->root));
        $this->assertFalse($service->report()['tracking_available']);
        $before=$this->snapshot();
        try { $service->register('documentos',true); $this->fail('Expected migration prerequisite'); }
        catch (RuntimeException $e) { $this->assertStringContainsString('CodexCore',$e->getMessage()); }
        $this->assertSame($before,$this->snapshot());
    }

    public function testLegacySettingsRemainAvailableWithTheirPopupAndCompanyContext(): void
    {
        $settings=(new ModuleNavigation())->settings('a',null);
        $this->assertSame(['Inventario','Ventas','Impresión y Tickets'],array_column($settings,'name'));
        $this->assertTrue($settings[2]['popup']);
        foreach ($settings as $setting) $this->assertStringContainsString('company_id=a',$setting['url']);
    }

    public function testNavigationKeepsLegacySystemsAndFiltersCrossCompanyAndRevokedGrants(): void
    {
        $nav=new ModuleNavigation(new ModuleRegistry($this->root));
        $user=['id'=>'u','company_id'=>'a','role_slug'=>'operador'];
        // A caller cannot change this user's navigation company through the query string.
        $this->assertSame(['documentos'],array_column($nav->accessible($user,'b'),'slug'));
        db_connect()->table('user_systems')->where('id','user-grant')->update(['active'=>0]);
        $this->assertSame([],$nav->accessible($user,'a'));
        db_connect()->table('user_systems')->insert(['id'=>'foreign','system_id'=>'existing','company_id'=>'b','user_id'=>'u','active'=>1]);
        $this->assertSame([],$nav->accessible($user,'a'));
        $this->assertCount(1,$nav->accessible(['id'=>'root','role_slug'=>'superadmin'],null));
    }

    public function testNewSettingsAppearOnlyWithCompanyAndManageAssignment(): void
    {
        $this->manifest('A',['settings'=>[['name'=>'Documentos','path'=>'configuracion','description'=>'Reglas documentales','icon'=>'bi-file']]]);
        $nav=new ModuleNavigation(new ModuleRegistry($this->root));
        $user=['id'=>'u','company_id'=>'a','role_slug'=>'admin','branch_code'=>'MAIN','permissions'=>['settings.manage']];
        $this->assertCount(1,$nav->settings('a',$user));
        $this->assertSame([],$nav->settings('b',$user));
        db_connect()->table('user_systems')->where('id','user-grant')->update(['access_level'=>'view']);
        $this->assertSame([],$nav->settings('a',$user));
        $this->assertCount(1,$nav->settings('a',['id'=>'root','role_slug'=>'superadmin']));
        db_connect()->table('systems')->where('id','existing')->update(['active'=>0]);
        $this->assertSame([],$nav->settings('a',['id'=>'root','role_slug'=>'superadmin']));
    }

    public function testSavedVerificationDoesNotMaskSubsequentSchemaLoss(): void
    {
        $this->manifest();
        $service=new ModuleInstallationService(new ModuleRegistry($this->root));
        $service->register('documentos',true);
        db_connect()->query('DROP TABLE '.db_connect()->prefixTable('module_documents'));
        $this->tables=array_values(array_diff($this->tables,['module_documents']));
        db_connect()->resetDataCache();
        $row=$service->report()['systems'][0];
        $this->assertFalse($row['technical']['ok']);
        $this->assertFalse($row['current']);
    }

    public function testIsolatedMigrationNamespaceDiscoversOnlyItsOwnTable(): void
    {
        $runner=\Config\Services::migrations(null,null,false);
        $runner->setNamespace('CodexCore');
        $migrations=$runner->findMigrations();
        $this->assertCount(2,$migrations);
        $migration=array_values($migrations)[0];
        $this->assertSame('CodexCore',$migration->namespace);
        $this->assertContains('CreateSystemInstallations',array_column($migrations,'name'));
        $this->assertContains('CreateIntegrationOutbox',array_column($migrations,'name'));
    }

    public function testTrustedModuleRouteFileIsDiscoveredWithoutCatalogExecution(): void
    {
        $this->manifest();
        $folder=$this->root.'/Documents/Config'; mkdir($folder);
        $file=$folder.'/Routes.php';
        file_put_contents($file, '<?php $routes->get("module-probe", "\\App\\Controllers\\SystemsController::index", ["filter"=>"auth"]);');
        try {
            $registry=new ModuleRegistry($this->root);
            $routes=\Config\Services::routes(false);
            $registry->registerRoutes($routes);
            $this->assertArrayHasKey('module-probe',$routes->getRoutes('get'));
            $check=$registry->check('documentos',null,['sistemas'=>'\\App\\Controllers\\SystemsController::index']);
            $this->assertTrue($check['ok'],implode(' ',$check['issues']));
        } finally { unlink($file); rmdir($folder); }
    }

    public function testIntegrationReportRejectsAnAdminBeforeReadingGlobalMetadata(): void
    {
        $auth=$this->getMockBuilder(\App\Libraries\AuthService::class)->disableOriginalConstructor()->onlyMethods(['user'])->getMock();
        $auth->method('user')->willReturn(['id'=>'u','role_slug'=>'admin','company_id'=>'a']);
        \Config\Services::injectMock('auth',$auth);
        try {
            $controller=new \App\Controllers\ModuleIntegrationController();
            $request=new \CodeIgniter\HTTP\IncomingRequest(config('App'),new \CodeIgniter\HTTP\URI('http://example.com/sistemas/integracion'),null,new \CodeIgniter\HTTP\UserAgent());
            $controller->initController($request,\Config\Services::response(),\Config\Services::logger());
            $response=$controller->index();
            $this->assertSame(403,$response->getStatusCode());
        } finally { \Config\Services::resetSingle('auth'); }
    }
}
