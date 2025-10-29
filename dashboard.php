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
           END as partner_role,
           CASE 
               WHEN m.student_id = ? THEN u2.profile_picture
               ELSE u1.profile_picture
           END as partner_picture
    FROM matches m
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE (m.student_id = ? OR m.mentor_id = ?) 
    ORDER BY m.created_at DESC 
    LIMIT 5
");
$recent_matches_stmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
$recent_matches = $recent_matches_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en"> <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="color-scheme" content="light dark">
    <title>Dashboard - Study Buddy</title>
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
            color: #1a1a1a;
        }

        /* ===== HEADER & NAVIGATION ===== */
        .header {
            background: white;
            border-bottom: 1px solid var(--border-color);
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
            background: #f0f0f0;
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
            border: 2px solid white;
        }

        .notification-dropdown {
            display: none;
            position: absolute;
            right: -10px;
            top: 100%;
            margin-top: 0.75rem;
            width: 380px;
            max-height: 450px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            overflow: hidden;
            flex-direction: column;
        }

        .notification-dropdown.show {
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

        .notification-item-dropdown.unread {
            background: #fefbeb; /* CHANGED: Light yellow */
        }

        .notification-footer {
            padding: 0.75rem;
            text-align: center;
            border-top: 1px solid #f0f0f0;
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
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
        }

        .profile-dropdown.show {
            display: block;
        }

        .profile-dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
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
            color: #999;
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
            background: #f5f5f5;
            color: var(--primary-color);
        }

        .profile-dropdown-item.logout {
            color: #dc2626;
        }

        .profile-dropdown-item.logout:hover {
            background: #fee2e2;
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
            background: #fafafa;
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
        
        /* ADDED: btn-outline for dark mode */
        .btn-outline {
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            background: transparent;
        }
        .btn-outline:hover {
            background: #f5f5f5;
        }
        /* END ADDED */

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        /* Action Grid */
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.75rem;
        }

        .action-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 1rem 0.5rem;
            border-radius: 10px;
            background: #fafafa;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.85rem;
            font-weight: 500;
            min-height: 90px;
        }

        .action-item:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
            transform: translateY(-2px);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .action-item i {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
            transition: color 0.2s ease;
        }

        .action-item.primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .action-item.primary i {
            color: white;
        }

        .action-item.primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: white;
            transform: translateY(-2px);
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
            background: rgba(37, 99, 235, 0.05);
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
            overflow: hidden;
        }

        .match-info-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .match-info {
            flex: 1;
            min-width: 0;
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
            background: #fef3c7; /* CHANGED: */
            color: #92400e; /* CHANGED: */
        }

        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--border-color);
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

            .page-header h1 {
                font-size: 1.5rem;
            }

            .stats-grid,
            .content-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .container {
                padding: 0 0.75rem;
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

            .action-grid {
                gap: 0.5rem;
            }

            .action-item {
                padding: 0.75rem 0.5rem;
                font-size: 0.8rem;
                min-height: 80px;
            }

            .action-item i {
                font-size: 1.25rem;
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
                width: 320px;
                right: -60px;
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

            .page-header {
                margin-bottom: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
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

            .notification-dropdown {
                width: calc(100vw - 20px);
                right: -10px;
            }
        }
        
        /* ===== DARK MODE STYLES ===== */
        [data-theme="dark"] {
            --primary-color: #3b82f6; /* User requested */
            --text-primary: #e4e4e7;
            --text-secondary: #a1a1aa;
            --border-color: #374151; /* User requested */
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.3);

            /* Semantic colors */
            --bg-body: #111827;       /* User requested as --bg-color */
            --bg-card: #1f2937;       /* User requested as --card-bg */
            --bg-card-header: #3a3a3e; /* Unchanged from original */
            --bg-hover: #374151;       /* Matched to new border-color */
        }

        [data-theme="dark"] body {
            background: var(--bg-body);
            color: var(--text-primary);
        }

        [data-theme="dark"] .header,
        [data-theme="dark"] .card,
        [data-theme="dark"] .stat-card,
        [data-theme="dark"] .profile-dropdown,
        [data-theme="dark"] .notification-dropdown,
        [data-theme="dark"] .nav-links {
            background: var(--bg-card);
            border-color: var(--border-color);
        }
        
        [data-theme="dark"] .card-header {
            background: var(--bg-card-header);
            border-color: var(--border-color);
        }
        
        [data-theme="dark"] .profile-dropdown-menu hr,
        [data-theme="dark"] .nav-links a,
        [data-theme="dark"] .notification-footer,
        [data-theme="dark"] .notification-header,
        [data-theme="dark"] .notification-item-dropdown,
        [data-theme="dark"] .profile-dropdown-header {
            border-color: var(--border-color);
        }
        
        [data-theme="dark"] .btn-outline {
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .btn-outline:hover,
        [data-theme="dark"] .notification-bell:hover,
        [data-theme="dark"] .profile-dropdown-item:hover,
        [data-theme="dark"] .notification-item-dropdown:hover {
            background: var(--bg-hover);
        }
        
        [data-theme="dark"] .profile-dropdown-item:hover {
            color: var(--primary-color);
        }
        
        [data-theme="dark"] .profile-dropdown-item.logout:hover {
            background: #3f1212;
        }
        
        [data-theme="dark"] .notification-item-dropdown.unread {
            background: #3a3a3e; /* CHANGED: Dark yellow background */
        }
        
        [data-theme="dark"] .alert-error {
            background: #3f1212;
            border-color: #dc2626;
            color: #fca5a5;
        }
        
        [data-theme="dark"] .alert-success {
            background: #062f1e;
            border-color: #16a34a;
            color: #a7f3d0;
        }

        [data-theme="dark"] .alert-warning {
            background: #451a03;
            border-color: #d97706;
            color: #fcd34d;
        }
        
        [data-theme="dark"] .stat-card {
            box-shadow: none;
        }
        [data-theme="dark"] .stat-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
        }
        
        [data-theme="dark"] .stat-icon.primary {
            background: #1e3a8a;
            color: #93c5fd;
        }
        [data-theme="dark"] .stat-icon.success {
            background: #064e3b;
            color: #6ee7b7;
        }
        [data-theme="dark"] .stat-icon.warning {
            background: #451a03;
            color: #fcd34d;
        }
        
        [data-theme="dark"] .action-item {
            background: var(--bg-card-header);
            border-color: var(--border-color);
            color: var(--text-secondary);
        }
        [data-theme="dark"] .action-item:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background: rgba(59, 130, 246, 0.05);
            box-shadow: none;
        }
        [data-theme="dark"] .action-item.primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        [data-theme="dark"] .action-item.primary:hover {
             background: #1d4ed8;
             border-color: #1d4ed8;
        }

        [data-theme="dark"] .match-item {
            background: var(--bg-card-header);
            border-color: var(--border-color);
        }
        [data-theme="dark"] .match-item:hover {
            border-color: var(--primary-color);
            background: rgba(59, 130, 246, 0.05);
        }
        
        [data-theme="dark"] .match-info-icon {
            background: #1e3a8a;
            color: #93c5fd;
        }
        
        [data-theme="dark"] .status-accepted {
            background: #064e3b;
            color: #6ee7b7;
        }
        [data-theme="dark"] .status-pending {
            background: #451a03; /* CHANGED: */
            color: #fef9c3; /* CHANGED: */
        }
        
        [data-theme="dark"] .empty-state i {
            color: var(--border-color);
        }
        
        [data-theme="dark"] .hamburger span {
            background-color: var(--text-primary);
        }
        
        [data-theme="dark"] .profile-icon:hover {
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        [data-theme="dark"] .user-role {
            color: var(--text-secondary);
        }
        
        /* Fix for JS-injected inline styles */
        [data-theme="dark"] .notification-list div[style*="color: #999"] {
            color: var(--text-secondary) !important;
        }
        [data-theme="dark"] .notification-list div[style*="color: #666"] {
            color: var(--text-secondary) !important;
        }
        
    </style>
    
    <script>
        (function() {
            let theme = localStorage.getItem('theme');
            if (!theme) {
                // No theme saved, use system preference
                theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.removeAttribute('data-theme');
            }
        })();
    </script>
</head>
<body>
    <header class="header">
        <div class="navbar">
            <button class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <a href="dashboard.php" class="logo">
                <i class="fas fa-book-open"></i> Study Buddy
            </a>

            <ul class="nav-links" id="navLinks">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="matches/index.php"><i class="fas fa-handshake"></i> Matches</a></li>
                <li><a href="sessions/index.php"><i class="fas fa-calendar"></i> Sessions</a></li>
                <li><a href="messages/index.php"><i class="fas fa-envelope"></i> Messages</a></li>
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
                            <a href="notifications/index.php" style="font-size: 0.875rem; color: #2563eb; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-arrow-right"></i> View All
                            </a>
                        </div>
                    </div>
                </div>

                <div class="profile-menu">
                    <button class="profile-icon" onclick="toggleProfileMenu(event)">
                        <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile">
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
                            <a href="profile/index.php" class="profile-dropdown-item">
                                <i class="fas fa-user-circle"></i>
                                <span>View Profile</span>
                            </a>
                            <?php if (in_array($user['role'], ['mentor', 'peer'])): ?>
                                <a href="profile/commission-payments.php" class="profile-dropdown-item">
                                    <i class="fas fa-wallet"></i>
                                    <span>Commissions</span>
                                </a>
                            <?php endif; ?>
                            <a href="profile/settings.php" class="profile-dropdown-item">
                                <i class="fas fa-sliders-h"></i>
                                <span>Settings</span>
                            </a>
                            <button class="profile-dropdown-item" id="theme-toggle-btn" style="cursor: pointer;">
                                <i class="fas fa-moon" id="theme-toggle-icon"></i>
                                <span id="theme-toggle-text">Dark Mode</span>
                            </button>
                            <hr style="margin: 0.5rem 0; border: none; border-top: 1px solid #f0f0f0;">
                            <a href="auth/logout.php" class="profile-dropdown-item logout">
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
                <h1>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
                <p class="page-subtitle">
                    <?php if ($user['role'] === 'peer'): ?>
                        Track your learning and teaching progress
                    <?php else: ?>
                        Here's your learning dashboard
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($commission_warning): ?>
                <div class="alert <?php echo isset($commission_warning['suspended']) ? 'alert-error' : 'alert-warning'; ?>">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <?php if (isset($commission_warning['suspended'])): ?>
                            <h4>Account Suspended</h4>
                            <p>Your account has been suspended due to unpaid commissions. Please settle all overdue payments to continue.</p>
                        <?php else: ?>
                            <h4>Overdue Commission Payments</h4>
                            <p>You have <?php echo $commission_warning['overdue_count']; ?> overdue commission payment(s) totaling ₱<?php echo number_format($commission_warning['total_overdue'], 2); ?>.</p>
                            <p>Oldest unpaid commission: <?php echo $commission_warning['oldest_days']; ?> days overdue.</p>
                            <?php if ($commission_warning['oldest_days'] > 21): ?>
                                <p><strong>Warning:</strong> Your account will be suspended if commissions remain unpaid after 30 days.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="profile/commission-payments.php" class="btn" style="background: #dc2626; color: white; font-size: 0.8rem; padding: 0.5rem 0.75rem; width: auto; margin-top: 0.5rem;">
                            <i class="fas fa-credit-card"></i> Pay Now
                        </a>
                    </div>
                </div>
            <?php endif; ?>

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

            <div class="content-grid">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-bolt"></i>
                        <h3 class="card-title">Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="action-grid">
                            <?php if ($user['role'] === 'student'): ?>
                                <a href="matches/find.php" class="action-item primary">
                                    <i class="fas fa-search"></i>
                                    <span>Find a Mentor</span>
                                </a>
                                <a href="sessions/schedule.php" class="action-item">
                                    <i class="fas fa-calendar-plus"></i>
                                    <span>Schedule Session</span>
                                </a>
                                <a href="profile/subjects.php" class="action-item">
                                    <i class="fas fa-book"></i>
                                    <span>Manage Subjects</span>
                                </a>
                                <a href="matches/index.php" class="action-item">
                                    <i class="fas fa-handshake"></i>
                                    <span>View Matches</span>
                                </a>

                            <?php elseif ($user['role'] === 'peer'): ?>
                                <a href="matches/find.php" class="action-item primary">
                                    <i class="fas fa-users"></i>
                                    <span>Find Partners</span>
                                </a>
                                <a href="matches/index.php" class="action-item">
                                    <i class="fas fa-inbox"></i>
                                    <span>Match Requests</span>
                                </a>
                                <a href="profile/availability.php" class="action-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Set Availability</span>
                                </a>
                                <a href="profile/subjects.php" class="action-item">
                                    <i class="fas fa-book"></i>
                                    <span>Manage Subjects</span>
                                </a>

                            <?php elseif ($user['role'] === 'mentor'): ?>
                                <a href="matches/index.php" class="action-item primary">
                                    <i class="fas fa-inbox"></i>
                                    <span>Match Requests</span>
                                </a>
                                <a href="profile/availability.php" class="action-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Set Availability</span>
                                </a>
                                <a href="profile/commission-payments.php" class="action-item">
                                    <i class="fas fa-wallet"></i>
                                    <span>View Commissions</span>
                                </a>
                                <a href="profile/subjects.php" class="action-item">
                                    <i class="fas fa-book"></i>
                                    <span>Manage Subjects</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

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
                                        <div style="display: flex; align-items: center; flex: 1; min-width: 0; gap: 0.75rem;">
                                            <div class="match-info-icon">
                                                <?php if (!empty($match['partner_picture']) && file_exists($match['partner_picture'])): ?>
                                                    <img src="<?php echo htmlspecialchars($match['partner_picture']); ?>" alt="<?php echo htmlspecialchars($match['partner_name']); ?>">
                                                <?php else: ?>
                                                    <i class="fas fa-<?php echo $match['partner_role'] === 'mentor' ? 'chalkboard-user' : 'user'; ?>"></i>
                                                <?php endif; ?>
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
            
            /* ADDED: ===== THEME TOGGLE LOGIC ===== */
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

            // Set initial state for the button
            let currentTheme = document.documentElement.hasAttribute('data-theme') ? 'dark' : 'light';
            setTheme(currentTheme);

            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    let newTheme = document.documentElement.hasAttribute('data-theme') ? 'light' : 'dark';
                    setTheme(newTheme);
                });
            }
            
            // Listen for system preference changes
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
                if (!localStorage.getItem('theme')) {
                    setTheme(e.matches ? 'dark' : 'light');
                }
            });
            /* ADDED: ===== END THEME TOGGLE LOGIC ===== */
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