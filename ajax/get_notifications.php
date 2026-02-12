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

// Fetch notifications for the user
$query = "SELECT id, title, message, is_read, created_at 
          FROM notifications 
          WHERE user_id = ? 
          ORDER BY created_at DESC 
          LIMIT 20";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
$unread_count = 0;

while ($row = $result->fetch_assoc()) {
    // Calculate time ago
    $created = strtotime($row['created_at']);
    $now = time();
    $diff = $now - $created;
    
    if ($diff < 60) {
        $time_ago = 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        $time_ago = $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        $time_ago = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } else {
        $days = floor($diff / 86400);
        $time_ago = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
    
    $row['time_ago'] = $time_ago;
    $notifications[] = $row;
    
    if ($row['is_read'] == 0) {
        $unread_count++;
    }
}

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'unread_count' => $unread_count
]);
?>
