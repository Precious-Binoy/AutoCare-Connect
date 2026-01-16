<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

echo "Fixing drivers table schema...\n";
$conn = getDbConnection();

// Check if email column exists in drivers table
$result = $conn->query("SHOW COLUMNS FROM drivers LIKE 'email'");
if ($result->num_rows > 0) {
    echo "Found email column in drivers table. Removing it...\n";
    $sql = "ALTER TABLE drivers DROP COLUMN email";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Removed email column from drivers table.\n";
    } else {
        echo "✗ Error removing email column: " . $conn->error . "\n";
    }
} else {
    echo "✓ email column does not exist in drivers table.\n";
}

// Check other redundant columns
$redundantColumns = ['name', 'phone', 'password'];
foreach ($redundantColumns as $col) {
    $result = $conn->query("SHOW COLUMNS FROM drivers LIKE '$col'");
    if ($result->num_rows > 0) {
        echo "Found $col column in drivers table. Removing it...\n";
        $sql = "ALTER TABLE drivers DROP COLUMN $col";
        if ($conn->query($sql) === TRUE) {
            echo "✓ Removed $col column from drivers table.\n";
        } else {
            echo "✗ Error removing $col column: " . $conn->error . "\n";
        }
    }
}

echo "\nSchema fix completed.\n";
$conn->close();
?>
