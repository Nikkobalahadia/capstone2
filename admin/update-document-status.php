<?php
require_once '../config/config.php';

// Check if user is logged in and is admin
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = get_logged_in_user();
if (!$user || $user['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$document_id = isset($_POST['document_id']) ? (int)$_POST['document_id'] : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';
$rejection_reason = isset($_POST['rejection_reason']) ? sanitize_input($_POST['rejection_reason']) : null;

if (!$document_id || !in_array($status, ['approved', 'rejected', 'pending'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $db = getDB();
    
    try {
        $db->exec("ALTER TABLE user_verification_documents ADD COLUMN reviewed_at TIMESTAMP NULL AFTER reviewed_by");
    } catch (Exception $e) {
        // Column already exists, ignore error
    }
    
    // Get document info
    $doc_stmt = $db->prepare("SELECT user_id FROM user_verification_documents WHERE id = ?");
    $doc_stmt->execute([$document_id]);
    $doc = $doc_stmt->fetch();
    
    if (!$doc) {
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit;
    }
    
    // Update document status
    $stmt = $db->prepare("
        UPDATE user_verification_documents 
        SET status = ?, 
            reviewed_by = ?, 
            reviewed_at = NOW(),
            rejection_reason = ?
        WHERE id = ?
    ");
    $stmt->execute([$status, $user['id'], $rejection_reason, $document_id]);
    
    // If approved, check if user should be verified
    if ($status === 'approved') {
        // Count approved documents
        $count_stmt = $db->prepare("
            SELECT COUNT(*) as approved_count 
            FROM user_verification_documents 
            WHERE user_id = ? AND status = 'approved'
        ");
        $count_stmt->execute([$doc['user_id']]);
        $count = $count_stmt->fetch();
        
        // If user has 2+ approved documents, verify them
        if ($count['approved_count'] >= 2) {
            $verify_stmt = $db->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
            $verify_stmt->execute([$doc['user_id']]);
        }
    }
    
    // Log the action
    $log_stmt = $db->prepare("
        INSERT INTO user_activity_logs (user_id, action, details, ip_address) 
        VALUES (?, 'admin_review_document', ?, ?)
    ");
    $log_stmt->execute([
        $user['id'], 
        json_encode([
            'document_id' => $document_id,
            'status' => $status,
            'user_id' => $doc['user_id']
        ]), 
        $_SERVER['REMOTE_ADDR']
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Document status updated successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error updating document: ' . $e->getMessage()]);
}
