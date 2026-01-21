<?php 
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

// Require mechanic role
if ($_SESSION['user_role'] !== 'mechanic') {
    header('Location: login.php');
    exit;
}

$conn = getDbConnection();
$user_id = getCurrentUserId();
$current_page = 'mechanic_dashboard.php';
$activeTab = $_GET['tab'] ?? 'jobs';

// Fetch mechanic and user info
$mechanicQuery = "SELECT m.*, u.name, u.email, u.phone, u.profile_image 
                  FROM mechanics m 
                  JOIN users u ON m.user_id = u.id 
                  WHERE u.id = ?";
$mechanicRes = executeQuery($mechanicQuery, [$user_id], 'i');
$mechanic = $mechanicRes->fetch_assoc();

if (!$mechanic) {
    die("Error: Mechanic record not found.");
}

$success_msg = '';
$error_msg = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = sanitizeInput($_POST['name']);
    // Removed phone, specialization, and years_experience from the update logic as per user request
    
    // Update basic user info
    $updateUserQuery = "UPDATE users SET name = ? WHERE id = ?";
    if (executeQuery($updateUserQuery, [$name, $user_id], 'si')) {
        $_SESSION['user_name'] = $name;
        $success_msg = "Profile updated successfully!";
        // Refresh mechanic data
        $mechanicRes = executeQuery($mechanicQuery, [$user_id], 'i');
        $mechanic = $mechanicRes->fetch_assoc();
    } else {
        $error_msg = "Error updating profile.";
    }
}

// Handle Job Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $booking_id = intval($_POST['booking_id']);
    $new_status = $_POST['status'];
    $mechanic_fee = floatval($_POST['mechanic_fee'] ?? 0);
    $service_notes = $_POST['service_notes'] ?? '';
    
    // Repair Items (parts/products)
    $item_names = $_POST['item_name'] ?? [];
    $item_types = $_POST['item_type'] ?? [];
    $item_prices = $_POST['item_price'] ?? [];
    $item_quantities = $_POST['item_qty'] ?? [];

    $conn->begin_transaction();
    try {
        if ($new_status === 'completed') {
            // Calculate parts total cost
            $parts_total = 0;
            foreach ($item_names as $i => $name) {
                if (!empty($name)) {
                    $price = floatval($item_prices[$i]);
                    $qty = intval($item_quantities[$i]);
                    $total = $price * $qty;
                    $parts_total += $total;
                    
                    $insertPart = "INSERT INTO parts_used (booking_id, part_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)";
                    executeQuery($insertPart, [$booking_id, $name, $qty, $price, $total], 'isidd');
                }
            }

            // Check if booking has pickup/delivery
            $checkQuery = "SELECT has_pickup_delivery FROM bookings WHERE id = ?";
            $checkRes = executeQuery($checkQuery, [$booking_id], 'i');
            $bookingData = $checkRes->fetch_assoc();
            $hasPickupDelivery = $bookingData['has_pickup_delivery'] ?? false;
            
            if ($hasPickupDelivery) {
                $finalStatus = 'ready_for_delivery';
                $finalMsg = "Repair Completed. Mechanic Fee: ₹" . number_format($mechanic_fee, 2) . ". Parts Total: ₹" . number_format($parts_total, 2) . ". Ready for delivery.";
                
                // NEW: Ensure the delivery task is scheduled so drivers can see it
                $updateDelivery = "UPDATE pickup_delivery SET status = 'scheduled' WHERE booking_id = ? AND type = 'delivery' AND status = 'pending'";
                executeQuery($updateDelivery, [$booking_id], 'i');
            } else {
                $finalStatus = 'completed';
                $finalMsg = "Repair Completed. Mechanic Fee: ₹" . number_format($mechanic_fee, 2) . ". Parts Total: ₹" . number_format($parts_total, 2) . ". Ready for customer pickup.";
            }
            
            // Update booking status, mechanic fee, and add to final_cost
            $updateBookingQuery = "UPDATE bookings SET status = ?, mechanic_fee = ?, service_notes = ?, completion_date = NOW(), progress_percentage = 100, is_billed = TRUE, final_cost = IFNULL(final_cost, 0) + ? + ? WHERE id = ? AND mechanic_id = ?";
            executeQuery($updateBookingQuery, [$finalStatus, $mechanic_fee, $service_notes, $mechanic_fee, $parts_total, $booking_id, $mechanic['id']], 'sdsddii');

            // Insert service update
            $insertUpdateQuery = "INSERT INTO service_updates (booking_id, status, message, progress_percentage, updated_by) VALUES (?, ?, ?, ?, ?)";
            executeQuery($insertUpdateQuery, [$booking_id, $finalStatus, $finalMsg, 100, $user_id], 'issii');

            // Make mechanic available
            executeQuery("UPDATE mechanics SET is_available = TRUE WHERE id = ?", [$mechanic['id']], 'i');

        } else { // For 'in_progress'
            $updateBookingQuery = "UPDATE bookings SET status = ?, progress_percentage = 50 WHERE id = ? AND mechanic_id = ?";
            executeQuery($updateBookingQuery, [$new_status, $booking_id, $mechanic['id']], 'sii');

            // Insert service update
            $insertUpdateQuery = "INSERT INTO service_updates (booking_id, status, message, progress_percentage, updated_by) VALUES (?, ?, ?, ?, ?)";
            executeQuery($insertUpdateQuery, [$booking_id, $new_status, "Work started on the vehicle.", 50, $user_id], 'issii');
        }
        
        $conn->commit();
        $success_msg = "Job status updated!";
    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = "Error updating job status: " . $e->getMessage();
    }
}

// Check if mechanic already has an active task
$hasActiveJobQuery = "SELECT COUNT(*) as active_count FROM bookings WHERE mechanic_id = ? AND status IN ('confirmed', 'in_progress')";
$hasActiveJobRes = executeQuery($hasActiveJobQuery, [$mechanic['id']], 'i');
$hasActiveJob = $hasActiveJobRes->fetch_assoc()['active_count'] > 0;

// Handle Job Acceptance (Self-Assignment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_job'])) {
    if ($hasActiveJob) {
        $error_msg = "You already have an active job. Complete it before accepting another.";
    } else {
        $booking_id = intval($_POST['booking_id']);
        
        $conn->begin_transaction();
        try {
            // Assign this mechanic to the booking
            $assignQuery = "UPDATE bookings SET mechanic_id = ?, status = 'confirmed' WHERE id = ? AND mechanic_id IS NULL";
            $result = executeQuery($assignQuery, [$mechanic['id'], $booking_id], 'ii');
            
            if ($result) {
                // Set mechanic as unavailable
                executeQuery("UPDATE mechanics SET is_available = FALSE WHERE id = ?", [$mechanic['id']], 'i');
                
                // Add service update
                $insertUpdate = "INSERT INTO service_updates (booking_id, status, message, progress_percentage, updated_by) VALUES (?, 'confirmed', 'Mechanic assigned and confirmed.', 10, ?)";
                executeQuery($insertUpdate, [$booking_id, $user_id], 'ii');
                
                $conn->commit();
                $success_msg = "Job accepted successfully! You are now assigned to this booking.";
            } else {
                $conn->rollback();
                $error_msg = "This job has already been assigned to another mechanic.";
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Error accepting job: " . $e->getMessage();
        }
    }
}

// Fetch Available Jobs (Unassigned)
// Only show if:
// 1. Drop-off (has_pickup_delivery = 0) and status 'pending'
// 2. Pickup (has_pickup_delivery = 1) and status 'confirmed' (driver finished pickup)
$availableJobsQuery = "SELECT b.*, v.make, v.model, v.year, v.license_plate, v.type, u.name as customer_name, u.phone as customer_phone
                       FROM bookings b 
                       JOIN vehicles v ON b.vehicle_id = v.id 
                       JOIN users u ON b.user_id = u.id 
                       WHERE b.mechanic_id IS NULL 
                       AND (
                           (b.has_pickup_delivery = 0 AND b.status = 'pending')
                           OR
                           (b.has_pickup_delivery = 1 AND b.status = 'confirmed')
                       )
                       ORDER BY b.created_at DESC";
$availableJobsResult = executeQuery($availableJobsQuery, [], '');
$availableJobs = [];
if ($availableJobsResult) {
    while ($row = $availableJobsResult->fetch_assoc()) {
        $availableJobs[] = $row;
    }
}

// Fetch Active Jobs
$activeJobsQuery = "SELECT b.*, v.make, v.model, v.year, v.license_plate, u.name as customer_name 
                    FROM bookings b 
                    JOIN vehicles v ON b.vehicle_id = v.id 
                    JOIN users u ON b.user_id = u.id 
                    WHERE b.mechanic_id = ? AND b.status IN ('pending', 'confirmed', 'in_progress') 
                    ORDER BY b.preferred_date ASC";
$activeJobsResult = executeQuery($activeJobsQuery, [$mechanic['id']], 'i');
$activeJobs = [];
if ($activeJobsResult) {
    while ($row = $activeJobsResult->fetch_assoc()) {
        $activeJobs[] = $row;
    }
}

// Fetch History
$historyQuery = "SELECT b.*, v.make, v.model, v.year, u.name as customer_name 
                 FROM bookings b 
                 JOIN vehicles v ON b.vehicle_id = v.id 
                 JOIN users u ON b.user_id = u.id 
                 WHERE b.mechanic_id = ? AND b.status IN ('completed', 'delivered', 'ready_for_delivery') 
                 ORDER BY b.updated_at DESC LIMIT 20";
$historyResult = executeQuery($historyQuery, [$mechanic['id']], 'i');
$history = [];
if ($historyResult) {
    while ($row = $historyResult->fetch_assoc()) {
        $history[] = $row;
    }
}

$page_title = 'Mechanic Dashboard';
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
                <?php if ($success_msg): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl relative mb-6 animate-fade-in" role="alert">
                        <span class="block sm:inline font-bold"><i class="fa-solid fa-circle-check mr-2"></i> <?php echo htmlspecialchars($success_msg); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_msg): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl relative mb-6 animate-fade-in" role="alert">
                        <span class="block sm:inline font-bold"><i class="fa-solid fa-circle-xmark mr-2"></i> <?php echo htmlspecialchars($error_msg); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($activeTab === 'jobs'): ?>
                    <!-- Jobs Section with Tabs -->
                    <div class="flex justify-between items-center mb-8">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Repair Jobs</h1>
                            <p class="text-muted">Manage your assignments and find new work.</p>
                        </div>
                    </div>

                    <!-- Sub-tabs for Active and Available -->
                    <div class="flex gap-4 mb-6 border-b border-gray-200">
                        <a href="?tab=jobs&subtab=active" class="px-4 py-2 font-bold <?php echo (!isset($_GET['subtab']) || $_GET['subtab'] == 'active') ? 'text-primary border-b-2 border-primary' : 'text-muted hover:text-gray-700'; ?>">
                            My Active Jobs <?php if (!empty($activeJobs)): ?><span class="badge badge-primary ml-2"><?php echo count($activeJobs); ?></span><?php endif; ?>
                        </a>
                        <a href="?tab=jobs&subtab=available" class="px-4 py-2 font-bold <?php echo (isset($_GET['subtab']) && $_GET['subtab'] == 'available') ? 'text-primary border-b-2 border-primary' : 'text-muted hover:text-gray-700'; ?>">
                            Available Jobs <?php if (!empty($availableJobs)): ?><span class="badge badge-success ml-2"><?php echo count($availableJobs); ?></span><?php endif; ?>
                        </a>
                    </div>

                    <?php $subtab = $_GET['subtab'] ?? 'active'; ?>
                    
                    <?php if ($subtab == 'active'): ?>
                    <!-- Active Jobs -->

                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                        <?php if (empty($activeJobs)): ?>
                            <div class="col-span-1 xl:col-span-2 card p-16 text-center text-muted border-dashed bg-gray-50/50">
                                <div class="w-24 h-24 bg-white shadow-sm rounded-full flex items-center justify-center mx-auto mb-6">
                                    <i class="fa-solid fa-screwdriver-wrench text-5xl text-gray-300"></i>
                                </div>
                                <h3 class="font-bold text-2xl text-gray-400">No Assignments Yet</h3>
                                <p class="max-w-sm mx-auto mt-2">New job requests will appear here once they are confirmed by the admin.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($activeJobs as $job): ?>
                                <div class="card overflow-hidden hover:shadow-2xl transition-all duration-300 group relative border-0 shadow-lg">
                                    <div class="absolute top-0 w-full h-1.5 bg-gradient-to-r from-blue-500 to-purple-600"></div>
                                    <div class="flex flex-col h-full">
                                        <div class="p-8 flex-1">
                                            <div class="flex justify-between items-start mb-6">
                                                <div class="flex items-center gap-2">
                                                    <span class="badge <?php echo getStatusBadgeClass($job['status']); ?> px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider shadow-sm">
                                                        <i class="fa-solid fa-circle text-[6px] mr-1.5"></i> <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
                                                    </span>
                                                    <?php if ($job['status'] === 'in_progress'): ?>
                                                        <span class="animate-pulse w-2 h-2 bg-green-500 rounded-full"></span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="font-mono text-xs font-bold text-gray-400 bg-gray-50 px-2 py-1 rounded-md border border-gray-100">#<?php echo $job['booking_number']; ?></span>
                                            </div>
                                            
                                            <h3 class="text-2xl md:text-3xl font-black text-gray-900 mb-2 leading-tight">
                                                <?php echo $job['year'] . ' ' . $job['make'] . ' ' . $job['model']; ?>
                                            </h3>
                                            
                                            <div class="flex flex-wrap gap-3 mb-6">
                                                <div class="flex items-center gap-2 text-xs font-bold text-gray-500 bg-gray-50 px-3 py-1.5 rounded-full border border-gray-100">
                                                    <i class="fa-solid fa-user text-primary/70"></i> <?php echo htmlspecialchars($job['customer_name']); ?>
                                                </div>
                                                <div class="flex items-center gap-2 text-xs font-bold text-gray-500 bg-gray-50 px-3 py-1.5 rounded-full border border-gray-100 font-mono">
                                                    <i class="fa-solid fa-hashtag text-primary/70"></i> <?php echo htmlspecialchars($job['license_plate']); ?>
                                                </div>
                                            </div>

                                            <div class="bg-blue-50/50 p-5 rounded-2xl border border-blue-50 flex gap-4 transition-colors group-hover:bg-blue-50">
                                                <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-blue-500 shrink-0">
                                                    <i class="fa-solid fa-clipboard-list"></i>
                                                </div>
                                                <div>
                                                    <div class="text-[10px] uppercase font-bold text-blue-400 mb-1 tracking-wider">Service Requirements</div>
                                                    <div class="font-bold text-gray-900 text-sm md:text-base"><?php echo htmlspecialchars($job['service_type']); ?></div>
                                                    <div class="text-xs text-gray-500 mt-1 italic leading-relaxed">
                                                        <i class="fa-solid fa-quote-left mr-1 opacity-50"></i>
                                                        <?php echo htmlspecialchars($job['notes'] ?? 'No special instructions provided.'); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="p-6 bg-gray-50 border-t border-gray-100">
                                            <?php if ($job['status'] === 'confirmed' || $job['status'] === 'pending'): ?>
                                                 <form method="POST">
                                                     <input type="hidden" name="booking_id" value="<?php echo $job['id']; ?>">
                                                     <input type="hidden" name="status" value="in_progress">
                                                     <button type="submit" name="update_status" class="btn btn-primary w-full py-3.5 font-bold shadow-lg shadow-blue-500/20 rounded-xl hover:scale-[1.02] transition-transform">
                                                         <i class="fa-solid fa-play mr-2"></i> Start Diagnosis / Repair
                                                     </button>
                                                 </form>
                                             <?php elseif ($job['status'] === 'in_progress'): ?>
                                                <button class="btn btn-success w-full py-3.5 font-bold shadow-lg shadow-green-500/20 rounded-xl hover:scale-[1.02] transition-transform" onclick="openCompleteModal(<?php echo $job['id']; ?>)">
                                                    <i class="fa-solid fa-check-to-slot mr-2"></i> Finalize & Generate Bill
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php elseif ($subtab == 'available'): ?>
                    <!-- Available Jobs -->
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                        <?php if (empty($availableJobs)): ?>
                            <div class="col-span-1 xl:col-span-2 card p-16 text-center text-muted border-dashed">
                                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6">
                                    <i class="fa-solid fa-clipboard-list text-4xl opacity-20"></i>
                                </div>
                                <h3 class="font-bold text-2xl text-gray-400">No Jobs Available</h3>
                                <p class="max-w-sm mx-auto mt-2">There are currently no pending jobs available for assignment.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($availableJobs as $job): ?>
                                <div class="card overflow-hidden hover:shadow-2xl transition-all duration-300 border border-gray-100 group">
                                    <div class="p-8 flex flex-col h-full">
                                        <div class="flex justify-between items-start mb-6">
                                            <span class="badge badge-success px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider shadow-sm flex items-center gap-2">
                                                <span class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></span> New Request
                                            </span>
                                            <div class="text-right">
                                                <span class="text-[10px] text-muted font-bold block">Booked on</span>
                                                <span class="text-xs font-bold text-gray-700"><?php echo date('M d, H:i', strtotime($job['created_at'])); ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center gap-4 mb-6">
                                            <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center text-gray-400 text-xl">
                                                <i class="fa-solid fa-car"></i>
                                            </div>
                                            <div>
                                                <h3 class="text-xl font-black text-gray-900 leading-tight">
                                                    <?php echo $job['year'] . ' ' . $job['make'] . ' ' . $job['model']; ?>
                                                </h3>
                                                <div class="flex gap-2 mt-1">
                                                    <span class="text-[10px] font-bold text-gray-500 bg-gray-50 px-2 py-0.5 rounded border border-gray-100">
                                                        <?php echo htmlspecialchars($job['license_plate']); ?>
                                                    </span>
                                                    <span class="text-[10px] font-bold text-blue-500 bg-blue-50 px-2 py-0.5 rounded border border-blue-50">
                                                        <?php echo htmlspecialchars($job['type']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-100 mb-6 flex-1">
                                            <label class="text-[10px] uppercase font-bold text-muted mb-2 block tracking-widest">Initial Report</label>
                                            <p class="text-sm font-bold text-gray-800 line-clamp-2">
                                                <?php echo htmlspecialchars($job['service_type']); ?>
                                            </p>
                                            <?php if(!empty($job['notes'])): ?>
                                                <p class="text-xs text-gray-500 mt-1 line-clamp-1 italic">"<?php echo htmlspecialchars($job['notes']); ?>"</p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to accept this job? You will be marked as unavailable for other tasks.');">
                                            <input type="hidden" name="booking_id" value="<?php echo $job['id']; ?>">
                                            <button type="submit" name="accept_job" class="btn btn-primary w-full py-3.5 font-bold shadow-lg shadow-blue-500/20 rounded-xl hover:scale-[1.02] transition-transform">
                                                <i class="fa-solid fa-hand-point-up mr-2"></i> Accept Assignment
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                <?php elseif ($activeTab === 'history'): ?>
                    <!-- History Section -->
                    <div class="flex justify-between items-center mb-8">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Work History</h1>
                            <p class="text-muted">Tracking your professional service journey.</p>
                        </div>
                    </div>
                    
                    <div class="card p-0 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead style="background: #F8FAFC; border-bottom: 1px solid var(--border);">
                                    <tr class="text-xs font-bold uppercase text-muted tracking-wider">
                                        <th class="p-5">Completed Date</th>
                                        <th class="p-5">Vehicle & Customer</th>
                                        <th class="p-5">Service Type</th>
                                        <th class="p-5">Service Bill</th>
                                        <th class="p-5 text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm">
                                    <?php if (empty($history)): ?>
                                        <tr>
                                            <td colspan="5" class="p-16 text-center text-muted">
                                                <i class="fa-solid fa-clock-rotate-left text-6xl mb-4 opacity-10"></i>
                                                <p>No completed jobs in your history yet.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($history as $h): ?>
                                            <tr class="border-t border-gray-100 hover:bg-gray-50/50 transition-colors">
                                                <td class="p-5 font-medium text-gray-500">
                                                    <?php echo date('M d, Y', strtotime($h['updated_at'])); ?>
                                                </td>
                                                <td class="p-5">
                                                    <div class="font-bold text-gray-900"><?php echo $h['year'] . ' ' . $h['make'] . ' ' . $h['model']; ?></div>
                                                    <div class="text-xs text-muted font-medium"><?php echo htmlspecialchars($h['customer_name']); ?></div>
                                                </td>
                                                <td class="p-5 text-gray-700"><?php echo htmlspecialchars($h['service_type']); ?></td>
                                                <td class="p-5 font-black text-gray-900">₹<?php echo number_format($h['bill_amount'] ?? 0, 2); ?></td>
                                                <td class="p-5 text-right">
                                                    <span class="badge <?php echo getStatusBadgeClass($h['status']); ?> px-3 py-1 rounded-full text-[10px] font-bold uppercase">
                                                        <?php echo formatStatusLabel($h['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($activeTab === 'profile'): ?>
                    <!-- Profile Section -->
                    <div class="max-w-4xl mx-auto">
                        <div class="mb-8">
                            <h1 class="text-3xl font-bold text-gray-900">Professional Identity</h1>
                            <p class="text-muted">Your public-facing expertise and background settings.</p>
                        </div>

                        <div class="card overflow-hidden">
                            <div class="bg-gradient-to-r from-primary/10 to-transparent p-10 flex flex-col md:flex-row items-center gap-8 border-b border-gray-100">
                                <div class="relative">
                                    <img src="<?php echo $mechanic['profile_image'] ? $mechanic['profile_image'] : 'https://ui-avatars.com/api/?name=' . urlencode($mechanic['name']) . '&background=0D9488&color=fff&size=256'; ?>" 
                                         class="w-32 h-32 rounded-3xl object-cover shadow-2xl border-4 border-white" alt="Profile">
                                    <div class="absolute -bottom-2 -right-2 w-10 h-10 bg-primary text-white rounded-xl flex items-center justify-center shadow-lg border-2 border-white cursor-pointer">
                                        <i class="fa-solid fa-camera"></i>
                                    </div>
                                </div>
                                <div class="text-center md:text-left">
                                    <h3 class="text-3xl font-black text-gray-900 mb-1"><?php echo htmlspecialchars($mechanic['name']); ?></h3>
                                    <p class="text-lg text-primary font-bold mb-4 uppercase tracking-widest text-sm"><?php echo htmlspecialchars($mechanic['specialization']); ?> Expert</p>
                                    <div class="flex flex-wrap gap-3 justify-center md:justify-start">
                                        <span class="px-4 py-1.5 bg-white rounded-full text-xs font-bold text-muted shadow-sm border border-gray-100"><i class="fa-solid fa-envelope mr-1.5 opacity-60"></i> <?php echo htmlspecialchars($mechanic['email']); ?></span>
                                        <span class="px-4 py-1.5 bg-white rounded-full text-xs font-bold text-muted shadow-sm border border-gray-100"><i class="fa-solid fa-shield mr-1.5 opacity-60"></i> Verified Professional</span>
                                    </div>
                                </div>
                            </div>

                            <div class="p-10">
                                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <div class="form-group flex flex-col gap-2">
                                        <label class="text-xs font-black uppercase text-gray-500 ml-1">Full Legal Name</label>
                                        <input type="text" name="name" class="form-control h-14 px-5 text-lg font-bold" value="<?php echo htmlspecialchars($mechanic['name']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group flex flex-col gap-2">
                                        <label class="text-xs font-black uppercase text-gray-500 ml-1">Contact Phone (Locked)</label>
                                        <div class="relative h-14">
                                            <input type="text" class="form-control h-full px-5 text-lg font-bold bg-gray-50 cursor-not-allowed border-dashed" value="<?php echo htmlspecialchars($mechanic['phone']); ?>" disabled>
                                            <i class="fa-solid fa-lock absolute right-5 top-1/2 -translate-y-1/2 text-muted opacity-40"></i>
                                        </div>
                                        <p class="text-[10px] text-muted italic ml-1">Contact platform support to update registered logistics number.</p>
                                    </div>

                                    <div class="form-group flex flex-col gap-2">
                                        <label class="text-xs font-black uppercase text-gray-500 ml-1">Repair Specialization (Locked)</label>
                                        <div class="relative h-14">
                                            <input type="text" class="form-control h-full px-5 text-lg font-bold bg-gray-50 cursor-not-allowed border-dashed" value="<?php echo htmlspecialchars($mechanic['specialization']); ?>" disabled>
                                            <i class="fa-solid fa-lock absolute right-5 top-1/2 -translate-y-1/2 text-muted opacity-40"></i>
                                        </div>
                                    </div>

                                    <div class="form-group flex flex-col gap-2">
                                        <label class="text-xs font-black uppercase text-gray-500 ml-1">Years of Experience (Locked)</label>
                                        <div class="relative h-14">
                                            <input type="text" class="form-control h-full px-5 text-lg font-bold bg-gray-50 cursor-not-allowed border-dashed" value="<?php echo $mechanic['years_experience']; ?> Years" disabled>
                                            <i class="fa-solid fa-lock absolute right-5 top-1/2 -translate-y-1/2 text-muted opacity-40"></i>
                                        </div>
                                    </div>

                                    <div class="md:col-span-2 pt-8 border-t border-gray-100 flex justify-end">
                                        <button type="submit" name="update_profile" class="btn btn-primary h-14 px-12 text-lg font-bold shadow-xl shadow-blue-100 transition-all active:scale-95">
                                            <i class="fa-solid fa-floppy-disk mr-2"></i> Save Profile Updates
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Completion Modal -->
    <div id="completeModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 1.5rem;">
        <div style="background: white; border-radius: 2rem; max-width: 550px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden; animation: modalEnter 0.4s cubic-bezier(0.16, 1, 0.3, 1);">
            <div class="p-10">
                <div class="w-16 h-16 bg-green-100 text-green-600 rounded-2xl flex items-center justify-center mb-6 text-2xl shadow-sm">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
                <h2 class="text-3xl font-black mb-2">Service Finalization</h2>
                <p class="text-muted text-lg mb-8 font-medium">Please detail the work performed and the final costs for the customer.</p>
                
                <form method="POST">
                    <input type="hidden" name="booking_id" id="modal_booking_id">
                    <input type="hidden" name="status" value="completed">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div class="form-group">
                            <label class="text-xs font-black uppercase text-gray-400 mb-2 block ml-1">Labor / Mechanic Fee (₹) *</label>
                            <div class="relative">
                                <span class="absolute left-5 top-1/2 -translate-y-1/2 font-bold text-gray-400">₹</span>
                                <input type="number" name="mechanic_fee" oninput="calculateTotal()" class="form-control h-14 pl-10 pr-5 text-lg font-black bg-gray-50 border-gray-200 focus:bg-white transition-all rounded-2xl" 
                                       required placeholder="0.00" min="0" step="0.01">
                            </div>
                        </div>
                        <div class="flex items-center justify-center bg-primary/5 rounded-2xl border border-primary/10 p-4">
                            <div class="text-center">
                                <p class="text-[9px] font-black uppercase text-primary tracking-widest mb-1">Live Bill Preview</p>
                                <p class="text-2xl font-black text-primary" id="pricePreview">₹0.00</p>
                            </div>
                        </div>
                    </div>

                    <div class="mb-10">
                        <!-- Invoice Header -->
                        <div class="flex justify-between items-end mb-6 border-b border-gray-200 pb-4">
                            <div>
                                <h3 class="text-sm font-black uppercase tracking-widest text-gray-900">Billable Items</h3>
                                <p class="text-xs text-gray-500 mt-1">Add parts and services used in this repair.</p>
                            </div>
                            <button type="button" onclick="addRepairItem()" class="btn btn-sm btn-outline rounded-lg text-xs border-dashed hover:border-solid">
                                <i class="fa-solid fa-plus mr-1"></i> Add Item
                            </button>
                        </div>
                        
                        <!-- Invoice Table Header -->
                        <div class="hidden md:grid grid-cols-12 gap-4 px-2 mb-3">
                            <div class="col-span-1 text-[10px] font-black uppercase text-gray-400 tracking-wider">#</div>
                            <div class="col-span-5 text-[10px] font-black uppercase text-gray-400 tracking-wider">Description</div>
                            <div class="col-span-2 text-[10px] font-black uppercase text-gray-400 tracking-wider text-center">Qty</div>
                            <div class="col-span-2 text-[10px] font-black uppercase text-gray-400 tracking-wider text-right">Price</div>
                            <div class="col-span-2 text-[10px] font-black uppercase text-gray-400 tracking-wider text-right">Amount</div>
                        </div>

                        <div id="repairItemsContainer" class="flex flex-col gap-0 border-t border-gray-100">
                            <!-- Items will be added here -->
                            <div class="repair-item-row grid grid-cols-1 md:grid-cols-12 gap-2 md:gap-4 items-center py-4 border-b border-gray-100 group hover:bg-gray-50/50 transition-colors px-2 relative">
                                <!-- Item Number -->
                                <div class="col-span-1 hidden md:block">
                                    <span class="text-sm font-bold text-gray-300 item-index">01</span>
                                </div>

                                <!-- Description -->
                                <div class="col-span-5">
                                    <label class="md:hidden text-[10px] font-bold text-gray-400 uppercase mb-1 block">Description</label>
                                    <input type="text" name="item_name[]" placeholder="Item Name / Description" 
                                           class="w-full bg-transparent border-0 border-b border-transparent focus:border-primary focus:ring-0 text-sm font-bold text-gray-900 placeholder-gray-300 p-0 transition-all" required>
                                </div>

                                <!-- Qty -->
                                <div class="col-span-2">
                                    <label class="md:hidden text-[10px] font-bold text-gray-400 uppercase mb-1 block">Qty</label>
                                    <input type="number" name="item_qty[]" value="1" min="1" oninput="calculateTotal()" 
                                           class="w-full bg-transparent border-0 border-b border-transparent focus:border-primary focus:ring-0 text-sm font-bold text-gray-900 text-left md:text-center p-0 transition-all" required>
                                </div>

                                <!-- Price -->
                                <div class="col-span-2">
                                    <label class="md:hidden text-[10px] font-bold text-gray-400 uppercase mb-1 block">Price</label>
                                    <div class="flex items-center md:justify-end gap-1">
                                        <span class="text-gray-400 text-xs font-medium">₹</span>
                                        <input type="number" name="item_price[]" placeholder="0.00" step="0.01" oninput="calculateTotal()" 
                                               class="w-full md:w-24 bg-transparent border-0 border-b border-transparent focus:border-primary focus:ring-0 text-sm font-bold text-gray-900 text-left md:text-right p-0 transition-all" required>
                                    </div>
                                </div>

                                <!-- Amount (Calculated) -->
                                <div class="col-span-2 relative">
                                    <label class="md:hidden text-[10px] font-bold text-gray-400 uppercase mb-1 block">Amount</label>
                                    <div class="text-right">
                                        <span class="text-sm font-black text-gray-900 item-amount">₹0.00</span>
                                    </div>
                                    
                                    <!-- Delete Action (Absolute positioned on desktop) -->
                                    <button type="button" onclick="this.closest('.repair-item-row').remove(); calculateTotal(); reindexItems();" 
                                            class="absolute -right-2 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full flex items-center justify-center text-gray-300 hover:text-red-500 hover:bg-red-50 transition-all opacity-100 md:opacity-0 group-hover:opacity-100">
                                        <i class="fa-solid fa-xmark text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-10">
                        <label class="text-xs font-black uppercase text-gray-400 mb-2 block ml-1">Comprehensive Repair Notes</label>
                        <textarea name="service_notes" class="form-control p-5 text-base font-medium bg-gray-50 border-gray-200 focus:bg-white transition-all rounded-2xl" 
                                  rows="3" placeholder="Additional details about the repair..."></textarea>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4">
                        <button type="submit" name="update_status" class="btn btn-primary flex-1 h-16 text-lg font-bold rounded-2xl shadow-lg shadow-blue-100">
                             Finalize Service & Bill
                        </button>
                        <button type="button" class="btn btn-outline flex-1 h-16 text-lg font-bold rounded-2xl hover:bg-gray-50" onclick="document.getElementById('completeModal').style.display='none'">
                            Discard
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        @keyframes modalEnter {
            from { opacity: 0; transform: scale(0.95) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
    </style>

    <script>
        function openCompleteModal(id) {
            document.getElementById('modal_booking_id').value = id;
            document.getElementById('completeModal').style.display = 'flex';
            calculateTotal();
        }

        function addRepairItem() {
            const container = document.getElementById('repairItemsContainer');
            const row = document.createElement('div');
            row.className = 'repair-item-row grid grid-cols-1 md:grid-cols-12 gap-2 md:gap-4 items-center py-4 border-b border-gray-100 group hover:bg-gray-50/50 transition-colors px-2 relative animate-fade-in';
            row.innerHTML = `
                <!-- Item Number -->
                <div class="col-span-1 hidden md:block">
                    <span class="text-sm font-bold text-gray-300 item-index">00</span>
                </div>

                <!-- Description -->
                <div class="col-span-5">
                    <label class="md:hidden text-[10px] font-bold text-gray-400 uppercase mb-1 block">Description</label>
                    <input type="text" name="item_name[]" placeholder="Item Name / Description" 
                           class="w-full bg-transparent border-0 border-b border-transparent focus:border-primary focus:ring-0 text-sm font-bold text-gray-900 placeholder-gray-300 p-0 transition-all" required>
                </div>

                <!-- Qty -->
                <div class="col-span-2">
                    <label class="md:hidden text-[10px] font-bold text-gray-400 uppercase mb-1 block">Qty</label>
                    <input type="number" name="item_qty[]" value="1" min="1" oninput="calculateTotal()" 
                           class="w-full bg-transparent border-0 border-b border-transparent focus:border-primary focus:ring-0 text-sm font-bold text-gray-900 text-left md:text-center p-0 transition-all" required>
                </div>

                <!-- Price -->
                <div class="col-span-2">
                    <label class="md:hidden text-[10px] font-bold text-gray-400 uppercase mb-1 block">Price</label>
                    <div class="flex items-center md:justify-end gap-1">
                        <span class="text-gray-400 text-xs font-medium">₹</span>
                        <input type="number" name="item_price[]" placeholder="0.00" step="0.01" oninput="calculateTotal()" 
                               class="w-full md:w-24 bg-transparent border-0 border-b border-transparent focus:border-primary focus:ring-0 text-sm font-bold text-gray-900 text-left md:text-right p-0 transition-all" required>
                    </div>
                </div>

                <!-- Amount (Calculated) -->
                <div class="col-span-2 relative">
                    <label class="md:hidden text-[10px] font-bold text-gray-400 uppercase mb-1 block">Amount</label>
                    <div class="text-right">
                        <span class="text-sm font-black text-gray-900 item-amount">₹0.00</span>
                    </div>
                    
                    <!-- Delete Action -->
                    <button type="button" onclick="this.closest('.repair-item-row').remove(); calculateTotal(); reindexItems();" 
                            class="absolute -right-2 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full flex items-center justify-center text-gray-300 hover:text-red-500 hover:bg-red-50 transition-all opacity-100 md:opacity-0 group-hover:opacity-100">
                        <i class="fa-solid fa-xmark text-xs"></i>
                    </button>
                </div>
            `;
            container.appendChild(row);
            reindexItems();
        }

        function reindexItems() {
            const rows = document.querySelectorAll('.repair-item-row');
            rows.forEach((row, index) => {
                const indexDisplay = row.querySelector('.item-index');
                if (indexDisplay) {
                    indexDisplay.innerText = (index + 1).toString().padStart(2, '0');
                }
            });
        }

        function calculateTotal() {
            let total = 0;
            const laborInput = document.querySelector('input[name="mechanic_fee"]');
            const labor = parseFloat(laborInput.value) || 0;
            
            // Calculate item rows
            const rows = document.querySelectorAll('.repair-item-row');
            let partsTotal = 0;
            
            rows.forEach(row => {
                const qtyInput = row.querySelector('input[name="item_qty[]"]');
                const priceInput = row.querySelector('input[name="item_price[]"]');
                const amountDisplay = row.querySelector('.item-amount');
                
                const qty = parseFloat(qtyInput.value) || 0;
                const price = parseFloat(priceInput.value) || 0;
                const amount = qty * price;
                
                if (amountDisplay) {
                    amountDisplay.innerText = '₹' + amount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
                
                partsTotal += amount;
            });
            
            total = labor + partsTotal;
            
            const totalDisplay = document.getElementById('pricePreview');
            if (totalDisplay) {
                totalDisplay.innerText = '₹' + total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }

        // Close modal if clicked outside
        window.onclick = function(event) {
            const modal = document.getElementById('completeModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>
