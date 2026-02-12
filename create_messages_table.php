<?php
require_once 'config/db.php';

$conn = getDbConnection();

$sql = "CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    subject VARCHAR(255),
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'messages' created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}
?>
