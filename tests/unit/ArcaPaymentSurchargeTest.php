<?php

use App\Libraries\ArcaService;
use CodeIgniter\Test\CIUnitTestCase;

final class ArcaPaymentSurchargeTest extends CIUnitTestCase
{
    public function testCheckboxControlsFiscalTotalAndBalancesBothPayloads(): void
    {
        $sale = ['total'=>2255, 'subtotal'=>1818.18, 'tax_total'=>381.82, 'payment_surcharge_amount'=>55];
        $items = [['quantity'=>1, 'unit_price'=>2200, 'subtotal'=>1818.18, 'tax_total'=>381.82, 'tax_rate'=>21, 'line_total'=>2200]];
        $method = new ReflectionMethod(ArcaService::class, 'buildPayloadPreview');
        $method->setAccessible(true);
        foreach ([0=>2200.0, 1=>2255.0] as $flag=>$expected) {
            [$fiscal, $details] = \App\Libraries\PaymentFiscalPolicy::apply($sale, $items, [['show_on_receipt'=>$flag, 'surcharge_amount'=>55]]);
            $this->assertSame($expected, ArcaService::fiscalTotal($fiscal));
            foreach (['wsfev1','wsmtxca'] as $service) {
                $payload = $method->invoke(new ArcaService(), $fiscal, ['afip_code'=>6], [], [], $details, [], ['slug'=>$service]);
                $tax = $payload['imp_iva'] ?? array_sum(array_column($payload['iva_subtotals'], 'importe'));
                $this->assertSame($expected, $payload['imp_total']);
                $this->assertEquals($expected, round($payload['imp_neto'] + $tax, 2));
            }
        }
        $this->assertSame(2200, $items[0]['line_total']);
        $this->assertSame(2255, $sale['total']);
    }

    public function testMixedFlagsRatesAndRounding(): void
    {
        $sale = ['total'=>241.50,'subtotal'=>200,'tax_total'=>31.50,'payment_surcharge_amount'=>10];
        $items = [
            ['quantity'=>1,'subtotal'=>100,'tax_total'=>21,'tax_rate'=>21,'line_total'=>121],
            ['quantity'=>1,'subtotal'=>100,'tax_total'=>10.50,'tax_rate'=>10.50,'line_total'=>110.50],
        ];
        [$fiscal,$details] = \App\Libraries\PaymentFiscalPolicy::apply($sale,$items,[
            ['show_on_receipt'=>1,'surcharge_amount'=>3.33],
            ['show_on_receipt'=>0,'surcharge_amount'=>6.67],
            ['show_on_receipt'=>1,'surcharge_amount'=>99,'status'=>'reversed'],
        ]);
        $this->assertSame(234.83,ArcaService::fiscalTotal($fiscal));
        $this->assertSame(234.83,round($fiscal['subtotal']+$fiscal['tax_total'],2));
        $this->assertSame(234.83,round(array_sum(array_column($details,'line_total')),2));
        $this->assertSame([21,10.50],array_column($details,'tax_rate'));
    }

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
