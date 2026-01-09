<?php
/**
 * Database Diagnostic and Auto-Fix
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$results = [];
$autoFix = isset($_GET['fix']) && $_GET['fix'] === 'yes';

// Test 1: MySQL Connection
$results[] = ['test' => 'MySQL Connection', 'status' => 'testing'];
$conn = new mysqli('localhost', 'root', '');
if ($conn->connect_error) {
    $results[count($results)-1]['status'] = 'failed';
    $results[count($results)-1]['message'] = 'Connection failed: ' . $conn->connect_error;
} else {
    $results[count($results)-1]['status'] = 'passed';
    $results[count($results)-1]['message'] = 'Connected successfully';
}

// Test 2: Database Exists
$results[] = ['test' => 'Database Exists', 'status' => 'testing'];
$dbExists = $conn->select_db('autocare_connect');
if (!$dbExists) {
    if ($autoFix) {
        $conn->query("CREATE DATABASE autocare_connect");
        $conn->select_db('autocare_connect');
        $results[count($results)-1]['status'] = 'fixed';
        $results[count($results)-1]['message'] = 'Database created';
    } else {
        $results[count($results)-1]['status'] = 'failed';
        $results[count($results)-1]['message'] = 'Database does not exist';
    }
} else {
    $results[count($results)-1]['status'] = 'passed';
    $results[count($results)-1]['message'] = 'Database exists';
}

$conn->select_db('autocare_connect');

// Test 3: Users Table
$results[] = ['test' => 'Users Table', 'status' => 'testing'];
$tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
    if ($autoFix) {
        $createUsers = $conn->query("CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            phone VARCHAR(20),
            password VARCHAR(255),
            role ENUM('customer', 'mechanic', 'admin') DEFAULT 'customer',
            google_id VARCHAR(100) UNIQUE,
            profile_image VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $results[count($results)-1]['status'] = $createUsers ? 'fixed' : 'failed';
        $results[count($results)-1]['message'] = $createUsers ? 'Users table created' : 'Failed to create users table';
    } else {
        $results[count($results)-1]['status'] = 'failed';
        $results[count($results)-1]['message'] = 'Users table does not exist';
    }
} else {
    $results[count($results)-1]['status'] = 'passed';
    $results[count($results)-1]['message'] = 'Users table exists';
}

// Test 4: Check Users Table Structure
$results[] = ['test' => 'Users Table Structure', 'status' => 'testing'];
$columns = $conn->query("SHOW COLUMNS FROM users");
$hasName = false;
$hasEmail = false;
$hasGoogleId = false;
if ($columns) {
    while ($col = $columns->fetch_assoc()) {
        if ($col['Field'] === 'name') $hasName = true;
        if ($col['Field'] === 'email') $hasEmail = true;
        if ($col['Field'] === 'google_id') $hasGoogleId = true;
    }
}

if ($hasName && $hasEmail && $hasGoogleId) {
    $results[count($results)-1]['status'] = 'passed';
    $results[count($results)-1]['message'] = 'All required columns exist';
} else {
    $results[count($results)-1]['status'] = 'failed';
    $missing = [];
    if (!$hasName) $missing[] = 'name';
    if (!$hasEmail) $missing[] = 'email';
    if (!$hasGoogleId) $missing[] = 'google_id';
    $results[count($results)-1]['message'] = 'Missing columns: ' . implode(', ', $missing);
}

$conn->close();

// Count results
$passed = 0;
$failed = 0;
$fixed = 0;
foreach ($results as $r) {
    if ($r['status'] === 'passed') $passed++;
    if ($r['status'] === 'failed') $failed++;
    if ($r['status'] === 'fixed') $fixed++;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Diagnostic - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .diagnostic-container { max-width: 800px; margin: 50px auto; padding: 2rem; }
        .result-item { padding: 1rem; margin: 0.5rem 0; border-radius: 8px; border-left: 4px solid; }
        .result-passed { background: #F0FDF4; border-left-color: #10B981; color: #065F46; }
        .result-failed { background: #FEF2F2; border-left-color: #EF4444; color: #991B1B; }
        .result-fixed { background: #FFF7ED; border-left-color: #F59E0B; color: #92400E; }
        .summary { padding: 1.5rem; background: white; border-radius: 8px; margin-bottom: 2rem; }
    </style>
</head>
<body>
    <div class="diagnostic-container">
        <h1 class="text-3xl font-bold mb-4">Database Diagnostic</h1>
        
        <div class="summary">
            <h3 class="font-bold mb-2">Summary:</h3>
            <p>✅ Passed: <?php echo $passed; ?> | ❌ Failed: <?php echo $failed; ?> | 🔧 Fixed: <?php echo $fixed; ?></p>
        </div>

        <?php foreach ($results as $result): ?>
            <div class="result-item result-<?php echo $result['status']; ?>">
                <strong><?php echo $result['test']; ?>:</strong>
                <?php if ($result['status'] === 'passed'): ?>
                    <i class="fa-solid fa-check"></i>
                <?php elseif ($result['status'] === 'failed'): ?>
                    <i class="fa-solid fa-times"></i>
                <?php else: ?>
                    <i class="fa-solid fa-wrench"></i>
                <?php endif; ?>
                <?php echo $result['message']; ?>
            </div>
        <?php endforeach; ?>

        <div class="mt-6">
            <?php if ($failed > 0 && !$autoFix): ?>
                <a href="?fix=yes" class="btn btn-primary">
                    <i class="fa-solid fa-tools"></i> Auto-Fix All Issues
                </a>
            <?php elseif ($failed === 0 || $fixed > 0): ?>
                <div class="alert alert-success mb-4">
                    ✅ All checks passed! Database is ready.
                </div>
                <a href="login.php" class="btn btn-primary">
                    <i class="fa-solid fa-arrow-right"></i> Go to Login & Test Google Sign-In
                </a>
            <?php endif; ?>
            
            <a href="?" class="btn btn-outline ml-2">
                <i class="fa-solid fa-rotate"></i> Run Check Again
            </a>
        </div>
    </div>
</body>
</html>
