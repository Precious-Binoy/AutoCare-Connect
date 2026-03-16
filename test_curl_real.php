<?php
$url = "http://localhost/autocare-connect/ajax/get_notifications.php";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP Status: " . $httpcode . "\n";
echo "Result length: " . strlen($result) . "\n";
echo "Response snippet: " . substr($result, 0, 200) . "\n";
?>
