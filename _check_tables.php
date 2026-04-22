<?php
$db = new mysqli('127.0.0.1', 'root', '', 'codex');
$result = $db->query('SHOW TABLES');
while($row = $result->fetch_row()) { echo $row[0] . PHP_EOL; }
