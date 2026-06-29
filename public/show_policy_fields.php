<?php
header('Content-Type: text/plain');
$db = db_connect();
$fields = $db->getFieldNames("sales_discount_policies");
print_r($fields);
unlink(__FILE__);
