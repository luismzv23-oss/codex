<?php
$files = ['app/Libraries/AutomationService.php', 'app/Libraries/CodexAssistService.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        $lines = file($file);
        foreach ($lines as $i => $line) {
            if (strpos($line, 'company_settings') !== false) {
                echo basename($file) . ':' . ($i+1) . ':' . trim($line) . PHP_EOL;
            }
        }
    }
}
