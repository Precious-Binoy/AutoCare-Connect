<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

// Require admin access
requireAdmin();

// Set current page for navigation
$current_page = 'admin_bookings.php';
$page_title = 'Manage Bookings';

// Fetch statistics
$pendingQuery = "SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'";
$pendingResult = executeQuery($pendingQuery, [], '');
$pendingCount = ($pendingResult && $row = $pendingResult->fetch_assoc()) ? $row['count'] : 0;

$inProgressQuery = "SELECT COUNT(*) as count FROM bookings WHERE status = 'in_progress'";
$inProgressResult = executeQuery($inProgressQuery, [], '');
$inProgressCount = ($inProgressResult && $row = $inProgressResult->fetch_assoc()) ? $row['count'] : 0;

$completedTodayQuery = "SELECT COUNT(*) as count FROM bookings WHERE (status = 'completed' OR status = 'delivered') AND DATE(completion_date) = CURDATE()";
$completedTodayResult = executeQuery($completedTodayQuery, [], '');
$completedToday = ($completedTodayResult && $row = $completedTodayResult->fetch_assoc()) ? $row['count'] : 0;

$activeDriversQuery = "SELECT COUNT(DISTINCT driver_user_id) as count FROM pickup_delivery WHERE status IN ('in_transit', 'scheduled')";
$activeDriversResult = executeQuery($activeDriversQuery, [], '');
$activeDrivers = ($activeDriversResult && $row = $activeDriversResult->fetch_assoc()) ? $row['count'] : 0;

// Fetch all bookings with related data
$bookingsQuery = "SELECT 
                    b.id,
                    b.booking_number,
                    b.status,
                    b.preferred_date,
                    b.service_type,
                    b.mechanic_fee,
                    b.final_cost as total_bill,
                    u.name as customer_name,
                    u.email as customer_email,
                    CONCAT(v.make, ' ', v.model) as vehicle,
                    v.license_plate,
                    m.id as mechanic_id,
                    mu.name as mechanic_name,
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
        if (in_array($row['status'], ['completed', 'delivered', 'cancelled'])) {
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
            
            // Assign Driver to SPECIFIC task (using pd_id)
            $stmt = $conn->prepare("UPDATE pickup_delivery SET driver_user_id = ?, driver_name = ?, driver_phone = ?, status = 'scheduled' WHERE id = ?");
            $stmt->bind_param("issi", $driver_user_id, $dRes['name'], $dRes['phone'], $pd_id);
            $stmt->execute();

            // Mark Driver as Busy (keep this logic if drivers are limited to one task at a time)
            $stmt = $conn->prepare("UPDATE drivers SET is_available = FALSE WHERE user_id = ?");
            $stmt->bind_param("i", $driver_user_id);
            $stmt->execute();

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
                        <h1 class="text-2xl font-bold">Manage Bookings</h1>
                        <p class="text-muted">View and manage all service requests, assignments, and statuses.</p>
                    </div>
                </div>

                <?php if (isset($successMessage) && $successMessage): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                        <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                <?php endif; ?>

                <!-- Admin Stats -->
                <div class="grid gap-4 mb-8" style="grid-template-columns: repeat(4, 1fr);">
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
                                     <span class="text-3xl font-bold"><?php echo $activeDrivers; ?></span>
                                 </div>
                             </div>
                             <span class="opacity-50 text-xl"><i class="fa-solid fa-truck"></i></span>
                          </div>
                     </div>
                </div>

                <!-- Filters -->
                <div class="flex justify-between items-center mb-4">
                    <div class="text-sm text-muted">
                        <!-- Empty space for balance -->
                    </div>
                    <div class="flex items-center gap-3">
                        <div id="resultsCounter" class="text-sm text-muted font-medium">
                            <!-- Results count will appear here -->
                        </div>
                        <select id="statusFilter" class="form-control" style="width: 180px;">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="ready_for_delivery">Ready for Delivery</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>

                <!-- Active Bookings -->
                <div class="mb-4">
                    <h2 class="text-xl font-bold mb-2">Active Bookings</h2>
                    <p class="text-sm text-muted">Manage ongoing services and assignments.</p>
                </div>
                <div class="card mb-8" style="padding: 0; overflow: hidden;">
                     <div style="overflow-x: auto;">
                        <table class="w-full text-left" style="min-width: 800px;">
                            <thead style="background: #F8FAFC; border-bottom: 1px solid var(--border);">
                                <tr>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Booking ID</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Customer & Vehicle</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Service</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Date</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Status</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Assignee Info</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Logistics Summary</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm">
                                <?php if (empty($activeBookings)): ?>
                                    <tr>
                                        <td colspan="7" class="p-4 text-center text-muted italic">No active bookings under management.</td>
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
                                            <td class="p-4 font-bold">#<?php echo htmlspecialchars($booking['booking_number']); ?></td>
                                            <td class="p-4" data-customer="<?php echo htmlspecialchars($booking['customer_name'] ?? ''); ?>" data-vehicle="<?php echo htmlspecialchars($booking['vehicle'] ?? ''); ?>" data-plate="<?php echo htmlspecialchars($booking['license_plate'] ?? ''); ?>">
                                                <div class="font-bold"><?php echo htmlspecialchars($booking['customer_name'] ?? 'Unknown'); ?></div>
                                                <div class="text-xs text-muted"><?php echo htmlspecialchars($booking['vehicle'] ?? 'N/A'); ?></div>
                                                <div class="text-[10px] text-primary font-mono font-bold"><?php echo htmlspecialchars($booking['license_plate'] ?? 'N/A'); ?></div>
                                            </td>
                                            <td class="p-4">
                                                <div class="text-xs"><?php echo htmlspecialchars($booking['service_type']); ?></div>
                                            </td>
                                            <td class="p-4">
                                                <div class="font-bold text-gray-700"><?php echo $booking['preferred_date'] ? date('M d, Y', strtotime($booking['preferred_date'])) : 'N/A'; ?></div>
                                            </td>
                                            <td class="p-4">
                                                <span class="badge <?php echo $badgeClass; ?> text-[10px]"><?php echo formatStatusLabel($booking['status']); ?></span>
                                            </td>
                                             <td class="p-4">
                                                <!-- Mechanic Assignment -->
                                                <?php if ($booking['mechanic_id']): ?>
                                                    <div class="flex items-center gap-1.5 text-gray-700">
                                                        <i class="fa-solid fa-screwdriver-wrench text-[10px] text-primary"></i>
                                                        <span class="text-xs font-bold"><?php echo htmlspecialchars($booking['mechanic_name']); ?></span>
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
                                            <td class="p-4">
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
                                                <div class="flex flex-col gap-2">
                                                    <!-- Pickup Assignment -->
                                                    <?php if($pickup): ?>
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-[9px] font-black uppercase text-muted w-12">Pickup:</span>
                                                            <?php if ($pickup['driver_name']): ?>
                                                                <div class="flex items-center gap-1">
                                                                    <span class="text-[10px] font-bold text-gray-700"><?php echo htmlspecialchars($pickup['driver_name']); ?></span>
                                                                    <!-- Status Indicator -->
                                                                    <?php if($pickup['status'] === 'completed'): ?>
                                                                        <span class="text-[9px] text-green-600 bg-green-50 px-1 rounded border border-green-100"><i class="fa-solid fa-check"></i> Done</span>
                                                                    <?php elseif($pickup['status'] === 'in_transit'): ?>
                                                                        <span class="text-[9px] text-blue-600 bg-blue-50 px-1 rounded border border-blue-100"><i class="fa-solid fa-truck-fast"></i> En Route</span>
                                                                    <?php elseif($pickup['status'] === 'scheduled'): ?>
                                                                        <span class="text-[9px] text-orange-600 bg-orange-50 px-1 rounded border border-orange-100"><i class="fa-solid fa-clock"></i> Assigned</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php else: ?>
                                                                <form method="POST" class="flex items-center gap-1">
                                                                    <input type="hidden" name="action" value="assign_driver">
                                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                                    <input type="hidden" name="pd_id" value="<?php echo $pickup['id']; ?>">
                                                                    <select name="driver_user_id" class="text-[9px] py-0.5 border-gray-200 rounded bg-gray-50 max-w-[100px]" required>
                                                                        <option value="">Assign Driver</option>
                                                                        <?php foreach($drivers as $d): ?>
                                                                            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                    <button type="submit" class="text-primary text-[10px]"><i class="fa-solid fa-check"></i></button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Delivery Assignment -->
                                                    <?php if($delivery): ?>
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-[9px] font-black uppercase text-muted w-12">Delivery:</span>
                                                            <?php if ($delivery['driver_name']): ?>
                                                                <div class="flex items-center gap-1">
                                                                    <span class="text-[10px] font-bold text-gray-700"><?php echo htmlspecialchars($delivery['driver_name']); ?></span>
                                                                    <!-- Status Indicator -->
                                                                    <?php if($delivery['status'] === 'completed'): ?>
                                                                        <span class="text-[9px] text-green-600 bg-green-50 px-1 rounded border border-green-100"><i class="fa-solid fa-check"></i> Done</span>
                                                                    <?php elseif($delivery['status'] === 'in_transit'): ?>
                                                                        <span class="text-[9px] text-blue-600 bg-blue-50 px-1 rounded border border-blue-100"><i class="fa-solid fa-truck-fast"></i> En Route</span>
                                                                    <?php elseif($delivery['status'] === 'scheduled'): ?>
                                                                        <span class="text-[9px] text-orange-600 bg-orange-50 px-1 rounded border border-orange-100"><i class="fa-solid fa-clock"></i> Assigned</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php else: ?>
                                                                <form method="POST" class="flex items-center gap-1">
                                                                    <input type="hidden" name="action" value="assign_driver">
                                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                                    <input type="hidden" name="pd_id" value="<?php echo $delivery['id']; ?>">
                                                                    <select name="driver_user_id" class="text-[9px] py-0.5 border-gray-200 rounded bg-gray-50 max-w-[100px]" required>
                                                                        <option value="">Assign Driver</option>
                                                                        <?php foreach($drivers as $d): ?>
                                                                            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                    <button type="submit" class="text-primary text-[10px]"><i class="fa-solid fa-check"></i></button>
                                                                </form>
                                                            <?php endif; ?>
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
                <div class="mb-4 pt-4 border-t border-gray-100">
                    <h2 class="text-xl font-bold mb-2">Booking History</h2>
                    <p class="text-sm text-muted">Completed, delivered, or cancelled services.</p>
                </div>
                <div class="card" style="padding: 0; overflow: hidden; opacity: 0.85;">
                     <div style="overflow-x: auto;">
                        <table class="w-full text-left" style="min-width: 800px;">
                            <thead style="background: #F8FAFC; border-bottom: 1px solid var(--border);">
                                <tr>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Booking ID</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Customer & Vehicle</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Service</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Date/Time</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Status</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Assignee Info</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase" colspan="2">Logistics Summary</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm">
                                <?php if (empty($bookingHistory)): ?>
                                    <tr>
                                        <td colspan="8" class="p-4 text-center text-muted">No history found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($bookingHistory as $booking): ?>
                                        <?php
                                        $badgeClass = 'badge-success';
                                        if ($booking['status'] === 'cancelled') $badgeClass = 'badge-danger';
                                        ?>
                                        <tr style="border-bottom: 1px solid var(--border); background: #fcfcfc;" data-status="<?php echo strtolower($booking['status']); ?>">
                                            <td class="p-4 font-bold text-muted">#<?php echo htmlspecialchars($booking['booking_number']); ?></td>
                                            <td class="p-4" data-customer="<?php echo htmlspecialchars($booking['customer_name'] ?? ''); ?>" data-vehicle="<?php echo htmlspecialchars($booking['vehicle'] ?? ''); ?>" data-plate="<?php echo htmlspecialchars($booking['license_plate'] ?? ''); ?>">
                                                <div class="font-bold"><?php echo htmlspecialchars($booking['customer_name'] ?? 'Unknown'); ?></div>
                                                <div class="text-xs text-muted"><?php echo htmlspecialchars($booking['vehicle'] ?? 'N/A'); ?></div>
                                                <div class="text-[10px] text-primary font-mono font-bold"><?php echo htmlspecialchars($booking['license_plate'] ?? 'N/A'); ?></div>
                                            </td>
                                            <td class="p-4">
                                                <div class="text-xs"><?php echo htmlspecialchars($booking['service_type']); ?></div>
                                            </td>
                                            <td class="p-4">
                                                <div><?php echo $booking['preferred_date'] ? date('M d, Y', strtotime($booking['preferred_date'])) : 'N/A'; ?></div>
                                            </td>
                                            <td class="p-4"><span class="badge <?php echo $badgeClass; ?>"><?php echo formatStatusLabel($booking['status']); ?></span></td>
                                             <td class="p-4">
                                                <div class="text-xs font-bold text-gray-700">
                                                    <i class="fa-solid fa-screwdriver-wrench mr-1"></i> 
                                                    <?php echo $booking['mechanic_name'] ? htmlspecialchars($booking['mechanic_name']) : 'Not Assigned'; ?>
                                                </div>
                                            </td>
                                             <td class="p-4 text-xs" colspan="2">
                                                 <div class="flex gap-4">
                                                     <?php 
                                                        $pdQuery = "SELECT * FROM pickup_delivery WHERE booking_id = ?";
                                                        $pdResult = executeQuery($pdQuery, [$booking['id']], 'i');
                                                        if ($pdResult): 
                                                            while($task = $pdResult->fetch_assoc()): ?>
                                                                <div class="flex flex-col gap-0.5">
                                                                    <span class="text-[9px] font-black uppercase text-gray-400"><?php echo $task['type']; ?></span>
                                                                    <span class="font-bold text-gray-600"><?php echo $task['driver_name'] ? htmlspecialchars($task['driver_name']) : 'N/A'; ?></span>
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
    </script>
</body>
</html>
