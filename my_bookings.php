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
if ($role === 'customer') {
    $query = "SELECT b.*, v.make, v.model, v.license_plate, v.type as vehicle_type
              FROM bookings b 
              JOIN vehicles v ON b.vehicle_id = v.id 
              WHERE b.user_id = ? 
              ORDER BY b.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
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
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="page-content">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-3xl font-black text-gray-900">Service History</h1>
                        <p class="text-muted text-sm border-l-4 border-primary pl-3 mt-2">Centralized record of all your vehicle maintenance and repairs.</p>
                    </div>
                </div>

                <div class="glass-card p-0 overflow-hidden shadow-xl border-none">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
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
                                    <th class="p-5 text-[10px] font-black uppercase text-muted tracking-widest text-right">Action</th>
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
                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center text-muted border border-gray-100">
                                                        <i class="fa-solid fa-car text-sm"></i>
                                                    </div>
                                                    <div>
                                                        <div class="font-black text-sm text-gray-900"><?php echo htmlspecialchars($b['make'] . ' ' . $b['model']); ?></div>
                                                        <div class="text-[10px] text-muted font-bold tracking-tight"><?php echo htmlspecialchars($b['license_plate']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <?php if ($role !== 'customer'): ?>
                                                <td class="p-5 text-sm font-bold text-gray-700"><?php echo htmlspecialchars($b['customer_name'] ?? 'N/A'); ?></td>
                                            <?php endif; ?>
                                            <td class="p-5">
                                                <div class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($b['service_type']); ?></div>
                                                <div class="text-[9px] text-muted uppercase font-black"><?php echo htmlspecialchars($b['service_category']); ?></div>
                                            </td>
                                            <td class="p-5">
                                                <span class="badge <?php echo getStatusBadgeClass($b['status']); ?> text-[9px] font-black uppercase tracking-tighter px-3 py-1 rounded-full">
                                                    <?php echo formatStatusLabel($b['status']); ?>
                                                </span>
                                            </td>
                                            <td class="p-5 whitespace-nowrap">
                                                <div class="text-xs font-black text-gray-500"><?php echo date('M d, Y', strtotime($b['created_at'])); ?></div>
                                                <div class="text-[9px] text-muted"><?php echo date('h:i A', strtotime($b['created_at'])); ?></div>
                                            </td>
                                            <td class="p-5 text-right">
                                                <a href="track_service.php?id=<?php echo $b['id']; ?>" class="w-9 h-9 rounded-xl bg-white border border-gray-100 flex items-center justify-center text-primary shadow-sm hover:bg-primary hover:text-white hover:border-primary transition-all mx-auto lg:mr-0 lg:ml-auto">
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
