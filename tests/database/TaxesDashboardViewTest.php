<?php
use CodeIgniter\Test\CIUnitTestCase;

final class TaxesDashboardViewTest extends CIUnitTestCase
{
    private function data(): array
    {
        $company = ['id'=>'demo','name'=>'Empresa Demo'];
        $record = ['fecha'=>'2026-10-04','neto_gravado'=>100,'iva_21'=>21,'total'=>121,'cae'=>'12345678901234'];
        return ['pageTitle'=>'Impuestos','isPopup'=>false,'context'=>['company'=>$company], 'companies'=>[$company],'selectedCompanyId'=>'demo',
            'filters'=>['from'=>'2026-10-01','to'=>'2026-10-04'],
            'ivaVentas'=>['count'=>7,'records'=>array_fill(0,7,$record),'totals'=>['neto_gravado'=>700,'iva'=>147,'total'=>847]],
            'ivaCompras'=>['count'=>0,'records'=>[],'totals'=>[]],
            'sicoreSummary'=>['withholdings'=>[],'perceptions'=>[],'withholdings_total'=>10,'perceptions_total'=>20,'withholdings_count'=>1,'perceptions_count'=>2]];
    }

    public function testIndicatorsTablesTotalsAndExportFilters(): void
    {
        helper(['app','auth','url']);
        $html = view('taxes/index',$this->data(),['saveData'=>false]);
        $this->assertSame(6,substr_count($html,'class="insight-kpi '));
        $this->assertSame(3,substr_count($html,'data-tax-table'));
        $this->assertStringContainsString('data-tax-total',$html);
        $this->assertStringContainsString('147,00',$html);
        $this->assertStringContainsString('847,00',$html);
        $this->assertSame(7,substr_count($html,'12345678901234'));
        foreach (['iva-ventas/cbte','iva-ventas/alicuotas','iva-compras/cbte','iva-compras/alicuotas','sicore/retenciones/txt','sicore/percepciones/txt'] as $route) {
            $this->assertStringContainsString('impuestos/'.$route.'?company_id=demo&from=2026-10-01&to=2026-10-04',$html);
        }
    }

    public function testEmptyPopupHasNoBanner(): void
    {
        helper(['app','auth','url']);
        $data=$this->data();$data['isPopup']=true;$data['ivaVentas']=['count'=>0,'records'=>[],'totals'=>[]];
        $html=view('taxes/index',$data,['saveData'=>false]);
        $this->assertStringNotContainsString('<header class="insight-hero">',$html);
        $this->assertStringContainsString('Sin comprobantes de venta',$html);
        $this->assertStringContainsString('Sin retenciones ni percepciones',$html);
    }
}
