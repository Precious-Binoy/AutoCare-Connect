<?php
// api/update_pickup_delivery.php — mock pickup/delivery update endpoint
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

$pickup_datetime = trim($data['pickup_datetime'] ?? '');
$pickup_address = trim($data['pickup_address'] ?? '');

$errors = [];
if($pickup_datetime === '') $errors[] = 'Pickup date/time is required';
if($pickup_address === '') $errors[] = 'Pickup address is required';

if(!empty($errors)){
    echo json_encode(['success' => false, 'message' => implode('; ', $errors)]);
    exit;
}

// Simulate a conflict if address contains "unavailable"
if(stripos($pickup_address, 'unavailable') !== false){
    echo json_encode(['success' => false, 'message' => 'Address is currently unavailable for pickup service']);
    exit;
}

// Simulate successful update
echo json_encode([
    'success' => true,
    'message' => 'Pickup/Delivery request updated',
    'data' => [
        'pickup_datetime' => $pickup_datetime,
        'pickup_address' => $pickup_address,
        'updated_at' => date('Y-m-d H:i:s')
    ]
]);

?>
