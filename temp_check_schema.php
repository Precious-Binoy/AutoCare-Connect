<?php
require_once 'config/db.php';
$conn = getDbConnection();
$res = $conn->query("SHOW CREATE TABLE notifications");
if($row = $res->fetch_assoc()) {
    echo $row['Create Table'];
} else {
    echo "Table not found";
}
