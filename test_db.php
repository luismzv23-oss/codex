<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'codex');
if ($mysqli->connect_error) { die('Connect Error'); }
$res = $mysqli->query('SELECT COUNT(*) as c FROM inventory_products WHERE active = 1');
echo 'PRODUCTS: ' . $res->fetch_assoc()['c'] . "\n";
$res = $mysqli->query('SELECT * FROM inventory_products WHERE active = 1 LIMIT 1');
print_r($res->fetch_assoc());
