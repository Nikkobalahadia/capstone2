<?php
require_once '../config/config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = get_logged_in_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

switch ($method) {
    case 'GET':
        // Get messages for a match
        $match_id = isset($_GET['match_id']) ? (int)$_GET['match_id'] : 0;
        $since = isset($_GET['since']) ? $_GET['since'] : null;
        
        if (!$match_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Match ID required']);
            exit;
        }
        
        // Verify user is part of this match
        $match_stmt = $db->prepare("SELECT id FROM matches WHERE id = ? AND (student_id = ? OR mentor_id = ?) AND status = 'accepted'");
        $match_stmt->execute([$match_id, $user['id'], $user['id']]);
        if (!$match_stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }
        
        // Build query
        $query = "
            SELECT m.*, u.first_name, u.last_name 
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.match_id = ?
        ";
        $params = [$match_id];
        
        if ($since) {
            $query .= " AND m.created_at > ?";
            $params[] = $since;
        }
        
        $query .= " ORDER BY m.created_at ASC LIMIT 50";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $messages = $stmt->fetchAll();
        
        // Mark messages as read
        $read_stmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE match_id = ? AND sender_id != ?");
        $read_stmt->execute([$match_id, $user['id']]);
        
        echo json_encode(['messages' => $messages]);
        break;
        
    case 'POST':
        // Send a new message
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['match_id']) || !isset($input['message'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Match ID and message required']);
            exit;
        }
        
        $match_id = (int)$input['match_id'];
        $message = trim($input['message']);
        
        if (empty($message)) {
            http_response_code(400);
            echo json_encode(['error' => 'Message cannot be empty']);
            exit;
        }
        
        // Verify user is part of this match
        $match_stmt = $db->prepare("SELECT id FROM matches WHERE id = ? AND (student_id = ? OR mentor_id = ?) AND status = 'accepted'");
        $match_stmt->execute([$match_id, $user['id'], $user['id']]);
        if (!$match_stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }
        
        try {
            $stmt = $db->prepare("INSERT INTO messages (match_id, sender_id, message) VALUES (?, ?, ?)");
            $stmt->execute([$match_id, $user['id'], $message]);
            $message_id = $db->lastInsertId();
            
            // Get the created message
            $get_stmt = $db->prepare("
                SELECT m.*, u.first_name, u.last_name 
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.id = ?
            ");
            $get_stmt->execute([$message_id]);
            $new_message = $get_stmt->fetch();
            
            echo json_encode(['success' => true, 'message' => $new_message]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to send message']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>
