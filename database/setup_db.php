<?php
/**
 * Simple Database Initialization Script
 * Run this file once to create the database and tables
 */

$conn = new mysqli('localhost', 'root', '');

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]));
}

// Create database
$conn->query("CREATE DATABASE IF NOT EXISTS autocare_connect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db('autocare_connect');

// Create users table
$conn->query("CREATE TABLE IF NOT EXISTS users (
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
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_email (email),
    INDEX idx_google_id (google_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Create password_resets table
$conn->query("CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    INDEX idx_token (token),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Create vehicles table
$conn->query("CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    make VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    year INT NOT NULL,
    color VARCHAR(30),
    license_plate VARCHAR(20) NOT NULL UNIQUE,
    vin VARCHAR(17),
    type ENUM('sedan', 'suv', 'truck', 'van', 'coupe', 'hatchback', 'pickup', 'other') DEFAULT 'sedan',
    mileage INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_license_plate (license_plate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Create mechanics table
$conn->query("CREATE TABLE IF NOT EXISTS mechanics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    specialization VARCHAR(100),
    certification VARCHAR(100),
    years_experience INT,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Create bookings table
$conn->query("CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_number VARCHAR(20) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    mechanic_id INT,
    service_type VARCHAR(100) NOT NULL,
    service_category ENUM('maintenance', 'repair', 'diagnostic', 'inspection', 'other') DEFAULT 'maintenance',
    description TEXT,
    preferred_date DATETIME NOT NULL,
    scheduled_date DATETIME,
    completion_date DATETIME,
    status ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    estimated_cost DECIMAL(10, 2),
    final_cost DECIMAL(10, 2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (mechanic_id) REFERENCES mechanics(id) ON DELETE SET NULL,
    INDEX idx_booking_number (booking_number),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Create service_updates table
$conn->query("CREATE TABLE IF NOT EXISTS service_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    progress_percentage INT DEFAULT 0,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_booking_id (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Create pickup_delivery table
$conn->query("CREATE TABLE IF NOT EXISTS pickup_delivery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    type ENUM('pickup', 'delivery') NOT NULL,
    address TEXT NOT NULL,
    city VARCHAR(50),
    state VARCHAR(50),
    zip_code VARCHAR(10),
    contact_phone VARCHAR(20),
    scheduled_time DATETIME,
    actual_time DATETIME,
    status ENUM('pending', 'scheduled', 'in_transit', 'completed', 'cancelled') DEFAULT 'pending',
    driver_name VARCHAR(100),
    driver_phone VARCHAR(20),
    vehicle_number VARCHAR(20),
    current_location VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_booking_id (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Create notifications table
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('booking', 'service', 'pickup', 'delivery', 'general') DEFAULT 'general',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Add sample data
$checkUser = $conn->query("SELECT COUNT(*) as count FROM users");
if ($checkUser && $checkUser->fetch_assoc()['count'] == 0) {
    // Add users
    $users = [
        ['John Doe', 'john@example.com', '+1 (555) 012-3456', password_hash('password123', PASSWORD_DEFAULT), 'customer', null],
        ['Admin User', 'admin@autocare.com', '+1 (555) 999-0000', password_hash('admin123', PASSWORD_DEFAULT), 'admin', null],
        ['Mike Mechanic', 'mike@autocare.com', '+1 (555) 555-1234', password_hash('mechanic123', PASSWORD_DEFAULT), 'mechanic', null],
    ];
    
    $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, google_id) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($users as $user) {
        $stmt->bind_param('ssssss', $user[0], $user[1], $user[2], $user[3], $user[4], $user[5]);
        $stmt->execute();
    }
    $stmt->close();
    
    // Get user IDs
    $john = $conn->query("SELECT id FROM users WHERE email = 'john@example.com'")->fetch_assoc()['id'];
    $mike_user = $conn->query("SELECT id FROM users WHERE email = 'mike@autocare.com'")->fetch_assoc()['id'];
    
    // Add mechanic
    $conn->query("INSERT INTO mechanics (user_id, specialization, certification, years_experience) VALUES ($mike_user, 'General Repair', 'ASE Certified', 8)");
    $mechanic_id = $conn->insert_id;
    
    // Add vehicles
    $stmt = $conn->prepare("INSERT INTO vehicles (user_id, make, model, year, color, license_plate, vin, type, mileage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $vehicles = [
        [$john, 'Toyota', 'Camry', 2019, 'Silver', 'ABC-1234', '1HGBH41JXMN109186', 'sedan', 45000],
        [$john, 'Ford', 'Ranger', 2021, 'Blue', 'XYZ-9876', '1FTYR14D17PA12345', 'pickup', 20000],
    ];
    foreach ($vehicles as $v) {
        $stmt->bind_param('issississi', $v[0], $v[1], $v[2], $v[3], $v[4], $v[5], $v[6], $v[7], $v[8]);
        $stmt->execute();
    }
    $stmt->close();
    
    // Get vehicle ID
    $toyota = $conn->query("SELECT id FROM vehicles WHERE license_plate = 'ABC-1234'")->fetch_assoc()['id'];
    
    // Add booking
    $booking_num = 'BK-' . strtoupper(substr(md5(uniqid()), 0, 8));
    $conn->query("INSERT INTO bookings (booking_number, user_id, vehicle_id, mechanic_id, service_type, service_category, description, preferred_date, scheduled_date, status, estimated_cost) 
                  VALUES ('$booking_num', $john, $toyota, $mechanic_id, 'Oil Change', 'maintenance', 'Regular oil change', '2024-10-25 10:00:00', '2024-10-25 10:00:00', 'in_progress', 89.99)");
    $booking_id = $conn->insert_id;
    
    // Add service updates
    $conn->query("INSERT INTO service_updates (booking_id, status, message, progress_percentage) VALUES ($booking_id, 'Booking Created', 'Service booking created', 0)");
    $conn->query("INSERT INTO service_updates (booking_id, status, message, progress_percentage) VALUES ($booking_id, 'In Progress', 'Vehicle checked in', 25)");
}

echo json_encode(['success' => true, 'message' => 'Database and sample data created successfully!']);
$conn->close();
?>
