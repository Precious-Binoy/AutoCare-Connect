<?php
/**
 * Get Dashboard Statistics API
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$userId = getCurrentUserId();

// Get total vehicles
$vehiclesQuery = "SELECT COUNT(*) as total FROM vehicles WHERE user_id = ?";
$vehiclesResult = executeQuery($vehiclesQuery, [$userId], 'i');
$totalVehicles = $vehiclesResult ? $vehiclesResult->fetch_assoc()['total'] : 0;

// Get active services (in_progress or confirmed)
$activeQuery = "SELECT COUNT(*) as total FROM bookings WHERE user_id = ? AND status IN ('in_progress', 'confirmed')";
$activeResult = executeQuery($activeQuery, [$userId], 'i');
$activeServices = $activeResult ? $activeResult->fetch_assoc()['total'] : 0;

// Get pickup requests
$pickupQuery = "SELECT COUNT(DISTINCT pd.booking_id) as total 
                FROM pickup_delivery pd
                INNER JOIN bookings b ON pd.booking_id = b.id
                WHERE b.user_id = ? AND pd.status IN ('pending', 'scheduled')";
$pickupResult = executeQuery($pickupQuery, [$userId], 'i');
$pickupRequests = $pickupResult ? $pickupResult->fetch_assoc()['total'] : 0;

// Get completed services
$completedQuery = "SELECT COUNT(*) as total FROM bookings WHERE user_id = ? AND status = 'completed'";
$completedResult = executeQuery($completedQuery, [$userId], 'i');
$completedServices = $completedResult ? $completedResult->fetch_assoc()['total'] : 0;

sendJsonResponse([
    'success' => true,
    'statistics' => [
        'total_vehicles' => $totalVehicles,
        'active_services' => $activeServices,
        'pickup_requests' => $pickupRequests,
        'completed_services' => $completedServices
    ]
]);
?>
