<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'autocare_connect');

// Application Configuration
define('APP_NAME', 'AutoCare Connect');
define('APP_URL', 'http://localhost/autocare-connect');

// Session Configuration
define('SESSION_NAME', 'autocare_session');
define('SESSION_LIFETIME', 3600 * 24); // 24 hours

// Security
define('PASSWORD_MIN_LENGTH', 6);

// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', '498753295346-jnh8q2qlnrbdbjit9add5nmfef3cc2lr.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', ''); // Add your client secret here

// Maps Configuration
define('GOOGLE_MAPS_API_KEY', 'YOUR_GOOGLE_MAPS_API_KEY_HERE');

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
