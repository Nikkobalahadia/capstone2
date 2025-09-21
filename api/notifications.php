<?php
require_once '../config/config.php';
require_once '../includes/hybrid_matchmaking.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = get_logged_in_user();
$db = getDB();
$hybridMatcher = new HybridMatchmakingEngine($db);
$onlineManager = new OnlineStatusManager($db);

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get real-time notifications
        $notifications = $hybridMatcher->getRealTimeNotifications($user['id']);
        echo json_encode(['notifications' => $notifications]);
        break;
        
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['action'])) {
            switch ($input['action']) {
                case 'heartbeat':
                    // Update user activity
                    $onlineManager->updateActivity($user['id']);
                    echo json_encode(['status' => 'success']);
                    break;
                    
                case 'mark_delivered':
                    if (isset($input['notification_id'])) {
                        $hybridMatcher->markNotificationDelivered($input['notification_id']);
                        echo json_encode(['status' => 'success']);
                    } else {
                        echo json_encode(['error' => 'Missing notification_id']);
                    }
                    break;
                    
                case 'mark_seen':
                    if (isset($input['notification_id'])) {
                        $hybridMatcher->markNotificationSeen($input['notification_id']);
                        echo json_encode(['status' => 'success']);
                    } else {
                        echo json_encode(['error' => 'Missing notification_id']);
                    }
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
?>
