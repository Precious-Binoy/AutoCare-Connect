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
                    // Drivers table has: user_id, license_number, vehicle_number, vehicle_type, vehicle_info, is_available
                    $stmt = $conn->prepare("INSERT INTO drivers (user_id, license_number, vehicle_number, vehicle_type, is_available) 
                                           VALUES (?, ?, ?, ?, TRUE) 
                                           ON DUPLICATE KEY UPDATE license_number = VALUES(license_number), vehicle_number = VALUES(vehicle_number)");
                    $license = "Verified"; 
                    $vehicle = "Not Assigned";
                    $v_type = "Not Specified";
                    $stmt->bind_param("isss", $user_id, $license, $vehicle, $v_type);
                    $stmt->execute();
                } elseif ($request['role_requested'] === 'mechanic') {
                    // Mechanics table has: user_id, years_experience, certification, status, is_available
                    $stmt = $conn->prepare("INSERT INTO mechanics (user_id, years_experience, certification, status, is_available) 
                                           VALUES (?, ?, ?, 'available', TRUE) 
                                           ON DUPLICATE KEY UPDATE years_experience = VALUES(years_experience), certification = VALUES(certification), status='available'");
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
                $login_url = APP_URL . "/login.php";
                $role_label = ucfirst($request['role_requested']);
                $user_name = htmlspecialchars($request['name']);
                $year = date('Y');
                
                $body = "
                <div style=\"font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7fa; padding: 40px 0; width: 100%;\">
                    <div style=\"max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e1e8f0;\">
                        <div style=\"background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); padding: 40px 20px; text-align: center;\">
                            <h1 style=\"color: #ffffff; margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.5px;\">AutoCare Connect</h1>
                        </div>
                        <div style=\"padding: 40px 40px 20px 40px;\">
                            <h2 style=\"color: #1e293b; margin: 0 0 20px 0; font-size: 22px; font-weight: 800;\">Welcome to the Team!</h2>
                            <p style=\"color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;\">Hello <strong>$user_name</strong>,</p>
                            <p style=\"color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 24px 0;\">We are thrilled to inform you that your application for the role of <strong>$role_label</strong> has been <strong>approved</strong> by our administration team.</p>
                            <div style=\"background-color: #f8fafc; border-radius: 12px; padding: 24px; border: 1px solid #f1f5f9; margin-bottom: 30px;\">
                                <h3 style=\"color: #334155; margin: 0 0 12px 0; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;\">Account Status: Active</h3>
                                <p style=\"color: #64748b; font-size: 14px; margin: 0;\">You can now access your specialized dashboard using your registered email and the password you set during the application process.</p>
                            </div>
                            <div style=\"text-align: center; margin-bottom: 40px;\">
                                <a href=\"$login_url\" style=\"background-color: #2563eb; color: #ffffff; padding: 16px 32px; text-decoration: none; border-radius: 12px; display: inline-block; font-weight: 700; font-size: 16px; box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.25);\">Login to Your Dashboard</a>
                            </div>
                            <hr style=\"border: 0; border-top: 1px solid #f1f5f9; margin: 0 0 30px 0;\">
                            <p style=\"color: #94a3b8; font-size: 14px; line-height: 1.6; margin: 0;\">If you have any questions or need assistance getting started, please don't hesitate to reach out to our support team.</p>
                        </div>
                        <div style=\"background-color: #f8fafc; padding: 30px 40px; text-align: center; border-top: 1px solid #f1f5f9;\">
                            <p style=\"color: #64748b; font-size: 13px; margin: 0 0 8px 0;\">&copy; $year AutoCare Connect. All rights reserved.</p>
                            <p style=\"color: #94a3b8; font-size: 11px; margin: 0;\">This is an automated message, please do not reply to this email.</p>
                        </div>
                    </div>
                </div>";
                
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
                // Send Rejection Email
                $subject = "Update on Your AutoCare Connect Application";
                $user_name = htmlspecialchars($request['name']);
                $role_label = ucfirst($request['role_requested']);
                $year = date('Y');
                
                $body = "
                <div style=\"font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7fa; padding: 40px 0; width: 100%;\">
                    <div style=\"max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e1e8f0;\">
                        <div style=\"background: linear-gradient(135deg, #475569 0%, #1e293b 100%); padding: 30px 20px; text-align: center;\">
                            <h1 style=\"color: #ffffff; margin: 0; font-size: 20px; font-weight: 700; letter-spacing: 0.5px;\">AutoCare Connect</h1>
                        </div>
                        <div style=\"padding: 40px;\">
                            <h2 style=\"color: #1e293b; margin: 0 0 20px 0; font-size: 20px; font-weight: 700;\">Application Status Update</h2>
                            <p style=\"color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;\">Dear <strong>$user_name</strong>,</p>
                            <p style=\"color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;\">Thank you for taking the time to apply for the <strong>$role_label</strong> position with AutoCare Connect.</p>
                            <p style=\"color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;\">After careful consideration of your application and qualifications, we regret to inform you that we will not be moving forward with your application at this time.</p>
                            <p style=\"color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;\">We appreciate your interest in joining our network and wish you the best in your future endeavors.</p>
                            
                            <hr style=\"border: 0; border-top: 1px solid #f1f5f9; margin: 0 0 30px 0;\">
                            <p style=\"color: #94a3b8; font-size: 14px; margin: 0;\">Sincerely,<br>The AutoCare Connect Team</p>
                        </div>
                        <div style=\"background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #f1f5f9;\">
                            <p style=\"color: #94a3b8; font-size: 12px; margin: 0;\">&copy; $year AutoCare Connect. All rights reserved.</p>
                        </div>
                    </div>
                </div>";
                
                sendMail($request['email'], $subject, $body);
                
                $success_msg = "Application rejected and notification email sent.";
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
    <style>
        /* Custom Professional ATS Styles for Job Requests */
        .ats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 16px;
        }
        .ats-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: box-shadow 0.2s;
        }
        .ats-card:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-color: #cbd5e1;
        }
        .ats-header {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f8fafc;
        }
        .ats-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            background: #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }
        .ats-name {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 2px 0;
            line-height: 1.2;
        }
        .ats-meta {
            font-size: 11px;
            color: #64748b;
        }
        .ats-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .ats-badge.mechanic { background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; }
        .ats-badge.driver { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
        
        .ats-body {
            padding: 16px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .ats-data-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #475569;
        }
        .ats-data-icon {
            color: #94a3b8;
            width: 14px;
            text-align: center;
        }
        .ats-section-title {
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 6px 0;
        }
        .ats-qualifications {
            font-size: 12px;
            color: #334155;
            background: #f8fafc;
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #f1f5f9;
            max-height: 60px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }
        
        .ats-docs {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .ats-doc-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            text-decoration: none;
            transition: all 0.2s;
        }
        .ats-doc-link:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
        }
        .ats-doc-link i { font-size: 12px; }
        .icon-pdf { color: #ef4444; }
        .icon-id { color: #6366f1; }
        .icon-lic { color: #10b981; }
        
        .ats-actions {
            display: flex;
            border-top: 1px solid #f1f5f9;
        }
        .ats-btn {
            flex: 1;
            padding: 10px;
            border: none;
            background: transparent;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .ats-btn-approve {
            color: #10b981;
            border-right: 1px solid #f1f5f9;
        }
        .ats-btn-approve:hover { background: #ecfdf5; }
        .ats-btn-reject {
            color: #ef4444;
        }
        .ats-btn-reject:hover { background: #fef2f2; }
    </style>
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

                <div class="ats-grid">
                    <?php if (empty($pending_requests)): ?>
                        <div class="card p-8 text-center" style="grid-column: 1 / -1; border: 1px dashed #cbd5e1; background: #f8fafc; border-radius: 8px;">
                            <i class="fa-solid fa-inbox text-muted" style="font-size: 24px; margin-bottom: 8px;"></i>
                            <h3 style="font-size: 14px; font-weight: 700; color: #475569; margin: 0;">No Pending Applications</h3>
                            <p style="font-size: 12px; color: #94a3b8; margin: 4px 0 0 0;">New requests will appear here.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pending_requests as $req): ?>
                            <div class="ats-card">
                                <!-- Header Section -->
                                <div class="ats-header">
                                    <?php if ($req['profile_image_path']): ?>
                                        <img src="<?php echo $req['profile_image_path']; ?>" class="ats-avatar">
                                    <?php else: ?>
                                        <div class="ats-avatar"><?php echo strtoupper(substr($req['name'], 0, 1)); ?></div>
                                    <?php endif; ?>
                                    
                                    <div style="flex-grow: 1;">
                                        <h3 class="ats-name">
                                            <?php echo htmlspecialchars($req['name']); ?> 
                                            <span class="ats-badge <?php echo $req['role_requested']; ?>"><?php echo ucfirst($req['role_requested']); ?></span>
                                        </h3>
                                        <div class="ats-meta">
                                            Applied <?php echo date('M d, Y', strtotime($req['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Body Section -->
                                <div class="ats-body">
                                    <!-- Contact Info -->
                                    <div>
                                        <div class="ats-data-row">
                                            <i class="fa-solid fa-envelope ats-data-icon"></i>
                                            <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 250px;" title="<?php echo htmlspecialchars($req['email']); ?>"><?php echo htmlspecialchars($req['email']); ?></span>
                                        </div>
                                        <div class="ats-data-row" style="margin-top: 4px;">
                                            <i class="fa-solid fa-phone ats-data-icon"></i>
                                            <span><?php echo htmlspecialchars($req['phone']); ?></span>
                                            <span style="margin-left: auto; font-weight: 600; background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-size: 10px; color: #475569;">
                                                <?php echo $req['experience_years']; ?> Yrs Exp.
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Qualifications -->
                                    <div>
                                        <h4 class="ats-section-title">Qualifications</h4>
                                        <div class="ats-qualifications" title="<?php echo htmlspecialchars($req['qualifications']); ?>">
                                            <?php echo htmlspecialchars($req['qualifications'] ? $req['qualifications'] : 'N/A'); ?>
                                        </div>
                                    </div>

                                    <!-- Documents -->
                                    <div style="flex-grow: 1;">
                                        <h4 class="ats-section-title">Documents</h4>
                                        <div class="ats-docs">
                                            <?php if ($req['id_proof_path']): ?>
                                            <a href="<?php echo $req['id_proof_path']; ?>" target="_blank" class="ats-doc-link">
                                                <i class="fa-solid fa-id-card icon-id"></i> ID Proof
                                            </a>
                                            <?php endif; ?>
                                            <?php if ($req['resume_path']): ?>
                                            <a href="<?php echo $req['resume_path']; ?>" target="_blank" class="ats-doc-link">
                                                <i class="fa-solid fa-file-pdf icon-pdf"></i> Resume
                                            </a>
                                            <?php endif; ?>
                                            <?php if ($req['license_path']): ?>
                                            <a href="<?php echo $req['license_path']; ?>" target="_blank" class="ats-doc-link">
                                                <i class="fa-solid fa-drivers-license icon-lic"></i> License
                                            </a>
                                            <?php endif; ?>
                                            <?php if(!$req['id_proof_path'] && !$req['resume_path'] && !$req['license_path']): ?>
                                                <span style="font-size: 11px; color: #94a3b8; font-style: italic;">None attached</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="ats-actions">
                                    <form method="POST" style="flex: 1; display: flex;" onsubmit="return confirm('Approve this application?');">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <button type="submit" class="ats-btn ats-btn-approve">
                                            <i class="fa-solid fa-check"></i> Approve
                                        </button>
                                    </form>
                                    <form method="POST" style="flex: 1; display: flex;" onsubmit="return confirm('Reject this application?');">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <button type="submit" class="ats-btn ats-btn-reject">
                                            <i class="fa-solid fa-xmark"></i> Reject
                                        </button>
                                    </form>
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
