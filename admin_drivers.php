<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

// Require admin access
requireAdmin();

// Set current page for navigation
$conn = getDbConnection();
$current_page = 'admin_drivers.php';
$page_title = 'Manage Drivers';

$successMessage = '';
$errorMessage = '';

// Handle Add Driver
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_driver') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $phone = trim($_POST['phone']);
    $licenseNumber = trim($_POST['license_number']);
    
    // Validate
    if (empty($name) || empty($email) || empty($password)) {
        $errorMessage = 'Name, email, and password are required.';
    } else {
        // Check if email exists
        $checkQuery = "SELECT id FROM users WHERE email = ?";
        $result = executeQuery($checkQuery, [$email], 's');
        
        if ($result && $result->num_rows > 0) {
            $errorMessage = 'Email already exists.';
        } else {
            // Create user account
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertUserQuery = "INSERT INTO users (name, email, phone, password, role, is_active) VALUES (?, ?, ?, ?, 'driver', TRUE)";
            $userResult = executeQuery($insertUserQuery, [$name, $email, $phone, $hashedPassword], 'ssss');
            
            if ($userResult) {
                $userId = getLastInsertId();
                
                // Create driver record
                $insertDriverQuery = "INSERT INTO drivers (user_id, license_number, is_available) VALUES (?, ?, TRUE)";
                $driverResult = executeQuery($insertDriverQuery, [$userId, $licenseNumber], 'is');
                
                if ($driverResult) {
                    $successMessage = 'Driver added successfully!';
                } else {
                    $errorMessage = 'Failed to create driver record.';
                }
            } else {
                $errorMessage = 'Failed to create user account.';
            }
        }
    }
}

// Handle Delete Driver
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_driver') {
    $driverId = intval($_POST['driver_id']);
    
    // Begin transaction to delete user and driver record
    $conn->begin_transaction();
    try {
        // Get user_id from driver record
        $stmt = $conn->prepare("SELECT user_id FROM drivers WHERE id = ?");
        $stmt->bind_param("i", $driverId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $userId = $result->fetch_assoc()['user_id'];
            
            // Delete User (Cascade will delete driver record)
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            $conn->commit();
            $successMessage = 'Driver deleted successfully!';
        } else {
            throw new Exception("Driver not found.");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $errorMessage = 'Failed to delete driver: ' . $e->getMessage();
    }
}

// Fetch drivers with stats
$query = "SELECT 
            d.id as driver_id,
            d.license_number,
            d.is_available,
            u.id as user_id,
            u.name,
            u.email,
            u.phone,
            (SELECT COUNT(*) FROM pickup_delivery pd WHERE pd.driver_user_id = u.id AND pd.type = 'delivery') as total_deliveries,
            (SELECT COUNT(*) FROM pickup_delivery pd WHERE pd.driver_user_id = u.id AND pd.type = 'delivery' AND pd.status = 'completed') as completed_deliveries
          FROM drivers d
          JOIN users u ON d.user_id = u.id
          ORDER BY u.name ASC";
$result = $conn->query($query);
$drivers = $result->fetch_all(MYSQLI_ASSOC);

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
                        <h1 class="text-3xl font-bold">Manage Drivers</h1>
                        <p class="text-muted text-sm">Monitor driver availability and logistics performance.</p>
                    </div>
                    <button class="btn btn-primary btn-icon" onclick="document.getElementById('addDriverModal').style.display='flex'">
                        <i class="fa-solid fa-plus"></i> Add Driver
                    </button>
                </div>

                <?php if ($successMessage): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl relative mb-6 animate-fade-in" role="alert">
                        <span class="block sm:inline font-bold"><i class="fa-solid fa-circle-check mr-2"></i> <?php echo htmlspecialchars($successMessage); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl relative mb-6 animate-fade-in" role="alert">
                        <span class="block sm:inline font-bold"><i class="fa-solid fa-circle-xmark mr-2"></i> <?php echo htmlspecialchars($errorMessage); ?></span>
                    </div>
                <?php endif; ?>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="overflow-x: auto;">
                        <table class="w-full text-left" style="min-width: 800px;">
                            <thead style="background: #F8FAFC; border-bottom: 1px solid var(--border);">
                                <tr>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Name</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Email</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Phone</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">License</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Performance</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Status</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm">
                                <?php if (empty($drivers)): ?>
                                    <tr>
                                        <td colspan="7" class="p-12 text-center text-muted">
                                            <i class="fa-solid fa-id-badge text-6xl mb-4 opacity-10"></i>
                                            <h3 class="font-bold text-xl text-gray-400">No Professional Drivers Found</h3>
                                            <p class="max-w-xs mx-auto mt-2">Approved driver applications from the Job Requests section will appear here.</p>
                                            <a href="admin_job_requests.php" class="btn btn-primary mt-6">View Job Requests</a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($drivers as $driver): ?>
                                        <tr style="border-bottom: 1px solid var(--border);">
                                            <td class="p-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="avatar bg-primary text-white flex items-center justify-center rounded-lg font-bold w-10 h-10 text-sm">
                                                        <?php echo strtoupper(substr($driver['name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="font-bold"><?php echo htmlspecialchars($driver['name']); ?></div>
                                                </div>
                                            </td>
                                            <td class="p-4"><?php echo htmlspecialchars($driver['email']); ?></td>
                                            <td class="p-4"><?php echo htmlspecialchars($driver['phone'] ?? 'N/A'); ?></td>
                                            <td class="p-4"><span class="text-xs font-mono bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($driver['license_number'] ?? 'N/A'); ?></span></td>
                                            <td class="p-4">
                                                <div class="text-xs font-bold"><?php echo $driver['completed_deliveries']; ?> / <?php echo $driver['total_deliveries']; ?> Tasks</div>
                                                <div class="w-24 h-1.5 bg-gray-100 rounded-full mt-1 overflow-hidden">
                                                    <?php $percent = $driver['total_deliveries'] > 0 ? ($driver['completed_deliveries'] / $driver['total_deliveries']) * 100 : 0; ?>
                                                    <div class="h-full bg-success" style="width: <?php echo $percent; ?>%"></div>
                                                </div>
                                            </td>
                                            <td class="p-4">
                                                <?php if ($driver['is_available']): ?>
                                                    <span class="badge badge-success flex items-center gap-1 w-fit"><i class="fa-solid fa-circle text-[6px] animate-pulse"></i> Available</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning w-fit">On Duty</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-4 text-right">
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('WARNING: This will permanently remove the driver and their associated user account. Continue?');">
                                                    <input type="hidden" name="action" value="delete_driver">
                                                    <input type="hidden" name="driver_id" value="<?php echo $driver['driver_id']; ?>">
                                                    <button type="submit" class="text-red-500 hover:text-red-700 cursor-pointer" style="background: none; border: none; font-size: 1.1rem;">
                                                        <i class="fa-solid fa-trash-can"></i>
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
        <div style="background: white; padding: 2.5rem; border-radius: 12px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold">Add New Driver</h2>
                <button onclick="document.getElementById('addDriverModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_driver">
                
                <div class="form-group mb-4">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="John Doe">
                </div>

                <div class="form-group mb-4">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" class="form-control" required placeholder="john@example.com">
                </div>

                <div class="form-group mb-4">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" class="form-control" placeholder="+1 234 567 890">
                </div>

                <div class="form-group mb-4">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" required placeholder="••••••••">
                </div>

                <div class="form-group mb-6">
                    <label class="form-label">License Number</label>
                    <input type="text" name="license_number" class="form-control" placeholder="ABC-12345-XYZ">
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="btn btn-primary w-full py-3">Register Driver</button>
                    <button type="button" class="btn btn-outline w-full py-3" onclick="document.getElementById('addDriverModal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Close modal if clicked outside
        window.onclick = function(event) {
            const modal = document.getElementById('addDriverModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>
