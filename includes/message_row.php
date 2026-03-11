<?php
// Determining display values based on context
if ($activeTab === 'sent') {
    $allNames = explode(',', $msg['recipient_names'] ?? '');
    $allImages = explode(',', $msg['recipient_images'] ?? '');
    
    $bestName = $allNames[0] ?? 'Unknown';
    $bestImage = !empty($allImages[0]) ? $allImages[0] : '';
    
    if ($msg['recipient_count'] > 1) {
        $roles = explode(',', $msg['recipient_roles']);
        $uniqueRoles = array_unique($roles);
        
        $isToAdmins = true;
        foreach ($uniqueRoles as $r) {
            if (strtolower(trim($r)) !== 'admin') {
                $isToAdmins = false;
                break; 
            }
        }
        
        if ($isToAdmins) {
            $displayParams = [
                'id' => $msg['id'],
                'name' => $bestName,
                'role' => 'Admin',
                'avatar' => !empty($bestImage) ? $bestImage : strtoupper(substr($bestName, 0, 1)),
                'is_image' => !empty($bestImage),
                'subject' => $msg['subject'],
                'body' => $msg['message'],
                'date' => $msg['created_at'],
                'unread' => false,
                'is_announcement' => false
            ];
        } else {
            $displayParams = [
                'id' => $msg['id'],
                'name' => 'Broadcast',
                'role' => $msg['recipient_count'] . ' recipients',
                'avatar' => 'B',
                'is_image' => false,
                'subject' => $msg['subject'],
                'body' => $msg['message'],
                'date' => $msg['created_at'],
                'unread' => false,
                'is_announcement' => true
            ];
        }
    } else {
        $displayParams = [
            'id' => $msg['id'],
            'name' => $bestName,
            'role' => $msg['single_receiver_role'] ?? 'User',
            'avatar' => !empty($bestImage) ? $bestImage : strtoupper(substr($bestName, 0, 1)),
            'is_image' => !empty($bestImage),
            'subject' => $msg['subject'],
            'body' => $msg['message'],
            'date' => $msg['created_at'],
            'unread' => false,
            'is_announcement' => false
        ];
    }
} else {
    $name = $msg['sender_name'] ?? 'System';
    $role = $msg['sender_role'] ?? 'System';
    $profileInfo = $msg['profile_image'] ?? '';
    
    $displayParams = [
        'id' => $msg['id'],
        'name' => $name,
        'role' => $role,
        'avatar' => !empty($profileInfo) ? $profileInfo : strtoupper(substr($name, 0, 1)),
        'is_image' => !empty($profileInfo),
        'subject' => $msg['subject'],
        'body' => $msg['message'],
        'date' => $msg['created_at'],
        'unread' => !($msg['is_read'] ?? 1),
        'is_announcement' => (strtolower($role) === 'admin' && $user_role !== 'admin')
    ];
}
?>

<div class="message-row <?php echo $displayParams['unread'] ? 'unread' : ''; ?>" 
     data-id="<?php echo $displayParams['id']; ?>"
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
            <div class="sender-name">
                <?php if ($displayParams['unread']): ?><span class="unread-dot"></span><?php endif; ?>
                <?php echo htmlspecialchars($displayParams['name']); ?>
            </div>
            <div class="sender-role-badge">
                <?php echo ($activeTab === 'inbox') ? 'From: ' : 'To: '; ?>
                <?php echo htmlspecialchars(ucfirst($displayParams['role'])); ?>
            </div>
        </div>
    </div>

    <div class="col-content">
        <div class="msg-subject">
            <?php echo htmlspecialchars($displayParams['subject']); ?>
            <?php if ($displayParams['is_announcement']): ?>
                <span class="announcement-badge">Announcement</span>
            <?php endif; ?>
        </div>
        <div class="msg-preview">
            <?php echo htmlspecialchars(substr(str_replace(["\r", "\n"], ' ', $displayParams['body']), 0, 120)) . '...'; ?>
        </div>
    </div>

    <div class="col-date">
        <?php echo date('M d, h:i A', strtotime($displayParams['date'])); ?>
    </div>
</div>
