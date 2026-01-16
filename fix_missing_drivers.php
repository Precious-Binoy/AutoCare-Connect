<?php
require_once __DIR__ . '/config/db.php';
$conn = getDbConnection();

echo "Checking for drivers with missing records...\n";

// Find users with role 'driver' who don't have a record in 'drivers' table
$sql = "SELECT u.id, u.name, u.email 
        FROM users u 
        LEFT JOIN drivers d ON u.id = d.user_id 
        WHERE u.role = 'driver' AND d.id IS NULL";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "Found " . $result->num_rows . " drivers with missing records:\n";
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['name'] . " (" . $row['email'] . ")\n";
        
        // Fix it
        $uniqueId = time() . rand(100, 999);
        $tempLicense = 'PEND-L-' . $uniqueId;
        $tempVehicle = 'PEND-V-' . $uniqueId;
        
        $insert = "INSERT INTO drivers (user_id, is_available, license_number, vehicle_number) VALUES (?, 1, ?, ?)";
        $stmt = $conn->prepare($insert);
        $stmt->bind_param("iss", $row['id'], $tempLicense, $tempVehicle);
        
        if ($stmt->execute()) {
            echo "  > Fixed! Created record for ID " . $row['id'] . "\n";
        } else {
            echo "  > Failed to fix ID " . $row['id'] . ": " . $stmt->error . "\n";
        }
    }
} else {
    echo "All drivers have valid records. No fix needed.\n";
}
?>
