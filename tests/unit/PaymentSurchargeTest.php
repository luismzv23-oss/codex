<?php

use App\Libraries\PaymentSurcharge;
use CodeIgniter\Test\CIUnitTestCase;

final class PaymentSurchargeTest extends CIUnitTestCase
{
    public function testSurchargeUsesFinalTaxInclusiveBase(): void
    {
        $result = PaymentSurcharge::calculate(12100, 2.5);
        $this->assertEquals(302.50, $result['amount']);
        $this->assertEquals(12402.50, $result['total']);
        $this->assertEquals(2.50, $result['rate']);
    }

    public function testDiscountedBaseAndCentRounding(): void
    {
        $this->assertEquals(225, PaymentSurcharge::calculate(9000, 2.5)['amount']);
        $this->assertEquals(0.01, PaymentSurcharge::calculate(0.20, 2.5)['amount']);
        $this->assertEquals(10, PaymentSurcharge::calculate(10, 0)['total']);
        $this->assertEquals(0, PaymentSurcharge::calculate(0, 2.5)['total']);
    }

    public function testInvalidPercentageIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        PaymentSurcharge::calculate(100, INF);
    }

    public function testLedgerBalancesSurchargeAfterDiscount(): void
    {
        $accounting = new class extends \App\Libraries\AccountingService {
            public function createJournalEntry(string $companyId, array $data, array $lines): array { return $lines; }
        };
        $lines = $accounting->journalFromSale('company', [
            'subtotal' => 10000, 'tax_total' => 2100, 'global_discount_total' => 100,
            'payment_surcharge_amount' => 300, 'total' => 12300,
        ], ['receivable' => 'receivable', 'revenue' => 'revenue', 'iva_debito' => 'tax']);
        $this->assertEquals(12300, array_sum(array_column($lines, 'debit')));
        $this->assertEquals(12300, array_sum(array_column($lines, 'credit')));
        $this->assertCount(4, $lines);
    }

    public function testFiscalAuthorizationDoesNotSendUnbalancedAmounts(): void
    {
        $result = (new \App\Libraries\ArcaService())->authorizeSale(
            ['payment_surcharge_amount' => 302.50], ['category' => 'invoice'], [],
            ['wsfev1_enabled' => 1], []
        );
        $this->assertSame('SURCHARGE_FISCAL_MAPPING_REQUIRED', $result['result_code']);
        $this->assertSame([], $result['request_payload']);
    }
}
