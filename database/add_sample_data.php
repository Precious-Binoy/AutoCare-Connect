<?php
/**
 * Add Sample Data to Database
 */

$conn = new mysqli('localhost', 'root', '');

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]));
}

$conn->select_db('autocare_connect');

// Sample users with hashed passwords
$users = [
    ['John Doe', 'john@example.com', '+1 (555) 012-3456', password_hash('password123', PASSWORD_DEFAULT), 'customer', null],
    ['Jane Smith', 'jane@example.com', '+1 (555) 123-4567', password_hash('password123', PASSWORD_DEFAULT), 'customer', null],
    ['Admin User', 'admin@autocare.com', '+1 (555) 999-0000', password_hash('admin123', PASSWORD_DEFAULT), 'admin', null],
    ['Mike Mechanic', 'mike@autocare.com', '+1 (555) 555-1234', password_hash('mechanic123', PASSWORD_DEFAULT), 'mechanic', null],
];

// Check if users already exist
$checkUser = $conn->query("SELECT COUNT(*) as count FROM users");
if ($checkUser && $checkUser->fetch_assoc()['count'] == 0) {
    $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, google_id) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($users as $user) {
        $stmt->bind_param('ssssss', $user[0], $user[1], $user[2], $user[3], $user[4], $user[5]);
        $stmt->execute();
    }
    $stmt->close();

    // Get user IDs
    $john = $conn->query("SELECT id FROM users WHERE email = 'john@example.com'")->fetch_assoc()['id'];
    $jane = $conn->query("SELECT id FROM users WHERE email = 'jane@example.com'")->fetch_assoc()['id'];
    $mike_user = $conn->query("SELECT id FROM users WHERE email = 'mike@autocare.com'")->fetch_assoc()['id'];

    // Add mechanic
    $conn->query("INSERT INTO mechanics (user_id, specialization, certification, years_experience) 
                  VALUES ($mike_user, 'General Repair & Diagnostics', 'ASE Certified', 8)");
    $mechanic_id = $conn->insert_id;

    // Add vehicles
    $vehicles = [
        [$john, 'Toyota', 'Camry', 2019, 'Silver', 'ABC-1234', '1HGBH41JXMN109186', 'sedan', 45000],
        [$john, 'Ford', 'Ranger', 2021, 'Blue', 'XYZ-9876', '1FTYR14D17PA12345', 'pickup', 20000],
        [$jane, 'Honda', 'Civic', 2018, 'White', 'DEF-5678', '2HGFC2F59HH123456', 'sedan', 52000],
    ];

    $stmt = $conn->prepare("INSERT INTO vehicles (user_id, make, model, year, color, license_plate, vin, type, mileage) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($vehicles as $v) {
        $stmt->bind_param('issississi', $v[0], $v[1], $v[2], $v[3], $v[4], $v[5], $v[6], $v[7], $v[8]);
        $stmt->execute();
    }
    $stmt->close();

    // Get vehicle IDs
    $toyota = $conn->query("SELECT id FROM vehicles WHERE license_plate = 'ABC-1234'")->fetch_assoc()['id'];

    // Add bookings
    $bookings = [
        ['BK-' . strtoupper(substr(md5(uniqid()), 0, 8)), $john, $toyota, $mechanic_id, 'Oil Change', 'maintenance', 
         'Regular synthetic oil change', '2024-10-25 10:00:00', '2024-10-25 10:00:00', null, 'in_progress', 'normal', 89.99, null],
    ];

    $stmt = $conn->prepare("INSERT INTO bookings 
        (booking_number, user_id, vehicle_id, mechanic_id, service_type, service_category, description, preferred_date, scheduled_date, completion_date, status, priority, estimated_cost, final_cost) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($bookings as $b) {
        $stmt->bind_param('siiissssssssdd', $b[0], $b[1], $b[2], $b[3], $b[4], $b[5], $b[6], $b[7], $b[8], $b[9], $b[10], $b[11], $b[12], $b[13]);
        $stmt->execute();
    }
    $booking_id = $conn->insert_id;
    $stmt->close();

    // Add service updates
    $conn->query("INSERT INTO service_updates (booking_id, status, message, progress_percentage) 
                  VALUES ($booking_id, 'Booking Created', 'Your service booking has been created', 0)");
    $conn->query("INSERT INTO service_updates (booking_id, status, message, progress_percentage) 
                  VALUES ($booking_id, 'Vehicle Checked In', 'Vehicle checked in and inspection started', 25)");
}

echo json_encode(['success' => true, 'message' => 'Sample data added successfully!']);
$conn->close();
?>
