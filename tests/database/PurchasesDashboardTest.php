<?php
use App\Libraries\PurchasesDashboard;
use CodeIgniter\Test\CIUnitTestCase;

final class PurchasesDashboardTest extends CIUnitTestCase
{
    public function testInlineActionsReturnJsonAndRenewCsrfWithoutRedirect(): void
    {
        helper('url');
        $request = service('request');
        $request->setHeader('X-Requested-With', 'XMLHttpRequest');
        try {
            $controller = new \App\Controllers\PurchasesController();
            $controller->initController($request, service('response'), service('logger'));
            $method = new ReflectionMethod($controller, 'purchaseActionResponse');
            $method->setAccessible(true);
            foreach ([true, false] as $ok) {
                $response = $method->invoke($controller, 'demo', 'Resultado', $ok);
                $body = json_decode($response->getBody(), true);
                $this->assertSame($ok ? 200 : 422, $response->getStatusCode());
                $this->assertSame($ok, $body['ok']);
                $this->assertSame('Resultado', $body['message']);
                $this->assertNotEmpty($body['csrfName']);
                $this->assertNotEmpty($body['csrfHash']);
                $this->assertFalse($response->hasHeader('Location'));
            }
        } finally {
            $request->removeHeader('X-Requested-With');
        }
    }

    private function emptyData(): array
    {
        return array_fill_keys(['suppliers','orders','receipts','payables','invoices','creditNotes','costHistory'], []);
    }

    public function testPeriodSupplierAndCurrencySeparation(): void
    {
        $data = $this->emptyData();
        $data['suppliers'] = [['id'=>'a','active'=>1],['id'=>'b','active'=>1]];
        $data['orders'] = [
            ['supplier_id'=>'a','issued_at'=>'2026-10-03 23:59:59','status'=>'received_partial'],
            ['supplier_id'=>'a','issued_at'=>'2026-09-30','status'=>'draft'],
            ['supplier_id'=>'b','issued_at'=>'2026-10-03','status'=>'draft'],
        ];
        foreach (['ARS'=>100,'USD'=>20] as $currency=>$amount) {
            $data['payables'][] = ['supplier_id'=>'a','created_at'=>'2020-01-01','status'=>'pending','balance_amount'=>$amount,'currency_code'=>$currency,'due_date'=>'2026-10-02'];
        }
        $data['payables'][] = ['supplier_id'=>'a','status'=>'pending','balance_amount'=>-10,'currency_code'=>'ARS','due_date'=>'2026-10-02'];
        $result = PurchasesDashboard::prepare($data,['supplier_id'=>'a','from'=>'2026-10-01','to'=>'2026-10-03'],'2026-10-03');
        $this->assertCount(1,$result['orders']);
        $this->assertSame(1,$result['summary']['orders_approved']);
        $this->assertSame(0,$result['summary']['orders_draft']);
        $this->assertSame(2,$result['summary']['overdue']);
        $this->assertSame(['ARS'=>100.0,'USD'=>20.0],$result['summary']['balances']);
        $this->assertCount(3,$result['payables']);
        $this->assertCount(2,$result['supplierOptions']);
    }

    public function testFiltersNormalizeReversedAndInvalidDates(): void
    {
        $filters = PurchasesDashboard::filters(['from'=>'2026-10-04','to'=>'2026-10-01','supplier_id'=>[]]);
        $this->assertSame('2026-10-01',$filters['from']);
        $this->assertSame('2026-10-04',$filters['to']);
        $this->assertSame('',$filters['supplier_id']);
        $filters = PurchasesDashboard::filters(['from'=>'2026-02-31']);
        $this->assertNotSame('2026-02-31',$filters['from']);
    }

    public function testDashboardAndEveryFormRenderWithoutChangingActions(): void
    {
        helper(['app','auth','url']);
        $company = ['id'=>'demo','name'=>'Empresa Demo'];
        $base = ['pageTitle'=>'Compras','context'=>['company'=>$company,'canManage'=>true], 'companies'=>[$company], 'selectedCompanyId'=>'demo','companyId'=>'demo'];
        $data = PurchasesDashboard::prepare($base + $this->emptyData(), ['supplier_id'=>'','from'=>'2026-10-01','to'=>'2026-10-03']);
        $html = view('purchases/index',$data,['saveData'=>false]);
        $this->assertSame(6,substr_count($html,'class="insight-kpi '));
        $this->assertSame(7,substr_count($html,'data-purchases-table'));
        $this->assertStringContainsString('purchases-filters',$html);
        foreach (['supplier','order','receipt','return','payment','invoice','credit_note'] as $form) {
            $values = $base + [
                'isPopup'=>true,'formAction'=>'/compras/test-save','suppliers'=>[], 'warehouses'=>[], 'products'=>[], 'currencyOptions'=>['ARS'=>'ARS'],
                'order'=>['id'=>'order','order_number'=>'OC-1'], 'receipt'=>['id'=>'receipt','receipt_number'=>'REC-1'],
                'payable'=>['id'=>'payable','payable_number'=>'CP-1','balance_amount'=>100,'currency_code'=>'ARS'],
                'items'=>[], 'gateways'=>[], 'checks'=>[], 'receipts'=>[], 'invoices'=>[], 'fromReceiptItems'=>[],
            ];
            $html = view('purchases/forms/'.$form,$values,['saveData'=>false]);
            $this->assertStringContainsString('purchases-shell',$html,$form);
            $this->assertStringContainsString('action="/compras/test-save?popup=1"',$html,$form);
            $this->assertStringContainsString('name="company_id"',$html,$form);
        }
        $data['context']['canManage'] = false;
        $html = view('purchases/index',$data,['saveData'=>false]);
        $this->assertStringNotContainsString('compras/ordenes/nueva',$html);
    }
}
