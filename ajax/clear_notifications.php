<?php
require_once('../includes/auth.php');

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$conn = getDbConnection();
$user_id = getCurrentUserId();

// Delete all notifications for the user
$query = "DELETE FROM notifications WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to clear notifications']);
}
?>
