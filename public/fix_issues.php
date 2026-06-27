<?php
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
chdir(FCPATH);

// 1. Fix ArcaService.php
$file = __DIR__ . '/../app/Libraries/ArcaService.php';
$content = file_get_contents($file);

// Find the duplicate block
// First occurrence of callWsfev1
$firstPos = strpos($content, '    private function callWsfev1(');
if ($firstPos !== false) {
    // Second occurrence
    $secondPos = strpos($content, '    private function callWsfev1(', $firstPos + 100);
    if ($secondPos !== false) {
        $endPos = strpos($content, '    // ── Integration logging', $secondPos);
        if ($endPos !== false) {
            $cleanContent = substr($content, 0, $secondPos) . substr($content, $endPos);
            file_put_contents($file, $cleanContent);
            echo "ArcaService.php fixed.<br>\n";
        } else {
            echo "Error: Could not find end of duplicate block in ArcaService.<br>\n";
        }
    } else {
        echo "ArcaService.php already fixed (second callWsfev1 not found).<br>\n";
    }
} else {
    echo "Error: callWsfev1 not found at all.<br>\n";
}

// 2. Run migrations
require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once SYSTEMPATH . 'Config/DotEnv.php';
(new CodeIgniter\Config\DotEnv(ROOTPATH))->load();
$app = \Config\Services::codeigniter();
$app->initialize();

$migrate = \Config\Services::migrations();
try {
    if ($migrate->latest()) {
        echo "Migrations ran successfully.<br>\n";
    } else {
        echo "No new migrations to run.<br>\n";
    }
} catch (\Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "<br>\n";
}
