<?php 
$page_title = 'Track Service'; 
$current_page = 'track_service.php';
require_once 'includes/auth.php';
requireLogin();

$userId = getCurrentUserId();
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$booking = null;

if ($booking_id) {
    // Fetch specific booking details with vehicle and mechanic info
    $query = "SELECT b.*, v.make, v.model, v.year, v.license_plate, v.vin, v.mileage, 
                     m.id as mechanic_id, u.name as mechanic_name, u.phone as mechanic_phone 
              FROM bookings b 
              JOIN vehicles v ON b.vehicle_id = v.id 
              LEFT JOIN mechanics m ON b.mechanic_id = m.id 
              LEFT JOIN users u ON m.user_id = u.id
              WHERE b.id = ? AND b.user_id = ?";
    $result = executeQuery($query, [$booking_id, $userId], 'ii');
    if ($result && $result->num_rows > 0) {
        $booking = $result->fetch_assoc();
    }
} else {
    // If no specific ID, find the most recent active booking
    $query = "SELECT id FROM bookings WHERE user_id = ? AND status IN ('confirmed', 'in_progress', 'pending') ORDER BY created_at DESC LIMIT 1";
    $result = executeQuery($query, [$userId], 'i');
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        header("Location: track_service.php?id=" . $row['id']);
        exit;
    }
}

// Helper for status to step mapping
function getStatusStep($status) {
    switch ($status) {
        case 'pending': return 1;
        case 'confirmed': return 1;
        case 'in_progress': return 3; // Pickup is 2
        case 'completed': return 4;
        case 'delivered': return 5;
        default: return 0;
    }
}
$currentStep = $booking ? getStatusStep($booking['status']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Service - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="page-content">
                <?php if (!$booking): ?>
                    <div class="text-center py-12">
                        <i class="fa-solid fa-clipboard-list text-6xl text-gray-300 mb-4"></i>
                        <h2 class="text-2xl font-bold text-gray-700">No Active Service Found</h2>
                        <p class="text-muted mb-6">You don't have any active service bookings to track right now.</p>
                        <a href="book_service.php" class="btn btn-primary">Book New Service</a>
                    </div>
                <?php else: ?>
                    <div class="flex justify-between items-center mb-6">
                        <div>
                             <div class="flex items-center gap-2 text-primary font-bold text-sm mb-1">
                                <i class="fa-solid fa-receipt"></i> Booking #<?php echo htmlspecialchars($booking['booking_number']); ?>
                            </div>
                            <h1 class="text-4xl font-extrabold mb-1">Track Your Service</h1>
                            <p class="text-muted">Real-time status updates for your vehicle repair.</p>
                        </div>
                        <div class="flex gap-2">
                             <a href="customer_dashboard.php" class="btn btn-white font-bold"><i class="fa-solid fa-arrow-left mr-2"></i> Dashboard</a>
                        </div>
                    </div>

                    <!-- Service Information Header -->
                    <div class="card p-6 mb-8 bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-primary">
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center text-primary text-2xl">
                                <i class="fa-solid fa-car"></i>
                            </div>
                            <div class="flex-1">
                                <h2 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($booking['year'] . ' ' . $booking['make'] . ' ' . $booking['model']); ?></h2>
                                <p class="text-sm font-mono text-muted uppercase tracking-wider mt-1"><?php echo htmlspecialchars($booking['license_plate']); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Worker Info Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <!-- Mechanic Card -->
                            <div class="card p-5 flex flex-col gap-3 border-l-4 border-primary">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-primary text-white flex items-center justify-center rounded-2xl text-xl font-bold shadow-lg shadow-blue-100">
                                        <i class="fa-solid fa-screwdriver-wrench"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="text-[10px] text-primary uppercase font-black tracking-[0.2em]">Assigned Mechanic</div>
                                        <div class="font-bold text-lg"><?php echo htmlspecialchars($booking['mechanic_name'] ?? 'Assigning Soon...'); ?></div>
                                    </div>
                                    <?php if (isset($booking['mechanic_phone']) && $booking['mechanic_phone']): ?>
                                        <a href="tel:<?php echo $booking['mechanic_phone']; ?>" class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center hover:scale-110 transition-transform shadow-md"><i class="fa-solid fa-phone text-sm"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Driver Cards - Only show RELEVANT phase -->
                            <?php 
                                $driverQuery = "SELECT driver_name, driver_phone, status, type FROM pickup_delivery WHERE booking_id = ? ORDER BY CASE WHEN type = 'pickup' THEN 0 ELSE 1 END ASC, created_at ASC";
                                $driverRes = executeQuery($driverQuery, [$booking_id], 'i');
                                $drivers = [];
                                if ($driverRes) {
                                    while ($d = $driverRes->fetch_assoc()) {
                                        $drivers[] = $d;
                                    }
                                }
                                
                                if (empty($drivers) && $booking['has_pickup_delivery']) {
                                    $drivers[] = [
                                        'type' => 'pickup',
                                        'driver_name' => '',
                                        'driver_phone' => '',
                                        'status' => 'pending'
                                    ];
                                }

                                // Filter: Only show the CURRENT relevant driver card
                                $visibleDrivers = [];
                                foreach ($drivers as $d) {
                                    if ($d['type'] == 'pickup') {
                                        if (in_array($booking['status'], ['pending', 'confirmed']) || $d['status'] != 'completed') {
                                            $visibleDrivers[] = $d;
                                        }
                                    } elseif ($d['type'] == 'delivery') {
                                        if (in_array($booking['status'], ['ready_for_delivery', 'completed', 'delivered'])) {
                                            $visibleDrivers[] = $d;
                                        }
                                    }
                                }
                                
                                if (empty($visibleDrivers) && !empty($drivers)) {
                                    $visibleDrivers[] = $drivers[0];
                                }
                            ?>

                            <?php foreach ($visibleDrivers as $activeDriver): ?>
                            <div class="card p-5 flex flex-col gap-3 border-l-4 <?php echo $activeDriver['type'] == 'pickup' ? 'border-warning' : 'border-success'; ?>">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 <?php echo $activeDriver['type'] == 'pickup' ? 'bg-warning' : 'bg-success'; ?> text-white flex items-center justify-center rounded-2xl text-xl font-bold shadow-lg">
                                        <i class="fa-solid <?php echo $activeDriver['type'] == 'pickup' ? 'fa-truck-arrow-right' : 'fa-truck-fast'; ?>"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="text-[10px] <?php echo $activeDriver['type'] == 'pickup' ? 'text-warning' : 'text-success'; ?> uppercase font-black tracking-[0.2em]"><?php echo ucfirst($activeDriver['type']); ?> Driver</div>
                                        <div class="font-bold text-lg">
                                            <?php 
                                            if (!empty($activeDriver['driver_name'])) {
                                                echo htmlspecialchars($activeDriver['driver_name']);
                                            } else {
                                                echo 'Awaiting Assignment';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <?php if ($activeDriver && $activeDriver['driver_phone']): ?>
                                        <a href="tel:<?php echo $activeDriver['driver_phone']; ?>" class="w-10 h-10 rounded-full <?php echo $activeDriver['type'] == 'pickup' ? 'bg-warning' : 'bg-success'; ?> text-white flex items-center justify-center hover:scale-110 transition-transform shadow-md"><i class="fa-solid fa-phone text-sm"></i></a>
                                    <?php endif; ?>
                                </div>
                                <div class="bg-gray-50 px-4 py-2 rounded-xl text-[10px] font-black uppercase flex items-center justify-between">
                                    <span class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full <?php 
                                            if ($activeDriver['status'] == 'completed') echo 'bg-success';
                                            elseif ($activeDriver['status'] == 'in_transit') echo 'bg-primary animate-pulse';
                                            else echo 'bg-warning';
                                        ?>"></span>
                                        <?php echo str_replace('_', ' ', $activeDriver['status']); ?>
                                    </span>
                                    <span class="<?php echo $activeDriver['type'] == 'pickup' ? 'text-warning' : 'text-success'; ?>"><?php echo ucfirst($activeDriver['type']); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                    <!-- Enhanced Tracker Component -->
                    <div class="card p-8 mb-8 overflow-x-auto border-t-4 border-primary">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="font-black text-lg text-gray-900">Service Progress Journey</h3>
                                <p class="text-xs text-muted mt-1">Real-time tracking of your vehicle's service lifecycle</p>
                            </div>
                        </div>
                        <?php 
                            $hasPickupDelivery = $booking['has_pickup_delivery'] ?? false;
                            $bStatus = $booking['status'];
                            $activeIndex = 0;
                            
                            $pickupTask = null;
                            $deliveryTask = null;
                            foreach ($drivers as $d) {
                                if ($d['type'] == 'pickup') $pickupTask = $d;
                                if ($d['type'] == 'delivery') $deliveryTask = $d;
                            }
                            
                            if ($hasPickupDelivery) {
                                $hasPickupDriver = $pickupTask && !empty($pickupTask['driver_name']);
                                
                                if ($bStatus == 'pending' || $bStatus == 'confirmed') {
                                    $activeIndex = 0;
                                }
                                
                                // Phase 1: Pickup (Only if driver is assigned)
                                if ($pickupTask && $hasPickupDriver && ($pickupTask['status'] == 'scheduled' || $pickupTask['status'] == 'in_transit')) {
                                    $activeIndex = 1;
                                }

                                // Phase 2: Repair (Status is in_progress or pickup finished)
                                if ($bStatus == 'in_progress' || ($pickupTask && $pickupTask['status'] == 'completed')) {
                                    $activeIndex = 2;
                                }

                                // Phase 3: Delivery (Status is ready_for_delivery or delivery is in_transit)
                                if ($bStatus == 'ready_for_delivery' || ($deliveryTask && $deliveryTask['status'] == 'in_transit')) {
                                    $activeIndex = 3;
                                }

                                // Phase 4: Done
                                if ($bStatus == 'delivered' || $bStatus == 'completed' || ($deliveryTask && $deliveryTask['status'] == 'completed')) {
                                    $activeIndex = 4;
                                }
                            } else {
                                if ($bStatus == 'pending') $activeIndex = 0;
                                elseif ($bStatus == 'confirmed') $activeIndex = 1;
                                elseif ($bStatus == 'in_progress') $activeIndex = 2;
                                elseif ($bStatus == 'ready_for_delivery') $activeIndex = 3;
                                elseif ($bStatus == 'completed' || $bStatus == 'delivered') $activeIndex = 4;
                            }

                            // Progress width as a fraction of the track between first and last dot centers
                            $progressFraction = $activeIndex / 4;
                        ?>
                        <div class="timeline-horizontal" style="min-width: 600px;">
                            <div class="timeline-progress" style="width: calc((100% - 3rem - 100px) * <?php echo $progressFraction; ?>);"></div>

                            <div class="timeline-step <?php echo $activeIndex >= 0 ? ($activeIndex > 0 ? 'completed' : 'active') : ''; ?>">
                                <div class="timeline-dot"><i class="fa-solid fa-calendar-check"></i></div>
                                <div class="timeline-label">Booked</div>
                            </div>

                            <div class="timeline-step <?php echo $activeIndex >= 1 ? ($activeIndex > 1 ? 'completed' : 'active') : ''; ?>">
                                <div class="timeline-dot">
                                    <i class="fa-solid <?php echo $hasPickupDelivery ? 'fa-truck-pickup' : 'fa-warehouse'; ?>"></i>
                                </div>
                                <div class="timeline-label"><?php echo $hasPickupDelivery ? 'Pickup' : 'Arrival'; ?></div>
                            </div>

                            <div class="timeline-step <?php echo $activeIndex >= 2 ? ($activeIndex > 2 ? 'completed' : 'active') : ''; ?>">
                                <div class="timeline-dot">
                                    <i class="fa-solid fa-screwdriver-wrench <?php echo ($activeIndex == 2) ? 'fa-spin' : ''; ?>"></i>
                                </div>
                                <div class="timeline-label">Service</div>
                            </div>

                            <div class="timeline-step <?php echo $activeIndex >= 3 ? ($activeIndex > 3 ? 'completed' : 'active') : ''; ?>">
                                <div class="timeline-dot">
                                    <i class="fa-solid <?php echo $hasPickupDelivery ? 'fa-truck-fast' : 'fa-car-side'; ?>"></i>
                                </div>
                                <div class="timeline-label"><?php echo $hasPickupDelivery ? 'Delivery' : 'Ready'; ?></div>
                            </div>

                            <div class="timeline-step <?php echo $activeIndex >= 4 ? 'completed' : ''; ?>">
                                <div class="timeline-dot"><i class="fa-solid fa-flag-checkered"></i></div>
                                <div class="timeline-label">Completed</div>
                            </div>
                        </div>
                    </div>

                    <script>
                        function copyToClipboard(text) {
                            navigator.clipboard.writeText(text).then(() => {
                                alert('Phone number copied to clipboard!');
                            });
                        }
                    </script>

                    <!-- Details Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                        <div class="lg:col-span-2 flex flex-col gap-6">
                            <div class="card p-0 overflow-hidden">
                                <div class="flex justify-between items-center p-4 bg-gray-50 border-b border-gray-100">
                                     <h3 class="font-bold">Service Details</h3>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 divide-y divide-gray-100">
                                    <div class="p-6">
                                         <div class="flex items-center gap-2 text-muted text-xs font-bold uppercase mb-2"><i class="fa-solid fa-briefcase"></i> Service Type</div>
                                         <div class="font-bold text-sm"><?php echo htmlspecialchars($booking['service_type']); ?></div>
                                    </div>
                                    <div class="p-6">
                                         <div class="flex items-center gap-2 text-muted text-xs font-bold uppercase mb-2"><i class="fa-solid fa-clock"></i> Preferred Date</div>
                                         <div class="font-bold"><?php echo date('M d, g:i A', strtotime($booking['preferred_date'])); ?></div>
                                    </div>
                                     <div class="p-6">
                                         <div class="flex items-center gap-2 text-muted text-xs font-bold uppercase mb-2"><i class="fa-solid fa-align-left"></i> Notes</div>
                                         <div class="text-sm text-muted"><?php echo htmlspecialchars($booking['notes'] ?? 'No notes provided.'); ?></div>
                                    </div>
                                    <div class="p-6">
                                         <div class="flex items-center gap-2 text-muted text-xs font-bold uppercase mb-2"><i class="fa-solid fa-id-card"></i> Booking ID</div>
                                         <div class="font-mono text-xs"><?php echo htmlspecialchars($booking['booking_number']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Invoice / Billing Card -->
                        <div class="flex flex-col gap-6">
                            <?php if ($booking['is_billed']): ?>
                            <div class="card overflow-hidden border-2 border-green-200 animate-fade-in shadow-lg">
                                <!-- Header -->
                                <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-6 border-b border-green-100">
                                    <div class="flex items-center gap-3 mb-2">
                                        <div class="w-12 h-12 bg-green-500 text-white rounded-xl flex items-center justify-center">
                                            <i class="fa-solid fa-file-invoice-dollar text-xl"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-black text-gray-900">Service Invoice</h3>
                                            <p class="text-xs text-muted">Itemized Breakdown</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Bill Details -->
                                <div class="p-6 bg-white">
                                    <div class="space-y-4 mb-6">
                                        <!-- Labor -->
                                        <div class="flex justify-between items-center text-sm">
                                            <span class="text-muted font-medium">Mechanic Labor / Service Fee</span>
                                            <span class="font-black text-gray-900">₹<?php echo number_format($booking['mechanic_fee'] ?? 0, 2); ?></span>
                                        </div>

                                        <!-- Parts -->
                                        <?php 
                                            $partsQuery = "SELECT * FROM parts_used WHERE booking_id = ?";
                                            $partsRes = executeQuery($partsQuery, [$booking_id], 'i');
                                            $parts = [];
                                            if ($partsRes) {
                                                while ($pRow = $partsRes->fetch_assoc()) {
                                                    $parts[] = $pRow;
                                                }
                                            }
                                            $partsTotal = 0;
                                            if (!empty($parts)):
                                        ?>
                                        <div class="pt-4 border-t border-gray-50">
                                            <div class="text-[10px] font-black uppercase text-gray-400 mb-2">Parts & Products</div>
                                            <?php foreach ($parts as $part): 
                                                $partsTotal += $part['total_price'];
                                            ?>
                                                <div class="flex justify-between items-center text-sm mb-1">
                                                    <span class="text-gray-600"><?php echo htmlspecialchars($part['part_name']); ?> <span class="text-[10px] text-muted">(x<?php echo $part['quantity']; ?>)</span></span>
                                                    <span class="font-bold">₹<?php echo number_format($part['total_price'], 2); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Logistics -->
                                        <?php 
                                            $logQuery = "SELECT type, fee FROM pickup_delivery WHERE booking_id = ? AND fee > 0";
                                            $logRes = executeQuery($logQuery, [$booking_id], 'i');
                                            $logItems = [];
                                            if ($logRes) {
                                                while ($lRow = $logRes->fetch_assoc()) {
                                                    $logItems[] = $lRow;
                                                }
                                            }
                                            $logTotal = 0;
                                            if (!empty($logItems)):
                                        ?>
                                        <div class="pt-4 border-t border-gray-50">
                                            <div class="text-[10px] font-black uppercase text-gray-400 mb-2">Logistics Fees</div>
                                            <?php foreach ($logItems as $log): 
                                                $logTotal += $log['fee'];
                                            ?>
                                                <div class="flex justify-between items-center text-sm mb-1">
                                                    <span class="text-gray-600 capitalize"><?php echo htmlspecialchars($log['type']); ?> Service</span>
                                                    <span class="font-bold">₹<?php echo number_format($log['fee'], 2); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="text-center py-6 border-t-2 border-dashed border-gray-100 mb-6">
                                        <div class="text-xs font-bold uppercase text-muted mb-2 tracking-wider">Total Amount Due</div>
                                        <div class="text-4xl font-black text-green-600">₹<?php echo number_format($booking['final_cost'] ?? 0, 2); ?></div>
                                    </div>
                                    
                                    <!-- Service Notes -->
                                    <div class="mb-6">
                                        <div class="flex items-center gap-2 mb-3">
                                            <i class="fa-solid fa-comment-dots text-primary"></i>
                                            <h4 class="font-bold text-sm uppercase tracking-wider text-gray-700">Mechanic Notes</h4>
                                        </div>
                                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                                            <p class="text-sm text-gray-700 italic leading-relaxed"><?php echo nl2br(htmlspecialchars($booking['service_notes'] ?? 'No specific notes from mechanic.')); ?></p>
                                        </div>
                                    </div>
                                    
                                    <!-- Payment Button -->
                                    <button class="btn btn-success w-full py-4 text-lg font-bold rounded-xl shadow-lg hover:shadow-xl transition-all">
                                        <i class="fa-solid fa-credit-card mr-2"></i> Pay Now
                                    </button>
                                    <p class="text-[10px] text-center text-muted mt-3">
                                        <i class="fa-solid fa-lock mr-1"></i> Secure payment powered by AutoCare Connect
                                    </p>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="card p-10 bg-white border-2 border-dashed border-gray-200 text-center flex flex-col items-center justify-center">
                                <div class="w-16 h-16 bg-gray-50 rounded-2xl flex items-center justify-center mb-4 text-gray-300">
                                    <i class="fa-solid fa-hourglass-half text-3xl"></i>
                                </div>
                                <h4 class="font-black text-gray-800 text-lg mb-2">Final Bill Pending</h4>
                                <p class="text-sm text-muted max-w-[200px] mx-auto">Your itemized invoice is being prepared by our mechanic. It will appear here shortly.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
