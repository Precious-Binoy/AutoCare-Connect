<?php
require_once 'config/db.php';
$conn = getDbConnection();

echo "--- VEHICLES SCHEMA ---\n";
$result = $conn->query("DESCRIBE vehicles");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "\n--- PICKUP_DELIVERY SCHEMA ---\n";
$result = $conn->query("DESCRIBE pickup_delivery");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
