<?php
require_once '../config/config.php';
require_once '../config/notification_helper.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = get_logged_in_user();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $db = getDB();
        
        $session_reminders_query = "
            SELECT 
                CONCAT('session_', sr.id) as id,
                'session_reminder' as type,
                'Study Session Reminder' as title,
                CONCAT('You have a study session with ', COALESCE(u2.first_name, 'Your partner'), ' for ', m.subject) as message,
                m.subject,
                s.session_date,
                s.start_time,
                s.end_time,
                CASE 
                    WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
                    ELSE CONCAT(u1.first_name, ' ', u1.last_name)
                END as partner_name,
                sr.reminder_time as created_at,
                0 as is_read,
                '/sessions/history.php' as link
            FROM session_reminders sr
            JOIN sessions s ON sr.session_id = s.id
            JOIN matches m ON s.match_id = m.id
            LEFT JOIN users u1 ON m.student_id = u1.id
            LEFT JOIN users u2 ON m.mentor_id = u2.id
            WHERE sr.user_id = ?
            AND sr.is_sent = FALSE
            AND sr.reminder_time <= NOW()
            ORDER BY sr.reminder_time DESC
            LIMIT 5
        ";
        
        $stmt = $db->prepare($session_reminders_query);
        $stmt->execute([$user['id'], $user['id']]);
        $session_reminders = $stmt->fetchAll();
        
        $notifications = get_recent_notifications($user['id'], 20);
        $unread_count = get_unread_count($user['id']);
        $unread_messages = get_recent_unread_messages($user['id'], 5);
        $unread_messages_count = get_unread_messages_count($user['id']);
        $announcements = get_recent_announcements($user['id'], 5);
        
        $combined_notifications = [];
        
        foreach ($session_reminders as $reminder) {
            $combined_notifications[] = $reminder;
        }
        
        foreach ($announcements as $announcement) {
            $combined_notifications[] = [
                'id' => 'announce_' . $announcement['id'],
                'type' => 'announcement',
                'title' => $announcement['title'],
                'message' => $announcement['message'],
                'announcement_type' => $announcement['type'],
                'created_by' => $announcement['first_name'] . ' ' . $announcement['last_name'],
                'created_at' => $announcement['created_at'],
                'is_read' => false,
                'link' => '/admin/announcements.php'
            ];
        }
        
        foreach ($unread_messages as $msg) {
            $combined_notifications[] = [
                'id' => 'msg_' . (isset($msg['id']) ? $msg['id'] : 0),
                'type' => 'message',
                'title' => 'New Message',
                'message' => isset($msg['message']) ? $msg['message'] : 'You have a new message',
                'sender_name' => (isset($msg['first_name']) ? $msg['first_name'] : '') . ' ' . (isset($msg['last_name']) ? $msg['last_name'] : ''),
                'sender_id' => isset($msg['sender_id']) ? $msg['sender_id'] : 0,
                'profile_picture' => isset($msg['profile_picture']) ? $msg['profile_picture'] : null,
                'created_at' => isset($msg['created_at']) ? $msg['created_at'] : date('Y-m-d H:i:s'),
                'is_read' => false,
                'link' => '/messages/chat.php?match_id=' . (isset($msg['match_id']) ? $msg['match_id'] : 0)
            ];
        }
        
        // Add other notifications
        foreach ($notifications as $notif) {
            $combined_notifications[] = $notif;
        }
        
        // Sort by created_at descending
        usort($combined_notifications, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        $announcement_count = count($announcements);
        $session_reminder_count = count($session_reminders);
        
        echo json_encode([
            'notifications' => $combined_notifications,
            'unread_count' => $unread_count + $unread_messages_count + $announcement_count + $session_reminder_count
        ]);
        break;
        
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['action'])) {
            switch ($input['action']) {
                case 'mark_read':
                    if (isset($input['notification_id'])) {
                        $notif_id = $input['notification_id'];
                        
                        if (str_starts_with($notif_id, 'session_')) {
                            $session_reminder_id = str_replace('session_', '', $notif_id);
                            $db = getDB();
                            $stmt = $db->prepare("UPDATE session_reminders SET is_sent = TRUE, sent_at = NOW() WHERE id = ?");
                            $stmt->execute([$session_reminder_id]);
                        } elseif (!str_starts_with($notif_id, 'announce_') && !str_starts_with($notif_id, 'msg_')) {
                            mark_notification_read($notif_id, $user['id']);
                        }
                        
                        echo json_encode(['status' => 'success']);
                    } else {
                        echo json_encode(['error' => 'Missing notification_id']);
                    }
                    break;
                    
                case 'mark_all_read':
                    mark_all_notifications_read($user['id']);
                    echo json_encode(['status' => 'success']);
                    break;
                    
                default:
                    echo json_encode(['error' => 'Invalid action']);
            }
        } else {
            echo json_encode(['error' => 'Missing action']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
