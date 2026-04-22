<?php
$db = new mysqli('localhost', 'root', '', 'codex');
$id = '10ec6812-c420-452f-8a61-8d0a1e68747b';
$r = $db->query("SELECT s.sale_number, p.code, p.afip_pos_number FROM sales s JOIN sales_points_of_sale p ON s.point_of_sale_id = p.id WHERE s.id = '$id'");
while ($row = $r->fetch_assoc()) { print_r($row); }
