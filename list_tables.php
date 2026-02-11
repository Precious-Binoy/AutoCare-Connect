<?php
require_once 'config/db.php';
$conn = getDbConnection();
$res = $conn->query("SHOW TABLES");
while($row = $res->fetch_row()) {
    echo $row[0] . "\n";
}
?>
