<?php
$db = new mysqli('localhost', 'root', '', 'codex');
$r = $db->query("SELECT service_slug, operation, status, error_message, response_payload, request_payload FROM integration_logs ORDER BY created_at DESC LIMIT 3");
while ($row = $r->fetch_assoc()) {
    echo "=== " . $row['service_slug'] . " | " . $row['operation'] . " | " . $row['status'] . " ===" . PHP_EOL;
    echo "Error: " . ($row['error_message'] ?? 'none') . PHP_EOL;
    echo "Response: " . substr($row['response_payload'] ?? '', 0, 1500) . PHP_EOL;
    echo "Request: " . substr($row['request_payload'] ?? '', 0, 1000) . PHP_EOL . PHP_EOL;
}

// Also check sales_arca_events
echo "=== ARCA EVENTS ===" . PHP_EOL;
$r2 = $db->query("SELECT service_slug, action, status, response_payload FROM sales_arca_events ORDER BY created_at DESC LIMIT 3");
while ($row = $r2->fetch_assoc()) {
    echo $row['service_slug'] . " | " . $row['action'] . " | " . $row['status'] . PHP_EOL;
    echo substr($row['response_payload'] ?? '', 0, 1500) . PHP_EOL . PHP_EOL;
}
