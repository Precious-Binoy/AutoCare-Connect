<?php 
$page_title = 'New Booking'; 
require_once 'includes/auth.php';
requireLogin();

$user = getCurrentUser();
$userId = getCurrentUserId();
$success_msg = '';
$error_msg = '';

// Handle Booking Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    $vehicle_id = sanitizeInput($_POST['vehicle_id'] ?? '');
    $service_type = sanitizeInput($_POST['service_type'] ?? '');
    $preferred_date = sanitizeInput($_POST['preferred_date'] ?? '');
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    if (empty($vehicle_id) || empty($service_type) || empty($preferred_date)) {
        $error_msg = 'Please complete all required fields.';
    } else {
        $booking_number = 'BK-' . strtoupper(generateRandomString(6));
        $query = "INSERT INTO bookings (booking_number, user_id, vehicle_id, service_type, preferred_date, notes) VALUES (?, ?, ?, ?, ?, ?)";
        $params = [$booking_number, $userId, $vehicle_id, $service_type, $preferred_date, $notes];
        
        if (executeQuery($query, $params, 'siisss')) {
            $success_msg = 'Booking created successfully! Reference: ' . $booking_number;
        } else {
            $error_msg = 'Error creating booking. Please try again.';
        }
    }
}

// Fetch User's Vehicles
$vehiclesQuery = "SELECT * FROM vehicles WHERE user_id = ? AND is_active = TRUE";
$vehiclesResult = executeQuery($vehiclesQuery, [$userId], 'i');
$vehicles = [];
if ($vehiclesResult) {
    while ($row = $vehiclesResult->fetch_assoc()) {
        $vehicles[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Service - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="page-content" style="max-width: 1000px;">
                <div class="flex justify-between items-center mb-6">
                    <div>
                         <div class="flex items-center gap-2 text-muted text-sm mb-1">
                            <span>Home</span> / <span>Bookings</span> / <span class="text-text-main pb-1 border-b-2 border-primary">New Booking</span>
                        </div>
                        <h1 class="text-3xl font-bold">New Service Booking</h1>
                        <p class="text-muted">Schedule vehicle maintenance for a customer.</p>
                    </div>
                </div>

                <?php if ($success_msg): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo $success_msg; ?></span>
                </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo $error_msg; ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="submit_booking" value="1">
                    
                    <!-- Customer Info -->
                    <div class="card p-6 mb-6">
                        <div class="flex justify-between mb-4">
                            <h3 class="font-bold text-lg">Customer Information</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="flex items-center gap-3">
                                <div style="width: 48px; height: 48px; background: #F1F5F9; border-radius: 50%; display: flex; align-items: center; justify-content: center;"><i class="fa-solid fa-user text-muted text-xl"></i></div>
                                 <div>
                                    <div class="text-xs text-muted mb-1">Customer Name</div>
                                    <div class="font-bold text-lg"><?php echo htmlspecialchars($user['name']); ?></div>
                                    <div class="text-xs text-muted">Member since <?php echo date('Y', strtotime($user['created_at'])); ?></div>
                                </div>
                            </div>
                            <div>
                                 <div class="text-xs text-muted mb-1">Email</div>
                                 <div class="font-bold"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                             <div>
                                 <div class="text-xs text-muted mb-1">Contact</div>
                                 <div class="font-bold"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Select Vehicle -->
                    <h3 class="font-bold text-lg mb-4">Select Vehicle</h3>
                    
                    <?php if (empty($vehicles)): ?>
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-8">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fa-solid fa-exclamation-triangle text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-700">
                                        You don't have any vehicles registered. 
                                        <a href="my_vehicles.php" class="font-medium underline text-yellow-700 hover:text-yellow-600">
                                            Register a vehicle first
                                        </a> to book a service.
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                            <?php foreach ($vehicles as $vehicle): ?>
                            <label class="card p-4 items-start relative cursor-pointer hover:border-primary transition-all has-checked:border-primary has-checked:bg-blue-50">
                                <input type="radio" name="vehicle_id" value="<?php echo $vehicle['id']; ?>" class="peer hidden" required>
                                <div class="absolute right-3 top-3 text-primary opacity-0 peer-checked:opacity-100 peer-checked:text-xl transition-opacity">
                                    <i class="fa-solid fa-circle-check"></i>
                                </div>
                                <div class="absolute right-3 top-3 text-gray-300 peer-checked:opacity-0 text-xl">
                                    <i class="fa-regular fa-circle"></i>
                                </div>
                                
                                <div class="flex gap-4">
                                     <div style="width: 48px; height: 48px; background: #F1F5F9; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                                        <i class="fa-solid fa-car text-xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold"><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></h3>
                                        <div class="text-xs text-muted"><?php echo htmlspecialchars($vehicle['year']); ?> • <?php echo htmlspecialchars(ucfirst($vehicle['type'])); ?></div>
                                        <div class="flex gap-2 mt-2">
                                            <span class="badge badge-secondary"><?php echo htmlspecialchars($vehicle['license_plate']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                            
                             <!-- Add New Button -->
                            <a href="my_vehicles.php" class="card p-4 items-center justify-center flex-col gap-2 cursor-pointer border-dashed hover:bg-gray-50 transition-colors" style="border-style: dashed; background: transparent; height: 100%;">
                                <div class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center"><i class="fa-solid fa-plus"></i></div>
                                <span class="font-medium text-muted">Add New Vehicle</span>
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Service Details -->
                    <h3 class="font-bold text-lg mb-4">Service Details</h3>
                    <div class="card p-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div class="form-group">
                                <label class="form-label">Service Type</label>
                                <select name="service_type" class="form-control" style="height: 48px;" required>
                                    <option value="">Select a service type...</option>
                                    <option value="Full Synthetic Oil Change">Full Synthetic Oil Change</option>
                                    <option value="Brake Inspection & Repair">Brake Inspection & Repair</option>
                                    <option value="Tire Rotation & Balance">Tire Rotation & Balance</option>
                                    <option value="General Diagnostic">General Diagnostic</option>
                                    <option value="State Inspection">State Inspection</option>
                                </select>
                                <div class="flex items-center gap-1 mt-1 text-xs text-muted"><i class="fa-solid fa-circle-info"></i> Standard duration: ~1.5 Hours</div>
                            </div>
                             <div class="form-group">
                                <label class="form-label">Preferred Date & Time</label>
                                <div class="search-bar">
                                     <input type="datetime-local" name="preferred_date" class="form-control" style="height: 48px;" required onclick="this.showPicker()">
                                </div>
                            </div>
                        </div>

                         <div class="form-group mb-0">
                            <label class="form-label">Service Notes / Reported Issues</label>
                            <textarea name="notes" class="form-control" rows="4" placeholder="e.g., Customer reported a strange noise when braking at high speeds. Please check brake pads and rotors."></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-6 pb-8">
                        <button type="button" onclick="history.back()" class="btn btn-white border border-gray-300" style="width: 120px;">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="width: 180px;" <?php echo empty($vehicles) ? 'disabled' : ''; ?>><i class="fa-solid fa-check mr-2"></i> Confirm Booking</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
