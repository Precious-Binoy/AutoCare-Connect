<?php
require_once __DIR__ . '/config.php';

// Global database connection
$conn = null;

/**
 * Get database connection
 * @return mysqli
 */
function getDbConnection() {
    global $conn;
    
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            die(json_encode([
                'success' => false,
                'message' => 'Database connection failed: ' . $conn->connect_error
            ]));
        }
        
        $conn->set_charset('utf8mb4');
    }
    
    return $conn;
}

/**
 * Execute a prepared statement
 * @param string $query
 * @param array $params
 * @param string $types
 * @return mysqli_result|bool
 */
function executeQuery($query, $params = [], $types = '') {
    $conn = getDbConnection();
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("Query preparation failed: " . $conn->error . " -- Query: " . $query);

        // Fallback: if params are provided, safely interpolate them and run a normal query.
        // This helps in environments where prepare() may fail unexpectedly.
        if (!empty($params)) {
            $safeQuery = $query;
            foreach ($params as $i => $p) {
                $t = isset($types[$i]) ? $types[$i] : 's';
                if ($t === 'i' || $t === 'd') {
                    $val = $conn->real_escape_string((string)$p);
                    $replacement = $val;
                } else {
                    $val = $conn->real_escape_string((string)$p);
                    $replacement = "'" . $val . "'";
                }
                // Replace only the first occurrence of '?' each loop
                $safeQuery = preg_replace('/\?/', $replacement, $safeQuery, 1);
            }

            $res = $conn->query($safeQuery);
            if ($res === false) {
                error_log("Fallback query failed: " . $conn->error . " -- Query: " . $safeQuery);
                return false;
            }

            return $res;
        }

        return false;
    }
    
    if (!empty($params) && !empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $success = $stmt->execute();
    
    if (!$success) {
        error_log("Query execution failed: " . $stmt->error);
        return false;
    }
    
    $result = $stmt->get_result();
    $stmt->close();
    
    return $result !== false ? $result : true;
}

/**
 * Get last insert ID
 * @return int
 */
function getLastInsertId() {
    $conn = getDbConnection();
    return $conn->insert_id;
}

/**
 * Escape string for SQL
 * @param string $string
 * @return string
 */
function escapeString($string) {
    $conn = getDbConnection();
    return $conn->real_escape_string($string);
}

/**
 * Close database connection
 */
function closeDbConnection() {
    global $conn;
    if ($conn !== null) {
        $conn->close();
        $conn = null;
    }
}
?>
