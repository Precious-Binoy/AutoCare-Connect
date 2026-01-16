<?php
require_once __DIR__ . '/config/db.php';
$conn = getDbConnection();
$res = $conn->query("DESCRIBE pickup_delivery");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
