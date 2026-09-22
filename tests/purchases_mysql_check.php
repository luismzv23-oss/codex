<?php

// Real row-lock and migration checks; never writes business data in the configured database.
require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';
helper('app');
$worker = ($argv[1] ?? '') === '--worker';
$name = $worker ? ($argv[2] ?? '') : 'codex_purchases_check_' . bin2hex(random_bytes(5));
if (! preg_match('/\Acodex_purchases_check_[a-f0-9]{10}\z/', $name)) { throw new RuntimeException('Invalid test database'); }
$settings = config('Database')->default;
$admin = $worker ? null : \Config\Database::connect($settings, false);
if ($admin) { $admin->query('CREATE DATABASE `' . $name . '`'); }
$settings['database'] = $name; $settings['DBPrefix'] = '';
config('Database')->tests = $settings;
$db = db_connect('tests');

if ($worker) {
    try {
        (new \App\Libraries\InventoryIntegrityService())->transaction('a', function () use ($db, $argv) {
            if ($argv[3] === 'receipt') {
                $order = $db->table('purchase_orders')->where('id', 'o')->get()->getRowArray();
                (new \App\Libraries\PurchaseIntegrityService())->validateReceipt('a', $order, [['purchase_order_item_id' => 'oi', 'quantity' => 6]]);
                $item = $db->table('purchase_order_items')->where('id', 'oi')->get()->getRowArray();
                usleep(250000);
                $db->table('purchase_order_items')->where('id', 'oi')->update(['received_quantity' => $item['received_quantity'] + 6]);
            } else {
                $payable = $db->table('purchase_payables')->where('id', 'pay')->get()->getRowArray();
                if ((float) $payable['balance_amount'] < 60) { throw new RuntimeException('Insufficient balance'); }
                usleep(250000);
                $db->table('purchase_payables')->where('id', 'pay')->update(['paid_amount' => $payable['paid_amount'] + 60, 'balance_amount' => $payable['balance_amount'] - 60]);
            }
        });
        echo 'ok';
    } catch (RuntimeException $e) {
        if (! in_array($e->getMessage(), ['Insufficient balance', 'La cantidad acumulada supera el pendiente de la orden.'], true)) { throw $e; }
        echo 'rejected';
    }
    exit;
}

function checkPurchase(bool $ok, string $message): void { if (! $ok) { throw new RuntimeException($message); } }
function parallelPurchases(string $name, string $operation): array
{
    $workers = [];
    for ($i = 0; $i < 2; $i++) {
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $name, $operation], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        if (! is_resource($process)) { throw new RuntimeException('Cannot start worker'); }
        $workers[] = [$process, $pipes];
    }
    $results = [];
    foreach ($workers as [$process, $pipes]) {
        $results[] = trim(stream_get_contents($pipes[1])); $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        checkPurchase(proc_close($process) === 0, 'Worker failed: ' . $errors);
    }
    sort($results); return $results;
}

try {
    $schemas = [
        'companies' => 'id CHAR(36) PRIMARY KEY',
        'inventory_warehouses' => 'id CHAR(36) PRIMARY KEY, company_id CHAR(36), active INT',
        'purchase_orders' => 'id CHAR(36) PRIMARY KEY, status VARCHAR(30), warehouse_id CHAR(36)',
        'purchase_order_items' => 'id CHAR(36) PRIMARY KEY, purchase_order_id CHAR(36), quantity DECIMAL(15,4), received_quantity DECIMAL(15,4)',
        'purchase_receipts' => 'id CHAR(36) PRIMARY KEY',
        'purchase_payables' => 'id CHAR(36) PRIMARY KEY, company_id CHAR(36), purchase_receipt_id CHAR(36) NOT NULL, total_amount DECIMAL(15,2), paid_amount DECIMAL(15,2), balance_amount DECIMAL(15,2), CONSTRAINT fk_test_purchase_receipt FOREIGN KEY (purchase_receipt_id) REFERENCES purchase_receipts(id) ON DELETE CASCADE ON UPDATE CASCADE',
        'purchase_credit_notes' => 'id CHAR(36) PRIMARY KEY',
    ];
    foreach ($schemas as $table => $columns) { $db->query('CREATE TABLE ' . $table . ' (' . $columns . ') ENGINE=InnoDB'); }
    $db->table('companies')->insert(['id' => 'a']);
    $db->table('inventory_warehouses')->insert(['id' => 'w', 'company_id' => 'a', 'active' => 1]);
    $db->table('purchase_orders')->insert(['id' => 'o', 'status' => 'approved', 'warehouse_id' => 'w']);
    $db->table('purchase_order_items')->insert(['id' => 'oi', 'purchase_order_id' => 'o', 'quantity' => 10, 'received_quantity' => 0]);
    $db->table('purchase_receipts')->insert(['id' => 'r']);
    $db->table('purchase_payables')->insert(['id' => 'pay', 'company_id' => 'a', 'purchase_receipt_id' => 'r', 'total_amount' => 100, 'paid_amount' => 0, 'balance_amount' => 100]);
    $before = $db->table('purchase_payables')->get()->getRowArray();
    require APPPATH . 'Database/Migrations/2026-09-21-130000_PurchaseDocumentPayables.php';
    $migration = new \App\Database\Migrations\PurchaseDocumentPayables(\Config\Database::forge('tests'));
    $migration->up();
    $after = $db->table('purchase_payables')->get()->getRowArray(); unset($after['purchase_invoice_id']);
    checkPurchase($before === $after, 'Migration changed existing payable');
    checkPurchase(count($db->getForeignKeyData('purchase_payables')) === 1, 'Receipt foreign key lost');
    $db->table('purchase_payables')->insert(['id' => 'direct', 'company_id' => 'a', 'purchase_receipt_id' => null, 'purchase_invoice_id' => 'invoice']);
    try { $migration->down(); throw new LogicException('Unsafe reversal accepted'); }
    catch (RuntimeException $e) { checkPurchase(strpos($e->getMessage(), 'No se puede revertir') === 0, $e->getMessage()); }
    $db->table('purchase_payables')->where('id', 'direct')->delete();
    $migration->down(); $migration->up();
    checkPurchase(parallelPurchases($name, 'receipt') === ['ok', 'rejected'], 'Overreceipt under concurrency');
    checkPurchase((float) $db->table('purchase_order_items')->get()->getRowArray()['received_quantity'] === 6.0, 'Wrong received balance');
    checkPurchase(parallelPurchases($name, 'payment') === ['ok', 'rejected'], 'Overpayment under concurrency');
    $payable = $db->table('purchase_payables')->where('id', 'pay')->get()->getRowArray();
    checkPurchase((float) $payable['paid_amount'] === 60.0 && (float) $payable['balance_amount'] === 40.0, 'Lost payment update');
    echo "OK: purchase migration/reversal, foreign key preservation, concurrent receipt and payment locks.\n";
} finally {
    $db->close(); $admin->query('DROP DATABASE `' . $name . '`'); $admin->close();
}
