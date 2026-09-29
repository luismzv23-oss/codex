<?php

use App\Libraries\ArcaService;
use CodeIgniter\Test\CIUnitTestCase;

final class ArcaPaymentSurchargeTest extends CIUnitTestCase
{
    public function testFiscalTotalUsesSavedSurchargeWithoutChangingSale(): void
    {
        $sale = ['total' => 38080, 'payment_surcharge_amount' => 500];
        $this->assertSame(37580.0, ArcaService::fiscalTotal($sale));
        $this->assertSame(38080, $sale['total']);
        $this->assertSame(121.0, ArcaService::fiscalTotal(['total' => 121]));
        $this->assertSame(12440.27, ArcaService::fiscalTotal(['total' => 12751.28, 'payment_surcharge_amount' => 311.01]));
    }

    public function testBothFiscalPayloadsExcludePaymentSurcharge(): void
    {
        $sale = ['total' => 123.50, 'subtotal' => 100, 'tax_total' => 21, 'payment_surcharge_amount' => 2.50];
        $method = new ReflectionMethod(ArcaService::class, 'buildPayloadPreview');
        $method->setAccessible(true);
        foreach (['wsfev1', 'wsmtxca'] as $service) {
            $payload = $method->invoke(new ArcaService(), $sale, ['category' => 'invoice', 'afip_code' => 6], [], [], [], [], ['slug' => $service]);
            $this->assertSame(121.0, $payload['imp_total']);
            $this->assertEquals(100, $payload['imp_neto']);
            $tax = $service === 'wsfev1' ? $payload['imp_iva'] : array_sum(array_column($payload['iva_subtotals'], 'importe'));
            $this->assertEquals(21, $tax);
            $this->assertArrayNotHasKey('payment_surcharge_amount', $payload);
        }
    }

    public function testInvalidSurchargeCannotProduceNegativeFiscalAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ArcaService::fiscalTotal(['total' => 100, 'payment_surcharge_amount' => 101]);
    }
}
