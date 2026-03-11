<?php
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = getCurrentUserId();
$conn = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($message_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid message ID']);
        exit;
    }

    // Update is_read only if the user is the receiver
    $query = "UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $message_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
