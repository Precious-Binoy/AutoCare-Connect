<?php
// includes/header.php
?>
<link rel="stylesheet" href="assets/css/profile-notifications.css">
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
            <?php elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'customer'): ?>
                <a href="customer_dashboard.php">Dashboard</a>
                <a href="my_vehicles.php" class="text-primary">My Vehicles</a>
            <?php endif; ?>
        </nav>
        
        <div class="profile-menu-container">
            <!-- Notification Bell -->
            <div class="notification-bell" id="notificationBell">
                <i class="fa-regular fa-bell text-xl"></i>
                <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
            </div>
            
            <!-- Notification Dropdown -->
            <div class="notification-dropdown" id="notificationDropdown">
                <div class="notification-header">
                    <h3>Notifications</h3>
                    <a href="#" class="notification-clear" id="clearNotifications">Clear All</a>
                </div>
                <div id="notificationList">
                    <div class="notification-empty">
                        <i class="fa-regular fa-bell-slash text-3xl mb-2"></i>
                        <p>No notifications yet</p>
                    </div>
                </div>
            </div>
            
            <!-- Profile Trigger -->
            <div class="profile-trigger" id="profileTrigger">
                <div class="text-right">
                    <div class="text-sm font-bold"><?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User'; ?></div>
                    <div class="text-xs text-muted"><?php echo isset($_SESSION['user_role']) ? ucfirst($_SESSION['user_role']) : 'Customer'; ?></div>
                </div>
                <?php
                $profileImage = 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['user_name'] ?? 'User') . '&background=2563eb&color=fff';
                
                // Check for profile_image in session or fetch from database
                if (isset($_SESSION['user_id'])) {
                    require_once('config/db.php');
                    $conn = getDbConnection();
                    $userId = $_SESSION['user_id'];
                    $stmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        if (!empty($row['profile_image'])) {
                            $profileImage = $row['profile_image'];
                        }
                    }
                }
                ?>
                <img src="<?php echo htmlspecialchars($profileImage); ?>" class="avatar" alt="User" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
            </div>
            
            <!-- Profile Dropdown -->
            <div class="profile-dropdown" id="profileDropdown">
                <div class="profile-dropdown-header">
                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile" class="profile-dropdown-avatar">
                    <div class="profile-dropdown-name"><?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User'; ?></div>
                    <div class="profile-dropdown-role"><?php echo isset($_SESSION['user_role']) ? htmlspecialchars($_SESSION['user_role']) : 'Customer'; ?></div>
                </div>
                <div class="profile-dropdown-menu">
                    <?php
                    $role = $_SESSION['user_role'] ?? 'customer';
                    $profileLink = '';
                    if ($role === 'driver') $profileLink = 'driver_dashboard.php?tab=profile';
                    elseif ($role === 'mechanic') $profileLink = 'mechanic_dashboard.php?tab=profile';
                    elseif ($role === 'admin') $profileLink = 'admin_dashboard.php';
                    else $profileLink = 'customer_dashboard.php?tab=profile';
                    ?>
                    <a href="<?php echo $profileLink; ?>" class="profile-dropdown-item">
                        <i class="fa-solid fa-user"></i>
                        My Profile
                    </a>
                    <div class="profile-dropdown-divider"></div>
                    <a href="logout.php" class="profile-dropdown-item" style="color: #ef4444;">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
// Profile and Notification Dropdown Logic
document.addEventListener('DOMContentLoaded', function() {
    const profileTrigger = document.getElementById('profileTrigger');
    const profileDropdown = document.getElementById('profileDropdown');
    const notificationBell = document.getElementById('notificationBell');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    // Toggle profile dropdown
    profileTrigger.addEventListener('click', function(e) {
        e.stopPropagation();
        profileDropdown.classList.toggle('active');
        notificationDropdown.classList.remove('active');
    });
    
    // Toggle notification dropdown
    notificationBell.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationDropdown.classList.toggle('active');
        profileDropdown.classList.remove('active');
        
        // Mark notifications as read
        markNotificationsAsRead();
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        profileDropdown.classList.remove('active');
        notificationDropdown.classList.remove('active');
    });
    
    // Prevent dropdown close when clicking inside
    profileDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });
    
    notificationDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });
    
    // Load notifications
    loadNotifications();
    
    // Refresh notifications every 30 seconds
    setInterval(loadNotifications, 30000);
    
    // Clear notifications
    document.getElementById('clearNotifications').addEventListener('click', function(e) {
        e.preventDefault();
        clearAllNotifications();
    });
});

function loadNotifications() {
    fetch('ajax/get_notifications.php')
        .then(response => response.json())
        .then(data => {
            const notificationList = document.getElementById('notificationList');
            const notificationBadge = document.getElementById('notificationBadge');
            
            if (data.success && data.notifications && data.notifications.length > 0) {
                notificationBadge.textContent = data.unread_count;
                notificationBadge.style.display = data.unread_count > 0 ? 'flex' : 'none';
                
                let html = '';
                data.notifications.forEach(notif => {
                    html += `
                        <div class="notification-item ${notif.is_read == 0 ? 'unread' : ''}">
                            <div class="notification-item-header">
                                <span class="notification-item-title">${notif.title}</span>
                                <span class="notification-item-time">${notif.time_ago}</span>
                            </div>
                            <div class="notification-item-message">${notif.message}</div>
                        </div>
                    `;
                });
                notificationList.innerHTML = html;
            } else {
                notificationBadge.style.display = 'none';
                notificationList.innerHTML = `
                    <div class="notification-empty">
                        <i class="fa-regular fa-bell-slash text-3xl mb-2"></i>
                        <p>No notifications yet</p>
                    </div>
                `;
            }
        })
        .catch(error => console.error('Error loading notifications:', error));
}

function markNotificationsAsRead() {
    fetch('ajax/mark_notifications_read.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('notificationBadge').style.display = 'none';
        }
    });
}

function clearAllNotifications() {
    fetch('ajax/clear_notifications.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
        }
    });
}
</script>
