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
$pendingCount = $pendingResult ? $pendingResult->fetch_assoc()['count'] : 0;

$inProgressQuery = "SELECT COUNT(*) as count FROM bookings WHERE status = 'in_progress'";
$inProgressResult = executeQuery($inProgressQuery, [], '');
$inProgressCount = $inProgressResult ? $inProgressResult->fetch_assoc()['count'] : 0;

$completedTodayQuery = "SELECT COUNT(*) as count FROM bookings WHERE status = 'completed' AND DATE(completion_date) = CURDATE()";
$completedTodayResult = executeQuery($completedTodayQuery, [], '');
$completedToday = $completedTodayResult ? $completedTodayResult->fetch_assoc()['count'] : 0;

$activeDriversQuery = "SELECT COUNT(DISTINCT driver_name) as count FROM pickup_delivery WHERE status IN ('in_transit', 'scheduled')";
$activeDriversResult = executeQuery($activeDriversQuery, [], '');
$activeDrivers = $activeDriversResult ? $activeDriversResult->fetch_assoc()['count'] : 0;

// Fetch all bookings with related data
$bookingsQuery = "SELECT 
                    b.id,
                    b.booking_number,
                    b.status,
                    b.preferred_date,
                    b.service_type,
                    u.name as customer_name,
                    u.email as customer_email,
                    CONCAT(v.make, ' ', v.model) as vehicle,
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
$bookings = [];
if ($bookingsResult) {
    while ($row = $bookingsResult->fetch_assoc()) {
        $bookings[] = $row;
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
        $driver_user_id = intval($_POST['driver_user_id']);
        
        $conn->begin_transaction();
        try {
            // Get driver details
            $dStmt = $conn->prepare("SELECT name, phone FROM users WHERE id = ?");
            $dStmt->bind_param("i", $driver_user_id);
            $dStmt->execute();
            $dRes = $dStmt->get_result()->fetch_assoc();
            
            // Assign Driver (Update all logistics tasks for this booking)
            $stmt = $conn->prepare("UPDATE pickup_delivery SET driver_user_id = ?, driver_name = ?, driver_phone = ?, status = 'scheduled' WHERE booking_id = ?");
            $stmt->bind_param("issi", $driver_user_id, $dRes['name'], $dRes['phone'], $booking_id);
            $stmt->execute();

            // Mark Driver as Busy
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
                                 <span class="opacity-80 text-sm font-medium">Drivers Available</span>
                                 <div class="flex items-end gap-2 mt-1">
                                     <span class="text-3xl font-bold"><?php echo $activeDrivers; ?></span>
                                 </div>
                             </div>
                             <span class="opacity-50 text-xl"><i class="fa-solid fa-truck"></i></span>
                          </div>
                     </div>
                </div>

                <!-- Filters -->
                <div class="flex justify-between mb-4">
                    <div class="search-bar" style="width: 300px;">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" class="form-control" placeholder="Search by Customer or Booking ID...">
                    </div>
                    <div class="flex gap-2">
                        <select class="form-control" style="width: 150px;">
                            <option>All Statuses</option>
                            <option>Pending</option>
                            <option>In Progress</option>
                            <option>Completed</option>
                        </select>
                        <button class="btn btn-outline btn-icon"><i class="fa-solid fa-filter"></i></button>
                    </div>
                </div>

                <!-- Bookings Table -->
                <div class="card" style="padding: 0; overflow: hidden;">
                     <div style="overflow-x: auto;">
                        <table class="w-full text-left" style="min-width: 800px;">
                            <thead style="background: #F8FAFC; border-bottom: 1px solid var(--border);">
                                <tr>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Booking ID</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Customer & Vehicle</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Service</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Date/Time</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Status</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Mechanic</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Driver Selection</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm">
                                <?php if (empty($bookings)): ?>
                                    <tr>
                                        <td colspan="7" class="p-4 text-center text-muted">No bookings found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($bookings as $booking): ?>
                                        <?php
                                        $badgeClass = 'badge-info';
                                        if ($booking['status'] === 'pending') $badgeClass = 'badge-warning';
                                        elseif ($booking['status'] === 'in_progress') $badgeClass = 'badge-info';
                                        elseif ($booking['status'] === 'completed') $badgeClass = 'badge-success';
                                        elseif ($booking['status'] === 'cancelled') $badgeClass = 'badge-danger';
                                        ?>
                                        <tr style="border-bottom: 1px solid var(--border);">
                                            <td class="p-4 font-bold">#<?php echo htmlspecialchars($booking['booking_number']); ?></td>
                                            <td class="p-4">
                                                <div class="font-bold"><?php echo htmlspecialchars($booking['customer_name'] ?? 'Unknown'); ?></div>
                                                <div class="text-xs text-muted"><?php echo htmlspecialchars($booking['vehicle'] ?? 'N/A'); ?></div>
                                            </td>
                                            <td class="p-4">
                                                <div class="text-xs"><?php echo htmlspecialchars($booking['service_type']); ?></div>
                                            </td>
                                            <td class="p-4">
                                                <div><?php echo $booking['preferred_date'] ? date('M d, Y', strtotime($booking['preferred_date'])) : 'N/A'; ?></div>
                                                <div class="text-xs text-muted"><?php echo $booking['preferred_date'] ? date('h:i A', strtotime($booking['preferred_date'])) : ''; ?></div>
                                            </td>
                                            <td class="p-4"><span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                                             <td class="p-4">
                                                <form method="POST" class="flex gap-1">
                                                    <input type="hidden" name="action" value="assign_mechanic">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <select name="mechanic_id" class="form-control" style="padding: 0.25rem; font-size: 0.85rem;" onchange="this.form.submit()">
                                                        <option value="">Assign Mechanic</option>
                                                        <?php foreach ($mechanics as $mechanic): ?>
                                                            <option value="<?php echo $mechanic['id']; ?>" <?php echo ($booking['mechanic_id'] == $mechanic['id']) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($mechanic['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </form>
                                            </td>
                                             <td class="p-4">
                                                <form method="POST" class="flex flex-col gap-2">
                                                    <input type="hidden" name="action" value="assign_driver">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    
                                                    <?php 
                                                        $pdQuery = "SELECT * FROM pickup_delivery WHERE booking_id = ?";
                                                        $pdResult = executeQuery($pdQuery, [$booking['id']], 'i');
                                                        $pdTasks = [];
                                                        if ($pdResult) {
                                                            while($task = $pdResult->fetch_assoc()) $pdTasks[$task['type']] = $task;
                                                        }
                                                     ?>
                                                     
                                                     <?php if (empty($pdTasks)): ?>
                                                        <div class="flex items-center gap-2 text-xs text-muted bg-gray-50 p-2 rounded-lg border border-dashed">
                                                            <i class="fa-solid fa-house-user"></i>
                                                            <span>Self Drop-off/Pickup</span>
                                                        </div>
                                                     <?php else: ?>
                                                         <div class="flex flex-col gap-1.5 mb-2">
                                                            <?php foreach (['pickup' => 'fa-arrow-down-long', 'delivery' => 'fa-arrow-up-long'] as $type => $icon): ?>
                                                                <?php if (isset($pdTasks[$type])): ?>
                                                                    <div class="p-2 rounded-lg <?php echo $type === 'pickup' ? 'bg-white border-gray-100' : 'bg-blue-50/50 border-blue-100'; ?> border shadow-sm">
                                                                        <div class="flex items-center justify-between mb-1">
                                                                            <span class="text-[9px] font-black uppercase <?php echo $type === 'pickup' ? 'text-gray-500' : 'text-primary'; ?>">
                                                                                <i class="fa-solid <?php echo $icon; ?> mr-1"></i> <?php echo $type; ?>
                                                                            </span>
                                                                            <span class="badge <?php echo getStatusBadgeClass($pdTasks[$type]['status']); ?> text-[8px] transform scale-90"><?php echo str_replace('_', ' ', $pdTasks[$type]['status']); ?></span>
                                                                        </div>
                                                                        <div class="text-[10px] font-bold text-gray-800 line-clamp-1 border-t border-gray-50 pt-1" title="<?php echo htmlspecialchars($pdTasks[$type]['address']); ?>">
                                                                            <?php echo htmlspecialchars($pdTasks[$type]['address']); ?>
                                                                        </div>
                                                                        <?php if(!empty($pdTasks[$type]['lat'])): ?>
                                                                            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $pdTasks[$type]['lat'].','.$pdTasks[$type]['lng']; ?>" target="_blank" class="text-[8px] font-black text-primary uppercase mt-1 inline-block hover:underline">
                                                                                <i class="fa-solid fa-location-dot"></i> GPS View
                                                                            </a>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                         </div>
                                                         
                                                         <div class="relative">
                                                             <select name="driver_user_id" class="form-control text-[11px]" style="padding: 0.35rem; background: #fff; height: auto;" onchange="this.form.submit()">
                                                                 <option value="">Assign New Driver</option>
                                                                 <?php foreach ($drivers as $driver): ?>
                                                                     <option value="<?php echo $driver['id']; ?>">
                                                                         <?php echo htmlspecialchars($driver['name']); ?>
                                                                     </option>
                                                                 <?php endforeach; ?>
                                                             </select>
                                                             <?php if (isset($pdTasks['pickup']) && $pdTasks['pickup']['driver_name']): ?>
                                                                <div class="text-[9px] text-muted mt-1 flex items-center gap-1">
                                                                    <i class="fa-solid fa-user-check text-[8px] text-green-500"></i>
                                                                    <span>Active: <?php echo htmlspecialchars($pdTasks['pickup']['driver_name']); ?></span>
                                                                </div>
                                                             <?php endif; ?>
                                                         </div>
                                                     <?php endif; ?>
                                                </form>
                                             </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>
</body>
</html>

