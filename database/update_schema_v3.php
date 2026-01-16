<?php
require_once __DIR__ . '/../config/db.php';

$conn = getDbConnection();

$sql = "ALTER TABLE pickup_delivery 
        ADD COLUMN parking_info TEXT AFTER address,
        ADD COLUMN pickup_location_name VARCHAR(255) AFTER parking_info";

if ($conn->query($sql)) {
    echo "Successfully updated pickup_delivery table with parking columns.\n";
} else {
    echo "Error updating table: " . $conn->error . "\n";
}
?>
