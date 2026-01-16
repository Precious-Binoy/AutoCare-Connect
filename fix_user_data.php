<?php
require_once 'config/db.php';
$conn = getDbConnection();

echo "Checking for users with empty email...\n";
$stmt = $conn->query("SELECT id, name, email FROM users WHERE email = ''");
while ($row = $stmt->fetch_assoc()) {
    echo "ID: {$row['id']}, Name: {$row['name']} has an empty email.\n";
}

echo "Checking for users with empty google_id string...\n";
$stmt = $conn->query("SELECT id, name, email FROM users WHERE google_id = ''");
while ($row = $stmt->fetch_assoc()) {
    echo "ID: {$row['id']}, Name: {$row['name']} has an empty google_id string. Fixing to NULL...\n";
    $conn->query("UPDATE users SET google_id = NULL WHERE id = " . $row['id']);
}

echo "Done.\n";
?>
