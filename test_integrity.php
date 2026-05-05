<?php
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';

echo "CodeIgniter loaded successfully.\n";

$db = \Config\Database::connect();
$tables = $db->listTables();
$requiredTables = ['sales', 'sales_orders', 'sales_quotes', 'accounts', 'inventory_products', 'journal_entries'];

echo "Checking database tables...\n";
$missing = [];
foreach ($requiredTables as $table) {
    if (!in_array($table, $tables)) {
        $missing[] = $table;
    }
}

if (empty($missing)) {
    echo "All critical database tables are present.\n";
} else {
    echo "Missing tables: " . implode(', ', $missing) . "\n";
}

echo "Checking critical classes for syntax errors...\n";
$classes = [
    \App\Controllers\SalesController::class,
    \App\Libraries\AccountingService::class,
    \App\Libraries\ArcaService::class,
    \App\Controllers\AccountingController::class,
    \App\Controllers\PurchasesController::class
];

$errors = 0;
foreach ($classes as $class) {
    try {
        if (class_exists($class)) {
            echo "Class $class is valid.\n";
        } else {
            echo "Class $class NOT FOUND.\n";
            $errors++;
        }
    } catch (\Throwable $e) {
        echo "Error loading $class: " . $e->getMessage() . "\n";
        $errors++;
    }
}

if ($errors === 0) {
    echo "All critical classes loaded successfully.\n";
} else {
    echo "Found $errors errors loading classes.\n";
}

echo "Integrity check completed.\n";
