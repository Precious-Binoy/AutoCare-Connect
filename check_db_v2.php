<?php
require_once 'config/db.php';
$conn = getDbConnection();

function checkTable($conn, $table) {
    echo "--- $table ---\n";
    $res = $conn->query("DESCRIBE $table");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "Error: " . $conn->error . "\n";
    }
    echo "\n";
}

checkTable($conn, 'bookings');
checkTable($conn, 'pickup_delivery');
checkTable($conn, 'parts_used');
?>
