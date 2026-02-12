<?php
session_start();
require_once('../config/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$conn = getDbConnection();
$user_id = $_SESSION['user_id'];

// Mark all notifications as read
$query = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to mark notifications as read']);
}
?>
