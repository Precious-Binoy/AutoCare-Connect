<?php
require_once 'config/config.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

// Ensure database connection is initialized
$conn = getDbConnection();

$page_title = 'Join Our Team';
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $role = sanitizeInput($_POST['role']);
    $password = $_POST['password'];
    $experience = intval($_POST['experience']);
    $qualifications = sanitizeInput($_POST['qualifications']);

    // Basic Validation
    if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($role)) {
        $error_msg = 'Please fill in all required fields.';
    } elseif (strlen($password) < 6) {
        $error_msg = 'Password must be at least 6 characters.';
    } else {
        // Check if email already exists in users or job_requests
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error_msg = 'This email is already registered.';
        } else {
            $stmt = $conn->prepare("SELECT id FROM job_requests WHERE email = ? AND status = 'pending'");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error_msg = 'You already have a pending application.';
            } else {
                // Handle File Uploads
                $upload_dir = 'assets/uploads/career_docs/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_paths = [
                    'id_proof' => null,
                    'resume' => null,
                    'license' => null,
                    'profile_image' => null
                ];

                $upload_error = false;
                foreach ($file_paths as $key => &$path) {
                    if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
                        $file_ext = pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION);
                        $file_name = $key . '_' . time() . '_' . uniqid() . '.' . $file_ext;
                        $target_path = $upload_dir . $file_name;

                        if (move_uploaded_file($_FILES[$key]['tmp_name'], $target_path)) {
                            $path = $target_path;
                        } else {
                            $upload_error = true;
                            $error_msg = "Failed to upload $key. Please try again.";
                            break;
                        }
                    }
                }

                if (!$upload_error) {
                    // Initialize Firebase
                    $firebase_uid = null;
                    try {
                        require_once __DIR__ . '/vendor/autoload.php';
                        $factory = (new \Kreait\Firebase\Factory)->withServiceAccount(__DIR__ . '/config/firebase-key.json');
                        $auth = $factory->createAuth();
                        
                        // Create user in Firebase (disabled until approved)
                        $userProperties = [
                            'email' => $email,
                            'password' => $password,
                            'displayName' => $name,
                            'disabled' => true,
                        ];
                        
                        $createdUser = $auth->createUser($userProperties);
                        $firebase_uid = $createdUser->uid;
                    } catch (\Exception $fe) {
                        // If user already exists in Firebase, try to get their UID
                        try {
                            $existingUser = $auth->getUserByEmail($email);
                            $firebase_uid = $existingUser->uid;
                        } catch (\Exception $fe2) {
                            $error_msg = 'Firebase Sync Error: ' . $fe->getMessage();
                            $upload_error = true;
                        }
                    }

                    if (!$upload_error) {
                        // Insert Job Request
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        // We store the firebase_uid in qualifications or a new column? 
                        // Let's store it in a way we can retrieve it. I'll add it to the qualifications text for now or just trust email lookup.
                        // Actually, I'll store it in the password_hash field by appending it or just use email lookup in admin.
                        // Better: I'll try to add a column if I can, but let's keep it simple: Use email lookup in Admin.
                        
                        $stmt = $conn->prepare("INSERT INTO job_requests (name, email, phone, role_requested, experience_years, qualifications, password_hash, id_proof_path, resume_path, license_path, profile_image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssissssss", $name, $email, $phone, $role, $experience, $qualifications, $password_hash, $file_paths['id_proof'], $file_paths['resume'], $file_paths['license'], $file_paths['profile_image']);
                        
                        if ($stmt->execute()) {
                            $success_msg = 'Your application has been submitted successfully! We will review your details and documents. You will receive an email once the admin approves your request. After that, you can login using your email and password to access your dashboard.';
                        } else {
                            $error_msg = 'Failed to submit application. Please try again.';
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Careers - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navbar (Simplified) -->
    <nav class="navbar" style="padding: 1rem 5%;">
        <div class="logo">
            <a href="index.php" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-car-wrench text-primary" style="font-size: 1.5rem;"></i>
                <span style="font-size: 1.5rem; font-weight: 800; letter-spacing: -0.5px;">AutoCare<span class="text-primary">Connect</span></span>
            </a>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline">Back to Home</a>
        </div>
    </nav>

    <div class="container" style="max-width: 800px; margin: 4rem auto; padding: 0 1rem;">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold mb-4">Join Our Team</h1>
            <p class="text-muted text-lg">We are looking for skilled Mechanics and reliable Drivers.</p>
        </div>

        <?php if ($success_msg): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-8 rounded text-center mb-8 shadow-sm">
                <i class="fa-solid fa-circle-check text-5xl mb-4 text-green-500"></i>
                <h3 class="font-bold text-2xl mb-4">Application Submitted!</h3>
                <p class="text-lg leading-relaxed"><?php echo $success_msg; ?></p>
                <div class="mt-8">
                    <p class="text-sm text-muted mb-4">You will be redirected to the home page in <span id="countdown">10</span> seconds...</p>
                    <a href="index.php" class="btn btn-primary px-8">Return Home Now</a>
                </div>
            </div>
            <script>
                let seconds = 10;
                const countdownEl = document.getElementById('countdown');
                const timer = setInterval(() => {
                    seconds--;
                    countdownEl.textContent = seconds;
                    if (seconds <= 0) {
                        clearInterval(timer);
                        window.location.href = 'index.php';
                    }
                }, 1000);
            </script>
        <?php else: ?>

            <?php if ($error_msg): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6">
                    <span class="block sm:inline"><?php echo $error_msg; ?></span>
                </div>
            <?php endif; ?>

            <div class="card p-8 mb-8" id="benefitsSection">
                <div id="defaultBenefits">
                    <h3 class="font-bold text-2xl mb-4">Why Join AutoCare Connect?</h3>
                    <p class="text-muted mb-6 text-lg">We are the fastest growing auto-care network. Partner with us and grow your professional career with flexibility and premium support.</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="flex items-start gap-3">
                            <i class="fa-solid fa-clock text-primary text-xl mt-1"></i>
                            <div>
                                <div class="font-bold">Flexible Hours</div>
                                <div class="text-xs text-muted">Work on your own schedule and earn more during peak hours.</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <i class="fa-solid fa-wallet text-secondary text-xl mt-1"></i>
                            <div>
                                <div class="font-bold">Weekly Payouts</div>
                                <div class="text-xs text-muted">Reliable weekly payments directly to your bank account.</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <i class="fa-solid fa-shield-halved text-success text-xl mt-1"></i>
                            <div>
                                <div class="font-bold">Insurance & Safety</div>
                                <div class="text-xs text-muted">Complete insurance coverage while you are on a job.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="mechanicBenefits" class="hidden animate-fade-in">
                    <h3 class="font-bold text-2xl mb-4 text-primary">Grow as a Professional Mechanic</h3>
                    <p class="text-muted mb-6 text-lg">Access high-end tools, steady stream of bookings, and premium workshop facilities.</p>
                    <ul class="grid grid-cols-1 md:grid-cols-2 gap-4 list-none p-0">
                        <li class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-success"></i> Modern Diagnostic Equipment</li>
                        <li class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-success"></i> Advanced Skills Training</li>
                        <li class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-success"></i> Premium Spare Parts Access</li>
                        <li class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-success"></i> Performance Bonuses</li>
                    </ul>
                </div>

                <div id="driverBenefits" class="hidden animate-fade-in">
                    <h3 class="font-bold text-2xl mb-4 text-secondary">Drive Your Way to Success</h3>
                    <p class="text-muted mb-6 text-lg">Be the face of our premium logistics. Safe, efficient, and rewarding driving opportunities.</p>
                    <ul class="grid grid-cols-1 md:grid-cols-2 gap-4 list-none p-0">
                        <li class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-success"></i> Dynamic Routing Technology</li>
                        <li class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-success"></i> Vehicle Maintenance Support</li>
                        <li class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-success"></i> Safety-First Protocols</li>
                        <li class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-success"></i> Referral Rewards Program</li>
                    </ul>
                </div>
            </div>

            <div class="card p-8">
                <form method="POST" enctype="multipart/form-data" id="careerForm">
                    <h3 class="font-bold text-xl mb-6">Application Form</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="John Doe">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" class="form-control" required placeholder="john@example.com">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="form-group">
                            <label class="form-label">Phone Number *</label>
                            <input type="tel" name="phone" class="form-control" required placeholder="+1 (555) 000-0000">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Applying For *</label>
                            <select name="role" id="roleSelect" class="form-control" required>
                                <option value="">Select Role</option>
                                <option value="driver">Driver</option>
                                <option value="mechanic">Mechanic</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group mb-6">
                        <label class="form-label">Create Password *</label>
                        <input type="password" name="password" class="form-control" required placeholder="Min. 6 characters">
                        <p class="text-xs text-muted mt-1">This will be your login password if accepted.</p>
                    </div>

                    <h4 class="font-bold text-lg mb-4 mt-8">Required Documents</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                         <div class="form-group">
                            <label class="form-label">Profile Image *</label>
                            <input type="file" name="profile_image" class="form-control" required accept="image/*">
                        </div>
                        <div class="form-group">
                            <label class="form-label">ID Proof (NID/Passport) *</label>
                            <input type="file" name="id_proof" class="form-control" required accept=".pdf,image/*">
                        </div>
                    </div>

                    <div id="mechanicFields" class="hidden">
                        <div class="form-group mb-6">
                            <label class="form-label">Resume / Experience Proof *</label>
                            <input type="file" name="resume" class="form-control" accept=".pdf,.doc,.docx,image/*">
                        </div>
                    </div>

                    <div id="driverFields" class="hidden">
                        <div class="form-group mb-6">
                            <label class="form-label">Driving License *</label>
                            <input type="file" name="license" class="form-control" accept=".pdf,image/*">
                        </div>
                    </div>

                    <h4 class="font-bold text-lg mb-4 mt-8">Qualifications</h4>

                    <div class="form-group mb-6">
                        <label class="form-label">Years of Experience</label>
                        <input type="number" name="experience" class="form-control" min="0" value="0">
                    </div>

                    <div class="form-group mb-6">
                        <label class="form-label">Skills, Certifications & Details</label>
                        <textarea name="qualifications" class="form-control" rows="5" placeholder="Tell us about your driving license type, mechanic certifications (ASE), or past work experience..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-full py-3 text-lg">Submit Application</button>
                </form>
            </div>
            
            <script>
                const roleSelect = document.getElementById('roleSelect');
                const mechanicFields = document.getElementById('mechanicFields');
                const driverFields = document.getElementById('driverFields');
                const resumeInput = document.querySelector('input[name="resume"]');
                const licenseInput = document.querySelector('input[name="license"]');
                
                const defaultBenefits = document.getElementById('defaultBenefits');
                const mechanicBenefits = document.getElementById('mechanicBenefits');
                const driverBenefits = document.getElementById('driverBenefits');

                roleSelect.addEventListener('change', function() {
                    mechanicFields.classList.add('hidden');
                    driverFields.classList.add('hidden');
                    resumeInput.required = false;
                    licenseInput.required = false;
                    
                    defaultBenefits.classList.add('hidden');
                    mechanicBenefits.classList.add('hidden');
                    driverBenefits.classList.add('hidden');

                    if (this.value === 'mechanic') {
                        mechanicFields.classList.remove('hidden');
                        resumeInput.required = true;
                        mechanicBenefits.classList.remove('hidden');
                    } else if (this.value === 'driver') {
                        driverFields.classList.remove('hidden');
                        licenseInput.required = true;
                        driverBenefits.classList.remove('hidden');
                    } else {
                        defaultBenefits.classList.remove('hidden');
                    }
                });
            </script>
        <?php endif; ?>
    </div>

    <style>
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</body>
</html>
