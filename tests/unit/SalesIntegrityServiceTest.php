<?php

use App\Libraries\SalesIntegrityService;
use CodeIgniter\Test\CIUnitTestCase;

final class SalesIntegrityServiceTest extends CIUnitTestCase
{
    public function testPartialReturnReducesDebtIncludingDiscounts(): void
    {
        $service = new SalesIntegrityService();
        $items = [['quantity' => 10, 'returned_quantity' => 4, 'line_total' => 100]];
        $this->assertSame(60.0, $service->netReceivableTotal(['total' => 100], $items));
        $this->assertSame(54.0, $service->netReceivableTotal(['total' => 90], $items));
        $this->assertSame(9.0, $service->returnUnitPrice(['total' => 90], $items, $items[0]));
    }

    public function testFullReturnClearsDebtAndCancellationRestocksOnlyRemainingUnits(): void
    {
        $service = new SalesIntegrityService();
        $items = [
            ['product_id' => 'a', 'quantity' => 10, 'returned_quantity' => 4, 'line_total' => 100],
            ['product_id' => 'b', 'quantity' => 2, 'returned_quantity' => 2, 'line_total' => 40],
        ];
        $remaining = $service->remainingItems($items);
        $this->assertCount(1, $remaining);
        $this->assertSame(6.0, $remaining[0]['quantity']);
        $items[0]['returned_quantity'] = 10;
        $this->assertSame(0.0, $service->netReceivableTotal(['total' => 140], $items));
    }
}
