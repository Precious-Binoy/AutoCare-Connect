<?php
/**
 * Get Vehicles API
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$userId = getCurrentUserId();

// Get all vehicles for the user
$query = "SELECT id, make, model, year, color, license_plate, vin, type, mileage, created_at 
          FROM vehicles 
          WHERE user_id = ? 
          ORDER BY created_at DESC";
$result = executeQuery($query, [$userId], 'i');

$vehicles = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $vehicles[] = $row;
    }
}

sendJsonResponse([
    'success' => true,
    'vehicles' => $vehicles,
    'count' => count($vehicles)
]);
?>
