<?php
require_once __DIR__ . '/config/db.php';
$conn = getDbConnection();

try {
    // 1. Describe table
    echo "--- Table Structure ---\n";
    $res = $conn->query("DESCRIBE drivers");
    if (!$res) throw new Exception($conn->error);
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . "\n";
    }

    // 2. Try simple insert on a dummy ID (assuming 99999 doesn't exist, this is just to check constraint, won't actually work due to FK likely)
    // Actually, let's just create a dummy user first to be safe
    $conn->query("INSERT INTO users (name, email) VALUES ('TestTmp', 'tmp@test.com')");
    $uid = $conn->insert_id;
    
    echo "\n--- Attempting Insert ---\n";
    if (!$conn->query("INSERT INTO drivers (user_id, is_available) VALUES ($uid, 1)")) {
        echo "Insert 1 failed: " . $conn->error . "\n";
        
        if (!$conn->query("INSERT INTO drivers (user_id, is_available, vehicle_number, license_number) VALUES ($uid, 1, 'V1', 'L1')")) {
            echo "Insert 2 failed: " . $conn->error . "\n";
        } else {
            echo "Insert 2 success!\n";
        }
    } else {
        echo "Insert 1 success!\n";
    }
    
    // Cleanup
    $conn->query("DELETE FROM users WHERE id = $uid");

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}
?>
