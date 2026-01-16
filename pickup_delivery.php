<?php 
$page_title = 'Pickup & Delivery'; 
require_once 'includes/auth.php';
requireLogin();

$userId = getCurrentUserId();
$current_page = 'pickup_delivery.php';
$pd_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$request = null;
$success_msg = '';
$error_msg = '';

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request'])) {
    $pickup_id = intval($_POST['pickup_id']);
    $address = sanitizeInput($_POST['address']);
    $scheduled_time = sanitizeInput($_POST['scheduled_time']);
    $user_phone = sanitizeInput($_POST['user_phone']);
    $lat = sanitizeInput($_POST['pickup_lat'] ?? '');
    $lng = sanitizeInput($_POST['pickup_lng'] ?? '');
    $coords = ($lat && $lng) ? "$lat,$lng" : null;
    
    if (empty($address) || empty($scheduled_time) || empty($user_phone)) {
        $error_msg = "Please fill in all fields.";
    } else {
        $conn = getDbConnection();
        $conn->begin_transaction();
        try {
            // Update pickup delivery details
            if ($coords) {
                $updateQuery = "UPDATE pickup_delivery SET address = ?, scheduled_time = ?, current_location = ? WHERE id = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param('sssi', $address, $scheduled_time, $coords, $pickup_id);
            } else {
                $updateQuery = "UPDATE pickup_delivery SET address = ?, scheduled_time = ? WHERE id = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param('ssi', $address, $scheduled_time, $pickup_id);
            }

            // Update user phone number if changed
            $updateUserQuery = "UPDATE users SET phone = ? WHERE id = ?";
            $stmt_u = $conn->prepare($updateUserQuery);
            $stmt_u->bind_param('si', $user_phone, $userId);
            $stmt_u->execute();

            $conn->commit();
            $success_msg = "Logistics preferences updated successfully!";
            $pd_id = $pickup_id;
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Error updating details: " . $e->getMessage();
        }
    }
}

if ($pd_id) {
    // Fetch specific pickup/delivery details
    $query = "SELECT pd.*, b.booking_number, b.status as booking_status, 
                     v.make, v.model, v.year, v.license_plate, v.color, v.type as vehicle_type,
                     u.name as user_name, u.email as user_email, u.phone as user_phone,
                     u_m.name as mechanic_name,
                     u_d.name as driver_name, u_d.phone as driver_phone,
                     d.license_number as driver_license, u_d.profile_image as driver_image
              FROM pickup_delivery pd
              JOIN bookings b ON pd.booking_id = b.id
              JOIN vehicles v ON b.vehicle_id = v.id
              JOIN users u ON b.user_id = u.id
              LEFT JOIN mechanics m ON b.mechanic_id = m.id
              LEFT JOIN users u_m ON m.user_id = u_m.id
              LEFT JOIN users u_d ON pd.driver_user_id = u_d.id
              LEFT JOIN drivers d ON pd.driver_user_id = d.user_id
              WHERE pd.id = ? AND b.user_id = ?";
    $result = executeQuery($query, [$pd_id, $userId], 'ii');
    if ($result && $result->num_rows > 0) {
        $request = $result->fetch_assoc();
    }
} else {
    // Find most recent active request
    $query = "SELECT pd.id FROM pickup_delivery pd 
              JOIN bookings b ON pd.booking_id = b.id 
              WHERE b.user_id = ? AND pd.status IN ('pending', 'scheduled', 'in_transit') 
              ORDER BY pd.created_at DESC LIMIT 1";
    $result = executeQuery($query, [$userId], 'i');
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        header("Location: pickup_delivery.php?id=" . $row['id']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistics Manager - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="page-content">
                <?php if (!$request): ?>
                    <div class="glass-card p-20 text-center animate-fade-in shadow-xl border-none mt-10">
                        <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6 border border-gray-100">
                            <i class="fa-solid fa-truck-ramp-box text-4xl text-gray-300"></i>
                        </div>
                        <h2 class="text-3xl font-black text-gray-900 mb-2">No Active Logistics</h2>
                        <p class="text-muted mb-8 max-w-md mx-auto">You don't have any pending pickup or delivery requests. Start by booking a premium service for your vehicle.</p>
                        <a href="book_service.php" class="btn btn-primary px-8 py-3 rounded-xl font-bold uppercase tracking-wider shadow-lg shadow-blue-200 hover:scale-105 transition-all">Book New Service</a>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 animate-slide-up">
                        <div>
                            <div class="flex items-center gap-2 text-primary font-black text-xs uppercase tracking-widest mb-1">
                                <i class="fa-solid fa-location-dot"></i> Live Logistics Tracker
                            </div>
                            <h1 class="text-4xl font-black text-gray-900"><?php echo ucfirst($request['type']); ?> Manager</h1>
                            <p class="text-muted text-sm mt-1">Status: <span class="text-primary font-bold">#<?php echo $request['booking_number']; ?></span> • Logistics ID #<?php echo $request['id']; ?></p>
                        </div>
                        <div class="flex gap-3">
                             <a href="customer_dashboard.php" class="btn btn-white border border-gray-100 shadow-sm rounded-xl px-5 font-bold"><i class="fa-solid fa-arrow-left mr-2"></i> Dashboard</a>
                             <a href="track_service.php?id=<?php echo $request['booking_id']; ?>" class="btn btn-primary rounded-xl px-5 font-bold shadow-lg shadow-blue-100">Track Service</a>
                        </div>
                    </div>

                    <?php if ($success_msg): ?>
                        <div class="glass-card bg-emerald-50 border-emerald-100 text-emerald-800 p-4 rounded-xl mb-6 flex items-center gap-3 animate-fade-in">
                            <i class="fa-solid fa-circle-check text-emerald-500"></i>
                            <span class="font-bold text-sm"><?php echo $success_msg; ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_msg): ?>
                        <div class="glass-card bg-rose-50 border-rose-100 text-rose-800 p-4 rounded-xl mb-6 flex items-center gap-3 animate-fade-in">
                            <i class="fa-solid fa-circle-exclamation text-rose-500"></i>
                            <span class="font-bold text-sm"><?php echo $error_msg; ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                        
                        <!-- Left Column: Status & Driver (4 Cols) -->
                        <div class="lg:col-span-4 flex flex-col gap-8">
                            
                            <!-- Master Status Card -->
                            <div class="glass-card p-8 border-none shadow-xl bg-gradient-to-br from-white to-gray-50">
                                <h3 class="font-black text-xs uppercase tracking-widest text-muted mb-6 flex items-center gap-2">
                                    <i class="fa-solid fa-signal text-primary"></i> Current Progress
                                </h3>
                                
                                <div class="flex flex-col gap-6">
                                    <div class="flex items-center gap-4">
                                        <div class="w-14 h-14 rounded-2xl bg-blue-100 flex items-center justify-center text-primary text-2xl shadow-inner">
                                            <i class="fa-solid <?php echo $request['type'] == 'pickup' ? 'fa-truck-arrow-right' : 'fa-truck-fast'; ?>"></i>
                                        </div>
                                        <div>
                                            <div class="text-[10px] font-black uppercase text-muted tracking-tighter">Current Phase</div>
                                            <div class="text-xl font-black text-gray-900"><?php echo str_replace('_', ' ', strtoupper($request['status'])); ?></div>
                                        </div>
                                    </div>

                                    <div class="bg-gray-100 h-2 w-full rounded-full overflow-hidden">
                                        <?php 
                                            $prog = 0;
                                            if ($request['status'] == 'pending') $prog = 10;
                                            if ($request['status'] == 'scheduled') $prog = 40;
                                            if ($request['status'] == 'in_transit') $prog = 80;
                                            if ($request['status'] == 'completed') $prog = 100;
                                        ?>
                                        <div class="bg-primary h-full transition-all duration-1000" style="width: <?php echo $prog; ?>%"></div>
                                    </div>

                                    <p class="text-xs text-muted leading-relaxed italic">
                                        <?php 
                                            if ($request['status'] == 'pending') echo "Waiting for our team to confirm your logistics request.";
                                            elseif ($request['status'] == 'scheduled') echo "Logistics has been scheduled. A driver will be assigned shortly.";
                                            elseif ($request['status'] == 'in_transit') echo "Driver is currently on the move! Please keep your phone reachable.";
                                            else echo "Logistics task successfully completed.";
                                        ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Driver Info Section -->
                            <div class="glass-card p-8 border-none shadow-xl">
                                <h3 class="font-black text-xs uppercase tracking-widest text-muted mb-6">Logistics Personnel</h3>
                                
                                <?php if ($request['driver_user_id']): ?>
                                    <div class="flex items-center gap-6 mb-6">
                                        <img src="<?php echo $request['driver_image'] ? 'uploads/profiles/'.$request['driver_image'] : 'https://ui-avatars.com/api/?name='.urlencode($request['driver_name']).'&background=0D8ABC&color=fff'; ?>" 
                                             alt="Driver" class="w-20 h-20 rounded-2xl object-cover border-4 border-white shadow-md">
                                        <div>
                                            <div class="font-black text-lg text-gray-900"><?php echo htmlspecialchars($request['driver_name']); ?></div>
                                            <div class="text-[10px] font-black text-blue-600 uppercase tracking-tight bg-blue-50 px-2 py-0.5 rounded inline-block">Pro Driver</div>
                                            <div class="text-xs text-muted mt-1 font-bold">L: <?php echo htmlspecialchars($request['driver_license'] ?? 'Verified'); ?></div>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <a href="tel:<?php echo $request['driver_phone']; ?>" class="btn btn-primary py-3 rounded-xl font-black text-xs uppercase tracking-widest flex items-center gap-2">
                                            <i class="fa-solid fa-phone"></i> Call Driver
                                        </a>
                                        <button class="btn btn-white border-gray-100 py-3 rounded-xl font-black text-xs uppercase tracking-widest flex items-center gap-2">
                                            <i class="fa-solid fa-comment"></i> Chat
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-6 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                                        <div class="animate-pulse flex flex-col items-center">
                                            <div class="w-12 h-12 bg-gray-100 rounded-full mb-3 flex items-center justify-center">
                                                <i class="fa-solid fa-user-clock text-gray-300"></i>
                                            </div>
                                            <span class="text-[10px] font-black uppercase text-gray-400 tracking-tighter">Assigning Personnel...</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Vehicle Summary Mini -->
                            <div class="glass-card p-0 overflow-hidden shadow-xl border-none">
                                <div class="p-6 bg-gradient-to-r from-gray-900 to-slate-800 text-white">
                                    <div class="flex justify-between items-center mb-1">
                                        <div class="text-[9px] font-black uppercase tracking-widest opacity-60">Vehicle Under Care</div>
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    </div>
                                    <div class="text-xl font-black"><?php echo htmlspecialchars($request['make'] . ' ' . $request['model']); ?></div>
                                    <div class="text-[10px] font-mono opacity-80"><?php echo htmlspecialchars($request['license_plate']); ?> • <?php echo ucfirst($request['vehicle_type']); ?></div>
                                </div>
                                <div class="p-6 bg-white flex justify-between gap-4">
                                     <div class="text-center flex-1 border-r border-gray-50">
                                         <div class="text-[9px] font-black uppercase text-muted tracking-tight mb-1">Model Year</div>
                                         <div class="font-black text-gray-900"><?php echo $request['year']; ?></div>
                                     </div>
                                     <div class="text-center flex-1">
                                         <div class="text-[9px] font-black uppercase text-muted tracking-tight mb-1">Color Info</div>
                                         <div class="font-black text-gray-900"><?php echo ucfirst($request['color']); ?></div>
                                     </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Preferences & Map (8 Cols) -->
                        <div class="lg:col-span-8 flex flex-col gap-8">
                            
                            <!-- Detailed Preferences Form -->
                            <div class="glass-card p-10 border-none shadow-2xl bg-white relative overflow-hidden">
                                <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50/50 rounded-bl-full -z-0"></div>
                                
                                <div class="relative z-10">
                                    <div class="flex items-center gap-4 mb-10">
                                        <div class="w-14 h-14 bg-primary rounded-2xl flex items-center justify-center text-white text-2xl shadow-xl shadow-blue-200">
                                            <i class="fa-solid fa-sliders"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-2xl font-black text-gray-900">Logistics Preferences</h3>
                                            <p class="text-sm text-muted">Update your location and timing for a seamless handoff.</p>
                                        </div>
                                    </div>

                                    <form method="POST">
                                        <input type="hidden" name="update_request" value="1">
                                        <input type="hidden" name="pickup_id" value="<?php echo $request['id']; ?>">
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                                            <div class="form-group mb-0">
                                                <label class="font-black text-[10px] uppercase text-primary tracking-widest block mb-3">Your Contact Number</label>
                                                <div class="relative">
                                                     <i class="fa-solid fa-phone absolute left-4 top-1/2 -translate-y-1/2 text-primary/40 text-sm"></i>
                                                     <input type="text" name="user_phone" class="form-control pl-12 py-4 bg-blue-50/30 border-blue-100 rounded-2xl font-bold focus:bg-white transition-all" value="<?php echo htmlspecialchars($request['user_phone']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="form-group mb-0">
                                                <label class="font-black text-[10px] uppercase text-primary tracking-widest block mb-3">Preferred Time</label>
                                                <div class="relative">
                                                     <i class="fa-solid fa-calendar-day absolute left-4 top-1/2 -translate-y-1/2 text-primary/40 text-sm"></i>
                                                     <input type="datetime-local" name="scheduled_time" class="form-control pl-12 py-4 bg-blue-50/30 border-blue-100 rounded-2xl font-bold focus:bg-white transition-all" value="<?php echo date('Y-m-d\TH:i', strtotime($request['scheduled_time'] ?? $request['created_at'])); ?>" required>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group mb-10">
                                            <label class="font-black text-[10px] uppercase text-primary tracking-widest block mb-3"><?php echo ucfirst($request['type']); ?> Location / Pin</label>
                                            <div class="relative" style="position: relative;">
                                                 <i class="fa-solid fa-location-arrow absolute left-4 top-1/2 -translate-y-1/2 text-primary text-sm"></i>
                                                 <input type="text" name="address" id="addressInput" class="form-control pl-12 pr-24 py-4 bg-blue-50/30 border-blue-100 rounded-2xl font-bold focus:bg-white transition-all" value="<?php echo htmlspecialchars($request['address']); ?>" required placeholder="Search or paste location address">
                                                 <button type="button" id="searchAddressBtn" class="btn btn-primary absolute right-2 top-1/2 -translate-y-1/2 rounded-xl px-4 font-bold uppercase text-xs tracking-wider shadow-sm transition-all" style="position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%); height: 2.5rem; z-index: 10;">
                                                     Search
                                                 </button>
                                            </div>
                                            <input type="hidden" name="pickup_lat" id="latInput">
                                            <input type="hidden" name="pickup_lng" id="lngInput">
                                            <p class="text-[10px] text-muted mt-3 italic flex items-center gap-2">
                                                <i class="fa-solid fa-info-circle"></i>
                                                This address will be synchronized directly to the assigned driver's GPS system.
                                            </p>
                                        </div>

                                        <!-- UI Element: Map Visual -->
                                        <div class="mb-10 rounded-3xl overflow-hidden border-4 border-gray-50 shadow-inner relative group h-80">
                                            <div id="map" style="width: 100%; height: 100%; z-index: 1;"></div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary w-full py-5 rounded-2xl font-black uppercase tracking-widest shadow-2xl shadow-blue-200 hover:scale-[1.01] active:scale-95 transition-all text-sm">
                                            Update Preferences & Confirm Logistics
                                        </button>
                                    </form>
                                </div>
                            </div>

                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check if Leaflet failed to load
            if (typeof L === 'undefined') {
                const mapDiv = document.getElementById('map');
                if (mapDiv) {
                    mapDiv.innerHTML = `
                        <div class="flex flex-col items-center justify-center h-full bg-gray-50 text-center p-4">
                            <i class="fa-solid fa-map-location-dot text-4xl text-gray-300 mb-2"></i>
                            <p class="text-sm text-red-500 font-bold">Map API Failed to Load</p>
                            <p class="text-xs text-muted">Please check your internet connection.</p>
                        </div>
                    `;
                }
                return;
            }

            // Initialize Map
            // Default center (can be generic or previous location)
            // Parse existing coords if available
            <?php 
                $lat = 10.8505; // Default fallback
                $lng = 76.2711;
                $hasCoords = false;
                if (!empty($request['current_location'])) {
                    $parts = explode(',', $request['current_location']);
                    if (count($parts) == 2) {
                        $lat = floatval($parts[0]);
                        $lng = floatval($parts[1]);
                        $hasCoords = true;
                    }
                }
            ?>

            let map;
            let marker;
            const latInput = document.getElementById('latInput');
            const lngInput = document.getElementById('lngInput');
            const addressInput = document.getElementById('addressInput');
            const searchAddressBtn = document.getElementById('searchAddressBtn');

            try {
                map = L.map('map').setView([<?php echo $lat; ?>, <?php echo $lng; ?>], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                const updateMarker = (lat, lng) => {
                    if (marker) map.removeLayer(marker);
                    marker = L.marker([lat, lng]).addTo(map);
                    map.setView([lat, lng], 16);
                    latInput.value = lat;
                    lngInput.value = lng;
                };

                // Set initial marker if we have coords
                <?php if ($hasCoords): ?>
                    updateMarker(<?php echo $lat; ?>, <?php echo $lng; ?>);
                <?php endif; ?>

                // Map Click Event
                map.on('click', (e) => {
                    updateMarker(e.latlng.lat, e.latlng.lng);

                    // Optional: Reverse Geocoding to fill address
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${e.latlng.lat}&lon=${e.latlng.lng}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data && data.display_name) {
                                addressInput.value = data.display_name;
                            }
                        })
                        .catch(err => console.error('Geocoding error:', err));
                });
                
                // Search Functionality
                const searchAddress = () => {
                    const query = addressInput.value;
                    if(!query) return;

                    searchAddressBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
                    
                    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`)
                        .then(r => r.json())
                        .then(data => {
                            searchAddressBtn.innerHTML = 'SEARCH';
                            if(data && data.length > 0) {
                                const lat = data[0].lat;
                                const lon = data[0].lon;
                                updateMarker(lat, lon);
                            } else {
                                alert("Location not found. Please try a different address or pick on map.");
                            }
                        })
                        .catch(e => {
                            console.error(e);
                            searchAddressBtn.innerHTML = 'SEARCH';
                            alert("Error searching address");
                        });
                };

                searchAddressBtn.addEventListener('click', searchAddress);
                addressInput.addEventListener('keydown', (e) => {
                    if(e.key === 'Enter') {
                        e.preventDefault();
                        searchAddress();
                    }
                });

                // Fix map size issues if container was hidden/resized
                setTimeout(() => { map.invalidateSize(); }, 500);
            } catch (err) {
                console.error("Map Init Error:", err);
                document.getElementById('map').innerHTML = '<div class="p-4 text-center text-xs text-red-500">Map Error: '+err.message+'</div>';
            }
        });
    </script>
</body>
</html>
