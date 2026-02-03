<?php
include 'config/db.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$res = $conn->query("SELECT id, booking_number, bill_amount, final_cost, estimated_cost FROM bookings ORDER BY id DESC LIMIT 10");
while($row = $res->fetch_assoc()) {
    echo "ID: {$row['id']} | Num: {$row['booking_number']} | Bill: {$row['bill_amount']} | Final: {$row['final_cost']} | Est: {$row['estimated_cost']}\n";
}
?>
