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
        $notifications = get_recent_notifications($user['id'], 20);
        $unread_count = get_unread_count($user['id']);
        $unread_messages = get_recent_unread_messages($user['id'], 5);
        $unread_messages_count = get_unread_messages_count($user['id']);
        
        // Combine notifications with message notifications
        $combined_notifications = [];
        
        // Add message notifications
        foreach ($unread_messages as $msg) {
            $combined_notifications[] = [
                'id' => 'msg_' . $msg['id'],
                'type' => 'message',
                'title' => 'New Message',
                'message' => $msg['message'],
                'sender_name' => $msg['first_name'] . ' ' . $msg['last_name'],
                'sender_id' => $msg['sender_id'],
                'profile_picture' => $msg['profile_picture'],
                'created_at' => $msg['created_at'],
                'link' => '/messages/chat.php?match_id=' . $msg['match_id']
            ];
        }
        
        // Add other notifications
        foreach ($notifications as $notif) {
            $combined_notifications[] = $notif;
        }
        
        echo json_encode([
            'notifications' => $combined_notifications,
            'unread_count' => $unread_count + $unread_messages_count
        ]);
        break;
        
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['action'])) {
            switch ($input['action']) {
                case 'mark_read':
                    if (isset($input['notification_id'])) {
                        mark_notification_read($input['notification_id'], $user['id']);
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
