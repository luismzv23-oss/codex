<?php
$db = new mysqli('localhost', 'root', '', 'codex');
$r = $db->query("SELECT id, name, code, document_type_id FROM sales_points_of_sale");
while ($row = $r->fetch_assoc()) {
    print_r($row);
}
