<?php
/**
 * Password Reset API
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

$email = trim($data['email'] ?? '');

if (empty($email)) {
    sendJsonResponse(['success' => false, 'message' => 'Please enter your email!']);
}

if (!isValidEmail($email)) {
    sendJsonResponse(['success' => false, 'message' => 'Invalid email format.']);
}

// Create password reset token
$result = createPasswordResetToken($email);

if ($result['success']) {
    // Attempt to send real email
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    
    if (file_exists($autoloadPath)) {
        require_once __DIR__ . '/../includes/mail_functions.php';
        
        $emailBody = "
        <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #e0e0e0; border-radius: 5px;'>
            <h2 style='color: #0d9488;'>Reset Your Password</h2>
            <p>Hello,</p>
            <p>We received a request to reset your password for your AutoCare Connect account.</p>
            <p>Click the button below to reset it:</p>
            <p>
                <a href='" . $result['reset_link'] . "' style='background-color: #0d9488; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a>
            </p>
            <p style='color: #666; font-size: 12px; margin-top: 20px;'>If you didn't ask for this, you can ignore this email.</p>
        </div>
        ";
        
        $mailResult = sendMail($email, "Reset Your Password - AutoCare Connect", $emailBody);
        $mailMessage = 'Password reset instructions sent to your email.';
    } else {
        $mailResult = ['success' => false, 'message' => 'Email dependencies are installing.'];
        $mailMessage = 'Email system is initializing. Please use the link below.';
    }
    
    sendJsonResponse([
        'success' => true,
        'message' => $mailMessage,
        'mail_status' => $mailResult,
        'debug_info' => [
            'reset_link' => $result['reset_link'],
            'note' => 'Link provided because email system might be setting up or on localhost.'
        ]
    ]);
    
} else {
    sendJsonResponse($result);
}
?>
