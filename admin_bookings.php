<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/notification_helper.php';

// Require admin access
requireAdmin();

// Set current page for navigation
$current_page = 'admin_bookings.php';
$page_title = 'Manage Bookings';

// Fetch statistics
$pendingQuery = "SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'";
$pendingResult = executeQuery($pendingQuery, [], '');
$pendingCount = ($pendingResult && $row = $pendingResult->fetch_assoc()) ? $row['count'] : 0;

$inProgressQuery = "SELECT COUNT(*) as count FROM bookings WHERE status IN ('confirmed', 'in_progress')";
$inProgressResult = executeQuery($inProgressQuery, [], '');
$inProgressCount = ($inProgressResult && $row = $inProgressResult->fetch_assoc()) ? $row['count'] : 0;

$completedTodayQuery = "SELECT COUNT(*) as count FROM bookings WHERE status IN ('completed', 'delivered', 'ready_for_delivery') AND DATE(completion_date) = CURDATE()";
$completedTodayResult = executeQuery($completedTodayQuery, [], '');
$completedToday = ($completedTodayResult && $row = $completedTodayResult->fetch_assoc()) ? $row['count'] : 0;

$activeWorkersQuery = "SELECT 
    (SELECT COUNT(*) FROM mechanics m JOIN users u ON m.user_id = u.id WHERE m.is_available = TRUE) + 
    (SELECT COUNT(*) FROM drivers d JOIN users u ON d.user_id = u.id WHERE d.is_available = TRUE) as count";
$activeWorkersResult = executeQuery($activeWorkersQuery, [], '');
$activeWorkers = ($activeWorkersResult && $row = $activeWorkersResult->fetch_assoc()) ? $row['count'] : 0;

// Fetch Financial Statistics
$financeQuery = "SELECT 
    SUM(CASE WHEN payment_status = 'paid' THEN final_cost ELSE 0 END) as total_received,
    SUM(CASE WHEN is_billed = TRUE AND (payment_status IS NULL OR payment_status != 'paid') THEN final_cost ELSE 0 END) as total_pending
    FROM bookings";
$financeResult = executeQuery($financeQuery, [], '');
$financeStats = ($financeResult && $row = $financeResult->fetch_assoc()) ? $row : ['total_received' => 0, 'total_pending' => 0];

// Fetch all bookings with related data
$bookingsQuery = "SELECT b.*, v.make, v.model, v.license_plate, u.name as customer_name, u.email as customer_email, b.payment_status,
                        m.id as mechanic_id, mu.name as mechanic_name,
                        pd.driver_name,
                        pd.pickup_location_name,
                        pd.parking_info,
                        pd.address as pickup_address
                  FROM bookings b
                  LEFT JOIN users u ON b.user_id = u.id
                  LEFT JOIN vehicles v ON b.vehicle_id = v.id
                  LEFT JOIN mechanics m ON b.mechanic_id = m.id
                  LEFT JOIN users mu ON m.user_id = mu.id
                  LEFT JOIN pickup_delivery pd ON b.id = pd.booking_id AND pd.type = 'pickup'
                  ORDER BY b.created_at DESC
                  LIMIT 50";
$bookingsResult = executeQuery($bookingsQuery, [], '');
$activeBookings = [];
$bookingHistory = [];

if ($bookingsResult) {
    while ($row = $bookingsResult->fetch_assoc()) {
        // Add 'vehicle' field for consistency with existing code
        $row['vehicle'] = $row['make'] . ' ' . $row['model'];
        if (in_array($row['status'], ['completed', 'delivered', 'cancelled', 'ready_for_delivery'])) {
            $bookingHistory[] = $row;
        } else {
            $activeBookings[] = $row;
        }
    }
}

// Fetch all mechanics for dropdown
$mechanicsQuery = "SELECT m.id, u.name FROM mechanics m INNER JOIN users u ON m.user_id = u.id WHERE m.is_available = TRUE";
$mechanicsResult = executeQuery($mechanicsQuery, [], '');
$mechanics = [];
if ($mechanicsResult) {
    while ($row = $mechanicsResult->fetch_assoc()) {
        $mechanics[] = $row;
    }
}

// Fetch all available drivers for dropdown
$driversQuery = "SELECT u.id, u.name FROM drivers d JOIN users u ON d.user_id = u.id WHERE d.is_available = TRUE ORDER BY u.name";
$driversResult = executeQuery($driversQuery, [], '');
$drivers = [];
if ($driversResult) {
    while ($row = $driversResult->fetch_assoc()) {
        $drivers[] = $row;
    }
}

// Handle Assignments
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDbConnection();
    
    if (isset($_POST['action']) && $_POST['action'] === 'assign_mechanic') {
        $booking_id = intval($_POST['booking_id']);
        $mechanic_id = intval($_POST['mechanic_id']);
        
        $conn->begin_transaction();
        try {
            // Update Booking
            $stmt = $conn->prepare("UPDATE bookings SET mechanic_id = ?, status = 'confirmed' WHERE id = ?");
            $stmt->bind_param("ii", $mechanic_id, $booking_id);
            $stmt->execute();

            // Mark Mechanic as Busy
            $stmt = $conn->prepare("UPDATE mechanics SET is_available = FALSE WHERE id = ?");
            $stmt->bind_param("i", $mechanic_id);
            $stmt->execute();
            
            // Get mechanic user_id and booking data for notification
            $mechanicRes = $conn->query("SELECT user_id FROM mechanics WHERE id = $mechanic_id");
            $customerData = $conn->query("SELECT b.user_id, b.booking_number, b.service_type, u.name as customer_name 
                                          FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.id = $booking_id");
            $bookingRow = $customerData->fetch_assoc();
            
            if ($mechanicRow = $mechanicRes->fetch_assoc()) {
                // Notify assigned mechanic
                notifyWorker(
                    $mechanicRow['user_id'],
                    "🔧 New Job Assigned",
                    "You have been assigned to service booking #{$bookingRow['booking_number']} ({$bookingRow['service_type']}). Check your dashboard for details.",
                    'assignment',
                    'mechanic_dashboard.php?tab=jobs'
                );
                
                // Notify all other available mechanics about the new job opportunity
                $allMechanicsQuery = "SELECT user_id FROM mechanics WHERE is_available = TRUE AND id != $mechanic_id";
                $allMechanicsRes = $conn->query($allMechanicsQuery);
                while ($mRow = $allMechanicsRes->fetch_assoc()) {
                    notifyUser(
                        $mRow['user_id'],
                        "🔧 New Job Available",
                        "A new {$bookingRow['service_type']} service job has been confirmed. Check for available assignments.",
                        'assignment',
                        'mechanic_dashboard.php?tab=jobs'
                    );
                }
            }

            // Notify customer: service confirmed + mechanic assigned
            if ($bookingRow) {
                notifyCustomer(
                    $bookingRow['user_id'],
                    "✅ Service Confirmed",
                    "Your booking #{$bookingRow['booking_number']} ({$bookingRow['service_type']}) has been confirmed and a mechanic has been assigned. Work will begin soon!",
                    'booking',
                    'track_service.php'
                );
            }

            $conn->commit();
            $successMessage = "Mechanic assigned successfully!";
        } catch (Exception $e) {
            $conn->rollback();
        }
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'assign_driver') {
        $booking_id = intval($_POST['booking_id']);
        $pd_id = intval($_POST['pd_id']);
        $driver_user_id = intval($_POST['driver_user_id']);
        
        $conn->begin_transaction();
        try {
            // Get driver details
            $dStmt = $conn->prepare("SELECT name, phone FROM users WHERE id = ?");
            $dStmt->bind_param("i", $driver_user_id);
            $dStmt->execute();
            $dRes = $dStmt->get_result()->fetch_assoc();
            
            // Get booking and pickup/delivery type details
            $pdTypeStmt = $conn->prepare("SELECT pd.type, b.user_id, b.booking_number, b.service_type 
                                          FROM pickup_delivery pd 
                                          JOIN bookings b ON pd.booking_id = b.id 
                                          WHERE pd.id = ?");
            $pdTypeStmt->bind_param("i", $pd_id);
            $pdTypeStmt->execute();
            $pdInfo = $pdTypeStmt->get_result()->fetch_assoc();
            $pdType = $pdInfo['type'] ?? 'pickup'; // 'pickup' or 'delivery'
            
            // Assign Driver to SPECIFIC task (using pd_id)
            $stmt = $conn->prepare("UPDATE pickup_delivery SET driver_user_id = ?, driver_name = ?, driver_phone = ?, status = 'scheduled' WHERE id = ?");
            $stmt->bind_param("issi", $driver_user_id, $dRes['name'], $dRes['phone'], $pd_id);
            $stmt->execute();

            // Mark Driver as Busy
            $stmt = $conn->prepare("UPDATE drivers SET is_available = FALSE WHERE user_id = ?");
            $stmt->bind_param("i", $driver_user_id);
            $stmt->execute();
            
            // Notify driver with appropriate message based on type
            if ($pdType === 'pickup') {
                notifyWorker(
                    $driver_user_id,
                    "🚗 Pickup Job Assigned",
                    "You have been assigned to pick up vehicle for booking #{$pdInfo['booking_number']} ({$pdInfo['service_type']}). Check your dashboard for address details.",
                    'assignment',
                    'driver_dashboard.php?tab=jobs'
                );
                // Notify customer: pickup driver assigned
                if ($pdInfo['user_id']) {
                    notifyCustomer(
                        $pdInfo['user_id'],
                        "🚗 Driver Assigned for Pickup",
                        "A driver ({$dRes['name']}) has been assigned to pick up your vehicle. Please have your vehicle ready.",
                        'pickup',
                        'track_service.php'
                    );
                }
            } else {
                notifyWorker(
                    $driver_user_id,
                    "🚚 Delivery Job Assigned",
                    "You have been assigned to deliver vehicle for booking #{$pdInfo['booking_number']}. Check your dashboard for address details.",
                    'assignment',
                    'driver_dashboard.php?tab=jobs'
                );
                // Notify customer: return driver assigned
                if ($pdInfo['user_id']) {
                    notifyCustomer(
                        $pdInfo['user_id'],
                        "🚚 Return Driver Assigned",
                        "Your serviced vehicle is on the way! Driver {$dRes['name']} is delivering your vehicle back to you.",
                        'delivery',
                        'track_service.php'
                    );
                }
            }

            $conn->commit();
            $successMessage = "Driver assigned successfully!";
        } catch (Exception $e) {
            $conn->rollback();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="page-content">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <div class="text-[9px] font-black uppercase text-gray-900 tracking-widest leading-none">Manage Bookings</div>
                        <p class="text-[7px] text-muted italic mt-1 font-medium">View and manage all service requests, assignments, and statuses.</p>
                    </div>
                    <a href="admin_income_report.php" class="btn btn-primary flex items-center gap-2 px-6 py-2.5 rounded-xl shadow-lg shadow-primary/20 transition-all hover:-translate-y-0.5">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                        <span class="font-bold text-sm tracking-wide">Generate Income Report</span>
                    </a>
                </div>

                <?php if (isset($successMessage) && $successMessage): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                        <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                <?php endif; ?>


                <!-- Admin Stats -->
                <div class="grid gap-4 mb-8" style="grid-template-columns: repeat(6, 1fr);">
                    <div class="card p-4">
                         <div class="flex justify-between">
                            <div>
                                <span class="text-muted text-sm font-medium">Pending Requests</span>
                                <div class="flex items-end gap-2 mt-1">
                                    <span class="text-3xl font-bold"><?php echo $pendingCount; ?></span>
                                </div>
                            </div>
                            <span class="text-warning text-xl"><i class="fa-solid fa-clipboard-list"></i></span>
                         </div>
                    </div>
                    <div class="card p-4">
                         <div class="flex justify-between">
                            <div>
                                <span class="text-muted text-sm font-medium">In Progress</span>
                                <div class="flex items-end gap-2 mt-1">
                                    <span class="text-3xl font-bold"><?php echo $inProgressCount; ?></span>
                                </div>
                            </div>
                            <span class="text-info text-xl"><i class="fa-solid fa-screwdriver-wrench"></i></span>
                         </div>
                    </div>
                    <div class="card p-4">
                         <div class="flex justify-between">
                            <div>
                                <span class="text-muted text-sm font-medium">Completed Today</span>
                                <div class="flex items-end gap-2 mt-1">
                                    <span class="text-3xl font-bold"><?php echo $completedToday; ?></span>
                                </div>
                            </div>
                            <span class="text-success text-xl"><i class="fa-solid fa-circle-check"></i></span>
                         </div>
                    </div>
                     <div class="card p-4 bg-primary text-white border-none">
                          <div class="flex justify-between">
                             <div>
                                 <span class="opacity-80 text-sm font-medium">Active Workers</span>
                                 <div class="flex items-end gap-2 mt-1">
                                     <span class="text-3xl font-bold"><?php echo $activeWorkers; ?></span>
                                 </div>
                             </div>
                             <span class="opacity-50 text-xl"><i class="fa-solid fa-user-check"></i></span>
                          </div>
                     </div>
                     <div class="card p-4 border-l-4 border-success">
                          <div class="flex justify-between">
                             <div>
                                 <span class="text-muted text-[10px] font-bold uppercase tracking-widest">Received</span>
                                 <div class="flex items-end gap-2 mt-1">
                                     <span class="text-2xl font-bold text-success">₹<?php echo number_format($financeStats['total_received'] ?? 0); ?></span>
                                 </div>
                             </div>
                             <span class="text-success text-xl opacity-50"><i class="fa-solid fa-money-bill-wave"></i></span>
                          </div>
                     </div>
                     <div class="card p-4 border-l-4 border-warning">
                          <div class="flex justify-between">
                             <div>
                                 <span class="text-muted text-[10px] font-bold uppercase tracking-widest">Pending</span>
                                 <div class="flex items-end gap-2 mt-1">
                                     <span class="text-2xl font-bold text-warning">₹<?php echo number_format($financeStats['total_pending'] ?? 0); ?></span>
                                 </div>
                             </div>
                             <span class="text-warning text-xl opacity-50"><i class="fa-solid fa-clock-rotate-left"></i></span>
                          </div>
                     </div>
                </div>

                <!-- Filters -->
                <div class="flex justify-between items-center mb-8">
                    <div id="resultsCounter" class="text-xs font-bold text-muted uppercase tracking-widest bg-gray-50 px-4 py-2 rounded-lg border border-gray-100">
                        <!-- Results count will appear here -->
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-[7px] font-black text-muted uppercase tracking-widest whitespace-nowrap">Filter By:</span>
                        <div class="relative inline-block">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none text-muted text-xs">
                                <i class="fa-solid fa-chevron-down"></i>
                            </div>
                            <select id="statusFilter" class="form-control pl-10 pr-4 py-2 text-xs font-bold rounded-xl border-gray-200 focus:border-primary transition-all shadow-sm appearance-none bg-white" style="width: 160px; cursor: pointer;">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="in_progress">In Progress</option>
                                <option value="ready_for_delivery">Ready for Delivery</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Active Bookings -->
                <div class="mb-3">
                    <div class="text-[7px] font-black uppercase text-gray-900 tracking-widest leading-none">Active Bookings</div>
                    <p class="text-[7px] text-muted italic mt-0.5 font-medium">Manage ongoing services and assignments.</p>
                </div>
                <div class="card mb-8 no-hover" style="padding: 0; overflow: hidden;">
                     <div style="overflow-x: auto;">
                        <table class="w-full text-left" style="min-width: 800px;">
                            <thead style="background: #F8FAFC; border-bottom: 1px solid var(--border);">
                                <tr>
                                    <th class="p-3 text-[6px] font-black text-gray-900 uppercase tracking-widest leading-none">Booking ID</th>
                                    <th class="p-3 text-[6px] font-black text-gray-900 uppercase tracking-widest leading-none">Customer & Vehicle</th>
                                    <th class="p-3 text-[6px] font-black text-gray-900 uppercase tracking-widest leading-none">Service</th>
                                    <th class="p-3 text-[6px] font-black text-gray-900 uppercase tracking-widest leading-none">Date</th>
                                    <th class="p-3 text-[6px] font-black text-gray-900 uppercase tracking-widest text-center leading-none">Status</th>
                                    <th class="p-3 text-[6px] font-black text-gray-900 uppercase tracking-widest leading-none">Mechanic Assigned</th>
                                    <th class="p-3 text-[6px] font-black text-gray-900 uppercase tracking-widest text-center leading-none">Payment</th>
                                    <th class="p-3 text-[6px] font-black text-gray-900 uppercase tracking-widest leading-none">Logistics Summary</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm">
                                <?php if (empty($activeBookings)): ?>
                                    <tr>
                                        <td colspan="8" class="p-3 text-center text-muted italic text-[11px]">No active bookings under management.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($activeBookings as $booking): ?>
                                        <?php
                                        $badgeClass = 'badge-info';
                                        if ($booking['status'] === 'pending') $badgeClass = 'badge-warning';
                                        elseif ($booking['status'] === 'in_progress') $badgeClass = 'badge-info';
                                        elseif ($booking['status'] === 'ready_for_delivery') $badgeClass = 'badge-primary';
                                        ?>
                                         <tr style="border-bottom: 1px solid var(--border);" data-status="<?php echo strtolower($booking['status']); ?>">
                                            <td class="p-3 font-mono text-[11px] font-bold text-gray-400">#<?php echo htmlspecialchars($booking['booking_number']); ?></td>
                                            <td class="p-3" data-customer="<?php echo htmlspecialchars($booking['customer_name'] ?? ''); ?>" data-vehicle="<?php echo htmlspecialchars($booking['vehicle'] ?? ''); ?>" data-plate="<?php echo htmlspecialchars($booking['license_plate'] ?? ''); ?>">
                                                <div class="font-bold text-gray-900 border-l-2 border-primary/20 pl-2"><?php echo htmlspecialchars($booking['customer_name'] ?? 'Unknown'); ?></div>
                                                <div class="text-[11px] text-muted font-medium pl-2"><?php echo htmlspecialchars($booking['vehicle'] ?? 'N/A'); ?></div>
                                                <div class="text-[9px] text-primary font-black tracking-widest pl-2 mt-0.5"><?php echo htmlspecialchars($booking['license_plate'] ?? 'N/A'); ?></div>
                                            </td>
                                            <td class="p-3">
                                                <div class="text-[11px] font-bold text-gray-700"><?php echo htmlspecialchars($booking['service_type']); ?></div>
                                            </td>
                                            <td class="p-3">
                                                <div class="font-bold text-[11px] text-gray-600"><?php echo $booking['preferred_date'] ? date('M d, Y', strtotime($booking['preferred_date'])) : 'N/A'; ?></div>
                                            </td>
                                            <td class="p-3 text-center">
                                                <span class="badge <?php echo $badgeClass; ?> text-[9px] font-black uppercase px-2 py-0.5 rounded-md"><?php echo formatStatusLabel($booking['status']); ?></span>
                                            </td>
                                             <td class="p-3">
                                                <!-- Mechanic Assigned -->
                                                <?php if ($booking['mechanic_id']): ?>
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center text-primary text-[10px] font-black">
                                                            <?php echo strtoupper(substr($booking['mechanic_name'], 0, 1)); ?>
                                                        </div>
                                                        <span class="text-[11px] font-black text-gray-800"><?php echo htmlspecialchars($booking['mechanic_name']); ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <form method="POST" class="flex items-center gap-1">
                                                        <input type="hidden" name="action" value="assign_mechanic">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        <select name="mechanic_id" class="text-[10px] py-1 border-gray-200 rounded bg-gray-50 focus:bg-white" required>
                                                            <option value="">Assign Mechanic</option>
                                                            <?php foreach($mechanics as $m): ?>
                                                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" class="p-1 text-primary hover:text-primary-dark transition-colors">
                                                            <i class="fa-solid fa-circle-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                             <td class="p-3 text-center">
                                                <div class="text-[9px] font-black leading-tight border border-gray-100 rounded-lg p-2 bg-gray-50/30 flex flex-col items-center gap-1.5">
                                                    <?php if ($booking['payment_status'] === 'paid'): ?>
                                                        <span class="text-success flex items-center justify-center gap-1"><i class="fa-solid fa-check-circle"></i> PAID</span>
                                                        <span class="bg-green-100 text-green-800 px-1.5 py-0.5 rounded text-[7px] uppercase tracking-wider"><?php echo htmlspecialchars($booking['payment_method'] ?? 'Online'); ?></span>
                                                    <?php elseif ($booking['is_billed']): ?>
                                                        <span class="text-warning flex flex-col items-center gap-0.5">
                                                            <?php if ((float)($booking['final_cost'] ?? 0) > 0): ?>
                                                                <span><i class="fa-solid fa-clock-rotate-left"></i> UNPAID</span>
                                                            <?php endif; ?>
                                                            <span class="text-gray-900">₹<?php echo number_format((float)($booking['final_cost'] ?? 0)); ?></span>
                                                        </span>
                                                        <?php if ((float)($booking['final_cost'] ?? 0) > 0): ?>
                                                        <button onclick="markAsCashPaid(<?php echo $booking['id']; ?>)" class="mt-1 px-2 py-1 bg-white hover:bg-green-50 text-green-600 border border-green-200 hover:border-green-300 rounded shadow-sm transition-all flex items-center justify-center gap-1 w-full" title="Mark as Paid via Cash">
                                                            <i class="fa-solid fa-money-bill-wave"></i> Cash
                                                        </button>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted italic opacity-50">NOT BILLED</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="p-3">
                                                <?php 
                                                    $pdQuery = "SELECT id, type, driver_name, status FROM pickup_delivery WHERE booking_id = ?";
                                                    $pdRes = executeQuery($pdQuery, [$booking['id']], 'i');
                                                    $pdItems = [];
                                                    if ($pdRes) {
                                                        while ($pdRow = $pdRes->fetch_assoc()) {
                                                            $pdItems[] = $pdRow;
                                                        }
                                                    }
                                                    
                                                    $pickup = null; $delivery = null;
                                                    foreach($pdItems as $item) {
                                                        if($item['type'] == 'pickup') $pickup = $item;
                                                        if($item['type'] == 'delivery') $delivery = $item;
                                                    }
                                                ?>
                                                <div class="flex flex-col gap-3">
                                                    <!-- Pickup Assignment -->
                                                    <?php if($pickup): ?>
                                                        <div class="flex items-center gap-2 min-w-[180px]">
                                                            <span class="text-[9px] font-black uppercase text-muted w-14 shrink-0">Pickup:</span>
                                                            <div class="flex-1 flex items-center justify-end gap-1">
                                                                <?php if ($pickup['driver_name']): ?>
                                                                    <span class="text-[10px] font-bold text-gray-700 truncate max-w-[80px]" title="<?php echo htmlspecialchars($pickup['driver_name']); ?>"><?php echo htmlspecialchars($pickup['driver_name']); ?></span>
                                                                    <!-- Status Indicator -->
                                                                    <?php if($pickup['status'] === 'completed'): ?>
                                                                        <span class="text-[9px] text-green-600 bg-green-50 px-1 rounded border border-green-100 shrink-0"><i class="fa-solid fa-check"></i> Done</span>
                                                                    <?php elseif($pickup['status'] === 'in_transit'): ?>
                                                                        <span class="text-[9px] text-blue-600 bg-blue-50 px-1 rounded border border-blue-100 shrink-0"><i class="fa-solid fa-truck-fast"></i> En Route</span>
                                                                    <?php elseif($pickup['status'] === 'scheduled'): ?>
                                                                        <span class="text-[9px] text-orange-600 bg-orange-50 px-1 rounded border border-orange-100 shrink-0"><i class="fa-solid fa-clock"></i> Assigned</span>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <form method="POST" class="flex items-center gap-1 w-full justify-end">
                                                                        <input type="hidden" name="action" value="assign_driver">
                                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                                        <input type="hidden" name="pd_id" value="<?php echo $pickup['id']; ?>">
                                                                        <select name="driver_user_id" class="text-[9px] py-0.5 px-1 border-gray-200 rounded bg-gray-50 flex-1 min-w-0" required>
                                                                            <option value="">Assign</option>
                                                                            <?php foreach($drivers as $d): ?>
                                                                                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                        <button type="submit" class="text-primary text-[10px] shrink-0 p-1"><i class="fa-solid fa-check"></i></button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Delivery Assignment -->
                                                    <?php if($delivery): ?>
                                                        <div class="flex items-center gap-2 min-w-[180px]">
                                                            <span class="text-[9px] font-black uppercase text-muted w-14 shrink-0">Delivery:</span>
                                                            <div class="flex-1 flex items-center justify-end gap-1">
                                                                <?php if ($delivery['driver_name']): ?>
                                                                    <span class="text-[10px] font-bold text-gray-700 truncate max-w-[80px]" title="<?php echo htmlspecialchars($delivery['driver_name']); ?>"><?php echo htmlspecialchars($delivery['driver_name']); ?></span>
                                                                    <!-- Status Indicator -->
                                                                    <?php if($delivery['status'] === 'completed'): ?>
                                                                        <span class="text-[9px] text-green-600 bg-green-50 px-1 rounded border border-green-100 shrink-0"><i class="fa-solid fa-check"></i> Done</span>
                                                                    <?php elseif($delivery['status'] === 'in_transit'): ?>
                                                                        <span class="text-[9px] text-blue-600 bg-blue-50 px-1 rounded border border-blue-100 shrink-0"><i class="fa-solid fa-truck-fast"></i> En Route</span>
                                                                    <?php elseif($delivery['status'] === 'scheduled'): ?>
                                                                        <span class="text-[9px] text-orange-600 bg-orange-50 px-1 rounded border border-orange-100 shrink-0"><i class="fa-solid fa-clock"></i> Assigned</span>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <form method="POST" class="flex items-center gap-1 w-full justify-end">
                                                                        <input type="hidden" name="action" value="assign_driver">
                                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                                        <input type="hidden" name="pd_id" value="<?php echo $delivery['id']; ?>">
                                                                        <select name="driver_user_id" class="text-[9px] py-0.5 px-1 border-gray-200 rounded bg-gray-50 flex-1 min-w-0" required>
                                                                            <option value="">Assign</option>
                                                                            <?php foreach($drivers as $d): ?>
                                                                                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                        <button type="submit" class="text-primary text-[10px] shrink-0 p-1"><i class="fa-solid fa-check"></i></button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                     </div>
                </div>

                <!-- Booking History -->
                <div class="mb-3 pt-3 border-t border-gray-100">
                    <div class="text-[7px] font-black uppercase text-gray-900 tracking-widest leading-none">Booking History</div>
                    <p class="text-[7px] text-muted italic mt-0.5 font-medium">Completed, delivered, or cancelled services.</p>
                </div>
                <div class="card no-hover" style="padding: 0; overflow: hidden; opacity: 0.85;">
                     <div style="overflow-x: auto;">
                        <table class="w-full text-left" style="min-width: 800px;">
                            <thead style="background: #F8FAFC; border-bottom: 1px solid var(--border);">
                                <tr>
                                    <th class="p-3 text-[6px] font-black text-gray-900 uppercase tracking-widest leading-none">Booking ID</th>
                                    <th class="p-3 text-[6px] font-black text-gray-900 uppercase tracking-widest leading-none">Customer & Vehicle</th>
                                    <th class="p-3 text-[6px] font-black text-gray-900 uppercase tracking-widest leading-none">Service</th>
                                    <th class="p-3 text-[6px] font-black text-gray-900 uppercase tracking-widest leading-none">Date</th>
                                    <th class="p-3 text-[6px] font-black text-gray-900 uppercase tracking-widest text-center leading-none">Status</th>
                                    <th class="p-3 text-[6px] font-black text-gray-900 uppercase tracking-widest leading-none">Mechanic Assigned</th>
                                    <th class="p-3 text-[6px] font-black text-gray-900 uppercase tracking-widest text-center leading-none">Payment</th>
                                    <th class="p-3 text-[6px] font-black text-gray-900 uppercase tracking-widest leading-none">Logistics Summary</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm">
                                <?php if (empty($bookingHistory)): ?>
                                    <tr>
                                        <td colspan="8" class="p-3 text-center text-muted text-[11px]">No history found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($bookingHistory as $booking): ?>
                                        <?php
                                        $badgeClass = 'badge-success';
                                        if ($booking['status'] === 'cancelled') $badgeClass = 'badge-danger';
                                        ?>
                                         <tr style="border-bottom: 1px solid var(--border); background: #fcfcfc;" data-status="<?php echo strtolower($booking['status']); ?>">
                                            <td class="p-3 font-mono text-[11px] font-bold text-gray-400">#<?php echo htmlspecialchars($booking['booking_number']); ?></td>
                                            <td class="p-3" data-customer="<?php echo htmlspecialchars($booking['customer_name'] ?? ''); ?>" data-vehicle="<?php echo htmlspecialchars($booking['vehicle'] ?? ''); ?>" data-plate="<?php echo htmlspecialchars($booking['license_plate'] ?? ''); ?>">
                                                <div class="font-bold text-gray-900 border-l-2 border-gray-200 pl-2"><?php echo htmlspecialchars($booking['customer_name'] ?? 'Unknown'); ?></div>
                                                <div class="text-[11px] text-muted font-medium pl-2"><?php echo htmlspecialchars($booking['vehicle'] ?? 'N/A'); ?></div>
                                                <div class="text-[9px] text-primary font-black tracking-widest pl-2 mt-0.5"><?php echo htmlspecialchars($booking['license_plate'] ?? 'N/A'); ?></div>
                                            </td>
                                            <td class="p-3">
                                                <div class="text-[11px] font-bold text-gray-500"><?php echo htmlspecialchars($booking['service_type']); ?></div>
                                            </td>
                                            <td class="p-3">
                                                <div class="text-[11px] font-medium text-gray-500"><?php echo $booking['preferred_date'] ? date('M d, Y', strtotime($booking['preferred_date'])) : 'N/A'; ?></div>
                                            </td>
                                            <td class="p-3 text-center"><span class="badge <?php echo $badgeClass; ?> text-[9px] font-black uppercase px-2 py-0.5 rounded-md"><?php echo formatStatusLabel($booking['status']); ?></span></td>
                                             <td class="p-3">
                                                <div class="flex items-center gap-2 grayscale opacity-70">
                                                    <div class="w-5 h-5 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 text-[9px] font-black border border-gray-200">
                                                        <?php echo $booking['mechanic_name'] ? strtoupper(substr($booking['mechanic_name'], 0, 1)) : '?'; ?>
                                                    </div>
                                                    <span class="text-[10px] font-bold text-gray-500 uppercase tracking-tight"><?php echo $booking['mechanic_name'] ? htmlspecialchars($booking['mechanic_name']) : 'N/A'; ?></span>
                                                </div>
                                            </td>
                                             <td class="p-3 text-center">
                                                <div class="text-[9px] font-black leading-tight border border-gray-100 rounded-lg p-2 bg-gray-50/30 flex flex-col items-center gap-1.5">
                                                    <?php if ($booking['payment_status'] === 'paid'): ?>
                                                        <span class="text-success flex items-center justify-center gap-1"><i class="fa-solid fa-check-circle"></i> PAID</span>
                                                        <span class="bg-green-100 text-green-800 px-1.5 py-0.5 rounded text-[7px] uppercase tracking-wider"><?php echo htmlspecialchars($booking['payment_method'] ?? 'Online'); ?></span>
                                                    <?php elseif ($booking['is_billed']): ?>
                                                        <span class="text-warning flex flex-col items-center gap-0.5">
                                                            <?php if ((float)($booking['final_cost'] ?? 0) > 0): ?>
                                                                <span><i class="fa-solid fa-clock-rotate-left"></i> UNPAID</span>
                                                            <?php endif; ?>
                                                            <span class="text-gray-900">₹<?php echo number_format((float)($booking['final_cost'] ?? 0)); ?></span>
                                                        </span>
                                                        <?php if ((float)($booking['final_cost'] ?? 0) > 0): ?>
                                                        <button onclick="markAsCashPaid(<?php echo $booking['id']; ?>)" class="mt-1 px-2 py-1 bg-white hover:bg-green-50 text-green-600 border border-green-200 hover:border-green-300 rounded shadow-sm transition-all flex items-center justify-center gap-1 w-full" title="Mark as Paid via Cash">
                                                            <i class="fa-solid fa-money-bill-wave"></i> Cash
                                                        </button>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted italic opacity-50">NOT BILLED</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                             <td class="p-3 text-xs">
                                                 <div class="flex gap-4">
                                                     <?php 
                                                        $pdQuery = "SELECT * FROM pickup_delivery WHERE booking_id = ?";
                                                        $pdResult = executeQuery($pdQuery, [$booking['id']], 'i');
                                                        if ($pdResult): 
                                                            while($task = $pdResult->fetch_assoc()): ?>
                                                                <div class="flex flex-col gap-0.5">
                                                                    <span class="text-[8px] font-black uppercase text-gray-400 tracking-tighter leading-none"><?php echo $task['type']; ?></span>
                                                                    <span class="text-[10px] font-bold text-gray-500 leading-tight"><?php echo $task['driver_name'] ? htmlspecialchars($task['driver_name']) : 'N/A'; ?></span>
                                                                </div>
                                                            <?php endwhile;
                                                        endif;
                                                     ?>
                                                 </div>
                                             </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                </div>

            </div>
        </main>
    </div>

    <!-- Financial Details Modal -->
    <div id="financialModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-100">
                <div class="bg-white px-8 pt-8 pb-4">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-xl font-black text-gray-900" id="modal-title">Financial Audit</h3>
                            <p class="text-xs text-muted" id="modal-booking-id">Booking #---</p>
                        </div>
                        <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <i class="fa-solid fa-xmark text-xl"></i>
                        </button>
                    </div>
                    
                    <div id="financialContent" class="space-y-4">
                        <!-- Content will be loaded via AJAX -->
                        <div class="flex flex-col items-center justify-center py-12">
                            <i class="fa-solid fa-circle-notch fa-spin text-primary text-3xl mb-3"></i>
                            <p class="text-xs font-bold text-muted uppercase tracking-widest">Calculating Charges...</p>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-8 py-4 flex justify-between items-center">
                    <p class="text-[10px] text-muted italic">All charges are synchronized in real-time with worker inputs.</p>
                    <button type="button" onclick="closeModal()" class="btn btn-white text-xs font-black uppercase tracking-widest px-6 py-2 rounded-xl">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Status Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const statusFilter = document.getElementById('statusFilter');
            const resultsCounter = document.getElementById('resultsCounter');
            
            // Function to apply status filter
            function applyFilter() {
                const selectedStatus = statusFilter ? statusFilter.value.toLowerCase() : '';
                
                // Get all table rows from both Active and History tables
                const allRows = document.querySelectorAll('table tbody tr');
                let visibleCount = 0;
                
                allRows.forEach((row) => {
                    // Get row status from data-status attribute
                    const rowStatusAttribute = row.getAttribute('data-status');
                    
                    // Skip rows that don't have a status (like empty state messages or headers)
                    if (!rowStatusAttribute) {
                        return;
                    }
                    
                    const rowStatus = rowStatusAttribute.toLowerCase();
                    
                    // Check if status matches (or if "All Statuses" is selected)
                    const shouldShow = selectedStatus === '' || rowStatus === selectedStatus;
                    
                    // Show or hide row
                    row.style.display = shouldShow ? '' : 'none';
                    
                    if (shouldShow) {
                        visibleCount++;
                    }
                });
                
                // Update results counter
                if (resultsCounter) {
                    if (selectedStatus) {
                        resultsCounter.innerHTML = `<i class="fa-solid fa-filter text-primary"></i> <span class="font-bold">${visibleCount}</span> booking${visibleCount !== 1 ? 's' : ''} found`;
                    } else {
                        resultsCounter.innerHTML = '';
                    }
                }
            }
            
            // Add event listener to status filter
            if (statusFilter) {
                statusFilter.addEventListener('change', applyFilter);
                
                // Run filter on page load in case dropdown has a pre-selected value
                if (statusFilter.value) {
                    applyFilter();
                }
            }
        });

        function viewFinancials(bookingId) {
            const modal = document.getElementById('financialModal');
            const content = document.getElementById('financialContent');
            const idLabel = document.getElementById('modal-booking-id');
            
            modal.classList.remove('hidden');
            idLabel.innerText = 'Refetching details for Booking ID: #' + bookingId;
            
            // Fetch financial data via AJAX
            fetch(`ajax/get_financial_details.php?id=${bookingId}`)
                .then(response => response.text())
                .then(html => {
                    content.innerHTML = html;
                })
                .catch(err => {
                    content.innerHTML = `<div class="p-6 text-center text-red-500 text-xs">Error loading financial details.</div>`;
                });
        }

        function closeModal() {
            document.getElementById('financialModal').classList.add('hidden');
            document.getElementById('financialContent').innerHTML = `
                <div class="flex flex-col items-center justify-center py-12">
                    <i class="fa-solid fa-circle-notch fa-spin text-primary text-3xl mb-3"></i>
                    <p class="text-xs font-bold text-muted uppercase tracking-widest">Calculating Charges...</p>
                </div>
            `;
        }

        function markAsCashPaid(bookingId) {
            if (confirm(`Are you sure you want to mark Booking #${bookingId} as paid via Cash? This action cannot be undone.`)) {
                
                const formData = new FormData();
                formData.append('booking_id', bookingId);
                
                fetch('ajax/admin_mark_cash_paid.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An unexpected error occurred while processing the request.');
                });
            }
        }
    </script>
</body>
</html>
