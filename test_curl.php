<?php
$ch = curl_init("http://localhost/autocare-connect/ajax/get_notifications.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// We need to send the session cookie to act as user 20
// So I will just write a wrapper script to mimic it directly.
