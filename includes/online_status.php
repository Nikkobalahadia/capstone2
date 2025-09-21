<?php

class OnlineStatusManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Set user as online
     */
    public function setUserOnline($user_id, $session_id = null) {
        $stmt = $this->db->prepare("
            INSERT INTO user_online_status (user_id, is_online, last_activity, session_id) 
            VALUES (?, TRUE, NOW(), ?)
            ON DUPLICATE KEY UPDATE 
                is_online = TRUE, 
                last_activity = NOW(), 
                session_id = ?
        ");
        return $stmt->execute([$user_id, $session_id, $session_id]);
    }
    
    /**
     * Set user as offline
     */
    public function setUserOffline($user_id) {
        $stmt = $this->db->prepare("
            UPDATE user_online_status 
            SET is_online = FALSE, last_activity = NOW() 
            WHERE user_id = ?
        ");
        return $stmt->execute([$user_id]);
    }
    
    /**
     * Check if user is online (active within last 5 minutes)
     */
    public function isUserOnline($user_id) {
        $stmt = $this->db->prepare("
            SELECT is_online, last_activity 
            FROM user_online_status 
            WHERE user_id = ? 
            AND last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        $stmt->execute([$user_id]);
        $status = $stmt->fetch();
        
        return $status && $status['is_online'];
    }
    
    /**
     * Update user activity (heartbeat)
     */
    public function updateActivity($user_id) {
        $stmt = $this->db->prepare("
            UPDATE user_online_status 
            SET last_activity = NOW() 
            WHERE user_id = ?
        ");
        return $stmt->execute([$user_id]);
    }
    
    /**
     * Get all online users
     */
    public function getOnlineUsers() {
        $stmt = $this->db->query("
            SELECT u.id, u.first_name, u.last_name, u.role, uos.last_activity
            FROM user_online_status uos
            JOIN users u ON uos.user_id = u.id
            WHERE uos.is_online = TRUE 
            AND uos.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ORDER BY uos.last_activity DESC
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Clean up offline users (run periodically)
     */
    public function cleanupOfflineUsers() {
        $stmt = $this->db->query("
            UPDATE user_online_status 
            SET is_online = FALSE 
            WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        return $stmt->rowCount();
    }
}

class NotificationManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Create notification for user
     */
    public function createNotification($user_id, $type, $title, $message, $data = null) {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, type, title, message, data) 
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $user_id, 
            $type, 
            $title, 
            $message, 
            $data ? json_encode($data) : null
        ]);
    }
    
    /**
     * Get unread notifications for user
     */
    public function getUnreadNotifications($user_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND is_read = FALSE 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notification_id, $user_id) {
        $stmt = $this->db->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE id = ? AND user_id = ?
        ");
        return $stmt->execute([$notification_id, $user_id]);
    }
    
    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead($user_id) {
        $stmt = $this->db->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE user_id = ?
        ");
        return $stmt->execute([$user_id]);
    }
}
?>
