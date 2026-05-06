<?php
require 'vendor/autoload.php';
require 'system/bootstrap.php';

$db = db_connect();
$count = $db->table('inventory_products')->where('active', 1)->countAllResults();
echo "PRODUCTS: " . $count . "\n";
