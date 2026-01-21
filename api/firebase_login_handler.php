<?php
// api/firebase_login_handler.php
// Handles Firebase token verification and user synchronization (Firebase -> MySQL)

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$idTokenString = $input['token'] ?? null;
$inputName = $input['name'] ?? null;
$inputPhone = $input['phone'] ?? null;

if (!$idTokenString) {
    echo json_encode(['success' => false, 'message' => 'No token provided']);
    exit;
}

try {
    // Initialize Firebase Auth
    // Assuming service account key is in config/firebase-key.json
    $factory = (new Factory)->withServiceAccount(__DIR__ . '/../config/firebase-key.json');
    $auth = $factory->createAuth();

    // Verify ID Token
    // This checks strictly with Firebase heavily relying on the result.
    // "check the firbase database in every time"
    $verifiedIdToken = $auth->verifyIdToken($idTokenString);
    $uid = $verifiedIdToken->claims()->get('sub');
    $email = $verifiedIdToken->claims()->get('email');
    
    // Prioritize passed Name (from registration form) over token claim (which might be delayed) or email prefix
    $name = $inputName ?? $verifiedIdToken->claims()->get('name') ?? explode('@', $email)[0];
    $picture = $verifiedIdToken->claims()->get('picture');

    // Token is valid. Now Sync with MySQL.
    // Check if user exists by email or google_id (using UID as google_id here for consistency, 
    // though UID is technically Firebase UID, it maps perfectly for this usage).
    
    // We reuse logic similar to loginWithGoogle but stricter and more direct.
    
    // Check columns existence for safety
    $columnsQuery = "SHOW COLUMNS FROM users";
    $columnsResult = executeQuery($columnsQuery, [], '');
    $availableColumns = [];
    if ($columnsResult) {
        while ($col = $columnsResult->fetch_assoc()) {
            $availableColumns[] = $col['Field'];
        }
    }
    
    $hasGoogleId = in_array('google_id', $availableColumns);
    $hasProfileImage = in_array('profile_image', $availableColumns);
    $hasName = in_array('name', $availableColumns);
    $hasPhone = in_array('phone', $availableColumns);

    // Find User
    if ($hasGoogleId) {
        $query = "SELECT * FROM users WHERE email = ? OR google_id = ?";
        $result = executeQuery($query, [$email, $uid], 'ss');
    } else {
        $query = "SELECT * FROM users WHERE email = ?";
        $result = executeQuery($query, [$email], 's');
    }

    $user = null;
    if ($result && $result->num_rows > 0) {
        // User Found - Update Details
        $user = $result->fetch_assoc();
        $userId = $user['id'];
        
        // Update fields if they changed (using UID as google_id)
        $updateParts = [];
        $updateParams = [];
        $updateTypes = "";
        
        if ($hasGoogleId && $user['google_id'] !== $uid) {
            $updateParts[] = "google_id = ?";
            $updateParams[] = $uid;
            $updateTypes .= "s";
        }
        
        if ($hasProfileImage && $picture && $user['profile_image'] !== $picture) {
             $updateParts[] = "profile_image = ?";
             $updateParams[] = $picture;
             $updateTypes .= "s";
        }

        // Update name if provided and significantly better (e.g. not empty and different)
        if ($hasName && $name && $user['name'] !== $name) {
             $updateParts[] = "name = ?";
             $updateParams[] = $name;
             $updateTypes .= "s";
             $user['name'] = $name; // Sync for session
        }

        // Update phone if provided
        if ($hasPhone && $inputPhone && (empty($user['phone']) || $user['phone'] !== $inputPhone)) {
            $updateParts[] = "phone = ?";
            $updateParams[] = $inputPhone;
            $updateTypes .= "s";
            $user['phone'] = $inputPhone;
        }
        
        if (!empty($updateParts)) {
            $updateParams[] = $userId;
            $updateTypes .= "i";
            $updateQuery = "UPDATE users SET " . implode(', ', $updateParts) . " WHERE id = ?";
            executeQuery($updateQuery, $updateParams, $updateTypes);
        }
        
    } else {
        // User Not Found - Create New
        // Note: Password is required by schema probably, but irrelevant here. We use a random hash.
        $dummyPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        
        $insertColumns = ['email', 'password', 'role'];
        $insertValues = ['?', '?', "'customer'"];
        $insertParams = [$email, $dummyPassword];
        $insertTypes = 'ss';
        
        if ($hasName) {
            $insertColumns[] = 'name';
            $insertValues[] = '?';
            $insertParams[] = $name;
            $insertTypes .= 's';
        }
        
        if ($hasPhone && $inputPhone) {
            $insertColumns[] = 'phone';
            $insertValues[] = '?';
            $insertParams[] = $inputPhone;
            $insertTypes .= 's';
        }

        if ($hasGoogleId) {
            $insertColumns[] = 'google_id';
            $insertValues[] = '?';
            $insertParams[] = $uid;
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
        
        if ($insertResult) {
            $userId = getLastInsertId();
            // Fetch the new user to be sure
            $user = [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'role' => 'customer',
                'phone' => $inputPhone ?? null
            ];
        } else {
             echo json_encode(['success' => false, 'message' => 'Database Sync Failed: Could not create user']);
             exit;
        }
    }

    // Set Session (The Login Step)
    $_SESSION['user_id'] = $user['id'] ?? $userId;
    $_SESSION['user_name'] = $user['name'] ?? $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_phone'] = $user['phone'] ?? $inputPhone ?? null;
    $_SESSION['user_role'] = $user['role'] ?? 'customer';
    $_SESSION['firebase_uid'] = $uid; // Store Firebase UID in session for reference
    $_SESSION['last_activity'] = time();

    // Determine redirect URL
    $redirect = 'customer_dashboard.php';
    if ($_SESSION['user_role'] === 'admin') {
        $redirect = 'admin_dashboard.php';
    } elseif ($_SESSION['user_role'] === 'driver') {
        $redirect = 'driver_dashboard.php';
    } elseif ($_SESSION['user_role'] === 'mechanic') {
        $redirect = 'mechanic_dashboard.php';
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Login Successful',
        'redirect' => $redirect
    ]);

} catch (FailedToVerifyToken $e) {
    echo json_encode(['success' => false, 'message' => 'Token Verification Failed: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}
?>
