<?php

use App\Libraries\SalesPackAvailability;
use CodeIgniter\Test\CIUnitTestCase;

final class SalesPackAvailabilityTest extends CIUnitTestCase
{
    public function testPacksShareBaseStockAndRespectReservationsAndWarehouses(): void
    {
        $products = [['id'=>'b','product_type'=>'simple'], ['id'=>'c','product_type'=>'kit'], ['id'=>'h','product_type'=>'kit']];
        $components = [['product_id'=>'c','component_product_id'=>'b','quantity'=>8], ['product_id'=>'h','component_product_id'=>'b','quantity'=>4]];
        $stocks = [['product_id'=>'b','warehouse_id'=>'a','stock'=>13,'reserved'=>0], ['product_id'=>'b','warehouse_id'=>'z','stock'=>8,'reserved'=>1]];
        $result = (new SalesPackAvailability())->calculate($products, $components, $stocks);
        $this->assertSame(13.0, $result[0]['stocks']['a']['available']);
        $this->assertSame(1.0, $result[1]['stocks']['a']['available']);
        $this->assertSame(3.0, $result[2]['stocks']['a']['available']);
        $this->assertSame(0.0, $result[1]['stocks']['z']['available']);
        $this->assertSame(1.0, $result[2]['stocks']['z']['available']);
        // The catalog may only contain a searched pack, not its base product.
        $filtered = (new SalesPackAvailability())->calculate([$products[1]], $components, $stocks);
        $this->assertSame($result[1], $filtered[0]);
    }

    public function testMultipleComponentsAndDuplicateRecipeRowsLimitAvailability(): void
    {
        $products = [['id'=>'k','product_type'=>'kit']];
        $components = [['product_id'=>'k','component_product_id'=>'a','quantity'=>2], ['product_id'=>'k','component_product_id'=>'a','quantity'=>2], ['product_id'=>'k','component_product_id'=>'b','quantity'=>1]];
        $stocks = [['product_id'=>'a','warehouse_id'=>'w','stock'=>13,'reserved'=>0], ['product_id'=>'b','warehouse_id'=>'w','stock'=>2,'reserved'=>0]];
        $result = (new SalesPackAvailability())->calculate($products, $components, $stocks);
        $this->assertSame(2.0, $result[0]['stocks']['w']['available']);
        array_pop($stocks);
        $result = (new SalesPackAvailability())->calculate($products, $components, $stocks);
        $this->assertSame(0.0, $result[0]['stocks']['w']['available']);
    }
}
