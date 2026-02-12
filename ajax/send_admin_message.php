<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/notification_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_POST['admin_message'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$conn = getDbConnection();
$user_id = $_SESSION['user_id'];
$message = sanitizeInput($_POST['admin_message']);

// Get user details
$userQuery = "SELECT name, role FROM users WHERE id = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

$title = "📩 Message from {$user['name']} ({$user['role']})";

// Use notification helper to notify all admins
if (notifyAdmins($title, $message, 'message')) {
    echo json_encode(['success' => true, 'message' => 'Message sent to admin successfully']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to send message']);
}
?>
