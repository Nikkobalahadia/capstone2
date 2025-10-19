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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$match_id = isset($_POST['match_id']) ? (int)$_POST['match_id'] : 0;

if (!$match_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Match ID required']);
    exit;
}

// Verify user is part of this match
$db = getDB();
$match_stmt = $db->prepare("SELECT id FROM matches WHERE id = ? AND (student_id = ? OR mentor_id = ?) AND status = 'accepted'");
$match_stmt->execute([$match_id, $user['id'], $user['id']]);
if (!$match_stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['attachment'];

// Validate file size (max 10MB)
$maxSize = 10 * 1024 * 1024; // 10MB in bytes
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'File size exceeds 10MB limit']);
    exit;
}

// Allowed file types
$allowedTypes = [
    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf',
    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'text/plain',
    'application/zip', 'application/x-zip-compressed'
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'File type not allowed. Allowed types: images, PDF, Word, Excel, PowerPoint, text, ZIP']);
    exit;
}

// Create upload directory if it doesn't exist
$uploadDir = '../uploads/messages/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('msg_') . '_' . time() . '.' . $extension;
$filepath = $uploadDir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file']);
    exit;
}

// Save message with attachment to database
try {
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    
    $stmt = $db->prepare("
        INSERT INTO messages (match_id, sender_id, message, attachment_name, attachment_path, attachment_type, attachment_size) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $match_id,
        $user['id'],
        $message,
        $file['name'],
        'uploads/messages/' . $filename,
        $mimeType,
        $file['size']
    ]);
    
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
    
    // Log activity
    $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'file_uploaded', ?, ?)");
    $log_stmt->execute([$user['id'], json_encode(['match_id' => $match_id, 'filename' => $file['name']]), $_SERVER['REMOTE_ADDR']]);
    
    echo json_encode(['success' => true, 'message' => $new_message]);
    
} catch (Exception $e) {
    // Delete uploaded file if database insert fails
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save message']);
}
?>
