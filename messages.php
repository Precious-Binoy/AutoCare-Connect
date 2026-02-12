<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$conn = getDbConnection();
$user_id = getCurrentUserId();
$user_role = $_SESSION['user_role'] ?? 'customer'; // Fixed session variable
$activeTab = $_GET['tab'] ?? 'inbox'; // inbox, sent, compose
$current_page = 'messages.php'; // For sidebar highlighting

$messages = [];

// Fetch Inbox Messages
if ($activeTab === 'inbox') {
    $query = "SELECT m.*, u.name as sender_name, u.role as sender_role, u.profile_image 
              FROM messages m 
              JOIN users u ON m.sender_id = u.id 
              WHERE m.receiver_id = ? 
              ORDER BY m.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = $result->fetch_all(MYSQLI_ASSOC);
} 
// Fetch Sent Messages
elseif ($activeTab === 'sent') {
    // Group ALL sent messages by subject/time to handle broadcasts/multi-admin sends correctly
    // Admins might send to groups, customers send to admins.
    // The query needs to adapt to fetch relevant recipient info for display.
    if ($user_role === 'admin') {
        // Admin sent messages - can be to individuals or groups (drivers, mechanics, all)
        $query = "SELECT 
                    MIN(m.id) as id,
                    m.subject, 
                    m.message, 
                    MIN(m.created_at) as created_at,
                    COUNT(m.receiver_id) as recipient_count,
                    GROUP_CONCAT(DISTINCT u.role) as recipient_roles,
                    GROUP_CONCAT(DISTINCT u.name ORDER BY u.name ASC) as recipient_names,
                    GROUP_CONCAT(DISTINCT u.profile_image ORDER BY (u.profile_image != '') DESC) as recipient_images,
                    MIN(u.name) as single_receiver_name,
                    MIN(u.role) as single_receiver_role,
                    MIN(u.profile_image) as single_receiver_image
                  FROM messages m 
                  JOIN users u ON m.receiver_id = u.id 
                  WHERE m.sender_id = ? 
                  GROUP BY m.subject, m.message, DATE_FORMAT(m.created_at, '%Y-%m-%d %H:%i')
                  ORDER BY created_at DESC";
    } else {
        // Standard users (Drivers/Mechanics) sending to Admin or individuals
        $query = "SELECT 
                    MIN(m.id) as id,
                    m.subject, 
                    m.message, 
                    MIN(m.created_at) as created_at,
                    COUNT(m.receiver_id) as recipient_count,
                    GROUP_CONCAT(DISTINCT u.role) as recipient_roles,
                    GROUP_CONCAT(DISTINCT u.name ORDER BY (u.name = 'Admin User') ASC) as recipient_names,
                    GROUP_CONCAT(DISTINCT u.profile_image ORDER BY (u.profile_image != '') DESC) as recipient_images,
                    MIN(u.name) as single_receiver_name,
                    MIN(u.role) as single_receiver_role,
                    MIN(u.profile_image) as single_receiver_image
                  FROM messages m 
                  JOIN users u ON m.receiver_id = u.id 
                  WHERE m.sender_id = ? 
                  GROUP BY m.subject, m.message, DATE_FORMAT(m.created_at, '%Y-%m-%d %H:%i')
                  ORDER BY created_at DESC";
    }
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result(); 
    $messages = $result->fetch_all(MYSQLI_ASSOC);
}

// Mark as read logic would go here if viewing a specific message
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page-content {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            padding: 0;
            overflow: hidden;
            margin-top: 20px;
        }

        .action-bar {
            padding: 20px;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
        }

        .tabs {
            display: flex;
            gap: 8px;
            background: #f3f4f6;
            padding: 4px;
            border-radius: 8px;
        }

        .tab-link {
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            color: #6b7280;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .tab-link.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .compose-btn-main {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            transition: background 0.2s;
            border: none;
            cursor: pointer;
        }
        
        .compose-btn-main:hover {
            background-color: #1d4ed8;
        }

        /* Message List Styles */
        .message-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .message-row {
            display: flex;
            align-items: center;
            padding: 16px 24px;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
            transition: background 0.1s;
            gap: 20px;
        }

        .message-row:hover {
            background-color: #f9fafb;
        }

        .message-row.unread {
            background-color: #eff6ff;
            font-weight: 600;
        }

        .message-row:last-child {
            border-bottom: none;
        }

        .col-sender {
            width: 220px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .sender-avatar-small {
            width: 32px;
            height: 32px;
            background: #e5e7eb;
            color: #4b5563;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            flex-shrink: 0;
            overflow: hidden;
        }

        .sender-info-text {
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .sender-name {
            font-size: 0.95rem;
            color: #1f2937;
            font-weight: 600;
            line-height: 1.2;
        }

        .sender-role-badge {
            font-size: 0.75rem;
            color: #6b7280;
            font-weight: normal;
        }

        .col-content {
            flex: 1;
            min-width: 0; /* flexible text truncation */
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .msg-subject {
            color: #111827;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .msg-preview {
            color: #6b7280;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: normal;
        }

        .col-date {
            width: 140px;
            text-align: right;
            font-size: 0.85rem;
            color: #9ca3af;
            flex-shrink: 0;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            padding: 0;
            position: relative;
            transform: translateY(20px);
            transition: transform 0.3s;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: #f9fafb;
        }

        .modal-body {
            padding: 24px;
            overflow-y: auto;
            line-height: 1.6;
            color: #374151;
            white-space: pre-wrap;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            background: #fff;
        }

        .close-modal-btn {
            background: none;
            border: none;
            color: #6b7280;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }

        /* Form elements for Compose */
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            margin-top: 0.25rem;
        }
        .btn { padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; font-weight: 500; }
        .btn-primary { background: var(--primary); color: white; border: none; }
        .btn-outline { background: white; border: 1px solid #d1d5db; color: #374151; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="page-content">
                
                <!-- Action Header -->
                <div class="action-bar">
                    <div class="tabs">
                        <a href="?tab=inbox" class="tab-link <?php echo $activeTab === 'inbox' ? 'active' : ''; ?>">
                            <?php echo ($user_role === 'admin') ? 'Inbox' : 'Announcements'; ?>
                        </a>
                        <a href="?tab=sent" class="tab-link <?php echo $activeTab === 'sent' ? 'active' : ''; ?>">
                             <?php echo ($user_role === 'admin') ? 'Announcement History' : 'Message History'; ?>
                        </a>
                    </div>
                    
                    <button onclick="openComposeModal()" class="compose-btn-main">
                        <i class="fa-solid fa-pen-to-square"></i>
                        <?php echo ($user_role === 'admin') ? 'Post Announcement' : 'Send Message to Admin'; ?>
                    </button>
                </div>

                <!-- Messages List -->
                <?php if (empty($messages)): ?>
                    <div class="empty-state">
                        <i class="fa-regular fa-clipboard text-4xl mb-4 opacity-50"></i>
                        <p class="text-lg font-medium">No <?php echo ($activeTab === 'inbox') ? 'new announcements' : 'messages sent'; ?></p>
                    </div>
                <?php else: ?>
                    <div class="message-list">
                        <?php foreach ($messages as $msg): 
                            // Determine display values based on context
                            if ($activeTab === 'sent') {
                                // Grouped Sent View for ALL users
                                // Identify realistic names/images from group
                                $allNames = explode(',', $msg['recipient_names'] ?? '');
                                $allImages = explode(',', $msg['recipient_images'] ?? '');
                                
                                $bestName = $allNames[0] ?? 'Unknown';
                                $bestImage = !empty($allImages[0]) ? $allImages[0] : '';

                                if ($msg['recipient_count'] > 1) {
                                    $roles = explode(',', $msg['recipient_roles']);
                                    $uniqueRoles = array_unique($roles);
                                    
                                    // Check if it's sent to Admins
                                    $isToAdmins = false;
                                    foreach ($uniqueRoles as $r) {
                                        if (strtolower(trim($r)) === 'admin') {
                                            $isToAdmins = true;
                                        } else {
                                            $isToAdmins = false;
                                            break; 
                                        }
                                    }

                                    if ($isToAdmins) {
                                        $displayParams = [
                                            'name' => $bestName,
                                            'role' => 'Admin',
                                            'avatar' => !empty($bestImage) ? $bestImage : strtoupper(substr($bestName, 0, 1)),
                                            'is_image' => !empty($bestImage),
                                            'subject' => $msg['subject'],
                                            'body' => $msg['message'],
                                            'date' => $msg['created_at'],
                                            'unread' => false
                                        ];
                                    } else {
                                        $displayParams = [
                                            'name' => 'Broadcast',
                                            'role' => $msg['recipient_count'] . ' recipients',
                                            'avatar' => 'B',
                                            'is_image' => false,
                                            'subject' => $msg['subject'],
                                            'body' => $msg['message'],
                                            'date' => $msg['created_at'],
                                            'unread' => false
                                        ];
                                    }
                                } else {
                                    // Single Recipient
                                    $displayParams = [
                                        'name' => $bestName,
                                        'role' => $msg['single_receiver_role'] ?? 'User',
                                        'avatar' => !empty($bestImage) ? $bestImage : strtoupper(substr($bestName, 0, 1)),
                                        'is_image' => !empty($bestImage),
                                        'subject' => $msg['subject'],
                                        'body' => $msg['message'],
                                        'date' => $msg['created_at'],
                                        'unread' => false
                                    ];
                                }
                            } else {
                                // Inbox Logic (Standard)
                                $name = $msg['sender_name'] ?? 'System';
                                $role = $msg['sender_role'] ?? 'System';
                                $profileInfo = $msg['profile_image'] ?? '';
                                
                                $displayParams = [
                                    'name' => $name,
                                    'role' => $role,
                                    'avatar' => !empty($profileInfo) ? $profileInfo : strtoupper(substr($name, 0, 1)),
                                    'is_image' => !empty($profileInfo),
                                    'subject' => $msg['subject'],
                                    'body' => $msg['message'],
                                    'date' => $msg['created_at'],
                                    'unread' => !($msg['is_read'] ?? 1)
                                ];
                            }
                        ?>
                            <!-- Compact Unified Message Row -->
                            <div class="message-row <?php echo $displayParams['unread'] ? 'unread' : ''; ?>" 
                                 onclick='viewMessage(<?php echo json_encode($displayParams); ?>)'>
                                
                                <div class="col-sender">
                                    <div class="sender-avatar-small" <?php echo $displayParams['is_image'] ? 'style="background:none;"' : ''; ?>>
                                        <?php if ($displayParams['is_image']): ?>
                                            <img src="<?php echo htmlspecialchars($displayParams['avatar']); ?>" alt="" style="width:100%; height:100%; border-radius:50%; object-fit:cover;" onerror="this.style.display='none'; this.parentElement.innerText='<?php echo htmlspecialchars(substr($displayParams['name'], 0, 1)); ?>';">
                                        <?php else: ?>
                                            <?php echo $displayParams['avatar']; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="sender-info-text">
                                        <div class="sender-name"><?php echo htmlspecialchars($displayParams['name']); ?></div>
                                        <div class="sender-role-badge">
                                            <?php echo ($activeTab === 'inbox') ? 'From: ' : 'To: '; ?>
                                            <?php echo htmlspecialchars(ucfirst($displayParams['role'])); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-content">
                                    <div class="msg-subject"><?php echo htmlspecialchars($displayParams['subject']); ?></div>
                                    <div class="msg-preview">
                                        <?php echo htmlspecialchars(substr(str_replace(["\r", "\n"], ' ', $displayParams['body']), 0, 120)) . '...'; ?>
                                    </div>
                                </div>

                                <div class="col-date">
                                    <?php echo date('M d, h:i A', strtotime($displayParams['date'])); ?>
                                </div>
                            </div>

                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <!-- View Message Modal -->
    <div id="viewModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="flex items-center gap-4">
                    <div id="viewAvatar" class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center font-bold text-gray-600 overflow-hidden"></div>
                    <div>
                        <h3 id="viewSubject" class="text-xl font-bold text-gray-900 mb-1"></h3>
                        <div class="text-sm text-gray-500">
                            <span id="viewMeta1" class="font-medium text-gray-700"></span> 
                            <span class="mx-2">&bull;</span>
                            <span id="viewDate"></span>
                        </div>
                    </div>
                </div>
                <button onclick="closeViewModal()" class="close-modal-btn">&times;</button>
            </div>
            <div id="viewBody" class="modal-body"></div>
            <div class="modal-footer">
                <button onclick="closeViewModal()" class="btn btn-outline">Close</button>
            </div>
        </div>
    </div>

    <!-- Compose Modal -->
    <div id="composeModal" class="modal-overlay">
        <!-- Reusing same content structure but wrapping in a div for scrolling -->
        <div class="modal-content" style="padding: 30px; overflow: visible;">
            <button onclick="closeComposeModal()" class="close-modal" style="top: 20px; right: 20px;">&times;</button>
            <h2 class="text-2xl font-bold mb-6 text-gray-800">
                <?php echo ($user_role === 'admin') ? 'New Announcement' : 'Send Message to Admin'; ?>
            </h2>
            
            <form id="composeForm" onsubmit="sendMessage(event)">
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">To:</label>
                    <select name="recipient_type" id="recipientType" class="form-control" onchange="toggleRecipientInput()">
                        <?php if ($user_role === 'admin'): ?>
                            <option value="group_drivers">Drivers (Broadcast)</option>
                            <option value="group_mechanics">Mechanics (Broadcast)</option>
                            <option value="group_all">All Workers (Broadcast)</option>
                        <?php else: ?>
                            <option value="group_admins">Administrator</option>
                        <?php endif; ?>
                    </select>
                    
                    <?php if ($user_role === 'admin'): ?>
                        <div id="individualRecipient" style="display: none; margin-top: 10px;">
                            <select name="receiver_id" id="receiverId" class="form-control">
                                <option value="">Select User...</option>
                                <?php
                                $users = $conn->query("SELECT id, name, role FROM users WHERE role != 'admin' ORDER BY name");
                                while($u = $users->fetch_assoc()): ?>
                                    <option value="<?php echo $u['id']; ?>">
                                        <?php echo htmlspecialchars($u['name']) . ' (' . ucfirst($u['role']) . ')'; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Subject:</label>
                    <input type="text" name="subject" class="form-control" required placeholder="<?php echo ($user_role === 'admin') ? 'Announcement Title...' : 'Message Subject...'; ?>">
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Message:</label>
                    <textarea name="message" id="messageBody" rows="6" class="form-control" required placeholder="<?php echo ($user_role === 'admin') ? 'Write your announcement here...' : 'Type your message to the administrator...'; ?>"></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeComposeModal()" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary px-6">
                        <?php echo ($user_role === 'admin') ? 'Post Announcement' : 'Send Message'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Compose Modal Logic
        function openComposeModal() {
            const modal = document.getElementById('composeModal');
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('active'), 10);
        }

        function closeComposeModal() {
            const modal = document.getElementById('composeModal');
            modal.classList.remove('active');
            setTimeout(() => modal.style.display = 'none', 300);
        }

        // View Modal Logic
        function viewMessage(msg) {
            const modal = document.getElementById('viewModal');
            document.getElementById('viewSubject').textContent = msg.subject;
            document.getElementById('viewMeta1').textContent = msg.name + ' (' + msg.role + ')';
            document.getElementById('viewDate').textContent = msg.date;
            document.getElementById('viewBody').innerHTML = msg.body.replace(/\n/g, '<br>');
            
            const avatarEl = document.getElementById('viewAvatar');
            if (msg.is_image) {
                avatarEl.innerHTML = `<img src="${msg.avatar}" style="width:100%; height:100%; object-fit:cover;" onerror="this.parentElement.innerHTML='${msg.name.charAt(0).toUpperCase()}';">`;
            } else {
                avatarEl.innerHTML = msg.avatar;
            }

            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('active'), 10);
        }

        function closeViewModal() {
            const modal = document.getElementById('viewModal');
            modal.classList.remove('active');
            setTimeout(() => modal.style.display = 'none', 300);
        }

        // Close logic
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('active');
                setTimeout(() => event.target.style.display = 'none', 300);
            }
        }

        function toggleRecipientInput() {
            const type = document.getElementById('recipientType').value;
            const individualDiv = document.getElementById('individualRecipient');
            if (individualDiv) {
                if (type === 'individual') {
                    individualDiv.style.display = 'block';
                } else {
                    individualDiv.style.display = 'none';
                }
            }
        }

        function sendMessage(e) {
            e.preventDefault();
            const subject = document.querySelector('#composeModal input[name="subject"]').value;
            const message = document.querySelector('#composeModal textarea[name="message"]').value;
            const recipientType = document.getElementById('recipientType').value;
            
            let data = new URLSearchParams();
            data.append('subject', subject);
            data.append('message', message);

            if (recipientType === 'group_drivers') data.append('group', 'drivers');
            else if (recipientType === 'group_mechanics') data.append('group', 'mechanics');
            else if (recipientType === 'group_all') data.append('group', 'all');
            else if (recipientType === 'group_admins') data.append('group', 'admins');
            else if (recipientType === 'individual') {
                data.append('receiver_id', document.getElementById('receiverId').value);
            }

            fetch('ajax/send_message.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: data.toString()
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    alert('Message sent successfully!');
                    closeComposeModal();
                    window.location.reload();
                } else {
                    alert('Error: ' + res.error);
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred');
            });
        }
    </script>
</body>
</html>
