<?php

use App\Libraries\PosTicketDesign;
use CodeIgniter\Test\CIUnitTestCase;

final class PosTicketDesignTest extends CIUnitTestCase
{
    public function testVisibilityAndEscapingPreserveSaleTotal(): void
    {
        helper('url');
        $data = PosTicketDesign::preview(['name'=>'Empresa <script>', 'legal_name'=>'Empresa <script>']);
        $data['ticketSettings'] = ['ticket_show_customer'=>0, 'ticket_show_sku'=>0, 'ticket_show_qr'=>0];
        $html = view('sales/pdf/pos', $data);
        $this->assertStringContainsString('Empresa &lt;script&gt;', $html);
        $this->assertStringContainsString('data-pos-block="show_customer" style="display:none"', $html);
        $this->assertStringContainsString('data-pos-block="show_sku" style="display:none"', $html);
        $this->assertStringContainsString('12.350,00', $html);
        $this->assertSame(12350, $data['sale']['total']);
    }

    public function testRealDocumentWithoutAuthorizationDoesNotInventFiscalData(): void
    {
        helper('url');
        $data = PosTicketDesign::preview(['name'=>'Demo']);
        $data['preview'] = false;
        $data['fiscal'] = [];
        $data['qrDataUri'] = null;
        $html = view('sales/pdf/pos', $data);
        $this->assertStringContainsString('SIN AUTORIZACIÓN FISCAL DISPONIBLE', $html);
        $this->assertStringNotContainsString('EJEMPLO SIN VALIDEZ', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertNull(PosTicketDesign::qrDataUri(null));
    }

    public function testPaymentFlagNeverPrintsSurchargeAndTotalIsUnchanged(): void
    {
        helper('url');
        $data = PosTicketDesign::preview(['name'=>'Demo']);
        foreach ([0, 1] as $flag) {
            $data['payments'][0]['show_on_receipt'] = $flag;
            $html = view('sales/pdf/pos', $data);
            if ($flag === 1) {
                $this->assertStringContainsString('TARJETA', $html);
            } else {
                $this->assertStringNotContainsString('TARJETA', $html);
            }
            $this->assertStringNotContainsString('Recargo', $html);
            $this->assertStringContainsString('12.350,00', $html);
        }
    }

    public function testMixedPaymentVisibilityIsIndependent(): void
    {
        helper('url');
        $data = PosTicketDesign::preview(['name'=>'Demo']);
        foreach ([0, 1] as $cashVisible) {
            $data['payments'] = [
                ['payment_method_code'=>'EFECTIVO_PRUEBA','amount'=>1000,'show_on_receipt'=>$cashVisible],
                ['payment_method_code'=>'DEBITO_PRUEBA','amount'=>11350,'show_on_receipt'=>1-$cashVisible],
            ];
            $html = view('sales/pdf/pos', $data);
            $this->assertSame($cashVisible === 1, str_contains($html, 'EFECTIVO_PRUEBA'));
            $this->assertSame($cashVisible === 0, str_contains($html, 'DEBITO_PRUEBA'));
            $this->assertStringNotContainsString('Recargo', $html);
            $this->assertStringContainsString('12.350,00', $html);
        }
    }

    public function testQrCanBeEmbeddedOfflineInPdf(): void
    {
        $image = PosTicketDesign::qrDataUri('VISTA PREVIA');
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $image);
        $this->assertStringContainsString('<svg', base64_decode(explode(',', $image, 2)[1]));
    }

    public function testAuthorizedReceiptAlwaysShowsFiscalSection(): void
    {
        helper('url');
        $data = PosTicketDesign::preview(['name'=>'Demo']);
        $data['preview'] = false;
        $data['fiscal']['cae'] = '12345678901234';
        $data['ticketSettings'] = ['ticket_show_qr'=>0, 'ticket_show_authorization'=>0];
        $html = view('sales/pdf/pos', $data);
        $this->assertStringNotContainsString('data-pos-block="show_qr" style="display:none"', $html);
        $this->assertStringNotContainsString('data-pos-block="show_authorization" style="display:none"', $html);
        $this->assertStringContainsString('12345678901234', $html);
        $this->assertStringContainsString('alt="QR fiscal"', $html);
    }
}
