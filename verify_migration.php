<?php
require_once 'config/db.php';
$conn = getDbConnection();
$required = [
    'bookings' => ['mechanic_fee', 'has_pickup_delivery', 'final_cost', 'service_notes', 'is_billed'],
    'pickup_delivery' => ['fee']
];

$output = "";
foreach ($required as $table => $cols) {
    $output .= "Checking table: $table\n";
    foreach ($cols as $col) {
        $res = $conn->query("SHOW COLUMNS FROM $table LIKE '$col'");
        if ($res && $res->num_rows > 0) {
            $output .= "  Column '$col' exists.\n";
        } else {
            $output .= "  Column '$col' MISSING!\n";
        }
    }
}
file_put_contents('migration_status.txt', $output);
?>
