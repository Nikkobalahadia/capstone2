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
        // Handle multipart/form-data (from FormData)
        if (!isset($_POST['match_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Match ID required']);
            exit;
        }

        $match_id = (int)$_POST['match_id'];
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
        $file_url = null;

        // START: UPDATED FIX - Server-side block for Google Meet AND Zoom
        if (preg_match('/(meet\.google\.com\/|zoom\.us\/(j|my)\/)/i', $message)) {
            http_response_code(400); // Bad Request
            echo json_encode([
                'error' => 'Sharing external meeting links is not allowed. Please use the official "Schedule Session" button to create a meeting. This ensures the session is tracked for commission.'
            ]);
            exit;
        }
        // END: UPDATED FIX

        // Handle file upload
        if (isset($_FILES['file'])) {
            $file = $_FILES['file'];
            
            if ($file['error'] === UPLOAD_ERR_OK) {
                // Validate file size (e.g., 5MB)
                $max_size = 5 * 1024 * 1024;
                if ($file['size'] > $max_size) {
                    http_response_code(400);
                    echo json_encode(['error' => 'File is too large. Max size is 5MB.']);
                    exit;
                }
                
                // Validate file type
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime_type = $finfo->file($file['tmp_name']);
                
                $allowed_types = [
                    'image/jpeg', 'image/png', 'image/gif',
                    'application/pdf',
                    'application/msword', // doc
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
                    'application/vnd.ms-excel', // xls
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
                    'application/vnd.ms-powerpoint', // ppt
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation', // pptx
                    'text/plain', // txt
                    'application/zip' // zip
                ];

                if (!in_array($mime_type, $allowed_types)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid file type.']);
                    exit;
                }

                $upload_dir = '../uploads/attachments/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $safe_filename = uniqid('file_', true) . '.' . htmlspecialchars($file_extension);
                $upload_path = $upload_dir . $safe_filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $file_url = 'uploads/attachments/' . $safe_filename; // Relative path to store in DB
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to move uploaded file.']);
                    exit;
                }
            } elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {
                http_response_code(400);
                echo json_encode(['error' => 'File upload error code: ' . $file['error']]);
                exit;
            }
        }
        
        // Check if message is empty AND no file is uploaded
        if (empty($message) && !$file_url) {
            http_response_code(400);
            echo json_encode(['error' => 'Message or file cannot be empty']);
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
            // Add file_url to INSERT query
            $stmt = $db->prepare("INSERT INTO messages (match_id, sender_id, message, file_url) VALUES (?, ?, ?, ?)");
            $stmt->execute([$match_id, $user['id'], $message, $file_url]);
            
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
            echo json_encode(['error' => 'Failed to send message: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
        break;
}
?>