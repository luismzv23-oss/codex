<?php
use App\Libraries\AccountingService;
use CodeIgniter\Test\CIUnitTestCase;

/** Purchase receipts need the inventory account; companies configured before it existed only have goods_received. */
final class AccountingInventoryMappingTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        db_connect()->query('CREATE TABLE ' . db_connect()->prefixTable('company_settings') . ' (id TEXT, company_id TEXT, "key" TEXT, value TEXT, created_at TEXT, updated_at TEXT)');
    }

    protected function tearDown(): void
    {
        db_connect()->query('DROP TABLE ' . db_connect()->prefixTable('company_settings'));
        parent::tearDown();
    }

    private function mapping(string $company): array
    {
        $method = new ReflectionMethod(AccountingService::class, 'getAccountMapping');
        $method->setAccessible(true);

        return $method->invoke(new AccountingService(), $company);
    }

    public function testInventoryFallsBackToGoodsReceivedOnlyWhenMissing(): void
    {
        db_connect()->table('company_settings')->insertBatch([
            ['id' => '1', 'company_id' => 'legacy', 'key' => 'account_goods_received', 'value' => 'bienes'],
            ['id' => '2', 'company_id' => 'explicit', 'key' => 'account_goods_received', 'value' => 'bienes'],
            ['id' => '3', 'company_id' => 'explicit', 'key' => 'account_inventory', 'value' => 'inventario'],
        ]);

        $this->assertSame('bienes', $this->mapping('legacy')['inventory']);
        $this->assertSame('inventario', $this->mapping('explicit')['inventory'], 'An explicit inventory account is never replaced.');
        $this->assertArrayNotHasKey('inventory', $this->mapping('empty'));
    }
}
