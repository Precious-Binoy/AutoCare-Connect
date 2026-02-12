<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = getCurrentUser();
$userId = $user['id'];
$role = $user['role'];

// Only mechanics, drivers (to request) and admins (to manage) can access this
if ($role !== 'mechanic' && $role !== 'driver' && $role !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action']) && $data['action'] === 'request') {
        // Handle New Request (Mechanic/Driver)
        if ($role === 'admin') {
            echo json_encode(['success' => false, 'message' => 'Admins cannot request leave']);
            exit;
        }
        
        $type = $data['leave_type'] ?? '';
        $start = $data['start_date'] ?? '';
        $end = $data['end_date'] ?? '';
        $reason = $data['reason'] ?? '';
        
        if (!$type || !$start || !$end || !$reason) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        // Validation for reason length
        if (strlen(trim($reason)) < 3) {
            echo json_encode(['success' => false, 'message' => 'Reason must be at least 3 characters long']);
            exit;
        }

        if (strlen($reason) > 500) {
            echo json_encode(['success' => false, 'message' => 'Reason cannot exceed 500 characters']);
            exit;
        }
        
        $query = "INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason) VALUES (?, ?, ?, ?, ?)";
        if (executeQuery($query, [$userId, $type, $start, $end, $reason], 'issss')) {
            // Send notification to admin
            $userQuery = "SELECT name, role FROM users WHERE id = ?";
            $userResult = executeQuery($userQuery, [$userId], 'i');
            if ($userRow = $userResult->fetch_assoc()) {
                // Get all admin users
                $adminQuery = "SELECT id FROM users WHERE role = 'admin'";
                $adminResult = executeQuery($adminQuery);
                
                $notifTitle = "📋 New Leave Request";
                $notifMessage = "{$userRow['name']} ({$userRow['role']}) has requested {$type} leave from {$start} to {$end}.";
                
                while ($adminRow = $adminResult->fetch_assoc()) {
                    $notifQuery = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'general')";
                    executeQuery($notifQuery, [$adminRow['id'], $notifTitle, $notifMessage], 'iss');
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Leave request submitted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to submit leave request']);
        }
        
    } elseif (isset($data['action']) && $data['action'] === 'admin_update') {
        // Handle Admin Update (Approve/Reject)
        if ($role !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Admin access required']);
            exit;
        }
        
        $leaveId = $data['leave_id'] ?? 0;
        $status = $data['status'] ?? ''; // 'approved' or 'rejected'
        $adminComment = $data['admin_comment'] ?? '';
        
        if (!$leaveId || !in_array($status, ['approved', 'rejected'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }
        
        $query = "UPDATE leave_requests SET status = ?, admin_comment = ? WHERE id = ?";
        if (executeQuery($query, [$status, $adminComment, $leaveId], 'ssi')) {
            // Get leave request details to send notification
            $leaveQuery = "SELECT lr.user_id, lr.leave_type, u.name 
                          FROM leave_requests lr 
                          JOIN users u ON lr.user_id = u.id 
                          WHERE lr.id = ?";
            $leaveResult = executeQuery($leaveQuery, [$leaveId], 'i');
            
            if ($leaveResult && $leaveRow = $leaveResult->fetch_assoc()) {
                $notifTitle = $status === 'approved' ? '✅ Leave Request Approved' : '❌ Leave Request Rejected';
                $notifMessage = $status === 'approved' 
                    ? "Your {$leaveRow['leave_type']} leave request has been approved by the admin."
                    : "Your {$leaveRow['leave_type']} leave request has been rejected. " . ($adminComment ? "Reason: $adminComment" : '');
                
                // Insert notification
                $notifQuery = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'general')";
                executeQuery($notifQuery, [$leaveRow['user_id'], $notifTitle, $notifMessage], 'iss');
            }
            
            echo json_encode(['success' => true, 'message' => 'Leave request updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update leave request']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} elseif ($method === 'GET') {
    // Fetch Requests
    if ($role === 'admin') {
        // Admin: Fetch all pending or recent requests
        $query = "SELECT lr.*, u.name as user_name, u.role as user_role 
                  FROM leave_requests lr 
                  JOIN users u ON lr.user_id = u.id 
                  ORDER BY lr.created_at DESC";
        $result = executeQuery($query);
    } else {
        // Mechanic/Driver: Fetch personal requests
        $query = "SELECT * FROM leave_requests WHERE user_id = ? ORDER BY created_at DESC";
        $result = executeQuery($query, [$userId], 'i');
    }
    
    $requests = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }
    }
    echo json_encode(['success' => true, 'data' => $requests]);
}
?>
