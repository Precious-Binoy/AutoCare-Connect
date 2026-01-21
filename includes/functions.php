<?php
/**
 * Utility Functions
 */

/**
 * Sanitize user input
 * @param string $data
 * @return string
 */
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Format date for display
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = 'M d, Y') {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    return date($format, strtotime($date));
}

/**
 * Format datetime for display
 * @param string $datetime
 * @param string $format
 * @return string
 */
function formatDateTime($datetime, $format = 'M d, Y h:i A') {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    return date($format, strtotime($datetime));
}

/**
 * Get status badge CSS class
 * @param string $status
 * @return string
 */
function getStatusBadgeClass($status) {
    $classes = [
        'pending' => 'badge-warning',
        'confirmed' => 'badge-info',
        'in_progress' => 'badge-info',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger',
        'scheduled' => 'badge-info',
        'in_transit' => 'badge-primary',
        'ready_for_delivery' => 'badge-warning',
        'delivered' => 'badge-success',
        'repaired' => 'badge-success'
    ];
    
    return $classes[$status] ?? 'badge-secondary';
}

/**
 * Format status label for display
 * @param string $status
 * @return string
 */
function formatStatusLabel($status) {
    if ($status === 'delivered') return 'Completed';
    return ucfirst(str_replace('_', ' ', $status));
}

/**
 * Send JSON response and exit
 * @param array $data
 * @param int $statusCode
 */
function sendJsonResponse($data, $statusCode = 200) {
    // Clean any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Generate random string
 * @param int $length
 * @return string
 */
function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Upload file
 * @param array $file $_FILES array element
 * @param string $uploadDir
 * @param array $allowedTypes
 * @return array ['success' => bool, 'message' => string, 'filename' => string]
 */
function uploadFile($file, $uploadDir = 'uploads/', $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf']) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file upload'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload failed with error code: ' . $file['error']];
    }
    
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB
        return ['success' => false, 'message' => 'File too large. Maximum size is 5MB'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowedTypes)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $destination = $uploadDir . $filename;
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
    
    return [
        'success' => true,
        'message' => 'File uploaded successfully',
        'filename' => $filename,
        'path' => $destination
    ];
}

/**
 * Get vehicle image based on type
 * @param string $type
 * @return string
 */
function getVehicleImage($type) {
    $images = [
        'sedan' => 'https://images.unsplash.com/photo-1550355291-bbee04a92027?auto=format&fit=crop&w=800&q=80',
        'suv' => 'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?auto=format&fit=crop&w=800&q=80',
        'truck' => 'https://images.unsplash.com/photo-1586191582056-94033be1556a?auto=format&fit=crop&w=800&q=80',
        'coupe' => 'https://images.unsplash.com/photo-1542281286-9e0a16bb7366?auto=format&fit=crop&w=800&q=80',
        'hatchback' => 'https://images.unsplash.com/photo-1592198084033-aade902d1aae?auto=format&fit=crop&w=800&q=80',
        'van' => 'https://images.unsplash.com/photo-1532938421977-4ad7130e82f1?auto=format&fit=crop&w=800&q=80'
    ];
    
    return $images[strtolower($type)] ?? 'https://images.unsplash.com/photo-1494976388531-d1058494cdd8?auto=format&fit=crop&w=800&q=80';
}
?>
