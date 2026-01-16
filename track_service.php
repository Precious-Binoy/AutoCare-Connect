<?php 
$page_title = 'Track Service'; 
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
                            <div class="card p-4 flex flex-col gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="avatar bg-primary text-white flex items-center justify-center rounded-xl text-xl font-bold" style="width: 45px; height: 45px;">
                                        <i class="fa-solid fa-screwdriver-wrench"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="text-[10px] text-muted uppercase font-bold tracking-wider">Mechanic</div>
                                        <div class="font-bold"><?php echo htmlspecialchars($booking['mechanic_name'] ?? 'Assigning Soon...'); ?></div>
                                    </div>
                                    <?php if (isset($booking['mechanic_phone']) && $booking['mechanic_phone']): ?>
                                        <button onclick="copyToClipboard('<?php echo $booking['mechanic_phone']; ?>')" class="btn btn-sm btn-outline px-2" title="Copy Number">
                                            <i class="fa-solid fa-copy"></i>
                                        </button>
                                        <a href="tel:<?php echo $booking['mechanic_phone']; ?>" class="btn btn-sm btn-primary px-2"><i class="fa-solid fa-phone"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Driver Card -->
                            <?php 
                                // Fetch driver for this booking
                                $driverQuery = "SELECT driver_name, driver_phone, status, type FROM pickup_delivery WHERE booking_id = ? AND status != 'completed' ORDER BY created_at DESC LIMIT 1";
                                $driverRes = executeQuery($driverQuery, [$booking_id], 'i');
                                $activeDriver = $driverRes ? $driverRes->fetch_assoc() : null;
                            ?>
                            <div class="card p-4 flex flex-col gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="avatar bg-secondary text-white flex items-center justify-center rounded-xl text-xl font-bold" style="width: 45px; height: 45px;">
                                        <i class="fa-solid fa-truck"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="text-[10px] text-muted uppercase font-bold tracking-wider">Driver / Logistics</div>
                                        <div class="font-bold"><?php echo htmlspecialchars($activeDriver['driver_name'] ?? ($booking['status'] == 'pending' ? 'Waiting for Confirmation' : 'No Pickup/Delivery Active')); ?></div>
                                    </div>
                                    <?php if ($activeDriver && $activeDriver['driver_phone']): ?>
                                        <button onclick="copyToClipboard('<?php echo $activeDriver['driver_phone']; ?>')" class="btn btn-sm btn-outline px-2" title="Copy Number">
                                            <i class="fa-solid fa-copy"></i>
                                        </button>
                                        <a href="tel:<?php echo $activeDriver['driver_phone']; ?>" class="btn btn-sm btn-primary px-2"><i class="fa-solid fa-phone"></i></a>
                                    <?php endif; ?>
                                </div>
                                <?php if ($activeDriver): ?>
                                    <div class="bg-gray-50 p-2 rounded text-[10px] font-bold uppercase flex items-center justify-between">
                                        <span>Status: <?php echo str_replace('_', ' ', $activeDriver['status']); ?></span>
                                        <span class="text-primary"><?php echo ucfirst($activeDriver['type']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced Tracker Component -->
                    <div class="card p-10 mb-8 overflow-x-auto">
                        <div class="text-center mb-6">
                            <h3 class="font-bold text-lg">Service Progress Journey</h3>
                        </div>
                        <?php 
                            // Determine if booking has pickup/delivery
                            $hasPickupDelivery = $booking['has_pickup_delivery'] ?? false;
                            $bStatus = $booking['status'];
                            $stepIndex = 1;
                            
                            if ($hasPickupDelivery) {
                                // Pickup/Delivery Flow: Booked → Pickup → In Workshop → Ready for Delivery → Delivered
                                if ($bStatus == 'confirmed') $stepIndex = 2;
                                if ($activeDriver && $activeDriver['type'] == 'pickup' && $activeDriver['status'] != 'completed') $stepIndex = 2;
                                if ($bStatus == 'in_progress') $stepIndex = 3;
                                if ($bStatus == 'ready_for_delivery') $stepIndex = 4;
                                if ($activeDriver && $activeDriver['type'] == 'delivery' && $activeDriver['status'] != 'completed') $stepIndex = 4;
                                if ($bStatus == 'delivered') $stepIndex = 5;
                            } else {
                                // Self-Pickup Flow: Booked → Arrived → In Workshop → Work Done → Collected
                                if ($bStatus == 'confirmed') $stepIndex = 2;
                                if ($bStatus == 'in_progress') $stepIndex = 3;
                                if ($bStatus == 'completed' || $bStatus == 'ready_for_delivery') $stepIndex = 4;
                                if ($bStatus == 'delivered') $stepIndex = 5;
                            }
                        ?>
                        <div class="progress-track" style="margin: 1.5rem 0; min-width: 700px;">
                            <div class="progress-line" style="height: 4px; background: #E2E8F0;"></div>
                            <div class="progress-line-fill" style="height: 4px; background: var(--primary); transition: width 1s ease-in-out; width: <?php echo max(0, ($stepIndex - 1) * 25); ?>%;"></div>

                            <!-- Step 1: Confirmed -->
                            <div class="progress-step">
                                <div class="step-circle <?php echo $stepIndex >= 1 ? 'completed' : ''; ?>"><i class="fa-solid fa-check"></i></div>
                                <div class="font-bold text-xs mt-2">Booked</div>
                            </div>
                            <!-- Step 2: Pickup/Arrived -->
                            <div class="progress-step">
                                <div class="step-circle <?php echo $stepIndex >= 2 ? ($stepIndex == 2 ? 'active' : 'completed') : ''; ?>">
                                    <i class="fa-solid <?php echo $hasPickupDelivery ? 'fa-truck-pickup' : 'fa-location-dot'; ?>"></i>
                                </div>
                                <div class="font-bold text-xs mt-2"><?php echo $hasPickupDelivery ? 'Pickup' : 'Arrived'; ?></div>
                            </div>
                            <!-- Step 3: Service -->
                            <div class="progress-step">
                                <div class="step-circle <?php echo $stepIndex >= 3 ? ($stepIndex == 3 ? 'active' : 'completed') : ''; ?>">
                                    <i class="fa-solid fa-screwdriver-wrench <?php echo ($stepIndex == 3) ? 'fa-spin' : ''; ?>"></i>
                                </div>
                                <div class="font-bold text-xs mt-2">In Workshop</div>
                            </div>
                            <!-- Step 4: Delivery/Work Done -->
                            <div class="progress-step">
                                <div class="step-circle <?php echo $stepIndex >= 4 ? ($stepIndex == 4 ? 'active' : 'completed') : ''; ?>">
                                    <i class="fa-solid <?php echo $hasPickupDelivery ? 'fa-car-side' : 'fa-clipboard-check'; ?>"></i>
                                </div>
                                <div class="font-bold text-xs mt-2"><?php echo $hasPickupDelivery ? 'Delivery' : 'Work Done'; ?></div>
                            </div>
                            <!-- Step 5: Done -->
                            <div class="progress-step">
                                <div class="step-circle <?php echo $stepIndex == 5 ? 'completed' : ''; ?>">
                                    <i class="fa-solid fa-flag-checkered"></i>
                                </div>
                                <div class="font-bold text-xs mt-2"><?php echo $hasPickupDelivery ? 'Delivered' : 'Collected'; ?></div>
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
                                            <p class="text-xs text-muted">Payment Details</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Bill Amount -->
                                <div class="p-6 bg-white">
                                    <div class="text-center mb-6 pb-6 border-b border-gray-100">
                                        <div class="text-xs font-bold uppercase text-muted mb-2 tracking-wider">Total Amount Due</div>
                                        <div class="text-4xl font-black text-green-600">₹<?php echo number_format($booking['bill_amount'], 2); ?></div>
                                    </div>
                                    
                                    <!-- Service Notes -->
                                    <div class="mb-6">
                                        <div class="flex items-center gap-2 mb-3">
                                            <i class="fa-solid fa-clipboard-list text-primary"></i>
                                            <h4 class="font-bold text-sm uppercase tracking-wider text-gray-700">Work Summary & Parts</h4>
                                        </div>
                                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                                            <p class="text-sm text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($booking['service_notes'] ?? 'No additional notes provided.')); ?></p>
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
                            <div class="card p-10 bg-gray-50 border-2 border-dashed border-gray-200 text-center flex flex-col items-center justify-center">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="fa-solid fa-hourglass-half text-3xl text-gray-300"></i>
                                </div>
                                <h4 class="font-bold text-gray-600 text-lg mb-2">Billing Pending</h4>
                                <p class="text-sm text-muted max-w-xs">Invoice will be generated once the mechanic completes the service work.</p>
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
