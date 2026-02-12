<?php
session_start();
require_once('../config/db.php');
require_once('../includes/auth.php');

header('Content-Type: application/json');

// Require admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$mechanic_id = isset($_GET['mechanic_id']) ? intval($_GET['mechanic_id']) : 0;

if (!$mechanic_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid mechanic ID']);
    exit;
}

$conn = getDbConnection();

// Fetch mechanic details with user information
$mechanicQuery = "SELECT m.*, u.name, u.email, u.phone, u.dob, u.address, u.profile_image, u.created_at as joined_date
                  FROM mechanics m
                  JOIN users u ON m.user_id = u.id
                  WHERE m.id = ?";
$stmt = $conn->prepare($mechanicQuery);
$stmt->bind_param("i", $mechanic_id);
$stmt->execute();
$mechanicResult = $stmt->get_result();
$mechanic = $mechanicResult->fetch_assoc();

if (!$mechanic) {
    echo json_encode(['success' => false, 'error' => 'Mechanic not found']);
    exit;
}

// Fetch job history
$jobQuery = "SELECT b.*, v.make, v.model, v.license_plate, u.name as customer_name
             FROM bookings b
             JOIN vehicles v ON b.vehicle_id = v.id
             JOIN users u ON b.user_id = u.id
             WHERE b.mechanic_id = ?
             ORDER BY b.created_at DESC
             LIMIT 50";
$stmt = $conn->prepare($jobQuery);
$stmt->bind_param("i", $mechanic_id);
$stmt->execute();
$jobResult = $stmt->get_result();
$jobHistory = [];
while ($row = $jobResult->fetch_assoc()) {
    $jobHistory[] = $row;
}

// Fetch leave requests
$leaveQuery = "SELECT * FROM leave_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 20";
$stmt = $conn->prepare($leaveQuery);
$stmt->bind_param("i", $mechanic['user_id']);
$stmt->execute();
$leaveResult = $stmt->get_result();
$leaveRequests = [];
while ($row = $leaveResult->fetch_assoc()) {
    $leaveRequests[] = $row;
}

// Calculate statistics
$stats = [
    'total_jobs' => count($jobHistory),
    'completed_jobs' => 0,
    'total_earnings' => 0
];

foreach ($jobHistory as $job) {
    if ($job['status'] === 'completed' || $job['status'] === 'delivered') {
        $stats['completed_jobs']++;
        $stats['total_earnings'] += floatval($job['mechanic_fee']);
    }
}

echo json_encode([
    'success' => true,
    'mechanic' => $mechanic,
    'job_history' => $jobHistory,
    'leave_requests' => $leaveRequests,
    'stats' => $stats
]);
?>
