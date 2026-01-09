<?php
/**
 * Reset Password Page
 */

require_once 'includes/auth.php';

$error = '';
$success = '';
$validToken = false;
$email = '';

// Check if token is provided
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $verification = verifyPasswordResetToken($token);
    
    if ($verification['success']) {
        $validToken = true;
        $email = $verification['email'];
    } else {
        $error = $verification['message'];
    }
} else {
    $error = 'No reset token provided';
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $token = $_POST['token'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($newPassword) || empty($confirmPassword)) {
        $error = 'Please fill in all fields';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
        $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
    } else {
        $result = resetPassword($token, $newPassword);
        
        if ($result['success']) {
            $success = 'Password reset successfully! You can now login with your new password.';
            $validToken = false;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="auth-container">
    <div class="auth-sidebar" style="background-image: linear-gradient(rgba(15, 23, 42, 0.8), rgba(15, 23, 42, 0.9)), url('https://images.unsplash.com/photo-1621905251189-08b45d6a269e'); color: white;">
        <div>
            <h1 style="color: white;" class="text-3xl font-bold mb-4">Reset Your Password</h1>
            <p class="text-lg opacity-90" style="color: white;">Create a new password for your AutoCare Connect account.</p>
        </div>
    </div>

    <div class="auth-content">
        <div class="auth-box">
            <div class="flex items-center gap-2 mb-6">
                <div style="width: 32px; height: 32px; background: var(--primary); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: white;">
                    <i class="fa-solid fa-car-side"></i>
                </div>
                <span class="font-bold text-xl text-primary">AutoCare Connect</span>
            </div>

            <h2 class="text-2xl font-bold mb-2">Reset Password</h2>
            <p class="text-muted mb-6">Enter your new password below.</p>

            <?php if ($error): ?>
                <div style="background: #FEE2E2; border: 1px solid #EF4444; color: #991B1B; padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 14px;">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div style="background: #D1FAE5; border: 1px solid #10B981; color: #065F46; padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 14px;">
                    <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?>
                </div>
                <a href="login.php" class="btn btn-primary w-full">Go to Login</a>
            <?php elseif ($validToken): ?>
                <form method="POST" action="">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
                    
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <div class="search-bar">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" id="newPassword" name="new_password" class="form-control" placeholder="Enter new password" required>
                            <i class="fa-regular fa-eye" id="toggleNewPassword" style="left: auto; right: 1rem; cursor: pointer;"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <div class="search-bar">
                            <i class="fa-solid fa-rotate-right"></i>
                            <input type="password" id="confirmPassword" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
                            <i class="fa-regular fa-eye" id="toggleConfirmPassword" style="left: auto; right: 1rem; cursor: pointer;"></i>
                        </div>
                    </div>

                    <button type="submit" name="reset_password" class="btn btn-primary w-full">Reset Password</button>
                </form>
            <?php else: ?>
                <p class="text-center text-muted mb-4">The password reset link is invalid or has expired.</p>
                <a href="login.php" class="btn btn-outline w-full">Back to Login</a>
            <?php endif; ?>

            <p class="text-center text-sm mt-6 text-muted">
                Remember your password? <a href="login.php" class="text-primary font-bold">Log in</a>
            </p>
        </div>
    </div>
</div>

<script>
// Password toggle functionality
const toggleNewPassword = document.getElementById('toggleNewPassword');
const newPassword = document.getElementById('newPassword');
const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
const confirmPassword = document.getElementById('confirmPassword');

if (toggleNewPassword && newPassword) {
    toggleNewPassword.addEventListener('click', function() {
        const type = newPassword.getAttribute('type') === 'password' ? 'text' : 'password';
        newPassword.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
}

if (toggleConfirmPassword && confirmPassword) {
    toggleConfirmPassword.addEventListener('click', function() {
        const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
        confirmPassword.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
}
</script>

</body>
</html>
