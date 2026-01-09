<?php
// includes/header.php
?>
<header class="top-header justify-between">
    <div class="flex items-center gap-4">
        <h2 class="text-xl font-bold"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h2>
    </div>

    <div class="flex items-center gap-6">
        <?php
        // Check if user is admin for navigation links
        $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
        ?>
        <nav class="flex gap-4 text-sm font-medium text-muted">
            <?php if ($isAdmin): ?>
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="admin_bookings.php" class="text-primary">Bookings</a>
            <?php else: ?>
                <a href="customer_dashboard.php">Dashboard</a>
                <a href="my_vehicles.php" class="text-primary">My Vehicles</a>
            <?php endif; ?>
        </nav>
        
        <div class="flex items-center gap-3">
            <button class="btn btn-outline btn-sm" style="border:none;"><i class="fa-regular fa-bell text-xl"></i></button>
            
            <!-- User Dropdown -->
            <div class="relative group" style="position: relative;">
                <div class="flex items-center gap-2 cursor-pointer">
                    <div class="text-right">
                        <div class="text-sm font-bold"><?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User'; ?></div>
                        <div class="text-xs text-muted"><?php echo isset($_SESSION['user_role']) ? ucfirst($_SESSION['user_role']) : 'Customer'; ?></div>
                    </div>
                    <img src="<?php echo isset($_SESSION['user_image']) && !empty($_SESSION['user_image']) ? htmlspecialchars($_SESSION['user_image']) : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['user_name'] ?? 'User') . '&background=0D9488&color=fff'; ?>" class="avatar" alt="User">
                </div>
                
                <!-- Dropdown Menu removed per request -->
            </div>
        </div>
    </div>
</header>
<style>
/* Simple CSS dropdown for group-hover */
.group:hover .hidden { display: block !important; }
</style>
