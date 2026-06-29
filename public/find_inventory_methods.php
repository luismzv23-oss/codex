<?php
header('Content-Type: text/plain');
$lines = file("../app/Controllers/InventoryController.php");
foreach ($lines as $i => $line) {
    if (strpos($line, "assembly") !== false || strpos($line, "revaluation") !== false || strpos($line, "closure") !== false || strpos($line, "function") !== false) {
        if (strpos($line, "function") !== false) {
            echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
        }
    }
}
