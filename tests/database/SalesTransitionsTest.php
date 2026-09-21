<?php

use App\Libraries\SalesIntegrityService;
use CodeIgniter\Test\CIUnitTestCase;

final class SalesTransitionsTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db = db_connect('tests');
        foreach (['sales', 'sales_delivery_notes'] as $table) {
            $name = $this->db->prefixTable($table);
            $this->db->query("CREATE TABLE $name (id VARCHAR(40) PRIMARY KEY, company_id VARCHAR(40), status VARCHAR(30))");
        }
        $this->db->query('CREATE TABLE ' . $this->db->prefixTable('sales_test_stock') . ' (quantity INTEGER)');
        $this->db->table('sales_test_stock')->insert(['quantity' => 10]);
    }

    protected function tearDown(): void
    {
        foreach (['sales', 'sales_delivery_notes', 'sales_test_stock'] as $table) {
            $this->db->query('DROP TABLE ' . $this->db->prefixTable($table));
        }
        parent::tearDown();
    }

    public function testRepeatedConfirmationIsRejectedByBothControllers(): void
    {
        $this->db->table('sales')->insert(['id' => 'sale', 'company_id' => 'a', 'status' => 'confirmed']);
        foreach ([\App\Controllers\SalesController::class, \App\Controllers\Api\V1\SalesController::class] as $class) {
            $method = new ReflectionMethod($class, 'confirmSaleTransaction');
            $method->setAccessible(true);
            $result = $method->invoke(new $class(), 'a', 'sale');
            $this->assertIsString($result);
            $this->assertStringContainsString('estado incompatible', $result);
        }
        $this->assertSame(10, (int) $this->db->table('sales_test_stock')->get()->getRowArray()['quantity']);
    }

    public function testOtherCompanyCannotOperateOnDocument(): void
    {
        $this->db->table('sales_delivery_notes')->insert(['id' => 'note', 'company_id' => 'b', 'status' => 'pending']);
        $this->expectException(RuntimeException::class);
        (new SalesIntegrityService())->locked($this->db, 'sales_delivery_notes', 'a', 'note', ['pending'], function () {
            $this->fail('No debe ejecutar una operacion de otra empresa.');
        });
    }

    public function testPendingDeliveryCannotBeMarkedDelivered(): void
    {
        $this->db->table('sales_delivery_notes')->insert(['id' => 'note', 'company_id' => 'a', 'status' => 'pending']);
        $this->expectException(RuntimeException::class);
        (new SalesIntegrityService())->locked($this->db, 'sales_delivery_notes', 'a', 'note', ['dispatched'], function () {
            $this->fail('Un remito pendiente no puede entregarse.');
        });
    }

    public function testStockAndDocumentRollbackTogetherOnMovementFailure(): void
    {
        $this->db->table('sales_delivery_notes')->insert(['id' => 'note', 'company_id' => 'a', 'status' => 'pending']);
        try {
            (new SalesIntegrityService())->locked($this->db, 'sales_delivery_notes', 'a', 'note', ['pending'], function () {
                $this->db->table('sales_test_stock')->update(['quantity' => 7]);
                $this->db->table('sales_delivery_notes')->where('id', 'note')->update(['status' => 'dispatched']);
                throw new RuntimeException('Periodo cerrado');
            });
            $this->fail('El error debe propagarse.');
        } catch (RuntimeException $e) {
            $this->assertSame('Periodo cerrado', $e->getMessage());
        }
        $this->assertSame(10, (int) $this->db->table('sales_test_stock')->get()->getRowArray()['quantity']);
        $this->assertSame('pending', $this->db->table('sales_delivery_notes')->get()->getRowArray()['status']);
    }

    public function testDispatchCommitsOnceAndRejectsRetry(): void
    {
        $this->db->table('sales_delivery_notes')->insert(['id' => 'note', 'company_id' => 'a', 'status' => 'pending']);
        $service = new SalesIntegrityService();
        $service->locked($this->db, 'sales_delivery_notes', 'a', 'note', ['pending'], function () {
            $this->db->table('sales_test_stock')->update(['quantity' => 7]);
            $this->db->table('sales_delivery_notes')->where('id', 'note')->update(['status' => 'dispatched']);
        });
        $this->assertSame(7, (int) $this->db->table('sales_test_stock')->get()->getRowArray()['quantity']);
        $this->expectException(RuntimeException::class);
        $service->locked($this->db, 'sales_delivery_notes', 'a', 'note', ['pending'], function () {
            $this->fail('El reintento no debe despachar otra vez.');
        });
    }

    public function testNestedFailureDoesNotLeaveAnOpenTransaction(): void
    {
        $this->db->table('sales')->insert(['id' => 'sale', 'company_id' => 'a', 'status' => 'draft']);
        try {
            (new SalesIntegrityService())->locked($this->db, 'sales', 'a', 'sale', ['draft'], function () {
                $this->db->transBegin();
                $this->db->table('sales_test_stock')->update(['quantity' => 0]);
                throw new RuntimeException('Fallo interno');
            });
            $this->fail('El error debe propagarse.');
        } catch (RuntimeException $e) {
            $this->assertSame('Fallo interno', $e->getMessage());
        }
        $this->assertSame(0, $this->db->transDepth);
        $this->assertSame(10, (int) $this->db->table('sales_test_stock')->get()->getRowArray()['quantity']);
    }
}
