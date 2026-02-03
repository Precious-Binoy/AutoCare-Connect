<?php
include 'config/db.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$res = $conn->query("DESCRIBE bookings");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>
