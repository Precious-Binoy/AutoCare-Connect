<?php
require_once 'config/db.php';
$conn = getDbConnection();

// 1. Add user_id to drivers if missing
$res = $conn->query("SHOW COLUMNS FROM drivers LIKE 'user_id'");
if ($res->num_rows == 0) {
    echo "Adding user_id to drivers table...\n";
    $conn->query("ALTER TABLE drivers ADD COLUMN user_id INT(11) AFTER id, ADD INDEX(user_id)");
}

// 2. Add vehicle_info to drivers for consistency (User requested it via error message reference)
$res = $conn->query("SHOW COLUMNS FROM drivers LIKE 'vehicle_info'");
if ($res->num_rows == 0) {
    echo "Adding vehicle_info to drivers table...\n";
    $conn->query("ALTER TABLE drivers ADD COLUMN vehicle_info TEXT AFTER vehicle_number");
}

// 3. Try to link existing drivers to users by email
$res = $conn->query("SELECT id, email FROM drivers WHERE user_id IS NULL OR user_id = 0");
while ($row = $res->fetch_assoc()) {
    $email = $row['email'];
    $uRes = $conn->query("SELECT id FROM users WHERE email = '$email'");
    if ($uRes && $uRow = $uRes->fetch_assoc()) {
        $uid = $uRow['id'];
        $did = $row['id'];
        $conn->query("UPDATE drivers SET user_id = $uid WHERE id = $did");
        echo "Linked driver $email to user ID $uid\n";
    }
}

echo "Migration complete.\n";
