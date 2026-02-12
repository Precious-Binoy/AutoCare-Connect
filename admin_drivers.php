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
            (SELECT COUNT(*) FROM pickup_delivery pd WHERE pd.driver_user_id = u.id AND pd.type = 'delivery' AND pd.status = 'completed') as completed_deliveries,
            u.profile_image
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
                        <h1 class="text-2xl font-bold">Manage Drivers</h1>
                        <p class="text-muted">Monitor driver availability and logistics performance.</p>
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
                                                    <div class="avatar bg-primary text-white flex items-center justify-center rounded-lg font-bold w-10 h-10 text-sm overflow-hidden">
                                                        <?php if (!empty($driver['profile_image'])): ?>
                                                            <img src="<?php echo htmlspecialchars($driver['profile_image']); ?>" class="w-full h-full object-cover">
                                                        <?php else: ?>
                                                            <?php echo strtoupper(substr($driver['name'], 0, 1)); ?>
                                                        <?php endif; ?>
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
                                                <button onclick="viewDriverDetails(<?php echo $driver['driver_id']; ?>)" class="btn btn-sm btn-outline-primary mr-2" title="View Details">
                                                    View Details
                                                </button>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('WARNING: This will permanently remove the driver and their associated user account. Continue?');">
                                                    <input type="hidden" name="action" value="delete_driver">
                                                    <input type="hidden" name="driver_id" value="<?php echo $driver['driver_id']; ?>">
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

    <!-- Driver Details Modal -->
    <div id="driverDetailsModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: 1.5rem; max-width: 900px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 40px 100px -20px rgba(0,0,0,0.4);">
            <div class="p-8 border-b border-gray-100">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-3xl font-black text-gray-900">Driver Details</h2>
                        <p class="text-sm text-muted mt-1">Complete profile and performance overview</p>
                    </div>
                    <button onclick="closeDriverModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fa-solid fa-xmark text-2xl"></i>
                    </button>
                </div>
            </div>
            <div id="driverModalContent" class="p-8">
                <div class="flex items-center justify-center py-20">
                    <i class="fa-solid fa-circle-notch fa-spin text-primary text-4xl"></i>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Close add driver modal if clicked outside
        window.onclick = function(event) {
            const modal = document.getElementById('addDriverModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
            const detailsModal = document.getElementById('driverDetailsModal');
            if (event.target == detailsModal) {
                closeDriverModal();
            }
        }

        function viewDriverDetails(driverId) {
            const modal = document.getElementById('driverDetailsModal');
            const content = document.getElementById('driverModalContent');
            
            modal.style.display = 'flex';
            content.innerHTML = '<div class="flex items-center justify-center py-20"><i class="fa-solid fa-circle-notch fa-spin text-primary text-4xl"></i></div>';
            
            fetch(`ajax/get_driver_details.php?driver_id=${driverId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderDriverDetails(data);
                    } else {
                        content.innerHTML = `<div class="text-center py-12 text-red-500">${data.error}</div>`;
                    }
                })
                .catch(error => {
                    content.innerHTML = '<div class="text-center py-12 text-red-500">Error loading driver details</div>';
                });
        }

        function renderDriverDetails(data) {
            const driver = data.driver;
            const stats = data.stats;
            const completionRate = stats.total_jobs > 0 ? (stats.completed_jobs / stats.total_jobs * 100).toFixed(1) : 0;
            
            let html = `
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="card bg-blue-50 border-blue-100">
                        <div class="text-center">
                            <i class="fa-solid fa-tasks text-3xl text-blue-600 mb-2"></i>
                            <div class="text-3xl font-black text-gray-900">${stats.total_jobs}</div>
                            <div class="text-xs font-bold text-muted uppercase tracking-wider">Total Jobs</div>
                        </div>
                    </div>
                    <div class="card bg-green-50 border-green-100">
                        <div class="text-center">
                            <i class="fa-solid fa-check-circle text-3xl text-green-600 mb-2"></i>
                            <div class="text-3xl font-black text-gray-900">${stats.completed_jobs}</div>
                            <div class="text-xs font-bold text-muted uppercase tracking-wider">Completed</div>
                        </div>
                    </div>
                    <div class="card bg-purple-50 border-purple-100">
                        <div class="text-center">
                            <i class="fa-solid fa-indian-rupee-sign text-3xl text-purple-600 mb-2"></i>
                            <div class="text-3xl font-black text-gray-900">₹${stats.total_earnings.toFixed(2)}</div>
                            <div class="text-xs font-bold text-muted uppercase tracking-wider">Total Earnings</div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    <div class="card">
                        <h3 class="text-lg font-black mb-4 text-gray-900">Personal Information</h3>
                        <div class="space-y-3">
                            <div><span class="text-xs font-bold text-muted uppercase block">Name</span><span class="font-bold text-gray-900">${driver.name || 'N/A'}</span></div>
                            <div><span class="text-xs font-bold text-muted uppercase block">Email</span><span class="font-bold text-gray-900">${driver.email || 'N/A'}</span></div>
                            <div><span class="text-xs font-bold text-muted uppercase block">Phone</span><span class="font-bold text-gray-900">${driver.phone || 'N/A'}</span></div>
                            <div><span class="text-xs font-bold text-muted uppercase block">Date of Birth</span><span class="font-bold text-gray-900">${driver.dob || 'N/A'}</span></div>
                            <div><span class="text-xs font-bold text-muted uppercase block">Address</span><span class="font-bold text-gray-900">${driver.address || 'N/A'}</span></div>
                        </div>
                    </div>
                    <div class="card">
                        <h3 class="text-lg font-black mb-4 text-gray-900">Employment Details</h3>
                        <div class="space-y-3">
                            <div><span class="text-xs font-bold text-muted uppercase block">License Number</span><span class="font-bold text-gray-900">${driver.license_number || 'N/A'}</span></div>
                            <div><span class="text-xs font-bold text-muted uppercase block">Joined Date</span><span class="font-bold text-gray-900">${new Date(driver.joined_date).toLocaleDateString()}</span></div>
                            <div><span class="text-xs font-bold text-muted uppercase block">Completion Rate</span><span class="font-bold text-gray-900">${completionRate}%</span></div>
                            <div><span class="text-xs font-bold text-muted uppercase block">Status</span><span class="badge ${driver.is_available ? 'badge-success' : 'badge-warning'}">${driver.is_available ? 'Available' : 'On Duty'}</span></div>
                        </div>
                    </div>
                </div>

                <div class="card mb-8">
                    <h3 class="text-lg font-black mb-4 text-gray-900">Job History (Recent ${data.job_history.length})</h3>
                    ${data.job_history.length > 0 ? `
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 text-xs uppercase">
                                    <tr>
                                        <th class="p-3 text-left">Date</th>
                                        <th class="p-3 text-left">Booking</th>
                                        <th class="p-3 text-left">Type</th>
                                        <th class="p-3 text-left">Vehicle</th>
                                        <th class="p-3 text-left">Status</th>
                                        <th class="p-3 text-right">Fee</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.job_history.map(job => `
                                        <tr class="border-t border-gray-100">
                                            <td class="p-3">${new Date(job.updated_at).toLocaleDateString()}</td>
                                            <td class="p-3 font-mono text-xs">#${job.booking_number}</td>
                                            <td class="p-3"><span class="badge ${job.type === 'pickup' ? 'badge-warning' : 'badge-info'} text-xs">${job.type}</span></td>
                                            <td class="p-3">${job.make} ${job.model}</td>
                                            <td class="p-3"><span class="badge ${job.status === 'completed' ? 'badge-success' : 'badge-info'} text-xs">${job.status}</span></td>
                                            <td class="p-3 text-right font-bold">₹${parseFloat(job.fee || 0).toFixed(2)}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    ` : '<p class="text-muted text-center py-8">No job history available</p>'}
                </div>

                <div class="card">
                    <h3 class="text-lg font-black mb-4 text-gray-900">Leave Requests</h3>
                    ${data.leave_requests.length > 0 ? `
                        <div class="space-y-2">
                            ${data.leave_requests.map(leave => `
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                    <div>
                                        <span class="font-bold text-gray-900">${leave.leave_type}</span>
                                        <span class="text-xs text-muted ml-2">${new Date(leave.start_date).toLocaleDateString()} - ${new Date(leave.end_date).toLocaleDateString()}</span>
                                    </div>
                                    <span class="badge ${leave.status === 'approved' ? 'badge-success' : leave.status === 'rejected' ? 'badge-danger' : 'badge-warning'} text-xs">${leave.status}</span>
                                </div>
                            `).join('')}
                        </div>
                    ` : '<p class="text-muted text-center py-8">No leave requests found</p>'}
                </div>
            `;
            
            document.getElementById('driverModalContent').innerHTML = html;
        }

        function closeDriverModal() {
            document.getElementById('driverDetailsModal').style.display = 'none';
        }
    </script>
</body>
</html>
