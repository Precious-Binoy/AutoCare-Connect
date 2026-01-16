<?php
require_once __DIR__ . '/../config/db.php';

$conn = getDbConnection();

$sql = "ALTER TABLE job_requests 
        ADD COLUMN id_proof_path VARCHAR(255) AFTER qualifications,
        ADD COLUMN resume_path VARCHAR(255) AFTER id_proof_path,
        ADD COLUMN license_path VARCHAR(255) AFTER resume_path,
        ADD COLUMN profile_image_path VARCHAR(255) AFTER license_path";

if ($conn->query($sql)) {
    echo "Successfully added document columns to job_requests table.\n";
} else {
    echo "Error updating table: " . $conn->error . "\n";
}
?>
