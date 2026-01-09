<?php 
$page_title = 'Pickup & Delivery'; 
require_once 'includes/auth.php';
requireLogin();

$userId = getCurrentUserId();
$pd_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$request = null;
$success_msg = '';
$error_msg = '';

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request'])) {
    $pickup_id = intval($_POST['pickup_id']);
    $address = sanitizeInput($_POST['address']);
    $scheduled_time = sanitizeInput($_POST['scheduled_time']);
    
    if (empty($address) || empty($scheduled_time)) {
        $error_msg = "Please fill in all fields.";
    } else {
        $updateQuery = "UPDATE pickup_delivery SET address = ?, scheduled_time = ? WHERE id = ?";
        if (executeQuery($updateQuery, [$address, $scheduled_time, $pickup_id], 'ssi')) {
            $success_msg = "Request updated successfully!";
            // Refresh data
            $pd_id = $pickup_id;
        } else {
            $error_msg = "Error updating request.";
        }
    }
}

if ($pd_id) {
    // Fetch specific pickup/delivery details
    $query = "SELECT pd.*, b.booking_number, b.status as booking_status, 
                     v.make, v.model, v.year, v.license_plate, v.color, v.type as vehicle_type,
                     u.name as user_name, u.email as user_email, u.phone as user_phone
              FROM pickup_delivery pd
              JOIN bookings b ON pd.booking_id = b.id
              JOIN vehicles v ON b.vehicle_id = v.id
              JOIN users u ON b.user_id = u.id
              WHERE pd.id = ? AND b.user_id = ?";
    $result = executeQuery($query, [$pd_id, $userId], 'ii');
    if ($result && $result->num_rows > 0) {
        $request = $result->fetch_assoc();
    }
} else {
    // If no specific ID, find the most recent pending/scheduled request
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
    <title>Pickup & Delivery - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Leaflet for map placeholder -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="page-content">
                <?php if (!$request): ?>
                    <div class="text-center py-12">
                        <i class="fa-solid fa-truck-pickup text-6xl text-gray-300 mb-4"></i>
                        <h2 class="text-2xl font-bold text-gray-700">No Pickup/Delivery Requests</h2>
                        <p class="text-muted mb-6">You don't have any active pickup or delivery requests.</p>
                        <a href="book_service.php" class="btn btn-primary">Book New Service</a>
                    </div>
                <?php else: ?>
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <div class="flex items-center gap-2 text-muted text-sm mb-1">
                                <span>Home</span> / <span>Bookings</span> / <span class="text-text-main">Pickup & Delivery</span>
                            </div>
                            <h1 class="text-2xl font-bold">Pickup Request #<?php echo $request['id']; ?></h1>
                            <p class="text-muted">Manage logistics for Booking #<?php echo htmlspecialchars($request['booking_number']); ?></p>
                        </div>
                        <div class="flex gap-2">
                             <a href="customer_dashboard.php" class="btn btn-white">Back</a>
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

                    <div class="flex gap-6 items-start flex-col lg:flex-row">
                        
                        <!-- Left Column -->
                        <div style="width: 100%; max-width: 320px;" class="flex flex-col gap-6">
                            <!-- Customer Profile -->
                             <div class="card p-6">
                                 <div class="flex justify-between items-center mb-4">
                                     <h3 class="font-bold">Customer Profile</h3>
                                 </div>
                                 <div class="flex items-center gap-3 mb-4">
                                     <div class="avatar bg-primary text-white flex items-center justify-center rounded-full text-xl font-bold" style="width: 48px; height: 48px;">
                                        <?php echo substr($request['user_name'], 0, 1); ?>
                                    </div>
                                     <div>
                                         <div class="font-bold text-lg"><?php echo htmlspecialchars($request['user_name']); ?></div>
                                         <div class="text-xs text-muted">Customer</div>
                                     </div>
                                 </div>
                                 <div class="flex flex-col gap-3 text-sm">
                                     <div class="flex items-center gap-2 text-muted"><i class="fa-solid fa-phone w-4 text-center"></i> <?php echo htmlspecialchars($request['user_phone'] ?? 'N/A'); ?></div>
                                     <div class="flex items-center gap-2 text-muted"><i class="fa-solid fa-envelope w-4 text-center"></i> <?php echo htmlspecialchars($request['user_email']); ?></div>
                                 </div>
                             </div>

                             <!-- Vehicle Details -->
                             <div class="card p-6">
                                 <div class="flex justify-between items-center mb-4">
                                     <h3 class="font-bold">Vehicle Details</h3>
                                     <span class="badge badge-success">ACTIVE</span>
                                 </div>
                                 <!-- Dynamic Unsplash Image based on type -->
                                 <img src="https://images.unsplash.com/photo-1621007947382-bb3c3968e3bb?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" alt="Car" style="border-radius: 8px; height: 160px; object-fit: cover; width: 100%; margin-bottom: 1rem;">
                                 <div class="font-bold text-lg"><?php echo htmlspecialchars($request['make'] . ' ' . $request['model']); ?></div>
                                 <div class="text-xs text-muted font-bold uppercase mb-4"><?php echo htmlspecialchars($request['year']); ?> <?php echo htmlspecialchars($request['color']); ?></div>

                                 <div class="grid grid-cols-2 gap-4 bg-gray-50 p-4 rounded-lg">
                                     <div>
                                         <div class="text-[10px] font-bold text-muted uppercase">Plate No.</div>
                                         <div class="font-bold text-sm"><?php echo htmlspecialchars($request['license_plate']); ?></div>
                                     </div>
                                     <div>
                                         <div class="text-[10px] font-bold text-muted uppercase">Type</div>
                                         <div class="font-bold text-sm"><?php echo htmlspecialchars(ucfirst($request['vehicle_type'])); ?></div>
                                     </div>
                                 </div>
                             </div>

                             <!-- Tracking Status -->
                             <div class="card p-6">
                                 <h3 class="font-bold mb-4">Tracking Status</h3>
                                 <div class="flex flex-col gap-6 relative">
                                      <div style="position: absolute; left: 11px; top: 10px; bottom: 10px; width: 2px; background: #E2E8F0; z-index: 0;"></div>

                                      <!-- Step 1 -->
                                      <div class="flex gap-4 relative z-10">
                                          <div style="width: 24px; height: 24px; background: var(--success); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 10px;">
                                              <i class="fa-solid fa-check"></i>
                                          </div>
                                          <div>
                                              <div class="font-bold text-sm">Request Received</div>
                                              <div class="text-xs text-muted"><?php echo date('M d, g:i A', strtotime($request['created_at'])); ?></div>
                                          </div>
                                      </div>
                                       <!-- Step 2 -->
                                      <div class="flex gap-4 relative z-10">
                                          <div style="width: 24px; height: 24px; background: <?php echo ($request['driver_name']) ? 'var(--primary)' : '#CBD5E1'; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 10px;">
                                              <i class="fa-solid fa-truck"></i>
                                          </div>
                                          <div>
                                              <div class="font-bold text-sm">Driver Assigned</div>
                                              <div class="text-xs text-muted"><?php echo htmlspecialchars($request['driver_name'] ?? 'Pending Assignment'); ?></div>
                                          </div>
                                      </div>
                                       <!-- Step 3 -->
                                      <div class="flex gap-4 relative z-10">
                                          <div style="width: 24px; height: 24px; background: <?php echo ($request['status'] == 'completed') ? 'var(--success)' : '#CBD5E1'; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 10px;">
                                              <i class="fa-solid fa-warehouse"></i>
                                          </div>
                                          <div>
                                              <div class="font-bold text-sm text-muted">Completed</div>
                                              <div class="text-xs text-muted"><?php echo htmlspecialchars(ucfirst($request['status'])); ?></div>
                                          </div>
                                      </div>
                                 </div>
                             </div>
                        </div>

                        <!-- Right Column: Request Details -->
                        <div style="flex: 1;" class="flex flex-col gap-6">
                            
                             <div class="card p-6">
                                 <div class="flex justify-between items-center mb-4">
                                     <div class="flex items-center gap-2">
                                         <div style="width: 32px; height: 32px; background: #EFF6FF; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--primary);"><i class="fa-solid fa-location-dot"></i></div>
                                         <h3 class="font-bold text-lg"><?php echo ucfirst($request['type']); ?> Request Details</h3>
                                     </div>
                                 </div>

                                 <!-- Map Section -->
                                 <div class="mb-6">
                                    <label class="font-bold text-xs text-muted uppercase mb-2 block">Location Map</label>
                                    <div style="height: 200px; border-radius: 12px; overflow: hidden; position: relative;">
                                        <img src="https://images.unsplash.com/photo-1502920514313-52581002a659?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Map Placeholder" style="width: 100%; height: 100%; object-fit: cover;">
                                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 0.5rem 1rem; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); font-weight: bold;">
                                            <i class="fa-solid fa-map-pin text-danger"></i> <?php echo htmlspecialchars($request['address']); ?>
                                        </div>
                                    </div>
                                 </div>

                                 <form method="POST">
                                     <input type="hidden" name="update_request" value="1">
                                     <input type="hidden" name="pickup_id" value="<?php echo $request['id']; ?>">
                                     
                                     <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                         <div class="form-group">
                                             <label class="form-label text-xs uppercase">Customer Name</label>
                                             <input type="text" class="form-control" value="<?php echo htmlspecialchars($request['user_name']); ?>" readonly>
                                         </div>
                                         <div class="form-group">
                                             <label class="form-label text-xs uppercase">Contact Number</label>
                                             <input type="text" class="form-control" value="<?php echo htmlspecialchars($request['user_phone']); ?>" readonly>
                                         </div>
                                     </div>

                                     <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                         <div class="form-group">
                                             <label class="form-label text-xs uppercase">Vehicle</label>
                                             <input type="text" class="form-control" value="<?php echo htmlspecialchars($request['make'] . ' ' . $request['model']); ?>" readonly>
                                         </div>
                                         <div class="form-group">
                                             <label class="form-label text-xs uppercase">License Plate</label>
                                             <input type="text" class="form-control" value="<?php echo htmlspecialchars($request['license_plate']); ?>" readonly>
                                         </div>
                                     </div>

                                     <div class="form-group mb-4">
                                         <label class="form-label text-xs uppercase">Scheduled Date & Time</label>
                                         <input type="datetime-local" name="scheduled_time" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($request['scheduled_time'] ?? $request['created_at'])); ?>" required onclick="this.showPicker()">
                                     </div>

                                     <div class="form-group mb-4">
                                         <label class="form-label text-xs uppercase">Address</label>
                                         <div class="search-bar">
                                            <i class="fa-solid fa-location-arrow"></i>
                                            <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($request['address']); ?>" required>
                                         </div>
                                     </div>
                                     
                                     <button type="submit" class="btn btn-primary w-full">Update Request</button>
                                 </form>
                             </div>

                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
