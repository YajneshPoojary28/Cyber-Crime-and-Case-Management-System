<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cybershield_db');

function getConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        die("Database connection error: " . $e->getMessage());
    }
}

// Session check functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function isOfficerLoggedIn() {
    return isset($_SESSION['officer_id']);
}

// Redirect functions
function redirectIfNotLoggedIn() {
    if (!isLoggedIn() && !isAdminLoggedIn() && !isOfficerLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function redirectIfNotAdmin() {
    if (!isAdminLoggedIn()) {
        header("Location: admin-login.php");
        exit();
    }
}

function redirectIfNotOfficer() {
    if (!isOfficerLoggedIn()) {
        header("Location: officer-login.php");
        exit();
    }
}

// Require functions (for pages that need authentication)
function requireLogin() {
    if (!isLoggedIn() && !isAdminLoggedIn() && !isOfficerLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header("Location: admin-login.php");
        exit();
    }
}

function requireOfficer() {
    if (!isOfficerLoggedIn()) {
        header("Location: officer-login.php");
        exit();
    }
}

function generateComplaintNumber() {
    $conn = getConnection();
    $year = date('Y');
    $result = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE complaint_num LIKE 'CYB-{$year}-%'");
    $row = $result->fetch_assoc();
    $count = $row['count'] + 1;
    $conn->close();
    return "CYB-{$year}-" . str_pad($count, 5, '0', STR_PAD_LEFT);
}

function addNotification($userId, $complaintNum, $title, $message) {
    $conn = getConnection();
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, complaint_num, title, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $complaintNum, $title, $message);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

function addActivityLog($adminId, $action, $ip) {
    $conn = getConnection();
    $stmt = $conn->prepare("INSERT INTO activity_logs (admin_id, action, ip_address) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $adminId, $action, $ip);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

function detectKeywords($text) {
    $keywords = ['otp', 'bank', 'credit card', 'upi', 'password', 'scam', 'fraud', 'phishing', 'aadhaar', 'pan', 'bitcoin', 'ransomware'];
    $found = [];
    $textLower = strtolower($text);
    foreach ($keywords as $kw) {
        if (strpos($textLower, $kw) !== false) {
            $found[] = $kw;
        }
    }
    return $found;
}

function autoPriority($description, $loss) {
    $high = ['bank', 'otp', 'credit card', 'upi'];
    $descLower = strtolower($description);
    
    if ($loss >= 50000) return 'critical';
    foreach ($high as $kw) {
        if (strpos($descLower, $kw) !== false) return 'high';
    }
    return 'medium';
}

// ============ OFFICER FUNCTIONS ============

// Assign complaint to officer
function assignComplaintToOfficer($complaint_id, $officer_id) {
    $conn = getConnection();
    $stmt = $conn->prepare("UPDATE complaints SET assigned_to = ?, assigned_date = NOW(), status = 'in-progress' WHERE id = ?");
    $stmt->bind_param("ii", $officer_id, $complaint_id);
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $result;
}

// Resolve complaint by officer
function resolveComplaint($complaint_id, $officer_id, $resolution_notes) {
    $conn = getConnection();
    
    $stmt = $conn->prepare("UPDATE complaints SET status = 'resolved', resolved_date = NOW(), resolution_notes = ?, admin_notified = 1 WHERE id = ? AND assigned_to = ?");
    $stmt->bind_param("sii", $resolution_notes, $complaint_id, $officer_id);
    $result = $stmt->execute();
    $stmt->close();
    
    if ($result) {
        // Update officer stats
        $update = $conn->prepare("UPDATE investigation_officers SET resolved_complaints = resolved_complaints + 1 WHERE id = ?");
        $update->bind_param("i", $officer_id);
        $update->execute();
        $update->close();
        
        // Notify admin (user_id = 1 is admin)
        $admin_message = "Complaint #$complaint_id has been resolved by Officer";
        $notify = $conn->prepare("INSERT INTO notifications (user_id, complaint_id, title, message, is_read, created_at) VALUES (1, ?, 'Complaint Resolved', ?, 0, NOW())");
        $notify->bind_param("is", $complaint_id, $admin_message);
        $notify->execute();
        $notify->close();
    }
    $conn->close();
    return $result;
}

// Get officer assigned complaints
function getOfficerComplaints($officer_id, $status = null) {
    $conn = getConnection();
    $sql = "SELECT c.*, u.name as citizen_name, u.email as citizen_email 
            FROM complaints c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.assigned_to = ?";
    if ($status) {
        $sql .= " AND c.status = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $officer_id, $status);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $officer_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    $conn->close();
    return $result;
}

// Get officer statistics
function getOfficerStats($officer_id) {
    $conn = getConnection();
    $stats = [];
    
    $total_query = $conn->prepare("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = ?");
    $total_query->bind_param("i", $officer_id);
    $total_query->execute();
    $stats['total'] = $total_query->get_result()->fetch_assoc()['count'];
    $total_query->close();
    
    $pending_query = $conn->prepare("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = ? AND status = 'in-progress'");
    $pending_query->bind_param("i", $officer_id);
    $pending_query->execute();
    $stats['pending'] = $pending_query->get_result()->fetch_assoc()['count'];
    $pending_query->close();
    
    $resolved_query = $conn->prepare("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = ? AND status = 'resolved'");
    $resolved_query->bind_param("i", $officer_id);
    $resolved_query->execute();
    $stats['resolved'] = $resolved_query->get_result()->fetch_assoc()['count'];
    $resolved_query->close();
    
    $conn->close();
    return $stats;
}

// Get all active officers
function getAllOfficers() {
    $conn = getConnection();
    $result = $conn->query("SELECT id, officer_id, full_name as name, email, rank, department, status FROM investigation_officers WHERE status = 'active'");
    $officers = [];
    while ($row = $result->fetch_assoc()) {
        $officers[] = $row;
    }
    $conn->close();
    return $officers;
}

// Get complaint by ID
function getComplaintById($id) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT c.*, u.name, u.email FROM complaints c JOIN users u ON c.user_id = u.id WHERE c.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $result;
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

// Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Get status badge class
function getStatusBadgeClass($status) {
    $classes = [
        'Pending' => 'warning',
        'In Progress' => 'info',
        'Resolved' => 'success',
        'pending' => 'warning',
        'in-progress' => 'info',
        'resolved' => 'success'
    ];
    return $classes[$status] ?? 'secondary';
}

// Get priority badge class
function getPriorityBadgeClass($priority) {
    $classes = [
        'Low' => 'success',
        'Medium' => 'warning',
        'High' => 'danger',
        'critical' => 'danger',
        'low' => 'success',
        'medium' => 'warning',
        'high' => 'danger'
    ];
    return $classes[$priority] ?? 'secondary';
}
?>