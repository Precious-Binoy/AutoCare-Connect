<?php
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$sender_id = $_SESSION['user_id'];
$conn = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
    $subject = sanitizeInput($_POST['subject'] ?? 'New Message');
    $message = sanitizeInput($_POST['message']);
    
    // If receiver_id is provided directly
    if ($receiver_id > 0) {
        $recipients = [$receiver_id];
    } 
    // If sending to a group (e.g., 'all_drivers', 'all_mechanics') - mainly for Admin
    elseif (isset($_POST['group'])) {
        $group = $_POST['group'];
        $recipients = [];
        
        if ($group === 'drivers') {
            $query = "SELECT user_id FROM drivers";
        } elseif ($group === 'mechanics') {
            $query = "SELECT user_id FROM mechanics";
        } elseif ($group === 'all') {
            // Select all drivers AND all mechanics
            $query = "SELECT user_id FROM drivers UNION SELECT user_id FROM mechanics";
        } elseif ($group === 'admins') {
            $query = "SELECT id as user_id FROM users WHERE role = 'admin'";
        }
        
        if (isset($query)) {
            $result = $conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $recipients[] = $row['user_id'];
            }
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No recipient specified']);
        exit;
    }

    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
        exit;
    }

    $successCount = 0;
    $conn->begin_transaction();

    try {
        foreach ($recipients as $recipient_id) {
            if ($recipient_id == $sender_id) continue; // Don't match self

            // 1. Insert into messages table
            $msgQuery = "INSERT INTO messages (sender_id, receiver_id, subject, message) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($msgQuery);
            $stmt->bind_param("iiss", $sender_id, $recipient_id, $subject, $message);
            $stmt->execute();
            
            // 2. Insert into notifications table
            $notifTitle = "New Message from " . $_SESSION['user_name'];
            $notifQuery = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'message')";
            $stmt = $conn->prepare($notifQuery);
            $stmt->bind_param("iss", $recipient_id, $notifTitle, $message);
            $stmt->execute();

            $successCount++;
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'count' => $successCount]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
