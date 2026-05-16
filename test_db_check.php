<?php
$m = new mysqli('127.0.0.1','root','','codex');
if ($m->connect_error) die('Connect error: ' . $m->connect_error);

echo "--- COLUMNS ---\n";
$r2 = $m->query('SHOW COLUMNS FROM sales_document_types');
while($row = $r2->fetch_assoc()) echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Default'] . "\n";

echo "\n--- DOCUMENT TYPES ---\n";
$r = $m->query('SELECT * FROM sales_document_types ORDER BY sort_order LIMIT 25');
while($row = $r->fetch_assoc()) {
    echo $row['code'] . " | " . $row['name'] . " | cat:" . $row['category'] . " | ch:" . ($row['channel'] ?? '-') . " | seq:" . ($row['sequence_key'] ?? '-') . "\n";
}

echo "\n--- PURCHASE TABLES ---\n";
$r3 = $m->query("SHOW TABLES LIKE 'purchase%'");
while($row = $r3->fetch_row()) echo $row[0] . "\n";

echo "\n--- SALE COLUMNS ---\n";
$r4 = $m->query('SHOW COLUMNS FROM sales');
while($row = $r4->fetch_assoc()) echo $row['Field'] . " | " . $row['Type'] . "\n";
