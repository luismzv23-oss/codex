<?php

use App\Libraries\ArcaService;
use CodeIgniter\Test\CIUnitTestCase;

final class ArcaPaymentSurchargeTest extends CIUnitTestCase
{
    public function testReceiptRecalculatesProductNetAndVatWithSurcharge(): void
    {
        [$example,$exampleItems] = \App\Libraries\PaymentFiscalPolicy::forReceipt(
            ['total'=>30360,'subtotal'=>24793.39,'tax_total'=>5206.61,'payment_surcharge_amount'=>360],
            [['quantity'=>1,'unit_price'=>30000,'subtotal'=>24793.39,'tax_total'=>5206.61,'tax_rate'=>21,'line_total'=>30000]],
            [['show_on_receipt'=>1,'surcharge_amount'=>360]]
        );
        $this->assertSame(30360.0,$example['total']);
        $this->assertSame(25090.91,$example['subtotal']);
        $this->assertSame(5269.09,$example['tax_total']);
        $this->assertSame(30360.0,$exampleItems[0]['unit_price']);
        $sale = ['total'=>30750, 'subtotal'=>24793.39, 'tax_total'=>5206.61, 'payment_surcharge_amount'=>750];
        $items = [['quantity'=>1,'unit_price'=>30000,'subtotal'=>24793.39,'tax_total'=>5206.61,'tax_rate'=>21,'line_total'=>30000]];
        [$receipt,$lines] = \App\Libraries\PaymentFiscalPolicy::forReceipt($sale,$items,[['show_on_receipt'=>1,'surcharge_amount'=>750]]);
        $this->assertSame(30750.0,$receipt['total']);
        $this->assertSame(25413.22,$receipt['subtotal']);
        $this->assertSame(5336.78,$receipt['tax_total']);
        $this->assertSame(30750.0,$lines[0]['unit_price']);
        $this->assertSame(30750.0,$lines[0]['line_total']);
        $this->assertSame(30000,$items[0]['unit_price']);
        // Previously authorized data cannot change the displayed sale total.
        [$reprint,$lines] = \App\Libraries\PaymentFiscalPolicy::forReceipt($sale,$items,[['show_on_receipt'=>0,'surcharge_amount'=>750]],['fiscalTotal'=>30750]);
        $this->assertSame($receipt,$reprint);
        [$excluded] = \App\Libraries\PaymentFiscalPolicy::forReceipt($sale,$items,[['show_on_receipt'=>1,'surcharge_amount'=>750]],['fiscalTotal'=>30000]);
        $this->assertSame(30750.0,$excluded['total']);
        $this->assertSame(25413.22,$excluded['subtotal']);
    }

    public function testVisibilityDoesNotChangeFiscalTotalAndBothPayloadsBalance(): void
    {
        $sale = ['total'=>2255, 'subtotal'=>1818.18, 'tax_total'=>381.82, 'payment_surcharge_amount'=>55];
        $items = [['quantity'=>1, 'unit_price'=>2200, 'subtotal'=>1818.18, 'tax_total'=>381.82, 'tax_rate'=>21, 'line_total'=>2200]];
        $method = new ReflectionMethod(ArcaService::class, 'buildPayloadPreview');
        $method->setAccessible(true);
        foreach ([0=>2255.0, 1=>2255.0] as $flag=>$expected) {
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
        $this->assertSame(241.50,ArcaService::fiscalTotal($fiscal));
        $this->assertSame(241.50,round($fiscal['subtotal']+$fiscal['tax_total'],2));
        $this->assertSame(241.50,round(array_sum(array_column($details,'line_total')),2));
        $this->assertSame([21,10.50],array_column($details,'tax_rate'));
    }

    public function testFiscalTotalUsesSavedSurchargeWithoutChangingSale(): void
    {
        $sale = ['total' => 38080, 'payment_surcharge_amount' => 500];
        $this->assertSame(38080.0, ArcaService::fiscalTotal($sale));
        $this->assertSame(38080, $sale['total']);
        $this->assertSame(121.0, ArcaService::fiscalTotal(['total' => 121]));
        $this->assertSame(12751.28, ArcaService::fiscalTotal(['total' => 12751.28, 'payment_surcharge_amount' => 311.01]));
    }

    public function testBothFiscalPayloadsMatchTheCustomerReceipt(): void
    {
        $sale = ['total'=>30360,'subtotal'=>24793.39,'tax_total'=>5206.61,'payment_surcharge_amount'=>360];
        $items = [['quantity'=>1,'unit_price'=>30000,'subtotal'=>24793.39,'tax_total'=>5206.61,'tax_rate'=>21,'line_total'=>30000]];
        [$sale,$items] = \App\Libraries\PaymentFiscalPolicy::forSale($sale,$items);
        $this->assertSame(30360.0, $items[0]['line_total']);
        $this->assertSame(30360.0, $items[0]['unit_price']);
        $this->assertSame([$sale,$items], \App\Libraries\PaymentFiscalPolicy::forReceipt($sale,$items,[]));
        $method = new ReflectionMethod(ArcaService::class, 'buildPayloadPreview');
        $method->setAccessible(true);
        foreach (['wsfev1', 'wsmtxca'] as $service) {
            $payload = $method->invoke(new ArcaService(), $sale, ['category' => 'invoice', 'afip_code' => 6], [], [], $items, [], ['slug' => $service]);
            $this->assertSame(30360.0, $payload['imp_total']);
            $this->assertEquals(25090.91, $payload['imp_neto']);
            $tax = $service === 'wsfev1' ? $payload['imp_iva'] : array_sum(array_column($payload['iva_subtotals'], 'importe'));
            $this->assertEquals(5269.09, $tax);
            $this->assertArrayNotHasKey('payment_surcharge_amount', $payload);
        }
    }

    public function testInvalidSurchargeCannotProduceNegativeFiscalAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ArcaService::fiscalTotal(['total' => 100, 'payment_surcharge_amount' => 101]);
    }
}
