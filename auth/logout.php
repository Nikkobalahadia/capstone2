<?php
require_once '../config/config.php';

if (is_logged_in()) {
    // Renamed get_current_user to get_logged_in_user
    $user = get_logged_in_user();
    
    // Log logout activity
    $db = getDB();
    $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'logout', ?, ?)");
    $log_stmt->execute([$user['id'], json_encode(['timestamp' => date('Y-m-d H:i:s')]), $_SERVER['REMOTE_ADDR']]);
}

// Destroy session
session_destroy();

// Redirect to home page
redirect('index.php');
?>
