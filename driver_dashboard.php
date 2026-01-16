<?php 
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

// Require driver role
if ($_SESSION['user_role'] !== 'driver') {
    header('Location: login.php');
    exit;
}

$conn = getDbConnection();
$user_id = getCurrentUserId();
$current_page = 'driver_dashboard.php';
$activeTab = $_GET['tab'] ?? 'jobs';

// Fetch driver details
$driverQuery = "SELECT d.*, u.phone FROM drivers d JOIN users u ON d.user_id = u.id WHERE d.user_id = ?";
$driverRes = executeQuery($driverQuery, [$user_id], 'i');
$driverData = $driverRes->fetch_assoc();

if (!$driverData) {
    // Driver record doesn't exist - create it (user account already exists)
    // Generate unique temporary license/vehicle to satisfy potential unique constraints
    $uniqueId = time() . rand(100, 999);
    $tempLicense = 'PEND-L-' . $uniqueId;
    $tempVehicle = 'PEND-V-' . $uniqueId;
    
    // Try insert with full fields
    $createDriverQuery = "INSERT INTO drivers (user_id, is_available, license_number, vehicle_number) VALUES (?, TRUE, ?, ?)";
    if (executeQuery($createDriverQuery, [$user_id, $tempLicense, $tempVehicle], 'iss')) {
        // Fetch the newly created driver record
        $driverRes = executeQuery($driverQuery, [$user_id], 'i');
        $driverData = $driverRes->fetch_assoc();
        
        // Also store phone in session if available
        if (isset($driverData['phone'])) {
            $_SESSION['user_phone'] = $driverData['phone'];
        }
    } else {
        // Fallback: Try simple insert if columns don't exist or other error
        $simpleQuery = "INSERT INTO drivers (user_id, is_available) VALUES (?, TRUE)";
        if (executeQuery($simpleQuery, [$user_id], 'i')) {
             $driverRes = executeQuery($driverQuery, [$user_id], 'i');
             $driverData = $driverRes->fetch_assoc();
        } else {
             die("Error: Unable to create driver record. Please contact administrator.");
        }
    }
}

$success_msg = '';
$error_msg = '';

// Handle Job Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $bookingId = intval($_POST['booking_id']);
    $requestId = intval($_POST['request_id']);

    if ($action === 'accept_job') {
        $updateQuery = "UPDATE pickup_delivery SET status = 'in_transit', driver_user_id = ?, driver_name = ?, driver_phone = ?, updated_at = NOW() WHERE id = ?";
        $result = executeQuery($updateQuery, [$user_id, $_SESSION['user_name'], $_SESSION['user_phone'] ?? 'N/A', $requestId], 'issi');
        
        if ($result) {
            $success_msg = "Job accepted successfully!";
        } else {
            $error_msg = "Failed to accept job.";
        }
    } elseif ($action === 'complete_job') {
        $conn->begin_transaction();
        try {
            // Update pickup_delivery status
            $updatePD = "UPDATE pickup_delivery SET status = 'completed', updated_at = NOW() WHERE id = ?";
            executeQuery($updatePD, [$requestId], 'i');

            // Fetch request details
            $pdQuery = "SELECT type FROM pickup_delivery WHERE id = ?";
            $pdRes = executeQuery($pdQuery, [$requestId], 'i');
            $pd = $pdRes->fetch_assoc();

            if ($pd['type'] === 'delivery') {
                $status = 'delivered';
                $updateBooking = "UPDATE bookings SET status = ? WHERE id = ?";
                executeQuery($updateBooking, [$status, $bookingId], 'si');
                
                // Final service update
                $insertUpdate = "INSERT INTO service_updates (booking_id, status, message, progress_percentage, updated_by) VALUES (?, ?, ?, ?, ?)";
                executeQuery($insertUpdate, [$bookingId, 'delivered', "Vehicle has been delivered back to the customer.", 100, $user_id], 'issii');
            } else { // pickup
                // Update booking status to confirmed if it was just assigned or something,
                // But usually pickup means it's now 'confirmed' stage and in progress at workshop soon.
                $insertUpdate = "INSERT INTO service_updates (booking_id, status, message, progress_percentage, updated_by) VALUES (?, ?, ?, ?, ?)";
                executeQuery($insertUpdate, [$bookingId, 'in_transit', "Vehicle picked up and moving to workshop.", 25, $user_id], 'issii');
            }

            // Set driver back to available if they don't have other active jobs (simplified here)
            executeQuery("UPDATE drivers SET is_available = 1 WHERE user_id = ?", [$user_id], 'i');

            $conn->commit();
            $success_msg = "Job completed successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Failed to complete job: " . $e->getMessage();
        }
    }
}

// Handle Availability Toggle
if (isset($_GET['toggle_availability'])) {
    $newStatus = $driverData['is_available'] ? 0 : 1;
    $updateQuery = "UPDATE drivers SET is_available = ? WHERE user_id = ?";
    if (executeQuery($updateQuery, [$newStatus, $user_id], 'ii')) {
        header("Location: driver_dashboard.php");
        exit;
    }
}

// Fetch Available Jobs
$availableJobsQuery = "SELECT pd.*, b.booking_number, b.status as booking_status, v.make, v.model, v.year, v.license_plate, u.name as customer_name, u.phone as customer_phone
                       FROM pickup_delivery pd 
                       JOIN bookings b ON pd.booking_id = b.id 
                       JOIN vehicles v ON b.vehicle_id = v.id 
                       JOIN users u ON b.user_id = u.id
                       WHERE pd.status = 'scheduled' AND (pd.driver_user_id IS NULL OR pd.driver_user_id = 0)
                       ORDER BY pd.created_at ASC";
$availableJobsRes = executeQuery($availableJobsQuery);
$availableJobs = [];
if ($availableJobsRes) {
    while ($row = $availableJobsRes->fetch_assoc()) {
        $row['request_date'] = $row['created_at']; // Polyfill for display
        $availableJobs[] = $row;
    }
}

// Handle Job Acceptance (Self-Assignment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_job'])) {
    $request_id = intval($_POST['request_id']);
    $bookingId = intval($_POST['booking_id']);
    
    $conn->begin_transaction();
    try {
        // Assign driver
        $updateQuery = "UPDATE pickup_delivery SET status = 'in_transit', driver_user_id = ?, driver_name = ?, driver_phone = ?, updated_at = NOW() WHERE id = ? AND (driver_user_id IS NULL OR driver_user_id = 0)";
        $driverName = $driverData['name'] ?? $_SESSION['user_name'];
        $driverPhone = $_SESSION['user_phone'] ?? 'N/A';
        
        $result = executeQuery($updateQuery, [$user_id, $driverName, $driverPhone, $request_id], 'issi');
        
        if ($result && $conn->affected_rows > 0) {
            // Set availability to false
            executeQuery("UPDATE drivers SET is_available = FALSE WHERE user_id = ?", [$user_id], 'i');
            
            // Add service update
            $statusMsg = "Driver assigned and en route.";
            $insertUpdate = "INSERT INTO service_updates (booking_id, status, message, progress_percentage, updated_by) VALUES (?, 'in_transit', ?, 15, ?)";
            executeQuery($insertUpdate, [$bookingId, $statusMsg, $user_id], 'isi');
            
            $conn->commit();
            $success_msg = "Job accepted successfully!";
            
            // Refresh active jobs
            header("Location: driver_dashboard.php?tab=jobs&subtab=active");
            exit;
        } else {
            $conn->rollback();
            $error_msg = "Failed to accept job or it was already taken.";
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = "Error accepting job: " . $e->getMessage();
    }
}

// Fetch Active Jobs for this driver
$activeJobsQuery = "SELECT pd.*, b.booking_number, v.make, v.model, v.year, v.license_plate, u.name as customer_name, u.phone as customer_phone
                    FROM pickup_delivery pd 
                    JOIN bookings b ON pd.booking_id = b.id 
                    JOIN vehicles v ON b.vehicle_id = v.id 
                    JOIN users u ON b.user_id = u.id
                    WHERE pd.driver_user_id = ? AND pd.status = 'in_transit'";
$activeJobsRes = executeQuery($activeJobsQuery, [$user_id], 'i');
$activeJobs = $activeJobsRes->fetch_all(MYSQLI_ASSOC);

// Fetch History
$historyQuery = "SELECT pd.*, b.booking_number, v.make, v.model, v.year, v.license_plate 
                 FROM pickup_delivery pd 
                 JOIN bookings b ON pd.booking_id = b.id 
                 JOIN vehicles v ON b.vehicle_id = v.id 
                 WHERE pd.driver_user_id = ? AND pd.status = 'completed'
                 ORDER BY pd.updated_at DESC LIMIT 20";
$historyRes = executeQuery($historyQuery, [$user_id], 'i');
$history = $historyRes->fetch_all(MYSQLI_ASSOC);

$page_title = 'Driver Dashboard';
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
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl relative mb-6 animate-fade-in">
                        <span class="block sm:inline font-bold"><i class="fa-solid fa-circle-check mr-2"></i> <?php echo htmlspecialchars($success_msg); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl relative mb-6 animate-fade-in">
                        <span class="block sm:inline font-bold"><i class="fa-solid fa-circle-xmark mr-2"></i> <?php echo htmlspecialchars($error_msg); ?></span>
                    </div>
                <?php endif; ?>

                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                    <div>
                        <h1 class="text-3xl font-black text-gray-900">Driver Dashboard</h1>
                        <p class="text-muted font-medium">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                    </div>
                    <?php if (!empty($driverData['vehicle_number']) && $driverData['vehicle_number'] !== 'Not Assigned'): ?>
                    <div class="bg-white px-5 py-3 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-3">
                        <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
                            <i class="fa-solid fa-truck-fast"></i>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Assigned Vehicle</div>
                            <div class="font-bold text-gray-900"><?php echo htmlspecialchars($driverData['vehicle_number']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($activeTab === 'jobs'): ?>
                    
                    <!-- Tabs -->
                    <div class="flex gap-4 mb-8 border-b border-gray-200">
                        <a href="?tab=jobs&subtab=active" class="px-6 py-3 font-black text-sm tracking-wide <?php echo (!isset($_GET['subtab']) || $_GET['subtab'] == 'active') ? 'text-primary border-b-2 border-primary' : 'text-muted hover:text-gray-700'; ?>">
                            Active Mission <?php if (!empty($activeJobs)): ?><span class="ml-2 px-2 py-0.5 bg-primary text-white rounded-md text-[10px]"><?php echo count($activeJobs); ?></span><?php endif; ?>
                        </a>
                        <a href="?tab=jobs&subtab=available" class="px-6 py-3 font-black text-sm tracking-wide <?php echo (isset($_GET['subtab']) && $_GET['subtab'] == 'available') ? 'text-primary border-b-2 border-primary' : 'text-muted hover:text-gray-700'; ?>">
                            Available Requests <?php if (!empty($availableJobs)): ?><span class="ml-2 px-2 py-0.5 bg-green-500 text-white rounded-md text-[10px]"><?php echo count($availableJobs); ?></span><?php endif; ?>
                        </a>
                    </div>

                    <?php $subtab = $_GET['subtab'] ?? 'active'; ?>

                    <?php if ($subtab == 'available'): ?>
                        <!-- Available Jobs List -->
                        <div class="grid grid-cols-1 gap-6">
                            <?php if (empty($availableJobs)): ?>
                                <div class="card p-16 text-center text-muted border-dashed">
                                    <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6">
                                        <i class="fa-solid fa-map-location-dot text-4xl opacity-20"></i>
                                    </div>
                                    <h3 class="font-bold text-2xl text-gray-400">No New Requests</h3>
                                    <p class="max-w-sm mx-auto mt-2">Check back later for new pickup or delivery requests.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($availableJobs as $job): ?>
                                    <div class="card overflow-hidden hover:shadow-xl transition-all duration-300 border-l-4 border-l-blue-500">
                                        <div class="flex flex-col md:flex-row">
                                            <div class="p-8 flex-1">
                                                <div class="flex justify-between items-start mb-6">
                                                    <span class="badge badge-blue px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider">
                                                        <i class="fa-solid <?php echo $job['type'] == 'pickup' ? 'fa-truck-pickup' : 'fa-truck-fast'; ?> mr-1"></i>
                                                        <?php echo ucfirst($job['type']); ?> Request
                                                    </span>
                                                    <div class="text-right">
                                                        <span class="font-mono text-xs font-bold text-gray-400 block">#<?php echo $job['booking_number']; ?></span>
                                                        <span class="text-[10px] text-muted font-bold"><?php echo date('M d, H:i', strtotime($job['request_date'])); ?></span>
                                                    </div>
                                                </div>
                                                
                                                <div class="flex flex-col md:flex-row gap-8 mb-6">
                                                    <div class="flex-1">
                                                        <label class="text-[10px] uppercase font-bold text-muted mb-2 block tracking-widest">Vehicle To Transport</label>
                                                        <div class="flex items-center gap-3">
                                                            <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center text-gray-400">
                                                                <i class="fa-solid fa-car"></i>
                                                            </div>
                                                            <div>
                                                                <h3 class="text-lg font-black text-gray-900 leading-tight">
                                                                    <?php echo $job['year'] . ' ' . $job['make'] . ' ' . $job['model']; ?>
                                                                </h3>
                                                                <p class="font-mono text-xs text-gray-500 mt-1 bg-gray-100 px-2 py-0.5 rounded w-fit"><?php echo htmlspecialchars($job['license_plate']); ?></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="flex-1 md:border-l md:border-gray-100 md:pl-8">
                                                        <label class="text-[10px] uppercase font-bold text-muted mb-2 block tracking-widest">Target Location</label>
                                                        <div class="flex items-start gap-3">
                                                            <i class="fa-solid fa-location-dot text-red-500 mt-1"></i>
                                                            <div>
                                                                <p class="font-bold text-gray-800 text-sm leading-relaxed"><?php echo htmlspecialchars($job['address']); ?></p>
                                                                <?php if (!empty($job['parking_info'])): ?>
                                                                    <p class="text-xs text-gray-500 mt-1"><i class="fa-solid fa-square-parking mr-1"></i> <?php echo htmlspecialchars($job['parking_info']); ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="p-8 bg-gray-50 flex flex-col justify-center w-full md:w-56 border-l border-gray-100">
                                                <form method="POST" onsubmit="return confirm('Accept this job? You will be marked as ON DUTY.');">
                                                    <input type="hidden" name="action" value="accept_job">
                                                    <input type="hidden" name="booking_id" value="<?php echo $job['booking_id']; ?>">
                                                    <input type="hidden" name="request_id" value="<?php echo $job['id']; ?>">
                                                    <button type="submit" name="accept_job" class="btn btn-primary w-full h-14 font-bold shadow-lg shadow-blue-100 text-sm rounded-xl">
                                                        Accept Job
                                                    </button>
                                                </form>
                                                <div class="text-center mt-3">
                                                    <span class="text-[10px] text-muted font-medium"><i class="fa-solid fa-clock mr-1"></i> Quick Response Required</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                    <?php else: ?>
                        <!-- Active Jobs Section -->
                        <div class="grid grid-cols-1 gap-6">
                            <?php if (empty($activeJobs)): ?>
                                <div class="card p-16 text-center text-muted border-dashed">
                                    <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6">
                                        <i class="fa-solid fa-truck-fast text-4xl opacity-20"></i>
                                    </div>
                                    <h3 class="font-bold text-2xl text-gray-400">No Active Mission</h3>
                                    <p class="max-w-sm mx-auto mt-2">You are currently standby. Check 'Available Requests' to pick up a new job.</p>
                                    <a href="?tab=jobs&subtab=available" class="btn btn-outline mt-6 rounded-xl font-bold">Find Jobs</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($activeJobs as $job): ?>
                                    <div class="card overflow-hidden border-t-4 border-t-primary shadow-xl">
                                        <div class="p-8">
                                            <!-- Job Header -->
                                            <div class="flex flex-col md:flex-row justify-between items-start gap-4 mb-8">
                                                <div>
                                                    <span class="badge badge-primary px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest mb-2 inline-block animate-pulse">
                                                        <i class="fa-solid fa-circle text-[6px] mr-2"></i> In Progress
                                                    </span>
                                                    <h3 class="text-3xl font-black text-gray-900"><?php echo $job['year'] . ' ' . $job['make'] . ' ' . $job['model']; ?></h3>
                                                    <p class="text-sm font-bold text-muted mt-1">Booking #<?php echo $job['booking_number']; ?></p>
                                                </div>
                                                <div class="text-right">
                                                    <span class="badge bg-gray-900 text-white px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider">
                                                        <?php echo ucfirst($job['type']); ?> Mission
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Job Details Grid -->
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                                                <!-- Location Card -->
                                                <div class="bg-gray-50 p-5 rounded-2xl border border-gray-100">
                                                    <label class="text-[10px] uppercase font-bold text-gray-400 mb-3 block tracking-wider">Destination</label>
                                                    <div class="flex items-start gap-3">
                                                        <div class="w-10 h-10 bg-white rounded-xl shadow-sm flex items-center justify-center text-red-500 shrink-0">
                                                            <i class="fa-solid fa-map-pin"></i>
                                                        </div>
                                                        <div>
                                                            <div class="font-bold text-gray-900 leading-snug mb-1"><?php echo htmlspecialchars($job['address']); ?></div>
                                                            <?php if(!empty($job['landmark'])): ?>
                                                                <div class="text-[10px] font-black text-blue-600 uppercase bg-blue-50 px-2 py-0.5 rounded w-fit mb-1.5">
                                                                    <i class="fa-solid fa-building mr-1"></i> Near <?php echo htmlspecialchars($job['landmark']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if(!empty($job['parking_info'])): ?>
                                                                <div class="text-[10px] text-muted flex items-center gap-1.5 bg-white px-2 py-1 rounded-md border border-gray-100 w-fit">
                                                                    <i class="fa-solid fa-info-circle text-primary"></i> 
                                                                    <?php echo htmlspecialchars($job['parking_info']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="mt-4 pt-4 border-t border-gray-200 flex gap-2">
                                                        <?php 
                                                            $nav_url = "https://www.google.com/maps/search/?api=1&query=";
                                                            if (!empty($job['lat']) && !empty($job['lng'])) {
                                                                $nav_url .= $job['lat'] . "," . $job['lng'];
                                                            } else {
                                                                $nav_url .= urlencode($job['address']);
                                                            }
                                                        ?>
                                                        <a href="<?php echo $nav_url; ?>" target="_blank" class="btn btn-primary flex-1 py-2 text-xs font-bold rounded-lg items-center justify-center flex">
                                                            <i class="fa-solid fa-location-arrow mr-2"></i> Navigate
                                                        </a>
                                                    </div>
                                                </div>

                                                <!-- Customer Card -->
                                                <div class="bg-gray-50 p-5 rounded-2xl border border-gray-100">
                                                    <label class="text-[10px] uppercase font-bold text-gray-400 mb-3 block tracking-wider">Client Contact</label>
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-10 h-10 bg-gray-900 text-white rounded-xl shadow-sm flex items-center justify-center text-sm font-bold shrink-0">
                                                            <?php echo strtoupper(substr($job['customer_name'] ?? 'C', 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <div class="font-bold text-gray-900 leading-snug"><?php echo htmlspecialchars($job['customer_name']); ?></div>
                                                            <div class="text-xs text-muted font-mono"><?php echo htmlspecialchars($job['customer_phone']); ?></div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-4 pt-4 border-t border-gray-200 flex gap-2">
                                                        <a href="tel:<?php echo htmlspecialchars($job['customer_phone']); ?>" class="btn btn-outline bg-white flex-1 py-2 text-xs font-bold rounded-lg items-center justify-center flex hover:bg-gray-50">
                                                            <i class="fa-solid fa-phone mr-2"></i> Call Now
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Actions -->
                                            <div class="flex flex-col gap-3">
                                                <form method="POST" onsubmit="return confirm('Are you sure you have completed this task?');">
                                                    <input type="hidden" name="action" value="complete_job">
                                                    <input type="hidden" name="booking_id" value="<?php echo $job['booking_id']; ?>">
                                                    <input type="hidden" name="request_id" value="<?php echo $job['id']; ?>">
                                                    <input type="hidden" name="type" value="<?php echo $job['type']; ?>">
                                                    <button type="submit" class="btn btn-success w-full py-4 text-base font-black rounded-xl shadow-lg shadow-green-100 hover:shadow-xl hover:-translate-y-1 transition-all">
                                                        <i class="fa-solid fa-flag-checkered mr-2"></i> Mark As Completed
                                                    </button>
                                                </form>
                                                <p class="text-[10px] text-center text-muted font-medium bg-yellow-50 text-yellow-700 py-2 rounded-lg">
                                                    <i class="fa-solid fa-triangle-exclamation mr-1"></i> Ensure vehicle is handed over safely before completing.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php elseif ($activeTab === 'history'): ?>
                    <div class="flex flex-col gap-6 animate-fade-in">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-2xl font-black text-gray-800 flex items-center gap-3">
                                <div class="w-10 h-10 bg-gray-100 text-gray-500 rounded-xl flex items-center justify-center">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                </div>
                                Journey Logs
                            </h2>
                        </div>
                        
                        <div class="card p-0 overflow-hidden shadow-sm">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead class="bg-gray-50/80 text-[10px] font-black uppercase text-muted tracking-widest border-b border-gray-100">
                                        <tr>
                                            <th class="p-6">Completion Log</th>
                                            <th class="p-6">Type of Service</th>
                                            <th class="p-6">Vehicle Archive</th>
                                            <th class="p-6">Reference</th>
                                            <th class="p-6 text-right">Verification</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-sm">
                                        <?php if (empty($history)): ?>
                                            <tr>
                                                <td colspan="5" class="p-24 text-center">
                                                    <div class="flex flex-col items-center opacity-20">
                                                        <i class="fa-solid fa-receipt text-7xl mb-4"></i>
                                                        <h4 class="text-xl font-black">Archive Empty</h4>
                                                        <p class="text-sm">Completed assignments will be logged here.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($history as $h): ?>
                                                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                                    <td class="p-6 font-bold text-gray-500">
                                                        <div class="text-gray-900"><?php echo date('M d, Y', strtotime($h['updated_at'])); ?></div>
                                                        <div class="text-[10px] font-medium"><?php echo date('h:i A', strtotime($h['updated_at'])); ?></div>
                                                    </td>
                                                    <td class="p-6">
                                                        <span class="badge <?php echo $h['type'] == 'pickup' ? 'badge-info' : 'badge-warning'; ?> uppercase text-[9px] font-black px-3 py-1 rounded-full">
                                                            <?php echo $h['type']; ?>
                                                        </span>
                                                    </td>
                                                    <td class="p-6">
                                                        <div class="font-black text-gray-800"><?php echo $h['year'] . ' ' . $h['make'] . ' ' . $h['model']; ?></div>
                                                        <div class="text-[10px] font-mono text-muted uppercase"><?php echo htmlspecialchars($h['license_plate']); ?></div>
                                                    </td>
                                                    <td class="p-6 font-mono text-primary font-black">#<?php echo $h['booking_number']; ?></td>
                                                    <td class="p-6 text-right">
                                                        <span class="inline-flex items-center gap-2 text-green-600 font-black text-[10px] uppercase bg-green-50 px-3 py-1 rounded-lg">
                                                            <i class="fa-solid fa-circle-check text-[8px]"></i> Completed
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
