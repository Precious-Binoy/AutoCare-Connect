<?php
/**
 * Add Vehicle API
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

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
$make = trim($data['make'] ?? '');
$model = trim($data['model'] ?? '');
$year = intval($data['year'] ?? 0);
$color = trim($data['color'] ?? '');
$licensePlate = trim($data['license_plate'] ?? '');
$vin = trim($data['vin'] ?? '');
$type = trim($data['type'] ?? 'sedan');
$mileage = intval($data['mileage'] ?? 0);

// Validation
if (empty($make) || empty($model) || empty($licensePlate)) {
    sendJsonResponse(['success' => false, 'message' => 'Make, model, and license plate are required']);
}

if ($year < 1900 || $year > date('Y') + 1) {
    sendJsonResponse(['success' => false, 'message' => 'Invalid year']);
}

// Check if license plate already exists
$checkQuery = "SELECT id FROM vehicles WHERE license_plate = ? AND user_id != ?";
$result = executeQuery($checkQuery, [$licensePlate, $userId], 'si');
if ($result && $result->num_rows > 0) {
    sendJsonResponse(['success' => false, 'message' => 'License plate already registered']);
}

// Insert vehicle
$insertQuery = "INSERT INTO vehicles (user_id, make, model, year, color, license_plate, vin, type, mileage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
$insertResult = executeQuery($insertQuery, [$userId, $make, $model, $year, $color, $licensePlate, $vin, $type, $mileage], 'issississi');

if ($insertResult) {
    $vehicleId = getLastInsertId();
    sendJsonResponse([
        'success' => true,
        'message' => 'Vehicle added successfully',
        'vehicle_id' => $vehicleId
    ]);
} else {
    sendJsonResponse(['success' => false, 'message' => 'Failed to add vehicle']);
}
?>
