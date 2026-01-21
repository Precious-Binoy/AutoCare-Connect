<?php
require_once 'config/db.php';
echo "--- bookings ---\n";
$columns = executeQuery("SHOW COLUMNS FROM bookings");
while ($row = $columns->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
echo "\n--- pickup_delivery ---\n";
$columns = executeQuery("SHOW COLUMNS FROM pickup_delivery");
while ($row = $columns->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>
