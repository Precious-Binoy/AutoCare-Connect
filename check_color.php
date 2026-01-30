<?php
require_once 'config/db.php';
$conn = getDbConnection();
try {
    $conn->query("SELECT color FROM vehicles LIMIT 1");
    echo "Color column exists";
} catch (Exception $e) {
    echo "Color column missing";
}
?>
