<?php
/**
 * Process Razorpay Payment Verification
 * Called after Razorpay payment modal completes on client side.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

requireLogin();
$userId = getCurrentUserId();
$conn = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$bookingId       = intval($_POST['booking_id'] ?? 0);
$razorpayOrderId = $_POST['razorpay_order_id'] ?? '';
$razorpayPaymentId = $_POST['razorpay_payment_id'] ?? '';
$razorpaySignature = $_POST['razorpay_signature'] ?? '';

if (!$bookingId || !$razorpayOrderId || !$razorpayPaymentId || !$razorpaySignature) {
    echo json_encode(['success' => false, 'message' => 'Missing payment data.']);
    exit;
}

// Razorpay secret key for signature verification
$razorpaySecret = '6YyNvbydsg9HDPssm7e5QNxM';

// Verify signature
$expectedSignature = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, $razorpaySecret);
if (!hash_equals($expectedSignature, $razorpaySignature)) {
    echo json_encode(['success' => false, 'message' => 'Payment verification failed. Invalid signature.']);
    exit;
}

// Check booking belongs to user and is billed but unpaid
$bookingQuery = "SELECT id, user_id, final_cost, is_billed, payment_status FROM bookings WHERE id = ? AND user_id = ?";
$bookingRes = executeQuery($bookingQuery, [$bookingId, $userId], 'ii');
if (!$bookingRes || $bookingRes->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}
$booking = $bookingRes->fetch_assoc();

if (!$booking['is_billed']) {
    echo json_encode(['success' => false, 'message' => 'Bill not yet generated for this booking.']);
    exit;
}
if ($booking['payment_status'] === 'paid') {
    echo json_encode(['success' => false, 'message' => 'This bill has already been paid.']);
    exit;
}

$conn->begin_transaction();
try {
    // Insert into payments table
    $insertPayment = "INSERT INTO payments (booking_id, user_id, amount, method, razorpay_order_id, razorpay_payment_id, razorpay_signature, status)
                      VALUES (?, ?, ?, 'razorpay', ?, ?, ?, 'completed')";
    executeQuery($insertPayment, [$bookingId, $userId, $booking['final_cost'], $razorpayOrderId, $razorpayPaymentId, $razorpaySignature], 'iidsss');

    // Update booking payment status
    $updateBooking = "UPDATE bookings SET payment_status = 'paid', payment_method = 'razorpay', paid_at = NOW() WHERE id = ?";
    executeQuery($updateBooking, [$bookingId], 'i');

    // Notify admin
    $adminResult = $conn->query("SELECT id FROM users WHERE role = 'admin'");
    $customerName = $_SESSION['user_name'] ?? 'Customer';
    $msg = "Payment of ₹" . number_format($booking['final_cost'], 2) . " received from {$customerName} for Booking #{$bookingId} via Razorpay. ID: {$razorpayPaymentId}";
    notifyAdmins('💰 Payment Received', $msg, 'payment');

    // Notify Customer
    notifyCustomer($userId, '💳 Payment Successful', "Your payment of ₹" . number_format($booking['final_cost'], 2) . " for Booking #{$bookingId} has been confirmed. Thank you!", 'payment');

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Payment successful!',
        'payment_id' => $razorpayPaymentId,
        'amount' => $booking['final_cost']
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
