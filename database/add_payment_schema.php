<?php
require_once __DIR__ . '/../config/db.php';
$conn = getDbConnection();
$errors = [];
$success = [];

// 1. Add payment columns to bookings
$alterBookings = [
    "ALTER TABLE bookings ADD COLUMN payment_status ENUM('unpaid','paid') DEFAULT 'unpaid' AFTER is_billed",
    "ALTER TABLE bookings ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL AFTER payment_status",
    "ALTER TABLE bookings ADD COLUMN paid_at DATETIME DEFAULT NULL AFTER payment_method",
];

foreach ($alterBookings as $sql) {
    if ($conn->query($sql)) {
        $success[] = "✅ " . substr($sql, 0, 60) . "...";
    } else {
        // Ignore 'Duplicate column' errors (already exists)
        if (strpos($conn->error, 'Duplicate column') !== false) {
            $success[] = "⚠️ Column already exists (skipped).";
        } else {
            $errors[] = "❌ Error: " . $conn->error;
        }
    }
}

// 2. Create payments table
$createPayments = "CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  user_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  method ENUM('cash','card','upi','razorpay') NOT NULL DEFAULT 'razorpay',
  razorpay_order_id VARCHAR(100) DEFAULT NULL,
  razorpay_payment_id VARCHAR(100) DEFAULT NULL,
  razorpay_signature VARCHAR(255) DEFAULT NULL,
  status ENUM('pending','completed','failed') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($createPayments)) {
    $success[] = "✅ payments table created (or already exists).";
} else {
    $errors[] = "❌ Error creating payments table: " . $conn->error;
}

echo "<html><body style='font-family:monospace;padding:20px;'>";
echo "<h2>Payment Schema Migration</h2>";
foreach ($success as $s) { echo "<p style='color:green;'>$s</p>"; }
foreach ($errors as $e) { echo "<p style='color:red;'>$e</p>"; }
if (empty($errors)) {
    echo "<p style='color:green;font-weight:bold;'>✅ Migration complete!</p>";
} else {
    echo "<p style='color:orange;'>Some errors occurred. Check above.</p>";
}
echo "</body></html>";
?>
