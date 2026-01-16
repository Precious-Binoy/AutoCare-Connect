<?php
/**
 * Database Migration Script for Service Tracking Fixes
 * Run this to add necessary columns and ensure driver table has vehicle_number
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

echo "Running database migration for service tracking fixes...\n";
$conn = getDbConnection();

// 1. Add has_pickup_delivery column to bookings table
$result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'has_pickup_delivery'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE bookings ADD COLUMN has_pickup_delivery BOOLEAN DEFAULT FALSE AFTER notes";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Added has_pickup_delivery column to bookings table.\n";
        
        // Update existing bookings based on pickup_delivery records
        $updateSql = "UPDATE bookings b 
                      SET b.has_pickup_delivery = TRUE 
                      WHERE EXISTS (SELECT 1 FROM pickup_delivery pd WHERE pd.booking_id = b.id)";
        $conn->query($updateSql);
        echo "✓ Updated existing bookings with pickup/delivery status.\n";
    } else {
        echo "✗ Error adding has_pickup_delivery column: " . $conn->error . "\n";
    }
} else {
    echo "✓ has_pickup_delivery column already exists.\n";
}

// 2. Ensure drivers table has vehicle_number column
$result = $conn->query("SHOW COLUMNS FROM drivers LIKE 'vehicle_number'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE drivers ADD COLUMN vehicle_number VARCHAR(50) AFTER vehicle_type";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Added vehicle_number column to drivers table.\n";
    } else {
        echo "✗ Error adding vehicle_number column: " . $conn->error . "\n";
    }
} else {
    echo "✓ vehicle_number column already exists in drivers table.\n";
}

// 3. Ensure bill_amount and service_notes columns exist in bookings
$result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'bill_amount'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE bookings ADD COLUMN bill_amount DECIMAL(10,2) DEFAULT 0.00 AFTER final_cost";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Added bill_amount column to bookings table.\n";
    } else {
        echo "✗ Error adding bill_amount column: " . $conn->error . "\n";
    }
} else {
    echo "✓ bill_amount column already exists.\n";
}

$result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'service_notes'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE bookings ADD COLUMN service_notes TEXT AFTER bill_amount";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Added service_notes column to bookings table.\n";
    } else {
        echo "✗ Error adding service_notes column: " . $conn->error . "\n";
    }
} else {
    echo "✓ service_notes column already exists.\n";
}

$result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'is_billed'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE bookings ADD COLUMN is_billed BOOLEAN DEFAULT FALSE AFTER service_notes";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Added is_billed column to bookings table.\n";
    } else {
        echo "✗ Error adding is_billed column: " . $conn->error . "\n";
    }
} else {
    echo "✓ is_billed column already exists.\n";
}

$result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'progress_percentage'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE bookings ADD COLUMN progress_percentage INT DEFAULT 0 AFTER is_billed";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Added progress_percentage column to bookings table.\n";
    } else {
        echo "✗ Error adding progress_percentage column: " . $conn->error . "\n";
    }
} else {
    echo "✓ progress_percentage column already exists.\n";
}

// 4. Add parking_info column to pickup_delivery if missing
$result = $conn->query("SHOW COLUMNS FROM pickup_delivery LIKE 'parking_info'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE pickup_delivery ADD COLUMN parking_info TEXT AFTER address";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Added parking_info column to pickup_delivery table.\n";
    } else {
        echo "✗ Error adding parking_info column: " . $conn->error . "\n";
    }
} else {
    echo "✓ parking_info column already exists.\n";
}

echo "\n✅ Database migration completed successfully!\n";
$conn->close();
?>
