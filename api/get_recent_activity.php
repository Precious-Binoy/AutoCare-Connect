<?php
/**
 * Get Recent Activity API - Fixed
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$userId = getCurrentUserId();
$limit = intval($_GET['limit'] ?? 10);

// Get recent bookings with vehicle details
$query = "SELECT 
    b.id, b.booking_number, b.service_type, b.service_category, b.status, 
    b.preferred_date, b.scheduled_date, b.completion_date, b.created_at,
    v.make, v.model, v.year, v.color, v.license_plate, v.type
    FROM bookings b
    INNER JOIN vehicles v ON b.vehicle_id = v.id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
    LIMIT ?";

$result = executeQuery($query, [$userId, $limit], 'ii');

$activities = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
}

sendJsonResponse([
    'success' => true,
    'activities' => $activities,
    'count' => count($activities)
]);
?>
