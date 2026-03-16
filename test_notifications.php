<?php
require_once __DIR__ . '/config/db.php';
$conn = getDbConnection();

$res = $conn->query("SELECT * FROM users WHERE role = 'driver'");
echo "Drivers in users table:\n";
while($r = $res->fetch_assoc()) {
    print_r($r);
}

$res = $conn->query("SELECT * FROM drivers");
echo "\nDrivers in drivers table:\n";
while($r = $res->fetch_assoc()) {
    print_r($r);
}

$res = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5");
echo "\nRecent notifications:\n";
while($r = $res->fetch_assoc()) {
    print_r($r);
}
?>
