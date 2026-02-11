<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

$conn = getDbConnection();

echo "Scanning for potential test users...\n";

$query = "SELECT id, name, email, role FROM users WHERE name LIKE '%test%' OR email LIKE '%test%' OR email LIKE '%example.com%' OR name LIKE '%Demo%'";
$res = $conn->query($query);

if ($res && $res->num_rows > 0) {
    echo "Potential Test Users Found:\n";
    while ($row = $res->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | Email: " . $row['email'] . " | Role: " . $row['role'] . "\n";
    }
} else {
    echo "No obvious test users found by basic name/email filtering.\n";
    echo "Listing all users for manual inspection:\n";
    $allRes = $conn->query("SELECT id, name, email, role FROM users LIMIT 20");
    while ($row = $allRes->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | Email: " . $row['email'] . " | Role: " . $row['role'] . "\n";
    }
}
?>
