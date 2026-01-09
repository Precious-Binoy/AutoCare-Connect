<?php
// api/create_user.php — mock registration endpoint
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');
$password = $data['password'] ?? '';
$confirm = $data['confirm_password'] ?? '';

$errors = [];
if($name === '') $errors[] = 'Name required';
if($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required';
if($password === '') $errors[] = 'Password required';
if($password !== $confirm) $errors[] = 'Passwords do not match';

// Simulate existing user when email contains "exists" for testing
if(stripos($email, 'exists') !== false) {
    echo json_encode(['success' => false, 'message' => 'Email already registered']);
    exit;
}

if(!empty($errors)){
    echo json_encode(['success' => false, 'message' => implode('; ', $errors)]);
    exit;
}

// In a real app: hash password, save to DB, send verification email, etc.

// Simulate created user
echo json_encode([
    'success' => true,
    'user' => [
        'id' => rand(1000,9999),
        'name' => $name,
        'email' => $email,
        'phone' => $phone
    ]
]);

?>
