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
    $bill_amount = $_POST['bill_amount'] ?? null;
    $service_notes = $_POST['service_notes'] ?? '';
    
    $conn->begin_transaction();
    try {
        if ($new_status === 'completed') {
            // Check if booking has pickup/delivery
            $checkQuery = "SELECT has_pickup_delivery FROM bookings WHERE id = ?";
            $checkRes = executeQuery($checkQuery, [$booking_id], 'i');
            $bookingData = $checkRes->fetch_assoc();
            $hasPickupDelivery = $bookingData['has_pickup_delivery'] ?? false;
            
            if ($hasPickupDelivery) {
                // Has delivery - set to ready_for_delivery
                $finalStatus = 'ready_for_delivery';
                $finalMsg = "Repair Completed. Work Done: " . $service_notes . ". Total Bill: ₹" . number_format($bill_amount, 2) . ". Ready for delivery.";
            } else {
                // Self-pickup - set to completed
                $finalStatus = 'completed';
                $finalMsg = "Repair Completed. Work Done: " . $service_notes . ". Total Bill: ₹" . number_format($bill_amount, 2) . ". Ready for customer pickup.";
            }
            
            // Update booking status, bill amount and service notes
            $updateBookingQuery = "UPDATE bookings SET status = ?, bill_amount = ?, service_notes = ?, completion_date = NOW(), progress_percentage = 100, is_billed = TRUE WHERE id = ? AND mechanic_id = ?";
            executeQuery($updateBookingQuery, [$finalStatus, $bill_amount, $service_notes, $booking_id, $mechanic['id']], 'sdsii');

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

// Handle Job Acceptance (Self-Assignment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_job'])) {
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

// Fetch Available Jobs (Unassigned)
$availableJobsQuery = "SELECT b.*, v.make, v.model, v.year, v.license_plate, v.type, u.name as customer_name, u.phone as customer_phone
                       FROM bookings b 
                       JOIN vehicles v ON b.vehicle_id = v.id 
                       JOIN users u ON b.user_id = u.id 
                       WHERE b.mechanic_id IS NULL AND b.status = 'pending'
                       ORDER BY b.created_at DESC";
$availableJobsResult = executeQuery($availableJobsQuery, [], '');
$availableJobs = $availableJobsResult->fetch_all(MYSQLI_ASSOC);

// Fetch Active Jobs
$activeJobsQuery = "SELECT b.*, v.make, v.model, v.year, v.license_plate, u.name as customer_name 
                    FROM bookings b 
                    JOIN vehicles v ON b.vehicle_id = v.id 
                    JOIN users u ON b.user_id = u.id 
                    WHERE b.mechanic_id = ? AND b.status IN ('confirmed', 'in_progress') 
                    ORDER BY b.preferred_date ASC";
$activeJobsResult = executeQuery($activeJobsQuery, [$mechanic['id']], 'i');
$activeJobs = $activeJobsResult->fetch_all(MYSQLI_ASSOC);

// Fetch History
$historyQuery = "SELECT b.*, v.make, v.model, v.year, u.name as customer_name 
                 FROM bookings b 
                 JOIN vehicles v ON b.vehicle_id = v.id 
                 JOIN users u ON b.user_id = u.id 
                 WHERE b.mechanic_id = ? AND b.status IN ('completed', 'delivered', 'ready_for_delivery') 
                 ORDER BY b.updated_at DESC LIMIT 20";
$historyResult = executeQuery($historyQuery, [$mechanic['id']], 'i');
$history = $historyResult->fetch_all(MYSQLI_ASSOC);

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

                    <div class="grid grid-cols-1 gap-6">
                        <?php if (empty($activeJobs)): ?>
                            <div class="card p-16 text-center text-muted border-dashed">
                                <i class="fa-solid fa-screwdriver-wrench text-7xl mb-6 opacity-10"></i>
                                <h3 class="font-bold text-2xl text-gray-400">No Assignments Yet</h3>
                                <p class="max-w-sm mx-auto mt-2">New job requests will appear here once they are confirmed by the admin.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($activeJobs as $job): ?>
                                <div class="card overflow-hidden hover:shadow-xl transition-shadow duration-300">
                                    <div class="flex flex-col md:flex-row">
                                        <div class="p-8 flex-1">
                                            <div class="flex items-center gap-3 mb-4">
                                                <span class="badge <?php echo getStatusBadgeClass($job['status']); ?> px-4 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider">
                                                    <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
                                                </span>
                                                <span class="text-xs text-muted font-bold font-mono">ID: #<?php echo $job['booking_number']; ?></span>
                                            </div>
                                            
                                            <h3 class="text-2xl font-black text-gray-900 mb-2">
                                                <?php echo $job['year'] . ' ' . $job['make'] . ' ' . $job['model']; ?>
                                            </h3>
                                            
                                            <div class="flex flex-wrap gap-4 text-sm text-muted mb-6">
                                                <div class="flex items-center gap-2"><i class="fa-solid fa-user text-primary/60"></i> <?php echo htmlspecialchars($job['customer_name']); ?></div>
                                                <div class="flex items-center gap-2 font-mono bg-gray-50 px-2 py-0.5 rounded border border-gray-100"><i class="fa-solid fa-hashtag text-primary/60"></i> <?php echo htmlspecialchars($job['license_plate']); ?></div>
                                            </div>

                                            <div class="bg-gray-50 p-5 rounded-2xl border border-gray-100 flex gap-4">
                                                <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-primary">
                                                    <i class="fa-solid fa-clipboard-check"></i>
                                                </div>
                                                <div>
                                                    <div class="text-[10px] uppercase font-bold text-muted mb-1">Service Type</div>
                                                    <div class="font-bold text-gray-800"><?php echo htmlspecialchars($job['service_type']); ?></div>
                                                    <div class="text-sm text-gray-600 mt-1 italic"><?php echo htmlspecialchars($job['notes'] ?? 'No special notes.'); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="p-8 bg-gray-50/50 border-l border-gray-100 flex flex-col justify-center gap-3 w-full md:w-64">
                                            <?php if ($job['status'] === 'confirmed'): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="booking_id" value="<?php echo $job['id']; ?>">
                                                    <input type="hidden" name="status" value="in_progress">
                                                    <button type="submit" name="update_status" class="btn btn-primary w-full py-4 font-bold shadow-lg shadow-blue-100">
                                                        <i class="fa-solid fa-play mr-2"></i> Start Work
                                                    </button>
                                                </form>
                                            <?php elseif ($job['status'] === 'in_progress'): ?>
                                                <button class="btn btn-success w-full py-4 font-bold shadow-lg shadow-green-100" onclick="openCompleteModal(<?php echo $job['id']; ?>)">
                                                    <i class="fa-solid fa-check-double mr-2"></i> Complete & Bill
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
                    <div class="grid grid-cols-1 gap-6">
                        <?php if (empty($availableJobs)): ?>
                            <div class="card p-16 text-center text-muted border-dashed">
                                <i class="fa-solid fa-clipboard-list text-7xl mb-6 opacity-10"></i>
                                <h3 class="font-bold text-2xl text-gray-400">No Jobs Available</h3>
                                <p class="max-w-sm mx-auto mt-2">There are currently no pending jobs available for assignment.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($availableJobs as $job): ?>
                                <div class="card overflow-hidden hover:shadow-xl transition-shadow duration-300">
                                    <div class="flex flex-col md:flex-row">
                                        <div class="p-8 flex-1">
                                            <div class="flex items-center gap-3 mb-4">
                                                <span class="badge badge-success px-4 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider">
                                                    New Request
                                                </span>
                                                <span class="text-xs text-muted font-bold font-mono">Booked: <?php echo date('M d, H:i', strtotime($job['created_at'])); ?></span>
                                            </div>
                                            
                                            <h3 class="text-2xl font-black text-gray-900 mb-2">
                                                <?php echo $job['year'] . ' ' . $job['make'] . ' ' . $job['model']; ?>
                                            </h3>
                                            
                                            <div class="flex flex-wrap gap-4 text-sm text-muted mb-6">
                                                <div class="flex items-center gap-2"><i class="fa-solid fa-user text-primary/60"></i> <?php echo htmlspecialchars($job['customer_name']); ?></div>
                                                <div class="flex items-center gap-2 font-mono bg-gray-50 px-2 py-0.5 rounded border border-gray-100"><i class="fa-solid fa-hashtag text-primary/60"></i> <?php echo htmlspecialchars($job['license_plate']); ?></div>
                                            </div>

                                            <div class="bg-gray-50 p-5 rounded-2xl border border-gray-100 flex gap-4">
                                                <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-primary">
                                                    <i class="fa-solid fa-clipboard-check"></i>
                                                </div>
                                                <div>
                                                    <div class="text-[10px] uppercase font-bold text-muted mb-1">Service Type</div>
                                                    <div class="font-bold text-gray-800"><?php echo htmlspecialchars($job['service_type']); ?></div>
                                                    <div class="text-sm text-gray-600 mt-1 italic"><?php echo htmlspecialchars($job['notes'] ?? 'No special notes.'); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="p-8 bg-gray-50/50 border-l border-gray-100 flex flex-col justify-center gap-3 w-full md:w-64">
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to accept this job? You will be marked as unavailable for other tasks.');">
                                                <input type="hidden" name="booking_id" value="<?php echo $job['id']; ?>">
                                                <button type="submit" name="accept_job" class="btn btn-primary w-full py-4 font-bold shadow-lg shadow-blue-100">
                                                    <i class="fa-solid fa-hand-point-up mr-2"></i> Accept Job
                                                </button>
                                            </form>
                                        </div>
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
                                                        <?php echo ucfirst($h['status']); ?>
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
                    
                    <div class="form-group mb-6">
                        <label class="text-xs font-black uppercase text-gray-500 mb-2 block ml-1">Final Service Amount (₹) *</label>
                        <div class="relative">
                            <span class="absolute left-5 top-1/2 -translate-y-1/2 font-bold text-gray-400">₹</span>
                            <input type="number" name="bill_amount" class="form-control h-16 pl-10 pr-5 text-xl font-black bg-gray-50 border-gray-200 focus:bg-white transition-all rounded-2xl" 
                                   required placeholder="0.00" min="1" step="0.01">
                        </div>
                    </div>

                    <div class="form-group mb-10">
                        <label class="text-xs font-black uppercase text-gray-500 mb-2 block ml-1">Comprehensive Repair Notes</label>
                        <textarea name="service_notes" class="form-control p-5 text-base font-medium bg-gray-50 border-gray-200 focus:bg-white transition-all rounded-2xl" 
                                  rows="4" placeholder="Mention all parts changed, specific adjustments made, and future recommendations..."></textarea>
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
