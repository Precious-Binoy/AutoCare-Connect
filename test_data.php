<?php
require_once 'config/db.php';
$conn = getDbConnection();

echo "--- Users/Bookings Data ---\n";
$res = $conn->query("SELECT u.name, u.phone, b.id as b_id, b.status FROM users u JOIN bookings b ON b.user_id = u.id");
while($row = $res->fetch_assoc()) {
    echo "User: {$row['name']} | Phone: " . ($row['phone'] ?: 'NULL') . " | Booking: {$row['b_id']} | Status: {$row['status']}\n";
}

echo "\n--- Available Jobs in Mechanic Dashboard ---\n";
$availableJobsQuery = "SELECT b.id, b.status, u.name as customer_name, u.phone as customer_phone
                       FROM bookings b 
                       JOIN users u ON b.user_id = u.id 
                       WHERE b.mechanic_id IS NULL";
$res = $conn->query($availableJobsQuery);
while($row = $res->fetch_assoc()) {
    echo "ID: {$row['id']} | Customer: {$row['customer_name']} | Phone: " . ($row['customer_phone'] ?: 'NULL') . "\n";
}
?>
