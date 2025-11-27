<?php
require_once __DIR__ . '/database.php';

/**
 * Notification Helper Class
 * usage: NotificationHelper::create($userId, 'message', 'New Message', 'Hello world', '/messages/123');
 */
class NotificationHelper {
    
    /**
     * Create a new notification
     */
    public static function create($userId, $type, $title, $message, $link = null, $data = []) {
        $db = getDB();
        try {
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, type, title, message, link, data, is_read, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
            ");
            
            $jsonData = !empty($data) ? json_encode($data) : null;
            
            return $stmt->execute([
                $userId, 
                $type, 
                $title, 
                $message, 
                $link, 
                $jsonData
            ]);
        } catch (PDOException $e) {
            error_log("Notification Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get notifications for a user
     */
    public static function get($userId, $limit = 20) {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get unread count
     */
    public static function countUnread($userId) {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }

    /**
     * Mark as read
     */
    public static function markRead($id, $userId) {
        $db = getDB();
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }

    /**
     * Mark ALL as read
     */
    public static function markAllRead($userId) {
        $db = getDB();
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }
}
?>