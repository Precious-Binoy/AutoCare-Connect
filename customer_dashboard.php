<?php 
$page_title = 'Dashboard'; 
$current_page = 'customer_dashboard.php';
require_once 'includes/auth.php';

// Require login
requireLogin();

$user = getCurrentUser();
$userId = getCurrentUserId();

// Get dashboard statistics
$vehiclesQuery = "SELECT COUNT(*) as total FROM vehicles WHERE user_id = ?";
$vehiclesResult = executeQuery($vehiclesQuery, [$userId], 'i');
$totalVehicles = $vehiclesResult ? $vehiclesResult->fetch_assoc()['total'] : 0;

$activeQuery = "SELECT COUNT(*) as total FROM bookings WHERE user_id = ? AND status IN ('in_progress', 'confirmed', 'ready_for_delivery')";
$activeResult = executeQuery($activeQuery, [$userId], 'i');
$activeServices = $activeResult ? $activeResult->fetch_assoc()['total'] : 0;

$pickupQuery = "SELECT COUNT(DISTINCT pd.booking_id) as total 
                FROM pickup_delivery pd
                INNER JOIN bookings b ON pd.booking_id = b.id
                WHERE b.user_id = ? AND pd.status IN ('pending', 'scheduled')";
$pickupResult = executeQuery($pickupQuery, [$userId], 'i');
$pickupRequests = $pickupResult ? $pickupResult->fetch_assoc()['total'] : 0;

$completedQuery = "SELECT COUNT(*) as total FROM bookings WHERE user_id = ? AND status IN ('completed', 'delivered')";
$completedResult = executeQuery($completedQuery, [$userId], 'i');
$completedServices = $completedResult ? $completedResult->fetch_assoc()['total'] : 0;


// Check for active deliveries with expanded driver info
$deliveryQuery = "SELECT pd.id, pd.booking_id, pd.status, pd.driver_name, pd.driver_phone, pd.type, v.make, v.model, v.license_plate, v.color,
                         b.status as booking_status,
                         d.license_number, d.vehicle_info as driver_vehicle, 
                         u_d.profile_image as driver_image
                  FROM pickup_delivery pd
                  JOIN bookings b ON pd.booking_id = b.id
                  JOIN vehicles v ON b.vehicle_id = v.id
                  LEFT JOIN users u_d ON pd.driver_user_id = u_d.id
                  LEFT JOIN drivers d ON pd.driver_user_id = d.user_id
                  WHERE b.user_id = ? AND pd.status IN ('scheduled', 'in_transit')
                  ORDER BY CASE WHEN pd.type = 'pickup' THEN 0 ELSE 1 END ASC, pd.created_at DESC
                  LIMIT 1";
$deliveryResult = executeQuery($deliveryQuery, [$userId], 'i');
$activeDelivery = $deliveryResult ? $deliveryResult->fetch_assoc() : null;

// Fetch all vehicles for the garage
$garageQuery = "SELECT * FROM vehicles WHERE user_id = ?";
$garageResult = executeQuery($garageQuery, [$userId], 'i');
$vehicles = [];
if ($garageResult) {
    while ($row = $garageResult->fetch_assoc()) {
        $vehicles[] = $row;
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

                <?php if ($activeDelivery): ?>
                    <div class="glass-card p-0 mb-8 overflow-hidden animate-fade-in border-l-4 border-primary">
                        <div class="p-6 flex flex-col md:flex-row justify-between gap-6">
                            <div class="flex items-start gap-4">
                                <div class="w-16 h-16 rounded-2xl bg-blue-100 flex items-center justify-center text-blue-600 flex-shrink-0">
                                    <i class="fa-solid fa-truck-fast text-3xl"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-blue-900 text-xl mb-1">Logistics: <?php echo ucfirst($activeDelivery['type']); ?> In Progress</h3>
                                    <p class="text-blue-700 font-medium">
                                        Your <strong><?php echo htmlspecialchars($activeDelivery['color'] . ' ' . $activeDelivery['make'] . ' ' . $activeDelivery['model']); ?></strong> 
                                        (<?php echo htmlspecialchars($activeDelivery['license_plate']); ?>)
                                    </p>
                                    <div class="flex items-center gap-2 mt-2">
                                        <span class="badge <?php echo ($activeDelivery['status'] == 'in_transit') ? 'badge-primary' : 'badge-warning'; ?> uppercase text-[10px]">
                                            <?php echo ($activeDelivery['status'] == 'in_transit') ? 'On the way' : 'Scheduled'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Driver Info Card -->
                            <?php if ($activeDelivery['driver_name']): ?>
                            <div class="bg-blue-50/50 p-4 rounded-2xl border border-blue-100 flex items-center gap-4 min-w-[300px]">
                                <img src="<?php echo $activeDelivery['driver_image'] ? 'uploads/profiles/' . $activeDelivery['driver_image'] : 'https://ui-avatars.com/api/?name=' . urlencode($activeDelivery['driver_name']) . '&background=0D8ABC&color=fff'; ?>" 
                                     alt="Driver" class="w-14 h-14 rounded-full object-cover border-2 border-white shadow-sm">
                                <div class="flex-1">
                                    <div class="text-[10px] font-bold text-blue-600 uppercase mb-1">Your Driver</div>
                                    <div class="font-bold text-blue-900"><?php echo htmlspecialchars($activeDelivery['driver_name']); ?></div>
                                    <div class="text-[10px] text-blue-700">License: <?php echo htmlspecialchars($activeDelivery['license_number'] ?? 'Verified'); ?></div>
                                </div>
                                <?php if ($activeDelivery['driver_phone']): ?>
                                <a href="tel:<?php echo $activeDelivery['driver_phone']; ?>" class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center hover:scale-110 transition-transform">
                                    <i class="fa-solid fa-phone"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Modern Timeline Strip -->
                        <div class="bg-gray-50/80 px-6 py-6 border-t border-gray-100">
                            <?php 
                                $bStatus = $activeDelivery['booking_status'] ?? 'pending';
                                $pdStatus = $activeDelivery['status'];
                                $pdType = $activeDelivery['type'];
                                $hasDriver = !empty($activeDelivery['driver_name']);
                                
                                $activeIndex = 0;
                                // Phase 0: Booked (Initial state)
                                if ($bStatus == 'confirmed' || $bStatus == 'pending') $activeIndex = 0;
                                
                                // Phase 1: Pickup (Only if driver is assigned for a pickup task)
                                if ($pdType == 'pickup' && $hasDriver && ($pdStatus == 'scheduled' || $pdStatus == 'in_transit')) {
                                    $activeIndex = 1;
                                }
                                
                                // Phase 2: Repair (Status is in_progress)
                                if ($bStatus == 'in_progress') {
                                    $activeIndex = 2;
                                }
                                
                                // Phase 3: Delivery (Status is ready_for_delivery or delivery is in_transit)
                                if ($bStatus == 'ready_for_delivery') {
                                    $activeIndex = 3;
                                }
                                
                                // Phase 4: Done
                                if ($bStatus == 'delivered' || $bStatus == 'completed') {
                                    $activeIndex = 4;
                                }
                                
                                $progressFraction = $activeIndex / 4;
                            ?>
                            <div class="flex items-center justify-between mb-8">
                                <span class="text-[10px] font-black text-blue-600 uppercase tracking-[0.2em] flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-blue-600 animate-ping"></span>
                                    Live Service Progression
                                </span>
                                <a href="track_service.php?id=<?php echo $activeDelivery['booking_id']; ?>" class="text-[10px] font-black text-gray-400 uppercase tracking-widest hover:text-primary transition-colors">Full Details <i class="fa-solid fa-chevron-right ml-1"></i></a>
                            </div>

                            <div class="timeline-horizontal">
                                <div class="timeline-progress" style="width: calc((100% - 3rem - 100px) * <?php echo $progressFraction; ?>);"></div>
                                
                                <div class="timeline-step <?php echo $activeIndex >= 0 ? ($activeIndex > 0 ? 'completed' : 'active') : ''; ?>">
                                    <div class="timeline-dot"><i class="fa-solid fa-calendar-check text-[10px]"></i></div>
                                    <div class="timeline-label">Booked</div>
                                </div>
                                
                                <div class="timeline-step <?php echo $activeIndex >= 1 ? ($activeIndex > 1 ? 'completed' : 'active') : ''; ?>">
                                    <div class="timeline-dot"><i class="fa-solid fa-truck-pickup text-[10px]"></i></div>
                                    <div class="timeline-label">Pickup</div>
                                </div>
                                
                                <div class="timeline-step <?php echo $activeIndex >= 2 ? ($activeIndex > 2 ? 'completed' : 'active') : ''; ?>">
                                    <div class="timeline-dot"><i class="fa-solid fa-screwdriver-wrench text-[10px]"></i></div>
                                    <div class="timeline-label">Repair</div>
                                </div>
                                
                                <div class="timeline-step <?php echo $activeIndex >= 3 ? ($activeIndex > 3 ? 'completed' : 'active') : ''; ?>">
                                    <div class="timeline-dot"><i class="fa-solid fa-truck-fast text-[10px]"></i></div>
                                    <div class="timeline-label">Delivery</div>
                                </div>

                                <div class="timeline-step <?php echo $activeIndex >= 4 ? 'completed' : ''; ?>">
                                    <div class="timeline-dot"><i class="fa-solid fa-flag-checkered text-[10px]"></i></div>
                                    <div class="timeline-label">Done</div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Stats Overview -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="glass-card p-6 border-l-4 border-primary animate-slide-up">
                        <div class="flex justify-between items-start">
                            <?php 
                                $bCountQuery = "SELECT COUNT(*) as total FROM bookings WHERE user_id = ?";
                                $bCountRes = executeQuery($bCountQuery, [$userId], 'i');
                                $totalBookings = $bCountRes ? $bCountRes->fetch_assoc()['total'] : 0;
                            ?>
                            <div>
                                <div class="text-xs font-bold text-muted uppercase mb-1">Total Bookings</div>
                                <div class="text-3xl font-bold"><?php echo $totalBookings; ?></div>
                            </div>
                            <div class="bg-blue-100 p-2 rounded-lg text-primary">
                                <i class="fa-solid fa-calendar-check text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="glass-card p-6 border-l-4 border-secondary animate-slide-up" style="animation-delay: 0.1s">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="text-xs font-bold text-muted uppercase mb-1">Active Repairs</div>
                                <div class="text-3xl font-bold"><?php echo $activeServices; ?></div>
                            </div>
                            <div class="bg-indigo-100 p-2 rounded-lg text-secondary">
                                <i class="fa-solid fa-screwdriver-wrench text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="glass-card p-6 border-l-4 border-success animate-slide-up" style="animation-delay: 0.2s">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="text-xs font-bold text-muted uppercase mb-1">Vehicles Registered</div>
                                <div class="text-3xl font-bold"><?php echo $totalVehicles; ?></div>
                            </div>
                            <div class="bg-emerald-100 p-2 rounded-lg text-success">
                                <i class="fa-solid fa-car-side text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="glass-card p-6 border-l-4 border-warning animate-slide-up" style="animation-delay: 0.3s">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="text-xs font-bold text-muted uppercase mb-1">Services Completed</div>
                                <div class="text-3xl font-bold"><?php echo $completedServices; ?></div>
                            </div>
                            <div class="bg-amber-100 p-2 rounded-lg text-warning">
                                <i class="fa-solid fa-circle-check text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Recent Bookings -->
                    <div class="lg:col-span-2">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-900">Recent Service History</h2>
                            <a href="my_bookings.php" class="text-primary font-black text-xs uppercase tracking-widest hover:underline">View Full History</a>
                        </div>
                        
                        <div class="flex flex-col gap-4">
                            <?php 
                            // Fetch recent bookings with billing info
                            $recentQuery = "SELECT 
                                b.id, b.booking_number, b.service_type, b.service_category, b.status, 
                                b.preferred_date, b.created_at, b.final_cost, b.is_billed, b.service_notes,
                                v.make, v.model, v.year, v.license_plate
                                FROM bookings b
                                INNER JOIN vehicles v ON b.vehicle_id = v.id
                                WHERE b.user_id = ?
                                ORDER BY b.created_at DESC
                                LIMIT 3";
                            $recentResult = executeQuery($recentQuery, [$userId], 'i');
                            ?>
                            <?php if (!$recentResult || $recentResult->num_rows == 0): ?>
                                <div class="glass-card p-12 text-center text-muted">
                                    <i class="fa-solid fa-ghost text-5xl mb-4 text-gray-200"></i>
                                    <p>No bookings yet. Start your first service!</p>
                                </div>
                            <?php else: ?>
                                <?php while ($booking = $recentResult->fetch_assoc()): ?>
                                    <div class="glass-card p-0 transition-all hover:border-primary overflow-hidden">
                                        <div class="p-5 flex justify-between items-center">
                                            <div class="flex items-center gap-4">
                                                <div class="bg-gray-100 p-3 rounded-xl border border-gray-200 text-muted">
                                                    <i class="fa-solid fa-car-wrench text-xl"></i>
                                                </div>
                                                <div>
                                                    <div class="font-bold text-lg mb-1"><?php echo htmlspecialchars($booking['service_type']); ?></div>
                                                    <div class="text-xs text-muted">
                                                        #<?php echo $booking['booking_number']; ?> • 
                                                        <?php echo date('M d, Y', strtotime($booking['preferred_date'])); ?> •
                                                        <span class="font-bold text-main uppercase"><?php echo htmlspecialchars($booking['make'] . ' ' . $booking['model']); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <span class="badge <?php echo getStatusBadgeClass($booking['status']); ?> uppercase text-[10px]"><?php echo formatStatusLabel($booking['status']); ?></span>
                                                <div class="mt-2 text-primary font-bold text-sm"><a href="track_service.php?id=<?php echo $booking['id']; ?>"><i class="fa-solid fa-arrow-right"></i></a></div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($booking['is_billed'] || $booking['final_cost'] > 0): ?>
                                        <div class="bg-blue-50/50 px-5 py-3 border-t border-blue-100/50 flex justify-between items-center">
                                            <div class="flex items-center gap-2">
                                                <i class="fa-solid fa-file-invoice-dollar text-primary"></i>
                                                <span class="text-xs font-bold text-blue-900">Total Bill: ₹<?php echo number_format($booking['final_cost'], 2); ?></span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                 <span class="text-[10px] text-muted italic line-clamp-1 max-w-[200px]"><?php echo htmlspecialchars($booking['service_notes'] ?? ''); ?></span>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right Column: Quick Actions & My Garage -->
                    <div class="flex flex-col gap-8">
                        <div>
                             <h2 class="text-xl font-bold mb-6">Quick Actions</h2>
                             <div class="grid grid-cols-2 gap-4">
                                 <a href="book_service.php" class="glass-card p-4 flex flex-col items-center gap-3 hover:bg-blue-50 transition-all group border-none">
                                     <div class="w-12 h-12 rounded-2xl bg-blue-100 text-primary flex items-center justify-center text-xl group-hover:scale-110 group-hover:bg-primary group-hover:text-white transition-all"><i class="fa-solid fa-plus-circle"></i></div>
                                     <span class="font-bold text-xs uppercase tracking-tight">New Booking</span>
                                 </a>
                                 <a href="pickup_delivery.php" class="glass-card p-4 flex flex-col items-center gap-3 hover:bg-indigo-50 transition-all group border-none">
                                     <div class="w-12 h-12 rounded-2xl bg-indigo-100 text-secondary flex items-center justify-center text-xl group-hover:scale-110 group-hover:bg-secondary group-hover:text-white transition-all"><i class="fa-solid fa-truck"></i></div>
                                     <span class="font-bold text-xs uppercase tracking-tight">Logistics</span>
                                 </a>
                             </div>
                        </div>

                        <div>
                            <div class="flex justify-between items-center mb-6">
                                <h2 class="text-xl font-bold">My Garage</h2>
                                <a href="my_vehicles.php" class="text-primary font-bold text-sm hover:underline">Manage</a>
                            </div>
                            <div class="flex flex-col gap-3">
                                <?php if (empty($vehicles)): ?>
                                    <div class="glass-card p-8 text-center text-muted italic text-sm border-dashed border-2">No vehicles registered.</div>
                                <?php else: ?>
                                    <?php foreach (array_slice($vehicles, 0, 3) as $vehicle): ?>
                                            <a href="my_vehicles.php#vehicle-<?php echo $vehicle['id']; ?>" class="glass-card p-4 flex items-center gap-4 hover:border-primary transition-all w-full text-inherit no-underline">
                                                <div class="w-12 h-12 rounded-xl bg-gray-100 border border-gray-100 flex items-center justify-center text-muted"><i class="fa-solid fa-car"></i></div>
                                                <div class="flex-1">
                                                    <div class="font-bold text-sm"><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></div>
                                                    <div class="text-[10px] text-muted uppercase font-bold"><?php echo $vehicle['license_plate']; ?></div>
                                                </div>
                                                <i class="fa-solid fa-chevron-right text-[10px] text-gray-300"></i>
                                            </a>
                                    <?php endforeach; ?>
                                    <?php if (count($vehicles) > 3): ?>
                                        <a href="my_vehicles.php" class="btn btn-white btn-sm w-full text-xs font-bold border-none shadow-sm">View All (<?php echo count($vehicles); ?>)</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
</body>
</html>