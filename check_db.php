<?php
$conn = new mysqli('localhost', 'root', '', 'autocare_connect');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$tables = ['bookings', 'mechanics', 'drivers', 'pickup_delivery', 'service_updates'];
foreach ($tables as $table) {
    echo "--- $table ---\n";
    $res = $conn->query("DESCRIBE $table");
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    echo "\n";
}
