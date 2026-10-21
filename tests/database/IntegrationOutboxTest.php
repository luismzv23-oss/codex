<?php

use App\Libraries\Core\Events\{BusinessEvents,ConsumerRegistry,DeliveryWorker,OutboxPublisher,DeliveryStatus};
use CodeIgniter\Test\CIUnitTestCase;

final class IntegrationOutboxTest extends CIUnitTestCase
{
    use \Tests\Support\IntegrationEventsFixture;
    private array $tables=[];
    protected function setUp(): void
    {
        parent::setUp(); helper('app'); db_connect()->resetDataCache();
        foreach ([
            'companies'=>'id TEXT PRIMARY KEY',
            'effects'=>'id TEXT PRIMARY KEY, company_id TEXT, value TEXT',
            'sales'=>'id TEXT PRIMARY KEY, company_id TEXT, status TEXT, total REAL, customer_id TEXT, document_type_id TEXT',
            'sales_document_types'=>'id TEXT PRIMARY KEY, company_id TEXT, category TEXT',
            'tax_perceptions_applied'=>'id TEXT PRIMARY KEY, company_id TEXT, perception_id TEXT, source_type TEXT, source_id TEXT, customer_id TEXT, base_amount REAL, rate REAL, amount REAL, applied_at TEXT',
            'purchase_payments'=>'id TEXT PRIMARY KEY, company_id TEXT, status TEXT, amount REAL, supplier_id TEXT',
            'tax_withholdings_applied'=>'id TEXT PRIMARY KEY, company_id TEXT, withholding_id TEXT, source_type TEXT, source_id TEXT, supplier_id TEXT, base_amount REAL, rate REAL, amount REAL, certificate_number TEXT, applied_at TEXT',
            'automation_log'=>'id TEXT PRIMARY KEY, company_id TEXT, event TEXT, actions TEXT, actions_count INTEGER, created_at TEXT',
        ] as $table=>$schema) {
            db_connect()->query('CREATE TABLE '.db_connect()->prefixTable($table).' ('.$schema.')'); $this->tables[]=$table;
        }
        $this->installEventFixture();
        foreach (['a','b'] as $id) db_connect()->table('companies')->insert(['id'=>$id]);
    }
    protected function tearDown(): void
    {
        $this->removeEventFixture();
        foreach (array_reverse($this->tables) as $table) db_connect()->query('DROP TABLE '.db_connect()->prefixTable($table));
        parent::tearDown();
    }
    private function registry(?callable $callback=null): ConsumerRegistry
    {
        return new ConsumerRegistry(['test.changed'=>['test.effect'=>['version'=>1,'handler'=>$callback ?? static function(array $event,array $payload) {
            if (!db_connect()->table('effects')->insert(['id'=>$event['id'],'company_id'=>$event['company_id'],'value'=>$payload['value'] ?? 'ok'])) throw new RuntimeException('Write failed');
        }]]]);
    }
    private function publish(?ConsumerRegistry $registry=null,string $company='a',string $key='operation',array $payload=['value'=>'ok']): string
    {
        $db=db_connect(); $db->transBegin();
        try { $id=(new OutboxPublisher(null,$registry ?? $this->registry()))->publish($company,'test.changed',$key,'test','source',$payload); $db->transCommit(); return $id; }
        catch (Throwable $e) { $db->transRollback(); throw $e; }
    }
    private function row(string $event): array { return db_connect()->table('integration_deliveries')->where('event_id',$event)->get()->getRowArray(); }
    private function due(): void { db_connect()->table('integration_deliveries')->where('status','retry')->update(['available_at'=>'2000-01-01 00:00:00']); }

    public function testEventRequiresActiveTransaction(): void
    {
        $this->expectException(RuntimeException::class);
        (new OutboxPublisher(null,$this->registry()))->publish('a','test.changed','key','test','source',[]);
    }
    public function testBusinessRollbackRemovesEventAndDeliveries(): void
    {
        db_connect()->transBegin();
        (new OutboxPublisher(null,$this->registry()))->publish('a','test.changed','key','test','source',[]);
        db_connect()->table('effects')->insert(['id'=>'business','company_id'=>'a']);
        db_connect()->transRollback();
        foreach (['effects','integration_outbox','integration_deliveries'] as $table) $this->assertSame(0,db_connect()->table($table)->countAllResults());
    }
    public function testDeduplicationAndConflictingEnvelope(): void
    {
        $id=$this->publish(); $this->assertSame($id,$this->publish());
        $this->assertSame(1,db_connect()->table('integration_deliveries')->countAllResults());
        $this->expectException(RuntimeException::class); $this->publish(null,'a','operation',['value'=>'changed']);
    }
    public function testCanonicalNestedPayloadIsIdempotent(): void
    {
        $a=$this->publish(null,'a','canonical',['z'=>1,'a'=>['b'=>2,'a'=>3]]);
        $b=$this->publish(null,'a','canonical',['a'=>['a'=>3,'b'=>2],'z'=>1]);
        $this->assertSame($a,$b);
    }
    public function testSuccessfulDeliveryCannotRepeatItsEffect(): void
    {
        $registry=$this->registry(); $id=$this->publish($registry); $worker=new DeliveryWorker(null,$registry);
        $this->assertSame(1,$worker->processBatch('a')['succeeded']);
        $this->assertSame(0,$worker->processBatch('a')['claimed']);
        $this->assertSame('succeeded',$this->row($id)['status']);
        $this->assertSame(1,db_connect()->table('effects')->countAllResults());
    }
    public function testFailureRollsBackEffectAndRetriesOnlyAfterDueTime(): void
    {
        $failing=$this->registry(static function(array $event) {
            db_connect()->table('effects')->insert(['id'=>$event['id'],'company_id'=>'a']);
            throw new RuntimeException('secret-token-must-not-be-stored');
        });
        $id=$this->publish($failing); $worker=new DeliveryWorker(null,$failing);
        $this->assertSame(1,$worker->processBatch('a')['retry']);
        $this->assertSame(0,db_connect()->table('effects')->countAllResults());
        $this->assertSame(0,$worker->processBatch('a')['claimed']);
        $this->assertStringNotContainsString('secret',$this->row($id)['last_error']);
        $this->due();
        $this->assertSame(1,(new DeliveryWorker(null,$this->registry()))->processBatch('a')['succeeded']);
        $this->assertSame(2,db_connect()->table('integration_delivery_attempts')->countAllResults());
    }
    public function testConsumerSqlFailureDoesNotPoisonNextDelivery(): void
    {
        $registry=$this->registry(static function() { db_connect()->query('INSERT INTO table_that_does_not_exist VALUES (1)'); });
        $this->publish($registry);
        $this->assertSame(1,(new DeliveryWorker(null,$registry))->processBatch('a')['retry']);
        $this->assertTrue(db_connect()->transStatus());
        $this->due();
        $this->assertSame(1,(new DeliveryWorker(null,$this->registry()))->processBatch('a')['succeeded']);
    }
    public function testSeparateConsumersDoNotReplaySuccessfulSibling(): void
    {
        $registry=new ConsumerRegistry(['test.changed'=>[
            'first'=>['version'=>1,'handler'=>static function(array $event) { db_connect()->table('effects')->insert(['id'=>$event['id'],'company_id'=>'a']); }],
            'second'=>['version'=>1,'handler'=>static function() { throw new RuntimeException('fail'); }],
        ]]);
        $this->publish($registry); $worker=new DeliveryWorker(null,$registry);
        $counts=$worker->processBatch('a'); $this->assertSame(1,$counts['succeeded']); $this->assertSame(1,$counts['retry']);
        $this->due(); $this->assertSame(1,$worker->processBatch('a')['retry']);
        $this->assertSame(1,db_connect()->table('effects')->countAllResults());
    }
    public function testLeaseRecoveryRejectsOldWorkerToken(): void
    {
        $registry=$this->registry(); $id=$this->publish($registry); $worker=new DeliveryWorker(null,$registry);
        $first=$worker->claim('a',$this->row($id)['id']); $this->assertNotNull($first);
        $this->assertNull($worker->claim('a',$first['id']));
        db_connect()->table('integration_deliveries')->where('id',$first['id'])->update(['lease_until'=>'2000-01-01 00:00:00']);
        $second=$worker->claim('a',$first['id']);
        $this->assertNotSame($first['token'],$second['token']);
        $this->assertSame('stale',$worker->deliver('a',$first));
        $this->assertSame('succeeded',$worker->deliver('a',$second));
        $this->assertSame(1,db_connect()->table('effects')->countAllResults());
        $this->assertSame(1,db_connect()->table('integration_delivery_attempts')->where('result','abandoned')->countAllResults());
    }
    public function testCompanyIsolationIncludesClaimsStatusAndManualRetry(): void
    {
        $registry=$this->registry(); $id=$this->publish($registry,'b'); $worker=new DeliveryWorker(null,$registry);
        $this->assertSame(0,$worker->processBatch('a')['claimed']);
        $this->assertNull($worker->claim('a',$this->row($id)['id']));
        $this->assertSame(0,DeliveryStatus::summary('a')['deliveries']['pending']);
        $this->expectException(RuntimeException::class); $worker->retryFailed('a',$this->row($id)['id']);
    }
    public function testMissingConsumerBlocksWithoutExecutingAndManualRetryIsAudited(): void
    {
        $id=$this->publish(); $worker=new DeliveryWorker(null,new ConsumerRegistry([]));
        $this->assertSame(1,$worker->processBatch('a')['blocked']);
        $worker->retryFailed('a',$this->row($id)['id']);
        $this->assertSame(1,(new DeliveryWorker(null,$this->registry()))->processBatch('a')['succeeded']);
        $this->assertSame(1,db_connect()->table('integration_delivery_attempts')->where('result','manual_retry')->countAllResults());
    }
    public function testFiveAttemptsExhaustAndRequireExplicitRetry(): void
    {
        $registry=$this->registry(static function() { throw new RuntimeException('fail'); });
        $id=$this->publish($registry); $worker=new DeliveryWorker(null,$registry);
        for ($i=0;$i<5;$i++) { $worker->processBatch('a'); $this->due(); }
        $this->assertSame('dead',$this->row($id)['status']);
        $this->assertSame(5,(int)$this->row($id)['attempts']);
        $this->assertSame(0,$worker->processBatch('a')['claimed']);
        $worker->retryFailed('a',$this->row($id)['id']);
        $this->assertSame(1,(new DeliveryWorker(null,$this->registry()))->processBatch('a')['succeeded']);
    }
    public function testTamperedEventIsBlocked(): void
    {
        $id=$this->publish(); db_connect()->table('integration_outbox')->where('id',$id)->update(['payload'=>'{}']);
        $this->assertSame(1,(new DeliveryWorker(null,$this->registry()))->processBatch('a')['blocked']);
        $this->assertSame(0,db_connect()->table('effects')->countAllResults());
    }
    public function testTaxSnapshotSurvivesRateChangesAndDoesNotDuplicate(): void
    {
        db_connect()->table('sales_document_types')->insert(['id'=>'doc','company_id'=>'a','category'=>'invoice']);
        db_connect()->table('sales')->insert(['id'=>'sale','company_id'=>'a','status'=>'confirmed','total'=>100,'document_type_id'=>'doc']);
        db_connect()->table('tax_perceptions')->insert(['id'=>'tax','company_id'=>'a','name'=>'Tax','tax_type'=>'iibb','rate'=>2,'active'=>1]);
        db_connect()->transBegin(); $id=BusinessEvents::sale('a','sale','confirmed'); db_connect()->transCommit();
        db_connect()->table('tax_perceptions')->where('id','tax')->update(['rate'=>99]);
        $worker=new DeliveryWorker(); $this->assertSame(2,$worker->processBatch('a')['succeeded']);
        $this->assertSame(0,$worker->processBatch('a')['claimed']);
        $record=db_connect()->table('tax_perceptions_applied')->get()->getRowArray(); $this->assertSame(2.0,(float)$record['amount']);
        $this->assertSame(1,db_connect()->table('tax_perceptions_applied')->countAllResults());
        $this->assertSame(1,db_connect()->table('automation_log')->countAllResults());
        $this->assertSame('succeeded',$this->row($id)['status']);
    }
    public function testWithholdingsSnapshotUsesOriginalRateAndUniqueOrigin(): void
    {
        db_connect()->table('purchase_payments')->insert(['id'=>'payment','company_id'=>'a','status'=>'posted','amount'=>100,'supplier_id'=>'supplier']);
        db_connect()->table('tax_withholdings')->insert(['id'=>'tax','company_id'=>'a','name'=>'Tax','tax_type'=>'iva','rate'=>3,'active'=>1,'min_amount'=>0,'applies_to'=>'purchases']);
        db_connect()->transBegin(); BusinessEvents::supplierPayment('a','payment'); db_connect()->transCommit();
        db_connect()->table('tax_withholdings')->where('id','tax')->update(['rate'=>99]);
        $this->assertSame(2,(new DeliveryWorker())->processBatch('a')['succeeded']);
        $row=db_connect()->table('tax_withholdings_applied')->get()->getRowArray(); $this->assertSame(3.0,(float)$row['amount']);
        (new \App\Libraries\WithholdingService())->calculateWithholdings('a',100,'purchase_payment','payment','supplier',[['id'=>'tax','company_id'=>'a','rate'=>3,'min_amount'=>0,'tax_type'=>'iva','name'=>'Tax']]);
        $this->assertSame(1,db_connect()->table('tax_withholdings_applied')->countAllResults());
    }

    public function testMigratedEventBusDoesNotAlsoExecuteLegacyListener(): void
    {
        db_connect()->table('sales')->insert(['id'=>'sale','company_id'=>'a','status'=>'cancelled']);
        $property=new ReflectionProperty(\App\Libraries\EventBus::class,'listeners'); $property->setAccessible(true); $previous=$property->getValue();
        $called=0; \App\Libraries\EventBus::on('sale.cancelled',static function() use (&$called) { $called++; });
        try {
            db_connect()->transBegin();
            $result=\App\Libraries\EventBus::emit('sale.cancelled',['company_id'=>'a','sale'=>['id'=>'sale','password'=>'must-not-be-copied']]);
            db_connect()->transCommit();
            $this->assertSame(0,$called);
            $this->assertSame('pending',$result[0]['status']);
            $row=db_connect()->table('integration_outbox')->get()->getRowArray();
            $this->assertStringNotContainsString('password',$row['payload']);
            $this->assertSame(['sale_id'=>'sale'],json_decode($row['payload'],true));
        } finally { $property->setValue($previous); }
    }

    public function testMigrationRollbackCannotErasePersistedEvidence(): void
    {
        $this->publish();
        try { (new \CodexCore\Database\Migrations\CreateIntegrationOutbox())->down(); $this->fail('Evidence must be preserved'); }
        catch (RuntimeException $e) { $this->assertStringContainsString('evidencias',$e->getMessage()); }
        $this->assertSame(1,db_connect()->table('integration_outbox')->countAllResults());
    }
}
