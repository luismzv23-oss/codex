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

    public function testHiddenPaymentDoesNotAppearAndTotalIsUnchanged(): void
    {
        helper('url');
        $data = PosTicketDesign::preview(['name'=>'Demo']);
        $data['payments'][0]['show_on_receipt'] = 0;
        $html = view('sales/pdf/pos', $data);
        $this->assertStringNotContainsString('TARJETA', $html);
        $this->assertStringNotContainsString('Recargos por medios de pago', $html);
        $this->assertStringContainsString('12.350,00', $html);
    }

    public function testQrCanBeEmbeddedOfflineInPdf(): void
    {
        $image = PosTicketDesign::qrDataUri('VISTA PREVIA');
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $image);
        $this->assertStringContainsString('<svg', base64_decode(explode(',', $image, 2)[1]));
    }
}
