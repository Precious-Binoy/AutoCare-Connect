<?php
require_once 'config/db.php';
$conn = getDbConnection();

$queries = [
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS progress_percentage INT DEFAULT 0",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS bill_amount DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS service_notes TEXT",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS is_billed BOOLEAN DEFAULT FALSE"
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "Success: $q\n";
    } else {
        echo "Error: " . $conn->error . " for query: $q\n";
    }
}
?>
