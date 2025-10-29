<?php
require_once '../config/config.php';
require_once '../config/notification_helper.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user) {
    redirect('auth/login.php');
}

$unread_notifications = get_unread_count($user['id']);

$match_id = isset($_GET['match_id']) ? (int)$_GET['match_id'] : 0;
if (!$match_id) {
    redirect('index.php');
}

$db = getDB();

// Verify user is part of this match
$match_stmt = $db->prepare("
    SELECT m.*, 
           CASE 
               WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
               ELSE CONCAT(u1.first_name, ' ', u1.last_name)
           END as partner_name,
           CASE 
               WHEN m.student_id = ? THEN u2.id
               ELSE u1.id
           END as partner_id,
           CASE 
               WHEN m.student_id = ? THEN u2.profile_picture
               ELSE u1.profile_picture
           END as partner_profile_picture
    FROM matches m
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE m.id = ? AND (m.student_id = ? OR m.mentor_id = ?) AND m.status = 'accepted'
");
$match_stmt->execute([$user['id'], $user['id'], $user['id'], $match_id, $user['id'], $user['id']]);
$match = $match_stmt->fetch();

if (!$match) {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $reason = trim($_POST['reason']);
        $description = trim($_POST['description']);
        
        if (empty($reason) || empty($description)) {
            $error = 'Please provide both a reason and description for the report.';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO user_reports (reporter_id, reported_id, reason, description) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user['id'], $match['partner_id'], $reason, $description]);
                
                // Log activity
                $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'user_reported', ?, ?)");
                $log_stmt->execute([$user['id'], json_encode(['reported_user_id' => $match['partner_id'], 'reason' => $reason]), $_SERVER['REMOTE_ADDR']]);
                
                $success = 'Report submitted successfully. Our admin team will review it shortly.';
                
            } catch (Exception $e) {
                $error = 'Failed to submit report. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Chat with <?php echo htmlspecialchars($match['partner_name']); ?> - Study Buddy</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --text-primary: #1a1a1a;
            --text-secondary: #666;
            --border-color: #e5e5e5;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.1);
            --bg-color: #fafafa;
            --card-bg: white;
            --chat-bg: #f8fafc;
            --message-bg: white;
            --own-message-bg: #2563eb;
        }

        [data-theme="dark"] {
            --primary-color: #3b82f6;
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --border-color: #374151;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.3);
            --bg-color: #111827;
            --card-bg: #1f2937;
            --chat-bg: #111827;
            --message-bg: #1f2937;
            --own-message-bg: #3b82f6;
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

        /* Hamburger Menu */
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

        /* Logo */
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

        /* Navigation Links */
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

        /* Notification Bell */
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
            background: rgba(37, 99, 235, 0.1);
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

        /* Profile Menu */
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

        /* Main Content */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        main {
            padding: 2rem 0;
            margin-top: 60px;
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
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: var(--border-color);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .btn-outline {
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            background: transparent;
        }

        .btn-outline:hover {
            background: var(--border-color);
        }

        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            min-height: 36px;
        }

        /* Chat Styles */
        .page-back-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .page-back-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

        .page-back-header p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin: 0.25rem 0 0 0;
        }

        .chat-container {
            height: calc(100vh - 200px);
            display: flex;
            flex-direction: column;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            background: var(--card-bg);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .chat-header {
            padding: 1rem 1.25rem;
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-shrink: 0;
        }

        .chat-partner-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
            min-width: 0;
        }

        .chat-partner-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            flex-shrink: 0;
            overflow: hidden;
        }

        .chat-partner-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .chat-partner-avatar.initials {
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .chat-partner-details {
            flex: 1;
            min-width: 0;
        }

        .chat-partner-name {
            font-weight: 600;
            font-size: 1rem;
            margin: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--text-primary);
        }

        .chat-partner-status {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .chat-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-shrink: 0;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background: var(--chat-bg);
        }
        
        .message {
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message.own {
            flex-direction: row-reverse;
        }
        
        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            flex-shrink: 0;
        }
        
        .message-content {
            max-width: 70%;
            background: var(--message-bg);
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            word-wrap: break-word;
            word-break: break-word;
        }
        
        .message.own .message-content {
            background: var(--own-message-bg);
            color: white;
        }
        
        .message-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }
        
        .message.own .message-time {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .chat-input {
            padding: 1rem;
            background: var(--card-bg);
            border-top: 1px solid var(--border-color);
            flex-shrink: 0;
        }
        
        .input-group {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .form-input {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            background: var(--card-bg);
            color: var(--text-primary);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .loading-messages {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }
        
        .empty-messages {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }
        
        .empty-messages svg {
            width: 64px;
            height: 64px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .message-attachment {
            margin-top: 0.5rem;
        }

        .message-attachment img {
            max-width: 100%;
            border-radius: 8px;
            cursor: pointer;
        }

        .attachment-preview {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: rgba(37, 99, 235, 0.1);
            border: 1px solid rgba(37, 99, 235, 0.2);
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .attachment-preview button {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-secondary);
            padding: 0.25rem;
            display: flex;
            align-items: center;
        }

        .attachment-preview button:hover {
            color: #dc2626;
        }

        .hide-on-small {
            display: inline;
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

            .container {
                padding: 0 0.75rem;
            }

            .page-back-header {
                margin-bottom: 1rem;
            }

            .page-back-header h1 {
                font-size: 1.25rem;
            }

            .chat-container {
                height: calc(100vh - 160px);
                border-radius: 8px;
            }

            .chat-header {
                padding: 0.75rem;
            }

            .chat-partner-avatar {
                width: 40px;
                height: 40px;
            }

            .chat-partner-avatar.initials {
                font-size: 1rem;
            }

            .chat-partner-name {
                font-size: 0.95rem;
            }

            .chat-partner-status {
                font-size: 0.8rem;
            }

            .chat-actions {
                flex-wrap: wrap;
                gap: 0.375rem;
            }

            .chat-actions .btn-sm {
                padding: 0.425rem 0.625rem;
                font-size: 0.8rem;
            }

            .chat-messages {
                padding: 0.75rem;
            }

            .message-content {
                max-width: 80%;
            }

            .chat-input {
                padding: 0.75rem;
            }

            .input-group {
                gap: 0.375rem;
            }

            .notification-dropdown {
                width: calc(100vw - 2rem);
                right: -0.5rem;
            }

            input, select, textarea, button {
                font-size: 16px !important;
            }

            .hide-on-small {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .logo {
                font-size: 1rem;
            }

            .page-back-header h1 {
                font-size: 1.1rem;
            }

            .chat-container {
                height: calc(100vh - 140px);
                border-radius: 0;
                border-left: none;
                border-right: none;
            }

            .chat-header {
                padding: 0.625rem 0.75rem;
            }

            .chat-partner-avatar {
                width: 36px;
                height: 36px;
            }

            .chat-partner-avatar.initials {
                font-size: 0.95rem;
            }

            .chat-actions .btn-sm {
                padding: 0.375rem 0.5rem;
                font-size: 0.75rem;
            }

            .chat-messages {
                padding: 0.625rem;
            }

            .message {
                gap: 0.5rem;
            }

            .message-avatar {
                width: 28px;
                height: 28px;
                font-size: 0.75rem;
            }

            .message-content {
                padding: 0.625rem 0.875rem;
                font-size: 0.9rem;
                max-width: 85%;
            }

            .input-group .btn-outline {
                padding: 0.625rem;
            }

            .input-group .btn-primary {
                padding: 0.625rem 0.875rem;
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
                <li><a href="index.php"><i class="fas fa-envelope"></i> Messages</a></li>
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
                            <h4 style="margin: 0; font-size: 1rem;"><i class="fas fa-bell"></i> Notifications</h4>
                        </div>
                        <div class="notification-list" id="notificationList">
                             <div style="text-align: center; padding: 1.5rem; color: #999;">
                                 <i class="fas fa-spinner fa-spin"></i>
                             </div>
                        </div>
                        <div class="notification-footer">
                            <a href="../notifications/index.php"><i class="fas fa-arrow-right"></i> View All</a>
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
                                <i class="fas fa-user-circle"></i> <span>View Profile</span>
                            </a>
                            <?php if (in_array($user['role'], ['mentor'])): ?>
                            <a href="../profile/commission-payments.php" class="profile-dropdown-item">
                                <i class="fas fa-wallet"></i> <span>Commissions</span>
                            </a>
                            <?php endif; ?>
                            <a href="../profile/settings.php" class="profile-dropdown-item">
                                <i class="fas fa-sliders-h"></i> <span>Settings</span>
                            </a>
                            <button type="button" class="profile-dropdown-item" onclick="toggleTheme()">
                                <i class="fas fa-sun" id="theme-icon"></i> <span id="theme-text">Toggle Theme</span>
                            </button>
                            <hr style="margin: 0.5rem 0; border: none; border-top: 1px solid var(--border-color);">
                            <a href="../auth/logout.php" class="profile-dropdown-item logout">
                                <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="page-back-header">
                <a href="index.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <div style="flex: 1;">
                    <h1>Chat with <?php echo htmlspecialchars($match['partner_name']); ?></h1>
                    <p>Subject: <?php echo htmlspecialchars($match['subject']); ?></p>
                </div>
            </div>

            <?php if ($error): ?>
                <div style="padding: 1rem; background: #fee2e2; border: 1px solid #fecaca; border-radius: 8px; color: #991b1b; margin-bottom: 1rem;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div style="padding: 1rem; background: #d1fae5; border: 1px solid #a7f3d0; border-radius: 8px; color: #065f46; margin-bottom: 1rem;">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <div class="chat-container">
                <div class="chat-header">
                    <div class="chat-partner-info">
                        <?php if (!empty($match['partner_profile_picture']) && file_exists('../' . $match['partner_profile_picture'])): ?>
                            <div class="chat-partner-avatar">
                                <img src="../<?php echo htmlspecialchars($match['partner_profile_picture']); ?>" alt="<?php echo htmlspecialchars($match['partner_name']); ?>">
                            </div>
                        <?php else: ?>
                            <div class="chat-partner-avatar initials">
                                <?php echo strtoupper(substr($match['partner_name'], 0, 2)); ?>
                            </div>
                        <?php endif; ?>
                        <div class="chat-partner-details">
                            <p class="chat-partner-name"><?php echo htmlspecialchars($match['partner_name']); ?></p>
                            <p class="chat-partner-status">Online</p>
                        </div>
                    </div>
                    <div class="chat-actions">
                        <a href="../sessions/schedule.php?match_id=<?php echo $match_id; ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-calendar-plus"></i> <span class="hide-on-small">Schedule</span>
                        </a>
                        <div style="position: relative;">
                            <button type="button" class="btn btn-outline btn-sm" id="chatMenuBtn" onclick="toggleChatMenu()">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div id="chatMenu" style="display: none; position: absolute; right: 0; top: 100%; margin-top: 0.5rem; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); min-width: 180px; z-index: 1000;">
                                <button type="button" onclick="openReportModal()" style="width: 100%; text-align: left; padding: 0.75rem 1rem; border: none; background: transparent; display: flex; align-items: center; gap: 0.5rem; color: #dc2626; cursor: pointer; font-size: 0.9rem;">
                                    <i class="fas fa-flag"></i> Report User
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <div class="loading-messages">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading messages...</p>
                    </div>
                </div>
                
                <div class="chat-input">
                    <div id="attachmentPreview" style="display: none;"></div>
                    <form id="messageForm">
                        <input type="file" id="fileInput" style="display: none;" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip">
                        <div class="input-group">
                            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('fileInput').click()">
                                <i class="fas fa-paperclip"></i>
                            </button>
                            <input type="text" name="message" id="messageInput" class="form-input" placeholder="Type your message..." maxlength="1000" autocomplete="off">
                            <button type="submit" class="btn btn-primary btn-sm" id="sendBtn">
                                <i class="fas fa-paper-plane"></i> <span class="hide-on-small">Send</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <div id="reportModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: var(--card-bg); border-radius: 12px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 600; color: var(--text-primary);">Report User</h3>
                <button type="button" onclick="closeReportModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div style="padding: 1.5rem;">
                    <div style="margin-bottom: 1rem;">
                        <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                            You are reporting <strong><?php echo htmlspecialchars($match['partner_name']); ?></strong>. Please provide details about why you're reporting this user.
                        </p>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-primary);">Reason for Report</label>
                        <select name="reason" class="form-input" required style="width: 100%;">
                            <option value="">Select a reason...</option>
                            <option value="harassment">Harassment or Bullying</option>
                            <option value="inappropriate">Inappropriate Content</option>
                            <option value="spam">Spam or Scam</option>
                            <option value="fake_profile">Fake Profile</option>
                            <option value="no_show">Repeated No-Shows</option>
                            <option value="unprofessional">Unprofessional Behavior</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-primary);">Description</label>
                        <textarea name="description" class="form-input" rows="4" required placeholder="Please provide specific details about the issue..." style="width: 100%; resize: vertical;"></textarea>
                        <small style="color: var(--text-secondary);">Be as specific as possible. This will help our team review your report.</small>
                    </div>
                </div>
                <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <button type="button" onclick="closeReportModal()" class="btn btn-outline">Cancel</button>
                    <button type="submit" name="submit_report" class="btn btn-primary">Submit Report</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="linkBlockModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: var(--card-bg); border-radius: 12px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 600; color: var(--text-primary);">Create an Official Session</h3>
                <button type="button" onclick="closeLinkBlockModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">&times;</button>
            </div>
            <div style="padding: 1.5rem;">
                <p style="color: var(--text-secondary); line-height: 1.6; margin-bottom: 1.5rem;">
                    It looks like you're trying to share a meeting link. To prevent commission evasion and ensure your session is tracked, please use the official "Schedule Session" button.
                </p>
                <a href="../sessions/schedule.php?match_id=<?php echo $match_id; ?>" class="btn btn-primary" style="width: 100%; text-align: center;">
                    Go to Schedule Page
                </a>
                <button type="button" onclick="closeLinkBlockModal()" class="btn btn-outline" style="width: 100%; margin-top: 0.75rem;">
                    Cancel
                </button>
            </div>
        </div>
    </div>
    <script>
        const matchId = <?php echo $match_id; ?>;
        const currentUserId = <?php echo $user['id']; ?>;
        let lastMessageTime = null;
        let isLoadingMessages = false;
        let messagePollingInterval;
        
        const chatMessages = document.getElementById('chatMessages');
        const messageForm = document.getElementById('messageForm');
        const messageInput = document.getElementById('messageInput');
        
        const fileInput = document.getElementById('fileInput');
        const attachmentPreview = document.getElementById('attachmentPreview');
        let file = null;

        // --- Theme Toggle JS ---
        const body = document.body;
        const themeIcon = document.getElementById('theme-icon');
        const themeText = document.getElementById('theme-text');
        
        function setTheme(theme) {
            body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            updateThemeToggleUI(theme);
        }
        
        function updateThemeToggleUI(theme) {
            if (theme === 'dark') {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
                themeText.textContent = 'Light Mode';
            } else {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
                themeText.textContent = 'Dark Mode';
            }
        }
        
        function toggleTheme() {
            const currentTheme = body.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            setTheme(newTheme);
        }
        
        (function loadTheme() {
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            let initialTheme = 'light';
            
            if (savedTheme) {
                initialTheme = savedTheme;
            } else if (prefersDark) {
                initialTheme = 'dark';
            }
            
            setTheme(initialTheme);
        })();

        function openLinkBlockModal() {
            document.getElementById('linkBlockModal').style.display = 'flex';
        }
        function closeLinkBlockModal() {
            document.getElementById('linkBlockModal').style.display = 'none';
        }

        function openReportModal() {
            document.getElementById('reportModal').style.display = 'flex';
            toggleChatMenu(true);
        }
        function closeReportModal() {
            document.getElementById('reportModal').style.display = 'none';
        }
        
        function toggleChatMenu(forceClose = false) {
            const menu = document.getElementById('chatMenu');
            if (forceClose || menu.style.display === 'block') {
                menu.style.display = 'none';
            } else {
                menu.style.display = 'block';
            }
        }
        
        document.addEventListener('click', function(e) {
            const menu = document.getElementById('chatMenu');
            const btn = document.getElementById('chatMenuBtn');
            if (menu && btn && !menu.contains(e.target) && !btn.contains(e.target)) {
                menu.style.display = 'none';
            }
        });

        // FIXED: Remove ' UTC' to prevent 8-hour offset
        function formatMessageTime(dateTime) {
            // Parse datetime as Manila time (it's already stored in Manila timezone)
            const dt = new Date(dateTime.replace(' ', 'T'));
            return dt.toLocaleString('en-US', {
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: 'numeric', 
                minute: '2-digit', 
                hour12: true
            });
        }
        
        function renderMessage(msg) {
            const isOwn = parseInt(msg.sender_id) === currentUserId;
            const messageTime = formatMessageTime(msg.created_at);
            
            let attachmentHTML = '';
            if (msg.file_url) {
                if (msg.file_url.match(/\.(jpeg|jpg|gif|png)$/i)) {
                    attachmentHTML = `<div class="message-attachment"><img src="../${msg.file_url}" alt="Attachment" style="max-width: 200px; cursor: pointer;" onclick="window.open('../${msg.file_url}', '_blank')"></div>`;
                } else {
                    attachmentHTML = `<div class="message-attachment"><a href="../${msg.file_url}" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-file-alt"></i> Download File</a></div>`;
                }
            }

            const messageText = msg.message ? msg.message.replace(/</g, "&lt;").replace(/>/g, "&gt;") : '';

            const messageHTML = `
                <div class="message ${isOwn ? 'own' : ''}" id="msg-${msg.id}">
                    <div class="message-avatar" style="background-color: ${isOwn ? 'var(--primary-color)' : '#64748b'};">
                        ${isOwn ? '<?php echo strtoupper(substr($user['first_name'], 0, 2)); ?>' : '<?php echo strtoupper(substr($match['partner_name'], 0, 2)); ?>'}
                    </div>
                    <div class="message-content">
                        ${messageText}
                        ${attachmentHTML}
                        <div class="message-time">${messageTime}</div>
                    </div>
                </div>
            `;
            return messageHTML;
        }

        async function loadMessages(initial = true) {
            if (isLoadingMessages) return;
            isLoadingMessages = true;
            
            let url = `../api/messages.php?match_id=${matchId}`;
            if (!initial && lastMessageTime) {
                url += `&since=${encodeURIComponent(lastMessageTime)}`;
            }

            try {
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.messages && data.messages.length > 0) {
                    if (initial) {
                        chatMessages.innerHTML = '';
                    }
                    
                    data.messages.forEach(msg => {
                        if (!document.getElementById(`msg-${msg.id}`)) {
                            chatMessages.innerHTML += renderMessage(msg);
                            lastMessageTime = msg.created_at;
                        }
                    });
                    
                    if (initial) {
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    }
                } else if (initial && data.messages.length === 0) {
                    chatMessages.innerHTML = `
                        <div class="empty-messages">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193l-3.72 3.72a.75.75 0 01-1.06 0l-3.72-3.72H6.31c-1.136 0-1.98-.967-1.98-2.193v-4.286c0-.97.616-1.813 1.5-2.097L6.75 8.25m.75 3h6M8.25 15h.008v.008H8.25V15z" />
                            </svg>
                            <p>This is the start of your conversation.<br>Messages you send will appear here.</p>
                        </div>
                    `;
                }
                
            } catch (error) {
                console.error('Failed to load messages:', error);
                if (initial) {
                    chatMessages.innerHTML = '<div class="loading-messages">Error loading messages. Please refresh.</div>';
                }
            } finally {
                isLoadingMessages = false;
            }
        }

        async function handleMessageSend(event) {
            event.preventDefault();
            const message = messageInput.value.trim();
            const sendBtn = document.getElementById('sendBtn');

            const meetLinkRegex = /(meet\.google\.com\/|zoom\.us\/(j|my)\/)/i;
            if (meetLinkRegex.test(message)) {
                openLinkBlockModal();
                return;
            }
            
            if (message === '' && !file) {
                return;
            }

            const originalBtnContent = sendBtn.innerHTML;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            sendBtn.disabled = true;
            messageInput.disabled = true;

            const formData = new FormData();
            formData.append('match_id', matchId);
            formData.append('message', message);
            if (file) {
                formData.append('file', file);
            }

            try {
                const response = await fetch('../api/messages.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success && data.message) {
                    if (chatMessages.querySelector('.empty-messages')) {
                        chatMessages.innerHTML = '';
                    }
                    chatMessages.innerHTML += renderMessage(data.message);
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                    lastMessageTime = data.message.created_at;
                    messageInput.value = '';
                    clearAttachment();
                } else {
                    alert('Error: ' + (data.error || 'Failed to send message.'));
                }

            } catch (error) {
                console.error('Failed to send message:', error);
                alert('A network error occurred. Please try again.');
            } finally {
                sendBtn.innerHTML = originalBtnContent;
                sendBtn.disabled = false;
                messageInput.disabled = false;
                messageInput.focus();
            }
        }
        
        function clearAttachment() {
            file = null;
            fileInput.value = '';
            attachmentPreview.innerHTML = '';
            attachmentPreview.style.display = 'none';
        }

        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                file = fileInput.files[0];
                
                if (file.size > 5 * 1024 * 1024) {
                    alert('File is too large. Max size is 5MB.');
                    clearAttachment();
                    return;
                }
                
                attachmentPreview.innerHTML = `
                    <div class="attachment-preview">
                        <i class="fas fa-file"></i>
                        <span>${file.name} (${(file.size / 1024).toFixed(1)} KB)</span>
                        <button type="button" onclick="clearAttachment()">&times;</button>
                    </div>
                `;
                attachmentPreview.style.display = 'block';
            }
        });

        messageForm.addEventListener('submit', handleMessageSend);

        loadMessages(true);
        
        messagePollingInterval = setInterval(() => loadMessages(false), 3000);
        
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                loadMessages(false);
            }
        });

        let profileDropdownOpen = false;
        let notificationDropdownOpen = false;

        function toggleProfileMenu(event) {
            event.stopPropagation();
            if (notificationDropdownOpen) {
                document.getElementById('notificationDropdown').classList.remove('show');
                notificationDropdownOpen = false;
            }
            const dropdown = document.getElementById('profileDropdown');
            profileDropdownOpen = !profileDropdownOpen;
            dropdown.classList.toggle('show');
        }
        
        function toggleNotifications(event) {
            event.stopPropagation();
            if (profileDropdownOpen) {
                document.getElementById('profileDropdown').classList.remove('show');
                profileDropdownOpen = false;
            }
            const dropdown = document.getElementById('notificationDropdown');
            notificationDropdownOpen = !notificationDropdownOpen;
            dropdown.classList.toggle('show');
            if (notificationDropdownOpen) {
                loadNotifications();
            }
        }
        
        async function loadNotifications() {
            const list = document.getElementById('notificationList');
            list.innerHTML = '<div style="text-align: center; padding: 1.5rem; color: #999;"><i class="fas fa-spinner fa-spin"></i></div>';
            
            try {
                const response = await fetch('../api/notifications.php?mark_read=true');
                const data = await response.json();
                
                if (data.notifications && data.notifications.length > 0) {
                    list.innerHTML = '';
                    data.notifications.forEach(n => {
                        list.innerHTML += `
                            <div class="notification-item-dropdown ${n.is_read == 0 ? 'unread' : ''}" onclick="window.location.href='${n.link}'">
                                <div style="flex-shrink: 0;"><i class="fas fa-info-circle" style="color: var(--primary-color);"></i></div>
                                <div>
                                    <p style="margin: 0; font-size: 0.9rem; color: var(--text-primary);">${n.message}</p>
                                    <small style="color: var(--text-secondary);">${formatMessageTime(n.created_at)}</small>
                                </div>
                            </div>
                        `;
                    });
                    document.querySelector('.notification-badge')?.remove();
                } else {
                    list.innerHTML = '<div style="text-align: center; padding: 1.5rem; color: #999;">No notifications</div>';
                }
            } catch (e) {
                list.innerHTML = '<div style="text-align: center; padding: 1.5rem; color: #999;">Failed to load</div>';
            }
        }

        document.getElementById('hamburger').addEventListener('click', function() {
            this.classList.toggle('active');
            document.getElementById('navLinks').classList.toggle('active');
        });
        
        document.addEventListener('click', function() {
            if (notificationDropdownOpen) {
                document.getElementById('notificationDropdown').classList.remove('show');
                notificationDropdownOpen = false;
            }
            if (profileDropdownOpen) {
                document.getElementById('profileDropdown').classList.remove('show');
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
                                bell.appendChild(newBadge);
                            }
                        } else if (badge) {
                            badge.remove();
                        }
                    });
            }
        }, 1000);
    </script>
</body>
</html>