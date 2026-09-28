<?php
use App\Libraries\KioskPayments;
use App\Libraries\PaymentIntegrityService;
use CodeIgniter\Test\CIUnitTestCase;

final class KioskPaymentsTest extends CIUnitTestCase
{
    private function method(string $type, float $percentage = 0): array
    {
        return ['id'=>$type.'-guid','code'=>strtoupper($type),'type'=>$type,'percentage'=>$percentage];
    }
    public function testMixedPaymentsChargeOnlyTheirAssignedBase(): void
    {
        $cash = KioskPayments::line($this->method('cash'), ['base_amount'=>'6000.00','received_amount'=>'7000.00']);
        $card = KioskPayments::line($this->method('card',2.5), ['base_amount'=>'4000.00','surcharge_amount'=>9999,'percentage'=>99]);
        KioskPayments::assertAllocated([$cash,$card],10000);
        $this->assertEquals(100,$card['surcharge_amount']);
        $this->assertEquals(4100,$card['amount']);
        $this->assertEquals(1000,$cash['change_amount']);
        $this->assertEquals(10100,PaymentIntegrityService::paid([$cash,$card]));
        $this->assertSame('card-guid',$card['payment_method_id']);
    }
    public function testEachCardUsesItsOwnRate(): void
    {
        $a=KioskPayments::line($this->method('card',2.5),['base_amount'=>'4000']);
        $b=KioskPayments::line($this->method('card',5),['base_amount'=>'6000']);
        $this->assertEquals(400,$a['surcharge_amount']+$b['surcharge_amount']);
    }
    public function testPendingTransferIsNotPaid(): void
    {
        $line=KioskPayments::line($this->method('transfer',2.5),['base_amount'=>'4000','reference'=>'BANK-123']);
        $this->assertSame('pending',$line['status']);
        $this->assertEquals(0,PaymentIntegrityService::paid([$line]));
        $this->assertEquals(4100,$line['amount']);
    }
    public function testOverAllocationIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        KioskPayments::assertAllocated([['base_amount'=>101]],100);
    }
    public function testUnderAllocationIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        KioskPayments::assertAllocated([['base_amount'=>99]],100);
    }
    public function testCashMustCoverItsSurcharge(): void
    {
        $this->expectException(RuntimeException::class);
        KioskPayments::line($this->method('cash',2.5),['base_amount'=>'100','received_amount'=>'100']);
    }
    public function testKioskTransferWithoutReferenceRemainsPending(): void
    {
        $line = KioskPayments::line($this->method('transfer'),['base_amount'=>'100']);
        $this->assertSame('pending', $line['status']);
        $this->assertSame('', $line['reference']);
        $this->assertEquals(0, PaymentIntegrityService::paid([$line]));
    }
    public function testOtherFlowsStillRequireTransferReference(): void
    {
        $this->expectException(RuntimeException::class);
        (new PaymentIntegrityService())->parse([['payment_method'=>'transfer','amount'=>'100']]);
    }
    public function testZeroLinesAreRejected(): void
    {
        $this->expectException(RuntimeException::class);
        KioskPayments::line($this->method('card'),['base_amount'=>'0']);
    }
}
