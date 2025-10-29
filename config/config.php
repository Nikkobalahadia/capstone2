<?php
// config.php
// Application configuration
session_start();

// Define constants
define('BASE_URL', 'http://localhost/study-mentorship-platform/');
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Timezone setting - MUST be before any date operations
date_default_timezone_set('Asia/Manila');

// Include database configuration
require_once 'database.php';

require_once 'email.php';

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Helper functions
function sanitize_input($data) {
    if ($data === null) {
        return '';
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_logged_in_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    $db = getDB();
    // Fetch user by ID, regardless of active status (we will check it in PHP)
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
        // Check 1: Deactivated
        if (!$user['is_active']) {
            session_destroy();
            redirect('auth/login.php?error=Your+account+has+been+deactivated.');
            exit;
        }

        // Check 2: Suspended (The new logic)
        if ($user['account_status'] === 'suspended') {
            session_destroy();
            redirect('auth/login.php?error=Your+account+has+been+suspended+due+to+unpaid+commissions.');
            exit;
        }
    } else {
        // User not found in DB, destroy session
        session_destroy();
        return null;
    }
    
    return $user; // Return the user if they passed all checks
}

function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit();
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Helper function to get current Manila time
function get_manila_time() {
    $dt = new DateTime('now', new DateTimeZone('Asia/Manila'));
    return $dt->format('Y-m-d H:i:s');
}
?>