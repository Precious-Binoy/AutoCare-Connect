<?php
/**
 * Notification Helper Functions
 * Centralized notification creation to avoid code duplication
 */

require_once(__DIR__ . '/../config/db.php');

/**
 * Create a notification for a specific user
 */
function createNotification($user_id, $title, $message, $type = 'general') {
    $conn = getDbConnection();
    
    $query = "INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
              VALUES (?, ?, ?, ?, FALSE, NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isss", $user_id, $title, $message, $type);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Notify all admin users
 */
function notifyAdmins($title, $message, $type = 'general') {
    $conn = getDbConnection();
    
    // Get all admin user IDs
    $query = "SELECT id FROM users WHERE role = 'admin' AND is_active = TRUE";
    $result = $conn->query($query);
    
    $success = true;
    while ($row = $result->fetch_assoc()) {
        $success = createNotification($row['id'], $title, $message, $type) && $success;
    }
    
    return $success;
}

/**
 * Notify a worker (driver or mechanic)
 */
function notifyWorker($worker_user_id, $title, $message, $type = 'general') {
    return createNotification($worker_user_id, $title, $message, $type);
}

/**
 * Notify customer about booking/service updates
 */
function notifyCustomer($customer_user_id, $title, $message, $type = 'service') {
    return createNotification($customer_user_id, $title, $message, $type);
}

/**
 * Notify all available drivers about new job
 */
function notifyAvailableDrivers($title, $message) {
    $conn = getDbConnection();
    
    // Get all available driver user IDs
    $query = "SELECT d.user_id 
              FROM drivers d 
              JOIN users u ON d.user_id = u.id 
              WHERE d.is_available = TRUE AND u.is_active = TRUE";
    $result = $conn->query($query);
    
    $success = true;
    while ($row = $result->fetch_assoc()) {
        $success = createNotification($row['user_id'], $title, $message, 'job_status') && $success;
    }
    
    return $success;
}

/**
 * Notify all available mechanics about new job
 */
function notifyAvailableMechanics($title, $message) {
    $conn = getDbConnection();
    
    // Get all available mechanic user IDs
    $query = "SELECT m.user_id 
              FROM mechanics m 
              JOIN users u ON m.user_id = u.id 
              WHERE m.is_available = TRUE AND u.is_active = TRUE";
    $result = $conn->query($query);
    
    $success = true;
    while ($row = $result->fetch_assoc()) {
        $success = createNotification($row['user_id'], $title, $message, 'job_status') && $success;
    }
    
    return $success;
}
?>
