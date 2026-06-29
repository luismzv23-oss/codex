<?php
header('Content-Type: text/plain');
$lines = file("../app/Controllers/Api/V1/SalesController.php");
foreach ($lines as $i => $line) {
    if (strpos($line, "parseSaleItems") !== false) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
