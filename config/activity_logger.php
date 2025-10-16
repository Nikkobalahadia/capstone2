<?php

function log_activity($user_id, $action, $description = null) {
    try {
        $db = getDB();
        
        // Get IP address
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        
        // Get user agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$user_id, $action, $description, $ip_address, $user_agent]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}

// Common activity types
define('ACTIVITY_LOGIN', 'login');
define('ACTIVITY_LOGOUT', 'logout');
define('ACTIVITY_REGISTER', 'register');
define('ACTIVITY_PROFILE_UPDATE', 'profile_update');
define('ACTIVITY_MATCH_REQUEST', 'match_request');
define('ACTIVITY_MATCH_ACCEPT', 'match_accept');
define('ACTIVITY_MATCH_REJECT', 'match_reject');
define('ACTIVITY_SESSION_SCHEDULE', 'session_schedule');
define('ACTIVITY_SESSION_COMPLETE', 'session_complete');
define('ACTIVITY_SESSION_CANCEL', 'session_cancel');
define('ACTIVITY_MESSAGE_SENT', 'message_sent');
define('ACTIVITY_COMMISSION_SUBMIT', 'commission_submit');
define('ACTIVITY_COMMISSION_VERIFY', 'commission_verify');
define('ACTIVITY_ADMIN_ACTION', 'admin_action');
