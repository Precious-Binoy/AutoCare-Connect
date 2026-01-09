<?php
/**
 * Admin User Setup Script
 * One-time script to create/update the admin user
 * 
 * IMPORTANT: Delete this file after running it successfully
 */

require_once 'config/db.php';
require_once 'includes/functions.php';

// Admin credentials
$adminEmail = 'preciousbinoy3@gmail.com';
$adminPassword = 'precious123';
$adminName = 'Precious Binoy';

// Safety check - prevent accidental re-runs
$confirmParam = $_GET['confirm'] ?? '';
if ($confirmParam !== 'yes') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Setup - AutoCare Connect</title>
        <link rel="stylesheet" href="assets/css/style.css">
        <style>
            .setup-container {
                max-width: 600px;
                margin: 100px auto;
                padding: 2rem;
                background: white;
                border-radius: 12px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            .warning-box {
                background: #FEF3C7;
                border: 2px solid #F59E0B;
                padding: 1rem;
                border-radius: 8px;
                margin: 1rem 0;
            }
            .success-box {
                background: #D1FAE5;
                border: 2px solid #10B981;
                padding: 1rem;
                border-radius: 8px;
                margin: 1rem 0;
            }
            .error-box {
                background: #FEE2E2;
                border: 2px solid #EF4444;
                padding: 1rem;
                border-radius: 8px;
                margin: 1rem 0;
            }
        </style>
    </head>
    <body>
        <div class="setup-container">
            <h1>Admin User Setup</h1>
            <p>This script will create or update the admin user account.</p>
            
            <div class="warning-box">
                <strong>⚠️ Warning:</strong> This will create/update an admin account with the following credentials:
                <ul>
                    <li><strong>Email:</strong> <?php echo htmlspecialchars($adminEmail); ?></li>
                    <li><strong>Name:</strong> <?php echo htmlspecialchars($adminName); ?></li>
                    <li><strong>Role:</strong> admin</li>
                </ul>
            </div>
            
            <p><strong>Important:</strong> Delete this file after running it successfully for security reasons.</p>
            
            <a href="?confirm=yes" class="btn btn-primary" style="display: inline-block; margin-top: 1rem;">
                Proceed with Setup
            </a>
            <a href="index.php" class="btn btn-outline" style="display: inline-block; margin-top: 1rem;">
                Cancel
            </a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Proceed with setup
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Setup - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .setup-container {
            max-width: 600px;
            margin: 100px auto;
            padding: 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .success-box {
            background: #D1FAE5;
            border: 2px solid #10B981;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .error-box {
            background: #FEE2E2;
            border: 2px solid #EF4444;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .info-box {
            background: #DBEAFE;
            border: 2px solid #3B82F6;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <h1>Admin Setup Results</h1>
        
        <?php
        try {
            // Hash the password
            $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
            
            // Check if admin user already exists
            $checkQuery = "SELECT id, role FROM users WHERE email = ?";
            $result = executeQuery($checkQuery, [$adminEmail], 's');
            
            if ($result && $result->num_rows > 0) {
                // User exists, update to admin role and password
                $user = $result->fetch_assoc();
                $userId = $user['id'];
                
                $updateQuery = "UPDATE users SET name = ?, password = ?, role = 'admin' WHERE id = ?";
                executeQuery($updateQuery, [$adminName, $hashedPassword, $userId], 'ssi');
                
                echo '<div class="success-box">';
                echo '<strong>✓ Success!</strong><br>';
                echo 'Existing user account has been updated to admin role.';
                echo '</div>';
                
                echo '<div class="info-box">';
                echo '<strong>Updated Account Details:</strong><br>';
                echo 'Email: ' . htmlspecialchars($adminEmail) . '<br>';
                echo 'Name: ' . htmlspecialchars($adminName) . '<br>';
                echo 'Role: admin<br>';
                echo 'Password: (securely hashed)';
                echo '</div>';
                
            } else {
                // User doesn't exist, create new admin user
                $insertQuery = "INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, 'admin', '')";
                $insertResult = executeQuery($insertQuery, [$adminName, $adminEmail, $hashedPassword], 'sss');
                
                if ($insertResult) {
                    echo '<div class="success-box">';
                    echo '<strong>✓ Success!</strong><br>';
                    echo 'Admin user account has been created successfully.';
                    echo '</div>';
                    
                    echo '<div class="info-box">';
                    echo '<strong>New Account Details:</strong><br>';
                    echo 'Email: ' . htmlspecialchars($adminEmail) . '<br>';
                    echo 'Name: ' . htmlspecialchars($adminName) . '<br>';
                    echo 'Role: admin<br>';
                    echo 'Password: (securely hashed)';
                    echo '</div>';
                } else {
                    throw new Exception('Failed to create admin user account.');
                }
            }
            
            echo '<div class="warning-box" style="background: #FEF3C7; border: 2px solid #F59E0B; padding: 1rem; border-radius: 8px; margin: 1rem 0;">';
            echo '<strong>⚠️ Important Security Notice:</strong><br>';
            echo 'Please delete this file (setup_admin.php) immediately for security reasons.';
            echo '</div>';
            
            echo '<a href="login.php" class="btn btn-primary" style="display: inline-block; margin-top: 1rem;">Go to Login</a>';
            
        } catch (Exception $e) {
            echo '<div class="error-box">';
            echo '<strong>✗ Error:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';
            
            echo '<a href="?confirm=yes" class="btn btn-primary" style="display: inline-block; margin-top: 1rem;">Try Again</a>';
        }
        ?>
    </div>
</body>
</html>
