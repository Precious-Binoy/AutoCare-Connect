<?php
/**
 * Get Service Status API - Fixed
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$bookingId = intval($_GET['booking_id'] ?? 0);
$userId = getCurrentUserId();

if ($bookingId === 0) {
    sendJsonResponse(['success' => false, 'message' => 'Booking ID is required']);
}

// Get booking details with vehicle and mechanic info
$query = "SELECT 
    b.id, b.booking_number, b.service_type, b.service_category, b.description, b.status,
    b.preferred_date, b.scheduled_date, b.completion_date, b.estimated_cost, b.final_cost,
    b.notes, b.created_at,
    v.make, v.model, v.year, v.color, v.license_plate, v.type, v.mileage,
    u.name as mechanic_name, u.phone as mechanic_phone, u.email as mechanic_email
    FROM bookings b
    INNER JOIN vehicles v ON b.vehicle_id = v.id
    LEFT JOIN mechanics m ON b.mechanic_id = m.id
    LEFT JOIN users u ON m.user_id = u.id
    WHERE b.id = ? AND b.user_id = ?";

$result = executeQuery($query, [$bookingId, $userId], 'ii');

if (!$result || $result->num_rows === 0) {
    sendJsonResponse(['success' => false, 'message' => 'Booking not found']);
}

$booking = $result->fetch_assoc();

// Get service updates timeline
$updatesQuery = "SELECT 
    su.id, su.status, su.message, su.progress_percentage, su.created_at,
    u.name as updated_by_name
    FROM service_updates su
    LEFT JOIN users u ON su.updated_by = u.id
    WHERE su.booking_id = ?
    ORDER BY su.created_at ASC";

$updatesResult = executeQuery($updatesQuery, [$bookingId], 'i');

$timeline = [];
if ($updatesResult) {
    while ($row = $updatesResult->fetch_assoc()) {
        $timeline[] = $row;
    }
}

// Get pickup/delivery info if exists
$deliveryQuery = "SELECT * FROM pickup_delivery WHERE booking_id = ? ORDER BY created_at DESC LIMIT 2";
$deliveryResult = executeQuery($deliveryQuery, [$bookingId], 'i');

$delivery = [];
if ($deliveryResult) {
    while ($row = $deliveryResult->fetch_assoc()) {
        $delivery[] = $row;
    }
}

sendJsonResponse([
    'success' => true,
    'booking' => $booking,
    'timeline' => $timeline,
    'delivery' => $delivery
]);
?>
