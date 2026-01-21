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
                $status = 'delivered';
                $updateBooking = "UPDATE bookings SET status = ?, final_cost = IFNULL(final_cost, 0) + ? WHERE id = ?";
                executeQuery($updateBooking, [$status, $fee, $bookingId], 'sdi');
                
                // Final service update
                $insertUpdate = "INSERT INTO service_updates (booking_id, status, message, progress_percentage, updated_by) VALUES (?, ?, ?, ?, ?)";
                executeQuery($insertUpdate, [$bookingId, 'delivered', "Vehicle has been delivered back to the customer. Delivery Fee: ₹" . number_format($fee, 2), 100, $user_id], 'issii');
            } else { // pickup
                // Update booking to show it's at workshop/confirmed
                $updateBooking = "UPDATE bookings SET status = 'confirmed', final_cost = IFNULL(final_cost, 0) + ? WHERE id = ?";
                executeQuery($updateBooking, [$fee, $bookingId], 'di');

                $insertUpdate = "INSERT INTO service_updates (booking_id, status, message, progress_percentage, updated_by) VALUES (?, ?, ?, ?, ?)";
                executeQuery($insertUpdate, [$bookingId, 'in_transit', "Vehicle picked up and arrived at workshop. Pickup Fee: ₹" . number_format($fee, 2), 25, $user_id], 'issii');
            }

            // Set driver back to available
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

// Check if driver already has an active task
$hasActiveTaskQuery = "SELECT COUNT(*) as active_count FROM pickup_delivery WHERE driver_user_id = ? AND status IN ('scheduled', 'in_transit')";
$hasActiveTaskRes = executeQuery($hasActiveTaskQuery, [$user_id], 'i');
$hasActiveTask = $hasActiveTaskRes->fetch_assoc()['active_count'] > 0;

// Fetch Available Jobs (Sequenced)
// 1. Pickup: status 'scheduled' and b.status 'pending' or 'confirmed'
// 2. Delivery: status 'scheduled' and b.status 'ready_for_delivery'
$availableJobsQuery = "SELECT pd.*, b.booking_number, b.status as booking_status, v.make, v.model, v.year, v.license_plate, u.name as customer_name, u.phone as customer_phone
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

// Handle Job Acceptance (Self-Assignment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_job'])) {
    if ($hasActiveTask) {
        $error_msg = "You already have an active mission. Complete it before accepting another.";
    } else {
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
}

// Fetch Active Jobs for this driver
$activeJobsQuery = "SELECT pd.*, b.booking_number, v.make, v.model, v.year, v.license_plate, u.name as customer_name, u.phone as customer_phone
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
$historyQuery = "SELECT pd.*, b.booking_number, v.make, v.model, v.year, v.license_plate 
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
                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                            <?php if (empty($availableJobs)): ?>
                                <div class="col-span-1 xl:col-span-2 card p-16 text-center text-muted border-dashed">
                                    <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6">
                                        <i class="fa-solid fa-map-location-dot text-4xl opacity-20"></i>
                                    </div>
                                    <h3 class="font-bold text-2xl text-gray-400">No New Requests</h3>
                                    <p class="max-w-sm mx-auto mt-2">Check back later for new pickup or delivery requests.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($availableJobs as $job): ?>
                                    <div class="card overflow-hidden hover:shadow-2xl transition-all duration-300 border border-gray-100 group relative">
                                        <div class="absolute top-0 left-0 w-1 h-full bg-blue-500 group-hover:w-2 transition-all"></div>
                                        <div class="flex flex-col h-full">
                                            <div class="p-6 md:p-8 flex-1">
                                                <div class="flex justify-between items-start mb-6">
                                                    <span class="badge badge-blue px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider shadow-sm">
                                                        <i class="fa-solid <?php echo $job['type'] == 'pickup' ? 'fa-truck-pickup' : 'fa-truck-fast'; ?> mr-1"></i>
                                                        <?php echo ucfirst($job['type']); ?> Request
                                                    </span>
                                                    <div class="text-right">
                                                        <span class="font-mono text-xs font-bold text-gray-400 block tracking-tight">#<?php echo $job['booking_number']; ?></span>
                                                        <span class="text-[10px] text-muted font-bold block mt-0.5"><?php echo date('M d, H:i', strtotime($job['request_date'])); ?></span>
                                                    </div>
                                                </div>
                                                
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-2">
                                                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-50 group-hover:border-blue-50 transition-colors">
                                                        <label class="text-[10px] uppercase font-bold text-gray-400 mb-2 block tracking-widest">Transport Vehicle</label>
                                                        <div class="flex items-center gap-3">
                                                            <div class="w-10 h-10 bg-white rounded-lg shadow-sm flex items-center justify-center text-gray-400 shrink-0">
                                                                <i class="fa-solid fa-car"></i>
                                                            </div>
                                                            <div class="min-w-0">
                                                                <h3 class="text-sm font-black text-gray-900 leading-tight truncate">
                                                                    <?php echo $job['year'] . ' ' . $job['make'] . ' ' . $job['model']; ?>
                                                                </h3>
                                                                <p class="font-mono text-[10px] text-gray-500 mt-1 bg-white px-1.5 py-0.5 rounded border border-gray-100 w-fit"><?php echo htmlspecialchars($job['license_plate']); ?></p>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-50 group-hover:border-red-50 transition-colors">
                                                        <label class="text-[10px] uppercase font-bold text-gray-400 mb-2 block tracking-widest">Target Location</label>
                                                        <div class="flex items-start gap-3">
                                                            <div class="w-10 h-10 bg-white rounded-lg shadow-sm flex items-center justify-center text-red-400 shrink-0">
                                                                <i class="fa-solid fa-location-dot"></i>
                                                            </div>
                                                            <div class="min-w-0">
                                                                <p class="font-bold text-gray-800 text-sm leading-snug line-clamp-2"><?php echo htmlspecialchars($job['address']); ?></p>
                                                                <?php if (!empty($job['parking_info'])): ?>
                                                                    <p class="text-[10px] text-gray-500 mt-1 truncate"><i class="fa-solid fa-square-parking mr-1"></i> <?php echo htmlspecialchars($job['parking_info']); ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="p-4 bg-gray-50/50 border-t border-gray-100">
                                                <form method="POST" onsubmit="return confirm('Accept this job? You will be marked as ON DUTY.');">
                                                    <input type="hidden" name="action" value="accept_job">
                                                    <input type="hidden" name="booking_id" value="<?php echo $job['booking_id']; ?>">
                                                    <input type="hidden" name="request_id" value="<?php echo $job['id']; ?>">
                                                    <button type="submit" name="accept_job" class="btn btn-primary w-full py-3.5 font-bold shadow-lg shadow-blue-500/20 text-sm rounded-xl hover:scale-[1.02] transition-transform">
                                                        Accept Mission <i class="fa-solid fa-arrow-right ml-2 opacity-60"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                    <?php else: ?>
                        <!-- Active Jobs Section -->
                        <div class="grid grid-cols-1 gap-8">
                            <?php if (empty($activeJobs)): ?>
                                <div class="card p-16 text-center text-muted border-dashed bg-gray-50/50">
                                    <div class="w-20 h-20 bg-white shadow-sm rounded-full flex items-center justify-center mx-auto mb-6">
                                        <i class="fa-solid fa-truck-fast text-4xl text-gray-300"></i>
                                    </div>
                                    <h3 class="font-bold text-2xl text-gray-400">No Active Mission</h3>
                                    <p class="max-w-sm mx-auto mt-2">You are currently standby. Check 'Available Requests' to pick up a new job.</p>
                                    <a href="?tab=jobs&subtab=available" class="btn btn-primary mt-8 px-8 py-3 rounded-xl font-bold shadow-lg shadow-blue-500/20">Find Next Mission</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($activeJobs as $job): ?>
                                    <div class="card overflow-hidden border-0 shadow-2xl relative">
                                        <!-- Ambient Header Background -->
                                        <div class="absolute top-0 w-full h-32 bg-gradient-to-r from-gray-900 to-gray-800"></div>
                                        
                                        <div class="relative p-6 md:p-10">
                                            <!-- Job Header -->
                                            <div class="flex flex-col md:flex-row justify-between items-start gap-4 mb-8">
                                                <div class="text-white">
                                                    <span class="badge bg-green-500/20 text-green-300 border border-green-500/20 backdrop-blur-md px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest mb-3 inline-block animate-pulse">
                                                        <i class="fa-solid fa-circle text-[6px] mr-2"></i> Live Mission
                                                    </span>
                                                    <h3 class="text-3xl md:text-4xl font-black text-white mb-1"><?php echo $job['year'] . ' ' . $job['make'] . ' ' . $job['model']; ?></h3>
                                                    <p class="text-sm font-medium text-gray-400">Booking #<?php echo $job['booking_number']; ?></p>
                                                </div>
                                                <div class="text-right">
                                                    <span class="badge bg-white/10 backdrop-blur-md text-white border border-white/10 px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider shadow-xl">
                                                        <i class="fa-solid <?php echo $job['type'] == 'pickup' ? 'fa-truck-pickup' : 'fa-truck-fast'; ?> mr-2"></i>
                                                        <?php echo ucfirst($job['type']); ?> Phase
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Job Details Grid -->
                                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                                                <!-- Location Card -->
                                                <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-100 flex flex-col justify-between group hover:border-blue-100 transition-colors">
                                                    <div>
                                                        <label class="text-[10px] uppercase font-bold text-gray-400 mb-4 block tracking-wider flex items-center gap-2">
                                                            <i class="fa-solid fa-map-pin text-red-500"></i> Destination Target
                                                        </label>
                                                        
                                                        <div class="mb-4">
                                                            <div class="font-bold text-gray-900 text-lg leading-snug mb-2"><?php echo htmlspecialchars($job['address']); ?></div>
                                                            <div class="flex flex-wrap gap-2">
                                                                <?php if(!empty($job['landmark'])): ?>
                                                                    <div class="text-[10px] font-bold text-gray-600 uppercase bg-gray-100 px-2 py-1 rounded-lg">
                                                                        <i class="fa-solid fa-building mr-1"></i> <?php echo htmlspecialchars($job['landmark']); ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if(!empty($job['parking_info'])): ?>
                                                                    <div class="text-[10px] font-bold text-blue-600 uppercase bg-blue-50 px-2 py-1 rounded-lg">
                                                                        <i class="fa-solid fa-info-circle mr-1"></i> <?php echo htmlspecialchars($job['parking_info']); ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="pt-4 mt-2 border-t border-gray-100">
                                                        <?php 
                                                            $nav_url = "https://www.google.com/maps/search/?api=1&query=";
                                                            if (!empty($job['lat']) && !empty($job['lng'])) {
                                                                $nav_url .= $job['lat'] . "," . $job['lng'];
                                                            } else {
                                                                $nav_url .= urlencode($job['address']);
                                                            }
                                                        ?>
                                                        <a href="<?php echo $nav_url; ?>" target="_blank" class="btn btn-primary w-full py-3.5 text-xs font-bold rounded-xl items-center justify-center flex shadow-lg shadow-blue-500/20 hover:scale-[1.02] transition-transform">
                                                            <i class="fa-solid fa-location-arrow mr-2"></i> Start Navigation
                                                        </a>
                                                    </div>
                                                </div>

                                                <!-- Customer Card -->
                                                <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-100 flex flex-col justify-between group hover:border-purple-100 transition-colors">
                                                    <div>
                                                        <label class="text-[10px] uppercase font-bold text-gray-400 mb-4 block tracking-wider flex items-center gap-2">
                                                            <i class="fa-solid fa-user text-purple-500"></i> Client Contact
                                                        </label>
                                                        <div class="flex items-center gap-4 mb-4">
                                                            <div class="w-12 h-12 bg-gray-900 text-white rounded-2xl shadow-lg flex items-center justify-center text-lg font-bold shrink-0">
                                                                <?php echo strtoupper(substr($job['customer_name'] ?? 'C', 0, 1)); ?>
                                                            </div>
                                                            <div>
                                                                <div class="font-bold text-gray-900 text-lg leading-snug"><?php echo htmlspecialchars($job['customer_name']); ?></div>
                                                                <div class="text-xs text-muted font-mono mt-1"><?php echo htmlspecialchars($job['customer_phone']); ?></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="pt-4 mt-2 border-t border-gray-100 flex gap-3">
                                                        <a href="tel:<?php echo htmlspecialchars($job['customer_phone']); ?>" class="btn btn-outline bg-gray-50 border-gray-200 flex-1 py-3.5 text-xs font-bold rounded-xl items-center justify-center flex hover:bg-gray-100 hover:border-gray-300 text-gray-700">
                                                            <i class="fa-solid fa-phone mr-2"></i> Call
                                                        </a>
                                                        <button onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($job['customer_phone']); ?>'); alert('Copied!');" class="btn btn-outline bg-gray-50 border-gray-200 w-12 py-3.5 rounded-xl flex items-center justify-center hover:bg-gray-100">
                                                            <i class="fa-solid fa-copy text-gray-500"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Actions -->
                                             <div class="bg-gray-50 -mx-6 -mb-6 md:-mx-10 md:-mb-10 p-6 md:p-8 border-t border-gray-200">
                                                 <?php if ($job['status'] === 'scheduled'): ?>
                                                     <form method="POST">
                                                         <input type="hidden" name="action" value="accept_job">
                                                         <input type="hidden" name="booking_id" value="<?php echo $job['booking_id']; ?>">
                                                         <input type="hidden" name="request_id" value="<?php echo $job['id']; ?>">
                                                         <button type="submit" class="btn btn-primary w-full py-4 text-base font-black rounded-xl shadow-lg shadow-blue-500/20 hover:shadow-xl hover:-translate-y-1 transition-all">
                                                             <i class="fa-solid fa-play mr-2"></i> Confirm Start of Mission
                                                         </button>
                                                     </form>
                                                 <?php else: ?>
                                                     <button type="button" class="btn btn-success w-full py-4 text-base font-black rounded-xl shadow-lg shadow-green-500/20 hover:shadow-xl hover:-translate-y-1 transition-all" 
                                                             onclick="openDriverCompleteModal(<?php echo $job['booking_id']; ?>, <?php echo $job['id']; ?>, '<?php echo $job['type']; ?>')">
                                                         <i class="fa-solid fa-flag-checkered mr-2"></i> Mission Accomplished
                                                     </button>
                                                 <?php endif; ?>
                                                <p class="text-[10px] text-center text-muted font-bold mt-4 flex items-center justify-center opacity-70">
                                                    <i class="fa-solid fa-shield-halved mr-1.5"></i> Follow safety protocols strictly.
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
    <!-- Driver Completion Modal -->
    <div id="driverCompleteModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 1.5rem;">
        <div style="background: white; border-radius: 2rem; max-width: 500px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden; animation: modalEnter 0.4s cubic-bezier(0.16, 1, 0.3, 1);">
            <div class="p-10">
                <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center mb-6 text-2xl">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <h2 class="text-3xl font-black mb-2" id="modalTitle">Complete Mission</h2>
                <p class="text-muted text-lg mb-8 font-medium">Please enter the fee for this service.</p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="complete_job">
                    <input type="hidden" name="booking_id" id="modal_booking_id">
                    <input type="hidden" name="request_id" id="modal_request_id">
                    
                    <div class="form-group mb-8">
                        <label class="text-xs font-black uppercase text-gray-500 mb-2 block ml-1">Service Fee (₹) *</label>
                        <div class="relative">
                            <span class="absolute left-5 top-1/2 -translate-y-1/2 font-bold text-gray-400">₹</span>
                            <input type="number" name="fee" class="form-control h-16 pl-10 pr-5 text-xl font-black bg-gray-50 border-gray-200 focus:bg-white transition-all rounded-2xl" 
                                   required placeholder="0.00" min="0" step="0.01">
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4">
                        <button type="submit" class="btn btn-primary flex-1 h-16 text-lg font-bold rounded-2xl shadow-lg shadow-blue-100">
                             Submit & Finalize
                        </button>
                        <button type="button" class="btn btn-outline flex-1 h-16 text-lg font-bold rounded-2xl" onclick="document.getElementById('driverCompleteModal').style.display='none'">
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
