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
    <title>Chat with <?php echo htmlspecialchars($match['partner_name']); ?> - StudyConnect</title>
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
    <!-- Header Navigation -->
    <header class="header">
        <div class="navbar">
            <!-- Mobile Hamburger -->
            <button class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <!-- Logo -->
            <a href="../dashboard.php" class="logo">
                <i class="fas fa-book-open"></i> StudyConnect
            </a>

            <!-- Desktop Navigation -->
            <ul class="nav-links" id="navLinks">
                <li><a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="../matches/index.php"><i class="fas fa-handshake"></i> Matches</a></li>
                <li><a href="../sessions/index.php"><i class="fas fa-calendar"></i> Sessions</a></li>
                <li><a href="index.php"><i class="fas fa-envelope"></i> Messages</a></li>
            </ul>

            <!-- Right Icons -->
            <div style="display: flex; align-items: center; gap: 1rem;">
                <!-- Notifications -->
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

                <!-- Profile Menu -->
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
                            <hr style="margin: 0.5rem 0; border: none; border-top: 1px solid var(--border-color);">
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
                                <img src="../<?php echo htmlspecialchars($match['partner_profile_picture']); ?>" 
                                     alt="<?php echo htmlspecialchars($match['partner_name']); ?>">
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
                                <button type="button" onclick="openReportModal()" 
                                        style="width: 100%; text-align: left; padding: 0.75rem 1rem; border: none; background: transparent; display: flex; align-items: center; gap: 0.5rem; color: #dc2626; cursor: pointer; font-size: 0.9rem;">
                                    <i class="fas fa-flag"></i>
                                    Report User
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
                            <input type="text" name="message" id="messageInput" class="form-input" 
                                   placeholder="Type your message..." 
                                   maxlength="1000" 
                                   autocomplete="off">
                            <button type="submit" class="btn btn-primary btn-sm" id="sendBtn">
                                <i class="fas fa-paper-plane"></i> <span class="hide-on-small">Send</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Report Modal -->
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
                            You are reporting <strong><?php echo htmlspecialchars($match['partner_name']); ?></strong>. 
                            Please provide details about why you're reporting this user.
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
                        <textarea name="description" class="form-input" rows="4" required 
                                  placeholder="Please provide specific details about the issue..."
                                  style="width: 100%; resize: vertical;"></textarea>
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

    <script>
        const matchId = <?php echo $match_id; ?>;
        const currentUserId = <?php echo $user['id']; ?>;
        let lastMessageTime = null;
        let isLoadingMessages = false;
        let messageCache = new Set();
        let selectedFile = null;
        let profileDropdownOpen = false;
        let notificationDropdownOpen = false;

        // Dark Mode Initialization
        const htmlElement = document.documentElement;
        const currentTheme = localStorage.getItem('theme') || 'light';
        htmlElement.setAttribute('data-theme', currentTheme);
        
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

                // Close menu when clicking on links
                const links = navLinks.querySelectorAll("a");
                links.forEach((link) => {
                    link.addEventListener("click", () => {
                        hamburger.classList.remove("active");
                        navLinks.classList.remove("active");
                    });
                });

                // Close menu when clicking outside
                document.addEventListener("click", (event) => {
                    if (hamburger && navLinks && !hamburger.contains(event.target) && !navLinks.contains(event.target)) {
                        hamburger.classList.remove("active");
                        navLinks.classList.remove("active");
                    }
                });
            }
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
                        list.innerHTML = `
                            <div style="text-align: center; padding: 1.5rem; color: #999;">
                                <i class="fas fa-bell-slash" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                                <p style="margin: 0.5rem 0 0 0;">No notifications</p>
                            </div>
                        `;
                        return;
                    }
                    
                    list.innerHTML = data.notifications.slice(0, 6).map(notif => {
                        const link = notif.link ? notif.link.replace(/'/g, "\\'") : '';
                        return `
                            <div class="notification-item-dropdown ${!notif.is_read ? 'unread' : ''}" 
                                 onclick="handleNotificationClick(${notif.id}, '${link}')">
                                <i class="fas ${getNotificationIcon(notif.type)}" 
                                   style="color: ${getNotificationColor(notif.type)}; font-size: 1.1rem; margin-top: 0.25rem;"></i>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.25rem; color: var(--text-primary);">
                                        ${escapeHtml(notif.title)}
                                    </div>
                                    <div style="font-size: 0.8rem; color: #666; line-height: 1.4;">
                                        ${escapeHtml(notif.message)}
                                    </div>
                                    <div style="font-size: 0.75rem; color: #999; margin-top: 0.25rem;">
                                        ${timeAgo(notif.created_at)}
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                })
                .catch(error => {
                    console.error('Error loading notifications:', error);
                    const list = document.getElementById('notificationList');
                    list.innerHTML = `
                        <div style="text-align: center; padding: 1.5rem; color: #999;">
                            <i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                            <p style="margin: 0.5rem 0 0 0;">Failed to load notifications</p>
                        </div>
                    `;
                });
        }

        function handleNotificationClick(notificationId, link) {
            fetch('../api/notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'mark_read', notification_id: notificationId})
            }).then(() => {
                if (link) window.location.href = link;
                else loadNotifications();
            });
        }

        function getNotificationIcon(type) {
            const icons = {
                'session_scheduled': 'fa-calendar-check',
                'session_accepted': 'fa-check-circle',
                'session_rejected': 'fa-times-circle',
                'match_request': 'fa-handshake',
                'match_accepted': 'fa-user-check',
                'announcement': 'fa-megaphone',
                'commission_due': 'fa-file-invoice-dollar',
                'message': 'fa-envelope'
            };
            return icons[type] || 'fa-bell';
        }

        function getNotificationColor(type) {
            const colors = {
                'session_accepted': '#16a34a',
                'session_rejected': '#dc2626',
                'match_accepted': '#16a34a',
                'announcement': '#2563eb',
                'commission_due': '#d97706',
                'session_scheduled': '#2563eb',
                'match_request': '#2563eb',
                'message': '#2563eb'
            };
            return colors[type] || '#666';
        }
        
        // Load messages
        async function loadMessages(isInitial = false) {
            if (isLoadingMessages) return;
            isLoadingMessages = true;
            
            try {
                let url = `../api/messages.php?match_id=${matchId}`;
                if (lastMessageTime && !isInitial) {
                    url += `&since=${encodeURIComponent(lastMessageTime)}`;
                }
                
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.messages && Array.isArray(data.messages)) {
                    const chatMessages = document.getElementById('chatMessages');
                    const isScrolledToBottom = chatMessages.scrollHeight - chatMessages.clientHeight <= chatMessages.scrollTop + 50;
                    
                    // Remove loading message on first load
                    if (isInitial) {
                        chatMessages.innerHTML = '';
                    }
                    
                    if (data.messages.length > 0) {
                        data.messages.forEach(message => {
                            // Avoid duplicates
                            if (!messageCache.has(message.id)) {
                                appendMessage(message);
                                messageCache.add(message.id);
                                lastMessageTime = message.created_at;
                            }
                        });
                        
                        // Auto-scroll if user was at bottom or initial load
                        if (isScrolledToBottom || isInitial) {
                            scrollToBottom();
                        }
                    } else if (isInitial) {
                        // Show empty state
                        chatMessages.innerHTML = `
                            <div class="empty-messages">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                </svg>
                                <p>No messages yet. Start the conversation!</p>
                            </div>
                        `;
                    }
                }
            } catch (error) {
                console.error('Error loading messages:', error);
            } finally {
                isLoadingMessages = false;
            }
        }
        
        // Append message to chat
        function appendMessage(message) {
            const chatMessages = document.getElementById('chatMessages');
            const isOwn = message.sender_id == currentUserId;
            
            // Remove empty state if exists
            const emptyState = chatMessages.querySelector('.empty-messages');
            if (emptyState) {
                emptyState.remove();
            }
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isOwn ? 'own' : ''}`;
            messageDiv.dataset.messageId = message.id;
            
            // Avatar
            const initials = message.first_name.charAt(0) + message.last_name.charAt(0);
            const bgColor = isOwn ? 'var(--primary-color)' : '#10b981';
            
            // Time
            const timeAgo = getTimeAgo(message.created_at);
            
            // Attachment HTML
            let attachmentHtml = '';
            if (message.attachment_path) {
                const isImage = message.attachment_type && message.attachment_type.startsWith('image/');
                if (isImage) {
                    attachmentHtml = `
                        <div class="message-attachment">
                            <a href="../${escapeHtml(message.attachment_path)}" target="_blank">
                                <img src="../${escapeHtml(message.attachment_path)}" 
                                     alt="${escapeHtml(message.attachment_name || 'Image')}"
                                     style="max-width: 250px; border-radius: 8px;"
                                     onerror="this.style.display='none'">
                            </a>
                        </div>
                    `;
                } else {
                    const fileSize = message.attachment_size ? (message.attachment_size / 1024).toFixed(1) : '0';
                    attachmentHtml = `
                        <div class="message-attachment" style="padding: 0.75rem; background: rgba(0,0,0,0.05); border-radius: 8px; margin-top: 0.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <i class="fas fa-file" style="font-size: 1.5rem;"></i>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(message.attachment_name || 'File')}</div>
                                    <div style="font-size: 0.8rem; opacity: 0.8;">${fileSize} KB</div>
                                </div>
                                <a href="../api/download-attachment.php?message_id=${message.id}" 
                                   class="btn btn-sm btn-outline" 
                                   style="padding: 0.25rem 0.75rem; white-space: nowrap;"
                                   download>
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </div>
                    `;
                }
            }
            
            messageDiv.innerHTML = `
                <div class="message-avatar" style="background: ${bgColor};">
                    ${initials.toUpperCase()}
                </div>
                <div class="message-content">
                    ${message.message ? `<div>${escapeHtml(message.message).replace(/\n/g, '<br>')}</div>` : ''}
                    ${attachmentHtml}
                    <div class="message-time">${timeAgo}</div>
                </div>
            `;
            
            chatMessages.appendChild(messageDiv);
        }
        
        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Get time ago string
        function getTimeAgo(dateString) {
            const now = new Date();
            const messageDate = new Date(dateString);
            const diffSeconds = Math.floor((now - messageDate) / 1000);
            
            if (diffSeconds < 60) return 'Just now';
            if (diffSeconds < 3600) return Math.floor(diffSeconds / 60) + ' min ago';
            if (diffSeconds < 86400) return Math.floor(diffSeconds / 3600) + ' hr ago';
            
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const month = months[messageDate.getMonth()];
            const day = messageDate.getDate();
            let hours = messageDate.getHours();
            const minutes = messageDate.getMinutes().toString().padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12 || 12;
            
            return `${month} ${day}, ${hours}:${minutes} ${ampm}`;
        }

        function timeAgo(dateString) {
            return getTimeAgo(dateString);
        }
        
        // Scroll to bottom
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Send message
        async function sendMessage(messageText) {
            const sendBtn = document.getElementById('sendBtn');
            const messageInput = document.getElementById('messageInput');
            
            // Check if there's a message or file
            if (!messageText.trim() && !selectedFile) return;
            
            // Disable button
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            try {
                let response, data;
                
                // If there's a file, use FormData and your upload endpoint
                if (selectedFile) {
                    const formData = new FormData();
                    formData.append('match_id', matchId);
                    formData.append('message', messageText);
                    formData.append('attachment', selectedFile);
                    
                    response = await fetch('../api/upload-attachment.php', {
                        method: 'POST',
                        body: formData
                    });
                    data = await response.json();
                } else {
                    // Otherwise use JSON endpoint for text-only messages
                    response = await fetch('../api/messages.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            match_id: matchId,
                            message: messageText
                        })
                    });
                    data = await response.json();
                }
                
                if (data.success && data.message) {
                    // Clear input and file
                    messageInput.value = '';
                    clearAttachment();
                    
                    // Add message if not already cached
                    if (!messageCache.has(data.message.id)) {
                        appendMessage(data.message);
                        messageCache.add(data.message.id);
                        lastMessageTime = data.message.created_at;
                        scrollToBottom();
                    }
                } else {
                    alert(data.error || 'Failed to send message');
                }
            } catch (error) {
                console.error('Error sending message:', error);
                alert('Failed to send message. Please try again.');
            } finally {
                // Re-enable button
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> <span class="hide-on-small">Send</span>';
                messageInput.focus();
            }
        }
        
        // Handle form submission
        document.getElementById('messageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const messageInput = document.getElementById('messageInput');
            sendMessage(messageInput.value);
        });
        
        // Handle Enter key
        document.getElementById('messageInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('messageForm').dispatchEvent(new Event('submit'));
            }
        });
        
        // File handling
        const fileInput = document.getElementById('fileInput');
        const attachmentPreview = document.getElementById('attachmentPreview');
        
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // Validate file size (10MB)
            if (file.size > 10 * 1024 * 1024) {
                alert('File size must be less than 10MB');
                fileInput.value = '';
                return;
            }
            
            selectedFile = file;
            showAttachmentPreview(file);
        });
        
        function showAttachmentPreview(file) {
            attachmentPreview.style.display = 'block';
            attachmentPreview.innerHTML = `
                <div class="attachment-preview">
                    <i class="fas fa-paperclip"></i>
                    <span>${escapeHtml(file.name)} (${(file.size / 1024).toFixed(1)} KB)</span>
                    <button type="button" onclick="clearAttachment()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        }
        
        function clearAttachment() {
            selectedFile = null;
            fileInput.value = '';
            attachmentPreview.style.display = 'none';
            attachmentPreview.innerHTML = '';
        }
        
        // Menu functions
        function toggleChatMenu() {
            const menu = document.getElementById('chatMenu');
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        }
        
        function openReportModal() {
            document.getElementById('reportModal').style.display = 'flex';
            document.getElementById('chatMenu').style.display = 'none';
        }
        
        function closeReportModal() {
            document.getElementById('reportModal').style.display = 'none';
        }
        
        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('chatMenu');
            const btn = document.getElementById('chatMenuBtn');
            if (menu && btn && !menu.contains(event.target) && !btn.contains(event.target)) {
                menu.style.display = 'none';
            }

            // Close profile dropdown
            if (profileDropdownOpen) {
                const profileDropdown = document.getElementById('profileDropdown');
                const profileIcon = document.querySelector('.profile-icon');
                if (profileDropdown && !profileDropdown.contains(event.target) && !profileIcon.contains(event.target)) {
                    profileDropdown.classList.remove('show');
                    profileDropdownOpen = false;
                }
            }

            // Close notification dropdown
            if (notificationDropdownOpen) {
                const notificationDropdown = document.getElementById('notificationDropdown');
                const notificationBell = document.querySelector('.notification-bell');
                if (notificationDropdown && !notificationDropdown.contains(event.target) && !notificationBell.contains(event.target)) {
                    notificationDropdown.classList.remove('show');
                    notificationDropdownOpen = false;
                }
            }
        });
        
        // Close modal when clicking outside
        document.getElementById('reportModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeReportModal();
            }
        });
        
        // Initialize
        window.addEventListener('load', function() {
            loadMessages(true);
            document.getElementById('messageInput').focus();
        });
        
        // Poll for new messages every 3 seconds
        setInterval(() => loadMessages(false), 3000);
        
        // Reload when tab becomes visible
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                loadMessages(false);
            }
        });

        // Auto-update notification badge
        setInterval(() => {
            if (!notificationDropdownOpen) {
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
            } else {
                loadNotifications();
            }
        }, 30000);
    </script>
</body>
</html>