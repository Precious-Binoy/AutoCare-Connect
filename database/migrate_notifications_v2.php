<?php
/**
 * Notifications v2 Migration
 * - Adds link_url column to notifications
 * - Updates type ENUM with all needed types
 */

require_once __DIR__ . '/../config/db.php';

$conn = getDbConnection();

echo "<pre>";

// 1. Add link_url column if not present
$result = $conn->query("SHOW COLUMNS FROM notifications LIKE 'link_url'");
if ($result->num_rows === 0) {
    if ($conn->query("ALTER TABLE notifications ADD COLUMN link_url VARCHAR(255) DEFAULT NULL AFTER type")) {
        echo "✅ Added link_url column\n";
    } else {
        echo "❌ Failed to add link_url: " . $conn->error . "\n";
    }
} else {
    echo "ℹ️ link_url column already exists\n";
}

// 2. Modify type ENUM to include all needed values
$alterType = "ALTER TABLE notifications MODIFY COLUMN type 
ENUM('booking','service','pickup','delivery','leave','message','job_request','assignment','announcement','general') 
DEFAULT 'general'";

if ($conn->query($alterType)) {
    echo "✅ Updated type ENUM\n";
} else {
    echo "❌ Failed to update ENUM: " . $conn->error . "\n";
}

echo "\n✅ Migration complete!\n";
echo "</pre>";
?>
