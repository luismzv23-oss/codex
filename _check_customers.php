<?php
require __DIR__ . '/public/index.php';
$db = \Config\Database::connect();
$fields = $db->getFieldData('customers');
echo "FIELDS:\n";
foreach ($fields as $f) {
    echo "{$f->name}: type={$f->type}, null=" . ($f->nullable ? 'YES' : 'NO') . ", default=" . var_export($f->default, true) . "\n";
}
echo "\nINDEXES:\n";
$indexes = $db->query("SHOW INDEX FROM customers")->getResultArray();
foreach ($indexes as $idx) {
    echo "{$idx['Key_name']}: column={$idx['Column_name']}, unique=" . ($idx['Non_unique'] == 0 ? 'YES' : 'NO') . "\n";
}
