<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

$conn = getDbConnection();

function getOrCreateUser($conn, $role, $email, $name) {
    // Check if exists
    $stmt = $conn->prepare("SELECT id, email FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        
        // Ensure role-specific record exists
        if ($role === 'mechanic') {
            $conn->query("INSERT IGNORE INTO mechanics (user_id, status) VALUES (" . $row['id'] . ", 'available')");
        } elseif ($role === 'driver') {
            // Check if driver record exists
            $d_check = $conn->query("SELECT id FROM drivers WHERE user_id = " . $row['id']);
            if ($d_check->num_rows == 0) {
                // Create with unique license/vehicle
                $unique = uniqid();
                $sql = "INSERT INTO drivers (user_id, is_available, license_number, vehicle_number) VALUES (" . $row['id'] . ", 1, 'DL-$unique', 'VH-$unique')";
                if (!$conn->query($sql)) {
                     // Try fallback if strict mode is off or schema differs
                     $conn->query("INSERT INTO drivers (user_id, is_available) VALUES (" . $row['id'] . ", 1)");
                }
            }
        }
        
        return $row;
    } else {
        // Create new
        $hashed = password_hash('password123', PASSWORD_DEFAULT);
        $phone = '555' . rand(1000000, 9999999); // Random phone to avoid duplicates if phone is unique
        
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) { die("Prepare failed: " . $conn->error); }
        $stmt->bind_param("sssss", $name, $email, $hashed, $role, $phone);
        if (!$stmt->execute()) { die("Execute failed: " . $stmt->error); }
        $id = $stmt->insert_id;
        
        if ($role === 'mechanic') {
            $conn->query("INSERT INTO mechanics (user_id, status) VALUES ($id, 'available')");
        } elseif ($role === 'driver') {
            $unique = uniqid();
            $sql = "INSERT INTO drivers (user_id, is_available, license_number, vehicle_number) VALUES ($id, 1, 'DL-$unique', 'VH-$unique')";
            if (!$conn->query($sql)) {
                 $conn->query("INSERT INTO drivers (user_id, is_available) VALUES ($id, 1)");
            }
        }
        
        return ['id' => $id, 'email' => $email];
    }
}

$start_date = date('Y-m-d');

// Ensure we have a pending pickup request for the driver to accept
function ensurePendingPickup($conn, $customerId) {
    // Check for existing 'scheduled' pickup
    $res = $conn->query("SELECT id FROM pickup_delivery WHERE status = 'scheduled' AND (driver_user_id IS NULL OR driver_user_id = 0) LIMIT 1");
    if ($res->num_rows == 0) {
        // Create booking
        $conn->query("INSERT INTO vehicles (user_id, make, model, year, license_plate) VALUES ($customerId, 'TestMake', 'TestModel', 2024, 'TEST-001') ON DUPLICATE KEY UPDATE id=id");
        $vehId = $conn->insert_id ? $conn->insert_id : $conn->query("SELECT id FROM vehicles WHERE license_plate='TEST-001'")->fetch_assoc()['id'];

        $bookingNum = 'BK-' . time();
        $full_addr = "123 Test St, Test City";
        $conn->query("INSERT INTO bookings (user_id, vehicle_id, booking_number, service_type, status, has_pickup_delivery) VALUES ($customerId, $vehId, '$bookingNum', 'Oil Change', 'confirmed', 1)");
        $bookingId = $conn->insert_id;

        $conn->query("INSERT INTO pickup_delivery (booking_id, type, status, address) VALUES ($bookingId, 'pickup', 'scheduled', '$full_addr')");
        echo "Created new pending pickup request.\n";
    } else {
        echo "Existing pending pickup found.\n";
    }
}

// 1. Customer
$customer = getOrCreateUser($conn, 'customer', 'test_cust@example.com', 'Test Customer');
echo "Customer: " . $customer['email'] . " / password123\n";

// 2. Driver
$driver = getOrCreateUser($conn, 'driver', 'test_driver@example.com', 'Test Driver');
echo "Driver: " . $driver['email'] . " / password123\n";

// 3. Mechanic
$mechanic = getOrCreateUser($conn, 'mechanic', 'test_mech@example.com', 'Test Mechanic');
echo "Mechanic: " . $mechanic['email'] . " / password123\n";

// Ensure data
ensurePendingPickup($conn, $customer['id']);

?>
