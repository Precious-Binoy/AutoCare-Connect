<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$conn = getDbConnection();
$successMessage = '';
$errorMessage = '';

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $recipient_type = sanitizeInput($_POST['recipient_type']); // 'all', 'drivers', 'mechanics', or specific user_id
    $message_title = sanitizeInput($_POST['message_title']);
    $message_content = sanitizeInput($_POST['message_content']);
    
    if (empty($message_title) || empty($message_content)) {
        $errorMessage = 'Please fill in all fields.';
    } else {
        $conn->begin_transaction();
        try {
            $recipients = [];
            
            if ($recipient_type === 'all') {
                // Send to all drivers and mechanics
                $query = "SELECT id FROM users WHERE role IN ('driver', 'mechanic')";
                $result = $conn->query($query);
                while ($row = $result->fetch_assoc()) {
                    $recipients[] = $row['id'];
                }
            } elseif ($recipient_type === 'drivers') {
                // Send to all drivers
                $query = "SELECT id FROM users WHERE role = 'driver'";
                $result = $conn->query($query);
                while ($row = $result->fetch_assoc()) {
                    $recipients[] = $row['id'];
                }
            } elseif ($recipient_type === 'mechanics') {
                // Send to all mechanics
                $query = "SELECT id FROM users WHERE role = 'mechanic'";
                $result = $conn->query($query);
                while ($row = $result->fetch_assoc()) {
                    $recipients[] = $row['id'];
                }
            }
            
            // Insert notifications for all recipients
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
            foreach ($recipients as $user_id) {
                $stmt->bind_param("iss", $user_id, $message_title, $message_content);
                $stmt->execute();
            }
            
            $conn->commit();
            $successMessage = "Message sent to " . count($recipients) . " user(s) successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $errorMessage = "Failed to send message: " . $e->getMessage();
        }
    }
}

$page_title = 'Send Message to Workers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="page-content">
                <?php if ($successMessage): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl relative mb-6 animate-fade-in">
                        <span class="block sm:inline font-bold"><i class="fa-solid fa-circle-check mr-2"></i> <?php echo htmlspecialchars($successMessage); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl relative mb-6 animate-fade-in">
                        <span class="block sm:inline font-bold"><i class="fa-solid fa-circle-xmark mr-2"></i> <?php echo htmlspecialchars($errorMessage); ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="page-header">
                    <h1 class="text-2xl font-bold">Send Message to Workers</h1>
                    <p class="text-sm text-gray-600">Send notifications to drivers and mechanics</p>
                </div>

                <div class="max-w-2xl mx-auto mt-8">
                    <div class="bg-white rounded-xl shadow-md p-8">
                        <form method="POST" action="">
                            <div class="mb-6">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fa-solid fa-users mr-2 text-blue-600"></i>Send To
                                </label>
                                <select name="recipient_type" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                                    <option value="all">All Workers (Drivers & Mechanics)</option>
                                    <option value="drivers">All Drivers Only</option>
                                    <option value="mechanics">All Mechanics Only</option>
                                </select>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fa-solid fa-heading mr-2 text-blue-600"></i>Message Title
                                </label>
                                <input type="text" name="message_title" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="e.g., Important Announcement" required>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fa-solid fa-message mr-2 text-blue-600"></i>Message Content
                                </label>
                                <textarea name="message_content" rows="6" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Enter your message here..." required></textarea>
                            </div>

                            <button type="submit" name="send_message" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-200 shadow-lg">
                                <i class="fa-solid fa-paper-plane mr-2"></i>Send Message
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Announcement History -->
                <div class="mt-8">
                    <h2 class="text-xl font-bold mb-4 text-gray-900">
                        <i class="fa-solid fa-clock-rotate-left mr-2 text-blue-600"></i>
                        Announcement History
                    </h2>
                    
                    <div class="bg-white rounded-xl shadow-md overflow-hidden">
                        <?php
                        // Fetch recent announcements sent by admin
                        $historyQuery = "SELECT n.*, COUNT(DISTINCT n.user_id) as recipient_count
                                         FROM notifications n
                                         JOIN users u ON n.user_id = u.id
                                         WHERE u.role IN ('driver', 'mechanic')
                                         AND n.created_at >= NOW() - INTERVAL 30 DAY
                                         GROUP BY n.title, n.message, DATE(n.created_at)
                                         ORDER BY n.created_at DESC
                                         LIMIT 20";
                        $historyResult = $conn->query($historyQuery);
                        ?>
                        
                        <?php if ($historyResult && $historyResult->num_rows > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-gray-50 border-b border-gray-200">
                                        <tr class="text-xs font-bold text-gray-700 uppercase">
                                            <th class="px-6 py-3 text-left">Date Sent</th>
                                            <th class="px-6 py-3 text-left">Title</th>
                                            <th class="px-6 py-3 text-left">Message</th>
                                            <th class="px-6 py-3 text-center">Recipients</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <?php while ($announcement = $historyResult->fetch_assoc()): ?>
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-6 py-4 text-sm text-gray-500">
                                                    <?php echo date('M d, Y H:i', strtotime($announcement['created_at'])); ?>
                                                </td>
                                                <td class="px-6 py-4 font-semibold text-gray-900">
                                                    <?php echo htmlspecialchars($announcement['title']); ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-700">
                                                    <?php 
                                                    $msg = htmlspecialchars($announcement['message']);
                                                    echo strlen($msg) > 100 ? substr($msg, 0, 100) . '...' : $msg;
                                                    ?>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <span class="badge badge-info text-xs">
                                                        <?php echo $announcement['recipient_count']; ?> users
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="p-12 text-center text-gray-500">
                                <i class="fa-solid fa-inbox text-5xl mb-3 opacity-20"></i>
                                <p>No announcements sent in the last 30 days</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
