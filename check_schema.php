<?php
require_once 'config/db.php';
$conn = getDbConnection();

$result = $conn->query("DESCRIBE users");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
