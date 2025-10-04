<?php
require_once '../config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user = get_logged_in_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Handle JSON requests
$input = json_decode(file_get_contents('php://input'), true);
$session_id = $input['session_id'] ?? 0;
$cancellation_reason = $input['cancellation_reason'] ?? 'No reason provided';
$admin_cancel = $input['admin_cancel'] ?? false;

if (!$session_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Session ID required']);
    exit;
}

$db = getDB();

try {
    // Verify user has permission to cancel this session
    if (!$admin_cancel) {
        $stmt = $db->prepare("
            SELECT s.id FROM sessions s
            JOIN matches m ON s.match_id = m.id
            WHERE s.id = ? AND (m.student_id = ? OR m.mentor_id = ?) AND s.status = 'scheduled'
        ");
        $stmt->execute([$session_id, $user['id'], $user['id']]);
        
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }
    } else {
        // Admin cancellation - verify admin role
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Admin access required']);
            exit;
        }
    }
    
    $stmt = $db->prepare("
        UPDATE sessions 
        SET status = 'cancelled', 
            cancellation_reason = ?, 
            cancelled_by = ?, 
            cancelled_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$cancellation_reason, $user['id'], $session_id]);
    
    // Log the cancellation activity
    $log_stmt = $db->prepare("
        INSERT INTO user_activity_logs (user_id, action, details, ip_address) 
        VALUES (?, 'session_cancelled', ?, ?)
    ");
    $log_stmt->execute([
        $user['id'], 
        json_encode([
            'session_id' => $session_id, 
            'reason' => $cancellation_reason,
            'admin_cancel' => $admin_cancel
        ]), 
        $_SERVER['REMOTE_ADDR']
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Session cancelled successfully']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to cancel session']);
}
?>
