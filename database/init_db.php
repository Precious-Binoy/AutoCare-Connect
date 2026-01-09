<?php
/**
 * Database Initialization Script
 * Run this file once to create the database and populate sample data
 */

// Connection without database selection
$conn = new mysqli('localhost', 'root', '');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully to MySQL server.\n\n";

// Read schema file
$schema = file_get_contents(__DIR__ . '/schema.sql');

// Execute the entire schema as multi-query (works better with MySQL)
if ($conn->multi_query($schema)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
        if ($conn->more_results()) {
            $conn->next_result();
        }
    } while ($conn->more_results());
}

if ($conn->error) {
    die("Error creating schema: " . $conn->error . "\n");
}

echo "Database schema created successfully!\n\n";

// Select the database
$conn->select_db('autocare_connect');

// Insert sample data
echo "Inserting sample data...\n\n";

// Sample users
$users = [
    ['John Doe', 'john@example.com', '+1 (555) 012-3456', password_hash('password123', PASSWORD_DEFAULT), 'customer', null],
    ['Jane Smith', 'jane@example.com', '+1 (555) 123-4567', password_hash('password123', PASSWORD_DEFAULT), 'customer', null],
    ['Admin User', 'admin@autocare.com', '+1 (555) 999-0000', password_hash('admin123', PASSWORD_DEFAULT), 'admin', null],
    ['Mike Mechanic', 'mike@autocare.com', '+1 (555) 555-1234', password_hash('mechanic123', PASSWORD_DEFAULT), 'mechanic', null],
];

$stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, google_id) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($users as $user) {
    $stmt->bind_param('ssssss', $user[0], $user[1], $user[2], $user[3], $user[4], $user[5]);
    $stmt->execute();
}
$stmt->close();
echo "✓ Sample users created\n";

// Get user IDs
$result = $conn->query("SELECT id FROM users WHERE email = 'john@example.com'");
$john_id = $result->fetch_assoc()['id'];

$result = $conn->query("SELECT id FROM users WHERE email = 'jane@example.com'");
$jane_id = $result->fetch_assoc()['id'];

$result = $conn->query("SELECT id FROM users WHERE email = 'mike@autocare.com'");
$mike_user_id = $result->fetch_assoc()['id'];

// Sample mechanic
$stmt = $conn->prepare("INSERT INTO mechanics (user_id, specialization, certification, years_experience) VALUES (?, ?, ?, ?)");
$specialization = 'General Repair & Diagnostics';
$certification = 'ASE Certified';
$experience = 8;
$stmt->bind_param('issi', $mike_user_id, $specialization, $certification, $experience);
$stmt->execute();
$mechanic_id = $conn->insert_id;
$stmt->close();
echo "✓ Sample mechanic created\n";

// Sample vehicles
$vehicles = [
    [$john_id, 'Toyota', 'Camry', 2019, 'Silver', 'ABC-1234', '1HGBH41JXMN109186', 'sedan', 45000],
    [$john_id, 'Ford', 'Ranger', 2021, 'Blue', 'XYZ-9876', '1FTYR14D17PA12345', 'pickup', 20000],
    [$jane_id, 'Honda', 'Civic', 2018, 'White', 'DEF-5678', '2HGFC2F59HH123456', 'sedan', 52000],
    [$jane_id, 'Tesla', 'Model 3', 2020, 'Red', 'EV-9988', '5YJ3E1EA1LF654321', 'sedan', 15000],
];

$stmt = $conn->prepare("INSERT INTO vehicles (user_id, make, model, year, color, license_plate, vin, type, mileage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($vehicles as $vehicle) {
    $stmt->bind_param('issississi', $vehicle[0], $vehicle[1], $vehicle[2], $vehicle[3], $vehicle[4], $vehicle[5], $vehicle[6], $vehicle[7], $vehicle[8]);
    $stmt->execute();
}
$stmt->close();
echo "✓ Sample vehicles created\n";

// Get vehicle IDs
$result = $conn->query("SELECT id FROM vehicles WHERE license_plate = 'ABC-1234'");
$toyota_id = $result->fetch_assoc()['id'];

$result = $conn->query("SELECT id FROM vehicles WHERE license_plate = 'DEF-5678'");
$honda_id = $result->fetch_assoc()['id'];

// Sample bookings
$bookings = [
    ['BK-' . rand(10000, 99999), $john_id, $toyota_id, $mechanic_id, 'Oil Change', 'maintenance', 'Regular synthetic oil change', '2024-10-25 10:00:00', '2024-10-25 10:00:00', null, 'in_progress', 'normal', 89.99, null],
    ['BK-' . rand(10000, 99999), $jane_id, $honda_id, $mechanic_id, 'Brake Pad Replacement', 'repair', 'Replace front brake pads', '2024-08-15 14:00:00', '2024-08-15 14:00:00', '2024-08-15 16:30:00', 'completed', 'normal', 250.00, 245.00],
    ['BK-' . rand(10000, 99999), $john_id, $toyota_id, null, 'Full Inspection', 'inspection', 'Complete vehicle inspection before road trip', '2024-11-10 09:00:00', null, null, 'pending', 'normal', 150.00, null],
];

$stmt = $conn->prepare("INSERT INTO bookings (booking_number, user_id, vehicle_id, mechanic_id, service_type, service_category, description, preferred_date, scheduled_date, completion_date, status, priority, estimated_cost, final_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($bookings as $booking) {
    $stmt->bind_param('siiissssssssdd', $booking[0], $booking[1], $booking[2], $booking[3], $booking[4], $booking[5], $booking[6], $booking[7], $booking[8], $booking[9], $booking[10], $booking[11], $booking[12], $booking[13]);
    $stmt->execute();
}
$stmt->close();
echo "✓ Sample bookings created\n";

// Get booking ID for service updates
$result = $conn->query("SELECT id FROM bookings WHERE status = 'in_progress' LIMIT 1");
$in_progress_booking = $result->fetch_assoc()['id'];

// Sample service updates
$updates = [
    [$in_progress_booking, 'Booking Confirmed', 'Your service appointment has been confirmed', 0, $mike_user_id],
    [$in_progress_booking, 'Vehicle Checked In', 'Vehicle has been checked in and initial inspection completed', 25, $mike_user_id],
    [$in_progress_booking, 'Service in Progress', 'Oil change in progress', 50, $mike_user_id],
];

$stmt = $conn->prepare("INSERT INTO service_updates (booking_id, status, message, progress_percentage, updated_by) VALUES (?, ?, ?, ?, ?)");
foreach ($updates as $update) {
    $stmt->bind_param('issii', $update[0], $update[1], $update[2], $update[3], $update[4]);
    $stmt->execute();
}
$stmt->close();
echo "✓ Sample service updates created\n";

echo "\n========================================\n";
echo "Database initialization complete!\n";
echo "========================================\n\n";
echo "Sample Login Credentials:\n";
echo "-------------------------\n";
echo "Customer: john@example.com / password123\n";
echo "Customer: jane@example.com / password123\n";
echo "Mechanic: mike@autocare.com / mechanic123\n";
echo "Admin: admin@autocare.com / admin123\n";
echo "\n";

$conn->close();
?>
