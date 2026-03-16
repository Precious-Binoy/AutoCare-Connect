<?php
/**
 * Notification Helper Functions
 * Centralized notification creation to avoid code duplication
 */

require_once(__DIR__ . '/../config/db.php');

/**
 * Core function to create a notification
 */
function createNotification($user_id, $title, $message, $type = 'general', $link_url = null) {
    $conn = getDbConnection();
    $query = "INSERT INTO notifications (user_id, title, message, type, link_url, is_read, created_at) 
              VALUES (?, ?, ?, ?, ?, FALSE, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issss", $user_id, $title, $message, $type, $link_url);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Send notification to a specific user
 */
function notifyUser($user_id, $title, $message, $type = 'general', $link_url = null) {
    return createNotification($user_id, $title, $message, $type, $link_url);
}

/**
 * Send notification to all active admin users
 */
function notifyAdmins($title, $message, $type = 'general', $link_url = null) {
    $conn = getDbConnection();
    $query = "SELECT id FROM users WHERE role = 'admin' AND is_active = TRUE";
    $result = $conn->query($query);
    $success = true;
    while ($row = $result->fetch_assoc()) {
        $success = createNotification($row['id'], $title, $message, $type, $link_url) && $success;
    }
    return $success;
}

/**
 * Send notification to a specific worker (mechanic or driver)
 */
function notifyWorker($worker_user_id, $title, $message, $type = 'general', $link_url = null) {
    return createNotification($worker_user_id, $title, $message, $type, $link_url);
}

/**
 * Notify customer about booking/service updates
 */
function notifyCustomer($customer_user_id, $title, $message, $type = 'service', $link_url = null) {
    return createNotification($customer_user_id, $title, $message, $type, $link_url);
}

/**
 * Notify all available drivers about new job
 */
function notifyAvailableDrivers($title, $message, $link_url = null) {
    $conn = getDbConnection();
    $query = "SELECT d.user_id 
              FROM drivers d 
              JOIN users u ON d.user_id = u.id 
              WHERE u.is_active = TRUE";
    $result = $conn->query($query);
    $success = true;
    while ($row = $result->fetch_assoc()) {
        $success = createNotification($row['user_id'], $title, $message, 'assignment', $link_url) && $success;
    }
    return $success;
}

/**
 * Notify all available mechanics about new job
 */
function notifyAvailableMechanics($title, $message, $link_url = null) {
    $conn = getDbConnection();
    $query = "SELECT m.user_id 
              FROM mechanics m 
              JOIN users u ON m.user_id = u.id 
              WHERE u.is_active = TRUE";
    $result = $conn->query($query);
    $success = true;
    while ($row = $result->fetch_assoc()) {
        $success = createNotification($row['user_id'], $title, $message, 'assignment', $link_url) && $success;
    }
    return $success;
}
?>
