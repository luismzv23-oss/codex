<?php
use CodeIgniter\Test\CIUnitTestCase;

final class SalesDashboardViewTest extends CIUnitTestCase
{
    public function testSalesDashboardMaintainsRoleActionsAndSixIndicators(): void
    {
        helper(['app','auth','url']);
        $company=['id'=>'demo','name'=>'Empresa Demo'];
        $summary=array_fill_keys(['drafts','confirmed','cancelled','returned','standard','kiosk','total_amount','receivable_balance','receivable_pending'],0);
        $data=['isPopup'=>false,'pageTitle'=>'Ventas','context'=>['company'=>$company,'canManage'=>true], 'user'=>['role_slug'=>'admin'], 'companies'=>[$company], 'selectedCompanyId'=>'demo','summary'=>$summary,'filters'=>[],'customers'=>[],'sales'=>[],'priceLists'=>[],'promotions'=>[]];
        $html=view('sales/index',$data,['saveData'=>false]);
        $this->assertSame(6,substr_count($html,'class="insight-kpi '));
        $this->assertStringContainsString('data-sales-table',$html);
        $this->assertStringContainsString('sales-filters',$html);
        $this->assertStringContainsString('ventas/pos',$html);
        $this->assertStringContainsString('ventas/kiosco',$html);
        $data['user']['role_slug']='vendedor';$data['context']['canManage']=false;
        $html=view('sales/index',$data,['saveData'=>false]);
        $this->assertStringNotContainsString('Comprobantes ARCA',$html);
        $this->assertStringNotContainsString('ventas/vendedores/nuevo',$html);
        $this->assertStringContainsString('ventas/pos',$html);
    }

    public function testPopupFormsDoNotHaveBannerOrLiveRefresh(): void
    {
        helper(['app','auth','url']);
        foreach(['agent','zone','condition','customer'] as $form){
            $html=view('sales/forms/'.$form,['pageTitle'=>'Formulario','isPopup'=>true,'companyId'=>'demo','formAction'=>'/test-save','agents'=>[],'zones'=>[],'conditions'=>[],'branches'=>[]],['saveData'=>false]);
            $this->assertStringNotContainsString('<header class="insight-hero',$html);
            $this->assertStringContainsString('data-sales-live="0"',$html);
            $this->assertStringContainsString('method="post"',$html);
            $this->assertStringContainsString('/test-save',$html);
        }
    }

    public function testActivityChartRendersEmptyAndSingleDay(): void
    {
        $html=view('sales/activity_chart',['report'=>['daily_series'=>[]]],['saveData'=>false]);
        $this->assertStringContainsString('No hay ventas',$html);
        $html=view('sales/activity_chart',['report'=>['daily_series'=>[['report_date'=>'2026-10-04','orders_count'=>3]]]],['saveData'=>false]);
        $this->assertStringContainsString('<circle',$html);
        $this->assertStringContainsString('3 ventas',$html);
    }
}
