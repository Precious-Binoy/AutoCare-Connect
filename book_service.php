<?php 
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = getDbConnection();
$user_id = getCurrentUserId();
$current_page = 'book_service.php';

$success_msg = '';
$error_msg = '';

// Handle Booking Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
    $service_type = sanitizeInput($_POST['service_type'] ?? '');
    $preferred_date = sanitizeInput($_POST['preferred_date'] ?? '');
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    if ($vehicle_id <= 0 || empty($service_type) || empty($preferred_date)) {
        $error_msg = 'Please select a vehicle and complete all required service details.';
    } else {
        $conn->begin_transaction();
        try {
            $booking_number = 'BK-' . strtoupper(bin2hex(random_bytes(4)));
            $has_pickup = (isset($_POST['request_pickup']) && $_POST['request_pickup'] === '1') ? 1 : 0;
            $query = "INSERT INTO bookings (booking_number, user_id, vehicle_id, service_type, preferred_date, notes, status, has_pickup_delivery) VALUES (?, ?, ?, ?, ?, ?, 'confirmed', ?)";
            executeQuery($query, [$booking_number, $user_id, $vehicle_id, $service_type, $preferred_date, $notes, $has_pickup], 'siisssi');
            $booking_id = $conn->insert_id;

            // Handle Pickup & Delivery Request
            if ($has_pickup) {
                // Pickup Details
                $p_address = sanitizeInput($_POST['pickup_address'] ?? '');
                $p_parking = sanitizeInput($_POST['pickup_parking'] ?? '');
                $p_landmark = sanitizeInput($_POST['pickup_landmark'] ?? '');
                $p_lat = sanitizeInput($_POST['pickup_lat'] ?? '');
                $p_lng = sanitizeInput($_POST['pickup_lng'] ?? '');
                $p_time = sanitizeInput($_POST['pickup_time'] ?? '');
                $p_phone = sanitizeInput($_POST['pickup_phone'] ?? ($_SESSION['user_phone'] ?? ''));

                // Valet Pickup Row
                $pd_query = "INSERT INTO pickup_delivery (booking_id, type, address, landmark, parking_info, lat, lng, scheduled_time, contact_phone, status) VALUES (?, 'pickup', ?, ?, ?, ?, ?, ?, ?, 'scheduled')";
                executeQuery($pd_query, [$booking_id, $p_address, $p_landmark, $p_parking, $p_lat, $p_lng, $preferred_date, $p_phone], 'isssssss');

                // Return Delivery Row (Always use same as pickup as per user request)
                $pd_del_query = "INSERT INTO pickup_delivery (booking_id, type, address, landmark, parking_info, lat, lng, contact_phone, status) VALUES (?, 'delivery', ?, ?, ?, ?, ?, ?, 'pending')";
                executeQuery($pd_del_query, [$booking_id, $p_address, $p_landmark, $p_parking, $p_lat, $p_lng, $p_phone], 'issssss');
            }

            // Initial service update
            $insertUpdate = "INSERT INTO service_updates (booking_id, status, message, progress_percentage, updated_by) VALUES (?, 'confirmed', 'Service booking confirmed and scheduled.', 10, ?)";
            executeQuery($insertUpdate, [$booking_id, $user_id], 'ii');

            $conn->commit();
            header("Location: customer_dashboard.php?booking_success=1&ref=" . $booking_number);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = 'Error creating booking: ' . $e->getMessage();
        }
    }
}

// Fetch User's Vehicles
$vehiclesQuery = "SELECT * FROM vehicles WHERE user_id = ?";
$vehiclesRes = executeQuery($vehiclesQuery, [$user_id], 'i');
$vehicles = $vehiclesRes->fetch_all(MYSQLI_ASSOC);

$pre_selected_vehicle = intval($_GET['vehicle_id'] ?? 0);

$page_title = 'Book Service';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="page-content">
                <?php if ($error_msg): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-2xl relative mb-6 animate-fade-in flex items-center gap-3">
                        <i class="fa-solid fa-circle-exclamation text-xl"></i>
                        <span class="font-bold"><?php echo htmlspecialchars($error_msg); ?></span>
                    </div>
                <?php endif; ?>

                <div class="mb-10">
                    <h1 class="text-4xl font-black text-gray-900 tracking-tight">Schedule Your Service</h1>
                    <p class="text-muted font-medium text-lg">Select a vehicle and let our experts handle the rest.</p>
                </div>

                <form method="POST" id="bookingForm" class="space-y-12 pb-20">
                    <input type="hidden" name="submit_booking" value="1">
                    
                    <!-- Vehicle Selection -->
                    <section>
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-black text-gray-800 flex items-center gap-3">
                                <div class="w-10 h-10 bg-primary/10 text-primary rounded-xl flex items-center justify-center">
                                    <i class="fa-solid fa-car-side"></i>
                                </div>
                                1. Choose Your Vehicle
                            </h2>
                            <a href="my_vehicles.php" class="text-primary font-bold text-sm hover:underline flex items-center gap-2">
                                <i class="fa-solid fa-plus-circle"></i> Add New
                            </a>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php if (empty($vehicles)): ?>
                                <div class="col-span-full card p-12 text-center border-dashed border-2">
                                    <p class="text-muted mb-4">You don't have any vehicles in your garage.</p>
                                    <a href="my_vehicles.php" class="btn btn-outline">Add Vehicle Now</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <div class="vehicle-select-card card p-6 cursor-pointer transition-all duration-300 border-2 <?php echo ($pre_selected_vehicle == $vehicle['id']) ? 'border-primary bg-primary/5' : 'border-transparent hover:border-gray-200'; ?>" 
                                         data-vehicle-id="<?php echo $vehicle['id']; ?>"
                                         onclick="toggleVehicleSelection(this, <?php echo $vehicle['id']; ?>)">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 bg-white rounded-xl shadow-sm border border-gray-100 flex items-center justify-center text-primary text-xl">
                                                <i class="fa-solid fa-car"></i>
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="font-black text-gray-900"><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></h4>
                                                <p class="text-[10px] font-black uppercase text-muted tracking-widest mt-1"><?php echo htmlspecialchars($vehicle['license_plate']); ?></p>
                                            </div>
                                            <div class="selection-indicator w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all <?php echo ($pre_selected_vehicle == $vehicle['id']) ? 'bg-primary border-primary' : 'border-gray-200'; ?>">
                                                <i class="fa-solid fa-check text-[10px] text-white <?php echo ($pre_selected_vehicle == $vehicle['id']) ? '' : 'hidden'; ?>"></i>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <input type="hidden" name="vehicle_id" id="selectedVehicleId" value="<?php echo $pre_selected_vehicle; ?>">
                        </div>
                    </section>

                    <!-- Service Details -->
                    <section class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                        <div>
                            <h2 class="text-xl font-black text-gray-800 flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 bg-primary/10 text-primary rounded-xl flex items-center justify-center">
                                    <i class="fa-solid fa-screwdriver-wrench"></i>
                                </div>
                                2. Service Options
                            </h2>
                            <div class="card p-8 space-y-6 shadow-xl shadow-gray-100/50">
                                <div class="form-group flex flex-col gap-2">
                                    <label class="text-[10px] font-black uppercase text-gray-500 ml-1 tracking-widest">Type of Service</label>
                                    <div class="relative">
                                        <select name="service_type" class="form-control h-16 px-6 text-lg font-bold rounded-2xl bg-gray-50 border-gray-100 focus:bg-white transition-all appearance-none cursor-pointer" required>
                                            <option value="" disabled selected>Select from specialties...</option>
                                            <option value="Periodic Maintenance">Periodic Maintenance (Service)</option>
                                            <option value="Engine Repair">Engine Diagnostic & Repair</option>
                                            <option value="Brake System">Brake System Refurbishment</option>
                                            <option value="Electrical & Electronics">Electrical & Electronics</option>
                                            <option value="Bodywork & Painting">Bodywork & Premium Painting</option>
                                            <option value="Suspension Work">Steering & Suspension</option>
                                        </select>
                                        <i class="fa-solid fa-chevron-down absolute right-6 top-1/2 -translate-y-1/2 text-gray-300 pointer-events-none"></i>
                                    </div>
                                </div>

                                <div class="form-group flex flex-col gap-2">
                                    <label class="text-[10px] font-black uppercase text-gray-500 ml-1 tracking-widest">Preferred Service Date</label>
                                    <input type="date" name="preferred_date" id="preferredDateInput" class="form-control h-16 px-6 text-lg font-bold rounded-2xl bg-gray-50 border-gray-100 focus:bg-white transition-all" min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h2 class="text-xl font-black text-gray-800 flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 bg-primary/10 text-primary rounded-xl flex items-center justify-center">
                                    <i class="fa-solid fa-comment-dots"></i>
                                </div>
                                3. Additional Instructions
                            </h2>
                            <div class="card p-8 h-full shadow-xl shadow-gray-100/50">
                                <div class="form-group flex flex-col gap-2 h-full">
                                    <label class="text-[10px] font-black uppercase text-gray-500 ml-1 tracking-widest">Service Notes / Issues Reported</label>
                                    <textarea name="notes" class="form-control flex-1 p-6 text-lg font-bold rounded-2xl bg-gray-50 border-gray-100 focus:bg-white transition-all" placeholder="Describe any specific problems, sounds, or requests you have..."></textarea>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Valet Pickup & Delivery - Modern Design -->
                    <section class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                        <div>
                            <h2 class="text-xl font-black text-gray-800 flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 bg-primary/10 text-primary rounded-xl flex items-center justify-center">
                                    <i class="fa-solid fa-truck-pickup"></i>
                                </div>
                                4. Valet Pickup & Delivery
                            </h2>
                            <div class="card p-8 space-y-6 shadow-xl shadow-gray-100/50">
                                <!-- Enable Toggle -->
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100">
                                    <div class="flex items-center gap-3">
                                        <i class="fa-solid fa-truck text-primary text-xl"></i>
                                        <div>
                                            <p class="font-bold text-gray-900">Enable Doorstep Service</p>
                                            <p class="text-xs text-muted">We'll pick up and deliver your vehicle</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="request_pickup" value="1" id="pickupToggle">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>

                                <!-- Pickup Details -->
                                <div id="pickupDetails" class="hidden space-y-6 animate-fade-in">
                                    <!-- Contact Phone -->
                                    <div class="form-group flex flex-col gap-2">
                                        <label class="text-[10px] font-black uppercase text-gray-500 ml-1 tracking-widest">Contact Phone</label>
                                        <div class="relative">
                                            <i class="fa-solid fa-phone absolute left-6 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                            <input type="tel" 
                                                   name="pickup_phone" 
                                                   value="<?php echo htmlspecialchars($_SESSION['user_phone'] ?? ''); ?>" 
                                                   class="form-control h-16 pl-14 pr-6 text-lg font-bold rounded-2xl bg-gray-50 border-gray-100 focus:bg-white transition-all w-full" 
                                                   placeholder="Mobile Number"
                                                   pattern="[0-9]{10}"
                                                   maxlength="10">
                                        </div>
                                    </div>

                                    <!-- Address Input -->
                                    <div class="form-group flex flex-col gap-2">
                                        <label class="text-[10px] font-black uppercase text-gray-500 ml-1 tracking-widest">Pickup Address</label>
                                        <div class="relative">
                                            <i class="fa-solid fa-location-dot absolute left-6 top-6 text-gray-400"></i>
                                            <textarea name="pickup_address" 
                                                      id="pickupAddressInput" 
                                                      rows="3"
                                                      class="form-control pl-14 pr-6 pt-5 text-base font-semibold rounded-2xl bg-gray-50 border-gray-100 focus:bg-white transition-all w-full resize-none" 
                                                      placeholder="House No., Building Name, Street, Area"></textarea>
                                        </div>
                                        <input type="hidden" name="pickup_lat" id="pickupLat">
                                        <input type="hidden" name="pickup_lng" id="pickupLng">
                                        
                                        <!-- Beautiful Colorful Buttons -->
                                        <div class="grid grid-cols-2 gap-4 mt-4">
                                            <!-- Current Location Button - Blue/Cyan Gradient -->
                                            <button type="button" 
                                                    id="useCurrentLocationBtn" 
                                                    class="group relative px-6 py-5 bg-gradient-to-br from-cyan-400 via-blue-500 to-blue-600 text-white rounded-2xl font-bold shadow-[0_8px_0_0_rgb(30,64,175),0_10px_20px_0_rgba(59,130,246,0.5)] hover:shadow-[0_4px_0_0_rgb(30,64,175),0_6px_15px_0_rgba(59,130,246,0.6)] active:shadow-[0_0_0_0_rgb(30,64,175),0_2px_8px_0_rgba(59,130,246,0.4)] hover:translate-y-[4px] active:translate-y-[8px] transition-all duration-200 flex flex-col items-center justify-center gap-2 border-b-4 border-blue-900 overflow-hidden">
                                                <!-- Shine effect -->
                                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
                                                <!-- Icon -->
                                                <div class="relative z-10 w-12 h-12 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
                                                    <i class="fa-solid fa-location-crosshairs text-2xl"></i>
                                                </div>
                                                <!-- Text -->
                                                <span class="relative z-10 text-sm">Use Current Location</span>
                                            </button>
                                            
                                            <!-- Choose from Map Button - Green/Emerald Gradient -->
                                            <button type="button" 
                                                    id="openMapBtn" 
                                                    class="group relative px-6 py-5 bg-gradient-to-br from-emerald-400 via-green-500 to-green-600 text-white rounded-2xl font-bold shadow-[0_8px_0_0_rgb(21,128,61),0_10px_20px_0_rgba(34,197,94,0.5)] hover:shadow-[0_4px_0_0_rgb(21,128,61),0_6px_15px_0_rgba(34,197,94,0.6)] active:shadow-[0_0_0_0_rgb(21,128,61),0_2px_8px_0_rgba(34,197,94,0.4)] hover:translate-y-[4px] active:translate-y-[8px] transition-all duration-200 flex flex-col items-center justify-center gap-2 border-b-4 border-green-900 overflow-hidden">
                                                <!-- Shine effect -->
                                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
                                                <!-- Icon -->
                                                <div class="relative z-10 w-12 h-12 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
                                                    <i class="fa-solid fa-map-marked-alt text-2xl"></i>
                                                </div>
                                                <!-- Text -->
                                                <span class="relative z-10 text-sm">Choose from Map</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Landmark -->
                                    <div class="form-group flex flex-col gap-2">
                                        <label class="text-[10px] font-black uppercase text-gray-500 ml-1 tracking-widest">Landmark (Optional)</label>
                                        <div class="relative">
                                            <i class="fa-solid fa-building absolute left-6 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                            <input type="text" 
                                                   name="pickup_landmark" 
                                                   class="form-control h-16 pl-14 pr-6 text-lg font-bold rounded-2xl bg-gray-50 border-gray-100 focus:bg-white transition-all w-full" 
                                                   placeholder="e.g., Near St. Mary's Church">
                                        </div>
                                    </div>

                                    <!-- Key Placement -->
                                    <div class="form-group flex flex-col gap-2">
                                        <label class="text-[10px] font-black uppercase text-gray-500 ml-1 tracking-widest">Key Placement & Parking Info</label>
                                        <div class="relative">
                                            <i class="fa-solid fa-key absolute left-6 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                            <input type="text" 
                                                   name="pickup_parking" 
                                                   class="form-control h-16 pl-14 pr-6 text-lg font-bold rounded-2xl bg-gray-50 border-gray-100 focus:bg-white transition-all w-full" 
                                                   placeholder="e.g., Key with security, Plot 12">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Info Section - Only show when enabled -->
                        <div id="infoSection" class="hidden">
                            <h2 class="text-xl font-black text-gray-800 flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 bg-primary/10 text-primary rounded-xl flex items-center justify-center">
                                    <i class="fa-solid fa-circle-info"></i>
                                </div>
                                Service Information
                            </h2>
                            <div class="card p-8 h-full shadow-xl shadow-gray-100/50 space-y-6">
                                <!-- Info Cards -->
                                <div class="space-y-4">
                                    <div class="p-5 bg-blue-50 rounded-2xl border border-blue-100 flex items-start gap-4">
                                        <div class="w-10 h-10 bg-blue-500 text-white rounded-xl flex items-center justify-center flex-shrink-0">
                                            <i class="fa-solid fa-truck-pickup"></i>
                                        </div>
                                        <div>
                                            <p class="font-bold text-gray-900 mb-1">Free Pickup</p>
                                            <p class="text-sm text-gray-600">Our driver will collect your vehicle from your doorstep at the scheduled time.</p>
                                        </div>
                                    </div>

                                    <div class="p-5 bg-green-50 rounded-2xl border border-green-100 flex items-start gap-4">
                                        <div class="w-10 h-10 bg-green-500 text-white rounded-xl flex items-center justify-center flex-shrink-0">
                                            <i class="fa-solid fa-rotate-left"></i>
                                        </div>
                                        <div>
                                            <p class="font-bold text-gray-900 mb-1">Return to Same Location</p>
                                            <p class="text-sm text-gray-600">Vehicle will be delivered back to your pickup address after service completion.</p>
                                        </div>
                                    </div>

                                    <div class="p-5 bg-purple-50 rounded-2xl border border-purple-100 flex items-start gap-4">
                                        <div class="w-10 h-10 bg-purple-500 text-white rounded-xl flex items-center justify-center flex-shrink-0">
                                            <i class="fa-solid fa-phone-volume"></i>
                                        </div>
                                        <div>
                                            <p class="font-bold text-gray-900 mb-1">Driver Coordination</p>
                                            <p class="text-sm text-gray-600">Our driver will call you 30 minutes before arrival for coordination.</p>
                                        </div>
                                    </div>

                                    <div class="p-5 bg-orange-50 rounded-2xl border border-orange-100 flex items-start gap-4">
                                        <div class="w-10 h-10 bg-orange-500 text-white rounded-xl flex items-center justify-center flex-shrink-0">
                                            <i class="fa-solid fa-shield-halved"></i>
                                        </div>
                                        <div>
                                            <p class="font-bold text-gray-900 mb-1">Fully Insured</p>
                                            <p class="text-sm text-gray-600">Your vehicle is covered under comprehensive insurance during transit.</p>
                                        </div>
                                    </div>

                                    <div class="p-5 bg-indigo-50 rounded-2xl border border-indigo-100 flex items-start gap-4">
                                        <div class="w-10 h-10 bg-indigo-500 text-white rounded-xl flex items-center justify-center flex-shrink-0">
                                            <i class="fa-solid fa-map-pin"></i>
                                        </div>
                                        <div>
                                            <p class="font-bold text-gray-900 mb-1">Flexible Location</p>
                                            <p class="text-sm text-gray-600">Set pickup location from anywhere - use your current location or choose from map.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Note -->
                                <div class="p-4 bg-gray-50 rounded-xl border border-gray-200">
                                    <p class="text-xs text-gray-600 leading-relaxed">
                                        <i class="fa-solid fa-info-circle text-primary mr-1"></i>
                                        <strong>Note:</strong> Please ensure someone is available at the pickup location to hand over the vehicle and keys to our driver.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Map Modal - Fixed -->
                    <div id="mapModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[9999] flex items-center justify-center p-4" style="display: none;">
                        <div class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden animate-fade-in">
                            <!-- Modal Header -->
                            <div class="px-8 py-6 bg-gradient-to-r from-purple-500 to-indigo-600 text-white flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center">
                                        <i class="fa-solid fa-map-location-dot text-2xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-2xl font-bold">Choose Pickup Location</h3>
                                        <p class="text-sm text-white/80">Click on map or drag marker to set exact location</p>
                                    </div>
                                </div>
                                <button type="button" id="closeMapBtn" class="w-12 h-12 hover:bg-white/20 rounded-xl transition-all flex items-center justify-center">
                                    <i class="fa-solid fa-times text-2xl"></i>
                                </button>
                            </div>

                            <!-- Map Container -->
                            <div class="relative h-[500px] bg-gray-100">
                                <div id="pickupMap" class="w-full h-full"></div>
                                <div id="mapLoader" class="absolute inset-0 flex items-center justify-center bg-white" style="display: flex;">
                                    <div class="flex flex-col items-center gap-4">
                                        <div class="w-16 h-16 border-4 border-purple-200 border-t-purple-600 rounded-full animate-spin"></div>
                                        <span class="text-lg font-bold text-gray-700">Loading Map...</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Footer -->
                            <div class="px-8 py-6 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="w-3 h-3 rounded-full bg-green-500 animate-pulse"></span>
                                    <span id="mapCoordinates" class="text-sm font-semibold text-gray-700">Click map to select location</span>
                                </div>
                                <button type="button" id="confirmLocationBtn" class="px-8 py-3 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-600 hover:to-indigo-700 text-white rounded-xl font-bold shadow-lg hover:shadow-xl transition-all">
                                    <i class="fa-solid fa-check mr-2"></i>
                                    Confirm Location
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-center gap-6 pt-10">
                        <button type="submit" class="btn btn-primary h-20 px-24 text-2xl font-black rounded-3xl shadow-2xl shadow-blue-100 transition-all hover:scale-105 active:scale-95 flex items-center gap-4">
                            Confirm Booking <i class="fa-solid fa-arrow-right-long opacity-50"></i>
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <style>
        /* Modern Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 28px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #e5e7eb;
            transition: .3s;
            border-radius: 28px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        input:checked + .toggle-slider {
            background-color: #3b82f6;
        }
        input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }
        
        .h-20 { height: 5rem; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Vehicle Selection Logic
            window.toggleVehicleSelection = function(card, id) {
                const hiddenInput = document.getElementById('selectedVehicleId');
                const currentSelected = hiddenInput.value;
                
                if (currentSelected == id) {
                    hiddenInput.value = '0';
                    card.classList.remove('border-primary', 'bg-primary/5');
                    card.querySelector('.selection-indicator').classList.add('border-gray-200');
                    card.querySelector('.selection-indicator').classList.remove('bg-primary', 'border-primary');
                    card.querySelector('.selection-indicator i').classList.add('hidden');
                } else {
                    document.querySelectorAll('.vehicle-select-card').forEach(c => {
                        c.classList.remove('border-primary', 'bg-primary/5');
                        c.classList.add('border-transparent');
                        const si = c.querySelector('.selection-indicator');
                        si.classList.add('border-gray-200');
                        si.classList.remove('bg-primary', 'border-primary');
                        si.querySelector('i').classList.add('hidden');
                    });
                    
                    hiddenInput.value = id;
                    card.classList.add('border-primary', 'bg-primary/5');
                    card.classList.remove('border-transparent');
                    const si = card.querySelector('.selection-indicator');
                    si.classList.remove('border-gray-200');
                    si.classList.add('bg-primary', 'border-primary');
                    si.querySelector('i').classList.remove('hidden');
                }
            };

            // 2. Valet Pickup & Delivery - Modern Design with Working Modal
            let pickupMap, pickupMarker;
            const pickupToggle = document.getElementById('pickupToggle');
            const pickupDetails = document.getElementById('pickupDetails');
            const infoSection = document.getElementById('infoSection');
            const addressInput = document.getElementById('pickupAddressInput');
            const latInput = document.getElementById('pickupLat');
            const lngInput = document.getElementById('pickupLng');
            const mapModal = document.getElementById('mapModal');
            const mapLoader = document.getElementById('mapLoader');
            const openMapBtn = document.getElementById('openMapBtn');
            const closeMapBtn = document.getElementById('closeMapBtn');
            const confirmLocationBtn = document.getElementById('confirmLocationBtn');
            const useLocationBtn = document.getElementById('useCurrentLocationBtn');
            const mapCoordinates = document.getElementById('mapCoordinates');

            // Toggle pickup details and info section visibility
            pickupToggle.addEventListener('change', function() {
                if (this.checked) {
                    pickupDetails.classList.remove('hidden');
                    infoSection.classList.remove('hidden');
                } else {
                    pickupDetails.classList.add('hidden');
                    infoSection.classList.add('hidden');
                }
            });

            // Open map modal - FIXED WITH ERROR HANDLING
            openMapBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('🗺️ Button clicked - Opening map modal...');
                
                // Check if Leaflet is loaded
                if (typeof L === 'undefined') {
                    alert('Map library is loading... Please wait a moment and try again.');
                    console.error('❌ Leaflet not loaded!');
                    return;
                }
                
                // Show loading state on button
                const originalHTML = this.innerHTML;
                this.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-2xl"></i><span class="text-sm mt-2">Opening Map...</span>';
                this.disabled = true;
                
                try {
                    // Force display with multiple methods
                    mapModal.style.display = 'flex';
                    mapModal.style.visibility = 'visible';
                    mapModal.style.opacity = '1';
                    mapModal.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                    
                    console.log('✅ Modal display set to flex');
                    console.log('Modal element:', mapModal);
                    
                    // Initialize map after a short delay
                    setTimeout(() => {
                        try {
                            initializeMap();
                            // Restore button
                            openMapBtn.innerHTML = originalHTML;
                            openMapBtn.disabled = false;
                        } catch (error) {
                            console.error('❌ Map initialization error:', error);
                            alert('Error loading map. Please refresh the page and try again.');
                            mapModal.style.display = 'none';
                            openMapBtn.innerHTML = originalHTML;
                            openMapBtn.disabled = false;
                        }
                    }, 300);
                } catch (error) {
                    console.error('❌ Error opening modal:', error);
                    alert('Error opening map. Please try again.');
                    this.innerHTML = originalHTML;
                    this.disabled = false;
                }
            });

            // Close map modal
            closeMapBtn.addEventListener('click', function() {
                mapModal.style.display = 'none';
                mapModal.classList.add('hidden');
                document.body.style.overflow = ''; // Restore scroll
            });

            // Close modal on backdrop click
            mapModal.addEventListener('click', function(e) {
                if (e.target === mapModal) {
                    mapModal.style.display = 'none';
                    mapModal.classList.add('hidden');
                    document.body.style.overflow = '';
                }
            });

            // Confirm location and close modal
            confirmLocationBtn.addEventListener('click', function() {
                if (latInput.value && lngInput.value) {
                    mapModal.style.display = 'none';
                    mapModal.classList.add('hidden');
                    document.body.style.overflow = '';
                } else {
                    alert('Please select a location on the map');
                }
            });

            // Initialize Leaflet Map
            function initializeMap() {
                if (!pickupMap) {
                    console.log('Initializing map...');
                    setTimeout(() => {
                        // Create map
                        pickupMap = L.map('pickupMap', {
                            scrollWheelZoom: true,
                            zoomControl: true,
                            dragging: true,
                            touchZoom: true,
                            doubleClickZoom: true
                        }).setView([10.8505, 76.2711], 13);
                        
                        // Add tiles
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '© OpenStreetMap',
                            maxZoom: 19
                        }).addTo(pickupMap);
                        
                        // Click event
                        pickupMap.on('click', function(e) {
                            updateLocation(e.latlng.lat, e.latlng.lng);
                        });
                        
                        // Hide loader
                        pickupMap.whenReady(() => {
                            setTimeout(() => {
                                mapLoader.style.display = 'none';
                                pickupMap.invalidateSize();
                                console.log('Map ready!');
                            }, 500);
                        });

                        // Load existing location if available
                        if (latInput.value && lngInput.value) {
                            updateLocation(parseFloat(latInput.value), parseFloat(lngInput.value), false);
                        } else if (navigator.geolocation) {
                            // Get current location
                            navigator.geolocation.getCurrentPosition(
                                (position) => {
                                    updateLocation(position.coords.latitude, position.coords.longitude);
                                },
                                (error) => {
                                    console.log('Geolocation error:', error);
                                },
                                { enableHighAccuracy: true, timeout: 5000 }
                            );
                        }
                    }, 300);
                } else {
                    setTimeout(() => {
                        pickupMap.invalidateSize();
                        if (pickupMarker) {
                            pickupMap.setView(pickupMarker.getLatLng(), 16);
                        }
                    }, 400);
                }
            }

            // Update location
            function updateLocation(lat, lng, reverseGeocode = true) {
                if (!pickupMap) return;

                const latlng = [lat, lng];
                
                // Create or update marker
                if (!pickupMarker) {
                    pickupMarker = L.marker(latlng, { 
                        draggable: true,
                        title: 'Drag to adjust'
                    }).addTo(pickupMap);
                    
                    // Drag event
                    pickupMarker.on('dragend', function(e) {
                        const pos = e.target.getLatLng();
                        updateLocation(pos.lat, pos.lng);
                    });
                } else {
                    pickupMarker.setLatLng(latlng);
                }

                // Center map
                pickupMap.setView(latlng, 16);
                
                // Update hidden inputs
                latInput.value = lat.toFixed(6);
                lngInput.value = lng.toFixed(6);

                // Update coordinates display
                mapCoordinates.textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;

                // Reverse geocode to get readable address
                if (reverseGeocode) {
                    addressInput.value = "📍 Getting address...";
                    
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`, {
                        headers: { 'Accept-Language': 'en-US,en;q=0.9' }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.address) {
                            // Extract readable address components
                            const addr = data.address;
                            let addressParts = [];
                            
                            // Build readable address
                            if (addr.house_number) addressParts.push(addr.house_number);
                            if (addr.road) addressParts.push(addr.road);
                            else if (addr.pedestrian) addressParts.push(addr.pedestrian);
                            
                            if (addr.neighbourhood) addressParts.push(addr.neighbourhood);
                            else if (addr.suburb) addressParts.push(addr.suburb);
                            
                            if (addr.city) addressParts.push(addr.city);
                            else if (addr.town) addressParts.push(addr.town);
                            else if (addr.village) addressParts.push(addr.village);
                            
                            if (addr.state) addressParts.push(addr.state);
                            if (addr.postcode) addressParts.push(addr.postcode);
                            
                            const readableAddress = addressParts.length > 0 ? addressParts.join(', ') : data.display_name;
                            addressInput.value = readableAddress;
                        } else if (data && data.display_name) {
                            addressInput.value = data.display_name;
                        } else {
                            addressInput.value = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                        }
                    })
                    .catch(error => {
                        console.error('Geocoding error:', error);
                        addressInput.value = `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}`;
                    });
                }
            }

            // Use current location button
            useLocationBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (navigator.geolocation) {
                    const btn = this;
                    const originalHTML = btn.innerHTML;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-lg relative z-10"></i><span class="relative z-10">Getting...</span>';
                    btn.disabled = true;
                    
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            btn.innerHTML = originalHTML;
                            btn.disabled = false;
                            
                            const lat = position.coords.latitude;
                            const lng = position.coords.longitude;
                            
                            // Update hidden inputs
                            latInput.value = lat.toFixed(6);
                            lngInput.value = lng.toFixed(6);
                            
                            // Reverse geocode to get readable address
                            addressInput.value = "📍 Getting address...";
                            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`, {
                                headers: { 'Accept-Language': 'en-US,en;q=0.9' }
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data && data.address) {
                                    // Extract readable address components
                                    const addr = data.address;
                                    let addressParts = [];
                                    
                                    // Build readable address
                                    if (addr.house_number) addressParts.push(addr.house_number);
                                    if (addr.road) addressParts.push(addr.road);
                                    else if (addr.pedestrian) addressParts.push(addr.pedestrian);
                                    
                                    if (addr.neighbourhood) addressParts.push(addr.neighbourhood);
                                    else if (addr.suburb) addressParts.push(addr.suburb);
                                    
                                    if (addr.city) addressParts.push(addr.city);
                                    else if (addr.town) addressParts.push(addr.town);
                                    else if (addr.village) addressParts.push(addr.village);
                                    
                                    if (addr.state) addressParts.push(addr.state);
                                    if (addr.postcode) addressParts.push(addr.postcode);
                                    
                                    const readableAddress = addressParts.length > 0 ? addressParts.join(', ') : data.display_name;
                                    addressInput.value = readableAddress;
                                } else if (data && data.display_name) {
                                    addressInput.value = data.display_name;
                                } else {
                                    addressInput.value = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                                }
                            })
                            .catch(error => {
                                console.error('Geocoding error:', error);
                                addressInput.value = `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}`;
                            });
                        },
                        (error) => {
                            btn.innerHTML = originalHTML;
                            btn.disabled = false;
                            alert("Unable to get your location. Please enable location services or choose from map.");
                            console.error('Geolocation error:', error);
                        },
                        { enableHighAccuracy: true, timeout: 10000 }
                    );
                } else {
                    alert("Geolocation is not supported by your browser. Please choose from map.");
                }
            });
        });
    </script>
</body>
</html>
