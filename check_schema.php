<?php
require_once 'config/db.php';
$conn = getDbConnection();

$tables = ['drivers', 'mechanics', 'users', 'pickup_delivery', 'bookings'];
$schema = [];

foreach ($tables as $table) {
    $res = $conn->query("DESCRIBE $table");
    if ($res) {
        $cols = [];
        while ($row = $res->fetch_assoc()) {
            $cols[] = $row;
        }
        $schema[$table] = $cols;
    }
}

file_put_contents('schema_dump.json', json_encode($schema, JSON_PRETTY_PRINT));
echo "Schema dumped to schema_dump.json\n";
