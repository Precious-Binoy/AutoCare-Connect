<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

requireAdmin();

$booking_id = intval($_GET['id'] ?? 0);
if (!$booking_id) die('<div class="text-red-500 text-xs">Invalid Booking ID</div>');

// Fetch booking and vehicle info
$query = "SELECT b.*, v.make, v.model, u.name as customer_name 
          FROM bookings b 
          JOIN vehicles v ON b.vehicle_id = v.id 
          JOIN users u ON b.user_id = u.id
          WHERE b.id = ?";
$res = executeQuery($query, [$booking_id], 'i');
$booking = $res->fetch_assoc();

if (!$booking) die('<div class="text-red-500 text-xs">Booking not found</div>');

// Fetch parts
$partsQuery = "SELECT * FROM parts_used WHERE booking_id = ?";
$partsRes = executeQuery($partsQuery, [$booking_id], 'i');
$parts = [];
$partsTotal = 0;
if ($partsRes) {
    while ($p = $partsRes->fetch_assoc()) {
        $parts[] = $p;
        $partsTotal += floatval($p['total_price'] ?? 0);
    }
}

// Fetch logistics
$pdQuery = "SELECT * FROM pickup_delivery WHERE booking_id = ?";
$pdRes = executeQuery($pdQuery, [$booking_id], 'i');
$logistics = [];
$logisticsTotal = 0;
if ($pdRes) {
    while ($l = $pdRes->fetch_assoc()) {
        $logistics[] = $l;
        $logisticsTotal += floatval($l['fee'] ?? 0);
    }
}

// Format Output
?>
<div class="space-y-6">
    <!-- Service Breakdown -->
    <div class="bg-blue-50/50 rounded-2xl p-5 border border-blue-100">
        <h4 class="text-[10px] font-black uppercase text-primary tracking-widest mb-3">Service & Repair</h4>
        <div class="space-y-2">
            <div class="flex justify-between items-center text-sm">
                <span class="text-gray-600"><?php echo htmlspecialchars($booking['service_type']); ?> (Base)</span>
                <span class="font-bold text-gray-900">₹<?php echo number_format($booking['mechanic_fee'], 2); ?></span>
            </div>
            <?php foreach($parts as $part): ?>
                <div class="flex justify-between items-center text-xs">
                    <span class="text-muted"><i class="fa-solid fa-gear mr-2 text-[9px]"></i> <?php echo htmlspecialchars($part['part_name']); ?> (x<?php echo $part['quantity']; ?>)</span>
                    <span class="font-medium text-gray-700">₹<?php echo number_format($part['total_price'], 2); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Logistics Breakdown -->
    <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100">
        <h4 class="text-[10px] font-black uppercase text-muted tracking-widest mb-3">Logistics Fees</h4>
        <div class="space-y-2">
            <?php foreach($logistics as $item): ?>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-600 capitalize"><?php echo $item['type']; ?> (<?php echo $item['driver_name'] ?? 'Self'; ?>)</span>
                    <span class="font-bold text-gray-900">₹<?php echo number_format($item['fee'], 2); ?></span>
                </div>
            <?php endforeach; ?>
            <?php if(empty($logistics)): ?>
                <p class="text-[10px] text-muted italic">Self-service (No logistics fees applied).</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Final Summary -->
    <div class="pt-4 border-t border-dashed border-gray-200">
        <div class="flex justify-between items-end">
            <div>
                <p class="text-[10px] font-black uppercase text-muted tracking-tighter">Net Payable Amount</p>
                <p class="text-3xl font-black text-primary">₹<?php echo number_format($booking['final_cost'], 2); ?></p>
            </div>
            <div class="text-right">
                <span class="badge <?php echo $booking['is_billed'] ? 'badge-success' : 'badge-warning'; ?> uppercase text-[9px] px-3">
                    <?php echo $booking['is_billed'] ? 'Billed' : 'Pending Payment'; ?>
                </span>
            </div>
        </div>
    </div>
</div>
