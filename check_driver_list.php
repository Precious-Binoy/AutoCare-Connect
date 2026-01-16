<?php
require_once __DIR__ . '/config/db.php';
$conn = getDbConnection();

echo "--- Users with role 'driver' ---\n";
$res = $conn->query("SELECT id, name, email FROM users WHERE role='driver'");
while ($row = $res->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | " . $row['name'] . " | " . $row['email'] . "\n";
}

echo "\n--- Drivers Table ---\n";
$res = $conn->query("SELECT d.id, d.user_id, d.license_number FROM drivers d");
while ($row = $res->fetch_assoc()) {
    echo "DID: " . $row['id'] . " | UID: " . $row['user_id'] . "\n";
}
?>
