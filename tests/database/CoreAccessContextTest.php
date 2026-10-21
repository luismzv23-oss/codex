<?php

use App\Libraries\AuthService;
use App\Libraries\Core\{AccessException, ActorContext, CompanyContext};
use App\Filters\{ApiAuthFilter, AuthFilter};
use CodeIgniter\HTTP\{IncomingRequest, URI, UserAgent};
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

final class CoreAccessContextTest extends CIUnitTestCase
{
    private array $tables=[];

    protected function setUp(): void
    {
        parent::setUp();
        helper(['auth','url','app']);
        session()->remove(['auth_user','active_company_id']);
        foreach ([
            'companies'=>'id TEXT PRIMARY KEY, name TEXT, active INTEGER',
            'branches'=>'id TEXT PRIMARY KEY, company_id TEXT, name TEXT, code TEXT',
            'roles'=>'id TEXT PRIMARY KEY, slug TEXT, name TEXT',
            'permissions'=>'id TEXT PRIMARY KEY, slug TEXT',
            'role_permission'=>'id TEXT PRIMARY KEY, role_id TEXT, permission_id TEXT',
            'users'=>'id TEXT PRIMARY KEY, company_id TEXT, branch_id TEXT, role_id TEXT, name TEXT, username TEXT, email TEXT, active INTEGER, password_hash TEXT, must_change_password INTEGER',
            'systems'=>'id TEXT PRIMARY KEY, name TEXT, slug TEXT, active INTEGER',
            'company_systems'=>'id TEXT PRIMARY KEY, company_id TEXT, system_id TEXT, active INTEGER',
            'user_systems'=>'id TEXT PRIMARY KEY, company_id TEXT, user_id TEXT, system_id TEXT, access_level TEXT, active INTEGER',
            'inventory_settings'=>'id TEXT PRIMARY KEY, company_id TEXT',
            'inventory_warehouses'=>'id TEXT PRIMARY KEY, company_id TEXT',
        ] as $table=>$schema) {
            db_connect()->query('CREATE TABLE '.db_connect()->prefixTable($table).' ('.$schema.')');
            $this->tables[]=$table;
        }
        foreach (['a','b'] as $id) {
            db_connect()->table('companies')->insert(['id'=>$id,'name'=>$id,'active'=>1]);
            db_connect()->table('branches')->insert(['id'=>$id,'company_id'=>$id,'code'=>'MAIN','name'=>'Central']);
            db_connect()->table('inventory_settings')->insert(['id'=>$id,'company_id'=>$id]);
            db_connect()->table('inventory_warehouses')->insert(['id'=>$id,'company_id'=>$id]);
        }
        db_connect()->table('systems')->insert(['id'=>'inventory','slug'=>'inventario','name'=>'Inventario','active'=>1]);
        db_connect()->table('company_systems')->insert(['id'=>'enabled','company_id'=>'a','system_id'=>'inventory','active'=>1]);
        foreach (['superadmin','admin','operador','vendedor'] as $role) {
            db_connect()->table('roles')->insert(['id'=>$role,'slug'=>$role,'name'=>$role]);
            db_connect()->table('role_permission')->insert(['id'=>$role,'role_id'=>$role,'permission_id'=>'systems.view']);
        }
        db_connect()->table('permissions')->insert(['id'=>'systems.view','slug'=>'systems.view']);
        db_connect()->table('permissions')->insert(['id'=>'systems.manage','slug'=>'systems.manage']);
        db_connect()->table('role_permission')->insert(['id'=>'admin-manage','role_id'=>'admin','permission_id'=>'systems.manage']);
        db_connect()->table('users')->insert(['id'=>'user','company_id'=>'a','branch_id'=>'a','role_id'=>'operador','name'=>'Actual','active'=>1,'password_hash'=>'must-not-enter-context','email'=>'user@example.test']);
        db_connect()->table('user_systems')->insert(['id'=>'assigned','company_id'=>'a','system_id'=>'inventory','user_id'=>'user','access_level'=>'manage','active'=>1]);
    }

    protected function tearDown(): void
    {
        session()->remove(['auth_user','active_company_id']);
        Services::resetSingle('auth'); Services::resetSingle('systemAccess');
        foreach (array_reverse($this->tables) as $table) db_connect()->query('DROP TABLE '.db_connect()->prefixTable($table));
        parent::tearDown();
    }

    private function request(array $get=[],array $post=[],?string $body=null): IncomingRequest
    {
        $request=new IncomingRequest(config('App'),new URI('http://example.com/inventario'),$body,new UserAgent());
        $request->setGlobal('get',$get); $request->setGlobal('post',$post);
        if ($body !== null) $request->setHeader('Content-Type','application/json');
        return $request;
    }

    private function authenticate(IncomingRequest $request, string $transport='session'): AuthService
    {
        $auth=new AuthService(); Services::injectMock('auth',$auth);
        if ($transport==='session') {
            // All claims except the identifier are deliberately stale and privileged.
            session()->set('auth_user',['id'=>'user','role_slug'=>'superadmin','company_id'=>'b','permissions'=>['systems.manage']]);
            $this->assertNull((new AuthFilter())->before($request));
        } else {
            session()->remove('auth_user');
            $token=(new \App\Libraries\JwtService())->generateAccessToken(['id'=>'user','role_slug'=>'superadmin','company_id'=>'b']);
            $request->setHeader('Authorization','Bearer '.$token);
            $this->assertNull((new ApiAuthFilter())->before($request));
            $this->assertNull(session()->get('auth_user'),'Bearer authentication must not create a web login');
        }
        return $auth;
    }

    private function probe(string $channel, IncomingRequest $request, string $action)
    {
        if ($channel==='web') {
            $controller=new class extends \App\Controllers\InventoryController {
                public function probe(string $action) { return $this->inventoryContext($action); }
            };
        } else {
            $controller=new class extends \App\Controllers\Api\V1\InventoryController {
                public function probe(string $action) { return $this->inventoryContext($action); }
            };
        }
        $controller->initController($request,service('response'),service('logger'));
        return $controller->probe($action);
    }

    public function roleMatrix(): array
    {
        return [
            'superadmin bypass'=>['superadmin',null,[true,true,true]],
            'admin management'=>['admin','manage',[true,true,true]],
            'admin consultation'=>['admin','view',[true,false,false]],
            'operator management'=>['operador','manage',[true,true,false]],
            'operator consultation'=>['operador','view',[true,false,false]],
            'seller restriction'=>['vendedor','manage',[false,false,false]],
        ];
    }

    /** @dataProvider roleMatrix */
    public function testInventoryWebAndApiShareTheSameRoleAndActionDecisions(string $role,?string $level,array $allowed): void
    {
        db_connect()->table('users')->where('id','user')->update(['role_id'=>$role]);
        if ($level===null) {
            db_connect()->table('company_systems')->where('id','enabled')->update(['active'=>0]);
            db_connect()->table('user_systems')->where('id','assigned')->update(['active'=>0]);
        } else db_connect()->table('user_systems')->where('id','assigned')->update(['access_level'=>$level]);
        foreach (['session','bearer'] as $transport) foreach (['view','manage','configure'] as $index=>$action) {
            $request=$this->request(['company_id'=>'a']); $this->authenticate($request,$transport);
            $web=$this->probe('web',$request,$action); $api=$this->probe('api',$request,$action);
            if ($allowed[$index]) {
                $this->assertIsArray($web); $this->assertArrayNotHasKey('error',$api);
                $this->assertSame($web,$api);
                $this->assertSame('a',$api['company']['id']);
            } else {
                $this->assertInstanceOf(RedirectResponse::class,$web);
                $this->assertSame(403,$api['status']);
                $this->assertSame(session()->getFlashdata('error'),$api['error']);
            }
        }
        $this->assertSame(1,db_connect()->table('company_systems')->countAllResults());
        $this->assertSame(1,db_connect()->table('user_systems')->countAllResults());
    }

    public function testConflictingAndForeignCompanySourcesAreRejectedAcrossTransports(): void
    {
        foreach (['session','bearer'] as $transport) {
            foreach ([$this->request(['company_id'=>'a'],['company_id'=>'b']),$this->request(['company_id'=>'a'],[], '{"company_id":"b"}'),$this->request(['company_id'=>['a']])] as $request) {
                $this->authenticate($request,$transport);
                $this->assertInstanceOf(RedirectResponse::class,$this->probe('web',$request,'view'));
                $this->assertSame(422,$this->probe('api',$request,'view')['status']);
            }
            $request=$this->request([],[], '{"company_id":"b"}'); $this->authenticate($request,$transport);
            $this->assertSame(403,$this->probe('api',$request,'view')['status']);
            $request=$this->request(['company_id'=>'a']); $request->setHeader('X-Company-Id','b'); $this->authenticate($request,$transport);
            $this->assertSame(422,$this->probe('api',$request,'view')['status']);
        }
    }

    public function testRevokedAssignmentCompanySystemAndRolePermissionTakeEffect(): void
    {
        foreach (['session','bearer'] as $transport) foreach (['user_systems','company_systems','systems','companies'] as $table) {
            $request=$this->request(['company_id'=>'a']); $this->authenticate($request,$transport);
            db_connect()->table($table)->update(['active'=>0]);
            $web=$this->probe('web',$request,'view'); $api=$this->probe('api',$request,'view');
            $this->assertInstanceOf(RedirectResponse::class,$web);
            $this->assertContains($api['status'],[403,404]);
            db_connect()->table($table)->update(['active'=>1]);
        }
        db_connect()->table('role_permission')->where('role_id','operador')->delete();
        foreach (['session','bearer'] as $transport) {
            $request=$this->request(['company_id'=>'a']); $this->authenticate($request,$transport);
            $this->assertSame(403,$this->probe('api',$request,'view')['status']);
            $this->assertInstanceOf(RedirectResponse::class,$this->probe('web',$request,'view'));
        }
    }

    public function testSessionRefreshesRoleAndBranchAndNeverCachesSecretsAcrossRequests(): void
    {
        db_connect()->table('users')->where('id','user')->update(['role_id'=>'admin']);
        $request=$this->request(); $auth=$this->authenticate($request);
        $this->assertTrue($auth->can('systems.manage'));
        $this->assertArrayNotHasKey('password_hash',$auth->user());
        db_connect()->table('branches')->where('id','a')->update(['code'=>'OTHER']);
        $this->assertTrue($auth->can('systems.manage'),'Snapshot lasts only for the current request');
        $next=new AuthService(); Services::injectMock('auth',$next);
        $this->assertFalse($next->can('systems.manage'));
        $this->assertFalse(ActorContext::current()->hasPermission('systems.manage'));
        db_connect()->table('users')->where('id','user')->update(['role_id'=>'operador']);
        $next=new AuthService(); Services::injectMock('auth',$next);
        $this->assertSame('operador',$next->user()['role_slug']);
        $next->logout(); $this->assertNull($next->user());
    }

    public function testInactiveOrDeletedIdentityCannotReuseSessionOrBearerClaims(): void
    {
        db_connect()->table('users')->where('id','user')->update(['active'=>0]);
        $auth=new AuthService(); Services::injectMock('auth',$auth);
        session()->set('auth_user',['id'=>'user','role_slug'=>'superadmin']);
        $this->assertFalse($auth->check()); $this->assertNull(session()->get('auth_user'));
        $request=$this->request();
        $token=(new \App\Libraries\JwtService())->generateAccessToken(['id'=>'user','role_slug'=>'superadmin']);
        $request->setHeader('Authorization','Bearer '.$token);
        $this->assertSame(401,(new ApiAuthFilter())->before($request)->getStatusCode());
        db_connect()->table('users')->where('id','user')->delete();
        $this->assertFalse((new AuthService())->authenticateRequest('user'));
    }

    public function testCompanyResourcesAndSuperadminFallbackRemainScoped(): void
    {
        $this->authenticate($this->request()); $actor=ActorContext::current(); $company=CompanyContext::resolve($actor,null);
        $company->assertResource(['company_id'=>'a']);
        try { $company->assertResource(['company_id'=>'b']); $this->fail('Foreign resource allowed'); }
        catch (AccessException $e) { $this->assertSame(404,$e->getCode()); }
        db_connect()->table('users')->where('id','user')->update(['role_id'=>'superadmin','company_id'=>null]);
        db_connect()->table('companies')->where('id','a')->update(['active'=>0]);
        $request=$this->request(); $this->authenticate($request);
        $this->assertSame('b',$this->probe('api',$request,'view')['company']['id']);
        $this->assertSame('b',$this->probe('web',$request,'view')['company']['id']);
        $request=$this->request(['company_id'=>'missing']);
        $this->assertSame(404,$this->probe('api',$request,'view')['status']);
    }

    public function testCompanyContextCannotBeReusedAfterUserChangesCompany(): void
    {
        $this->authenticate($this->request());
        $old=CompanyContext::resolve(ActorContext::current(),'a');
        db_connect()->table('users')->where('id','user')->update(['company_id'=>'b','branch_id'=>'b']);
        Services::injectMock('auth',new AuthService());
        try {
            service('systemAccess')->authorize(ActorContext::current(),$old,'inventario','view');
            $this->fail('Reused company context was accepted');
        } catch (AccessException $e) { $this->assertSame(403,$e->getCode()); }
    }

    public function testInvalidJsonAndUnknownActionsFailClosedWithoutDefaults(): void
    {
        $request=$this->request([],[],'{invalid'); $this->authenticate($request);
        $this->assertSame(422,$this->probe('api',$request,'view')['status']);
        $request=$this->request(['company_id'=>'a']); $this->authenticate($request);
        $this->assertSame(422,$this->probe('api',$request,'delete-everything')['status']);
        $this->assertSame(2,db_connect()->table('inventory_settings')->countAllResults());
        $this->assertSame(2,db_connect()->table('inventory_warehouses')->countAllResults());
    }
}
