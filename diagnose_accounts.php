<?php
require_once 'config/db.php';
$conn = getDbConnection();

echo "<h2>User Table Diagnostic</h2>";

// Check for empty emails
$res = $conn->query("SELECT id, name FROM users WHERE email = '' OR email IS NULL");
echo "<h3>Empty Emails:</h3>";
if ($res->num_rows > 0) {
    while($row = $res->fetch_assoc()) {
        echo "ID: {$row['id']} - Name: {$row['name']}<br>";
    }
} else {
    echo "No users with empty emails found.<br>";
}

// Check for duplicate google_id (excluding NULL)
$res = $conn->query("SELECT google_id, COUNT(*) as count FROM users WHERE google_id != '' AND google_id IS NOT NULL GROUP BY google_id HAVING count > 1");
echo "<h3>Duplicate Google IDs:</h3>";
if ($res->num_rows > 0) {
    while($row = $res->fetch_assoc()) {
        echo "Google ID: {$row['google_id']} - Count: {$row['count']}<br>";
    }
} else {
    echo "No duplicate Google IDs found.<br>";
}

// Check for empty string google_id
$res = $conn->query("SELECT id, name FROM users WHERE google_id = ''");
echo "<h3>Empty String Google IDs (Conflict Source):</h3>";
if ($res->num_rows > 0) {
    while($row = $res->fetch_assoc()) {
        echo "ID: {$row['id']} - Name: {$row['name']}<br>";
    }
} else {
    echo "No users with empty string Google IDs found.<br>";
}

// Check for email duplicates
$res = $conn->query("SELECT email, COUNT(*) as count FROM users GROUP BY email HAVING count > 1");
echo "<h3>Duplicate Emails:</h3>";
if ($res->num_rows > 0) {
    while($row = $res->fetch_assoc()) {
        echo "Email: {$row['email']} - Count: {$row['count']}<br>";
    }
} else {
    echo "No duplicate emails found.<br>";
}
?>
