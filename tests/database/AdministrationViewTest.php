<?php
use CodeIgniter\Test\CIUnitTestCase;
require_once __DIR__.'/../_support/UiPreview.php';
final class AdministrationViewTest extends CIUnitTestCase
{
    public function testDirectoriesKeepActionsAndSingleRuntime(): void
    {
        helper(['app','auth','url','form']);
        $companies=$users=$systems=$taxes=[];
        for($i=1;$i<=12;$i++) {
            $companies[]=['id'=>'c'.$i,'name'=>'Empresa '.$i,'tax_id'=>'TEST'.$i,'currency_code'=>'ARS','active'=>1];
            $users[]=['id'=>'u'.$i,'name'=>'Persona '.$i,'username'=>'persona'.$i,'email'=>'p'.$i.'@example.test','active'=>1,'role_slug'=>'operator','role_name'=>'Operador','company_id'=>'c1','company_name'=>'Empresa 1','branch_name'=>'Central'];
            $systems[]=['id'=>'s'.$i,'name'=>'Sistema '.$i,'slug'=>'sistema'.$i,'icon'=>'bi-grid','entry_url'=>'sistema'.$i,'active'=>1,'description'=>'Sistema de prueba'];
            $taxes[]=['id'=>'t'.$i,'name'=>'Impuesto '.$i,'code'=>'T'.$i,'rate'=>21,'active'=>1];
        }
        $cases=[
            'users'=>['usuarios',['users'=>$users,'canManageUsers'=>true,'canDisableOrDeleteUsers'=>true]],
            'companies'=>['empresas',['companies'=>$companies,'canCreate'=>true,'canDisable'=>true]],
            'systems'=>['sistemas',['companies'=>[],'selectedCompany'=>null,'selectedCompanyId'=>'c1','isSuperadmin'=>true,'canManageSystems'=>true,'accessibleSystems'=>[],'catalogSystems'=>$systems,'companyAssignments'=>[],'operatorAssignments'=>[]]],
            'settings'=>['configuracion',['company'=>$companies[0],'companies'=>$companies,'branches'=>[],'taxes'=>$taxes,'currencies'=>[],'voucherSequences'=>[],'paymentMethods'=>[]]],
        ];
        foreach($cases as $view=>[$route,$data]) {
            service('request')->getUri()->setPath('/'.$route);
            $html=view($view.'/index',$data,['saveData'=>false]);
            $this->assertStringContainsString('data-codex-ui="light"',$html);
            $this->assertSame(1,substr_count($html,'csp-3.17.4.min.js'));
            $this->assertStringContainsString('x-data="codexTablePages"',$html);
            $this->assertStringContainsString('x-data="codexNavigation"',$html);
            $this->assertStringContainsString('codex-controls.js',$html);
            $this->assertStringNotContainsString('bootstrap.bundle.min.js',$html);
            $this->assertStringContainsString('x-data="codexPopup"',$html);
            $this->assertStringNotContainsString('const buildPagination =',$html);
            captureUiPreview('admin-'.$view,$html);
        }
        service('request')->getUri()->setPath('/usuarios');
        $html=view('users/index',['users'=>$users,'canManageUsers'=>false,'canDisableOrDeleteUsers'=>false],['saveData'=>false]);
        $this->assertStringNotContainsString('usuarios/u1/editar',$html);
        $this->assertStringNotContainsString('usuarios/u1/eliminar',$html);
        $this->assertStringContainsString('Solo lectura',$html);
    }
}
