<?php
require_once 'config/db.php';
$columns = executeQuery("SHOW COLUMNS FROM pickup_delivery");
while ($row = $columns->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>
