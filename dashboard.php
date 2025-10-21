<?php
require_once 'config/config.php';
require_once 'config/commission_helper.php';
require_once 'config/notification_helper.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user) {
    redirect('auth/login.php');
}

$commission_warning = null;
if ($user['role'] === 'mentor') {
    $db = getDB();
    $overdue_info = check_overdue_commissions($user['id'], $db);
    
    if ($overdue_info['has_overdue']) {
        $commission_warning = $overdue_info;
        
        if (should_suspend_mentor($user['id'], $db)) {
            $commission_warning['suspended'] = true;
        }
    }
}

$unread_notifications = get_unread_count($user['id']);

// Get user statistics
$db = getDB();

$match_stmt = $db->prepare("SELECT COUNT(*) as count FROM matches WHERE (student_id = ? OR mentor_id = ?) AND status = 'accepted'");
$match_stmt->execute([$user['id'], $user['id']]);
$match_count = $match_stmt->fetch()['count'];

$session_stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM sessions s 
    JOIN matches m ON s.match_id = m.id 
    WHERE (m.student_id = ? OR m.mentor_id = ?) AND s.status = 'completed'
");
$session_stmt->execute([$user['id'], $user['id']]);
$session_count = $session_stmt->fetch()['count'];

// Get recent matches
$recent_matches_stmt = $db->prepare("
    SELECT m.*, 
           CASE 
               WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
               ELSE CONCAT(u1.first_name, ' ', u1.last_name)
           END as partner_name,
           CASE 
               WHEN m.student_id = ? THEN u2.role
               ELSE u1.role
           END as partner_role
    FROM matches m
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE (m.student_id = ? OR m.mentor_id = ?) 
    ORDER BY m.created_at DESC 
    LIMIT 5
");
$recent_matches_stmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
$recent_matches = $recent_matches_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Dashboard - StudyConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --text-primary: #1a1a1a;
            --text-secondary: #666;
            --border-color: #e5e5e5;
            --shadow: 0 1px 3px rgba(0,0,0,0.05);
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.1);
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
            background: #fafafa;
            color: var(--text-primary);
        }

        /* Header */
        .header {
            background: white;
            border-bottom: 1px solid var(--border-color);
            padding: 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 60px;
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
            letter-spacing: -0.5px;
            flex: 1;
            white-space: nowrap;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
            margin: 0;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 500;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-links a:hover {
            color: var(--primary-color);
        }

        /* Notification & Profile Buttons */
        .notification-bell,
        .profile-icon {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            cursor: pointer;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
            font-size: 1.1rem;
            color: var(--text-secondary);
            background: transparent;
            min-height: 44px;
            min-width: 44px;
        }

        .notification-bell:hover {
            background: #f0f0f0;
            color: var(--primary-color);
        }

        .profile-icon {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1e40af 100%);
            color: white;
        }

        .profile-icon:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
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
            border: 2px solid white;
        }

        /* Dropdowns */
        .notification-dropdown,
        .profile-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 0.5rem;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            overflow: hidden;
        }

        .notification-dropdown {
            width: 380px;
            max-height: 450px;
            flex-direction: column;
        }

        .profile-dropdown {
            width: 240px;
        }

        .notification-dropdown.show,
        .profile-dropdown.show {
            display: flex;
        }

        .notification-header {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
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
            border-bottom: 1px solid #f5f5f5;
            cursor: pointer;
            transition: background 0.15s;
            display: flex;
            gap: 0.75rem;
        }

        .notification-item-dropdown:hover {
            background: #fafafa;
        }

        .profile-dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            text-align: center;
        }

        .profile-dropdown-item {
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
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .profile-dropdown-item:hover {
            background: #f5f5f5;
            color: var(--primary-color);
        }

        .profile-dropdown-item.logout {
            color: #dc2626;
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

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .page-subtitle {
            font-size: 0.95rem;
            color: var(--text-secondary);
        }

        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }

        .alert-warning {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            color: #92400e;
        }

        .alert-error {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }

        .alert i {
            flex-shrink: 0;
            font-size: 1.25rem;
            margin-top: 0.125rem;
        }

        .alert h4 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }

        .stat-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-icon.primary {
            background: #dbeafe;
            color: var(--primary-color);
        }

        .stat-icon.success {
            background: #dcfce7;
            color: #16a34a;
        }

        .stat-icon.warning {
            background: #fef3c7;
            color: #d97706;
        }

        .stat-value {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .card {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .card-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-header i {
            font-size: 1.1rem;
            color: var(--primary-color);
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .card-body {
            padding: 1.25rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            min-height: 44px;
            width: 100%;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #f0f0f0;
            color: var(--text-primary);
        }

        .btn-outline {
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            background: transparent;
        }

        /* Action List */
        .action-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        /* Match Item */
        .match-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #fafafa;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            transition: all 0.2s;
            gap: 1rem;
        }

        .match-item:hover {
            border-color: var(--primary-color);
            background: #f0f7ff;
        }

        .match-info-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: #dbeafe;
            color: var(--primary-color);
            font-size: 0.95rem;
            flex-shrink: 0;
        }

        .match-info {
            flex: 1;
        }

        .match-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
        }

        .match-detail {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .match-status {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            flex-shrink: 0;
        }

        .status-accepted {
            background: #dcfce7;
            color: #166534;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: #999;
        }

        .empty-state i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #e5e5e5;
        }

        /* Mobile */
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
                background: white;
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

            .stats-grid,
            .content-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-value {
                font-size: 1.5rem;
            }

            .card-body {
                padding: 1rem;
            }

            .btn {
                font-size: 0.875rem;
                padding: 0.625rem 0.75rem;
            }

            .match-item {
                padding: 0.75rem;
                flex-wrap: wrap;
            }

            .match-status {
                padding: 0.25rem 0.5rem;
                font-size: 0.7rem;
            }

            .notification-dropdown {
                width: calc(100vw - 2rem);
                max-width: 380px;
                right: auto;
                left: 50%;
                transform: translateX(-50%);
            }

            .profile-dropdown {
                width: 100%;
                max-width: 240px;
            }

            input, select, textarea, button {
                font-size: 16px;
            }
        }

        @media (max-width: 480px) {
            .logo {
                font-size: 1rem;
            }

            .page-header h1 {
                font-size: 1.25rem;
            }

            .page-header {
                margin-bottom: 1.5rem;
            }

            .stats-grid {
                gap: 0.75rem;
            }

            .stat-card {
                padding: 0.75rem;
            }

            .stat-value {
                font-size: 1.25rem;
            }

            .stat-label {
                font-size: 0.8rem;
            }

            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1.25rem;
                margin-bottom: 0.75rem;
            }

            .card-header {
                padding: 1rem;
            }

            .card-body {
                padding: 0.75rem;
            }

            .btn {
                font-size: 0.8rem;
                padding: 0.5rem;
                min-height: 40px;
            }

            .match-item {
                padding: 0.5rem;
            }

            .match-info-icon {
                width: 32px;
                height: 32px;
                font-size: 0.85rem;
            }

            .match-name {
                font-size: 0.85rem;
            }

            .match-detail {
                font-size: 0.75rem;
            }

            .alert {
                padding: 0.75rem;
                gap: 0.75rem;
            }

            .alert i {
                font-size: 1rem;
            }

            .alert h4 {
                font-size: 0.9rem;
            }

            .alert p {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header Navigation -->
    <header class="header">
        <div class="navbar">
            <!-- Hamburger -->
            <button class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <!-- Logo -->
            <a href="dashboard.php" class="logo">
                <i class="fas fa-book-open"></i> StudyConnect
            </a>

            <!-- Desktop Nav -->
            <ul class="nav-links" id="navLinks">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="matches/index.php"><i class="fas fa-handshake"></i> Matches</a></li>
                <li><a href="sessions/index.php"><i class="fas fa-calendar"></i> Sessions</a></li>
                <li><a href="messages/index.php"><i class="fas fa-envelope"></i> Messages</a></li>
            </ul>

            <!-- Right Section -->
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <!-- Notification Bell -->
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
                            <div style="text-align: center; padding: 1.5rem;">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Menu -->
                <div style="position: relative;">
                    <button class="profile-icon" onclick="toggleProfileMenu(event)">
                        <i class="fas fa-user"></i>
                    </button>
                    <div class="profile-dropdown" id="profileDropdown">
                        <div class="profile-dropdown-header">
                            <p style="font-weight: 600; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                            <p style="font-size: 0.8rem; color: #999;"><?php echo ucfirst($user['role']); ?></p>
                        </div>
                        <div class="profile-dropdown-menu">
                            <a href="profile/index.php" class="profile-dropdown-item">
                                <i class="fas fa-user-circle"></i> View Profile
                            </a>
                            <?php if (in_array($user['role'], ['mentor'])): ?>
                                <a href="profile/commission-payments.php" class="profile-dropdown-item">
                                    <i class="fas fa-wallet"></i> Commissions
                                </a>
                            <?php endif; ?>
                            <a href="profile/settings.php" class="profile-dropdown-item">
                                <i class="fas fa-sliders-h"></i> Settings
                            </a>
                            <hr style="margin: 0.5rem 0; border: none; border-top: 1px solid #f0f0f0;">
                            <a href="auth/logout.php" class="profile-dropdown-item logout">
                                <i class="fas fa-sign-out-alt"></i> Logout
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
                <h1>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
                <p class="page-subtitle">
                    <?php if ($user['role'] === 'peer'): ?>
                        Track your learning and teaching progress
                    <?php else: ?>
                        Here's your learning dashboard
                    <?php endif; ?>
                </p>
            </div>

            <!-- Commission Warnings -->
            <?php if ($commission_warning): ?>
                <div class="alert <?php echo isset($commission_warning['suspended']) ? 'alert-error' : 'alert-warning'; ?>">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <?php if (isset($commission_warning['suspended'])): ?>
                            <h4>Account Suspended</h4>
                            <p>Your account has been suspended due to unpaid commissions. Please settle all overdue payments to continue.</p>
                        <?php else: ?>
                            <h4>Overdue Commissions</h4>
                            <p><?php echo $commission_warning['overdue_count']; ?> payment(s) pending • ₱<?php echo number_format($commission_warning['total_overdue'], 2); ?></p>
                            <?php if ($commission_warning['oldest_days'] > 21): ?>
                                <p><strong>Warning:</strong> Account will be suspended in <?php echo (30 - $commission_warning['oldest_days']); ?> days</p>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="profile/commission-payments.php" class="btn" style="background: #dc2626; color: white; font-size: 0.8rem; padding: 0.5rem 0.75rem; width: auto; margin-top: 0.5rem;">
                            <i class="fas fa-credit-card"></i> Pay Now
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <div class="stat-value"><?php echo $match_count; ?></div>
                    <div class="stat-label">Active Matches</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo $session_count; ?></div>
                    <div class="stat-label">Completed Sessions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-user-tag"></i>
                    </div>
                    <div class="stat-value"><?php echo ucfirst($user['role']); ?></div>
                    <div class="stat-label">Your Role</div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-bolt"></i>
                        <h3 class="card-title">Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="action-list">
                            <?php if ($user['role'] === 'student'): ?>
                                <a href="matches/find.php" class="btn btn-primary"><i class="fas fa-search"></i> Find a Mentor</a>
                                <a href="sessions/schedule.php" class="btn btn-secondary"><i class="fas fa-calendar-plus"></i> Schedule Session</a>
                            <?php elseif ($user['role'] === 'peer'): ?>
                                <a href="matches/find.php" class="btn btn-primary"><i class="fas fa-users"></i> Find Study Partners</a>
                                <a href="matches/index.php" class="btn btn-secondary"><i class="fas fa-inbox"></i> Match Requests</a>
                                <a href="profile/availability.php" class="btn btn-outline"><i class="fas fa-clock"></i> Update Availability</a>
                            <?php else: ?>
                                <a href="matches/index.php" class="btn btn-primary"><i class="fas fa-inbox"></i> Match Requests</a>
                                <a href="profile/availability.php" class="btn btn-secondary"><i class="fas fa-clock"></i> Update Availability</a>
                            <?php endif; ?>
                            <a href="profile/subjects.php" class="btn btn-outline"><i class="fas fa-book"></i> Manage Subjects</a>
                        </div>
                    </div>
                </div>

                <!-- Recent Matches -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history"></i>
                        <h3 class="card-title">Recent Matches</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_matches)): ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No matches yet. Start connecting!</p>
                            </div>
                        <?php else: ?>
                            <div class="action-list">
                                <?php foreach ($recent_matches as $match): ?>
                                    <div class="match-item">
                                        <div style="display: flex; align-items: center; flex: 1; min-width: 0;">
                                            <div class="match-info-icon">
                                                <i class="fas fa-<?php echo $match['partner_role'] === 'mentor' ? 'chalkboard-user' : 'user'; ?>"></i>
                                            </div>
                                            <div class="match-info">
                                                <div class="match-name"><?php echo htmlspecialchars($match['partner_name']); ?></div>
                                                <div class="match-detail">
                                                    <i class="fas fa-bookmark"></i> <?php echo htmlspecialchars($match['subject']); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <span class="match-status status-<?php echo $match['status']; ?>">
                                            <?php echo ucfirst($match['status']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
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

                const links = navLinks.querySelectorAll("a");
                links.forEach((link) => {
                    link.addEventListener("click", () => {
                        hamburger.classList.remove("active");
                        navLinks.classList.remove("active");
                    });
                });

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
            fetch('api/notifications.php')
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
            fetch('api/notifications.php', {
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

        setInterval(() => {
            if (notificationDropdownOpen) {
                loadNotifications();
            } else {
                fetch('api/notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        const badge = document.getElementById('notificationBadge');
                        if (data.unread_count > 0) {
                            if (badge) badge.textContent = data.unread_count;
                            else {
                                const bell = document.querySelector('.notification-bell');
                                if (bell) {
                                    bell.innerHTML += `<span class="notification-badge" id="notificationBadge">${data.unread_count}</span>`;
                                }
                            }
                        } else if (badge) badge.remove();
                    });
            }
        }, 30000);
    </script>
</body>
</html>