<?php
/**
 * Authentication and Session Management Functions
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current logged-in user ID
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current logged-in user data
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $userId = getCurrentUserId();
    
    // Check which columns exist
    $columnsQuery = "SHOW COLUMNS FROM users";
    $columnsResult = executeQuery($columnsQuery, [], '');
    $availableColumns = [];
    
    if ($columnsResult) {
        while ($col = $columnsResult->fetch_assoc()) {
            $availableColumns[] = $col['Field'];
        }
    }
    
    // Build select query with only available columns
    $selectColumns = ['id', 'email', 'role'];
    if (in_array('name', $availableColumns)) $selectColumns[] = 'name';
    if (in_array('phone', $availableColumns)) $selectColumns[] = 'phone';
    if (in_array('profile_image', $availableColumns)) $selectColumns[] = 'profile_image';
    if (in_array('created_at', $availableColumns)) $selectColumns[] = 'created_at';
    
    $query = "SELECT " . implode(', ', $selectColumns) . " FROM users WHERE id = ?";
    $result = executeQuery($query, [$userId], 'i');
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Add name if not present (use email or session name)
        if (!isset($user['name'])) {
            $user['name'] = $_SESSION['user_name'] ?? $user['email'];
        }
        
        return $user;
    }
    
    return null;
}

/**
 * Login user
 * @param string $email
 * @param string $password
 * @return array ['success' => bool, 'message' => string, 'user' => array]
 */
function loginUser($email, $password) {
    $query = "SELECT id, name, email, password, role FROM users WHERE email = ?";
    $result = executeQuery($query, [$email], 's');
    
    if (!$result || $result->num_rows === 0) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    $user = $result->fetch_assoc();
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    // Create session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['last_activity'] = time();
    
    unset($user['password']);
    
    return [
        'success' => true,
        'message' => 'Login successful',
        'user' => $user
    ];
}

/**
 * Login user with Google
 * @param array $googleData ['email', 'name', 'google_id', 'picture']
 * @return array
 */
function loginWithGoogle($googleData) {
    $email = $googleData['email'];
    $name = $googleData['name'] ?? $email; // Use email as fallback
    $googleId = $googleData['google_id'];
    $picture = $googleData['picture'] ?? null;
    
    // First, check what columns exist in the users table
    $columnsQuery = "SHOW COLUMNS FROM users";
    $columnsResult = executeQuery($columnsQuery, [], '');
    $availableColumns = [];
    
    if ($columnsResult) {
        while ($col = $columnsResult->fetch_assoc()) {
            $availableColumns[] = $col['Field'];
        }
    }
    
    $hasName = in_array('name', $availableColumns);
    $hasGoogleId = in_array('google_id', $availableColumns);
    $hasProfileImage = in_array('profile_image', $availableColumns);
    
    // Check if user exists (try email first, then google_id if column exists)
    if ($hasGoogleId) {
        $query = "SELECT id, email, role" . ($hasName ? ", name" : "") . " FROM users WHERE email = ? OR google_id = ?";
        $result = executeQuery($query, [$email, $googleId], 'ss');
    } else {
        $query = "SELECT id, email, role" . ($hasName ? ", name" : "") . " FROM users WHERE email = ?";
        $result = executeQuery($query, [$email], 's');
    }
    
    if ($result && $result->num_rows > 0) {
        // User exists
        $user = $result->fetch_assoc();
        
        // Update Google ID if column exists and not set
        if ($hasGoogleId && empty($user['google_id'] ?? '')) {
            $updateParts = ["google_id = ?"];
            $updateParams = [$googleId];
            $updateTypes = 's';
            
            if ($hasProfileImage) {
                $updateParts[] = "profile_image = ?";
                $updateParams[] = $picture;
                $updateTypes .= 's';
            }
            
            $updateParams[] = $user['id'];
            $updateTypes .= 'i';
            
            $updateQuery = "UPDATE users SET " . implode(', ', $updateParts) . " WHERE id = ?";
            executeQuery($updateQuery, $updateParams, $updateTypes);
        }
        
        // Add name to user array if not present
        if (!isset($user['name'])) {
            $user['name'] = $name;
        }
        
    } else {
        // Create new user - build insert query based on available columns
        $insertColumns = ['email', 'role'];
        $insertValues = ['?', "'customer'"];
        $insertParams = [$email];
        $insertTypes = 's';
        
        if ($hasName) {
            $insertColumns[] = 'name';
            $insertValues[] = '?';
            $insertParams[] = $name;
            $insertTypes .= 's';
        }
        
        if ($hasGoogleId) {
            $insertColumns[] = 'google_id';
            $insertValues[] = '?';
            $insertParams[] = $googleId;
            $insertTypes .= 's';
        }
        
        if ($hasProfileImage) {
            $insertColumns[] = 'profile_image';
            $insertValues[] = '?';
            $insertParams[] = $picture;
            $insertTypes .= 's';
        }
        
        $insertQuery = "INSERT INTO users (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";
        $insertResult = executeQuery($insertQuery, $insertParams, $insertTypes);
        
        if (!$insertResult) {
            return ['success' => false, 'message' => 'Failed to create user account'];
        }
        
        $userId = getLastInsertId();
        $user = [
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'role' => 'customer'
        ];
    }
    
    // Create session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'] ?? $email;
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['last_activity'] = time();
    
    return [
        'success' => true,
        'message' => 'Login successful',
        'user' => $user
    ];
}

/**
 * Register new user
 * @param array $data ['name', 'email', 'phone', 'password']
 * @return array
 */
function registerUser($data) {
    $name = trim($data['name']);
    $email = trim($data['email']);
    $phone = trim($data['phone'] ?? '');
    $password = $data['password'];
    
    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        return ['success' => false, 'message' => 'All required fields must be filled'];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address'];
    }
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
    }
    
    // Check if email already exists
    $checkQuery = "SELECT id FROM users WHERE email = ?";
    $result = executeQuery($checkQuery, [$email], 's');
    
    if ($result && $result->num_rows > 0) {
        return ['success' => false, 'message' => 'Email already registered'];
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $insertQuery = "INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'customer')";
    $insertResult = executeQuery($insertQuery, [$name, $email, $phone, $hashedPassword], 'ssss');
    
    if (!$insertResult) {
        return ['success' => false, 'message' => 'Failed to create account. Please try again.'];
    }
    
    $userId = getLastInsertId();
    
    // Auto-login after registration
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_role'] = 'customer';
    $_SESSION['last_activity'] = time();
    
    return [
        'success' => true,
        'message' => 'Account created successfully',
        'user_id' => $userId
    ];
}

/**
 * Logout user
 */
function logoutUser() {
    session_unset();
    session_destroy();
}

/**
 * Check if user has specific role
 * @param string $role
 * @return bool
 */
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Redirect if not logged in
 * @param string $redirectTo
 */
function requireLogin($redirectTo = 'login.php') {
    if (!isLoggedIn()) {
        header("Location: $redirectTo");
        exit;
    }
}

/**
 * Redirect if not admin
 * @param string $redirectTo
 */
function requireAdmin($redirectTo = 'customer_dashboard.php') {
    if (!isLoggedIn() || !hasRole('admin')) {
        header("Location: $redirectTo");
        exit;
    }
}

/**
 * Generate password reset token
 * @param string $email
 * @return array
 */
function createPasswordResetToken($email) {
    // Check if user exists
    $query = "SELECT id FROM users WHERE email = ?";
    $result = executeQuery($query, [$email], 's');
    
    if (!$result || $result->num_rows === 0) {
        return ['success' => false, 'message' => 'Email not found. Please register first.'];
    }
    
    // Generate token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour
    
    // Insert token
    $insertQuery = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
    executeQuery($insertQuery, [$email, $token, $expiresAt], 'sss');
    
    // Generate Link
    $resetLink = APP_URL . "/reset_password.php?token=" . $token;

    // Send Real Email
    $subject = "Reset Your Password - " . APP_NAME;
    $message = "Click here to reset your password: " . $resetLink;
    $headers = "From: no-reply@autocare-connect.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Try to send email (might fail on localhost without SMTP setup)
    @mail($email, $subject, $message, $headers);
    
    return [
        'success' => true,
        'message' => 'Password reset email sent! (If on localhost, check the screen below)',
        'token' => $token,
        'reset_link' => $resetLink
    ];
}

/**
 * Verify password reset token
 * @param string $token
 * @return array
 */
function verifyPasswordResetToken($token) {
    $query = "SELECT email, expires_at, used FROM password_resets WHERE token = ?";
    $result = executeQuery($query, [$token], 's');
    
    if (!$result || $result->num_rows === 0) {
        return ['success' => false, 'message' => 'Invalid reset token'];
    }
    
    $reset = $result->fetch_assoc();
    
    if ($reset['used']) {
        return ['success' => false, 'message' => 'Reset token already used'];
    }
    
    if (strtotime($reset['expires_at']) < time()) {
        return ['success' => false, 'message' => 'Reset token has expired'];
    }
    
    return ['success' => true, 'email' => $reset['email']];
}

/**
 * Reset password
 * @param string $token
 * @param string $newPassword
 * @return array
 */
function resetPassword($token, $newPassword) {
    // Verify token
    $verification = verifyPasswordResetToken($token);
    if (!$verification['success']) {
        return $verification;
    }
    
    $email = $verification['email'];
    
    if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
        return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
    }
    
    // Hash password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $updateQuery = "UPDATE users SET password = ? WHERE email = ?";
    executeQuery($updateQuery, [$hashedPassword, $email], 'ss');
    
    // Mark token as used
    $markUsedQuery = "UPDATE password_resets SET used = TRUE WHERE token = ?";
    executeQuery($markUsedQuery, [$token], 's');
    
    return ['success' => true, 'message' => 'Password reset successfully'];
}
?>
