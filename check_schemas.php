<?php
require_once 'config/db.php';
$conn = getDbConnection();
$tables = ['job_requests', 'parts_used', 'pickup_delivery', 'service_updates'];

foreach ($tables as $table) {
    echo "\n--- Schema for $table ---\n";
    $res = $conn->query("DESCRIBE $table");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            echo $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "Table $table does not exist.\n";
    }
}
?>
