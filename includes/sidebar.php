<?php
// Check if user is admin
$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
?>
<aside class="sidebar">
    <div class="sidebar-logo">
        <a href="<?php echo $isAdmin ? 'admin_dashboard.php' : 'customer_dashboard.php'; ?>" class="flex items-center gap-2">
            <div style="width: 32px; height: 32px; background: var(--primary); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: white;">
                <i class="fa-solid fa-car-side"></i>
            </div>
            <span class="font-bold text-xl text-primary">AutoCare Connect</span>
        </a>
    </div>

    <nav class="nav-menu">
        <?php if ($isAdmin): ?>
            <!-- Admin Navigation -->
            <a href="admin_dashboard.php" class="nav-item <?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-gauge"></i> Admin Dashboard
            </a>
            <a href="admin_bookings.php" class="nav-item <?php echo ($current_page == 'admin_bookings.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-clipboard-list"></i> Manage Bookings
            </a>
            <a href="admin_mechanics.php" class="nav-item <?php echo ($current_page == 'admin_mechanics.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-user-gear"></i> Manage Mechanics
            </a>
            <a href="admin_drivers.php" class="nav-item <?php echo ($current_page == 'admin_drivers.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-truck"></i> Manage Drivers
            </a>
        <?php else: ?>
            <!-- Customer Navigation -->
            <a href="customer_dashboard.php" class="nav-item <?php echo ($current_page == 'customer_dashboard.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-gauge"></i> Dashboard
            </a>
            <a href="my_vehicles.php" class="nav-item <?php echo ($current_page == 'my_vehicles.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-car"></i> My Vehicles
            </a>
            <a href="book_service.php" class="nav-item <?php echo ($current_page == 'book_service.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-calendar-plus"></i> Book Service
            </a>
            <a href="pickup_delivery.php" class="nav-item <?php echo ($current_page == 'pickup_delivery.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-truck"></i> Pickup & Delivery
            </a>
            <a href="track_service.php" class="nav-item <?php echo ($current_page == 'track_service.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-magnifying-glass-location"></i> Track Service
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="nav-item">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
</aside>

