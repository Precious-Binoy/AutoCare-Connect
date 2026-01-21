<?php
require_once 'config/db.php';
$columns = executeQuery("SHOW COLUMNS FROM bookings");
while ($row = $columns->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>
