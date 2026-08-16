<?php
require_once 'config/database.php';

// No session_start() here - it's already in database.php

if (!isLoggedIn() && !isAdminLoggedIn()) {
    echo json_encode(['success' => false]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get user ID from session
    $userId = $_SESSION['user_id'] ?? 0;
    
    if ($userId == 0) {
        echo json_encode(['success' => false]);
        exit();
    }
    
    $conn = getConnection();
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false]);
}
?>