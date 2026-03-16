<?php
require_once __DIR__ . '/config/db.php';
$conn = getDbConnection();

$res = $conn->query("SELECT * FROM bookings ORDER BY created_at DESC LIMIT 5");
echo "Recent Bookings:\n";
while($r = $res->fetch_assoc()) {
    print_r($r);
}

$res = $conn->query("SELECT * FROM pickup_delivery ORDER BY created_at DESC LIMIT 5");
echo "\nRecent Pickup/Delivery:\n";
while($r = $res->fetch_assoc()) {
    print_r($r);
}
?>
