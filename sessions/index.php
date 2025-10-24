<?php
require_once '../config/config.php';
require_once '../config/notification_helper.php';

if (!is_logged_in()) redirect('auth/login.php');
$user = get_logged_in_user();
if (!$user) redirect('auth/login.php');

$db = getDB();
$unread_notifications = get_unread_count($user['id']);

$sessions_query = "
    SELECT s.*, m.subject,
           CASE WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
                ELSE CONCAT(u1.first_name, ' ', u1.last_name) END as partner_name,
           CASE WHEN m.student_id = ? THEN u2.role ELSE u1.role END as partner_role,
           CASE WHEN m.student_id = ? THEN u2.profile_picture ELSE u1.profile_picture END as partner_profile_picture
    FROM sessions s
    JOIN matches m ON s.match_id = m.id
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE (m.student_id = ? OR m.mentor_id = ?)
    ORDER BY s.session_date DESC, s.start_time DESC
";

$stmt = $db->prepare($sessions_query);
$stmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
$sessions = $stmt->fetchAll();

$upcoming_sessions = array_filter($sessions, fn($s) => $s['status'] === 'scheduled' && strtotime($s['session_date'] . ' ' . $s['start_time']) > time());
$past_sessions_need_completion = array_filter($sessions, fn($s) => $s['status'] === 'scheduled' && strtotime($s['session_date'] . ' ' . $s['start_time']) <= time());
$past_sessions = array_filter($sessions, fn($s) => $s['status'] === 'completed');
$cancelled_sessions = array_filter($sessions, fn($s) => in_array($s['status'], ['cancelled', 'no_show']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>My Sessions - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            transition: background-color 0.3s ease;
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
        }

        .notification-header h4 {
            color: var(--text-primary);
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

        .notification-footer {
            padding: 0.75rem;
            text-align: center;
            border-top: 1px solid var(--border-color);
        }

        .notification-footer a {
            color: var(--primary-color);
            text-decoration: none;
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

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            gap: 1rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-primary);
        }

        .page-header h1 i {
            color: var(--primary-color);
        }

        .page-subtitle {
            font-size: 0.95rem;
            color: var(--text-secondary);
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
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--border-color);
            color: var(--text-primary);
        }

        .btn-success {
            background: #16a34a;
            color: white;
        }

        .btn-success:hover {
            background: #15803d;
        }

        .btn-outline {
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            background: transparent;
        }

        .btn-outline:hover {
            background: var(--border-color);
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
        }

        /* Card */
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: background-color 0.3s ease;
        }

        .card-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: var(--card-bg);
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .card-body {
            padding: 1.25rem;
            background: var(--card-bg);
        }

        /* Session Card */
        .session-card {
            padding: 1.25rem;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            margin-bottom: 1rem;
            display: flex;
            gap: 1.25rem;
            align-items: flex-start;
            background: var(--card-bg);
        }

        .session-card.upcoming {
            background: rgba(37, 99, 235, 0.1);
            border-color: rgba(37, 99, 235, 0.3);
        }

        .session-card.pending {
            background: rgba(245, 158, 11, 0.1);
            border-color: rgba(245, 158, 11, 0.3);
        }

        .session-card.completed {
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .session-card.cancelled {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
        }

        .session-avatar {
            width: 56px;
            height: 56px;
            border-radius: 10px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.25rem;
            color: white;
            overflow: hidden;
        }

        .session-avatar.upcoming {
            background: #3b82f6;
        }

        .session-avatar.pending {
            background: #f59e0b;
        }

        .session-avatar.completed {
            background: #10b981;
        }

        .session-avatar.cancelled {
            background: #ef4444;
        }

        .session-info {
            flex: 1;
            min-width: 0;
        }

        .session-partner {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.25rem;
            color: var(--text-primary);
        }

        .session-meta {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .session-time {
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .session-time.upcoming {
            color: #2563eb;
        }

        [data-theme="dark"] .session-time.upcoming {
            color: #60a5fa;
        }

        .session-time.pending {
            color: #f59e0b;
        }

        [data-theme="dark"] .session-time.pending {
            color: #fbbf24;
        }

        .session-time.completed {
            color: #10b981;
        }

        [data-theme="dark"] .session-time.completed {
            color: #34d399;
        }

        .session-actions {
            display: flex;
            gap: 0.5rem;
            flex-direction: column;
            flex-shrink: 0;
        }

        .session-actions .btn {
            width: 130px;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            margin-bottom: 1rem;
            display: block;
        }

        .empty-state h3 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
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

            .page-header {
                flex-direction: column;
                gap: 1rem;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .session-card {
                flex-direction: column;
                padding: 1rem;
            }

            .session-actions {
                flex-direction: row;
                width: 100%;
            }

            .session-actions .btn {
                width: auto;
                flex: 1;
                min-width: 80px;
            }

            .container {
                padding: 0 0.75rem;
            }

            .notification-dropdown {
                width: calc(100vw - 2rem);
                right: 0;
                left: 1rem;
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

            .session-card {
                padding: 0.75rem;
            }

            .session-avatar {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
            }

            .btn-sm {
                padding: 0.375rem 0.5rem;
                font-size: 0.75rem;
            }

            .card-body {
                padding: 1rem;
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
                <li><a href="index.php"><i class="fas fa-calendar"></i> Sessions</a></li>
                <li><a href="../messages/index.php"><i class="fas fa-envelope"></i> Messages</a></li>
            </ul>

            <!-- Right Icons -->
            <div style="display: flex; align-items: center; gap: 1rem;">
                <!-- Notifications -->
                <div style="position: relative;">
                    <button class="notification-bell" onclick="toggleNotifications(event)">
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
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-calendar-check"></i> My Sessions</h1>
                    <p class="page-subtitle">Manage your study sessions and track your learning progress</p>
                </div>
                <a href="schedule.php" class="btn btn-primary">
                    <i class="fas fa-calendar-plus"></i> Schedule Session
                </a>
            </div>

            <?php if (!empty($upcoming_sessions)): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-hourglass-start" style="color: var(--primary-color);"></i>
                        <h3 class="card-title">Upcoming Sessions (<?php echo count($upcoming_sessions); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($upcoming_sessions as $session): ?>
                            <div class="session-card upcoming">
                                <div class="session-avatar upcoming">
                                    <?php 
                                    if (!empty($session['partner_profile_picture']) && file_exists('../' . $session['partner_profile_picture'])) {
                                        echo '<img src="../' . htmlspecialchars($session['partner_profile_picture']) . '" alt="' . htmlspecialchars($session['partner_name']) . '" style="width: 100%; height: 100%; object-fit: cover;">';
                                    } else {
                                        echo strtoupper(substr($session['partner_name'], 0, 1));
                                    }
                                    ?>
                                </div>
                                <div class="session-info">
                                    <div class="session-partner"><?php echo htmlspecialchars($session['partner_name']); ?></div>
                                    <div class="session-meta">
                                        <i class="fas fa-graduation-cap"></i> <?php echo ucfirst($session['partner_role']); ?> • 
                                        <i class="fas fa-book"></i> <?php echo htmlspecialchars($session['subject']); ?>
                                    </div>
                                    <div class="session-time upcoming">
                                        <i class="fas fa-clock"></i> 
                                        <?php echo date('M j, Y', strtotime($session['session_date'])); ?> • 
                                        <?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?>
                                    </div>
                                </div>
                                <div class="session-actions">
                                    <a href="edit.php?id=<?php echo $session['id']; ?>" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="../messages/chat.php?match_id=<?php echo $session['match_id']; ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-comment"></i> Message
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($past_sessions_need_completion)): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-tasks" style="color: var(--primary-color);"></i>
                        <h3 class="card-title">Awaiting Completion (<?php echo count($past_sessions_need_completion); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($past_sessions_need_completion as $session): ?>
                            <div class="session-card pending">
                                <div class="session-avatar pending">
                                    <?php 
                                    if (!empty($session['partner_profile_picture']) && file_exists('../' . $session['partner_profile_picture'])) {
                                        echo '<img src="../' . htmlspecialchars($session['partner_profile_picture']) . '" alt="' . htmlspecialchars($session['partner_name']) . '" style="width: 100%; height: 100%; object-fit: cover;">';
                                    } else {
                                        echo strtoupper(substr($session['partner_name'], 0, 1));
                                    }
                                    ?>
                                </div>
                                <div class="session-info">
                                    <div class="session-partner"><?php echo htmlspecialchars($session['partner_name']); ?></div>
                                    <div class="session-meta">
                                        <i class="fas fa-graduation-cap"></i> <?php echo ucfirst($session['partner_role']); ?> • 
                                        <i class="fas fa-book"></i> <?php echo htmlspecialchars($session['subject']); ?>
                                    </div>
                                    <div class="session-time pending">
                                        <i class="fas fa-exclamation-triangle"></i> Session ended • 
                                        <?php echo date('M j, Y', strtotime($session['session_date'])); ?>
                                    </div>
                                </div>
                                <div class="session-actions">
                                    <a href="complete.php?id=<?php echo $session['id']; ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-check"></i> Complete
                                    </a>
                                    <a href="../messages/chat.php?match_id=<?php echo $session['match_id']; ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-comment"></i> Message
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($past_sessions)): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-check-circle" style="color: var(--primary-color);"></i>
                        <h3 class="card-title">Completed Sessions (<?php echo count($past_sessions); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach (array_slice($past_sessions, 0, 10) as $session): ?>
                            <div class="session-card completed">
                                <div class="session-avatar completed">
                                    <?php 
                                    if (!empty($session['partner_profile_picture']) && file_exists('../' . $session['partner_profile_picture'])) {
                                        echo '<img src="../' . htmlspecialchars($session['partner_profile_picture']) . '" alt="' . htmlspecialchars($session['partner_name']) . '" style="width: 100%; height: 100%; object-fit: cover;">';
                                    } else {
                                        echo strtoupper(substr($session['partner_name'], 0, 1));
                                    }
                                    ?>
                                </div>
                                <div class="session-info">
                                    <div class="session-partner"><?php echo htmlspecialchars($session['partner_name']); ?></div>
                                    <div class="session-meta">
                                        <i class="fas fa-graduation-cap"></i> <?php echo ucfirst($session['partner_role']); ?> • 
                                        <i class="fas fa-book"></i> <?php echo htmlspecialchars($session['subject']); ?>
                                    </div>
                                    <div class="session-time completed">
                                        <i class="fas fa-calendar"></i> 
                                        <?php echo date('M j, Y', strtotime($session['session_date'])); ?>
                                    </div>
                                </div>
                                <div class="session-actions">
                                    <?php
                                    $rating_stmt = $db->prepare("SELECT id FROM session_ratings WHERE session_id = ? AND rater_id = ?");
                                    $rating_stmt->execute([$session['id'], $user['id']]);
                                    $has_rated = $rating_stmt->fetch();
                                    ?>
                                    <?php if (!$has_rated): ?>
                                        <a href="rate.php?id=<?php echo $session['id']; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-star"></i> Rate
                                        </a>
                                    <?php else: ?>
                                        <span style="padding: 0.5rem 0.75rem; background: rgba(16, 185, 129, 0.2); color: #10b981; border-radius: 6px; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-check-circle"></i> Rated
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($cancelled_sessions)): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-times-circle" style="color: var(--primary-color);"></i>
                        <h3 class="card-title">Cancelled Sessions</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($cancelled_sessions as $session): ?>
                            <div class="session-card cancelled">
                                <div class="session-avatar cancelled">
                                    <?php 
                                    if (!empty($session['partner_profile_picture']) && file_exists('../' . $session['partner_profile_picture'])) {
                                        echo '<img src="../' . htmlspecialchars($session['partner_profile_picture']) . '" alt="' . htmlspecialchars($session['partner_name']) . '" style="width: 100%; height: 100%; object-fit: cover;">';
                                    } else {
                                        echo strtoupper(substr($session['partner_name'], 0, 1));
                                    }
                                    ?>
                                </div>
                                <div class="session-info">
                                    <div class="session-partner"><?php echo htmlspecialchars($session['partner_name']); ?></div>
                                    <div class="session-meta">
                                        <i class="fas fa-book"></i> <?php echo htmlspecialchars($session['subject']); ?> • 
                                        <i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($session['session_date'])); ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: #ef4444; margin-top: 0.5rem;">
                                        <i class="fas fa-ban"></i> <?php echo ucfirst($session['status']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($sessions)): ?>
                <div class="card">
                    <div class="card-body empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No sessions yet</h3>
                        <p>Schedule your first study session to start learning with your partners</p>
                        <a href="schedule.php" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Schedule Session
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        let notificationDropdownOpen = false;
        let profileDropdownOpen = false;

        // Theme management
        const currentTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', currentTheme);

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
                'commission_due': 'fa-file-invoice-dollar'
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
                'match_request': '#2563eb'
            };
            return colors[type] || '#666';
        }

        function escapeHtml(text) {
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

        // Periodic notification check
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
                                bell.innerHTML += `<span class="notification-badge">${data.unread_count}</span>`;
                            }
                        } else if (badge) {
                            badge.remove();
                        }
                    });
            }
        }, 30000);
    </script>
</body>
</html>