<?php 
$page_title = 'History'; 
require_once 'includes/auth.php';
requireLogin();

$user = getCurrentUser();
$userId = getCurrentUserId();
$role = $user['role'];

$bookings = [];
$conn = getDbConnection();

if ($role === 'customer') {
    $query = "SELECT b.*, v.make, v.model, v.license_plate 
              FROM bookings b 
              JOIN vehicles v ON b.vehicle_id = v.id 
              WHERE b.user_id = ? 
              ORDER BY b.created_at DESC";
    $result = executeQuery($query, [$userId], 'i');
} elseif ($role === 'mechanic') {
    // Get mechanic ID first
    $mQuery = "SELECT id FROM mechanics WHERE user_id = ?";
    $mRes = executeQuery($mQuery, [$userId], 'i');
    $mechanicId = $mRes ? $mRes->fetch_assoc()['id'] : 0;
    
    $query = "SELECT b.*, v.make, v.model, v.license_plate, u.name as customer_name
              FROM bookings b 
              JOIN vehicles v ON b.vehicle_id = v.id 
              JOIN users u ON b.user_id = u.id
              WHERE b.mechanic_id = ? 
              ORDER BY b.created_at DESC";
    $result = executeQuery($query, [$mechanicId], 'i');
} elseif ($role === 'driver') {
    $query = "SELECT pd.*, b.booking_number, v.make, v.model, v.license_plate, u.name as customer_name
              FROM pickup_delivery pd
              JOIN bookings b ON pd.booking_id = b.id
              JOIN vehicles v ON b.vehicle_id = v.id
              JOIN users u ON b.user_id = u.id
              WHERE pd.driver_user_id = ? 
              ORDER BY pd.created_at DESC";
    $result = executeQuery($query, [$userId], 'i');
} elseif ($role === 'admin') {
    $query = "SELECT b.*, v.make, v.model, v.license_plate, u.name as customer_name
              FROM bookings b 
              JOIN vehicles v ON b.vehicle_id = v.id 
              JOIN users u ON b.user_id = u.id
              ORDER BY b.created_at DESC LIMIT 100";
    $result = executeQuery($query, [], '');
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
}
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
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-3xl font-bold">Service History</h1>
                        <p class="text-muted">Viewing all your past and present service records.</p>
                    </div>
                </div>

                <div class="card p-0 overflow-hidden">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="p-4 text-xs font-bold text-muted uppercase">Reference</th>
                                <th class="p-4 text-xs font-bold text-muted uppercase">Vehicle</th>
                                <?php if ($role !== 'customer'): ?>
                                    <th class="p-4 text-xs font-bold text-muted uppercase">Customer</th>
                                <?php endif; ?>
                                <th class="p-4 text-xs font-bold text-muted uppercase">Service</th>
                                <th class="p-4 text-xs font-bold text-muted uppercase">Status</th>
                                <th class="p-4 text-xs font-bold text-muted uppercase">Date</th>
                                <th class="p-4 text-xs font-bold text-muted uppercase text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($bookings)): ?>
                                <tr>
                                    <td colspan="7" class="p-12 text-center text-muted">
                                        <i class="fa-solid fa-ghost text-4xl mb-3 opacity-20"></i>
                                        <p>No history found.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $b): ?>
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="p-4 font-mono text-xs font-bold text-primary">
                                            #<?php echo $b['booking_number'] ?? ($b['id'] . ' (PD)'); ?>
                                        </td>
                                        <td class="p-4">
                                            <div class="font-bold text-sm"><?php echo htmlspecialchars($b['make'] . ' ' . $b['model']); ?></div>
                                            <div class="text-[10px] text-muted"><?php echo htmlspecialchars($b['license_plate']); ?></div>
                                        </td>
                                        <?php if ($role !== 'customer'): ?>
                                            <td class="p-4 text-sm font-medium"><?php echo htmlspecialchars($b['customer_name'] ?? 'N/A'); ?></td>
                                        <?php endif; ?>
                                        <td class="p-4">
                                            <div class="text-sm"><?php echo htmlspecialchars($b['service_type'] ?? ($b['type'] . ' Task')); ?></div>
                                        </td>
                                        <td class="p-4">
                                            <span class="badge <?php echo getStatusBadgeClass($b['status']); ?> text-[10px] uppercase">
                                                <?php echo str_replace('_', ' ', $b['status']); ?>
                                            </span>
                                        </td>
                                        <td class="p-4 text-xs text-muted">
                                            <?php echo date('M d, Y', strtotime($b['created_at'])); ?>
                                        </td>
                                        <td class="p-4 text-right">
                                            <?php if ($role === 'customer'): ?>
                                                <a href="track_service.php?id=<?php echo $b['id']; ?>" class="btn btn-sm btn-white border-none shadow-sm"><i class="fa-solid fa-eye"></i></a>
                                            <?php else: ?>
                                                <span class="text-muted text-[10px]">Detail info N/A</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
