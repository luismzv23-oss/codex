<?php
namespace Tests\Support;

trait IntegrationEventsFixture
{
    private array $eventFixtureTables=[];
    private function installEventFixture(): void
    {
        db_connect()->resetDataCache();
        require_once APPPATH.'Modules/Core/Database/Migrations/2026-10-21-130000_CreateIntegrationOutbox.php';
        (new \CodexCore\Database\Migrations\CreateIntegrationOutbox())->up();
        $this->eventFixtureTables=['integration_outbox','integration_deliveries','integration_delivery_attempts'];
        foreach (['tax_perceptions','tax_withholdings'] as $table) {
            if (!db_connect()->tableExists($table)) {
                db_connect()->query('CREATE TABLE '.db_connect()->prefixTable($table).' (id TEXT PRIMARY KEY, company_id TEXT, name TEXT, tax_type TEXT, rate REAL, min_amount REAL, applies_to TEXT, active INTEGER)');
                $this->eventFixtureTables[]=$table;
            }
        }
        db_connect()->resetDataCache();
    }
    private function removeEventFixture(): void
    {
        while (db_connect()->transDepth) db_connect()->transRollback();
        foreach (array_reverse($this->eventFixtureTables) as $table) db_connect()->query('DROP TABLE '.db_connect()->prefixTable($table));
        db_connect()->resetDataCache();
    }
}
