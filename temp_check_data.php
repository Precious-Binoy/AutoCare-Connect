<?php
require_once 'config/db.php';
$conn = getDbConnection();
$res = $conn->query("SELECT * FROM notifications ORDER BY id DESC LIMIT 10");
$rows = [];
while($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
echo json_encode($rows, JSON_PRETTY_PRINT);
