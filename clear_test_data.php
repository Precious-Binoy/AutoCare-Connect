<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

$conn = getDbConnection();

echo "Starting system-wide test data cleanup (V2)...\n";

// 1. Identify test users
$findUsersQuery = "SELECT id, name, email FROM users 
                   WHERE name LIKE '%test%' 
                   OR email LIKE '%test%' 
                   OR email LIKE '%example.com%' 
                   OR name LIKE '%Demo%'";
$userRes = $conn->query($findUsersQuery);

$testUserIds = [];
$testEmails = [];
while ($row = $userRes->fetch_assoc()) {
    $testUserIds[] = $row['id'];
    $testEmails[] = $row['email'];
    echo "Found test user: " . $row['name'] . " (" . $row['email'] . ") [ID: " . $row['id'] . "]\n";
}

if (empty($testUserIds)) {
    echo "No test users found. Checking for orphaned job requests/resets...\n";
}

// 2. Cleanup job_requests and password_resets by email pattern if not found by user
$emailConditions = "email LIKE '%test%' OR email LIKE '%example.com%'";
echo "Cleaning up job_requests and password_resets...\n";
$conn->query("DELETE FROM job_requests WHERE $emailConditions");
$conn->query("DELETE FROM password_resets WHERE $emailConditions");

if (!empty($testUserIds)) {
    $userIdsList = implode(',', $testUserIds);

    // 3. Find associated bookings
    $findBookingsQuery = "SELECT id FROM bookings WHERE user_id IN ($userIdsList)";
    $bookingRes = $conn->query($findBookingsQuery);
    $testBookingIds = [];
    while ($row = $bookingRes->fetch_assoc()) {
        $testBookingIds[] = $row['id'];
    }

    echo "Found " . count($testBookingIds) . " associated bookings.\n";

    if (!empty($testBookingIds)) {
        $bookingIdsList = implode(',', $testBookingIds);

        // 4. Delete dependent records
        echo "Deleting service updates...\n";
        $conn->query("DELETE FROM service_updates WHERE booking_id IN ($bookingIdsList)");

        echo "Deleting pickup/delivery records...\n";
        $conn->query("DELETE FROM pickup_delivery WHERE booking_id IN ($bookingIdsList)");

        echo "Deleting parts used...\n";
        $conn->query("DELETE FROM parts_used WHERE booking_id IN ($bookingIdsList)");

        // 5. Delete bookings
        echo "Deleting bookings...\n";
        $conn->query("DELETE FROM bookings WHERE id IN ($bookingIdsList)");
    }

    // 6. Delete vehicles
    echo "Deleting vehicles...\n";
    $conn->query("DELETE FROM vehicles WHERE user_id IN ($userIdsList)");

    // 7. Delete roles and notifications
    echo "Deleting notifications...\n";
    $conn->query("DELETE FROM notifications WHERE user_id IN ($userIdsList)");
    
    echo "Deleting test users from role tables...\n";
    $conn->query("DELETE FROM mechanics WHERE user_id IN ($userIdsList)");
    $conn->query("DELETE FROM drivers WHERE user_id IN ($userIdsList)");
    
    echo "Deleting test users...\n";
    $conn->query("DELETE FROM users WHERE id IN ($userIdsList)");
}

echo "\nCleanup successful!\n";
?>
