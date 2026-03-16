<?php
session_start();
$_SESSION['user_id'] = 20;
$_SESSION['user_role'] = 'mechanic';
// Temporarily include it
chdir('ajax');
include 'get_notifications.php';
?>
