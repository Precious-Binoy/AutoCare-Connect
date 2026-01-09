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
                   u.name, u.email, u.phone
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
                                            <td class="p-4 font-bold"><?php echo htmlspecialchars($mechanic['name']); ?></td>
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
    </script>
</body>
</html>
