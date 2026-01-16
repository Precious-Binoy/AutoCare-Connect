<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

$conn = getDbConnection();
$result = $conn->query("DESCRIBE drivers");

echo "Drivers Table Structure:\n";
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
