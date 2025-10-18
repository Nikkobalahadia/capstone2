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
        // Get recent notifications
        $notifications = get_recent_notifications($user['id'], 20);
        $unread_count = get_unread_count($user['id']);
        
        echo json_encode([
            'notifications' => $notifications,
            'unread_count' => $unread_count
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
