<?php
require_once 'config/db.php';
$conn = getDbConnection();
$res = $conn->query("SELECT id, name, role, is_active FROM users");
$rows = [];
while($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
echo json_encode($rows, JSON_PRETTY_PRINT);
