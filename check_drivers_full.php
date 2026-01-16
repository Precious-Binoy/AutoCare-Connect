<?php
require_once __DIR__ . '/config/db.php';
$conn = getDbConnection();
$res = $conn->query("SHOW CREATE TABLE drivers");
$row = $res->fetch_assoc();
print_r($row);
?>
