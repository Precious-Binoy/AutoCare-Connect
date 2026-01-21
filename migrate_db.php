<?php
require_once 'config/db.php';

$conn = getDbConnection();

$alterBookings = [
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS mechanic_fee DECIMAL(10,2) DEFAULT 0.00 AFTER service_type",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS has_pickup_delivery TINYINT(1) DEFAULT 0 AFTER mechanic_id",
    "ALTER TABLE bookings MODIFY COLUMN final_cost DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS service_notes TEXT AFTER final_cost",
    "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS is_billed BOOLEAN DEFAULT FALSE AFTER service_notes"
];

foreach ($alterBookings as $sql) {
    if ($conn->query($sql)) {
        echo "Executed: $sql\n";
    } else {
        echo "Error: " . $conn->error . " in $sql\n";
    }
}

$alterPickupDelivery = [
    "ALTER TABLE pickup_delivery ADD COLUMN IF NOT EXISTS fee DECIMAL(10,2) DEFAULT 0.00"
];

foreach ($alterPickupDelivery as $sql) {
    if ($conn->query($sql)) {
        echo "Executed: $sql\n";
    } else {
        echo "Error: " . $conn->error . " in $sql\n";
    }
}

// Also check parts_used table
$createPartsUsed = "CREATE TABLE IF NOT EXISTS parts_used (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    part_name VARCHAR(255) NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) DEFAULT 0.00,
    total_price DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
)";

if ($conn->query($createPartsUsed)) {
    echo "Checked parts_used table.\n";
} else {
    echo "Error creating parts_used: " . $conn->error . "\n";
}

echo "Migration finished.\n";
?>
