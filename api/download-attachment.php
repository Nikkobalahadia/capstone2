<?php
require_once '../config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('../auth/login.php');
}

$user = get_logged_in_user();
if (!$user) {
    redirect('../auth/login.php');
}

$message_id = isset($_GET['message_id']) ? (int)$_GET['message_id'] : 0;

if (!$message_id) {
    die('Invalid message ID');
}

$db = getDB();

// Get message and verify user has access
$stmt = $db->prepare("
    SELECT m.*, ma.student_id, ma.mentor_id
    FROM messages m
    JOIN matches ma ON m.match_id = ma.id
    WHERE m.id = ? AND (ma.student_id = ? OR ma.mentor_id = ?)
");
$stmt->execute([$message_id, $user['id'], $user['id']]);
$message = $stmt->fetch();

if (!$message || !$message['attachment_path']) {
    die('File not found or access denied');
}

$filepath = '../' . $message['attachment_path'];

if (!file_exists($filepath)) {
    die('File not found on server');
}

// Set headers for file download
header('Content-Type: ' . $message['attachment_type']);
header('Content-Disposition: attachment; filename="' . $message['attachment_name'] . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');

// Output file
readfile($filepath);
exit;
?>
