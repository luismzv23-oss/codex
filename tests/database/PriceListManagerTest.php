<?php
use CodeIgniter\Test\CIUnitTestCase;
final class PriceListManagerTest extends CIUnitTestCase {
 private array $tables=[];
 protected function setUp():void {
 parent::setUp();helper(['app','form']);
 $schemas=['companies'=>'id TEXT PRIMARY KEY','inventory_products'=>'id TEXT PRIMARY KEY, company_id TEXT, sku TEXT, name TEXT, brand TEXT, unit TEXT, sale_price REAL, active INTEGER',
 'inventory_stock_levels'=>'product_id TEXT, company_id TEXT, quantity REAL, reserved_quantity REAL',
 'sales_price_lists'=>'id TEXT PRIMARY KEY, company_id TEXT, name TEXT, description TEXT, is_default INTEGER, active INTEGER, created_at TEXT, updated_at TEXT',
 'sales_price_list_items'=>'id TEXT PRIMARY KEY, price_list_id TEXT, product_id TEXT, price REAL, created_at TEXT, updated_at TEXT'];
 foreach($schemas as $t=>$schema){db_connect()->query('CREATE TABLE '.db_connect()->prefixTable($t).' ('.$schema.')');$this->tables[]=$t;}
 $db=db_connect();$db->table('companies')->insert(['id'=>'a']);$db->table('inventory_products')->insert(['id'=>'p','company_id'=>'a','sku'=>'SKU','name'=>'Producto','sale_price'=>1200,'active'=>1]);$db->table('inventory_products')->insert(['id'=>'foreign','company_id'=>'b','sku'=>'F','name'=>'Ajeno','sale_price'=>1200,'active'=>1]);
 $db->table('inventory_stock_levels')->insert(['product_id'=>'p','company_id'=>'a','quantity'=>10,'reserved_quantity'=>2]);
 }
 protected function tearDown():void{foreach(array_reverse($this->tables) as $t)db_connect()->query('DROP TABLE '.db_connect()->prefixTable($t));parent::tearDown();}
 private function callEndpoint(array $payload){
 $request=$this->createMock(CodeIgniter\HTTP\IncomingRequest::class);$request->method('getMethod')->willReturn('post');$request->method('getJSON')->willReturn($payload);
 $controller=new class($request) extends App\Controllers\SalesController {public function __construct($r){$this->request=$r;$this->response=service('response');}protected function salesContext(string $requiredAccess='view'){return ['company'=>['id'=>'a']];}};
 return $controller->priceListProducts();
 }
 public function testCreateUpdateAndTenantValidation():void {
 $response=$this->callEndpoint(['action'=>'metadata','name'=>'Minorista','active'=>1,'is_default'=>1]);$this->assertSame(200,$response->getStatusCode());$data=json_decode($response->getBody(),true);$id=$data['list_id'];$this->assertCount(1,$data['products']);$this->assertEquals(8,$data['products'][0]['available']);
 $response=$this->callEndpoint(['action'=>'price','list_id'=>$id,'product_id'=>'p','price'=>1100]);$this->assertSame(200,$response->getStatusCode());$data=json_decode($response->getBody(),true);$this->assertEquals(1100,$data['products'][0]['list_price']);$this->assertEquals(1200,$data['products'][0]['sale_price']);
 $this->callEndpoint(['action'=>'price','list_id'=>$id,'product_id'=>'p','price'=>1050]);$this->assertSame(1,db_connect()->table('sales_price_list_items')->countAllResults());
 $this->assertSame(422,$this->callEndpoint(['action'=>'price','list_id'=>$id,'product_id'=>'foreign','price'=>100])->getStatusCode());
 $this->assertSame(422,$this->callEndpoint(['action'=>'price','list_id'=>$id,'product_id'=>'p','price'=>-1])->getStatusCode());
 $this->assertSame(422,$this->callEndpoint(['action'=>'price','list_id'=>'foreign','product_id'=>'p','price'=>100])->getStatusCode());
 }
}
