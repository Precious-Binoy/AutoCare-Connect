<?php 
$page_title = 'Service History'; 
require_once 'includes/auth.php';
requireLogin();

$user = getCurrentUser();
$userId = getCurrentUserId();
$role = $user['role'];

$current_page = 'my_bookings.php';
$conn = getDbConnection();

// Fetch bookings with detailed vehicle and status info
$vehicleId = isset($_GET['vehicle_id']) ? intval($_GET['vehicle_id']) : 0;

if ($role === 'customer') {
    if ($vehicleId > 0) {
        $query = "SELECT b.*, v.make, v.model, v.license_plate, v.type as vehicle_type
                  FROM bookings b 
                  JOIN vehicles v ON b.vehicle_id = v.id 
                  WHERE b.user_id = ? AND b.vehicle_id = ?
                  ORDER BY b.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $userId, $vehicleId);
    } else {
        $query = "SELECT b.*, v.make, v.model, v.license_plate, v.type as vehicle_type
                  FROM bookings b 
                  JOIN vehicles v ON b.vehicle_id = v.id 
                  WHERE b.user_id = ? 
                  ORDER BY b.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userId);
    }
} else {
    // Admin or Worker view
    $query = "SELECT b.*, v.make, v.model, v.license_plate, v.type as vehicle_type, u.name as customer_name
              FROM bookings b 
              JOIN vehicles v ON b.vehicle_id = v.id 
              JOIN users u ON b.user_id = u.id
              ORDER BY b.created_at DESC LIMIT 100";
    $stmt = $conn->prepare($query);
}

$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service History - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="page-content">
                <div class="mb-8 text-center">
                    <h1 class="section-header">Service History</h1>
                    <p class="text-muted text-sm mt-1">Centralized record of all your vehicle maintenance and repairs.</p>
                </div>

                <div class="glass-card p-0 overflow-hidden shadow-xl border-none">
                    <div class="overflow-x-auto">
                        <table class="w-full text-center">
                            <thead class="bg-gray-50/50 border-b border-gray-100">
                                <tr>
                                    <th class="p-5 text-[10px] font-black uppercase text-muted tracking-widest">Reference</th>
                                    <th class="p-5 text-[10px] font-black uppercase text-muted tracking-widest">Vehicle Details</th>
                                    <?php if ($role !== 'customer'): ?>
                                        <th class="p-5 text-[10px] font-black uppercase text-muted tracking-widest">Customer</th>
                                    <?php endif; ?>
                                    <th class="p-5 text-[10px] font-black uppercase text-muted tracking-widest">Service Type</th>
                                    <th class="p-5 text-[10px] font-black uppercase text-muted tracking-widest">Status</th>
                                    <th class="p-5 text-[10px] font-black uppercase text-muted tracking-widest">Booking Date</th>
                                    <th class="p-5 text-[10px] font-black uppercase text-muted tracking-widest">Bill</th>
                                    <th class="p-5 text-[10px] font-black uppercase text-muted tracking-widest">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php if (empty($bookings)): ?>
                                    <tr>
                                        <td colspan="7" class="p-20 text-center">
                                            <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-gray-100">
                                                <i class="fa-solid fa-folder-open text-3xl text-gray-300"></i>
                                            </div>
                                            <h3 class="font-bold text-gray-900">No Records Found</h3>
                                            <p class="text-xs text-muted">You haven't booked any services yet.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($bookings as $b): ?>
                                        <tr class="hover:bg-blue-50/30 transition-all">
                                            <td class="p-5 whitespace-nowrap">
                                                <span class="font-mono text-[10px] font-black text-primary bg-blue-50 px-2 py-1 rounded">#<?php echo $b['booking_number']; ?></span>
                                            </td>
                                            <td class="p-5">
                                                <div class="flex items-center justify-center gap-3 text-left">
                                                    <div class="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center text-muted border border-gray-100">
                                                        <i class="fa-solid fa-car text-sm"></i>
                                                    </div>
                                                    <div>
                                                        <div class="font-black text-[10px] text-gray-900"><?php echo htmlspecialchars($b['make'] . ' ' . $b['model']); ?></div>
                                                        <div class="text-[9px] text-muted font-bold tracking-tight"><?php echo htmlspecialchars($b['license_plate']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <?php if ($role !== 'customer'): ?>
                                                <td class="p-5 text-sm font-bold text-gray-700"><?php echo htmlspecialchars($b['customer_name'] ?? 'N/A'); ?></td>
                                            <?php endif; ?>
                                            <td class="p-5">
                                                <div class="text-[10px] font-bold text-gray-800"><?php echo htmlspecialchars($b['service_type']); ?></div>
                                                <div class="text-[9px] text-muted uppercase font-black"><?php echo htmlspecialchars($b['service_category']); ?></div>
                                            </td>
                                            <td class="p-5">
                                                <?php 
                                                    $displayStatus = $b['status'];
                                                    $isPaid = ($b['payment_status'] ?? 'unpaid') === 'paid';
                                                    if ($displayStatus === 'ready_for_delivery' && $isPaid) {
                                                        $displayStatus = 'delivered'; // This shows "Completed" with green badge
                                                    }
                                                ?>
                                                <span class="badge <?php echo getStatusBadgeClass($displayStatus); ?> text-[9px] font-black uppercase tracking-tighter px-3 py-1 rounded-full">
                                                    <?php echo formatStatusLabel($displayStatus); ?>
                                                </span>
                                            </td>
                                            <td class="p-5 whitespace-nowrap">
                                                <div class="text-[10px] font-black text-gray-500"><?php echo date('M d, Y', strtotime($b['created_at'])); ?></div>
                                                <div class="text-[9px] text-muted"><?php echo date('h:i A', strtotime($b['created_at'])); ?></div>
                                            </td>
                                            <!-- Bill / Pay column -->
                                            <td class="p-5">
                                                <?php if ($b['is_billed'] && ($b['payment_status'] ?? 'unpaid') === 'paid'): ?>
                                                    <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-green-50 text-green-700 border border-green-200 text-[10px] font-black uppercase">
                                                        <i class="fa-solid fa-circle-check text-green-500"></i> Completed
                                                    </span>
                                                <?php elseif ($b['is_billed'] && $b['final_cost'] > 0): ?>
                                                    <a href="pay_bill.php?id=<?php echo $b['id']; ?>" class="inline-flex items-center gap-1.5 btn btn-success px-4 py-2 text-[10px] font-black rounded-lg shadow-sm hover:shadow-md transition-all">
                                                        <i class="fa-solid fa-credit-card"></i> Pay ₹<?php echo number_format($b['final_cost'], 0); ?>
                                                    </a>
                                                    <div class="text-[9px] text-warning font-bold mt-1 uppercase tracking-tighter">Payment Pending</div>
                                                <?php else: ?>
                                                    <span class="text-[10px] text-muted italic">Bill Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <!-- Track column -->
                                            <td class="p-5">
                                                <a href="track_service.php?id=<?php echo $b['id']; ?>" class="w-9 h-9 rounded-xl bg-white border border-gray-100 flex items-center justify-center text-primary shadow-sm hover:bg-primary hover:text-white hover:border-primary transition-all mx-auto">
                                                    <i class="fa-solid fa-arrow-right-long"></i>
                                                </a>
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
</body>
</html>
