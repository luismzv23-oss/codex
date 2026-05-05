<?php
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';

$companyId = \Config\Database::connect()->table('companies')->select('id')->limit(1)->get()->getRowArray()['id'];

if (!$companyId) {
    echo "No company found\n";
    exit;
}

$libroIva = new \App\Libraries\LibroIvaDigitalService();
try {
    $report = $libroIva->ventasReport($companyId, '2026-05-01', '2026-05-31');
    echo "Ventas report OK. Count: " . count($report['records']) . "\n";
} catch (\Throwable $e) {
    echo "Error in ventasReport: " . $e->getMessage() . "\n";
}

try {
    $report2 = $libroIva->comprasReport($companyId, '2026-05-01', '2026-05-31');
    echo "Compras report OK. Count: " . count($report2['records']) . "\n";
} catch (\Throwable $e) {
    echo "Error in comprasReport: " . $e->getMessage() . "\n";
}

$sicore = new \App\Libraries\SicoreService();
try {
    $report3 = $sicore->periodSummary($companyId, '2026-05-01', '2026-05-31');
    echo "Sicore OK.\n";
} catch (\Throwable $e) {
    echo "Error in Sicore: " . $e->getMessage() . "\n";
}

echo "Test done.\n";
