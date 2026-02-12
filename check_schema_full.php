<?php
require_once 'config/db.php';
$conn = getDbConnection();

$tables = ['users', 'drivers', 'mechanics'];
foreach ($tables as $table) {
    echo "Table: $table\n";
    $result = $conn->query("DESCRIBE $table");
    while($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
    echo "\n";
}
?>
