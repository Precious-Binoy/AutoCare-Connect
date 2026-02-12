<?php
require 'config/db.php';
$conn = getDbConnection();
$res = $conn->query("DESCRIBE pickup_delivery");
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Table pickup_delivery not found\n";
}
?>
