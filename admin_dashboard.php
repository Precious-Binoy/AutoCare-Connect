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
$inProgressQuery = "SELECT COUNT(*) as count FROM bookings WHERE status = 'in_progress'";
$inProgressResult = executeQuery($inProgressQuery, [], '');
$inProgressBookings = $inProgressResult ? $inProgressResult->fetch_assoc()['count'] : 0;

// Completed today
$completedTodayQuery = "SELECT COUNT(*) as count FROM bookings WHERE status = 'completed' AND DATE(completion_date) = CURDATE()";
$completedTodayResult = executeQuery($completedTodayQuery, [], '');
$completedToday = $completedTodayResult ? $completedTodayResult->fetch_assoc()['count'] : 0;

// Total mechanics
$totalMechanicsQuery = "SELECT COUNT(*) as count FROM mechanics";
$totalMechanicsResult = executeQuery($totalMechanicsQuery, [], '');
$totalMechanics = $totalMechanicsResult ? $totalMechanicsResult->fetch_assoc()['count'] : 0;

// Total unique drivers from pickup_delivery table
$totalDriversQuery = "SELECT COUNT(DISTINCT driver_name) as count FROM pickup_delivery WHERE driver_name IS NOT NULL AND driver_name != ''";
$totalDriversResult = executeQuery($totalDriversQuery, [], '');
$totalDrivers = $totalDriversResult ? $totalDriversResult->fetch_assoc()['count'] : 0;

// Total customers (users with customer role)
$totalCustomersQuery = "SELECT COUNT(*) as count FROM users WHERE role = 'customer'";
$totalCustomersResult = executeQuery($totalCustomersQuery, [], '');
$totalCustomers = $totalCustomersResult ? $totalCustomersResult->fetch_assoc()['count'] : 0;

// Today's revenue
$todayRevenueQuery = "SELECT SUM(final_cost) as revenue FROM bookings WHERE status = 'completed' AND DATE(completion_date) = CURDATE()";
$todayRevenueResult = executeQuery($todayRevenueQuery, [], '');
$todayRevenue = $todayRevenueResult ? ($todayRevenueResult->fetch_assoc()['revenue'] ?? 0) : 0;

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
                <div class="grid gap-4 mb-8" style="grid-template-columns: repeat(4, 1fr);">
                    <!-- Pending Bookings -->
                    <div class="card p-4">
                        <div class="flex justify-between">
                            <div>
                                <span class="text-muted text-sm font-medium">Pending Bookings</span>
                                <div class="flex items-end gap-2 mt-1">
                                    <span class="text-3xl font-bold"><?php echo $pendingBookings; ?></span>
                                </div>
                            </div>
                            <span class="text-warning text-xl"><i class="fa-solid fa-clipboard-list"></i></span>
                        </div>
                    </div>

                    <!-- In Progress -->
                    <div class="card p-4">
                        <div class="flex justify-between">
                            <div>
                                <span class="text-muted text-sm font-medium">In Progress</span>
                                <div class="flex items-end gap-2 mt-1">
                                    <span class="text-3xl font-bold"><?php echo $inProgressBookings; ?></span>
                                </div>
                            </div>
                            <span class="text-info text-xl"><i class="fa-solid fa-screwdriver-wrench"></i></span>
                        </div>
                    </div>

                    <!-- Completed Today -->
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

                    <!-- Today's Revenue -->
                    <div class="card p-4">
                        <div class="flex justify-between">
                            <div>
                                <span class="text-muted text-sm font-medium">Today's Revenue</span>
                                <div class="flex items-end gap-2 mt-1">
                                    <span class="text-3xl font-bold">₹<?php echo number_format($todayRevenue, 2); ?></span>
                                </div>
                            </div>
                            <span class="text-accent text-xl"><i class="fa-solid fa-indian-rupee-sign"></i></span>
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

                <!-- Quick Actions & Recent Activity -->
                <div class="grid gap-4" style="grid-template-columns: 1fr 1fr;">
                    <!-- Quick Actions -->
                    <div class="card p-6">
                        <h3 class="text-lg font-bold mb-4">Quick Actions</h3>
                        <div class="flex flex-col gap-3">
                            <a href="admin_bookings.php" class="btn btn-primary btn-icon w-full">
                                <i class="fa-solid fa-clipboard-list"></i> Manage Bookings
                            </a>
                            <a href="admin_mechanics.php" class="btn btn-outline btn-icon w-full">
                                <i class="fa-solid fa-user-gear"></i> Manage Mechanics
                            </a>
                            <a href="admin_drivers.php" class="btn btn-outline btn-icon w-full">
                                <i class="fa-solid fa-truck"></i> Manage Drivers
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
                                    elseif ($activity['status'] === 'completed') $badgeClass = 'badge-success';
                                    elseif ($activity['status'] === 'in_progress') $badgeClass = 'badge-info';
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
                                    <div class="flex items-center justify-between p-3 bg-gray-50" style="background: #F8FAFC; border-radius: 8px;">
                                        <div>
                                            <div class="font-bold text-sm"><?php echo htmlspecialchars($activity['customer_name'] ?? 'Unknown'); ?></div>
                                            <div class="text-xs text-muted"><?php echo htmlspecialchars($activity['service_type']); ?></div>
                                        </div>
                                        <div class="text-right">
                                            <span class="badge <?php echo $badgeClass; ?> text-xs"><?php echo ucfirst($activity['status']); ?></span>
                                            <div class="text-xs text-muted"><?php echo $timeAgo; ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>
</body>
</html>
