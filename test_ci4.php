<?php
define('FCPATH', __DIR__ . '/public' . DIRECTORY_SEPARATOR);
require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';

$app = Config\Services::codeigniter();
$app->initialize();

$db = db_connect();
$companyId = $db->table('companies')->select('id')->limit(1)->get()->getRowArray()['id'] ?? null;

if (!$companyId) {
    echo "No company found.\n";
    exit;
}

$model = new \App\Models\InventoryProductModel();
$products = $model->where('company_id', $companyId)->where('active', 1)->findAll();
echo "Total products: " . count($products) . "\n";
if (count($products) > 0) {
    echo "First product: " . print_r($products[0], true) . "\n";
}
