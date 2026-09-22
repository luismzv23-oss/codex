<?php

// Explicit integration check: creates and drops only its own temporary database.
require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';
helper('app');

$worker = ($argv[1] ?? '') === '--worker';
$name = $worker ? ($argv[2] ?? '') : 'codex_inventory_check_' . bin2hex(random_bytes(5));
if (! preg_match('/\Acodex_inventory_check_[a-f0-9]{10}\z/', $name)) {
    throw new RuntimeException('Invalid temporary database name.');
}
$settings = config('Database')->default;
$admin = null;
if (! $worker) {
    $admin = \Config\Database::connect($settings, false);
    $admin->query('CREATE DATABASE `' . $name . '`');
}
$settings['database'] = $name;
$settings['DBPrefix'] = '';
config('Database')->tests = $settings;
$db = db_connect('tests');

if ($worker) {
    $service = new \App\Libraries\InventoryIntegrityService();
    try {
        $service->transaction('a', function () use ($service, $argv) {
            if (($argv[3] ?? '') === 'reserve') {
                if ($service->available('a', 'p', 'w') < 6) {
                    throw new RuntimeException('Insufficient stock');
                }
                usleep(250000);
                $service->changeReserved('a', 'p', 'w', 6);
            } else {
                usleep(250000);
                $service->changeStock('a', 'p', 'w', 4);
            }
        });
        echo 'ok';
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'Insufficient stock') { throw $e; }
        echo 'rejected';
    }
    exit;
}

function checkInventory(bool $condition, string $message): void
{
    if (! $condition) { throw new RuntimeException($message); }
}

function parallelInventory(string $name, string $operation): array
{
    $processes = [];
    for ($i = 0; $i < 2; $i++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $name, $operation], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        if (! is_resource($process)) { throw new RuntimeException('Cannot start worker'); }
        $processes[] = [$process, $pipes];
    }
    $results = [];
    foreach ($processes as [$process, $pipes]) {
        $results[] = trim(stream_get_contents($pipes[1]));
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        checkInventory($code === 0, 'Worker failed: ' . $errors);
    }
    sort($results);
    return $results;
}

try {
    $db->query('CREATE TABLE companies (id VARCHAR(36) PRIMARY KEY) ENGINE=InnoDB');
    $db->query('CREATE TABLE inventory_products (id VARCHAR(36) PRIMARY KEY, company_id VARCHAR(36), min_stock DECIMAL(14,2)) ENGINE=InnoDB');
    $db->query('CREATE TABLE inventory_locations (id VARCHAR(36) PRIMARY KEY) ENGINE=InnoDB');
    $db->table('inventory_locations')->insert(['id' => 'l1']);
    $db->query('CREATE TABLE inventory_stock_levels (id VARCHAR(36) PRIMARY KEY, company_id VARCHAR(36), product_id VARCHAR(36), warehouse_id VARCHAR(36), location_id VARCHAR(36) NULL, quantity DECIMAL(14,2), reserved_quantity DECIMAL(14,2), min_stock DECIMAL(14,2), created_at DATETIME, updated_at DATETIME, UNIQUE KEY old_product_warehouse (product_id, warehouse_id), CONSTRAINT fk_test_location FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE SET NULL ON UPDATE CASCADE) ENGINE=InnoDB');
    $db->table('companies')->insert(['id' => 'a']);
    $db->table('inventory_products')->insert(['id' => 'p', 'company_id' => 'a']);
    $db->table('inventory_stock_levels')->insert(['id' => 'stock', 'company_id' => 'a', 'product_id' => 'p', 'warehouse_id' => 'w', 'quantity' => 10, 'reserved_quantity' => 0]);
    require APPPATH . 'Database/Migrations/2026-09-21-120000_InventoryStockLocations.php';
    $migration = new \App\Database\Migrations\InventoryStockLocations(\Config\Database::forge('tests'));
    $migration->up();
    $indexes = $db->getIndexData('inventory_stock_levels');
    checkInventory(! isset($indexes['old_product_warehouse']), 'Old unique index remains');
    $db->table('inventory_stock_levels')->insert(['id' => 'location', 'company_id' => 'a', 'product_id' => 'p', 'warehouse_id' => 'w', 'location_id' => 'l1', 'quantity' => 0, 'reserved_quantity' => 0]);
    checkInventory($db->table('inventory_stock_levels')->countAllResults() === 2, 'Multiple locations rejected');
    checkInventory(parallelInventory($name, 'reserve') === ['ok', 'rejected'], 'Concurrent reservations exceeded stock');
    checkInventory((float) $db->table('inventory_stock_levels')->selectSum('reserved_quantity', 'qty')->get()->getRowArray()['qty'] === 6.0, 'Incorrect reserved balance');
    checkInventory(parallelInventory($name, 'entry') === ['ok', 'ok'], 'Concurrent entries failed');
    checkInventory((float) $db->table('inventory_stock_levels')->selectSum('quantity', 'qty')->get()->getRowArray()['qty'] === 18.0, 'Lost concurrent update');
    try {
        $migration->down();
        throw new RuntimeException('Unsafe migration rollback was allowed');
    } catch (RuntimeException $e) {
        checkInventory(str_contains($e->getMessage(), 'multiples ubicaciones'), $e->getMessage());
    }
    $db->table('inventory_stock_levels')->where('id', 'location')->delete();
    $migration->down();
    echo "OK: migration up/down, multiple locations, concurrent reservations and entries.\n";
} finally {
    $db->close();
    $admin->query('DROP DATABASE `' . $name . '`');
    $admin->close();
}
