<?php
/**
 * Admin: Mark Booking as Paid via Cash/Offline
 * Only accessible by admin users.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notification_helper.php';

header('Content-Type: application/json');

requireAdmin();
$adminId = getCurrentUserId();
$conn = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$bookingId = intval($_POST['booking_id'] ?? 0);
$notes     = trim($_POST['notes'] ?? '');

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID.']);
    exit;
}

// Fetch booking — must be billed and not already paid
$bookingQuery = "SELECT b.id, b.user_id, b.final_cost, b.is_billed, b.payment_status, b.booking_number, u.name as customer_name
                 FROM bookings b
                 JOIN users u ON b.user_id = u.id
                 WHERE b.id = ?";
$bookingRes = executeQuery($bookingQuery, [$bookingId], 'i');

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
    echo json_encode(['success' => false, 'message' => 'This booking has already been marked as paid.']);
    exit;
}

$conn->begin_transaction();
try {
    // Insert into payments table as cash
    $insertPayment = "INSERT INTO payments (booking_id, user_id, amount, method, status, created_at)
                      VALUES (?, ?, ?, 'cash', 'completed', NOW())";
    executeQuery($insertPayment, [$bookingId, $booking['user_id'], $booking['final_cost']], 'iid');

    // Update booking payment status to paid
    $updateBooking = "UPDATE bookings SET payment_status = 'paid', payment_method = 'cash', paid_at = NOW() WHERE id = ?";
    executeQuery($updateBooking, [$bookingId], 'i');

    // Notify customer about cash payment confirmation
    $amount = number_format((float)$booking['final_cost'], 2);
    notifyCustomer(
        $booking['user_id'],
        '💵 Payment Confirmed (Cash)',
        "Your cash payment of ₹{$amount} for Booking #{$booking['booking_number']} has been received and confirmed by our admin. Thank you!",
        'payment',
        'track_service.php'
    );

    $conn->commit();

    echo json_encode([
        'success'  => true,
        'message'  => "Booking #{$booking['booking_number']} marked as paid via Cash. ₹{$amount} received.",
        'booking_number' => $booking['booking_number'],
        'amount'   => $amount
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
