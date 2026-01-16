<?php
require_once __DIR__ . '/config/db.php';
$conn = getDbConnection();

echo "Checking for approved requests where user role mismatch...\n";
$sql = "SELECT jr.id, jr.name, jr.email, jr.role_requested, u.role as current_role
        FROM job_requests jr
        JOIN users u ON jr.email = u.email
        WHERE jr.status = 'approved' AND jr.role_requested != u.role";

$res = $conn->query($sql);
if ($res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        echo "Mismatch: " . $row['name'] . " wanted " . $row['role_requested'] . " but is " . $row['current_role'] . "\n";
        // Fix role
        $update = "UPDATE users SET role = ? WHERE email = ?";
        $stmt = $conn->prepare($update);
        $stmt->bind_param("ss", $row['role_requested'], $row['email']);
        $stmt->execute();
        echo "  > Fixed role.\n";
    }
} else {
    echo "No role mismatches found.\n";
}
?>
