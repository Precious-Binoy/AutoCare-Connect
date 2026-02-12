<?php
/**
 * Database Migration: Update Notification Types
 * Expands the notification type ENUM to support new notification categories
 */

require_once(__DIR__ . '/../config/db.php');

echo "Starting notification types migration...\n";

$conn = getDbConnection();

try {
    // Expand notification type ENUM
    $alterQuery = "ALTER TABLE notifications 
                   MODIFY COLUMN type ENUM('booking', 'service', 'pickup', 'delivery', 'general', 'message', 'assignment', 'leave', 'job_status', 'work_update') 
                   DEFAULT 'general'";
    
    if ($conn->query($alterQuery)) {
        echo "✓ Successfully updated notification type column\n";
    } else {
        throw new Exception("Failed to update notification type: " . $conn->error);
    }
    
    echo "\n✓ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
?>
