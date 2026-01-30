<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

// Require admin access
requireAdmin();

// Set current page for navigation
$current_page = 'admin_dashboard.php';
$page_title = 'Admin Dashboard';

// Fetch real statistics from database
// Total bookings
$totalBookingsQuery = "SELECT COUNT(*) as count FROM bookings";
$totalBookingsResult = executeQuery($totalBookingsQuery, [], '');
$totalBookings = $totalBookingsResult ? $totalBookingsResult->fetch_assoc()['count'] : 0;

// Pending bookings
$pendingBookingsQuery = "SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'";
$pendingBookingsResult = executeQuery($pendingBookingsQuery, [], '');
$pendingBookings = $pendingBookingsResult ? $pendingBookingsResult->fetch_assoc()['count'] : 0;

// In progress bookings
$inProgressQuery = "SELECT COUNT(*) as count FROM bookings WHERE status IN ('in_progress', 'ready_for_delivery')";
$inProgressResult = executeQuery($inProgressQuery, [], '');
$inProgressBookings = $inProgressResult ? $inProgressResult->fetch_assoc()['count'] : 0;

// Completed today
$completedTodayQuery = "SELECT COUNT(*) as count FROM bookings WHERE status IN ('completed', 'delivered') AND DATE(completion_date) = CURDATE()";
$completedTodayResult = executeQuery($completedTodayQuery, [], '');
$completedToday = $completedTodayResult ? $completedTodayResult->fetch_assoc()['count'] : 0;

// Total mechanics
$totalMechanicsQuery = "SELECT COUNT(*) as count FROM mechanics m JOIN users u ON m.user_id = u.id";
$totalMechanicsResult = executeQuery($totalMechanicsQuery, [], '');
$totalMechanics = $totalMechanicsResult ? $totalMechanicsResult->fetch_assoc()['count'] : 0;

// Total unique drivers
$totalDriversQuery = "SELECT COUNT(*) as count FROM drivers d JOIN users u ON d.user_id = u.id";
$totalDriversResult = executeQuery($totalDriversQuery, [], '');
$totalDrivers = $totalDriversResult ? $totalDriversResult->fetch_assoc()['count'] : 0;

// Total customers (users with customer role)
$totalCustomersQuery = "SELECT COUNT(*) as count FROM users WHERE role = 'customer'";
$totalCustomersResult = executeQuery($totalCustomersQuery, [], '');
$totalCustomers = $totalCustomersResult ? $totalCustomersResult->fetch_assoc()['count'] : 0;

// Today's revenue
$todayRevenueQuery = "SELECT SUM(final_cost) as revenue FROM bookings WHERE status IN ('completed', 'delivered') AND DATE(completion_date) = CURDATE()";
$todayRevenueResult = executeQuery($todayRevenueQuery, [], '');
$todayRevenue = $todayRevenueResult ? ($todayRevenueResult->fetch_assoc()['revenue'] ?? 0) : 0;

// Total Revenue (All Time)
$totalRevenueQuery = "SELECT SUM(final_cost) as revenue FROM bookings WHERE status IN ('completed', 'delivered')";
$totalRevenueResult = executeQuery($totalRevenueQuery, [], '');
$totalRevenue = $totalRevenueResult ? ($totalRevenueResult->fetch_assoc()['revenue'] ?? 0) : 0;

// Recent activity - latest 5 bookings
$recentActivityQuery = "SELECT b.id, b.booking_number, b.status, b.created_at, b.service_type, 
                        u.name as customer_name, u.email as customer_email
                        FROM bookings b
                        LEFT JOIN users u ON b.user_id = u.id
                        ORDER BY b.created_at DESC
                        LIMIT 5";
$recentActivityResult = executeQuery($recentActivityQuery, [], '');
$recentActivities = [];
if ($recentActivityResult) {
    while ($row = $recentActivityResult->fetch_assoc()) {
        $recentActivities[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AutoCare Connect</title>
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
                        <h1 class="text-2xl font-bold">Admin Dashboard</h1>
                        <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! Here's what's happening today.</p>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="grid gap-4 mb-8" style="grid-template-columns: repeat(5, 1fr);">
                    <div class="card p-4 border-l-4 border-warning">
                        <div class="flex justify-between">
                            <div>
                                <span class="text-muted text-sm font-medium">Pending</span>
                                <div class="flex items-end gap-2 mt-1">
                                    <span class="text-2xl font-bold"><?php echo $pendingBookings; ?></span>
                                </div>
                            </div>
                            <span class="text-warning text-lg"><i class="fa-solid fa-clipboard-list"></i></span>
                        </div>
                    </div>

                    <!-- In Progress -->
                    <div class="card p-4 border-l-4 border-info">
                        <div class="flex justify-between">
                            <div>
                                <span class="text-muted text-sm font-medium">Active</span>
                                <div class="flex items-end gap-2 mt-1">
                                    <span class="text-2xl font-bold"><?php echo $inProgressBookings; ?></span>
                                </div>
                            </div>
                            <span class="text-info text-lg"><i class="fa-solid fa-screwdriver-wrench"></i></span>
                        </div>
                    </div>

                    <!-- Completed Today -->
                    <div class="card p-4 border-l-4 border-success">
                        <div class="flex justify-between">
                            <div>
                                <span class="text-muted text-sm font-medium">Done Today</span>
                                <div class="flex items-end gap-2 mt-1">
                                    <span class="text-2xl font-bold"><?php echo $completedToday; ?></span>
                                </div>
                            </div>
                            <span class="text-success text-lg"><i class="fa-solid fa-circle-check"></i></span>
                        </div>
                    </div>

                    <!-- Today's Revenue -->
                    <div class="card p-4 border-l-4 border-accent">
                        <div class="flex justify-between">
                            <div>
                                <span class="text-muted text-sm font-medium">Today's Rev</span>
                                <div class="flex items-end gap-2 mt-1">
                                    <span class="text-xl font-bold">₹<?php echo number_format($todayRevenue); ?></span>
                                </div>
                            </div>
                            <span class="text-accent text-lg"><i class="fa-solid fa-indian-rupee-sign"></i></span>
                        </div>
                    </div>

                    <!-- Total Revenue (NEW) -->
                    <div class="card p-4 border-l-4 border-primary bg-primary/5">
                        <div class="flex justify-between">
                            <div>
                                <span class="text-primary font-bold text-sm">Total Revenue</span>
                                <div class="flex items-end gap-2 mt-1">
                                    <span class="text-xl font-black text-gray-900">₹<?php echo number_format($totalRevenue); ?></span>
                                </div>
                            </div>
                            <span class="text-primary text-lg"><i class="fa-solid fa-sack-dollar"></i></span>
                        </div>
                    </div>
                </div>

                <!-- Second Row Stats -->
                <div class="grid gap-4 mb-8" style="grid-template-columns: repeat(4, 1fr);">
                    <!-- Total Bookings -->
                    <div class="card p-4">
                        <div class="flex justify-between">
                            <div>
                                <span class="text-muted text-sm font-medium">Total Bookings</span>
                                <div class="flex items-end gap-2 mt-1">
                                    <span class="text-3xl font-bold"><?php echo $totalBookings; ?></span>
                                </div>
                            </div>
                            <span class="text-primary text-xl"><i class="fa-solid fa-book"></i></span>
                        </div>
                    </div>

                    <!-- Total Mechanics -->
                    <div class="card p-4">
                        <div class="flex justify-between">
                            <div>
                                <span class="text-muted text-sm font-medium">Total Mechanics</span>
                                <div class="flex items-end gap-2 mt-1">
                                    <span class="text-3xl font-bold"><?php echo $totalMechanics; ?></span>
                                </div>
                            </div>
                            <span class="text-success text-xl"><i class="fa-solid fa-user-gear"></i></span>
                        </div>
                    </div>

                    <!-- Total Drivers -->
                    <div class="card p-4">
                        <div class="flex justify-between">
                            <div>
                                <span class="text-muted text-sm font-medium">Total Drivers</span>
                                <div class="flex items-end gap-2 mt-1">
                                    <span class="text-3xl font-bold"><?php echo $totalDrivers; ?></span>
                                </div>
                            </div>
                            <span class="text-warning text-xl"><i class="fa-solid fa-truck"></i></span>
                        </div>
                    </div>

                    <!-- Total Customers -->
                    <div class="card p-4">
                        <div class="flex justify-between">
                            <div>
                                <span class="text-muted text-sm font-medium">Total Customers</span>
                                <div class="flex items-end gap-2 mt-1">
                                    <span class="text-3xl font-bold"><?php echo $totalCustomers; ?></span>
                                </div>
                            </div>
                            <span class="text-info text-xl"><i class="fa-solid fa-users"></i></span>
                        </div>
                    </div>
                </div>

                <!-- Personnel Overview & Recent Activity -->
                <div class="grid gap-4" style="grid-template-columns: 1fr 1fr;">
                    <!-- Personnel Status -->
                    <div class="card p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">Personnel Availability</h3>
                            <div class="flex gap-2">
                                <a href="admin_mechanics.php" class="text-xs text-primary font-bold hover:underline">Manage All</a>
                            </div>
                        </div>
                        
                        <div class="flex flex-col gap-4">
                             <!-- Mechanics -->
                             <div>
                                 <div class="text-[10px] font-bold text-muted uppercase mb-2">Mechanics</div>
                                 <div class="flex flex-wrap gap-2">
                                     <?php
                                     $mStatusQuery = "SELECT u.name, m.is_available FROM mechanics m JOIN users u ON m.user_id = u.id LIMIT 5";
                                     $mStatusRes = executeQuery($mStatusQuery, [], '');
                                     while ($mRow = $mStatusRes->fetch_assoc()):
                                     ?>
                                         <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-lg border border-gray-100">
                                             <div class="w-2 h-2 rounded-full <?php echo $mRow['is_available'] ? 'bg-success' : 'bg-warning'; ?>"></div>
                                             <span class="text-xs font-medium"><?php echo htmlspecialchars($mRow['name']); ?></span>
                                         </div>
                                     <?php endwhile; ?>
                                 </div>
                             </div>

                             <!-- Drivers -->
                             <div>
                                 <div class="text-[10px] font-bold text-muted uppercase mb-2">Drivers</div>
                                 <div class="flex flex-wrap gap-2">
                                     <?php
                                     $dStatusQuery = "SELECT u.name, d.is_available FROM drivers d JOIN users u ON d.user_id = u.id LIMIT 5";
                                     $dStatusRes = executeQuery($dStatusQuery, [], '');
                                     while ($dRow = $dStatusRes->fetch_assoc()):
                                     ?>
                                         <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-lg border border-gray-100">
                                             <div class="w-2 h-2 rounded-full <?php echo $dRow['is_available'] ? 'bg-success' : 'bg-warning'; ?>"></div>
                                             <span class="text-xs font-medium"><?php echo htmlspecialchars($dRow['name']); ?></span>
                                         </div>
                                     <?php endwhile; ?>
                                     <?php if ($dStatusRes->num_rows == 0): ?>
                                         <span class="text-xs text-muted">No drivers registered.</span>
                                     <?php endif; ?>
                                 </div>
                             </div>
                        </div>

                        <div class="mt-6 pt-6 border-t flex gap-2">
                             <a href="admin_bookings.php" class="btn btn-primary btn-sm btn-icon">
                                <i class="fa-solid fa-clipboard-list"></i> Bookings
                            </a>
                            <a href="admin_job_requests.php" class="btn btn-outline btn-sm btn-icon">
                                <i class="fa-solid fa-user-plus"></i> Job Requests
                            </a>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card p-6">
                        <h3 class="text-lg font-bold mb-4">Recent Activity</h3>
                        <?php if (empty($recentActivities)): ?>
                            <p class="text-muted text-sm">No recent activity to display.</p>
                        <?php else: ?>
                            <div class="flex flex-col gap-3">
                                <?php foreach ($recentActivities as $activity): ?>
                                    <?php
                                    // Determine status badge color
                                    $badgeClass = 'badge-info';
                                    if ($activity['status'] === 'pending') $badgeClass = 'badge-warning';
                                    elseif ($activity['status'] === 'completed' || $activity['status'] === 'delivered') $badgeClass = 'badge-success';
                                    elseif ($activity['status'] === 'in_progress') $badgeClass = 'badge-info';
                                    elseif ($activity['status'] === 'ready_for_delivery') $badgeClass = 'badge-primary';
                                    elseif ($activity['status'] === 'cancelled') $badgeClass = 'badge-danger';
                                    
                                    // Format time ago
                                    $timeAgo = '';
                                    $timestamp = strtotime($activity['created_at']);
                                    $diff = time() - $timestamp;
                                    if ($diff < 60) $timeAgo = 'Just now';
                                    elseif ($diff < 3600) $timeAgo = floor($diff / 60) . 'm ago';
                                    elseif ($diff < 86400) $timeAgo = floor($diff / 3600) . 'h ago';
                                    else $timeAgo = floor($diff / 86400) . 'd ago';
                                    ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 hover:bg-white hover:shadow-sm transition-all rounded-xl border border-transparent hover:border-gray-100">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center text-primary border border-gray-100 font-bold text-xs">
                                                <?php echo strtoupper(substr($activity['customer_name'] ?? 'U', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="font-bold text-sm"><?php echo htmlspecialchars($activity['customer_name'] ?? 'Unknown'); ?></div>
                                                <div class="text-[10px] text-muted"><?php echo htmlspecialchars($activity['service_type']); ?></div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="badge <?php echo $badgeClass; ?> text-[10px]"><?php echo strtoupper(formatStatusLabel($activity['status'])); ?></span>
                                            <div class="text-[10px] text-muted mt-1"><?php echo $timeAgo; ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Current Running Services Section -->
                <div class="mb-8">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-lg">Current Running Services</h3>
                        <a href="admin_bookings.php" class="text-xs text-primary font-bold hover:underline">View All Bookings</a>
                    </div>
                    
                    <?php
                    // Fetch current in-progress services
                    $runningServicesQuery = "SELECT b.id, b.booking_number, b.service_type, b.status, b.preferred_date,
                                                    v.make, v.model, v.year, v.license_plate,
                                                    u.name as customer_name,
                                                    m.id as mechanic_id, mu.name as mechanic_name,
                                                    b.progress_percentage, b.has_pickup_delivery
                                             FROM bookings b
                                             JOIN vehicles v ON b.vehicle_id = v.id
                                             JOIN users u ON b.user_id = u.id
                                             LEFT JOIN mechanics m ON b.mechanic_id = m.id
                                             LEFT JOIN users mu ON m.user_id = mu.id
                                             WHERE b.status IN ('confirmed', 'in_progress', 'ready_for_delivery')
                                             ORDER BY b.updated_at DESC
                                             LIMIT 5";
                    $runningServicesResult = executeQuery($runningServicesQuery, [], '');
                    $runningServices = [];
                    if ($runningServicesResult) {
                        while ($row = $runningServicesResult->fetch_assoc()) {
                            $runningServices[] = $row;
                        }
                    }
                    ?>
                    
                    <div class="card p-0 overflow-hidden">
                        <?php if (empty($runningServices)): ?>
                            <div class="p-12 text-center text-muted">
                                <i class="fa-solid fa-clipboard-check text-6xl mb-4 opacity-10"></i>
                                <p>No services currently in progress.</p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left">
                                    <thead class="bg-gray-50 text-[10px] font-bold uppercase text-muted tracking-wider border-b border-gray-100">
                                        <tr>
                                            <th class="p-4">Booking</th>
                                            <th class="p-4">Vehicle</th>
                                            <th class="p-4">Service</th>
                                            <th class="p-4">Mechanic</th>
                                            <th class="p-4">Progress</th>
                                            <th class="p-4 text-right">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-sm">
                                        <?php foreach ($runningServices as $service): ?>
                                            <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                                <td class="p-4">
                                                    <div class="font-mono text-primary font-bold">#<?php echo htmlspecialchars($service['booking_number']); ?></div>
                                                    <div class="text-[10px] text-muted"><?php echo htmlspecialchars($service['customer_name']); ?></div>
                                                </td>
                                                <td class="p-4">
                                                    <div class="font-bold text-gray-900"><?php echo htmlspecialchars($service['year'] . ' ' . $service['make'] . ' ' . $service['model']); ?></div>
                                                    <div class="text-[10px] text-muted font-mono"><?php echo htmlspecialchars($service['license_plate']); ?></div>
                                                </td>
                                                <td class="p-4">
                                                    <div class="text-gray-700"><?php echo htmlspecialchars($service['service_type']); ?></div>
                                                    <?php if ($service['has_pickup_delivery']): ?>
                                                        <span class="text-[9px] bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full font-bold">Pickup/Delivery</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="p-4">
                                                    <?php if ($service['mechanic_name']): ?>
                                                        <div class="flex items-center gap-2">
                                                            <div class="w-6 h-6 bg-primary/10 text-primary rounded-full flex items-center justify-center text-[10px] font-bold">
                                                                <?php echo strtoupper(substr($service['mechanic_name'], 0, 1)); ?>
                                                            </div>
                                                            <span class="font-medium"><?php echo htmlspecialchars($service['mechanic_name']); ?></span>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted text-xs">Not assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="p-4">
                                                    <div class="flex items-center gap-2">
                                                        <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                                                            <div class="bg-primary h-full transition-all" style="width: <?php echo $service['progress_percentage'] ?? 0; ?>%"></div>
                                                        </div>
                                                        <span class="text-xs font-bold text-gray-600"><?php echo $service['progress_percentage'] ?? 0; ?>%</span>
                                                    </div>
                                                </td>
                                                <td class="p-4 text-right">
                                                    <span class="badge <?php echo getStatusBadgeClass($service['status']); ?> text-[10px] px-3 py-1 rounded-full font-bold uppercase">
                                                        <?php echo formatStatusLabel($service['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Employee Details Section -->
                <h3 class="font-bold text-lg mb-4 mt-8">Employee Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <!-- Mechanic Details -->
                    <div class="card p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-user-gear text-orange-500"></i>
                                <h4 class="font-bold">Active Mechanics</h4>
                            </div>
                            <a href="admin_mechanics.php" class="text-xs text-primary font-bold hover:underline">View All</a>
                        </div>
                        <div class="space-y-3">
                            <?php 
                            $mQuery = "SELECT u.name, m.specialization, m.is_available FROM mechanics m JOIN users u ON m.user_id = u.id LIMIT 4";
                            $mDetails = executeQuery($mQuery, [], '');
                            while($m = $mDetails->fetch_assoc()):
                            ?>
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-xl border border-gray-100">
                                    <div>
                                        <div class="font-bold text-sm"><?php echo htmlspecialchars($m['name']); ?></div>
                                        <div class="text-[10px] text-muted"><?php echo htmlspecialchars($m['specialization'] ?? ''); ?></div>
                                    </div>
                                    <span class="badge <?php echo $m['is_available'] ? 'badge-success' : 'badge-secondary'; ?> text-[10px]">
                                        <?php echo $m['is_available'] ? 'Available' : 'Busy'; ?>
                                    </span>
                                </div>
                            <?php endwhile; ?>
                            <?php if ($mDetails->num_rows == 0): ?>
                                <p class="text-xs text-muted text-center py-4">No mechanics found.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Driver Details -->
                    <div class="card p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-truck text-blue-500"></i>
                                <h4 class="font-bold">Active Drivers</h4>
                            </div>
                            <a href="admin_drivers.php" class="text-xs text-primary font-bold hover:underline">View All</a>
                        </div>
                        <div class="space-y-3">
                            <?php 
                            $dQuery = "SELECT u.name, d.vehicle_number, d.is_available FROM drivers d JOIN users u ON d.user_id = u.id LIMIT 4";
                            $dDetails = executeQuery($dQuery, [], '');
                            while($d = $dDetails->fetch_assoc()):
                            ?>
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-xl border border-gray-100">
                                    <div>
                                        <div class="font-bold text-sm"><?php echo htmlspecialchars($d['name']); ?></div>
                                        <div class="text-[10px] text-muted">Vehicle: <?php echo htmlspecialchars($d['vehicle_number'] ?? 'N/A'); ?></div>
                                    </div>
                                    <span class="badge <?php echo $d['is_available'] ? 'badge-success' : 'badge-secondary'; ?> text-[10px]">
                                        <?php echo $d['is_available'] ? 'Available' : 'Busy'; ?>
                                    </span>
                                </div>
                            <?php endwhile; ?>
                            <?php if ($dDetails->num_rows == 0): ?>
                                <p class="text-xs text-muted text-center py-4">No drivers found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
</body>
</html>
