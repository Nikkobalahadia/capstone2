<?php
/**
 * Notification Helper Functions
 * Creates and manages user notifications
 */

/**
 * Create a notification for a user
 */
function create_notification($user_id, $type, $title, $message, $link = null) {
    try {
        $db = getDB();
        
        $db->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                type VARCHAR(50) NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT,
                link VARCHAR(255),
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, type, title, message, link, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$user_id, $type, $title, $message, $link]);
        
        return $db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notification count for a user
 */
function get_unread_count($user_id) {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = ? AND is_read = FALSE
        ");
        
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error getting unread count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get recent notifications for a user
 */
function get_recent_notifications($user_id, $limit = 10) {
    try {
        $db = getDB();
        
        $limit = intval($limit);
        $stmt = $db->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT " . $limit
        );
        
        $stmt->execute([$user_id]);
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark notification as read
 */
function mark_notification_read($notification_id, $user_id) {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE id = ? AND user_id = ?
        ");
        
        $stmt->execute([$notification_id, $user_id]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notifications as read for a user
 */
function mark_all_notifications_read($user_id) {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE user_id = ? AND is_read = FALSE
        ");
        
        $stmt->execute([$user_id]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Get notification icon based on type
 */
function get_notification_icon($type) {
    $icons = [
        'session_scheduled' => 'fa-calendar-plus',
        'session_accepted' => 'fa-check-circle',
        'session_rejected' => 'fa-times-circle',
        'session_completed' => 'fa-check-double',
        'session_cancelled' => 'fa-ban',
        'match_request' => 'fa-handshake',
        'match_accepted' => 'fa-user-check',
        'match_rejected' => 'fa-user-times',
        'announcement' => 'fa-bullhorn',
        'commission_due' => 'fa-money-bill-wave',
        'commission_overdue' => 'fa-exclamation-triangle',
        'message' => 'fa-envelope',
        'report_resolved' => 'fa-flag-checkered',
        'account_warning' => 'fa-exclamation-circle',
        'account_suspended' => 'fa-user-lock',
    ];
    
    return $icons[$type] ?? 'fa-bell';
}

/**
 * Get notification color based on type
 */
function get_notification_color($type) {
    $colors = [
        'session_scheduled' => 'primary',
        'session_accepted' => 'success',
        'session_rejected' => 'danger',
        'session_completed' => 'success',
        'session_cancelled' => 'warning',
        'match_request' => 'info',
        'match_accepted' => 'success',
        'match_rejected' => 'danger',
        'announcement' => 'primary',
        'commission_due' => 'warning',
        'commission_overdue' => 'danger',
        'message' => 'info',
        'report_resolved' => 'success',
        'account_warning' => 'warning',
        'account_suspended' => 'danger',
    ];
    
    return $colors[$type] ?? 'secondary';
}

/**
 * Convert timestamp to relative time (e.g., "2 hours ago")
 */
function time_ago($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($diff / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}
