<?php
session_start();
require_once '../config/config.php';
require_once '../config/notification_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    if ($method === 'GET') {
        // NEW CLASS METHODS
        $notifications = NotificationHelper::get($userId);
        $unreadCount = NotificationHelper::countUnread($userId);
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
    } 
    elseif ($method === 'POST') {
        $action = $input['action'] ?? '';
        
        if ($action === 'mark_read' && isset($input['id'])) {
            NotificationHelper::markRead($input['id'], $userId);
            echo json_encode(['success' => true]);
        } 
        elseif ($action === 'mark_all_read') {
            NotificationHelper::markAllRead($userId);
            echo json_encode(['success' => true]);
        } else {
            throw new Exception('Invalid action');
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>