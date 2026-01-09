<?php
/**
 * Create Booking API
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

$userId = getCurrentUserId();
$vehicleId = intval($data['vehicle_id'] ?? 0);
$serviceType = trim($data['service_type'] ?? '');
$preferredDate = trim($data['preferred_date'] ?? '');
$mechanicId = !empty($data['mechanic_id']) ? intval($data['mechanic_id']) : null;
$notes = trim($data['notes'] ?? '');
$serviceCategory = trim($data['service_category'] ?? 'maintenance');

// Validation
$errors = [];
if ($vehicleId === 0) $errors[] = 'Vehicle is required';
if ($serviceType === '') $errors[] = 'Service type is required';
if ($preferredDate === '') $errors[] = 'Preferred date/time is required';

if (!empty($errors)) {
    sendJsonResponse(['success' => false, 'message' => implode('; ', $errors)]);
}

// Verify vehicle belongs to user
$checkQuery = "SELECT id FROM vehicles WHERE id = ? AND user_id = ?";
$checkResult = executeQuery($checkQuery, [$vehicleId, $userId], 'ii');
if (!$checkResult || $checkResult->num_rows === 0) {
    sendJsonResponse(['success' => false, 'message' => 'Vehicle not found or does not belong to you']);
}

// Generate booking number
$bookingNumber = 'BK-' . strtoupper(substr(md5(uniqid()), 0, 8));

// Insert booking
$insertQuery = "INSERT INTO bookings 
    (booking_number, user_id, vehicle_id, mechanic_id, service_type, service_category, description, preferred_date, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
$insertResult = executeQuery($insertQuery, 
    [$bookingNumber, $userId, $vehicleId, $mechanicId, $serviceType, $serviceCategory, $notes, $preferredDate], 
    'siiissss');

if ($insertResult) {
    $bookingId = getLastInsertId();
    
    // Add initial service update
    $updateQuery = "INSERT INTO service_updates (booking_id, status, message, progress_percentage) 
                    VALUES (?, 'Booking Created', 'Your service booking has been created and is pending confirmation', 0)";
    executeQuery($updateQuery, [$bookingId], 'i');
    
    sendJsonResponse([
        'success' => true,
        'booking_id' => $bookingId,
        'booking_number' => $bookingNumber,
        'message' => 'Booking created successfully'
    ]);
} else {
    sendJsonResponse(['success' => false, 'message' => 'Failed to create booking']);
}
?>
