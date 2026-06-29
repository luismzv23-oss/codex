<?php
header('Content-Type: text/plain');
$logDir = "../writable/logs/";
if (!is_dir($logDir)) {
    echo "Logs directory does not exist: $logDir\n";
    exit;
}
$files = scandir($logDir);
foreach ($files as $file) {
    if ($file !== '.' && $file !== '..') {
        $path = $logDir . $file;
        echo "File: $file (" . filesize($path) . " bytes)\n";
        if (strpos($file, date('Y-m-d')) !== false) {
            echo "--- CONTENT ---\n";
            echo file_get_contents($path);
            echo "\n---------------\n";
        }
    }
}
