<?php
require 'config/db.php';
$conn = getDbConnection();

echo "--- BOOKINGS ---\n";
$res = $conn->query("DESCRIBE bookings");
while($row = $res->fetch_assoc()) echo $row['Field'] . "\n";

echo "\n--- PICKUP_DELIVERY ---\n";
$res = $conn->query("DESCRIBE pickup_delivery");
if($res) while($row = $res->fetch_assoc()) echo $row['Field'] . "\n";

echo "\n--- SERVICE_UPDATES ---\n";
$res = $conn->query("DESCRIBE service_updates");
if($res) while($row = $res->fetch_assoc()) echo $row['Field'] . "\n";
?>
