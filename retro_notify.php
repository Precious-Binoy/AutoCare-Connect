<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/notification_helper.php';

$conn = getDbConnection();

// Retroactively notify drivers for any currently scheduled pickups that have no driver assigned
$query = "SELECT pd.*, b.booking_number 
          FROM pickup_delivery pd 
          JOIN bookings b ON pd.booking_id = b.id 
          WHERE pd.status = 'scheduled' AND (pd.driver_user_id IS NULL OR pd.driver_user_id = 0)";
$res = $conn->query($query);

while ($row = $res->fetch_assoc()) {
    $bNum = $row['booking_number'];
    echo "Notifier for Booking #$bNum\n";
    notifyAvailableDrivers("🚗 New Pickup Request", "A new pickup request is available. Booking #$bNum", "driver_dashboard.php?tab=jobs&subtab=available");
}
echo "Done retroactively notifying drivers!\n";

// Retroactively notify mechanics for any currently pending drop-offs (has_pickup_delivery = 0 AND b.status = 'pending')
// OR (has_pickup_delivery = 1 AND b.status = 'confirmed') and mechanic_id IS NULL
$query = "SELECT b.* 
          FROM bookings b 
          LEFT JOIN pickup_delivery pd ON b.id = pd.booking_id 
          WHERE b.mechanic_id IS NULL 
          AND (
              (b.has_pickup_delivery = 0 AND b.status = 'pending')
              OR
              (b.has_pickup_delivery = 1 AND b.status = 'confirmed')
          )";
$res = $conn->query($query);

while ($row = $res->fetch_assoc()) {
    $bNum = $row['booking_number'];
    echo "Notifier for Mechanics for Booking #$bNum\n";
    notifyAvailableMechanics("🔧 New Service Request", "A drop-off service request is available. Booking #$bNum", "mechanic_dashboard.php?tab=jobs&subtab=available");
}
echo "Done retroactively notifying mechanics!\n";

?>
