<?php
/**
 * Google OAuth Login API - Fixed Version
 */

// Suppress all output before JSON
ob_start();

// Set content type first
header('Content-Type: application/json; charset=utf-8');

// Don't display errors to output
ini_set('display_errors', 0);
error_reporting(0);

try {
    // Include required files
    if (!file_exists(__DIR__ . '/../includes/auth.php')) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Auth file not found. Server configuration error.']);
        exit;
    }
    
    require_once __DIR__ . '/../includes/auth.php';
    
    // Clear any output buffer
    ob_clean();
    
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }
    
    // Get POST data
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    
    if (!$data || !isset($data['credential'])) {
        echo json_encode(['success' => false, 'message' => 'No credential provided']);
        exit;
    }
    
    $credential = $data['credential'];
    
    // Decode JWT token
    $parts = explode('.', $credential);
    
    if (count($parts) !== 3) {
        echo json_encode(['success' => false, 'message' => 'Invalid credential format']);
        exit;
    }
    
    // Decode payload
    $payloadEncoded = str_replace(['-', '_'], ['+', '/'], $parts[1]);
    $payload = json_decode(base64_decode($payloadEncoded), true);
    
    if (!$payload || !isset($payload['email'])) {
        echo json_encode(['success' => false, 'message' => 'Could not decode Google credential']);
        exit;
    }
    
    // Prepare user data
    $googleData = [
        'email' => $payload['email'],
        'name' => $payload['name'] ?? $payload['email'],
        'google_id' => $payload['sub'],
        'picture' => $payload['picture'] ?? null
    ];
    
    // Try to login/create user
    $result = loginWithGoogle($googleData);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'user' => $result['user'],
            'redirect' => 'customer_dashboard.php'
        ]);
    } else {
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'PHP Error: ' . $e->getMessage()
    ]);
}

ob_end_flush();
?>

