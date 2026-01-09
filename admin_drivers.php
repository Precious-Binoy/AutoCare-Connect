<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

// Require admin access
requireAdmin();

// Set current page for navigation
$current_page = 'admin_drivers.php';
$page_title = 'Manage Drivers';

// Handle form submissions
$successMessage = '';
$errorMessage = '';

// Add driver
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_driver') {
    $driverName = trim($_POST['driver_name']);
    $driverPhone = trim($_POST['driver_phone']);
    $vehicleNumber = trim($_POST['vehicle_number']);
    
    // Validate
    if (empty($driverName) || empty($driverPhone)) {
        $errorMessage = 'Driver name and phone are required.';
    } else {
        // Create a basic pickup_delivery entry to register the driver
        // We'll use a dummy booking_id or create without one
        $insertDriverQuery = "INSERT INTO pickup_delivery (booking_id, type, address, driver_name, driver_phone, vehicle_number, status) 
                             VALUES (0, 'pickup', 'Driver Registration', ?, ?, ?, 'pending')";
        $result = executeQuery($insertDriverQuery, [$driverName, $driverPhone, $vehicleNumber], 'sss');
        
        if ($result) {
            $successMessage = 'Driver added successfully!';
        } else {
            $errorMessage = 'Failed to add driver.';
        }
    }
}

// Delete driver - removes all entries for this driver
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_driver') {
    $driverName = $_POST['driver_name'];
    
    $deleteQuery = "DELETE FROM pickup_delivery WHERE driver_name = ?";
    $result = executeQuery($deleteQuery, [$driverName], 's');
    
    if ($result) {
        $successMessage = 'Driver deleted successfully!';
    } else {
        $errorMessage = 'Failed to delete driver.';
    }
}

// Fetch unique drivers with their statistics
$driversQuery = "SELECT 
                    driver_name,
                    driver_phone,
                    vehicle_number,
                    COUNT(*) as total_deliveries,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_deliveries,
                    MAX(updated_at) as last_activity
                 FROM pickup_delivery
                 WHERE driver_name IS NOT NULL AND driver_name != ''
                 GROUP BY driver_name, driver_phone, vehicle_number
                 ORDER BY driver_name ASC";
$driversResult = executeQuery($driversQuery, [], '');
$drivers = [];
if ($driversResult) {
    while ($row = $driversResult->fetch_assoc()) {
        $drivers[] = $row;
    }
}
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
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-2xl font-bold">Manage Drivers</h1>
                        <p class="text-muted">Add, view, and manage delivery drivers.</p>
                    </div>
                    <button class="btn btn-primary btn-icon" onclick="document.getElementById('addDriverModal').style.display='block'">
                        <i class="fa-solid fa-plus"></i> Add Driver
                    </button>
                </div>

                <?php if ($successMessage): ?>
                    <div style="background: #D1FAE5; border: 2px solid #10B981; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <strong>✓ Success:</strong> <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div style="background: #FEE2E2; border: 2px solid #EF4444; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <strong>✗ Error:</strong> <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php endif; ?>

                <!-- Drivers Table -->
                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="overflow-x: auto;">
                        <table class="w-full text-left" style="min-width: 800px;">
                            <thead style="background: #F8FAFC; border-bottom: 1px solid var(--border);">
                                <tr>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Driver Name</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Phone</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Vehicle Number</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Total Deliveries</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Completed</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Last Activity</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm">
                                <?php if (empty($drivers)): ?>
                                    <tr>
                                        <td colspan="7" class="p-4 text-center text-muted">No drivers found. Add one to get started!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($drivers as $driver): ?>
                                        <tr style="border-bottom: 1px solid var(--border);">
                                            <td class="p-4 font-bold"><?php echo htmlspecialchars($driver['driver_name']); ?></td>
                                            <td class="p-4"><?php echo htmlspecialchars($driver['driver_phone'] ?? 'N/A'); ?></td>
                                            <td class="p-4"><?php echo htmlspecialchars($driver['vehicle_number'] ?? 'N/A'); ?></td>
                                            <td class="p-4"><?php echo $driver['total_deliveries']; ?></td>
                                            <td class="p-4">
                                                <span class="badge badge-success"><?php echo $driver['completed_deliveries']; ?></span>
                                            </td>
                                            <td class="p-4">
                                                <?php 
                                                if ($driver['last_activity']) {
                                                    echo date('M d, Y', strtotime($driver['last_activity']));
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                            <td class="p-4 text-right">
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this driver and all their delivery records?');">
                                                    <input type="hidden" name="action" value="delete_driver">
                                                    <input type="hidden" name="driver_name" value="<?php echo htmlspecialchars($driver['driver_name']); ?>">
                                                    <button type="submit" class="text-danger cursor-pointer" style="background: none; border: none; cursor: pointer;">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Add Driver Modal -->
    <div id="addDriverModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 12px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">Add New Driver</h2>
                <button onclick="document.getElementById('addDriverModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_driver">
                
                <div class="form-group">
                    <label class="form-label">Driver Name *</label>
                    <input type="text" name="driver_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Phone Number *</label>
                    <input type="tel" name="driver_phone" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Vehicle Number</label>
                    <input type="text" name="vehicle_number" class="form-control" placeholder="e.g., KA-01-AB-1234">
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary w-full">Add Driver</button>
                    <button type="button" class="btn btn-outline w-full" onclick="document.getElementById('addDriverModal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Show modal on click outside
        document.getElementById('addDriverModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });

        // Show modal if needed
        const modal = document.getElementById('addDriverModal');
        modal.style.display = modal.style.display === 'none' ? 'none' : 'flex';
    </script>
</body>
</html>
