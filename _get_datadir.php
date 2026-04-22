<?php
$db = new mysqli('127.0.0.1', 'root', '');
$res = $db->query("SHOW VARIABLES LIKE 'datadir'");
$row = $res->fetch_assoc();
echo $row['Value'];
