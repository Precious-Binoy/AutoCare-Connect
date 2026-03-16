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
                <span class="notification-badge" id="notificationBadge" style="display: none;"></span>
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
                // Default fallback avatar
                $fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['user_name'] ?? 'User') . '&background=2563eb&color=fff&size=80';
                $profileImage = $fallbackAvatar;
                
                if (isset($_SESSION['user_id'])) {
                    // Try session first to avoid DB query
                    if (!empty($_SESSION['profile_image'])) {
                        $profileImage = $_SESSION['profile_image'];
                    } else {
                        // DB check as fallback
                    if (!isset($conn)) {
                        require_once __DIR__ . '/../config/db.php';
                        $conn = getDbConnection();
                    }
                    $userId = $_SESSION['user_id'];
                    $stmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
                        $stmt->bind_param("i", $userId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($row = $result->fetch_assoc()) {
                            if (!empty($row['profile_image'])) {
                                $profileImage = $row['profile_image'];
                                $_SESSION['profile_image'] = $profileImage; // Sync session
                            }
                        }
                    }
                }

                // Resolve path: If it's a local file path (not starting with http), prepend APP_URL
                if (!empty($profileImage) && strpos($profileImage, 'http') !== 0) {
                    $baseUrl = defined('APP_URL') ? APP_URL : '';
                    $profileImage = rtrim($baseUrl, '/') . '/' . ltrim($profileImage, '/');
                }
                ?>
                <img src="<?php echo htmlspecialchars($profileImage); ?>" 
                     class="avatar" alt="User" 
                     style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"
                     onerror="this.src='<?php echo $fallbackAvatar; ?>'; this.onerror=null;">
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
                    elseif ($role === 'admin') $profileLink = 'admin_dashboard.php?tab=profile';

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
// Notification type icon mapping
const NOTIF_ICONS = {
    booking:     'fa-clipboard-list',
    service:     'fa-screwdriver-wrench',
    pickup:      'fa-truck-fast',
    delivery:    'fa-truck',
    leave:       'fa-calendar-minus',
    message:     'fa-envelope',
    job_request: 'fa-id-card',
    assignment:  'fa-briefcase',
    announcement:'fa-bullhorn',
    general:     'fa-bell'
};

// Track the highest seen notification ID
let lastSeenNotifId = 0;
// Track if page just loaded (skip toast on initial load)
let isFirstLoad = true;

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
        // Mark notifications as read when opened
        if (notificationDropdown.classList.contains('active')) {
            markNotificationsAsRead();
        }
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        profileDropdown.classList.remove('active');
        notificationDropdown.classList.remove('active');
    });
    
    profileDropdown.addEventListener('click', function(e) { e.stopPropagation(); });
    notificationDropdown.addEventListener('click', function(e) { e.stopPropagation(); });
    
    // Load notifications immediately
    loadNotifications();
    
    // Refresh notifications every 10 seconds (near real-time)
    setInterval(loadNotifications, 10000);
    
    // Clear notifications
    document.getElementById('clearNotifications').addEventListener('click', function(e) {
        e.preventDefault();
        clearAllNotifications();
    });
});

function loadNotifications() {
    fetch('ajax/get_notifications.php?t=' + new Date().getTime())
        .then(response => response.json())
        .then(data => {
            if (!data.success) return;
            
            const notificationList = document.getElementById('notificationList');
            const notificationBadge = document.getElementById('notificationBadge');
            const newLatestId = data.latest_id || 0;
            
            // Detect new notifications for toast (skip on first load)
            if (!isFirstLoad && newLatestId > lastSeenNotifId && data.unread_count > 0) {
                // Find notifications that are new (created after our last check)
                const newOnes = (data.notifications || []).filter(n => parseInt(n.id) > lastSeenNotifId && n.is_read == 0);
                if (newOnes.length > 0) {
                    showNotifToast(newOnes[0]);
                }
            }
            
            // Update lastSeenNotifId
            if (newLatestId > lastSeenNotifId) {
                lastSeenNotifId = newLatestId;
            }
            isFirstLoad = false;
            
            // Update badge
            notificationBadge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
            notificationBadge.style.display = data.unread_count > 0 ? 'flex' : 'none';
            
            // Render notification list
            if (data.notifications && data.notifications.length > 0) {
                let html = '';
                data.notifications.forEach(notif => {
                    const icon = NOTIF_ICONS[notif.type] || 'fa-bell';
                    const unreadClass = notif.is_read == 0 ? 'unread' : '';
                    const linkUrl = notif.link_url || '#';
                    const isClickable = notif.link_url ? 'cursor-pointer' : '';
                    
                    html += `<a href="${linkUrl}" class="notification-item ${unreadClass} ${isClickable}" style="text-decoration: none; color: inherit; display: block;" onclick="handleNotifClick(event, '${linkUrl}', ${notif.id})">
                        <div class="notification-item-header">
                            <span class="notification-item-title">
                                <i class="fa-solid ${icon}" style="margin-right: 6px; opacity: 0.7; font-size: 11px;"></i>${notif.title}
                            </span>
                            <span class="notification-item-time">${notif.time_ago}</span>
                        </div>
                        <div class="notification-item-message">${notif.message}</div>
                        ${notif.link_url ? '<div style="font-size: 9px; color: #2563eb; font-weight: 700; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.05em;"><i class="fa-solid fa-arrow-right" style="font-size: 8px;"></i> View Details</div>' : ''}
                    </a>`;
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

function handleNotifClick(e, linkUrl, notifId) {
    // Mark all as read when clicking any notification
    fetch('ajax/mark_notifications_read.php?t=' + new Date().getTime(), { method: 'POST' });
    // If there's a real link, navigate normally (don't prevent default)
    if (!linkUrl || linkUrl === '#') {
        e.preventDefault();
    }
}

function markNotificationsAsRead() {
    fetch('ajax/mark_notifications_read.php?t=' + new Date().getTime(), { method: 'POST' })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('notificationBadge').style.display = 'none';
            // Update unread visuals in dropdown
            document.querySelectorAll('.notification-item.unread').forEach(el => {
                el.classList.remove('unread');
            });
        }
    });
}

function clearAllNotifications() {
    fetch('ajax/clear_notifications.php?t=' + new Date().getTime(), { method: 'POST' })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
        }
    });
}

// Toast notification popup
function showNotifToast(notif) {
    const icon = NOTIF_ICONS[notif.type] || 'fa-bell';
    
    // Remove any existing toast
    const existing = document.getElementById('notifToast');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.id = 'notifToast';
    toast.style.cssText = `
        position: fixed;
        bottom: 24px;
        right: 24px;
        background: white;
        border-radius: 14px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.12), 0 2px 8px rgba(37,99,235,0.08);
        padding: 16px 20px;
        max-width: 340px;
        z-index: 99999;
        border-left: 4px solid #2563eb;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        animation: toastSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        cursor: pointer;
    `;
    
    const linkUrl = notif.link_url || 'javascript:void(0)';
    
    toast.innerHTML = `
        <div style="width: 36px; height: 36px; background: #EFF6FF; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i class="fa-solid ${icon}" style="color: #2563eb; font-size: 14px;"></i>
        </div>
        <div style="flex: 1; min-width: 0;">
            <div style="font-weight: 700; font-size: 13px; color: #111827; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${notif.title}</div>
            <div style="font-size: 11px; color: #6b7280; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">${notif.message}</div>
            ${notif.link_url ? '<div style="font-size: 10px; color: #2563eb; font-weight: 700; margin-top: 6px; text-transform: uppercase; letter-spacing: 0.05em;">Tap to view →</div>' : ''}
        </div>
        <button onclick="event.stopPropagation(); document.getElementById('notifToast').remove();" style="background: none; border: none; color: #9ca3af; cursor: pointer; padding: 2px; flex-shrink: 0; font-size: 14px; line-height: 1;">×</button>
    `;
    
    toast.addEventListener('click', function() {
        if (notif.link_url) window.location.href = notif.link_url;
        toast.remove();
    });
    
    document.body.appendChild(toast);
    
    // Auto-dismiss after 6 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.animation = 'toastSlideOut 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }
    }, 6000);
}

// Toast animation styles
const toastStyle = document.createElement('style');
toastStyle.textContent = `
    @keyframes toastSlideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes toastSlideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(120%); opacity: 0; }
    }
`;
document.head.appendChild(toastStyle);
</script>

