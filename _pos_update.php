<?php
$db = new mysqli('localhost', 'root', '', 'codex');
$db->query("UPDATE sales_points_of_sale SET afip_pos_number = 1 WHERE code = 'PV-STD'");
$db->query("UPDATE sales_points_of_sale SET afip_pos_number = 2 WHERE code = 'PV-KIOSCO'");
$db->query("UPDATE sales_points_of_sale SET afip_pos_number = 1 WHERE afip_pos_number IS NULL"); // fallback
echo "DB Updated.";
