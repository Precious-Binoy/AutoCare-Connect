<?php
/**
 * SMTP Mail Configuration
 * 
 * Instructions for User:
 * 1. Open this file.
 * 2. Replace 'your_email@gmail.com' and 'your_app_password' with your actual credentials.
 * 3. Save the file.
 * 
 * For Gmail: Use App Password (https://myaccount.google.com/apppasswords), NOT your main password.
 */

// Prevent direct access
if (!defined('DB_HOST')) {
    // Fallback or exit if config not loaded from main app
}

// SMTP Credentials
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587); // or 465
define('SMTP_USERNAME', 'autocare.connect.system@gmail.com'); // Placeholder
define('SMTP_PASSWORD', 'xxxx xxxx xxxx xxxx'); // Placeholder
define('SMTP_FROM_EMAIL', 'no-reply@autocare-connect.com');
define('SMTP_FROM_NAME', 'AutoCare Connect');
?>
