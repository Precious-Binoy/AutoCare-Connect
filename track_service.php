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
                     m.id as mechanic_id, u.name as mechanic_name 
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

                    <!-- Top Hero Card -->
                    <div class="flex gap-6 mb-8 flex-col lg:flex-row">
                        <!-- Vehicle Image Card -->
                        <div class="card" style="min-height:250px; background:#000; padding:0; border-radius: var(--radius-lg); overflow:hidden; position:relative; flex:1; display:flex; align-items:stretch;">
                            <!-- Placeholder Image using Unsplash -->
                            <img src="https://images.unsplash.com/photo-1619767886558-efdc259cde1a?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80" alt="Car" style="width:100%; height:100%; object-fit:cover; object-position:center; opacity:0.6; display:block;">
                            <div style="position: absolute; bottom: 0; left: 0; width: 100%; padding: 2rem; background: linear-gradient(transparent, rgba(0,0,0,0.75)); color: white;">
                                 <div class="flex justify-between items-end">
                                     <div>
                                         <h2 class="text-3xl font-bold mb-1 text-white"><?php echo htmlspecialchars($booking['year'] . ' ' . $booking['make'] . ' ' . $booking['model']); ?></h2>
                                         <p class="opacity-80"><?php echo htmlspecialchars($booking['license_plate']); ?></p>
                                         <div class="flex gap-8 mt-6">
                                             <div>
                                                 <div class="text-xs opacity-60 uppercase font-bold tracking-wider">VIN</div>
                                                 <div class="font-mono"><?php echo htmlspecialchars($booking['vin'] ?? 'N/A'); ?></div>
                                             </div>
                                             <div>
                                                 <div class="text-xs opacity-60 uppercase font-bold tracking-wider">Mileage</div>
                                                 <div class="font-mono"><?php echo htmlspecialchars($booking['mileage'] ?? 'N/A'); ?> mi</div>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                            </div>
                        </div>

                        <!-- Mechanic / Status Card -->
                        <div style="width: 100%; max-width: 350px;" class="flex flex-col gap-4">
                            <div class="card p-6 flex items-center gap-4">
                                <div class="relative">
                                    <div class="avatar bg-primary text-white flex items-center justify-center rounded-full text-xl font-bold" style="width: 50px; height: 50px;">
                                        <?php echo substr($booking['mechanic_name'] ?? 'A', 0, 1); ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[10px] text-muted uppercase font-bold tracking-wider mb-1">Assigned Mechanic</div>
                                    <div class="font-bold text-lg"><?php echo htmlspecialchars($booking['mechanic_name'] ?? 'Pending Assignment'); ?></div>
                                </div>
                            </div>

                            <div class="card p-6 flex-1 flex flex-col justify-center" style="background: #EFF6FF; border-color: var(--primary);">
                                 <div class="flex justify-between items-start mb-2">
                                     <div class="text-sm font-bold text-primary">Current Status</div>
                                     <div class="w-2 h-2 rounded-full bg-primary animate-pulse"></div>
                                 </div>
                                 <div class="text-2xl font-bold mb-1"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $booking['status']))); ?></div>
                                 <div class="text-xs text-muted">Created: <?php echo date('M d, g:i A', strtotime($booking['created_at'])); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Tracker Component -->
                    <div class="card p-8 mb-8 overflow-x-auto">
                         <div class="progress-track" style="margin: 1rem 0; min-width: 600px;">
                             <div class="progress-line"></div>
                             <div class="progress-line-fill" style="width: <?php echo ($currentStep - 1) * 25; ?>%;"></div>

                             <!-- Step 1: Booked -->
                             <div class="progress-step">
                                 <div class="step-circle <?php echo $currentStep >= 1 ? 'completed' : ''; ?>"><i class="fa-solid fa-calendar-check"></i></div>
                                 <div class="font-bold text-sm">Booked</div>
                             </div>
                              <!-- Step 2: Confirmed -->
                             <div class="progress-step">
                                 <div class="step-circle <?php echo $currentStep >= 2 ? 'completed' : ''; ?>"><i class="fa-solid fa-check-double"></i></div>
                                 <div class="font-bold text-sm">Confirmed</div>
                             </div>
                              <!-- Step 3: In Service -->
                             <div class="progress-step">
                                 <div class="step-circle <?php echo $currentStep >= 3 ? ($booking['status'] == 'in_progress' ? 'active' : 'completed') : ''; ?>">
                                     <i class="fa-solid fa-gear <?php echo $booking['status'] == 'in_progress' ? 'fa-spin' : ''; ?>"></i>
                                 </div>
                                 <div class="font-bold text-sm text-primary">In Service</div>
                             </div>
                              <!-- Step 4: Ready -->
                             <div class="progress-step">
                                 <div class="step-circle <?php echo $currentStep >= 4 ? 'completed' : ''; ?>"><i class="fa-solid fa-check"></i></div>
                                 <div class="font-bold text-sm text-muted">Ready</div>
                             </div>
                              <!-- Step 5: Completed -->
                             <div class="progress-step">
                                 <div class="step-circle <?php echo $currentStep >= 5 ? 'completed' : ''; ?>"><i class="fa-solid fa-flag-checkered"></i></div>
                                 <div class="font-bold text-sm text-muted">Completed</div>
                             </div>
                         </div>
                    </div>

                    <!-- Details Grid -->
                    <div class="card p-0 overflow-hidden">
                        <div class="flex justify-between items-center p-4 bg-gray-50 border-b border-gray-100">
                             <h3 class="font-bold">Service Details</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 divide-y lg:divide-y-0 lg:divide-x divide-gray-100">
                            <div class="p-6">
                                 <div class="flex items-center gap-2 text-muted text-xs font-bold uppercase mb-2"><i class="fa-solid fa-briefcase"></i> Service Type</div>
                                 <div class="font-bold text-sm"><?php echo htmlspecialchars($booking['service_type']); ?></div>
                            </div>
                            <div class="p-6">
                                 <div class="flex items-center gap-2 text-muted text-xs font-bold uppercase mb-2"><i class="fa-solid fa-clock"></i> Preferred Date</div>
                                 <div class="font-bold text-lg"><?php echo date('M d, g:i A', strtotime($booking['preferred_date'])); ?></div>
                            </div>
                             <div class="p-6">
                                 <div class="flex items-center gap-2 text-muted text-xs font-bold uppercase mb-2"><i class="fa-solid fa-dollar-sign"></i> Estimated Cost</div>
                                 <div class="font-bold text-lg"><?php echo $booking['estimated_cost'] ? '$' . number_format($booking['estimated_cost'], 2) : 'Pending Quote'; ?></div>
                            </div>
                             <div class="p-6">
                                 <div class="flex items-center gap-2 text-muted text-xs font-bold uppercase mb-2"><i class="fa-solid fa-align-left"></i> Notes</div>
                                 <div class="text-sm text-muted"><?php echo htmlspecialchars($booking['notes'] ?? 'No notes provided.'); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
