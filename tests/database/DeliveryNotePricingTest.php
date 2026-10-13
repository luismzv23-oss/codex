<?php
use App\Libraries\DeliveryNotePricing;
use CodeIgniter\Test\CIUnitTestCase;
final class DeliveryNotePricingTest extends CIUnitTestCase
{
 private array $tables=[];
 protected function setUp():void {
 parent::setUp();helper('app');
 $schemas=[
 'inventory_products'=>'id TEXT, company_id TEXT, sale_price REAL, active INTEGER',
 'sales_price_lists'=>'id TEXT, company_id TEXT, active INTEGER',
 'sales_price_list_items'=>'price_list_id TEXT, product_id TEXT, price REAL',
 'sale_items'=>'id TEXT PRIMARY KEY, sale_id TEXT, product_id TEXT, quantity REAL, unit_price REAL, discount_rate REAL, discount_amount REAL, tax_rate REAL, subtotal REAL, tax_total REAL, line_total REAL, updated_at TEXT',
 'sales'=>'id TEXT PRIMARY KEY, company_id TEXT, price_list_id TEXT, subtotal REAL, tax_total REAL, item_discount_total REAL, total REAL, payment_status TEXT, updated_at TEXT'];
 foreach($schemas as $t=>$schema){db_connect()->query('CREATE TABLE '.db_connect()->prefixTable($t).' ('.$schema.')');$this->tables[]=$t;}
 }
 protected function tearDown():void{foreach(array_reverse($this->tables) as $t)db_connect()->query('DROP TABLE '.db_connect()->prefixTable($t));parent::tearDown();}
 public function testRefreshUsesCurrentListPriceAndPersistsBalancedAmounts():void {
 $db=db_connect();$sale=['id'=>'s','company_id'=>'c','price_list_id'=>'l'];
 $item=['id'=>'i','sale_id'=>'s','product_id'=>'p','quantity'=>2,'unit_price'=>100,'tax_rate'=>21,'discount_rate'=>10];
 $db->table('sales')->insert($sale);$db->table('sale_items')->insert($item);
 $db->table('inventory_products')->insert(['id'=>'p','company_id'=>'c','sale_price'=>1210,'active'=>1]);
 $db->table('sales_price_lists')->insert(['id'=>'l','company_id'=>'c','active'=>1]);
 $db->table('sales_price_list_items')->insert(['price_list_id'=>'l','product_id'=>'p','price'=>2420]);
 [$updated,$items]=(new DeliveryNotePricing())->refresh('c',$sale,[$item]);
 $this->assertEquals(4356,$updated['total']);$this->assertEquals(3600,$updated['subtotal']);$this->assertEquals(756,$updated['tax_total']);
 $this->assertEquals(2420,$db->table('sale_items')->get()->getRowArray()['unit_price']);
 $sale['price_list_id']=null;[$updated]=(new DeliveryNotePricing())->refresh('c',$sale,[$item]);$this->assertEquals(2178,$updated['total']);
 $this->expectException(RuntimeException::class);(new DeliveryNotePricing())->refresh('other',$sale,[$item]);
 }
 public function testDeliveryPdfOmitsMonetaryValuesButInvoiceRetainsThem():void {
 $data=['ticketSettings'=>[],'documentType'=>['category'=>'delivery_note','name'=>'Remito'], 'company'=>['name'=>'Demo'], 'sale'=>['sale_number'=>'RTO-1','currency_code'=>'ARS','subtotal'=>1000,'total'=>1210], 'items'=>[['sku'=>'X','product_name'=>'Producto','quantity'=>1,'unit_price'=>1210,'line_total'=>1210,'tax_total'=>210,'tax_rate'=>21]],'payments'=>[]];
 $html=view('sales/pdf/pos',$data,['saveData'=>false]);
 $this->assertStringContainsString('Producto',$html);$this->assertStringNotContainsString('1.210,00',$html);$this->assertStringNotContainsString('Importe total',$html);$this->assertStringNotContainsString('Precio unitario',$html);
 $data['documentType']['category']='invoice';$html=view('sales/pdf/pos',$data,['saveData'=>false]);$this->assertStringContainsString('1.210,00',$html);$this->assertStringContainsString('Importe total',$html);
 }
}
