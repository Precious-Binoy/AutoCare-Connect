<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

require_once 'includes/mail_functions.php';

$conn = getDbConnection();

requireAdmin();

$current_page = 'admin_job_requests.php';
$page_title = 'Job Requests';
$success_msg = '';
$error_msg = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $request_id = intval($_POST['request_id']);
    
    // Get Request Details
    $stmt = $conn->prepare("SELECT * FROM job_requests WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();

    if ($request && $request['status'] === 'pending') {
        if ($_POST['action'] === 'approve') {
            // Start Transaction
            $conn->begin_transaction();
            try {
                // 0. Initialize Firebase and Get UID
                $firebase_uid = null;
                try {
                    require_once __DIR__ . '/vendor/autoload.php';
                    $factory = (new \Kreait\Firebase\Factory)->withServiceAccount(__DIR__ . '/config/firebase-key.json');
                    $auth = $factory->createAuth();
                    
                    try {
                        $fbUser = $auth->getUserByEmail($request['email']);
                        $firebase_uid = $fbUser->uid;
                        // Enable User
                        $auth->updateUser($firebase_uid, ['disabled' => false]);
                    } catch (\Exception $e) {
                         // If not found in firebase
                         $firebase_uid = null;
                    }
                } catch (\Exception $e) {
                    $firebase_uid = null;
                }

                // 1. Create or Update User
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->bind_param("s", $request['email']);
                $stmt->execute();
                $userRes = $stmt->get_result();
                
                if ($userRow = $userRes->fetch_assoc()) {
                    // Update existing user
                    $user_id = $userRow['id'];
                    $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, password = ?, role = ?, profile_image = ?, google_id = ?, is_active = TRUE WHERE id = ?");
                    $stmt->bind_param("ssssssi", $request['name'], $request['phone'], $request['password_hash'], $request['role_requested'], $request['profile_image_path'], $firebase_uid, $user_id);
                    $stmt->execute();
                } else {
                    // Insert new user
                    $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, profile_image, google_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssss", $request['name'], $request['email'], $request['phone'], $request['password_hash'], $request['role_requested'], $request['profile_image_path'], $firebase_uid);
                    $stmt->execute();
                    $user_id = $conn->insert_id;
                }

                // 2. Create/Update Specific Role Record
                if ($request['role_requested'] === 'driver') {
                    $stmt = $conn->prepare("INSERT INTO drivers (user_id, license_number, vehicle_number, is_available) VALUES (?, ?, ?, TRUE) ON DUPLICATE KEY UPDATE license_number = VALUES(license_number)");
                    $license = "See document"; 
                    $vehicle = "Not Assigned";
                    $stmt->bind_param("iss", $user_id, $license, $vehicle);
                    $stmt->execute();
                } elseif ($request['role_requested'] === 'mechanic') {
                    $stmt = $conn->prepare("INSERT INTO mechanics (user_id, years_experience, certification, status) VALUES (?, ?, ?, 'available') ON DUPLICATE KEY UPDATE years_experience = VALUES(years_experience), certification = VALUES(certification), status='available'");
                    $cert = "Verified ID: " . basename($request['id_proof_path']);
                    $stmt->bind_param("iis", $user_id, $request['experience_years'], $cert);
                    $stmt->execute();
                }

                // 3. Update Request Status
                $stmt = $conn->prepare("UPDATE job_requests SET status = 'approved' WHERE id = ?");
                $stmt->bind_param("i", $request_id);
                $stmt->execute();

                // 4. Send Email Notification
                $subject = "Welcome to AutoCare Connect - Application Approved!";
                $login_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/autocare-connect/login.php";
                $body = "<h2>Hello " . htmlspecialchars($request['name']) . ",</h2>";
                $body .= "<p>We are excited to inform you that your application for the role of <strong>" . ucfirst($request['role_requested']) . "</strong> has been <strong>approved</strong>!</p>";
                $body .= "<p>Your account is now active. You can log in using your registered email and the password you set during the application.</p>";
                $body .= "<p><a href='$login_url' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Login to Your Dashboard</a></p>";
                $body .= "<p>Welcome to the team!</p>";
                $body .= "<p>Best regards,<br>AutoCare Connect Team</p>";
                
                sendMail($request['email'], $subject, $body);

                $conn->commit();
                $success_msg = "Application approved! User account created and welcome email sent.";
            } catch (Exception $e) {
                $conn->rollback();
                $error_msg = "Error approving application: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'reject') {
            $stmt = $conn->prepare("UPDATE job_requests SET status = 'rejected' WHERE id = ?");
            $stmt->bind_param("i", $request_id);
            if ($stmt->execute()) {
                $success_msg = "Application rejected.";
            } else {
                $error_msg = "Error rejecting application.";
            }
        }
    }
}

// Fetch Pending Requests
$query = "SELECT * FROM job_requests WHERE status = 'pending' ORDER BY created_at ASC";
$result = $conn->query($query);
$pending_requests = $result->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AutoCare Connect</title>
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
                        <h1 class="text-2xl font-bold">Job Requests</h1>
                        <p class="text-muted">Review applications for Driver and Mechanic positions.</p>
                    </div>
                </div>

                <?php if ($success_msg): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                        <?php echo $success_msg; ?>
                    </div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                        <?php echo $error_msg; ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 gap-6">
                    <?php if (empty($pending_requests)): ?>
                        <div class="card p-12 text-center text-muted">
                            <i class="fa-solid fa-inbox text-4xl mb-4"></i>
                            <p>No pending job requests.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pending_requests as $req): ?>
                            <div class="card p-6">
                                <div class="flex justify-between items-start flex-col md:flex-row gap-4">
                                    <div class="flex gap-4">
                                        <div class="avatar bg-primary text-white flex items-center justify-center rounded-full text-xl font-bold" style="width: 50px; height: 50px;">
                                            <?php echo strtoupper(substr($req['name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <h3 class="font-bold text-lg"><?php echo htmlspecialchars($req['name']); ?></h3>
                                            <div class="text-sm text-muted mb-2">
                                                <i class="fa-solid fa-envelope mr-2"></i> <?php echo htmlspecialchars($req['email']); ?>
                                                <span class="mx-2">|</span>
                                                <i class="fa-solid fa-phone mr-2"></i> <?php echo htmlspecialchars($req['phone']); ?>
                                            </div>
                                            <span class="badge <?php echo $req['role_requested'] === 'mechanic' ? 'badge-primary' : 'badge-warning'; ?>">
                                                Applying for <?php echo ucfirst($req['role_requested']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex align-center gap-2">
                                        <form method="POST" onsubmit="return confirm('Approve this application? This will create a user account.');">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="fa-solid fa-check mr-1"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('Reject this application?');">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="fa-solid fa-times mr-1"></i> Reject
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <hr class="my-4 border-gray-100">
                                <div>
                                    <h4 class="font-bold text-sm text-muted uppercase mb-2">Qualifications & Experience</h4>
                                    <p class="text-sm mb-2"><strong>Years of Experience:</strong> <?php echo $req['experience_years']; ?></p>
                                    <div class="bg-gray-50 p-4 rounded text-sm whitespace-pre-wrap"><?php echo htmlspecialchars($req['qualifications']); ?></div>
                                </div>
                                <div class="mt-4">
                                    <h4 class="font-bold text-sm text-muted uppercase mb-2">Attached Documents</h4>
                                    <div class="flex flex-wrap gap-4">
                                        <?php if ($req['profile_image_path']): ?>
                                            <div class="text-center">
                                                <img src="<?php echo $req['profile_image_path']; ?>" class="rounded-lg border border-gray-200 mb-1" style="width: 80px; height: 80px; object-fit: cover;">
                                                <div class="text-xs text-muted">Profile Photo</div>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex gap-2 items-center">
                                            <?php if ($req['id_proof_path']): ?>
                                                <a href="<?php echo $req['id_proof_path']; ?>" target="_blank" class="btn btn-outline btn-sm">
                                                    <i class="fa-solid fa-file-invoice mr-1"></i> ID Proof
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($req['resume_path']): ?>
                                                <a href="<?php echo $req['resume_path']; ?>" target="_blank" class="btn btn-outline btn-sm">
                                                    <i class="fa-solid fa-file-lines mr-1"></i> Resume
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($req['license_path']): ?>
                                                <a href="<?php echo $req['license_path']; ?>" target="_blank" class="btn btn-outline btn-sm">
                                                    <i class="fa-solid fa-id-card mr-1"></i> License
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2 text-xs text-muted text-right">
                                    Applied on <?php echo date('M d, Y h:i A', strtotime($req['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
</body>
</html>
