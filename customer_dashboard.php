<?php 
$page_title = 'Dashboard'; 
require_once 'includes/auth.php';

// Require login
requireLogin();

$user = getCurrentUser();
$userId = getCurrentUserId();

// Get dashboard statistics
$vehiclesQuery = "SELECT COUNT(*) as total FROM vehicles WHERE user_id = ?";
$vehiclesResult = executeQuery($vehiclesQuery, [$userId], 'i');
$totalVehicles = $vehiclesResult ? $vehiclesResult->fetch_assoc()['total'] : 0;

$activeQuery = "SELECT COUNT(*) as total FROM bookings WHERE user_id = ? AND status IN ('in_progress', 'confirmed')";
$activeResult = executeQuery($activeQuery, [$userId], 'i');
$activeServices = $activeResult ? $activeResult->fetch_assoc()['total'] : 0;

$pickupQuery = "SELECT COUNT(DISTINCT pd.booking_id) as total 
                FROM pickup_delivery pd
                INNER JOIN bookings b ON pd.booking_id = b.id
                WHERE b.user_id = ? AND pd.status IN ('pending', 'scheduled')";
$pickupResult = executeQuery($pickupQuery, [$userId], 'i');
$pickupRequests = $pickupResult ? $pickupResult->fetch_assoc()['total'] : 0;

$completedQuery = "SELECT COUNT(*) as total FROM bookings WHERE user_id = ? AND status = 'completed'";
$completedResult = executeQuery($completedQuery, [$userId], 'i');
$completedServices = $completedResult ? $completedResult->fetch_assoc()['total'] : 0;

// Get recent bookings
$recentQuery = "SELECT 
    b.id, b.booking_number, b.service_type, b.service_category, b.status, 
    b.preferred_date, b.created_at,
    v.make, v.model, v.year, v.license_plate
    FROM bookings b
    INNER JOIN vehicles v ON b.vehicle_id = v.id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
    LIMIT 3";
$recentResult = executeQuery($recentQuery, [$userId], 'i');
$recentBookings = [];
if ($recentResult) {
    while ($row = $recentResult->fetch_assoc()) {
        $recentBookings[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AutoCare Connect</title>
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
                        <h1 class="text-2xl font-bold">Dashboard</h1>
                        <p class="text-muted">Welcome back, <?php echo htmlspecialchars($user['name']); ?>.</p>
                    </div>
                    <a href="book_service.php" class="btn btn-primary btn-icon"><i class="fa-solid fa-plus"></i> Book New Service</a>
                </div>

                <!-- Stats Grid -->
                <div class="grid gap-4 mb-8" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                    <!-- Total Vehicles -->
                    <div class="card">
                        <div class="flex justify-between items-start">
                            <div class="flex flex-col">
                                <span class="text-muted text-sm font-medium">Total Vehicles</span>
                                <span class="text-3xl font-bold mt-2"><?php echo $totalVehicles; ?></span>
                            </div>
                            <div style="width: 56px; height: 56px; background: #F8FAFC; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-car text-2xl text-muted"></i>
                            </div>
                        </div>
                    </div>
                     <!-- Active Services -->
                     <div class="card">
                        <div class="flex justify-between items-start">
                            <div class="flex flex-col">
                                <span class="text-muted text-sm font-medium">Active Services</span>
                                <div class="flex items-center gap-2 mt-2">
                                     <span class="text-3xl font-bold text-primary"><?php echo $activeServices; ?></span>
                                     <?php if ($activeServices > 0): ?>
                                     <span class="badge badge-info">In Progress</span>
                                     <?php endif; ?>
                                </div>
                            </div>
                            <div style="width: 56px; height: 56px; background: #F8FAFC; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-gears text-2xl text-muted"></i>
                            </div>
                        </div>
                    </div>
                     <!-- Pickup Requests -->
                     <div class="card">
                        <div class="flex justify-between items-start">
                            <div class="flex flex-col">
                                <span class="text-muted text-sm font-medium">Pickup Requests</span>
                                <span class="text-3xl font-bold mt-2"><?php echo $pickupRequests; ?></span>
                            </div>
                            <div style="width: 56px; height: 56px; background: #F8FAFC; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-truck text-2xl text-muted"></i>
                            </div>
                        </div>
                    </div>
                     <!-- Completed Services -->
                     <div class="card">
                        <div class="flex justify-between items-start">
                             <div class="flex flex-col">
                                <span class="text-muted text-sm font-medium">Completed Services</span>
                                <span class="text-3xl font-bold mt-2"><?php echo $completedServices; ?></span>
                            </div>
                            <div style="width: 56px; height: 56px; background: #F8FAFC; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-circle-check text-2xl text-muted"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card" style="padding: 0; overflow: hidden;">
                    <div class="flex justify-between items-center" style="padding: 1.75rem 2rem; border-bottom: 1px solid var(--border);">
                        <h3 class="font-bold text-lg">Recent Activity</h3>
                        <a href="track_service.php" class="text-primary text-sm font-medium">View All</a>
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table class="w-full text-left" style="min-width: 900px;">
                            <thead style="background: #F8FAFC; border-bottom: 1px solid var(--border);">
                                <tr>
                                    <th style="padding: 1rem 2rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">VEHICLE</th>
                                    <th style="padding: 1rem 2rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">SERVICE TYPE</th>
                                    <th style="padding: 1rem 2rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">DATE</th>
                                    <th style="padding: 1rem 2rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">STATUS</th>
                                    <th style="padding: 1rem 2rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">ACTION</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm">
                                <?php if (count($recentBookings) > 0): ?>
                                    <?php foreach ($recentBookings as $booking): ?>
                                    <tr style="border-bottom: 1px solid var(--border);">
                                        <td style="padding: 1.5rem 2rem;">
                                            <div class="flex items-center gap-3">
                                                <div style="width: 48px; height: 48px; background: #F8FAFC; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fa-solid fa-car text-muted"></i>
                                                </div>
                                                <div>
                                                    <div class="font-bold"><?php echo htmlspecialchars($booking['year'] . ' ' . $booking['make'] . ' ' . $booking['model']); ?></div>
                                                    <div class="text-xs text-muted">License: <?php echo htmlspecialchars($booking['license_plate']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 1.5rem 2rem;">
                                            <div class="font-medium"><?php echo htmlspecialchars($booking['service_type']); ?></div>
                                            <div class="text-xs text-muted"><?php echo htmlspecialchars(ucfirst($booking['service_category'])); ?></div>
                                        </td>
                                        <td style="padding: 1.5rem 2rem;"><?php echo formatDate($booking['preferred_date'], 'M d, Y'); ?></td>
                                        <td style="padding: 1.5rem 2rem;">
                                            <span class="badge <?php echo getStatusBadgeClass($booking['status']); ?>">
                                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $booking['status']))); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1.5rem 2rem; text-align: center;">
                                            <a href="track_service.php?id=<?php echo $booking['id']; ?>" class="btn btn-outline btn-sm">
                                                <i class="fa-regular fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                                            <i class="fa-solid fa-calendar-xmark" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                                            <p>No recent bookings found.</p>
                                            <a href="book_service.php" class="btn btn-primary mt-4">Book Your First Service</a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Table Footer -->
                    <div class="flex justify-between items-center" style="padding: 1.25rem 2rem; border-top: 1px solid var(--border);">
                        <span class="text-sm text-muted">Showing 3 of 10 recent services</span>
                        <div class="flex gap-2">
                            <button class="btn btn-outline btn-sm" style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-chevron-left" style="font-size: 0.75rem;"></i>
                            </button>
                            <button class="btn btn-outline btn-sm" style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-chevron-right" style="font-size: 0.75rem;"></i>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
</body>
</html>