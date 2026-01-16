<?php
require_once __DIR__ . '/config/db.php';
$conn = getDbConnection();

$emails = ["'test_cust@example.com'", "'test_driver@example.com'", "'test_mech@example.com'"];
$emailStr = implode(',', $emails);

$sql = "DELETE FROM users WHERE email IN ($emailStr)";
if ($conn->query($sql)) {
    echo "Deleted test users successfully.\n";
} else {
    echo "Error deleting test users: " . $conn->error . "\n";
}
?>
