<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Note: All basic functions (sanitize, validateEmail, isLoggedIn, etc.) 
// are already defined in config/database.php
// Only keep functions that are NOT in database.php or need global $conn

// Get statistics for dashboard (uses global $conn from database.php)
function getUserStats($user_id) {
    global $conn;
    $stats = [];
    $queries = [
        'total' => "SELECT COUNT(*) as count FROM complaints WHERE user_id = $user_id",
        'pending' => "SELECT COUNT(*) as count FROM complaints WHERE user_id = $user_id AND status = 'Pending'",
        'progress' => "SELECT COUNT(*) as count FROM complaints WHERE user_id = $user_id AND status = 'In Progress'",
        'resolved' => "SELECT COUNT(*) as count FROM complaints WHERE user_id = $user_id AND status = 'Resolved'"
    ];
    
    foreach ($queries as $key => $query) {
        $result = $conn->query($query);
        $stats[$key] = $result ? $result->fetch_assoc()['count'] : 0;
    }
    return $stats;
}

// Auto-assign priority and suspicious flag based on description
function analyzeComplaint($description) {
    $description_lower = strtolower($description);
    $suspicious_keywords = ['otp', 'bank', 'fraud', 'scam', 'password', 'credit card', 'debit card', 'upi', 'phishing', 'hacking'];
    $high_priority_keywords = ['bank', 'otp', 'credit card', 'debit card', 'net banking', 'password'];
    $medium_priority_keywords = ['fraud', 'scam', 'phishing', 'identity theft', 'hacking'];
    
    $is_suspicious = 0;
    foreach ($suspicious_keywords as $keyword) {
        if (strpos($description_lower, $keyword) !== false) {
            $is_suspicious = 1;
            break;
        }
    }
    
    $priority = 'Low';
    foreach ($high_priority_keywords as $keyword) {
        if (strpos($description_lower, $keyword) !== false) {
            $priority = 'High';
            break;
        }
    }
    if ($priority == 'Low') {
        foreach ($medium_priority_keywords as $keyword) {
            if (strpos($description_lower, $keyword) !== false) {
                $priority = 'Medium';
                break;
            }
        }
    }
    
    return ['priority' => $priority, 'is_suspicious' => $is_suspicious];
}

// Upload file function
function uploadFile($file, $complaint_id) {
    $upload_dir = __DIR__ . '/../uploads/';
    
    // Create uploads directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Check if directory is writable
    if (!is_writable($upload_dir)) {
        return ['success' => false, 'message' => 'Upload directory is not writable'];
    }
    
    // Check if file was uploaded without errors
    if ($file['error'] != UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error'];
    }
    
    // Validate file extension
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowed)];
    }
    
    // Validate file size (5MB max)
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File too large. Max 5MB'];
    }
    
    // Generate unique filename
    $filename = 'complaint_' . $complaint_id . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        chmod($filepath, 0644);
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'message' => 'Failed to save uploaded file'];
}

// Log admin activity (uses global $conn)
function logAdminActivity($admin_id, $action, $complaint_id = null) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, complaint_id) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $admin_id, $action, $complaint_id);
    return $stmt->execute();
}

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>