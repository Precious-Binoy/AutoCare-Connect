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
                executeQuery($pd_query, [$booking_id, $p_address, $p_landmark, $p_parking, $p_lat, $p_lng, $p_time, $p_phone], 'isssssss');

                // Return Delivery Row (Always use same as pickup as per user request)
                $pd_del_query = "INSERT INTO pickup_delivery (booking_id, type, address, landmark, parking_info, lat, lng, contact_phone, status) VALUES (?, 'delivery', ?, ?, ?, ?, ?, ?, 'pending')";
                executeQuery($pd_del_query, [$booking_id, $p_address, $p_landmark, $p_parking, $p_lat, $p_lng, $p_phone], 'isssssss');
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
                                    <input type="date" name="preferred_date" class="form-control h-16 px-6 text-lg font-bold rounded-2xl bg-gray-50 border-gray-100 focus:bg-white transition-all" required>
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

                    <!-- Logistics Option: Refined Style -->
                    <!-- Logistics Option: Final Streamlined Design -->
                    <section class="mt-12">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                            <h2 class="text-xl font-black text-gray-800 flex items-center gap-3">
                                <div class="w-10 h-10 bg-primary/10 text-primary rounded-xl flex items-center justify-center text-sm">
                                    <i class="fa-solid fa-truck-pickup"></i>
                                </div>
                                4. Book a Pickup & Delivery
                            </h2>
                            
                            <div class="flex items-center gap-3 bg-white px-4 py-2 rounded-2xl shadow-sm border border-gray-100 transition-all hover:bg-slate-50">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400" id="logisticsStatusText">Enable Valet?</span>
                                <label class="premium-switch small-blue">
                                    <input type="checkbox" name="request_pickup" value="1" id="pickupToggle">
                                    <span class="premium-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div id="pickupDetails" class="hidden card p-0 shadow-xl shadow-gray-100/50 rounded-3xl border border-gray-100 bg-white overflow-hidden animate-fade-in">
                            <div class="p-10 bg-slate-50/30">
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                                    <div class="space-y-8">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div class="space-y-2">
                                                <label class="text-[10px] font-black uppercase text-gray-500 tracking-widest ml-1">Preferred Pickup Date</label>
                                                <div class="relative">
                                                    <i class="fa-solid fa-calendar-day absolute left-4 top-1/2 -translate-y-1/2 text-primary/40"></i>
                                                    <input type="date" name="pickup_time" id="pickup_time" class="form-control pl-12 h-14 font-bold rounded-xl bg-white border-gray-100 focus:ring-4 focus:ring-primary/5 shadow-sm w-full">
                                                </div>
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-[10px] font-black uppercase text-gray-500 tracking-widest ml-1">Contact Phone</label>
                                                <div class="relative">
                                                    <i class="fa-solid fa-phone absolute left-4 top-1/2 -translate-y-1/2 text-primary/40"></i>
                                                    <input type="tel" name="pickup_phone" value="<?php echo htmlspecialchars($_SESSION['user_phone'] ?? ''); ?>" class="form-control pl-12 h-14 font-bold rounded-xl bg-white border-gray-100 focus:ring-4 focus:ring-primary/5 shadow-sm w-full">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <label class="text-[10px] font-black uppercase text-gray-500 tracking-widest ml-1">Location Details</label>
                                            <div class="relative group">
                                                <input type="text" name="pickup_address" id="pickupAddressInput" class="form-control pl-12 pr-12 h-16 text-sm font-bold rounded-xl bg-white border-gray-100 focus:ring-4 focus:ring-primary/5 shadow-md w-full" placeholder="Pick on map or enter address...">
                                                <i class="fa-solid fa-map-location-dot absolute left-4 top-1/2 -translate-y-1/2 text-primary"></i>
                                                <button type="button" id="usePickupLocationBtn" class="absolute right-3 top-1/2 -translate-y-1/2 text-primary hover:bg-primary/10 rounded-lg p-2 transition-all">
                                                    <i class="fa-solid fa-crosshairs"></i>
                                                </button>
                                            </div>
                                            <input type="hidden" name="pickup_lat" id="pickupLat">
                                            <input type="hidden" name="pickup_lng" id="pickupLng">
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div class="space-y-2">
                                                <label class="text-[10px] font-black uppercase text-gray-500 tracking-widest ml-1">Exact Location Details</label>
                                                <input type="text" name="pickup_landmark" class="form-control h-14 font-bold rounded-xl bg-white border-gray-100 w-full" placeholder="e.g. Near St. Mary's Church, Market gate">
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-[10px] font-black uppercase text-gray-500 tracking-widest ml-1">Key Placement & Parking Spot</label>
                                                <input type="text" name="pickup_parking" class="form-control h-14 font-bold rounded-xl bg-white border-gray-100 w-full" placeholder="e.g. Key with security, parked in 2nd slot">
                                            </div>
                                        </div>
                                        
                                        <div class="p-3 bg-blue-50/50 rounded-xl border border-blue-100/50 flex flex-col gap-2">
                                            <div class="flex items-center gap-2">
                                                <i class="fa-solid fa-circle-info text-blue-400 text-[10px]"></i>
                                                <p class="text-[9px] font-bold text-blue-600 uppercase tracking-tighter">Note: The vehicle will be returned to this same location unless specified otherwise.</p>
                                            </div>
                                            <div class="flex items-center gap-2 bg-blue-100/50 p-2 rounded-lg">
                                                <i class="fa-solid fa-phone-volume text-blue-500 text-xs"></i>
                                                <p class="text-[10px] font-black text-blue-700 uppercase">The driver will contact you on phone for the pickup coordination.</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Map Container -->
                                    <div class="h-80 lg:h-auto min-h-[400px] rounded-3xl overflow-hidden border-8 border-white shadow-2xl relative">
                                        <div id="pickupMap" class="w-full h-full bg-slate-50 z-0"></div>
                                        <div class="absolute inset-0 flex items-center justify-center bg-slate-100" id="mapLoader">
                                            <i class="fa-solid fa-circle-notch fa-spin text-3xl text-primary"></i>
                                        </div>
                                        <div class="absolute top-4 left-4 z-10 bg-white/90 backdrop-blur shadow-lg px-4 py-2 rounded-xl border border-gray-100 flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-600">Pick Exact Location</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    </section>

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
        .premium-switch { position: relative; display: inline-block; width: 64px; height: 36px; }
        .premium-switch input { opacity: 0; width: 0; height: 0; }
        .premium-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(255,255,255,0.2); transition: .4s; border-radius: 36px; }
        .premium-slider:before { position: absolute; content: ""; height: 28px; width: 28px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        input:checked + .premium-slider { background-color: #4ade80; }
        .small-blue input:checked + .premium-slider { background-color: #3b82f6; }
        input:checked + .premium-slider:before { transform: translateX(28px); }
        .small-blue input:checked + .premium-slider:before { transform: translateX(20px); }
        
        .small-blue.premium-switch { width: 48px; height: 28px; }
        .small-blue .premium-slider:before { height: 20px; width: 20px; }
        
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

            // 2. Streamlined Logistics Logic (Leaflet Integration)
            let valetMap, valetMarker;
            const valetToggle = document.getElementById('pickupToggle');
            const valetDetails = document.getElementById('pickupDetails');
            const statusText = document.getElementById('logisticsStatusText');
            const addrInput = document.getElementById('pickupAddressInput');
            const latInput = document.getElementById('pickupLat');
            const lngInput = document.getElementById('pickupLng');
            const mapLoader = document.getElementById('mapLoader');

            valetToggle.addEventListener('change', function() {
                if (this.checked) {
                    valetDetails.classList.remove('hidden');
                    statusText.innerText = 'Service Enabled';
                    statusText.classList.add('text-blue-500');
                    initValetMap();
                } else {
                    valetDetails.classList.add('hidden');
                    statusText.innerText = 'Enable Valet?';
                    statusText.classList.remove('text-blue-500');
                }
            });

            function initValetMap() {
                if (!valetMap) {
                    setTimeout(() => {
                        valetMap = L.map('pickupMap').setView([10.8505, 76.2711], 13);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '© OpenStreetMap'
                        }).addTo(valetMap);
                        
                        valetMap.on('click', (e) => updateValetLocation(e.latlng.lat, e.latlng.lng));
                        
                        valetMap.whenReady(() => {
                            mapLoader.classList.add('hidden');
                        });

                        if (navigator.geolocation && !latInput.value) {
                            navigator.geolocation.getCurrentPosition((pos) => {
                                updateValetLocation(pos.coords.latitude, pos.coords.longitude);
                            });
                        }
                    }, 100);
                } else {
                    setTimeout(() => valetMap.invalidateSize(), 150);
                }
            }

            window.updateValetLocation = function(lat, lng, syncInput = true) {
                if (!valetMap) return;

                if (valetMarker) valetMap.removeLayer(valetMarker);
                
                valetMarker = L.marker([lat, lng], { draggable: true }).addTo(valetMap);
                valetMarker.on('dragend', (e) => {
                    const pos = e.target.getLatLng();
                    updateValetLocation(pos.lat, pos.lng);
                });

                valetMap.setView([lat, lng], 16);
                latInput.value = lat;
                lngInput.value = lng;

                if (syncInput) {
                    addrInput.value = "Detecting address...";
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data && data.display_name) {
                                addrInput.value = data.display_name;
                            } else {
                                addrInput.value = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                            }
                        })
                        .catch(() => {
                            addrInput.value = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                        });
                }
            };

            // Search functionality
            addrInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const query = addrInput.value;
                    if (!query || query === "Detecting address...") return;

                    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data && data.length > 0) {
                                updateValetLocation(data[0].lat, data[0].lon, false);
                                latInput.value = data[0].lat;
                                lngInput.value = data[0].lon;
                            } else {
                                alert("Location not found. Please try picking on map.");
                            }
                        });
                }
            });

            // "My Location" Button
            document.getElementById('usePickupLocationBtn').addEventListener('click', () => {
                if (navigator.geolocation) {
                    const btn = document.getElementById('usePickupLocationBtn');
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
                    navigator.geolocation.getCurrentPosition((pos) => {
                        btn.innerHTML = '<i class="fa-solid fa-crosshairs"></i>';
                        updateValetLocation(pos.coords.latitude, pos.coords.longitude);
                    }, () => {
                        btn.innerHTML = '<i class="fa-solid fa-crosshairs"></i>';
                        alert("Geolocation failed.");
                    });
                }
            });
        });
    </script>
</body>
</html>
