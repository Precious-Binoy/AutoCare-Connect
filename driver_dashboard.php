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
$driverQuery = "SELECT d.*, u.phone, u.email, u.profile_image, u.dob, u.address FROM drivers d JOIN users u ON d.user_id = u.id WHERE d.user_id = ?";
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

                // Notify Customer
                $custQuery = "SELECT user_id FROM bookings WHERE id = ?";
                $custRes = executeQuery($custQuery, [$bookingId], 'i');
                if ($cust = $custRes->fetch_assoc()) {
                    $notifMsg = "Your vehicle has been successfully delivered. Thank you using AutoCare Connect!";
                    executeQuery("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Vehicle Delivered', ?, 'general')", [$cust['user_id'], $notifMsg], 'is');
                }
                
                // Notify Admin
                $adminRes = $conn->query("SELECT id FROM users WHERE role = 'admin'");
                while ($admin = $adminRes->fetch_assoc()) {
                    executeQuery("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Job Completed', 'Driver has completed the delivery for Booking #$bookingId', 'general')", [$admin['id']], 'i');
                }
            } else { // pickup
                // Update booking to show it's at workshop/confirmed
                $updateBooking = "UPDATE bookings SET status = 'confirmed', final_cost = IFNULL(final_cost, 0) + ? WHERE id = ?";
                executeQuery($updateBooking, [$fee, $bookingId], 'di');

                $insertUpdate = "INSERT INTO service_updates (booking_id, status, message, progress_percentage, updated_by) VALUES (?, 'confirmed', ?, 25, ?)";
                executeQuery($insertUpdate, [$bookingId, "Vehicle picked up and arrived at workshop. Ready for service.", $user_id], 'isi');

                // Notify Customer
                $custQuery = "SELECT user_id FROM bookings WHERE id = ?";
                $custRes = executeQuery($custQuery, [$bookingId], 'i');
                if ($cust = $custRes->fetch_assoc()) {
                    $notifMsg = "Your vehicle has arrived at our workshop and is ready for service.";
                    executeQuery("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Vehicle Arrived', ?, 'general')", [$cust['user_id'], $notifMsg], 'is');
                }
                
                // Notify Admin
                $adminRes = $conn->query("SELECT id FROM users WHERE role = 'admin'");
                while ($admin = $adminRes->fetch_assoc()) {
                    executeQuery("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Vehicle Arrived', 'Vehicle for Booking #$bookingId has arrived at workshop.', 'general')", [$admin['id']], 'i');
                }

                // Notify Assigned Mechanic (if any)
                $mechQuery = "SELECT mechanic_id FROM bookings WHERE id = ?";
                $mechRes = executeQuery($mechQuery, [$bookingId], 'i');
                if (($mechRow = $mechRes->fetch_assoc()) && !empty($mechRow['mechanic_id'])) {
                    // Get mechanic user_id
                    $mUserQuery = "SELECT user_id FROM mechanics WHERE id = ?";
                    $mUserRes = executeQuery($mUserQuery, [$mechRow['mechanic_id']], 'i');
                    if ($mUser = $mUserRes->fetch_assoc()) {
                         executeQuery("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Vehicle Arrived', 'Vehicle for Booking #$bookingId is now at the workshop.', 'general')", [$mUser['user_id']], 'i');
                    }
                }
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
    $phone = sanitizeInput($_POST['phone']);
    $dob = sanitizeInput($_POST['dob']);
    $address = sanitizeInput($_POST['address']);
    $license = sanitizeInput($_POST['license_number']);
    $experience = sanitizeInput($_POST['vehicle_number']); // Using vehicle_number as experience/years field based on context
    
    // Handle Image Upload
    $profile_image_path = $driverData['profile_image'] ?? null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'assets/uploads/profile/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileExt = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        $validExts = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExt, $validExts)) {
            $newFileName = 'user_' . $user_id . '_' . time() . '.' . $fileExt;
            $targetPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetPath)) {
                $profile_image_path = $targetPath;
            } else {
                $error_msg = "Failed to upload image.";
            }
        } else {
            $error_msg = "Invalid file type. Only JPG, PNG, GIF allowed.";
        }
    }

    if (empty($error_msg)) {
        // DOB Validation
        if (!empty($dob)) {
            $dobDate = new DateTime($dob);
            $today = new DateTime();
            $age = $today->diff($dobDate)->y;
            
            if ($dobDate > $today) {
                $error_msg = "Date of birth cannot be in the future.";
            } elseif ($age < 18) {
                $error_msg = "You must be at least 18 years old.";
            }
        }
    }

    if (empty($error_msg)) {
        $conn->begin_transaction();
        try {
            // Update users table
            $updateUserQuery = "UPDATE users SET name = ?, phone = ?, profile_image = ?, dob = ?, address = ? WHERE id = ?";
            executeQuery($updateUserQuery, [$name, $phone, $profile_image_path, $dob, $address, $user_id], 'sssssi');
            $_SESSION['user_name'] = $name;
            $_SESSION['user_phone'] = $phone;
            $_SESSION['profile_image'] = $profile_image_path;

            // Update drivers table
            $updateDriverQuery = "UPDATE drivers SET license_number = ?, vehicle_number = ? WHERE user_id = ?";
            executeQuery($updateDriverQuery, [$license, $experience, $user_id], 'ssi');

            $conn->commit();
            $success_msg = "Profile updated successfully!";
            
            // Refresh driver data
            $driverRes = executeQuery($driverQuery, [$user_id], 'i');
            $driverData = $driverRes->fetch_assoc();
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Error updating profile: " . $e->getMessage();
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
                
                // Send notification to customer
                $bookingQuery = "SELECT user_id FROM bookings WHERE id = ?";
                $bookingResult = executeQuery($bookingQuery, [$bookingId], 'i');
                if ($bookingRow = $bookingResult->fetch_assoc()) {
                    $notifQuery = "INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)";
                    $notifTitle = "🚗 Driver Assigned";
                    $notifMessage = "A driver has accepted your pickup/delivery request and will arrive soon.";
                    executeQuery($notifQuery, [$bookingRow['user_id'], $notifTitle, $notifMessage], 'iss');
                }
                
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

        // Notify Customer - Driver En Route
        $bQuery = "SELECT user_id FROM bookings WHERE id = ?";
        $bRes = executeQuery($bQuery, [$bookingId], 'i');
        if ($bRow = $bRes->fetch_assoc()) {
            executeQuery("INSERT INTO notifications (user_id, title, message, type) VALUES (?, '🚗 Driver En Route', 'Your driver is on the way to you. Please stay available!', 'service')", [$bRow['user_id']], 'i');
        }
        
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
    <style>
        /* Premium Pro Action Buttons */
        .btn-premium {
            background-color: #111827 !important; /* Deep Silk Black */
            color: #ffffff !important;
            border-radius: 14px !important;
            font-weight: 900 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
            text-decoration: none !important;
        }

        .btn-premium:hover {
            background-color: #000000 !important;
            transform: scale(1.05) translateY(-2px) !important;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1) !important;
            border-color: rgba(255, 255, 255, 0.2) !important;
        }

        .btn-premium:active {
            transform: scale(0.98) !important;
        }

        .btn-premium i {
            transition: transform 0.3s ease !important;
        }

        .btn-premium:hover i {
            transform: scale(1.2) rotate(5deg) !important;
        }

        .btn-premium-outline {
            background-color: #ffffff !important;
            color: #111827 !important;
            border: 2px solid #e5e7eb !important;
            border-radius: 14px !important;
            font-weight: 900 !important;
            text-transform: uppercase !important;
            transition: all 0.3s ease !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            text-decoration: none !important;
        }

        .btn-premium-outline:hover {
            border-color: #3b82f6 !important;
            background-color: #f9fafb !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05) !important;
        }

        .btn-premium-emerald {
            background-color: #059669 !important;
            color: #ffffff !important;
            border-radius: 14px !important;
            font-weight: 900 !important;
            text-transform: uppercase !important;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
            box-shadow: 0 10px 15px -3px rgba(5, 150, 105, 0.2) !important;
            border: none !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
        }

        .btn-premium-emerald:hover {
            background-color: #047857 !important;
            transform: scale(1.05) translateY(-2px) !important;
            box-shadow: 0 20px 25px -5px rgba(5, 150, 105, 0.3) !important;
        }
    </style>
    <?php
    // Helper to clean coordinates for Google Maps
    function cleanCoords($str) {
        if (empty($str)) return '';
        // Remove "Lat:", "Lng:", spaces, and other text labels
        return preg_replace('/[a-zA-Z:\s]/', '', $str);
    }
    ?>
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

                <?php if ($activeTab === 'jobs'): ?>
                    <!-- Welcome Message - Only show on jobs tab -->
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold">Welcome back, <?php echo htmlspecialchars($driverData['name'] ?? $_SESSION['user_name'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?>! 👋</h1>
                        <p class="text-gray-600">Here's your job overview for today.</p>
                    </div>
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
    <div class="max-w-4xl mx-auto mb-8">
        <div class="bg-white rounded-xl border border-blue-200 shadow-sm overflow-hidden relative p-8">
            <!-- Reference Header -->
            <div class="flex justify-between items-center mb-6">
                <div class="flex items-center gap-2 px-3 py-1 bg-green-50 rounded-full border border-green-100">
                    <span class="w-2.5 h-2.5 bg-green-500 rounded-full"></span>
                    <span class="text-[10px] font-black text-green-700 uppercase tracking-widest">Live Mission</span>
                </div>
                <div class="text-[14px] font-black text-slate-700 tracking-tight">#BK-<?php echo substr(strtoupper(md5($job['booking_number'])), 0, 6); ?></div>
            </div>

            <!-- Vehicle Title & Plate -->
            <div class="mb-8">
                <h2 class="text-2xl font-black text-slate-800 leading-tight"><?php echo htmlspecialchars($job['color'] . ' ' . $job['year'] . ' ' . $job['make'] . ' ' . $job['model']); ?></h2>
                <div class="text-[14px] font-black text-blue-600 mt-1 uppercase tracking-widest">
                    PLATE: <?php echo htmlspecialchars($job['license_plate']); ?>
                </div>
            </div>

            <!-- Simplified Customer Details Row Header -->
            <div class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-4 border-b border-slate-50 pb-2">Customer Details</div>

            <!-- Info Rows (Simplified & Colored) -->
            <div class="space-y-4 mb-8">
                <!-- Customer Row -->
                <div class="flex items-center gap-4">
                    <div class="w-5 flex justify-center text-slate-400"><i class="fa-solid fa-user text-sm"></i></div>
                    <div class="text-base font-black text-blue-600 uppercase">
                        <?php echo htmlspecialchars(strtoupper($job['customer_name'])); ?>
                    </div>
                </div>

                <!-- Phone Row -->
                <div class="flex items-center gap-4">
                    <div class="w-5 flex justify-center text-slate-400"><i class="fa-solid fa-phone text-sm"></i></div>
                    <div class="text-base font-black text-blue-600">
                        <?php echo htmlspecialchars($job['customer_phone']); ?>
                    </div>
                </div>

                <!-- Mission Phase Row -->
                <div class="flex items-center gap-4 pt-2">
                    <div class="w-5 flex justify-center text-slate-400"><i class="fa-solid fa-truck text-sm"></i></div>
                    <div class="text-[11px] font-black text-slate-800 uppercase tracking-widest">
                        Phase: <span class="text-blue-600"><?php echo htmlspecialchars($job['type']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Destination Target -->
            <div class="flex items-start gap-4 mb-6">
                <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shrink-0">
                    <i class="fa-solid fa-location-dot"></i>
                </div>
                <div>
                    <div class="text-[12px] font-black text-slate-800 uppercase tracking-tight mb-1">Destination Target</div>
                    <div class="text-sm font-bold text-slate-600 leading-snug">
                        <?php 
                        if (!empty($job['lat']) && !empty($job['lng'])) {
                            echo "Lat: " . $job['lat'] . ", Lng: " . $job['lng'];
                        } else {
                            echo htmlspecialchars($job['address']);
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- Extra Info Row (Lowercase, Small, Grey) -->
            <div class="flex flex-wrap items-center gap-6 mb-2">
                <div class="flex items-center gap-1.5 text-[10px] font-bold text-slate-400 lowercase italic opacity-80">
                    <i class="fa-solid fa-building text-[9px]"></i>
                    <?php echo !empty($job['landmark']) ? htmlspecialchars(strtolower($job['landmark'])) : 'near the location'; ?>
                </div>
                <div class="flex items-center gap-1.5 text-[10px] font-bold text-slate-400 lowercase italic opacity-80">
                    <i class="fa-solid fa-key text-[9px]"></i>
                    key placement inside the car
                </div>
            </div>

            <!-- Footer Toolbar (Premium Pro Action Buttons) -->
            <div class="mt-8 pt-4 -mx-8 -mb-8 px-8 py-5 bg-slate-50 border-t border-slate-100 flex items-center gap-4">
                <?php 
                $navQuery = (!empty($job['lat']) && !empty($job['lng'])) 
                    ? cleanCoords($job['lat']) . ',' . cleanCoords($job['lng']) 
                    : urlencode($job['address']);
                ?>
                <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo $navQuery; ?>" target="_blank" class="btn-premium flex-none h-14 px-8 w-auto">
                    <i class="fa-solid fa-diamond-turn-right text-lg text-blue-400 mr-3"></i> Navigate
                </a>
                
                <a href="tel:<?php echo htmlspecialchars($job['customer_phone']); ?>" class="btn-premium-outline flex-none h-14 px-8 w-auto">
                    <i class="fa-solid fa-phone text-lg mr-3"></i> Call client
                </a>
                
                <div class="flex-1">
                    <?php if ($job['status'] === 'scheduled'): ?>
                        <form method="POST" class="m-0">
                            <input type="hidden" name="start_mission" value="1">
                            <input type="hidden" name="booking_id" value="<?php echo $job['booking_id']; ?>">
                            <input type="hidden" name="request_id" value="<?php echo $job['id']; ?>">
                            <button type="submit" class="btn-premium w-full h-14 border-none">
                                <i class="fa-solid fa-play text-lg text-blue-400 mr-3"></i> Confirm Start
                            </button>
                        </form>
                    <?php else: ?>
                        <button type="button" class="btn-premium-emerald w-full h-14"
                                onclick="openDriverCompleteModal(<?php echo $job['booking_id']; ?>, <?php echo $job['id']; ?>, '<?php echo $job['type']; ?>')">
                            <i class="fa-solid fa-flag-checkered text-lg mr-3"></i> Confirm Finish
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
                                    <div class="max-w-4xl mx-auto mb-8">
                                        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden relative p-8 group hover:border-blue-200 transition-all">
                                            <!-- Available Header -->
                                            <div class="flex justify-between items-center mb-6">
                                                <div class="flex items-center gap-2 px-3 py-1 bg-emerald-50 rounded-full border border-emerald-100">
                                                    <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></span>
                                                    <span class="text-[10px] font-black text-emerald-700 uppercase tracking-widest">New Mission</span>
                                                </div>
                                                <div class="text-[14px] font-black text-slate-400 tracking-tight">#REQ-<?php echo $job['id']; ?></div>
                                            </div>

                                            <!-- Vehicle Title & Plate -->
                                            <div class="mb-8">
                                                <h2 class="text-2xl font-black text-slate-800 leading-tight"><?php echo htmlspecialchars($job['color'] . ' ' . $job['make'] . ' ' . $job['model']); ?></h2>
                                                <div class="text-[14px] font-black text-blue-600 mt-1 uppercase tracking-widest">
                                                    PLATE: <?php echo htmlspecialchars($job['license_plate']); ?>
                                                </div>
                                            </div>

                                            <!-- Simplified Customer Details Row Header -->
                                            <div class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-4 border-b border-slate-50 pb-2">Customer Details</div>

                                            <!-- Info Rows (Simplified & Colored) -->
                                            <div class="space-y-4 mb-8">
                                                <!-- Customer Row -->
                                                <div class="flex items-center gap-4">
                                                    <div class="w-5 flex justify-center text-slate-400"><i class="fa-solid fa-user text-sm"></i></div>
                                                    <div class="text-base font-black text-blue-600 uppercase">
                                                        <?php echo htmlspecialchars(strtoupper($job['customer_name'])); ?>
                                                    </div>
                                                </div>

                                                <!-- Mission Phase Row -->
                                                <div class="flex items-center gap-4 pt-2">
                                                    <div class="w-5 flex justify-center text-slate-400"><i class="fa-solid fa-screwdriver-wrench text-sm"></i></div>
                                                    <div class="text-[11px] font-black text-slate-800 uppercase tracking-widest">
                                                        Service: <span class="text-emerald-600"><?php echo htmlspecialchars($job['type']); ?></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Pickup Target -->
                                            <div class="flex items-start gap-4 mb-6">
                                                <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400 shrink-0">
                                                    <i class="fa-solid fa-location-dot"></i>
                                                </div>
                                                <div>
                                                    <div class="text-[12px] font-black text-slate-800 uppercase tracking-tight mb-1">Pickup Location</div>
                                                    <div class="text-sm font-bold text-slate-600 tracking-tight leading-snug">
                                                        <?php echo htmlspecialchars($job['address']); ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Action Footer (High-Impact Premium Button) -->
                                            <div class="mt-8 pt-4 -mx-8 -mb-8 px-8 py-5 bg-slate-50 border-t border-slate-100">
                                                <form method="POST" onsubmit="return confirm('Accept this job?');" class="m-0">
                                                    <input type="hidden" name="action" value="accept_job">
                                                    <input type="hidden" name="booking_id" value="<?php echo $job['booking_id']; ?>">
                                                    <input type="hidden" name="request_id" value="<?php echo $job['id']; ?>">
                                                    <button type="submit" name="accept_job" class="btn-premium w-full h-14 border-none py-0">
                                                        <i class="fa-solid fa-check-circle text-lg text-emerald-400 mr-3"></i> Accept Mission Assignment
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
                    <div class="animate-fade-in">
                        <!-- Centered History Header (Matched to Mechanic Dashboard) -->
                        <div class="text-center mb-20 relative z-20">
                            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Work History</h1>
                            <p class="text-slate-500 font-medium mt-1">Tracking your professional service journey.</p>
                            
                            <div class="mt-8 inline-flex px-6 py-3 bg-white rounded-2xl border border-gray-100 shadow-sm items-center gap-3 mx-auto">
                                <i class="fa-solid fa-clipboard-check text-[#3b82f6]"></i>
                                <div>
                                    <span class="text-lg font-black text-gray-900"><?php echo count($historyJobs); ?></span>
                                    <span class="text-[9px] font-black uppercase text-gray-400 ml-1 tracking-wider">Completed Jobs</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="max-w-6xl mx-auto">
                            <div class="card p-0 overflow-hidden bg-white rounded-xl border border-gray-100 shadow-sm">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-center">
                                        <thead style="background: #F8FAFC; border-bottom: 1px solid #e2e8f0;">
                                            <tr class="text-xs font-bold uppercase text-muted tracking-wider">
                                                <th class="p-5 text-center">Completed Date</th>
                                                <th class="p-5 text-center">Vehicle & Customer</th>
                                                <th class="p-5 text-center">Service Type</th>
                                                <th class="p-5 text-center">Service Bill</th>
                                                <th class="p-5 text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-sm">
                                            <?php if (empty($historyJobs)): ?>
                                                <tr>
                                                    <td colspan="5" class="p-16 text-center text-muted">
                                                        <i class="fa-solid fa-clock-rotate-left text-6xl mb-4 opacity-10"></i>
                                                        <p>No completed jobs in your history yet.</p>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($historyJobs as $job): 
                                                    // Vehicle Sanitization Filter
                                                    $rawParts = [
                                                        'year'  => trim($job['year'] ?? ''),
                                                        'make'  => trim($job['make'] ?? ''),
                                                        'model' => trim($job['model'] ?? ''),
                                                    ];
                                                    $cleanParts = [];
                                                    foreach ($rawParts as $p) {
                                                        if ($p !== '' && $p !== '0' && strtolower($p) !== 'null' && strtolower($p) !== 'n/a') {
                                                            $cleanParts[] = $p;
                                                        }
                                                    }
                                                    $vehicleTitle = implode(' ', $cleanParts) ?: 'Vehicle Details';
                                                ?>
                                                    <tr class="border-t border-gray-100 hover:bg-gray-50/50 transition-colors">
                                                        <td class="p-5 font-medium text-gray-500">
                                                            <?php echo date('M d, Y', strtotime($job['updated_at'])); ?>
                                                        </td>
                                                        <td class="p-5">
                                                            <div class="font-bold text-gray-900"><?php echo htmlspecialchars($vehicleTitle); ?></div>
                                                            <div class="text-xs text-muted font-medium"><?php echo htmlspecialchars(strtolower($job['customer_name'])); ?></div>
                                                        </td>
                                                        <td class="p-5 text-gray-700"><?php echo htmlspecialchars(ucfirst($job['type'])); ?></td>
                                                        <td class="p-5 font-black text-gray-900">₹<?php echo number_format($job['fee'], 2); ?></td>
                                                        <td class="p-5">
                                                            <span class="inline-flex items-center px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 border border-emerald-100 text-[10px] font-bold uppercase">
                                                                Completed
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
                    </div>
                    </div>

                <?php elseif ($activeTab === 'profile'): ?>
                    <!-- Modern Profile Redesign -->
                    <div class="animate-fade-in pt-6 pb-12">
                        
                        <?php 
                        // Check for missing details
                        $missingDetails = [];
                        if (empty($driverData['phone']) || $driverData['phone'] == 'N/A') $missingDetails[] = "Phone Number";
                        if (empty($driverData['license_number']) || strpos($driverData['license_number'], 'PEND-L') !== false) $missingDetails[] = "License Number";
                        
                        if (!empty($missingDetails)): 
                        ?>
                            <div class="profile-alert max-w-lg mx-auto shadow-sm">
                                <i class="fa-solid fa-circle-exclamation text-blue-600"></i>
                                <div>
                                    <h4 class="font-bold text-blue-800 text-sm mb-1">Complete Your Profile</h4>
                                    <p class="text-xs text-blue-600 leading-relaxed">
                                        Please add your <strong><?php echo implode(' and ', $missingDetails); ?></strong> to verify your account and start accepting missions.
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="profile-card">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="profile-avatar-container">
                                    <?php 
                                        $displayImage = !empty($driverData['profile_image']) ? $driverData['profile_image'] : 'assets/img/default-avatar.png';
                                    ?>
                                    <img src="<?php echo htmlspecialchars($displayImage); ?>" alt="Profile" class="profile-avatar" id="avatarPreview">
                                    <label for="profile_upload" class="edit-avatar-btn" title="Change Photo">
                                        <i class="fa-solid fa-pencil"></i>
                                    </label>
                                    <input type="file" name="profile_image" id="profile_upload" class="hidden" accept="image/*" onchange="previewImage(this)">
                                </div>

                                <h2 class="text-xl font-black text-gray-900 mb-1"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
                                <p class="text-xs font-bold text-muted uppercase tracking-wider mb-8">Driver & Logistics Partner</p>

                                <div class="text-left">
                                    <div class="input-group-modern">
                                        <label class="input-label-modern">Full Name</label>
                                        <input type="text" name="name" class="input-modern" value="<?php echo htmlspecialchars($_SESSION['user_name']); ?>" required>
                                    </div>

                                    <div class="input-group-modern">
                                        <label class="input-label-modern">
                                            Email Address 
                                            <span class="verified-badge"><i class="fa-solid fa-check"></i> Verified</span>
                                        </label>
                                        <input type="email" class="input-modern" value="<?php echo htmlspecialchars($driverData['email'] ?? 'No Email'); ?>" disabled>
                                    </div>

                                    <div class="input-group-modern">
                                        <label class="input-label-modern">Phone Number</label>
                                        <input type="tel" name="phone" class="input-modern" value="<?php echo htmlspecialchars($driverData['phone'] ?? ''); ?>" placeholder="Enter phone number" required>
                                    </div>

                                    <div class="input-group-modern">
                                        <label class="input-label-modern">Date of Birth</label>
                                        <input type="date" name="dob" class="input-modern" value="<?php echo htmlspecialchars($driverData['dob'] ?? ''); ?>">
                                    </div>

                                    <div class="input-group-modern">
                                        <label class="input-label-modern">Home Address</label>
                                        <textarea name="address" class="input-modern" style="min-height: 100px; resize: vertical;" placeholder="Enter your full address"><?php echo htmlspecialchars($driverData['address'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="input-group-modern">
                                            <label class="input-label-modern">License No.</label>
                                            <input type="text" name="license_number" class="input-modern" value="<?php echo htmlspecialchars($driverData['license_number'] ?? ''); ?>" placeholder="License No.">
                                        </div>
                                        <div class="input-group-modern">
                                            <label class="input-label-modern">Experience (Yrs)</label>
                                            <input type="number" name="vehicle_number" class="input-modern" value="<?php echo htmlspecialchars($driverData['vehicle_number'] ?? ''); ?>" placeholder="Years" disabled>
                                        </div>
                                    </div>
                                    
                                    <div class="input-group-modern">
                                        <label class="input-label-modern">Country</label>
                                        <select class="input-modern">
                                            <option>India</option>
                                        </select>
                                    </div>
                                </div>

                                <button type="submit" name="update_profile" class="btn-save-profile">
                                    Save Changes
                                </button>
                            </form>
                        </div>
                    </div>

                    <script>
                        function previewImage(input) {
                            if (input.files && input.files[0]) {
                                var reader = new FileReader();
                                reader.onload = function(e) {
                                    document.getElementById('avatarPreview').src = e.target.result;
                                }
                                reader.readAsDataURL(input.files[0]);
                            }
                        }
                    </script>

                <?php elseif ($activeTab === 'leave'): ?>
                    <!-- Leave Requests Section -->
                    <div class="flex flex-col lg:flex-row gap-8">
                        <!-- Submission Form Section -->
                        <div class="lg:w-1/3">
                            <div id="leaveFormSection" class="hidden animate-fade-in">
                                <div class="card p-5 sticky top-8">
                                    <h3 class="text-lg font-black text-gray-900 mb-4 font-primary border-b border-gray-100 pb-3">Request Leave</h3>
                                    <form id="leaveRequestForm" class="flex flex-col gap-4">
                                        <input type="hidden" name="action" value="request">
                                        <div class="form-group flex flex-col gap-1.5">
                                            <label class="text-[9px] font-black uppercase text-gray-400 ml-1 tracking-widest">Type of Leave</label>
                                            <select name="leave_type" class="form-control h-8 px-3 text-[11px] font-bold rounded-lg border-gray-200" required>
                                                <option value="sick">Sick Leave</option>
                                                <option value="casual">Casual Leave</option>
                                                <option value="emergency">Emergency</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div class="form-group flex flex-col gap-1.5">
                                                <label class="text-[9px] font-black uppercase text-gray-400 ml-1 tracking-widest">Start Date</label>
                                                <input type="date" name="start_date" class="form-control h-8 px-3 text-[11px] font-bold rounded-lg border-gray-200" required min="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                            <div class="form-group flex flex-col gap-1.5">
                                                <label class="text-[9px] font-black uppercase text-gray-400 ml-1 tracking-widest">End Date</label>
                                                <input type="date" name="end_date" class="form-control h-8 px-3 text-[11px] font-bold rounded-lg border-gray-200" required min="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                        </div>
                                        <div class="form-group flex flex-col gap-1.5">
                                            <div class="flex justify-between items-center ml-1">
                                                <label class="text-[9px] font-black uppercase text-gray-400 tracking-widest">Reason</label>
                                                <span id="reasonCount" class="text-[9px] font-black text-gray-300 uppercase">0 / 500</span>
                                            </div>
                                            <textarea id="leaveReason" name="reason" class="form-control p-3 font-medium text-[11px] rounded-lg h-24 resize-none border-gray-200 focus:border-primary transition-all outline-none" placeholder="Reason for leave..." required minlength="3" maxlength="500"></textarea>
                                            <div id="validationMsg" class="hidden animate-fade-in">
                                                <p class="text-[10px] text-[#db4437] font-bold mt-1 flex items-center gap-1">
                                                    <i class="fa-solid fa-circle-exclamation text-[8px]"></i>
                                                    Please provide a brief reason.
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex gap-2 pt-2 border-t border-gray-50 mt-1">
                                            <button type="submit" class="btn btn-primary flex-1 h-8 font-black text-[10px] rounded-lg uppercase tracking-wider">
                                                Submit Request
                                            </button>
                                            <button type="button" onclick="toggleLeaveForm()" class="btn btn-outline h-8 px-4 font-bold text-[10px] rounded-lg uppercase">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div id="leavePromptSection" class="animate-fade-in pt-4">
                                <button onclick="toggleLeaveForm()" class="btn btn-primary w-full h-12 rounded-xl shadow-lg shadow-blue-500/20 flex items-center justify-center gap-3 font-black text-sm uppercase tracking-widest active:scale-[0.98] transition-all">
                                    <i class="fa-solid fa-calendar-plus text-lg"></i>
                                    Apply for Leave
                                </button>
                            </div>
                        </div>

                        <!-- Requests History -->
                        <div class="lg:w-2/3">
                            <h3 class="text-2xl font-black text-gray-900 mb-6 font-primary">My Leave History</h3>
                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm text-left">
                                        <thead>
                                            <tr class="bg-gray-50 border-b border-gray-100">
                                                <th rowspan="2" class="px-4 py-3 font-bold text-gray-700 w-16 text-center border-r border-gray-100">Sl.No</th>
                                                <th colspan="2" class="px-4 py-2 font-bold text-gray-700 text-center border-b border-gray-100">Leave date</th>
                                                <th rowspan="2" class="px-4 py-3 font-bold text-gray-700 whitespace-nowrap text-center border-l border-gray-100">Applied On</th>
                                                <th rowspan="2" class="px-4 py-3 font-bold text-gray-700 text-center">Type</th>
                                                <th rowspan="2" class="px-4 py-3 font-bold text-gray-700 text-center">Reason</th>
                                                <th rowspan="2" class="px-4 py-3 font-bold text-gray-700 text-center">Status</th>
                                                <th rowspan="2" class="px-4 py-3 font-bold text-gray-700 text-center">Comments</th>
                                            </tr>
                                            <tr class="bg-gray-50 border-b border-gray-100">
                                                <th class="px-4 py-2 text-[10px] font-bold text-gray-500 text-center border-r border-gray-100">Start</th>
                                                <th class="px-4 py-2 text-[10px] font-bold text-gray-500 text-center">End</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <?php if (empty($leaveRequests)): ?>
                                                <tr>
                                                    <td colspan="8" class="px-4 py-10 text-center text-gray-500 italic">
                                                        No leave requests found.
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($leaveRequests as $index => $lr): ?>
                                                    <tr class="hover:bg-gray-50 transition-colors">
                                                        <td class="px-4 py-4 text-gray-500 text-center"><?php echo $index + 1; ?>.</td>
                                                        <td class="px-4 py-4 font-medium text-gray-900 whitespace-nowrap text-center">
                                                            <?php echo date('Y-m-d', strtotime($lr['start_date'])); ?>
                                                        </td>
                                                        <td class="px-4 py-4 font-medium text-gray-900 whitespace-nowrap text-center border-l border-gray-50">
                                                            <?php echo date('Y-m-d', strtotime($lr['end_date'])); ?>
                                                        </td>
                                                        <td class="px-4 py-4 text-gray-600 whitespace-nowrap text-center">
                                                            <?php echo date('Y-m-d H:i', strtotime($lr['created_at'])); ?>
                                                        </td>
                                                        <td class="px-4 py-4 text-gray-700 capitalize text-center">
                                                            <?php echo htmlspecialchars($lr['leave_type']); ?>
                                                        </td>
                                                        <td class="px-4 py-4 text-gray-600 min-w-[200px] text-center">
                                                            <?php echo htmlspecialchars($lr['reason']); ?>
                                                        </td>
                                                        <td class="px-4 py-4 text-center">
                                                            <span class="font-black tracking-wide uppercase text-[11px]" style="color: <?php 
                                                                $status_val = strtolower(trim($lr['status']));
                                                                echo ($status_val === 'approved') ? '#16a34a' : ($status_val === 'rejected' ? '#dc2626' : '#f59e0b'); 
                                                            ?> !important;">
                                                                <?php echo htmlspecialchars($lr['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-4 text-gray-500 text-center">
                                                            <?php echo $lr['admin_comment'] ? htmlspecialchars($lr['admin_comment']) : 'Nil'; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
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
                            
                            if (length > 0 && length < 3) {
                                validationMsg.classList.remove('hidden');
                                reasonInput.style.borderColor = '#db4437';
                                reasonCount.style.color = '#db4437';
                            } else {
                                validationMsg.classList.add('hidden');
                                reasonInput.style.borderColor = '';
                                reasonCount.style.color = '';
                            }
                        });

                        function toggleLeaveForm() {
                            const form = document.getElementById('leaveFormSection');
                            const prompt = document.getElementById('leavePromptSection');
                            if (form.classList.contains('hidden')) {
                                form.classList.remove('hidden');
                                prompt.classList.add('hidden');
                            } else {
                                form.classList.add('hidden');
                                prompt.classList.remove('hidden');
                            }
                        }

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
                        <h2 id="modalTitle" style="margin: 0; font-size: 1.5rem; font-weight: 900; letter-spacing: -0.025em; line-height: 1.1;">Service Finalization</h2>
                        <p style="margin: 4px 0 0; font-size: 0.85rem; font-weight: 500; opacity: 0.9;">Document work performed and finalize costs.</p>
                    </div>
                </div>
            </div>

            <div style="padding: 25px 30px;">
                <form method="POST">
                    <input type="hidden" name="action" value="complete_job">
                    <input type="hidden" name="booking_id" id="modal_booking_id">
                    <input type="hidden" name="request_id" id="modal_request_id">
                    
                    <div style="margin-bottom: 25px;">
                        <label style="display: block; font-size: 10px; font-weight: 900; color: #6b7280; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px; margin-left: 4px;">Labor Fee (₹)</label>
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
                             <i class="fa-solid fa-check-double"></i> Finalize & Generate Bill
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
<script src="assets/js/message-admin.js"></script>
<script src="assets/js/profile-validation.js"></script>
</body>
</html>
