<?php
require 'config/db.php';
$conn = getDbConnection();

$conn->query("DELETE d FROM drivers d LEFT JOIN users u ON d.user_id = u.id WHERE u.id IS NULL");
$conn->query("DELETE m FROM mechanics m LEFT JOIN users u ON m.user_id = u.id WHERE u.id IS NULL");
echo "Cleanup complete. Rows removed: " . $conn->affected_rows . "\n";

echo "--- VERIFYING --- \n";
$totalM = $conn->query("SELECT COUNT(*) as count FROM mechanics")->fetch_assoc()['count'];
$totalD = $conn->query("SELECT COUNT(*) as count FROM drivers")->fetch_assoc()['count'];
echo "Total Mechanics: $totalM | Total Drivers: $totalD\n";
?>
