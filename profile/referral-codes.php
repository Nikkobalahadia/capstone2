<?php
require_once '../config/config.php';
require_once '../config/notification_helper.php';

// Check if user is logged in and is a verified mentor or peer
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user || !in_array($user['role'], ['mentor', 'peer']) || !$user['is_verified']) {
    redirect('dashboard.php');
}

$unread_notifications = get_unread_count($user['id']);
$error = '';
$success = '';

// Handle referral code generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_code'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $max_uses = (int)$_POST['max_uses'];
        $expires_days = (int)$_POST['expires_days'];
        
        if ($max_uses < 1 || $max_uses > 50) {
            $error = 'Maximum uses must be between 1 and 50.';
        } elseif ($expires_days < 1 || $expires_days > 30) {
            $error = 'Expiration must be between 1 and 30 days.';
        } else {
            $db = getDB();
            
            // Generate unique referral code
            do {
                $prefix = strtoupper($user['role']);
                $code = $prefix . strtoupper(substr(uniqid(), -6));
                $check_stmt = $db->prepare("SELECT id FROM referral_codes WHERE code = ?");
                $check_stmt->execute([$code]);
            } while ($check_stmt->fetch());
            
            // Create referral code
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_days} days"));
            $stmt = $db->prepare("INSERT INTO referral_codes (code, created_by, max_uses, expires_at) VALUES (?, ?, ?, ?)");
            
            if ($stmt->execute([$code, $user['id'], $max_uses, $expires_at])) {
                $success = "Referral code '<strong>{$code}</strong>' generated successfully!";
            } else {
                $error = 'Failed to generate referral code. Please try again.';
            }
        }
    }
}

// Handle code deactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_code'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // NOTE: The form now sends 'deactivate_code' which contains the ID
        $code_id = (int)$_POST['deactivate_code'];
        $db = getDB();
        
        $stmt = $db->prepare("UPDATE referral_codes SET is_active = 0 WHERE id = ? AND created_by = ?");
        if ($stmt->execute([$code_id, $user['id']])) {
            $success = 'Referral code deactivated successfully.';
        } else {
            $error = 'Failed to deactivate referral code.';
        }
    }
}

// Get user's referral codes
$db = getDB();
$codes_stmt = $db->prepare("
    SELECT rc.*
    FROM referral_codes rc
    WHERE rc.created_by = ?
    ORDER BY rc.created_at DESC
");
$codes_stmt->execute([$user['id']]);
$referral_codes = $codes_stmt->fetchAll();

// Get referred users by checking user_activity_logs
$referred_stmt = $db->prepare("
    SELECT DISTINCT u.first_name, u.last_name, u.email, u.role, u.created_at, u.is_verified,
           ual.details
    FROM users u
    JOIN user_activity_logs ual ON u.id = ual.user_id
    WHERE ual.action = 'register'
    AND JSON_EXTRACT(ual.details, '$.referral_code') IN (
        SELECT code FROM referral_codes WHERE created_by = ?
    )
    ORDER BY u.created_at DESC
");
$referred_stmt->execute([$user['id']]);
$referred_users = $referred_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Referral Codes - Study Buddy</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary-color: #2563eb;
            --text-primary: #1a1a1a;
            --text-secondary: #666;
            --border-color: #e5e5e5;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.1);
            --bg-color: #fafafa;
            --card-bg: white;
        }

        /* UPDATED: Dark mode with new color variables */
        [data-theme="dark"] {
            --primary-color: #3b82f6;
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --border-color: #374151;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.3);
            --bg-color: #111827;
            --card-bg: #1f2937;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            overflow-x: hidden;
            width: 100%;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-color);
            color: var(--text-primary);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* ===== HEADER & NAVIGATION ===== */
        .header {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 60px;
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            height: 100%;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .hamburger {
            display: none;
            flex-direction: column;
            cursor: pointer;
            gap: 5px;
            background: none;
            border: none;
            padding: 0.5rem;
            z-index: 1001;
        }

        .hamburger span {
            width: 25px;
            height: 3px;
            background-color: var(--text-primary);
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(8px, 8px);
        }

        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }

        .logo {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
            white-space: nowrap;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
            margin: 0;
            padding: 0;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: var(--primary-color);
        }

        .notification-bell {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            cursor: pointer;
            border-radius: 8px;
            background: transparent;
            border: none;
            transition: background 0.2s;
            font-size: 1.1rem;
            color: var(--text-secondary);
        }

        .notification-bell:hover {
            background: var(--border-color);
            color: var(--primary-color);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 0.7rem;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
            border: 2px solid var(--card-bg);
        }

        .notification-dropdown {
            display: none;
            position: absolute;
            right: -10px;
            top: 100%;
            margin-top: 0.75rem;
            width: 380px;
            max-height: 450px;
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            overflow: hidden;
            flex-direction: column;
            border: 1px solid var(--border-color);
        }

        .notification-dropdown.show {
            display: flex;
        }

        .notification-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-list {
            max-height: 350px;
            overflow-y: auto;
        }

        .notification-item-dropdown {
            padding: 0.875rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background 0.15s;
            display: flex;
            gap: 0.75rem;
        }

        .notification-item-dropdown:hover {
            background: var(--border-color);
        }

        .notification-item-dropdown.unread {
            background: rgba(59, 130, 246, 0.1);
        }
        [data-theme="dark"] .notification-item-dropdown.unread {
            background: #374151;
        }

        .notification-footer {
            padding: 0.75rem;
            text-align: center;
            border-top: 1px solid var(--border-color);
        }

        .notification-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .profile-menu {
            position: relative;
        }

        .profile-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #1e40af 100%);
            color: white;
            cursor: pointer;
            font-size: 1.1rem;
            border: none;
            transition: transform 0.2s, box-shadow 0.2s;
            overflow: hidden;
        }

        .profile-icon:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .profile-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 0.5rem;
            width: 240px;
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            border: 1px solid var(--border-color);
        }

        .profile-dropdown.show {
            display: block;
        }

        .profile-dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            text-align: center;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
        }

        .user-role {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .profile-dropdown-menu {
            padding: 0.5rem 0;
        }

        .profile-dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            width: 100%;
            text-align: left;
            font-size: 0.9rem;
            background: transparent;
        }

        .profile-dropdown-item:hover {
            background: var(--border-color);
            color: var(--primary-color);
        }

        .profile-dropdown-item.logout {
            color: #dc2626;
        }

        .profile-dropdown-item.logout:hover {
            background: rgba(220, 38, 38, 0.1);
        }
        [data-theme="dark"] .profile-dropdown-item.logout:hover {
            background: #3f1212;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        main {
            padding: 2rem 0;
            margin-top: 60px;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header h1 i {
            color: var(--primary-color);
        }

        .page-subtitle {
            font-size: 0.95rem;
            color: var(--text-secondary);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 1rem;
            transition: gap 0.2s;
        }

        .back-link:hover {
            gap: 0.75rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            min-height: 44px;
            background: var(--primary-color);
            color: white;
        }

        .btn:hover {
            background: #1d4ed8;
        }
        
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            min-height: auto;
        }
        
        .btn-icon {
            padding: 0.5rem;
            width: 36px;
            height: 36px;
            font-size: 0.9rem;
            min-height: auto;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #1f2937;
        }
        .btn-secondary:hover {
            background: #d1d5db;
        }
        [data-theme="dark"] .btn-secondary {
            background: var(--border-color);
            color: var(--text-primary);
        }
        [data-theme="dark"] .btn-secondary:hover {
            background: #4b5563;
        }


        .card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .card-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-color);
        }
        [data-theme="dark"] .card-header {
             background: #1f2937;
             border-bottom: 1px solid #374151;
        }


        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.875rem;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            background: var(--card-bg);
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        [data-theme="dark"] .form-input:focus, 
        [data-theme="dark"] .form-select:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        [data-theme="dark"] .alert-error {
            background: #3f1212;
            color: #fca5a5;
            border-color: #dc2626;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        [data-theme="dark"] .alert-success {
            background: #062f1e;
            color: #a7f3d0;
            border-color: #16a34a;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .table-container {
            overflow-x: auto;
            border: 1px solid var(--border-color);
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            padding: 0.875rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-color);
        }
        [data-theme="dark"] th {
             background: #374151;
        }

        td {
            padding: 0.875rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.875rem;
            color: var(--text-primary);
        }
        
        tr:last-child td {
            border-bottom: none;
        }

        .code-badge {
            background: #eef2ff;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-weight: 600;
            font-family: 'Courier New', monospace;
            color: var(--primary-color);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        [data-theme="dark"] .code-badge {
            background: #3730a3;
            color: #e0e7ff;
        }


        .copy-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--primary-color);
            padding: 0.25rem;
            transition: transform 0.2s;
        }

        .copy-btn:hover {
            transform: scale(1.1);
        }

        .progress-bar-container {
            height: 6px;
            background: var(--border-color);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), #60a5fa);
            border-radius: 3px;
            transition: width 0.3s;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        [data-theme="dark"] .badge-success {
            background: #064e3b;
            color: #6ee7b7;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        [data-theme="dark"] .badge-warning {
            background: #78350f;
            color: #fef08a;
        }

        .badge-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        [data-theme="dark"] .badge-secondary {
            background: #4b5563;
            color: #e5e7eb;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        [data-theme="dark"] .badge-info {
            background: #1e3a8a;
            color: #bfdbfe;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        [data-theme="dark"] .badge-danger {
            background: #450a0a;
            color: #fca5a5;
        }

        .info-box {
            background: #eef2ff;
            border: 1px solid #e0e7ff;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        [data-theme="dark"] .info-box {
            background: #1e3a8a;
            border-color: #312e81;
        }

        .info-box h4 {
            font-size: 0.95rem;
            font-weight: 600;
            color: #312e81;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        [data-theme="dark"] .info-box h4 {
            color: #dbeafe;
        }

        .info-box ol {
            margin-left: 1.25rem;
            font-size: 0.875rem;
            color: #374151;
        }
        [data-theme="dark"] .info-box ol {
            color: #e0e7ff;
        }
        
        .info-box li {
            margin-bottom: 0.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            margin-bottom: 1rem;
        }

        /* ===== MOBILE RESPONSIVE ===== */
        @media (max-width: 768px) {
            .hamburger {
                display: flex;
            }

            .navbar {
                padding: 0.75rem 0.5rem;
            }

            .logo {
                font-size: 1.1rem;
            }

            .nav-links {
                display: none;
                position: fixed;
                top: 60px;
                left: 0;
                right: 0;
                background: var(--card-bg);
                flex-direction: column;
                gap: 0;
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease;
                box-shadow: var(--shadow-lg);
                z-index: 999;
            }

            .nav-links.active {
                max-height: 500px;
                display: flex;
            }

            .nav-links a {
                padding: 1rem;
                border-bottom: 1px solid var(--border-color);
                display: block;
                text-align: left;
            }

            main {
                padding: 1rem 0;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .container {
                padding: 0 0.75rem;
            }

            .notification-dropdown {
                width: 320px;
                right: -60px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }

            input, select, textarea, button {
                font-size: 16px !important;
            }
        }

        @media (max-width: 480px) {
            .logo {
                font-size: 1rem;
            }

            .page-header h1 {
                font-size: 1.25rem;
            }
            
            .card-body {
                padding: 1rem;
            }

            .notification-dropdown {
                width: calc(100vw - 20px);
                right: -10px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="navbar">
            <button class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </button>
    
            <a href="../dashboard.php" class="logo">
                <i class="fas fa-book-open"></i> Study Buddy
            </a>
    
            <ul class="nav-links" id="navLinks">
                <li><a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="../matches/index.php"><i class="fas fa-handshake"></i> Matches</a></li>
                <li><a href="../sessions/index.php"><i class="fas fa-calendar"></i> Sessions</a></li>
                <li><a href="../messages/index.php"><i class="fas fa-envelope"></i> Messages</a></li>
            </ul>
    
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="position: relative;">
                    <button class="notification-bell" onclick="toggleNotifications(event)" title="Notifications">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_notifications > 0): ?>
                            <span class="notification-badge"><?php echo $unread_notifications; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h4><i class="fas fa-bell"></i> Notifications</h4>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <div style="text-align: center; padding: 1.5rem; color: #999;">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                        <div class="notification-footer">
                            <a href="../notifications/index.php">View All</a>
                        </div>
                    </div>
                </div>
    
                <div class="profile-menu">
                    <button class="profile-icon" onclick="toggleProfileMenu(event)">
                        <?php if (!empty($user['profile_picture']) && file_exists('../' . $user['profile_picture'])): ?>
                            <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </button>
                    <div class="profile-dropdown" id="profileDropdown">
                        <div class="profile-dropdown-header">
                            <p class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                            <p class="user-role"><?php echo ucfirst($user['role']); ?></p>
                        </div>
                        <div class="profile-dropdown-menu">
                            <a href="../profile/index.php" class="profile-dropdown-item">
                                <i class="fas fa-user-circle"></i>
                                <span>View Profile</span>
                            </a>
                            <?php if (in_array($user['role'], ['mentor'])): ?>
                                <a href="../profile/commission-payments.php" class="profile-dropdown-item">
                                    <i class="fas fa-wallet"></i>
                                    <span>Commissions</span>
                                </a>
                            <?php endif; ?>
                            <a href="../profile/settings.php" class="profile-dropdown-item">
                                <i class="fas fa-sliders-h"></i>
                                <span>Settings</span>
                            </a>
                            <button class="profile-dropdown-item" id="theme-toggle-btn" style="cursor: pointer;">
                                <i class="fas fa-moon" id="theme-toggle-icon"></i>
                                <span id="theme-toggle-text">Dark Mode</span>
                            </button>
                            <hr style="margin: 0.5rem 0; border: none; border-top: 1px solid #f0f0f0;">
                            <a href="../auth/logout.php" class="profile-dropdown-item logout">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <main>
        <div class="container">
            <div class="page-header">
                <div>
                    <a href="index.php" class="back-link">
                        <i class="fas fa-arrow-left"></i>
                        Back to Profile
                    </a>
                    <h1><i class="fas fa-ticket-alt"></i> Referral Codes</h1>
                    <p class="page-subtitle">Manage your referral codes and track their usage.</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                 <div class="alert alert-success">
                     <i class="fas fa-check-circle"></i>
                    <span><?php echo $success; // Note: Using <strong>, so this is safe ?></span>
                </div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($referral_codes); ?></div>
                    <div class="stat-label">Total Codes Generated</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">
                        <?php 
                        echo count(array_filter($referral_codes, fn($c) => $c['is_active'] && strtotime($c['expires_at']) > time() && $c['current_uses'] < $c['max_uses'])); 
                        ?>
                    </div>
                    <div class="stat-label">Active Codes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($referred_users); ?></div>
                    <div class="stat-label">Total Users Referred</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-cogs"></i> Generate New Code</h3>
                </div>
                <div class="card-body">
                    <form action="referral-codes.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="max_uses" class="form-label">Max Uses</label>
                                <select id="max_uses" name="max_uses" class="form-select">
                                    <option value="1">1 use (Single Use)</option>
                                    <option value="5">5 uses</option>
                                    <option value="10">10 uses</option>
                                    <option value="25">25 uses</option>
                                    <option value="50">50 uses</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="expires_days" class="form-label">Expires In</label>
                                <select id="expires_days" name="expires_days" class="form-select">
                                    <option value="1">1 Day</option>
                                    <option value="7" selected>7 Days</option>
                                    <option value="14">14 Days</option>
                                    <option value="30">30 Days</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="generate_code" class="btn btn-sm">
                            <i class="fas fa-plus"></i> Generate Code
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list"></i> Your Codes</h3>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Status</th>
                                    <th>Usage</th>
                                    <th>Expires</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($referral_codes)): ?>
                                    <tr>
                                        <td colspan="5">
                                            <div class="empty-state" style="padding: 1rem;">
                                                <i class="fas fa-ticket-alt"></i>
                                                <p style="margin-bottom: 0;">You haven't generated any codes yet.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($referral_codes as $code): ?>
                                    <?php
                                    $is_expired = strtotime($code['expires_at']) < time();
                                    $is_maxed_out = $code['current_uses'] >= $code['max_uses'];
                                    $is_active = $code['is_active'] && !$is_expired && !$is_maxed_out;
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="code-badge">
                                                <span><?php echo htmlspecialchars($code['code']); ?></span>
                                                <button class="copy-btn" title="Copy code" onclick="copyCode('<?php echo htmlspecialchars($code['code']); ?>')">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($is_active): ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php elseif (!$code['is_active']): ?>
                                                <span class="badge badge-secondary">Deactivated</span>
                                            <?php elseif ($is_expired): ?>
                                                <span class="badge badge-warning">Expired</span>
                                            <?php elseif ($is_maxed_out): ?>
                                                <span class="badge badge-info">Max Uses</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span><?php echo $code['current_uses']; ?> / <?php echo $code['max_uses']; ?> uses</span>
                                            <div class="progress-bar-container">
                                                <div class="progress-bar" style="width: <?php echo ($code['current_uses'] / $code['max_uses']) * 100; ?>%;"></div>
                                            </div>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($code['expires_at'])); ?></td>
                                        <td>
                                            <form method="POST" action="referral-codes.php" style="display: inline-block;">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="deactivate_code" value="<?php echo $code['id']; ?>">
                                                <button type="button" class="btn btn-sm btn-icon btn-danger" 
                                                        title="Deactivate code"
                                                        onclick="confirmDeactivate(this, '<?php echo htmlspecialchars($code['code']); ?>')"
                                                        <?php echo !$is_active ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-users"></i> Users Referred</h3>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Referred With</th>
                                    <th>Date Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($referred_users)): ?>
                                    <tr>
                                        <td colspan="5">
                                            <div class="empty-state" style="padding: 1rem;">
                                                <i class="fas fa-user-times"></i>
                                                <p style="margin-bottom: 0;">No users have registered with your codes yet.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($referred_users as $ref_user): ?>
                                    <?php $details = json_decode($ref_user['details'], true); ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($ref_user['first_name'] . ' ' . $ref_user['last_name']); ?></td>
                                        <td><span class="badge badge-info"><?php echo ucfirst($ref_user['role']); ?></span></td>
                                        <td>
                                            <?php if ($ref_user['is_verified']): ?>
                                                <span class="badge badge-success">Verified</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Not Verified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <code class="code-badge" style="font-size: 0.8rem;"><?php echo htmlspecialchars($details['referral_code'] ?? 'N/A'); ?></code>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($ref_user['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="info-box" style="margin: 1.5rem; border-radius: 8px;">
                        <h4><i class="fas fa-info-circle"></i> How Referrals Work</h4>
                        <ol>
                            <li>Only users who register as a 'Student' can use a referral code.</li>
                            <li>When a student registers with your code, they are automatically upgraded to 'Peer'.</li>
                            <li>This table shows all users who have successfully registered using any of your codes.</li>
                        </ol>
                    </div>
                </div>
            </div>

        </div>
    </main>
    
    <script>
        let notificationDropdownOpen = false;
        let profileDropdownOpen = false;

        // Mobile Menu Toggle
        document.addEventListener("DOMContentLoaded", () => {
            const hamburger = document.querySelector(".hamburger");
            const navLinks = document.querySelector(".nav-links");
            
            if (hamburger) {
                hamburger.addEventListener("click", (e) => {
                    e.stopPropagation();
                    hamburger.classList.toggle("active");
                    navLinks.classList.toggle("active");
                });
            }
            
            document.addEventListener("click", (event) => {
                const hamburger = document.querySelector(".hamburger");
                const navLinks = document.querySelector(".nav-links");
                if (hamburger && navLinks && !hamburger.contains(event.target) && !navLinks.contains(event.target)) {
                    hamburger.classList.remove("active");
                    navLinks.classList.remove("active");
                }
            });
            
            // Theme Toggle
            const themeToggleBtn = document.getElementById('theme-toggle-btn');
            const themeToggleIcon = document.getElementById('theme-toggle-icon');
            const themeToggleText = document.getElementById('theme-toggle-text');
            
            function setTheme(theme) {
                if (theme === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    localStorage.setItem('theme', 'dark');
                    if(themeToggleIcon) themeToggleIcon.classList.replace('fa-moon', 'fa-sun');
                    if(themeToggleText) themeToggleText.textContent = 'Light Mode';
                } else {
                    document.documentElement.removeAttribute('data-theme');
                    localStorage.setItem('theme', 'light');
                    if(themeToggleIcon) themeToggleIcon.classList.replace('fa-sun', 'fa-moon');
                    if(themeToggleText) themeToggleText.textContent = 'Dark Mode';
                }
            }

            let currentTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            setTheme(currentTheme);

            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    let newTheme = document.documentElement.hasAttribute('data-theme') ? 'light' : 'dark';
                    setTheme(newTheme);
                });
            }
            
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
                if (!localStorage.getItem('theme')) {
                    setTheme(e.matches ? 'dark' : 'light');
                }
            });
        });

        function toggleNotifications(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('notificationDropdown');
            notificationDropdownOpen = !notificationDropdownOpen;
            
            if (notificationDropdownOpen) {
                dropdown.classList.add('show');
                document.getElementById('profileDropdown').classList.remove('show');
                profileDropdownOpen = false;
                loadNotifications();
            } else {
                dropdown.classList.remove('show');
            }
        }

        function toggleProfileMenu(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('profileDropdown');
            profileDropdownOpen = !profileDropdownOpen;
            
            if (profileDropdownOpen) {
                dropdown.classList.add('show');
                document.getElementById('notificationDropdown').classList.remove('show');
                notificationDropdownOpen = false;
            } else {
                dropdown.classList.remove('show');
            }
        }

        function loadNotifications() {
            fetch('../api/notifications.php')
                .then(response => response.json())
                .then(data => {
                    const list = document.getElementById('notificationList');
                    
                    if (!data.notifications || data.notifications.length === 0) {
                        list.innerHTML = '<div style="text-align: center; padding: 1.5rem; color: #999;"><i class="fas fa-bell-slash"></i><p>No notifications</p></div>';
                        return;
                    }
                    
                    list.innerHTML = data.notifications.slice(0, 6).map(notif => `
                        <div class="notification-item-dropdown ${!notif.is_read ? 'unread' : ''}" 
                             onclick="handleNotificationClick(${notif.id}, '${notif.link || ''}')">
                            <i class="fas ${getNotificationIcon(notif.type)}" style="color: ${getNotificationColor(notif.type)};"></i>
                            <div>
                                <div style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.25rem;">${escapeHtml(notif.title)}</div>
                                <div style="font-size: 0.8rem; color: #666;">${escapeHtml(notif.message)}</div>
                                <div style="font-size: 0.75rem; color: #999; margin-top: 0.25rem;">${timeAgo(notif.created_at)}</div>
                            </div>
                        </div>
                    `).join('');
                });
        }

        function handleNotificationClick(notificationId, link) {
            fetch('../api/notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'mark_read', notification_id: notificationId})
            }).then(() => {
                if (link) window.location.href = '../' + link;
                else loadNotifications();
            });
        }

        function getNotificationIcon(type) {
            const icons = { 'session_scheduled': 'fa-calendar-check', 'session_accepted': 'fa-check-circle', 'session_rejected': 'fa-times-circle', 'match_request': 'fa-handshake', 'match_accepted': 'fa-user-check', 'announcement': 'fa-megaphone', 'commission_due': 'fa-file-invoice-dollar' };
            return icons[type] || 'fa-bell';
        }

        function getNotificationColor(type) {
            const colors = { 'session_accepted': '#16a34a', 'session_rejected': '#dc2626', 'match_accepted': '#16a34a', 'announcement': '#2563eb', 'commission_due': '#d97706', 'session_scheduled': '#2563eb', 'match_request': '#2563eb', 'referral_used': '#16a34a' };
            return colors[type] || '#666';
        }

        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function timeAgo(dateString) {
            const date = new Date(dateString);
            const seconds = Math.floor((new Date() - date) / 1000);
            if (seconds < 60) return 'Just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
            if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
            return Math.floor(seconds / 604800) + 'w ago';
        }

        document.addEventListener('click', function(event) {
            const notifDropdown = document.getElementById('notificationDropdown');
            const profileDropdown = document.getElementById('profileDropdown');
            
            if (notificationDropdownOpen && notifDropdown && !notifDropdown.contains(event.target) && !event.target.closest('.notification-bell')) {
                notifDropdown.classList.remove('show');
                notificationDropdownOpen = false;
            }
            if (profileDropdownOpen && profileDropdown && !profileDropdown.contains(event.target) && !event.target.closest('.profile-icon')) {
                profileDropdown.classList.remove('show');
                profileDropdownOpen = false;
            }
        });

        setInterval(() => {
            if (notificationDropdownOpen) {
                loadNotifications();
            } else {
                fetch('../api/notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        const badge = document.querySelector('.notification-badge');
                        if (data.unread_count > 0) {
                            if (badge) {
                                badge.textContent = data.unread_count;
                            } else {
                                const bell = document.querySelector('.notification-bell');
                                const newBadge = document.createElement('span');
                                newBadge.className = 'notification-badge';
                                newBadge.textContent = data.unread_count;
                                if (bell) bell.appendChild(newBadge);
                            }
                        } else if (badge) {
                            badge.remove();
                        }
                    });
            }
        }, 30000);

        /**
         * UPDATED: Copy function to use SweetAlert toast
         */
        function copyCode(code) {
            navigator.clipboard.writeText(code).then(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'Copied!',
                    text: `Code "${code}" copied to clipboard`,
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }).catch(err => {
                Swal.fire({
                    icon: 'error',
                    title: 'Failed to copy',
                    text: 'Please copy the code manually',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            });
        }
        
        /**
         * NEW: SweetAlert confirmation for deactivating a code
         */
        function confirmDeactivate(button, code) {
            Swal.fire({
                title: 'Are you sure you want to deactivate this code?', // User's requested text
                html: `You are about to deactivate code: <strong>${code}</strong><br>This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, deactivate it',
                confirmButtonColor: '#dc2626', // Red color for the confirm button
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // If confirmed, find the parent form and submit it
                    button.closest('form').submit();
                }
            });
        }
    </script>
</body>
</html>