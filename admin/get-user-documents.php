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

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

try {
    $db = getDB();
    
    // Get user's verification documents
    $stmt = $db->prepare("
        SELECT vd.*, 
               u.first_name, u.last_name,
               reviewer.first_name as reviewer_first_name,
               reviewer.last_name as reviewer_last_name
        FROM user_verification_documents vd
        LEFT JOIN users u ON vd.user_id = u.id
        LEFT JOIN users reviewer ON vd.reviewed_by = reviewer.id
        WHERE vd.user_id = ?
        ORDER BY vd.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'documents' => $documents
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching documents: ' . $e->getMessage()
    ]);
}
