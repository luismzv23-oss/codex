<?php
header('Content-Type: text/plain');
$lines = file("../app/Controllers/SalesController.php");
foreach ($lines as $i => $line) {
    if (strpos($line, "paymentMethodDiscount") !== false) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
