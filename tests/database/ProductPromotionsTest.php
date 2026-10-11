<?php
use CodeIgniter\Test\CIUnitTestCase;
final class ProductPromotionsTest extends CIUnitTestCase {
 private array $tables=[];
 protected function setUp():void {
 parent::setUp();helper(['app','form']);
 $schemas=['companies'=>'id TEXT PRIMARY KEY','inventory_products'=>'id TEXT PRIMARY KEY, company_id TEXT, name TEXT, sku TEXT, sale_price REAL, active INTEGER',
 'sales_promotions'=>'id TEXT PRIMARY KEY, company_id TEXT, name TEXT, promotion_type TEXT, scope TEXT, value REAL, start_date TEXT, end_date TEXT, active INTEGER, created_at TEXT, updated_at TEXT',
 'sales_promotion_items'=>'id TEXT PRIMARY KEY, promotion_id TEXT, product_id TEXT, created_at TEXT, updated_at TEXT'];
 foreach($schemas as $table=>$schema){db_connect()->query('CREATE TABLE '.db_connect()->prefixTable($table).' ('.$schema.')');$this->tables[]=$table;}
 db_connect()->table('companies')->insert(['id'=>'company']);db_connect()->table('inventory_products')->insert(['id'=>'product','company_id'=>'company','name'=>'Producto','sku'=>'SKU','sale_price'=>1210,'active'=>1]);
 }
 protected function tearDown():void{foreach(array_reverse($this->tables) as $table)db_connect()->query('DROP TABLE '.db_connect()->prefixTable($table));parent::tearDown();}
 private function runRequest(array $body, string $role='admin') {
 $request=$this->createMock(CodeIgniter\HTTP\IncomingRequest::class);$request->method('getMethod')->willReturn('post');$request->method('getJSON')->willReturn($body);
 $controller=new class($request,$role) extends App\Controllers\SalesController {
 private string $testRole;
 public function __construct($request,$role){$this->request=$request;$this->response=service('response');$this->testRole=$role;}
 protected function salesContext(string $requiredAccess='view'){return ['company'=>['id'=>'company']];}
 protected function roleSlug():?string{return $this->testRole;}
 };
 return $controller->productPromotions();
 }
 private function payload():array{return ['product_id'=>'product','price'=>1000,'start'=>date('Y-m-d\TH:i',time()+3600),'end'=>date('Y-m-d\TH:i',time()+7200)];}
 public function testSaveAndListWithoutDuplicateOverlappingPeriod():void {
 $response=$this->runRequest($this->payload());$this->assertSame(200,$response->getStatusCode());$data=json_decode($response->getBody(),true);$this->assertCount(1,$data['rows']);$this->assertEquals(1000,$data['rows'][0]['value']);
 $this->assertSame(422,$this->runRequest($this->payload())->getStatusCode());$this->assertSame(1,db_connect()->table('sales_promotions')->countAllResults());
 }
 public function testRejectInvalidDatesForeignProductAndSeller():void {
 $body=$this->payload();$body['end']=$body['start'];$this->assertSame(422,$this->runRequest($body)->getStatusCode());
 $body=$this->payload();$body['product_id']='foreign';$this->assertSame(422,$this->runRequest($body)->getStatusCode());
 $this->assertSame(403,$this->runRequest($this->payload(),'vendedor')->getStatusCode());$this->assertSame(0,db_connect()->table('sales_promotions')->countAllResults());
 }
 public function testCurrentPriceResolvedForSelectedProductOnly():void {
 $controller=new App\Controllers\SalesController();$method=new ReflectionMethod($controller,'resolveProductPrice');$method->setAccessible(true);
 $map=['promotions'=>[['id'=>'promo','promotion_type'=>'price','scope'=>'selected','value'=>999]],'product_map'=>['product'=>['promo']]];
 $result=$method->invoke($controller,'company','product',null,$map);$this->assertEquals(999,$result['unit_price']);$this->assertTrue($result['has_promotional_price']);
 $map['product_map']=[];$result=$method->invoke($controller,'company','product',null,$map);$this->assertEquals(1210,$result['unit_price']);$this->assertFalse($result['has_promotional_price']);
 }
}
