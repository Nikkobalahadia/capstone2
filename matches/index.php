<?php
require_once '../config/config.php';
require_once '../config/notification_helper.php';
require_once '../includes/matchmaking.php';

if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user) {
    redirect('auth/login.php');
}

$unread_notifications = get_unread_count($user['id']);

$can_accept_matches = true;
$commission_block_message = '';

if ($user['role'] === 'mentor') {
    require_once '../config/commission_helper.php';
    $db = getDB();
    $overdue_info = check_overdue_commissions($user['id'], $db);
    
    if ($overdue_info['has_overdue']) {
        $can_accept_matches = false;
        $commission_block_message = "You have {$overdue_info['overdue_count']} overdue commission payment(s) totaling ₱" . number_format($overdue_info['total_overdue'], 2) . ". Please pay your commissions before accepting new matches.";
    }
}

$error = '';
$success = '';

$db = getDB();
$matchmaker = new MatchmakingEngine($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        if (!$can_accept_matches && $_POST['response'] === 'accepted') {
            $error = $commission_block_message;
        } else {
            $match_id = (int)$_POST['match_id'];
            $response = $_POST['response'];
            
            try {
                $matchmaker->respondToMatch($match_id, $user['id'], $response);
                $success = 'Match ' . ($response === 'accepted' ? 'accepted' : 'declined') . ' successfully!';
            } catch (Exception $e) {
                $error = 'Failed to process response. Please try again.';
            }
        }
    }
}

$matches_query = "
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
               WHEN m.student_id = ? THEN u2.bio
               ELSE u1.bio
           END as partner_bio,
           CASE 
               WHEN m.student_id = ? THEN u2.location
               ELSE u1.location
           END as partner_location,
           CASE 
               WHEN m.student_id = ? THEN u2.grade_level
               ELSE u1.grade_level
           END as partner_grade_level,
           CASE 
               WHEN m.student_id = ? THEN u2.id
               ELSE u1.id
           END as partner_id,
           CASE 
               WHEN m.student_id = ? THEN u2.profile_picture
               ELSE u1.profile_picture
           END as partner_profile_picture,
           (SELECT AVG(sr.rating) 
            FROM session_ratings sr 
            WHERE sr.rated_id = CASE 
                WHEN m.student_id = ? THEN u2.id 
                ELSE u1.id 
            END) as partner_avg_rating,
           (SELECT COUNT(*) 
            FROM session_ratings sr 
            WHERE sr.rated_id = CASE 
                WHEN m.student_id = ? THEN u2.id 
                ELSE u1.id 
            END) as partner_rating_count
    FROM matches m
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE (m.student_id = ? OR m.mentor_id = ?)
    ORDER BY 
        CASE m.status 
            WHEN 'pending' THEN 1 
            WHEN 'accepted' THEN 2 
            ELSE 3 
        END,
        m.created_at DESC
";

$stmt = $db->prepare($matches_query);
$stmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
$matches = $stmt->fetchAll();

$pending_matches = array_filter($matches, function($match) { return $match['status'] === 'pending'; });
$accepted_matches = array_filter($matches, function($match) { return $match['status'] === 'accepted'; });
$other_matches = array_filter($matches, function($match) { return !in_array($match['status'], ['pending', 'accepted']); });
?>
<!DOCTYPE html>
<html lang="en"> <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="color-scheme" content="light dark">
    <title>My Matches - Study Buddy</title>
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
            background: #fefbeb; /* CHANGED: */
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

        .btn-success {
            background: #16a34a;
            color: white;
        }

        .btn-success:hover {
            background: #15803d;
        }


        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-outline {
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            background: transparent;
        }

        .btn-outline:hover {
            background: #f5f5f5;
        }

        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            min-height: auto;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert-success {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .alert-warning {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            color: #92400e;
        }

        .card {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
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

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .card-body {
            padding: 1.25rem;
        }

        .match-card {
            padding: 1.25rem;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            margin-bottom: 1rem;
            display: flex;
            gap: 1.25rem;
            align-items: flex-start;
            background: white;
        }

        .match-card.pending {
            background: #fffbeb; /* CHANGED: */
            border-color: #fde68a; /* CHANGED: */
        }

        .match-card.accepted {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }

        .match-avatar {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.5rem;
            color: white;
            overflow: hidden;
        }

        .match-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .match-avatar.pending {
            background: #fbbf24; /* CHANGED: */
            color: #78350f; /* CHANGED: */
        }

        .match-avatar.accepted {
            background: #10b981;
        }

        .match-info {
            flex: 1;
            min-width: 0;
        }

        .match-name {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.25rem;
            color: var(--text-primary);
        }

        .match-meta {
            font-size: 0.85rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 0.5rem;
        }

        .match-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #dbeafe;
            color: #1e40af;
        }
        
        /* ADDED: Style for the pending badge */
        .match-status-badge {
            padding: 0.5rem;
            border-radius: 6px;
            text-align: center;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        /* CHANGED: For light yellow */
        .match-status-badge.pending-badge {
            background: #fef3c7;
            color: #92400e;
        }

        .match-actions {
            display: flex;
            gap: 0.5rem;
            flex-direction: column;
            flex-shrink: 0;
        }

        .match-actions .btn {
            width: 130px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, var(--primary-color) 0%, #1e40af 100%);
            color: white;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
        }

        .modal-body {
            padding: 1.5rem;
        }

        /* ADDED: CSS classes for modal elements */
        .modal-avatar {
            width: 80px; 
            height: 80px; 
            border-radius: 10px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: white; 
            font-weight: 600; 
            font-size: 2rem; 
            border: 3px solid var(--border-color);
            overflow: hidden;
        }
        .modal-avatar img {
             width: 100%;
             height: 100%;
             object-fit: cover;
        }
        .modal-badge {
            padding: 0.25rem 0.75rem; 
            border-radius: 12px; 
            font-size: 0.875rem; 
            font-weight: 600;
        }
        .modal-avatar.accepted, .modal-badge.accepted {
            background: #10b981; 
            color: white;
        }
        .modal-avatar.pending, .modal-badge.pending {
            background: #fbbf24; /* CHANGED: */
            color: #78350f; /* CHANGED: */
        }
        /* END ADDED */


        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 4rem;
            color: #e5e7eb;
            margin-bottom: 1.5rem;
            display: block;
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

            .page-header {
                flex-direction: column;
                gap: 1rem;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .match-card {
                flex-direction: column;
                padding: 1rem;
            }

            .match-actions {
                flex-direction: row;
                width: 100%;
            }

            .match-actions .btn {
                width: auto;
                flex: 1;
                min-width: 80px;
            }

            .container {
                padding: 0 0.75rem;
            }

            .notification-dropdown {
                width: 320px;
                right: -60px;
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

            .page-header h1 {
                font-size: 1.25rem;
            }

            .match-card {
                padding: 0.75rem;
            }

            .match-avatar {
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

            .modal-content {
                width: 95%;
            }

            .notification-dropdown {
                width: calc(100vw - 20px);
                right: -10px;
            }
        }
        
        /* ===== DARK MODE STYLES ===== */
        /* Applied via [data-theme="dark"] on <html> tag */
        
        [data-theme="dark"] {
            --primary-color: #3b82f6; /* User requested */
            --text-primary: #e4e4e7;
            --text-secondary: #a1a1aa;
            --border-color: #374151; /* User requested */
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.3);

            /* Semantic colors */
            --bg-body: #111827;       /* User requested */
            --bg-card: #1f2937;       /* User requested */
            --bg-card-header: #3a3a3e; /* Card-header, hover backgrounds */
            --bg-hover: #3f3f46;
        }

        [data-theme="dark"] body {
            background: var(--bg-body);
            color: var(--text-primary);
        }

        [data-theme="dark"] .header,
        [data-theme="dark"] .card,
        [data-theme="dark"] .modal-content,
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
        [data-theme="dark"] .info-row,
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
            background: #3a3a3e; /* CHANGED: */
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
        
        [data-theme="dark"] .match-card {
             background: var(--bg-card);
        }
        
        [data-theme="dark"] .match-card.pending {
            background: var(--bg-card-header); /* CHANGED: */
            border-color: #fde68a; /* CHANGED: */
        }
        
        [data-theme="dark"] .match-card.accepted {
            background: #062f1e;
            border-color: #16a34a;
        }
        
        [data-theme="dark"] .match-avatar.pending {
            background: #fbbf24; /* CHANGED: */
            color: #78350f; /* CHANGED: */
        }

        [data-theme="dark"] .match-status-badge.pending-badge {
            background: #451a03; /* CHANGED: */
            color: #fef9c3; /* CHANGED: */
        }
        
        /* ADDED: Dark mode for modal pending */
        [data-theme="dark"] .modal-avatar.pending, 
        [data-theme="dark"] .modal-badge.pending {
            background: #fbbf24; /* CHANGED: */
            color: #78350f; /* CHANGED: */
        }
        
        [data-theme="dark"] .match-badge {
            background: #1e3a8a;
            color: #dbeafe;
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

        [data-theme="dark"] .modal-header {
            background: var(--primary-color);
        }
        
        [data-theme="dark"] .modal-avatar {
            border-color: var(--border-color);
        }

        /* Fix for modal partner details */
        [data-theme="dark"] #modalBody h3 {
            color: var(--text-primary);
        }
        [data-theme="dark"] #modalBody .info-row span:first-child {
            color: var(--text-secondary);
        }
        [data-theme="dark"] #modalBody .info-row span:last-child {
            color: var(--text-primary);
        }
        [data-theme="dark"] #modalBody h4 {
            color: var(--text-primary);
        }
        [data-theme="dark"] #modalBody p {
            color: var(--text-secondary);
        }

        /* Fix for JS-injected inline styles */
        [data-theme="dark"] .notification-list div[style*="color: #999"] {
            color: var(--text-secondary) !important;
        }
        [data-theme="dark"] .notification-list div[style*="color: #666"] {
            color: var(--text-secondary) !important;
        }
        
        [data-theme="dark"] .user-role {
            color: var(--text-secondary);
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

            <a href="../dashboard.php" class="logo">
                <i class="fas fa-book-open"></i> Study Buddy
            </a>

            <ul class="nav-links" id="navLinks">
                <li><a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="index.php"><i class="fas fa-handshake"></i> Matches</a></li>
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
                            <a href="../notifications/index.php" style="font-size: 0.875rem; color: #2563eb; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-arrow-right"></i> View All
                            </a>
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
                    <h1><i class="fas fa-handshake"></i> My Matches</h1>
                    <p class="page-subtitle">Manage your study partnerships and match requests</p>
                </div>
                <a href="find.php" class="btn btn-primary">
                    <i class="fas fa-search"></i> <span class="hide-on-small">Find New Partners</span>
                </a>
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
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>

            <?php if (!$can_accept_matches): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <h4 style="margin-bottom: 0.5rem;"><i class="fas fa-lock"></i> Cannot Accept New Matches</h4>
                        <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem;"><?php echo $commission_block_message; ?></p>
                        <a href="../profile/commission-payments.php" class="btn btn-sm" style="margin-top: 0.75rem; background: #dc2626; color: white;">
                            <i class="fas fa-credit-card"></i> Pay Commissions
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($pending_matches)): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-clock" style="color: var(--primary-color);"></i>
                        <h3 class="card-title">Pending Requests (<?php echo count($pending_matches); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($pending_matches as $match): ?>
                            <div class="match-card pending">
                                <div class="match-avatar pending">
                                    <?php 
                                    if (!empty($match['partner_profile_picture']) && file_exists('../' . $match['partner_profile_picture'])) {
                                        echo '<img src="../' . htmlspecialchars($match['partner_profile_picture']) . '" alt="' . htmlspecialchars($match['partner_name']) . '">';
                                    } else {
                                        echo strtoupper(substr($match['partner_name'], 0, 1));
                                    }
                                    ?>
                                </div>
                                <div class="match-info">
                                    <div class="match-name"><?php echo htmlspecialchars($match['partner_name']); ?></div>
                                    <div class="match-meta">
                                        <span class="match-badge">
                                            <i class="fas fa-graduation-cap"></i>
                                            <?php echo ucfirst($match['partner_role']); ?>
                                        </span>
                                        <span class="match-badge">
                                            <i class="fas fa-book"></i>
                                            <?php echo htmlspecialchars($match['subject']); ?>
                                        </span>
                                    </div>
                                    <?php if ($match['partner_avg_rating']): ?>
                                        <div style="font-size: 0.85rem; color: var(--text-secondary); display: flex; align-items: center; gap: 0.25rem;">
                                            <i class="fas fa-star" style="color: #fbbf24;"></i>
                                            <?php echo number_format($match['partner_avg_rating'], 1); ?> (<?php echo $match['partner_rating_count']; ?> reviews)
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php 
                                $is_receiver = ($match['mentor_id'] == $user['id']);
                                ?>
                                
                                <div class="match-actions">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="openMatchModal(<?php echo htmlspecialchars(json_encode($match)); ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    
                                    <?php if ($is_receiver): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                            <input type="hidden" name="response" value="accepted">
                                            <button type="submit" class="btn btn-success btn-sm" <?php echo !$can_accept_matches ? 'disabled' : ''; ?>>
                                                <i class="fas fa-check"></i> Accept
                                            </button>
                                        </form>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                            <input type="hidden" name="response" value="rejected">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Reject this match?')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <div class="match-status-badge pending-badge">
                                            <i class="fas fa-hourglass-half"></i> Pending
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($accepted_matches)): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-star" style="color: var(--primary-color);"></i>
                        <h3 class="card-title">Active Partnerships (<?php echo count($accepted_matches); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($accepted_matches as $match): ?>
                            <div class="match-card accepted">
                                <div class="match-avatar accepted">
                                    <?php 
                                    if (!empty($match['partner_profile_picture']) && file_exists('../' . $match['partner_profile_picture'])) {
                                        echo '<img src="../' . htmlspecialchars($match['partner_profile_picture']) . '" alt="' . htmlspecialchars($match['partner_name']) . '">';
                                    } else {
                                        echo strtoupper(substr($match['partner_name'], 0, 1));
                                    }
                                    ?>
                                </div>
                                <div class="match-info">
                                    <div class="match-name"><?php echo htmlspecialchars($match['partner_name']); ?></div>
                                    <div class="match-meta">
                                        <span class="match-badge">
                                            <i class="fas fa-graduation-cap"></i>
                                            <?php echo ucfirst($match['partner_role']); ?>
                                        </span>
                                        <span class="match-badge">
                                            <i class="fas fa-book"></i>
                                            <?php echo htmlspecialchars($match['subject']); ?>
                                        </span>
                                    </div>
                                    <div style="font-size: 0.8rem; color: #16a34a; font-weight: 500;">
                                        <i class="fas fa-check-circle"></i> Active since <?php echo date('M j, Y', strtotime($match['updated_at'])); ?>
                                    </div>
                                </div>
                                
                                <div class="match-actions">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="openMatchModal(<?php echo htmlspecialchars(json_encode($match)); ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <a href="../messages/chat.php?match_id=<?php echo $match['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-comment"></i> <span class="hide-on-small">Message</span>
                                    </a>
                                    <a href="../sessions/schedule.php?match_id=<?php echo $match['id']; ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-calendar-plus"></i> <span class="hide-on-small">Schedule</span>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($other_matches)): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history" style="color: var(--text-secondary);"></i>
                        <h3 class="card-title">Match History</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($other_matches as $match): ?>
                            <div class="match-card">
                                <div class="match-avatar" style="background: #94a3b8;">
                                    <?php 
                                    if (!empty($match['partner_profile_picture']) && file_exists('../' . $match['partner_profile_picture'])) {
                                        echo '<img src="../' . htmlspecialchars($match['partner_profile_picture']) . '" alt="' . htmlspecialchars($match['partner_name']) . '">';
                                    } else {
                                        echo strtoupper(substr($match['partner_name'], 0, 1));
                                    }
                                    ?>
                                </div>
                                <div class="match-info">
                                    <div class="match-name"><?php echo htmlspecialchars($match['partner_name']); ?></div>
                                    <div class="match-meta">
                                        <span class="match-badge">
                                            <i class="fas fa-book"></i>
                                            <?php echo htmlspecialchars($match['subject']); ?>
                                        </span>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                        <i class="fas fa-times-circle"></i> <?php echo ucfirst($match['status']); ?> • <?php echo date('M j, Y', strtotime($match['updated_at'])); ?>
                                    </div>
                                </div>
                                <div class="match-actions">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="openMatchModal(<?php echo htmlspecialchars(json_encode($match)); ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($matches)): ?>
                <div class="card">
                    <div class="card-body">
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>No matches yet</h3>
                            <p>Start connecting with study partners to see your matches here.</p>
                            <a href="find.php" class="btn btn-primary" style="margin-top: 1rem;">
                                <i class="fas fa-search"></i> Find Study Partners
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div id="matchModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin: 0;"><i class="fas fa-user-circle"></i> Partner Details</h3>
                <button class="modal-close" onclick="closeMatchModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

            // Set initial state for the button based on the theme set by the <head> script
            let currentTheme = document.documentElement.hasAttribute('data-theme') ? 'dark' : 'light';
            setTheme(currentTheme); // This will set the correct initial icon/text

            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation(); // Prevent profile dropdown from closing
                    let newTheme = document.documentElement.hasAttribute('data-theme') ? 'light' : 'dark';
                    setTheme(newTheme);
                });
            }
            
            // Listen for system preference changes
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
                // Only update if no theme is manually set in localStorage
                // A user who clicks the toggle sets localStorage, so this listener will
                // be ignored, which is the correct behavior.
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
            if (text === null || text === undefined) return ''; // ADDED: Null check
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

        function openMatchModal(match) {
            const modal = document.getElementById('matchModal');
            const modalBody = document.getElementById('modalBody');
            
            let statusBadge = '';
            let statusClass = ''; // ADDED:
            if (match.status === 'accepted') {
                statusClass = 'accepted';
                statusBadge = `<span class="modal-badge accepted"><i class="fas fa-check-circle"></i> Active</span>`;
            } else if (match.status === 'pending') {
                statusClass = 'pending'; // ADDED:
                statusBadge = `<span class="modal-badge pending"><i class="fas fa-hourglass-half"></i> Pending</span>`; // CHANGED:
            }
            
            let profilePicHtml = '';
            if (match.partner_profile_picture && match.partner_profile_picture !== '') {
                // ADDED: class to div
                profilePicHtml = `<div class="modal-avatar ${statusClass}"><img src="../${escapeHtml(match.partner_profile_picture)}" alt="${escapeHtml(match.partner_name)}"></div>`;
            } else {
                // CHANGED: Replaced inline style with class
                profilePicHtml = `<div class="modal-avatar ${statusClass}">${escapeHtml(match.partner_name.substring(0, 1).toUpperCase())}</div>`;
            }
            
            modalBody.innerHTML = `
                <div style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color);">
                    ${profilePicHtml}
                    <div>
                        <h3 style="margin: 0 0 0.5rem 0; font-size: 1.5rem; color: var(--text-primary);">${escapeHtml(match.partner_name)}</h3>
                        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                            <span style="color: var(--text-secondary); font-weight: 500;">${escapeHtml(match.partner_role.charAt(0).toUpperCase() + match.partner_role.slice(1))}</span>
                            ${statusBadge}
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <h4 style="font-size: 1rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-info-circle" style="color: var(--primary-color);"></i> Information
                    </h4>
                    <div class="info-row">
                        <span style="color: var(--text-secondary); font-weight: 500;">Role</span>
                        <span style="color: var(--text-primary); font-weight: 500;">${escapeHtml(match.partner_role.charAt(0).toUpperCase() + match.partner_role.slice(1))}</span>
                    </div>
                    <div class="info-row">
                        <span style="color: var(--text-secondary); font-weight: 500;">Location</span>
                        <span style="color: var(--text-primary); font-weight: 500;">${escapeHtml(match.partner_location || 'Not specified')}</span>
                    </div>
                    <div class="info-row">
                        <span style="color: var(--text-secondary); font-weight: 500;">Subject</span>
                        <span style="color: var(--text-primary); font-weight: 500;">${escapeHtml(match.subject)}</span>
                    </div>
                    <div class="info-row">
                        <span style="color: var(--text-secondary); font-weight: 500;">Match Score</span>
                        <span style="background: linear-gradient(135deg, var(--primary-color) 0%, #1e40af 100%); color: white; padding: 0.25rem 0.75rem; border-radius: 6px; font-weight: 600; font-size: 0.9rem;">${match.match_score}%</span>
                    </div>
                </div>

                ${match.partner_bio ? `
                <div style="margin-bottom: 1.5rem;">
                    <h4 style="font-size: 1rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-quote-left" style="color: var(--primary-color);"></i> About ${escapeHtml(match.partner_name.split(' ')[0])}
                    </h4>
                    <p style="color: var(--text-secondary); line-height: 1.6; margin: 0;">${escapeHtml(match.partner_bio).replace(/\n/g, '<br>')}</p>
                </div>
                ` : ''}

                ${match.status === 'accepted' ? `
                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); flex-wrap: wrap;">
                    <a href="../messages/chat.php?match_id=${match.id}" class="btn btn-primary" style="flex: 1; text-align: center; min-width: 140px;">
                        <i class="fas fa-comment"></i> Message
                    </a>
                    <a href="../sessions/schedule.php?match_id=${match.id}" class="btn btn-outline" style="flex: 1; text-align: center; min-width: 140px;">
                        <i class="fas fa-calendar-plus"></i> Schedule
                    </a>
                </div>
                ` : ''}
            `;
            
            modal.classList.add("show");
            document.body.style.overflow = "hidden";
        }

        function closeMatchModal() {
            const modal = document.getElementById('matchModal');
            modal.classList.remove("show");
            document.body.style.overflow = "auto";
        }

        window.onclick = function(event) {
            const modal = document.getElementById('matchModal');
            if (event.target === modal) {
                closeMatchModal();
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeMatchModal();
            }
        });
    </script>
</body>
</html>