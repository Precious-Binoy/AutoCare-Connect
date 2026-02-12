<?php
require_once 'config/db.php';
$conn = getDbConnection();

// Try to select the columns
$sql = "SELECT profile_image, dob, address FROM users LIMIT 1";
$result = $conn->query($sql);

if ($result) {
    echo "Columns exist.\n";
} else {
    echo "Columns missing or error: " . $conn->error . "\n";
}
?>
