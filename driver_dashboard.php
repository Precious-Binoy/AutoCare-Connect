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

    if ($action === 'complete_job') {
        $fee = floatval($_POST['fee'] ?? 0);
        $conn->begin_transaction();
        try {
            // Update pickup_delivery status and fee
            $updatePD = "UPDATE pickup_delivery SET status = 'completed', fee = ?, updated_at = NOW() WHERE id = ?";
            executeQuery($updatePD, [$fee, $requestId], 'di');

            // Fetch request details
            $pdQuery = "SELECT type FROM pickup_delivery WHERE id = ?";
            $pdRes = executeQuery($pdQuery, [$requestId], 'i');
            $pd = $pdRes->fetch_assoc();

            if ($pd['type'] === 'delivery') {
                // Final completion - Update booking to 'completed' and set completion date
                $status = 'completed';
                $updateBooking = "UPDATE bookings SET status = ?, final_cost = IFNULL(final_cost, 0) + ?, completion_date = NOW() WHERE id = ?";
                executeQuery($updateBooking, [$status, $fee, $bookingId], 'sdi');
                
                // Final service update
                $insertUpdate = "INSERT INTO service_updates (booking_id, status, message, progress_percentage, updated_by) VALUES (?, 'completed', ?, 100, ?)";
                executeQuery($insertUpdate, [$bookingId, "Vehicle has been delivered back to the customer. Mission Accomplished.", $user_id], 'isi');
            } else { // pickup
                // Update booking to show it's at workshop/confirmed
                $updateBooking = "UPDATE bookings SET status = 'confirmed', final_cost = IFNULL(final_cost, 0) + ? WHERE id = ?";
                executeQuery($updateBooking, [$fee, $bookingId], 'di');

                $insertUpdate = "INSERT INTO service_updates (booking_id, status, message, progress_percentage, updated_by) VALUES (?, 'confirmed', ?, 25, ?)";
                executeQuery($insertUpdate, [$bookingId, "Vehicle picked up and arrived at workshop. Ready for service.", $user_id], 'isi');
            }

            // Set driver back to available
            executeQuery("UPDATE drivers SET is_available = 1 WHERE user_id = ?", [$user_id], 'i');

            $conn->commit();
            $success_msg = "Job completed successfully!";
            
            header("Location: driver_dashboard.php?tab=history");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Error: " . $e->getMessage();
        }
    }
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = sanitizeInput($_POST['name']);
    
    // Update basic user info
    $updateUserQuery = "UPDATE users SET name = ? WHERE id = ?";
    if (executeQuery($updateUserQuery, [$name, $user_id], 'si')) {
        $_SESSION['user_name'] = $name;
        $success_msg = "Profile identity updated!";
        // Refresh driver data
        $driverRes = executeQuery($driverQuery, [$user_id], 'i');
        $driverData = $driverRes->fetch_assoc();
    } else {
        $error_msg = "Error updating profile details.";
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

// Check if driver already has an active task
// This check MUST align with the $activeJobsQuery display logic
$hasActiveTaskQuery = "SELECT COUNT(*) as active_count 
                       FROM pickup_delivery pd 
                       JOIN bookings b ON pd.booking_id = b.id
                       WHERE pd.driver_user_id = ? 
                       AND pd.status IN ('scheduled', 'in_transit')
                       AND (
                           (pd.type = 'pickup' AND b.status IN ('pending', 'confirmed', 'in_progress'))
                           OR
                           (pd.type = 'delivery' AND b.status = 'ready_for_delivery')
                       )";
$hasActiveTaskRes = executeQuery($hasActiveTaskQuery, [$user_id], 'i');
$hasActiveTask = $hasActiveTaskRes->fetch_assoc()['active_count'] > 0;

// Fetch Available Jobs (Sequenced)
// 1. Pickup: status 'scheduled' and b.status 'pending' or 'confirmed'
// 2. Delivery: status 'scheduled' and b.status 'ready_for_delivery'
$availableJobsQuery = "SELECT pd.*, b.booking_number, b.status as booking_status, v.make, v.model, v.year, v.license_plate, v.color, u.name as customer_name, 
                              COALESCE(pd.contact_phone, u.phone) as customer_phone
                       FROM pickup_delivery pd 
                       JOIN bookings b ON pd.booking_id = b.id 
                       JOIN vehicles v ON b.vehicle_id = v.id 
                       JOIN users u ON b.user_id = u.id
                       WHERE pd.status = 'scheduled' 
                       AND (pd.driver_user_id IS NULL OR pd.driver_user_id = 0)
                       ORDER BY pd.created_at ASC";
$availableJobsRes = executeQuery($availableJobsQuery);
$availableJobs = [];
if ($availableJobsRes) {
    while ($row = $availableJobsRes->fetch_assoc()) {
        $row['request_date'] = $row['created_at']; // Polyfill for display
        $availableJobs[] = $row;
    }
}

// Fetch Mission History
$historyJobsQuery = "SELECT pd.*, b.booking_number, b.status as booking_status, v.make, v.model, v.year, v.license_plate, v.color, u.name as customer_name,
                            COALESCE(pd.contact_phone, u.phone) as customer_phone
                     FROM pickup_delivery pd
                     JOIN bookings b ON pd.booking_id = b.id
                     JOIN vehicles v ON b.vehicle_id = v.id
                     JOIN users u ON b.user_id = u.id
                     WHERE pd.driver_user_id = ? AND pd.status = 'completed'
                     ORDER BY pd.updated_at DESC LIMIT 20";
$historyJobsRes = executeQuery($historyJobsQuery, [$user_id], 'i');
$historyJobs = [];
if ($historyJobsRes) {
    while ($row = $historyJobsRes->fetch_assoc()) {
        $historyJobs[] = $row;
    }
}

// Handle Job Acceptance (Self-Assignment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_job'])) {
    if ($hasActiveTask) {
        $error_msg = "You already have an active mission. Complete it before accepting another.";
    } else {
        $request_id = intval($_POST['request_id']);
        $bookingId = intval($_POST['booking_id']);
        
        $conn->begin_transaction();
        try {
            // Assign driver - KEEP STATUS 'scheduled' (wait for start mission)
            $updateQuery = "UPDATE pickup_delivery SET status = 'scheduled', driver_user_id = ?, driver_name = ?, driver_phone = ?, updated_at = NOW() WHERE id = ? AND (driver_user_id IS NULL OR driver_user_id = 0)";
            $driverName = $driverData['name'] ?? $_SESSION['user_name'];
            $driverPhone = $_SESSION['user_phone'] ?? 'N/A';
            
            $result = executeQuery($updateQuery, [$user_id, $driverName, $driverPhone, $request_id], 'issi');
            
            if ($result && $conn->affected_rows > 0) {
                // Success case
                $success = true;
            } else {
                // Fallback verification: Check if we were effectively assigned anyway
                // (Sometimes mysqli->affected_rows is unreliable with prepared stmt wrappers)
                $verifyQuery = "SELECT driver_user_id FROM pickup_delivery WHERE id = ?";
                $verifyRes = executeQuery($verifyQuery, [$request_id], 'i');
                $verifyRow = $verifyRes->fetch_assoc();
                
                if ($verifyRow && $verifyRow['driver_user_id'] == $user_id) {
                    $success = true;
                } else {
                    $success = false;
                }
            }

            if ($success) {
                // Set availability to false
                executeQuery("UPDATE drivers SET is_available = FALSE WHERE user_id = ?", [$user_id], 'i');
                
                // Add service update
                $statusMsg = "Driver assigned. Waiting to start mission.";
                $insertUpdate = "INSERT INTO service_updates (booking_id, status, message, progress_percentage, updated_by) VALUES (?, 'scheduled', ?, 15, ?)";
                executeQuery($insertUpdate, [$bookingId, $statusMsg, $user_id], 'isi');
                
                $conn->commit();
                $success_msg = "Job accepted successfully!";
                
                // Refresh active jobs
                header("Location: driver_dashboard.php?tab=jobs&subtab=active");
                exit;
            } else {
                $conn->rollback();
                $error_msg = "Failed to accept job: It may have been taken by another driver.";
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Error accepting job: " . $e->getMessage();
        }
    }
}

// Handle Start Mission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_mission'])) {
    $requestId = intval($_POST['request_id']);
    $bookingId = intval($_POST['booking_id']);
    
    $conn->begin_transaction();
    try {
        // Update status to in_transit
        $updateQuery = "UPDATE pickup_delivery SET status = 'in_transit', updated_at = NOW() WHERE id = ? AND driver_user_id = ?";
        executeQuery($updateQuery, [$requestId, $user_id], 'ii');
        
        // Add service update
        $statusMsg = "Driver is on the way.";
        $insertUpdate = "INSERT INTO service_updates (booking_id, status, message, progress_percentage, updated_by) VALUES (?, 'in_transit', ?, 30, ?)";
        executeQuery($insertUpdate, [$bookingId, $statusMsg, $user_id], 'isi');
        
        $conn->commit();
        $success_msg = "Mission started! Drive safely.";
        
        header("Location: driver_dashboard.php?tab=jobs&subtab=active");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = "Error starting mission: " . $e->getMessage();
    }
}

// Fetch Active Jobs for this driver
$activeJobsQuery = "SELECT pd.*, b.booking_number, v.make, v.model, v.year, v.license_plate, v.color, u.name as customer_name, 
                           COALESCE(pd.contact_phone, u.phone) as customer_phone
                    FROM pickup_delivery pd 
                    JOIN bookings b ON pd.booking_id = b.id 
                    JOIN vehicles v ON b.vehicle_id = v.id 
                    JOIN users u ON b.user_id = u.id
                    WHERE pd.driver_user_id = ? 
                    AND pd.status IN ('scheduled', 'in_transit')
                    AND (
                        (pd.type = 'pickup' AND b.status IN ('pending', 'confirmed', 'in_progress'))
                        OR
                        (pd.type = 'delivery' AND b.status = 'ready_for_delivery')
                    )";
$activeJobsRes = executeQuery($activeJobsQuery, [$user_id], 'i');
$activeJobs = [];
if ($activeJobsRes) {
    while ($row = $activeJobsRes->fetch_assoc()) {
        $activeJobs[] = $row;
    }
}

// Fetch History
$historyQuery = "SELECT pd.*, b.booking_number, v.make, v.model, v.year, v.license_plate, v.color 
                 FROM pickup_delivery pd 
                 JOIN bookings b ON pd.booking_id = b.id 
                 JOIN vehicles v ON b.vehicle_id = v.id 
                 WHERE pd.driver_user_id = ? AND pd.status = 'completed'
                 ORDER BY pd.updated_at DESC LIMIT 20";
$historyRes = executeQuery($historyQuery, [$user_id], 'i');
$history = [];
if ($historyRes) {
    while ($row = $historyRes->fetch_assoc()) {
        $history[] = $row;
    }
}

// Fetch Leave Requests
$leaveRequestsQuery = "SELECT * FROM leave_requests WHERE user_id = ? ORDER BY created_at DESC";
$leaveRequestsResult = executeQuery($leaveRequestsQuery, [$user_id], 'i');
$leaveRequests = [];
if ($leaveRequestsResult) {
    while ($row = $leaveRequestsResult->fetch_assoc()) {
        $leaveRequests[] = $row;
    }
}

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

                <div class="p-4">
                    <!-- Dashboard content starts here -->
                </div>

                <?php if ($activeTab === 'jobs'): ?>
                    <!-- Jobs Section with Tabs -->
                    <div class="mb-6">
                    </div>

                    <!-- Primary Tabs for Jobs - Styled as Buttons per User Request -->
                    <div class="flex gap-6 mb-8">
                        <a href="?tab=jobs&subtab=active" class="relative btn <?php echo (!isset($_GET['subtab']) || $_GET['subtab'] == 'active') ? 'btn-primary px-8 py-3 rounded-xl shadow-lg shadow-blue-500/20' : 'btn-outline px-8 py-3 rounded-xl bg-white'; ?>" style="position: relative;">
                            My Active Jobs 
                            <?php if (!empty($activeJobs)): ?>
                                <span class="absolute" style="position: absolute; top: -10px; right: -10px; width: 22px; height: 22px; background: #EF4444; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 900; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); z-index: 10;">
                                    <?php echo count($activeJobs); ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <a href="?tab=jobs&subtab=available" class="relative btn <?php echo (isset($_GET['subtab']) && $_GET['subtab'] == 'available') ? 'btn-primary px-8 py-3 rounded-xl shadow-lg shadow-blue-500/20' : 'btn-outline px-8 py-3 rounded-xl bg-white'; ?>" style="position: relative;">
                            Available Requests 
                            <?php if (!empty($availableJobs)): ?>
                                <span class="absolute" style="position: absolute; top: -10px; right: -10px; width: 22px; height: 22px; background: #EF4444; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 900; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); z-index: 10;">
                                    <?php echo count($availableJobs); ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <a href="?tab=leave" class="relative btn <?php echo ($activeTab === 'leave') ? 'btn-primary px-8 py-3 rounded-xl shadow-lg shadow-blue-500/20' : 'btn-outline px-8 py-3 rounded-xl bg-white'; ?>" style="position: relative;">
                            <i class="fa-solid fa-calendar-minus mr-2"></i> Leave Requests
                        </a>
                    </div>

                    <?php $subtab = $_GET['subtab'] ?? 'active'; ?>

                    <?php if ($subtab == 'active'): ?>
                        <!-- Active Missions Grid -->
                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                            <?php if (empty($activeJobs)): ?>
                                <div class="col-span-1 xl:col-span-2 card p-16 text-center text-muted border-dashed bg-gray-50/50">
                                    <div class="w-24 h-24 bg-white shadow-sm rounded-full flex items-center justify-center mx-auto mb-6">
                                        <i class="fa-solid fa-truck-fast text-5xl text-gray-300"></i>
                                    </div>
                                    <h3 class="font-bold text-2xl text-gray-400">No Active Mission</h3>
                                    <p class="max-w-sm mx-auto mt-2">You are currently standby. Check 'Available Requests' to pick up a new job.</p>
                                    <a href="?tab=jobs&subtab=available" class="btn btn-primary mt-8 px-8 py-3 rounded-xl font-bold shadow-lg shadow-blue-500/20">Find Next Mission</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($activeJobs as $job): ?>
                                    <div class="card overflow-hidden hover:shadow-2xl transition-all duration-300 group relative border-0 shadow-lg">
                                        <div class="absolute top-0 w-full h-1.5 bg-gradient-to-r from-blue-600 to-indigo-700"></div>
                                        <div class="flex flex-col h-full">
                                            <div class="p-8 flex-1">
                                                <div class="flex justify-between items-start mb-6">
                                                    <div class="flex items-center gap-2">
                                                        <span class="badge bg-green-50 text-green-600 border border-green-100 px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider shadow-sm">
                                                            <i class="fa-solid fa-circle text-[6px] mr-1.5 animate-pulse"></i> Live Mission
                                                        </span>
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
                                                    <?php if(!empty($job['customer_phone'])): ?>
                                                        <div class="flex items-center gap-2 text-xs font-bold text-gray-500 bg-gray-50 px-3 py-1.5 rounded-full border border-gray-100">
                                                            <i class="fa-solid fa-phone text-primary/70"></i> <?php echo htmlspecialchars($job['customer_phone']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="flex items-center gap-2 text-xs font-bold text-gray-500 bg-gray-50 px-3 py-1.5 rounded-full border border-gray-100 font-mono">
                                                        <i class="fa-solid fa-hashtag text-primary/70"></i> <?php echo htmlspecialchars($job['license_plate']); ?>
                                                    </div>
                                                    <span class="px-3 py-1.5 rounded-full text-[10px] font-black uppercase bg-blue-50 text-blue-600 border border-blue-100">
                                                        <?php echo ucfirst($job['type']); ?> Phase
                                                    </span>
                                                </div>

                                                <div class="bg-indigo-50/50 p-6 rounded-2xl border border-indigo-50 flex flex-col gap-4 transition-colors group-hover:bg-indigo-50">
                                                    <div class="flex items-start gap-4">
                                                        <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-red-500 shrink-0">
                                                            <i class="fa-solid fa-location-dot"></i>
                                                        </div>
                                                        <div>
                                                            <div class="text-[10px] uppercase font-bold text-indigo-400 mb-1 tracking-wider">Destination Target</div>
                                                            <div class="font-bold text-gray-900 text-sm md:text-base leading-snug"><?php echo htmlspecialchars($job['address']); ?></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="flex flex-wrap gap-2 ml-14">
                                                        <?php if(!empty($job['landmark'])): ?>
                                                            <div class="text-[10px] font-bold text-gray-500 uppercase bg-white/80 px-2 py-1 rounded-lg border border-gray-100">
                                                                <i class="fa-solid fa-building mr-1 opacity-50"></i> <?php echo htmlspecialchars($job['landmark']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if(!empty($job['parking_info'])): ?>
                                                            <div class="text-[10px] font-bold text-blue-500 uppercase bg-white/80 px-2 py-1 rounded-lg border border-blue-100">
                                                                <i class="fa-solid fa-info-circle mr-1 opacity-50"></i> <?php echo htmlspecialchars($job['parking_info']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="p-6 bg-gray-50 border-t border-gray-100">
                                                <div class="flex flex-wrap items-center gap-3">
                                                    <?php 
                                                        $nav_url = "https://www.google.com/maps/search/?api=1&query=";
                                                        if (!empty($job['lat']) && !empty($job['lng'])) {
                                                            $nav_url .= $job['lat'] . "," . $job['lng'];
                                                        } else {
                                                            $nav_url .= urlencode($job['address']);
                                                        }
                                                    ?>
                                                    <a href="<?php echo $nav_url; ?>" target="_blank" class="flex-1 btn btn-primary py-3.5 font-bold shadow-lg shadow-blue-500/20 rounded-xl hover:scale-[1.02] transition-all flex items-center justify-center gap-2">
                                                        <i class="fa-solid fa-location-arrow"></i> Navigate
                                                    </a>
                                                    
                                                    <a href="tel:<?php echo htmlspecialchars($job['customer_phone'] ?? ''); ?>" class="flex-1 btn bg-white border border-gray-200 text-gray-800 py-3.5 font-bold rounded-xl hover:bg-gray-50 hover:scale-[1.02] transition-all flex items-center justify-center gap-2">
                                                        <i class="fa-solid fa-phone"></i> Call Client
                                                    </a>
                                                    
                                                    <?php if ($job['status'] === 'scheduled'): ?>
                                                        <form method="POST" class="w-full mt-3">
                                                            <input type="hidden" name="start_mission" value="1">
                                                            <input type="hidden" name="booking_id" value="<?php echo $job['booking_id']; ?>">
                                                            <input type="hidden" name="request_id" value="<?php echo $job['id']; ?>">
                                                            <button type="submit" class="btn btn-primary w-full py-4 text-sm font-black rounded-xl shadow-xl shadow-blue-600/20 uppercase tracking-widest">
                                                                <i class="fa-solid fa-play mr-2"></i> Confirm Start
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button type="button" class="w-full mt-3 btn btn-success py-4 text-sm font-black rounded-xl shadow-xl shadow-green-600/20 uppercase tracking-widest"
                                                                onclick="openDriverCompleteModal(<?php echo $job['booking_id']; ?>, <?php echo $job['id']; ?>, '<?php echo $job['type']; ?>')">
                                                            <i class="fa-solid fa-flag-checkered mr-2"></i> Complete Mission
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                    <?php elseif ($subtab == 'available'): ?>
                        <!-- Available Requests Grid -->
                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                            <?php if (empty($availableJobs)): ?>
                                <div class="col-span-1 xl:col-span-2 card p-16 text-center text-muted border-dashed bg-gray-50/50">
                                    <div class="w-24 h-24 bg-white shadow-sm rounded-full flex items-center justify-center mx-auto mb-6">
                                        <i class="fa-solid fa-map-location-dot text-5xl text-gray-300"></i>
                                    </div>
                                    <h3 class="font-bold text-2xl text-gray-400">No New Requests</h3>
                                    <p class="max-w-sm mx-auto mt-2">Check back later for new pickup or delivery requests.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($availableJobs as $job): ?>
                                    <div class="card overflow-hidden hover:shadow-2xl transition-all duration-300 group relative border-0 shadow-lg">
                                        <div class="absolute top-0 w-full h-1.5 bg-gradient-to-r from-green-500 to-teal-600"></div>
                                        <div class="p-8 flex flex-col h-full">
                                            <div class="flex justify-between items-start mb-6">
                                                <span class="badge bg-green-50 text-green-600 border border-green-100 px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider shadow-sm flex items-center gap-2">
                                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-bounce"></span> New Request
                                                </span>
                                                <div class="text-right">
                                                    <span class="text-[10px] text-muted font-bold block">Received</span>
                                                    <span class="text-xs font-bold text-gray-700"><?php echo date('M d, H:i', strtotime($job['request_date'])); ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-center gap-4 mb-6">
                                                <div class="w-14 h-14 bg-gray-100 rounded-2xl flex items-center justify-center text-gray-400 text-2xl shadow-inner">
                                                    <i class="fa-solid fa-car"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <h3 class="text-xl md:text-2xl font-black text-gray-900 leading-tight truncate">
                                                        <?php echo $job['year'] . ' ' . $job['make'] . ' ' . $job['model']; ?>
                                                    </h3>
                                                    <div class="flex gap-2 mt-1">
                                                        <span class="text-[10px] font-mono font-bold text-gray-500 bg-gray-50 px-2.5 py-1 rounded border border-gray-100">
                                                            <?php echo htmlspecialchars($job['license_plate']); ?>
                                                        </span>
                                                        <span class="text-[10px] font-black uppercase tracking-widest text-blue-600 bg-blue-50 px-2.5 py-1 rounded border border-blue-100">
                                                            <?php echo $job['type']; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex items-center justify-between mb-6 p-4 bg-gray-50/50 rounded-2xl border border-gray-100">
                                                <div class="flex flex-col gap-1">
                                                    <label class="text-[9px] uppercase font-bold text-muted tracking-widest">Customer</label>
                                                    <div class="text-sm font-black text-gray-900"><?php echo htmlspecialchars($job['customer_name']); ?></div>
                                                    <?php if(!empty($job['customer_phone'])): ?>
                                                        <div class="text-[11px] font-bold text-blue-600"><?php echo htmlspecialchars($job['customer_phone']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if(!empty($job['customer_phone'])): ?>
                                                    <a href="tel:<?php echo htmlspecialchars($job['customer_phone']); ?>" class="w-10 h-10 bg-primary/10 text-primary rounded-xl flex items-center justify-center hover:bg-primary hover:text-white transition-all shadow-sm">
                                                        <i class="fa-solid fa-phone"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>

                                            <div class="bg-gray-50/80 p-5 rounded-2xl border border-gray-100 mb-6 flex-1">
                                                <label class="text-[10px] uppercase font-bold text-muted mb-2 block tracking-widest flex items-center gap-2">
                                                    <i class="fa-solid fa-location-dot text-red-400"></i> Pickup/Drop Location
                                                </label>
                                                <p class="text-sm font-bold text-gray-800 line-clamp-2 leading-relaxed">
                                                    <?php echo htmlspecialchars($job['address']); ?>
                                                </p>
                                            </div>
                                            
                                            <form method="POST" onsubmit="return confirm('Accept this job? You will be marked as ON DUTY.');">
                                                <input type="hidden" name="action" value="accept_job">
                                                <input type="hidden" name="booking_id" value="<?php echo $job['booking_id']; ?>">
                                                <input type="hidden" name="request_id" value="<?php echo $job['id']; ?>">
                                                <button type="submit" name="accept_job" class="btn btn-primary w-full py-4 font-black shadow-xl shadow-blue-500/20 rounded-xl hover:scale-[1.02] transition-all uppercase tracking-widest text-sm">
                                                    <i class="fa-solid fa-check-double mr-2"></i> Accept Assignment
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php elseif ($activeTab === 'history'): ?>
                    <!-- Redesigned Mission History Section -->
                    <div class="animate-fade-in">
                        <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
                            <div>
                                <h1 class="text-3xl font-black text-gray-900 tracking-tight">Mission Log</h1>
                                <p class="text-sm font-bold text-gray-400 mt-1 uppercase tracking-widest opacity-60">Archive of completed logistics operations</p>
                            </div>
                            <div class="px-6 py-3 bg-gray-50 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-3">
                                <i class="fa-solid fa-clipboard-check text-primary"></i>
                                <div>
                                    <span class="text-lg font-black text-gray-900"><?php echo count($historyJobs); ?></span>
                                    <span class="text-[9px] font-black uppercase text-gray-400 ml-1 tracking-tighter">Completed Missions</span>
                                </div>
                            </div>
                        </div>

                        <div class="card p-0 overflow-hidden shadow-xl border-none">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left">
                                    <thead class="bg-gray-50/80 border-b border-gray-100">
                                        <tr class="text-[10px] font-black uppercase text-gray-400 tracking-[0.15em]">
                                            <th class="p-6">Completed Date</th>
                                            <th class="p-6">Vehicle & Customer</th>
                                            <th class="p-6">Mission Type</th>
                                            <th class="p-6">Service Fee</th>
                                            <th class="p-6 text-right">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-sm">
                                        <?php if (empty($historyJobs)): ?>
                                            <tr>
                                                <td colspan="5" class="p-20 text-center text-muted">
                                                    <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6">
                                                        <i class="fa-solid fa-clock-rotate-left text-5xl text-gray-200"></i>
                                                    </div>
                                                    <h3 class="font-bold text-xl text-gray-400">No Mission History</h3>
                                                    <p class="mt-2 text-xs">Your completed pickups and deliveries will appear here.</p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($historyJobs as $job): ?>
                                                <tr class="border-t border-gray-50 hover:bg-blue-50/30 transition-colors group">
                                                    <td class="p-6 font-bold text-gray-500 text-xs">
                                                        <?php echo date('M d, Y', strtotime($job['updated_at'])); ?>
                                                    </td>
                                                    <td class="p-6">
                                                        <div class="font-black text-gray-900 group-hover:text-primary transition-colors"><?php echo $job['year'] . ' ' . $job['make'] . ' ' . $job['model']; ?></div>
                                                        <div class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mt-0.5"><?php echo htmlspecialchars($job['customer_name']); ?></div>
                                                    </td>
                                                    <td class="p-6">
                                                        <span class="px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider <?php echo $job['type'] == 'pickup' ? 'bg-amber-50 text-amber-600 border border-amber-100' : 'bg-green-50 text-green-600 border border-green-100'; ?>">
                                                            <?php echo $job['type']; ?>
                                                        </span>
                                                    </td>
                                                    <td class="p-6 font-black text-gray-900">₹<?php echo number_format($job['fee'], 2); ?></td>
                                                    <td class="p-6 text-right">
                                                        <span class="badge badge-success px-3 py-1 rounded-md text-[9px] font-black uppercase tracking-widest">
                                                            Mission Accomplished
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

                <?php elseif ($activeTab === 'profile'): ?>
                    <!-- Professional Identity Section - Refined for Space -->
                    <div class="max-w-2xl mx-auto animate-fade-in pt-4">
                        <div class="card overflow-hidden shadow-xl border-0">
                            <div class="bg-gradient-to-r from-blue-600/5 to-transparent p-6 flex flex-col md:flex-row items-center gap-6 border-b border-gray-100">
                                <div class="relative">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name']); ?>&background=2563EB&color=fff&size=128" 
                                         class="w-16 h-16 rounded-xl object-cover shadow-lg border-2 border-white" alt="Profile">
                                    <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-primary text-white rounded-lg flex items-center justify-center shadow-md border border-white text-[8px]">
                                        <i class="fa-solid fa-shield-check"></i>
                                    </div>
                                </div>
                                <div class="text-center md:text-left">
                                    <h3 class="text-2xl font-black text-gray-900 mb-0.5"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
                                    <div class="flex flex-wrap gap-2 justify-center md:justify-start">
                                        <span class="px-3 py-1 bg-white rounded-full text-[10px] font-bold text-muted shadow-sm border border-gray-50 uppercase tracking-wider">Logistics Partner</span>
                                        <span class="px-3 py-1 bg-white rounded-full text-[10px] font-bold text-muted shadow-sm border border-gray-50 uppercase tracking-wider"><i class="fa-solid fa-id-card mr-1.5 opacity-60"></i> <?php echo htmlspecialchars($driverData['license_number'] ?? 'No License'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="p-8">
                                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="form-group flex flex-col gap-1.5">
                                        <label class="text-[10px] font-black uppercase text-gray-500 ml-1 tracking-widest">Full Name</label>
                                        <input type="text" name="name" class="form-control h-12 px-4 text-sm font-bold bg-white border-gray-200 focus:border-primary transition-all rounded-xl" value="<?php echo htmlspecialchars($_SESSION['user_name']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group flex flex-col gap-1.5">
                                        <label class="text-[10px] font-black uppercase text-gray-500 ml-1 tracking-widest">Registered Phone</label>
                                        <div class="relative h-12">
                                            <input type="text" class="form-control h-full px-4 text-sm font-bold bg-gray-50 cursor-not-allowed border-dashed rounded-xl" value="<?php echo htmlspecialchars($driverData['phone'] ?? 'N/A'); ?>" disabled>
                                            <i class="fa-solid fa-lock absolute right-4 top-1/2 -translate-y-1/2 text-muted opacity-30 text-xs"></i>
                                        </div>
                                    </div>

                                    <div class="md:col-span-2 pt-6 border-t border-gray-50 flex justify-end">
                                        <button type="submit" name="update_profile" class="btn btn-primary h-12 px-8 text-sm font-black shadow-lg shadow-blue-500/10 rounded-xl transition-all active:scale-95 uppercase tracking-widest">
                                            <i class="fa-solid fa-floppy-disk mr-2"></i> Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="mt-6 text-center">
                            <p class="text-[10px] text-muted font-bold uppercase tracking-widest opacity-60">Deployment Hardware: <?php echo htmlspecialchars($driverData['vehicle_number'] ?? 'Unassigned'); ?></p>
                        </div>
                    </div>

                <?php elseif ($activeTab === 'leave'): ?>
                    <!-- Leave Requests Section -->
                    <div class="flex flex-col lg:flex-row gap-8">
                        <!-- Submission Form -->
                        <div class="lg:w-1/3">
                            <div class="card p-8 sticky top-8">
                                <h3 class="text-2xl font-black text-gray-900 mb-6 font-primary">Request Leave</h3>
                                <form id="leaveRequestForm" class="flex flex-col gap-6">
                                    <input type="hidden" name="action" value="request">
                                    <div class="form-group flex flex-col gap-2">
                                        <label class="text-[10px] font-black uppercase text-gray-500 ml-1 tracking-widest">Type of Leave</label>
                                        <select name="leave_type" class="form-control h-12 px-4 font-bold rounded-xl" required>
                                            <option value="sick">Sick Leave</option>
                                            <option value="casual">Casual Leave</option>
                                            <option value="emergency">Emergency</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="form-group flex flex-col gap-2">
                                            <label class="text-[10px] font-black uppercase text-gray-500 ml-1 tracking-widest">Start Date</label>
                                            <input type="date" name="start_date" class="form-control h-12 px-4 font-bold rounded-xl" required min="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="form-group flex flex-col gap-2">
                                            <label class="text-[10px] font-black uppercase text-gray-500 ml-1 tracking-widest">End Date</label>
                                            <input type="date" name="end_date" class="form-control h-12 px-4 font-bold rounded-xl" required min="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group flex flex-col gap-2">
                                        <div class="flex justify-between items-center ml-1">
                                            <label class="text-[10px] font-black uppercase text-gray-500 tracking-widest">Reason</label>
                                            <span id="reasonCount" class="text-[10px] font-black text-gray-400 uppercase tracking-tighter">0 / 500</span>
                                        </div>
                                        <textarea id="leaveReason" name="reason" class="form-control p-4 font-normal text-sm rounded-xl h-32 resize-none border-2 border-gray-100 focus:border-primary transition-all outline-none" placeholder="Briefly explain your reason (min 15 chars)..." required minlength="15" maxlength="500"></textarea>
                                        <p id="validationMsg" class="text-[11px] text-red-500 hidden px-1 font-medium italic mt-1">Reason must be at least 15 characters.</p>
                                    </div>
                                    <button type="submit" class="btn btn-primary h-11 font-black text-xs rounded-xl shadow-lg shadow-blue-500/20 active:scale-95 transition-all uppercase tracking-widest">
                                        Submit Request
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Requests History -->
                        <div class="lg:w-2/3">
                            <h3 class="text-2xl font-black text-gray-900 mb-6 font-primary">My Leave History</h3>
                            <div class="grid grid-cols-1 gap-6">
                                <?php if (empty($leaveRequests)): ?>
                                    <div class="card p-12 text-center text-muted border-dashed bg-gray-50/50">
                                        <i class="fa-solid fa-calendar-xmark text-5xl mb-4 opacity-20"></i>
                                        <p class="font-bold">No leave requests found.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($leaveRequests as $lr): ?>
                                        <div class="card p-6 hover:shadow-xl transition-all border-l-4 <?php 
                                            echo $lr['status'] === 'approved' ? 'border-green-500' : ($lr['status'] === 'rejected' ? 'border-red-500' : 'border-yellow-500'); 
                                        ?>">
                                            <div class="flex flex-col md:flex-row justify-between gap-4 mb-4">
                                                <div>
                                                    <span class="text-[10px] uppercase font-black tracking-widest text-primary mb-1 block"><?php echo $lr['leave_type']; ?> Leave</span>
                                                    <h4 class="text-xl font-black text-gray-900">
                                                        <?php echo date('M d', strtotime($lr['start_date'])); ?> - <?php echo date('M d, Y', strtotime($lr['end_date'])); ?>
                                                    </h4>
                                                </div>
                                                <div class="flex items-center gap-3">
                                                    <span class="badge <?php 
                                                        echo $lr['status'] === 'approved' ? 'badge-success' : ($lr['status'] === 'rejected' ? 'badge-danger' : 'badge-warning'); 
                                                    ?> px-4 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider">
                                                        <?php echo $lr['status']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <p class="text-gray-600 font-medium text-sm mb-4"><?php echo htmlspecialchars($lr['reason']); ?></p>
                                            <?php if ($lr['admin_comment']): ?>
                                                <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                                                    <span class="text-[9px] uppercase font-black text-gray-400 mb-1 block">Admin Comment</span>
                                                    <p class="text-sm font-bold text-gray-800 italic">"<?php echo htmlspecialchars($lr['admin_comment']); ?>"</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <script>
                        const reasonInput = document.getElementById('leaveReason');
                        const reasonCount = document.getElementById('reasonCount');
                        const validationMsg = document.getElementById('validationMsg');

                        reasonInput.addEventListener('input', () => {
                            const length = reasonInput.value.length;
                            reasonCount.textContent = `${length} / 500`;
                            
                            if (length > 0 && length < 15) {
                                validationMsg.classList.remove('hidden');
                                reasonInput.classList.add('border-red-500');
                                reasonCount.classList.add('text-red-500');
                            } else {
                                validationMsg.classList.add('hidden');
                                reasonInput.classList.remove('border-red-500');
                                reasonCount.classList.remove('text-red-500');
                            }
                        });

                        document.getElementById('leaveRequestForm').addEventListener('submit', async (e) => {
                            e.preventDefault();
                            const formData = new FormData(e.target);
                            const data = Object.fromEntries(formData);
                            
                            try {
                                const response = await fetch('api/leave_handler.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify(data)
                                });
                                const result = await response.json();
                                if (result.success) {
                                    alert(result.message);
                                    location.reload();
                                } else {
                                    alert(result.message);
                                }
                            } catch (error) {
                                console.error('Error submitting leave request:', error);
                                alert('An error occurred. Please try again.');
                            }
                        });
                    </script>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <!-- Driver Completion Modal - Redesigned for Premium Aesthetics -->
    <div id="driverCompleteModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(12px); z-index: 10000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: 2rem; max-width: 450px; width: 100%; box-shadow: 0 40px 100px -20px rgba(0,0,0,0.4); overflow: hidden; animation: modalEnter 0.5s cubic-bezier(0.16, 1, 0.3, 1);">
            
            <!-- Modal Header with Ambient Blue Gradient -->
            <div style="padding: 25px 30px; background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); color: white; position: relative; overflow: hidden;">
                <!-- Decorative Elements -->
                <div style="position: absolute; top: -40px; right: -40px; width: 120px; height: 120px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                
                <div style="position: relative; z-index: 10; display: flex; align-items: center; gap: 15px;">
                    <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                        <i class="fa-solid fa-flag-checkered"></i>
                    </div>
                    <div>
                        <h2 id="modalTitle" style="margin: 0; font-size: 1.4rem; font-weight: 900; letter-spacing: -0.025em; line-height: 1.1;">Mission Complete</h2>
                        <p style="margin: 4px 0 0; font-size: 0.85rem; font-weight: 500; opacity: 0.9;">Log final fee to finish.</p>
                    </div>
                </div>
            </div>

            <div style="padding: 25px 30px;">
                <form method="POST">
                    <input type="hidden" name="action" value="complete_job">
                    <input type="hidden" name="booking_id" id="modal_booking_id">
                    <input type="hidden" name="request_id" id="modal_request_id">
                    
                    <div style="margin-bottom: 25px;">
                        <label style="display: block; font-size: 10px; font-weight: 900; color: #6b7280; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px; margin-left: 4px;">Service Fee (₹)</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); font-weight: 900; color: #9ca3af; font-size: 1rem;">₹</span>
                            <input type="number" name="fee" 
                                   style="width: 100%; height: 55px; padding: 0 15px 0 35px; font-size: 1.5rem; font-weight: 900; background: #f9fafb; border: 2px solid #f3f4f6; border-radius: 15px; outline: none; transition: all 0.3s;"
                                   onfocus="this.style.borderColor='#2563eb'; this.style.backgroundColor='#fff';"
                                   onblur="this.style.borderColor='#f3f4f6'; this.style.backgroundColor='#f9fafb';"
                                   required placeholder="0.00" min="0" step="0.01">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
                        <button type="submit" 
                                style="height: 50px; background: #111827; color: white; border: 0; border-radius: 15px; font-size: 0.95rem; font-weight: 800; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.15);"
                                onmouseover="this.style.backgroundColor='#000'; this.style.transform='translateY(-2px)';"
                                onmouseout="this.style.backgroundColor='#111827'; this.style.transform='none';">
                             <i class="fa-solid fa-check-double"></i> Submit & Finalize
                        </button>
                        <button type="button" onclick="document.getElementById('driverCompleteModal').style.display='none'"
                                style="height: 50px; background: #fff; color: #6b7280; border: 2px solid #f3f4f6; border-radius: 15px; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: all 0.3s;">
                            Cancel
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
        function openDriverCompleteModal(bookingId, requestId, type) {
            document.getElementById('modal_booking_id').value = bookingId;
            document.getElementById('modal_request_id').value = requestId;
            document.getElementById('modalTitle').innerText = (type === 'pickup' ? 'Complete Pickup' : 'Complete Delivery');
            document.getElementById('driverCompleteModal').style.display = 'flex';
        }

        // Close modal if clicked outside
        window.onclick = function(event) {
            const modal = document.getElementById('driverCompleteModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>
