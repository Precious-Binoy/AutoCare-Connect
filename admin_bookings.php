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
                    pd.driver_name
                  FROM bookings b
                  LEFT JOIN users u ON b.user_id = u.id
                  LEFT JOIN vehicles v ON b.vehicle_id = v.id
                  LEFT JOIN mechanics m ON b.mechanic_id = m.id
                  LEFT JOIN users mu ON m.user_id = mu.id
                  LEFT JOIN pickup_delivery pd ON b.id = pd.booking_id
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

// Fetch all drivers for dropdown
$driversQuery = "SELECT DISTINCT driver_name FROM pickup_delivery WHERE driver_name IS NOT NULL AND driver_name != '' ORDER BY driver_name";
$driversResult = executeQuery($driversQuery, [], '');
$drivers = [];
if ($driversResult) {
    while ($row = $driversResult->fetch_assoc()) {
        $drivers[] = $row;
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
                     <div class="card p-4">
                         <div class="flex justify-between">
                            <div>
                                <span class="text-muted text-sm font-medium">Drivers Active</span>
                                <div class="flex items-end gap-2 mt-1">
                                    <span class="text-3xl font-bold"><?php echo $activeDrivers; ?></span>
                                </div>
                            </div>
                            <span class="text-accent text-xl"><i class="fa-solid fa-truck"></i></span>
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
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Driver</th>
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
                                                <?php if ($booking['mechanic_name']): ?>
                                                    <span><?php echo htmlspecialchars($booking['mechanic_name']); ?></span>
                                                <?php else: ?>
                                                    <select class="form-control" style="padding: 0.25rem; font-size: 0.85rem;">
                                                        <option>Assign Mechanic</option>
                                                        <?php foreach ($mechanics as $mechanic): ?>
                                                            <option value="<?php echo $mechanic['id']; ?>"><?php echo htmlspecialchars($mechanic['name']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-4">
                                                <?php if ($booking['driver_name']): ?>
                                                    <span><?php echo htmlspecialchars($booking['driver_name']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted italic">N/A</span>
                                                <?php endif; ?>
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

