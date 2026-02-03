<?php
include 'config/db.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$res = $conn->query("SELECT id, booking_number, bill_amount, final_cost, estimated_cost FROM bookings LIMIT 5");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
