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

$driver_id = isset($_GET['driver_id']) ? intval($_GET['driver_id']) : 0;

if (!$driver_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid driver ID']);
    exit;
}

$conn = getDbConnection();

// Fetch driver details with user information
$driverQuery = "SELECT d.*, u.name, u.email, u.phone, u.dob, u.address, u.profile_image, u.created_at as joined_date
                FROM drivers d
                JOIN users u ON d.user_id = u.id
                WHERE d.id = ?";
$stmt = $conn->prepare($driverQuery);
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$driverResult = $stmt->get_result();
$driver = $driverResult->fetch_assoc();

if (!$driver) {
    echo json_encode(['success' => false, 'error' => 'Driver not found']);
    exit;
}

// Fetch job history
$jobQuery = "SELECT pd.*, b.booking_number, v.make, v.model, u.name as customer_name
             FROM pickup_delivery pd
             JOIN bookings b ON pd.booking_id = b.id
             JOIN vehicles v ON b.vehicle_id = v.id
             JOIN users u ON b.user_id = u.id
             WHERE pd.driver_user_id = ?
             ORDER BY pd.updated_at DESC
             LIMIT 50";
$stmt = $conn->prepare($jobQuery);
$stmt->bind_param("i", $driver['user_id']);
$stmt->execute();
$jobResult = $stmt->get_result();
$jobHistory = [];
while ($row = $jobResult->fetch_assoc()) {
    $jobHistory[] = $row;
}

// Fetch leave requests
$leaveQuery = "SELECT * FROM leave_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 20";
$stmt = $conn->prepare($leaveQuery);
$stmt->bind_param("i", $driver['user_id']);
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
    if ($job['status'] === 'completed') {
        $stats['completed_jobs']++;
        $stats['total_earnings'] += floatval($job['fee']);
    }
}

echo json_encode([
    'success' => true,
    'driver' => $driver,
    'job_history' => $jobHistory,
    'leave_requests' => $leaveRequests,
    'stats' => $stats
]);
?>
