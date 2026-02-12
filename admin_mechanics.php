<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

// Require admin access
requireAdmin();

// Set current page for navigation
$current_page = 'admin_mechanics.php';
$page_title = 'Manage Mechanics';

// Handle form submissions
$successMessage = '';
$errorMessage = '';

// Add mechanic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_mechanic') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $specialization = trim($_POST['specialization']);
    $yearsExperience = intval($_POST['years_experience']);
    
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
            $insertUserQuery = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'mechanic')";
            $userResult = executeQuery($insertUserQuery, [$name, $email, $hashedPassword], 'sss');
            
            if ($userResult) {
                $userId = getLastInsertId();
                
                // Create mechanic record
                $insertMechanicQuery = "INSERT INTO mechanics (user_id, specialization, years_experience, is_available) VALUES (?, ?, ?, TRUE)";
                $mechanicResult = executeQuery($insertMechanicQuery, [$userId, $specialization, $yearsExperience], 'isi');
                
                if ($mechanicResult) {
                    $successMessage = 'Mechanic added successfully!';
                } else {
                    $errorMessage = 'Failed to create mechanic record.';
                }
            } else {
                $errorMessage = 'Failed to create user account.';
            }
        }
    }
}

// Delete mechanic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_mechanic') {
    $mechanicId = intval($_POST['mechanic_id']);
    
    // Get user_id first
    $getUserQuery = "SELECT user_id FROM mechanics WHERE id = ?";
    $result = executeQuery($getUserQuery, [$mechanicId], 'i');
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $userId = $user['user_id'];
        
        // Delete mechanic (will cascade delete user due to foreign key)
        $deleteMechanicQuery = "DELETE FROM mechanics WHERE id = ?";
        $deleteResult = executeQuery($deleteMechanicQuery, [$mechanicId], 'i');
        
        if ($deleteResult) {
            // Delete user account
            $deleteUserQuery = "DELETE FROM users WHERE id = ?";
            executeQuery($deleteUserQuery, [$userId], 'i');
            $successMessage = 'Mechanic deleted successfully!';
        } else {
            $errorMessage = 'Failed to delete mechanic.';
        }
    }
}

// Fetch all mechanics
$mechanicsQuery = "SELECT m.id, m.specialization, m.certification, m.years_experience, m.is_available,
                   u.name, u.email, u.phone, u.profile_image
                   FROM mechanics m
                   INNER JOIN users u ON m.user_id = u.id
                   ORDER BY u.name ASC";
$mechanicsResult = executeQuery($mechanicsQuery, [], '');
$mechanics = [];
if ($mechanicsResult) {
    while ($row = $mechanicsResult->fetch_assoc()) {
        $mechanics[] = $row;
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
                        <h1 class="text-2xl font-bold">Manage Mechanics</h1>
                        <p class="text-muted">Add, view, and manage mechanic accounts.</p>
                    </div>
                    <button class="btn btn-primary btn-icon" onclick="document.getElementById('addMechanicModal').style.display='block'">
                        <i class="fa-solid fa-plus"></i> Add Mechanic
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

                <!-- Mechanics Table -->
                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="overflow-x: auto;">
                        <table class="w-full text-left" style="min-width: 800px;">
                            <thead style="background: #F8FAFC; border-bottom: 1px solid var(--border);">
                                <tr>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Name</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Email</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Phone</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Specialization</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Experience</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase">Status</th>
                                    <th class="p-4 text-xs font-semibold text-muted uppercase text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm">
                                <?php if (empty($mechanics)): ?>
                                    <tr>
                                        <td colspan="7" class="p-4 text-center text-muted">No mechanics found. Add one to get started!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($mechanics as $mechanic): ?>
                                        <tr style="border-bottom: 1px solid var(--border);">
                                            <td class="p-4 font-bold">
                                                <div class="flex items-center gap-3">
                                                    <div class="avatar bg-primary text-white flex items-center justify-center rounded-lg font-bold w-10 h-10 text-sm overflow-hidden">
                                                        <?php if (!empty($mechanic['profile_image'])): ?>
                                                            <img src="<?php echo htmlspecialchars($mechanic['profile_image']); ?>" class="w-full h-full object-cover">
                                                        <?php else: ?>
                                                            <?php echo strtoupper(substr($mechanic['name'], 0, 1)); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div><?php echo htmlspecialchars($mechanic['name']); ?></div>
                                                </div>
                                            </td>
                                            <td class="p-4"><?php echo htmlspecialchars($mechanic['email']); ?></td>
                                            <td class="p-4"><?php echo htmlspecialchars($mechanic['phone'] ?? 'N/A'); ?></td>
                                            <td class="p-4"><?php echo htmlspecialchars($mechanic['specialization'] ?? 'General'); ?></td>
                                            <td class="p-4"><?php echo $mechanic['years_experience'] ?? 0; ?> years</td>
                                            <td class="p-4">
                                                <span class="badge <?php echo $mechanic['is_available'] ? 'badge-success' : 'badge-warning'; ?>">
                                                    <?php echo $mechanic['is_available'] ? 'Available' : 'Unavailable'; ?>
                                                </span>
                                            </td>
                                            <td class="p-4 text-right">
                                                <button onclick="viewMechanicDetails(<?php echo $mechanic['id']; ?>)" class="btn btn-sm btn-outline-primary mr-2" title="View Details">
                                                    View Details
                                                </button>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this mechanic?');">
                                                    <input type="hidden" name="action" value="delete_mechanic">
                                                    <input type="hidden" name="mechanic_id" value="<?php echo $mechanic['id']; ?>">
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

    <!-- Add Mechanic Modal -->
    <div id="addMechanicModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 12px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">Add New Mechanic</h2>
                <button onclick="document.getElementById('addMechanicModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_mechanic">
                
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Specialization</label>
                    <input type="text" name="specialization" class="form-control" placeholder="e.g., Engine Repair, Electrical">
                </div>

                <div class="form-group">
                    <label class="form-label">Years of Experience</label>
                    <input type="number" name="years_experience" class="form-control" min="0" value="0">
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary w-full">Add Mechanic</button>
                    <button type="button" class="btn btn-outline w-full" onclick="document.getElementById('addMechanicModal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Mechanic Details Modal -->
    <div id="mechanicDetailsModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: 1.5rem; max-width: 900px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 40px 100px -20px rgba(0,0,0,0.4);">
            <div class="p-8 border-b border-gray-100">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-3xl font-black text-gray-900">Mechanic Details</h2>
                        <p class="text-sm text-muted mt-1">Complete profile and performance overview</p>
                    </div>
                    <button onclick="closeMechanicModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fa-solid fa-xmark text-2xl"></i>
                    </button>
                </div>
            </div>
            <div id="mechanicModalContent" class="p-8">
                <div class="flex items-center justify-center py-20">
                    <i class="fa-solid fa-circle-notch fa-spin text-primary text-4xl"></i>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Show modal on click
        document.getElementById('addMechanicModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });

        // Show modal if needed
        const modal = document.getElementById('addMechanicModal');
        modal.style.display = modal.style.display === 'none' ? 'none' : 'flex';

        function viewMechanicDetails(mechanicId) {
            const modal = document.getElementById('mechanicDetailsModal');
            const content = document.getElementById('mechanicModalContent');
            
            modal.style.display = 'flex';
            content.innerHTML = '<div class="flex items-center justify-center py-20"><i class="fa-solid fa-circle-notch fa-spin text-primary text-4xl"></i></div>';
            
            fetch(`ajax/get_mechanic_details.php?mechanic_id=${mechanicId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderMechanicDetails(data);
                    } else {
                        content.innerHTML = `<div class="text-center py-12 text-red-500">${data.error}</div>`;
                    }
                })
                .catch(error => {
                    content.innerHTML = '<div class="text-center py-12 text-red-500">Error loading mechanic details</div>';
                });
        }

        function renderMechanicDetails(data) {
            const mechanic = data.mechanic;
            const stats = data.stats;
            const completionRate = stats.total_jobs > 0 ? (stats.completed_jobs / stats.total_jobs * 100).toFixed(1) : 0;
            
            let html = `
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="card bg-blue-50 border-blue-100">
                        <div class="text-center">
                            <i class="fa-solid fa-wrench text-3xl text-blue-600 mb-2"></i>
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
                            <div><span class="text-xs font-bold text-muted uppercase block">Name</span><span class="font-bold text-gray-900">${mechanic.name || 'N/A'}</span></div>
                            <div><span class="text-xs font-bold text-muted uppercase block">Email</span><span class="font-bold text-gray-900">${mechanic.email || 'N/A'}</span></div>
                            <div><span class="text-xs font-bold text-muted uppercase block">Phone</span><span class="font-bold text-gray-900">${mechanic.phone || 'N/A'}</span></div>
                            <div><span class="text-xs font-bold text-muted uppercase block">Date of Birth</span><span class="font-bold text-gray-900">${mechanic.dob || 'N/A'}</span></div>
                            <div><span class="text-xs font-bold text-muted uppercase block">Address</span><span class="font-bold text-gray-900">${mechanic.address || 'N/A'}</span></div>
                        </div>
                    </div>
                    <div class="card">
                        <h3 class="text-lg font-black mb-4 text-gray-900">Employment Details</h3>
                        <div class="space-y-3">
                            <div><span class="text-xs font-bold text-muted uppercase block">Specialization</span><span class="font-bold text-gray-900">${mechanic.specialization || 'General'}</span></div>
                            <div><span class="text-xs font-bold text-muted uppercase block">Years of Experience</span><span class="font-bold text-gray-900">${mechanic.years_experience || 0} years</span></div>
                            <div><span class="text-xs font-bold text-muted uppercase block">Joined Date</span><span class="font-bold text-gray-900">${new Date(mechanic.joined_date).toLocaleDateString()}</span></div>
                            <div><span class="text-xs font-bold text-muted uppercase block">Completion Rate</span><span class="font-bold text-gray-900">${completionRate}%</span></div>
                            <div><span class="text-xs font-bold text-muted uppercase block">Status</span><span class="badge ${mechanic.is_available ? 'badge-success' : 'badge-warning'}">${mechanic.is_available ? 'Available' : 'Busy'}</span></div>
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
                                        <th class="p-3 text-left">Service Type</th>
                                        <th class="p-3 text-left">Vehicle</th>
                                        <th class="p-3 text-left">Status</th>
                                        <th class="p-3 text-right">Fee</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.job_history.map(job => `
                                        <tr class="border-t border-gray-100">
                                            <td class="p-3">${new Date(job.created_at).toLocaleDateString()}</td>
                                            <td class="p-3 font-mono text-xs">#${job.booking_number}</td>
                                            <td class="p-3">${job.service_type}</td>
                                            <td class="p-3">${job.make} ${job.model} (${job.license_plate})</td>
                                            <td class="p-3"><span class="badge ${job.status === 'completed' || job.status === 'delivered' ? 'badge-success' : 'badge-info'} text-xs">${job.status}</span></td>
                                            <td class="p-3 text-right font-bold">₹${parseFloat(job.mechanic_fee || 0).toFixed(2)}</td>
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
            
            document.getElementById('mechanicModalContent').innerHTML = html;
        }

        function closeMechanicModal() {
            document.getElementById('mechanicDetailsModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const detailsModal = document.getElementById('mechanicDetailsModal');
            if (event.target == detailsModal) {
                closeMechanicModal();
            }
        }
    </script>
</body>
</html>
