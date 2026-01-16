<?php
// Script to update the database schema
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

echo "Updating database schema...\n";
$conn = getDbConnection();

// Tables to create/update
$queries = [
    // Create Job Requests Table
    "CREATE TABLE IF NOT EXISTS job_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        role_requested ENUM('mechanic', 'driver') NOT NULL,
        experience_years INT,
        qualifications TEXT,
        password_hash VARCHAR(255) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        admin_comments TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Create Drivers Table
    "CREATE TABLE IF NOT EXISTS drivers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        license_number VARCHAR(50),
        vehicle_type VARCHAR(50),
        is_available BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Add driver_user_id to pickup_delivery if it doesn't exist
    // Checking column existence first or using IGNORE/IF NOT EXISTS logic where possible
    // For simplicity in this env, we'll try to add it and ignore errors if it exists, or check first
];

// Execute CREATE TABLE queries
foreach ($queries as $query) {
    if ($conn->query($query) === TRUE) {
        echo "Table created/checked successfully.\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n";
    }
}

// Add driver_user_id to pickup_delivery
$result = $conn->query("SHOW COLUMNS FROM pickup_delivery LIKE 'driver_user_id'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE pickup_delivery ADD COLUMN driver_user_id INT, ADD FOREIGN KEY (driver_user_id) REFERENCES users(id) ON DELETE SET NULL";
    if ($conn->query($sql) === TRUE) {
        echo "Added driver_user_id to pickup_delivery.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "Column driver_user_id already exists in pickup_delivery.\n";
}

// Update users enum if needed (Driver role)
// This is tricky in MySQL as modifying ENUMs can be strict.
// We will try to modify the column to include 'driver' role.
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
$row = $result->fetch_assoc();
if (strpos($row['Type'], "'driver'") === false) {
    $sql = "ALTER TABLE users MODIFY COLUMN role ENUM('customer', 'mechanic', 'admin', 'driver') DEFAULT 'customer'";
    if ($conn->query($sql) === TRUE) {
        echo "Updated users role ENUM.\n";
    } else {
        echo "Error updating role ENUM: " . $conn->error . "\n";
    }
} else {
    echo "Role ENUM already includes 'driver'.\n";
}

// Add status 'ready_for_delivery' and 'delivered' to bookings enum
$result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'status'");
$row = $result->fetch_assoc();
if (strpos($row['Type'], "'ready_for_delivery'") === false) {
    $sql = "ALTER TABLE bookings MODIFY COLUMN status ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'ready_for_delivery', 'delivered') DEFAULT 'pending'";
    if ($conn->query($sql) === TRUE) {
        echo "Updated bookings status ENUM.\n";
    } else {
        echo "Error updating status ENUM: " . $conn->error . "\n";
    }
} else {
    echo "Status ENUM already includes delivery statuses.\n";
}


echo "Database update complete.\n";
?>
