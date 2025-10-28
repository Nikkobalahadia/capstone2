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
$db = getDB();

// Get filter parameters
$filter_period = isset($_GET['period']) ? $_GET['period'] : 'all';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_subject = isset($_GET['subject']) ? sanitize_input($_GET['subject']) : '';

// Build date filter
$date_condition = '';
$date_params = [];

switch ($filter_period) {
    case 'week':
        $date_condition = 'AND s.session_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        break;
    case 'month':
        $date_condition = 'AND s.session_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        break;
    case 'quarter':
        $date_condition = 'AND s.session_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
        break;
    case 'year':
        $date_condition = 'AND s.session_date >= DATE_SUB(NOW(), INTERVAL 365 DAY)';
        break;
}

// Build status filter
$status_condition = '';
if ($filter_status !== 'all') {
    $status_condition = 'AND s.status = ?';
    $date_params[] = $filter_status;
}

// Build subject filter
$subject_condition = '';
if ($filter_subject) {
    $subject_condition = 'AND m.subject = ?';
    $date_params[] = $filter_subject;
}

// Helper function for displaying stars
function generate_stars($rating) {
    if (!$rating) return '';
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars .= '<i class="fas fa-star ' . ($i <= $rating ? 'filled' : '') . '"></i>';
    }
    return $stars;
}

$upcoming_query = "
    SELECT s.*, m.subject,
           CASE 
               WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
               ELSE CONCAT(u1.first_name, ' ', u1.last_name)
           END as partner_name,
           CASE 
               WHEN m.student_id = ? THEN u2.id
               ELSE u1.id
           END as partner_id,
           TIMESTAMPDIFF(MINUTE, s.start_time, s.end_time) as duration_minutes
    FROM sessions s
    JOIN matches m ON s.match_id = m.id
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE (m.student_id = ? OR m.mentor_id = ?)
    AND (s.session_date > CURDATE() OR (s.session_date = CURDATE() AND s.start_time > CURTIME()))
    AND s.status = 'scheduled'
    $subject_condition
    ORDER BY s.session_date ASC, s.start_time ASC
";

$past_query = "
    SELECT s.*, m.subject,
           CASE 
               WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
               ELSE CONCAT(u1.first_name, ' ', u1.last_name)
           END as partner_name,
           CASE 
               WHEN m.student_id = ? THEN u2.id
               ELSE u1.id
           END as partner_id,
           sr.rating, sr.feedback,
           TIMESTAMPDIFF(MINUTE, s.start_time, s.end_time) as duration_minutes
    FROM sessions s
    JOIN matches m ON s.match_id = m.id
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    LEFT JOIN session_ratings sr ON s.id = sr.session_id AND sr.rater_id = ?
    WHERE (m.student_id = ? OR m.mentor_id = ?)
    AND ((s.session_date < CURDATE() OR (s.session_date = CURDATE() AND s.start_time <= CURTIME())) OR s.status IN ('completed', 'cancelled', 'no_show'))
    $date_condition
    $status_condition
    $subject_condition
    ORDER BY s.session_date DESC, s.start_time DESC
";

$upcoming_params = array_merge([$user['id'], $user['id'], $user['id'], $user['id']], ($filter_subject ? [$filter_subject] : []));
$stmt = $db->prepare($upcoming_query);
$stmt->execute($upcoming_params);
$upcoming_sessions = $stmt->fetchAll();

$past_params = array_merge([$user['id'], $user['id'], $user['id'], $user['id'], $user['id']], $date_params);
$stmt = $db->prepare($past_query);
$stmt->execute($past_params);
$past_sessions = $stmt->fetchAll();

// Get user's subjects for filter
$subjects_stmt = $db->prepare("
    SELECT DISTINCT m.subject 
    FROM sessions s 
    JOIN matches m ON s.match_id = m.id 
    WHERE (m.student_id = ? OR m.mentor_id = ?)
    ORDER BY m.subject
");
$subjects_stmt->execute([$user['id'], $user['id']]);
$user_subjects = $subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

$page_title = "Session History";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo $page_title; ?> - Study Buddy</title>
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
            
            --green-light: #d1fae5;
            --green-dark: #065f46;
            --red-light: #fee2e2;
            --red-dark: #991b1b;
            --blue-light: #dbeafe;
            --blue-dark: #1e40af;
            --gray-light: #f3f4f6;
            --gray-dark: #4b5563;
        }

        [data-theme="dark"] {
            --primary-color: #3b82f6;
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --border-color: #374151;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.3);
            --bg-color: #111827;
            --card-bg: #1f2937;

            --green-light: #064e3b;
            --green-dark: #a7f3d0;
            --red-light: #450a0a;
            --red-dark: #fecaca;
            --blue-light: #1e3a8a;
            --blue-dark: #bfdbfe;
            --gray-light: #374151;
            --gray-dark: #d1d5db;
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

        /* Page Header */
        .page-header {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
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
            background: var(--primary-color);
            color: white;
        }

        .btn:hover {
            background: #1d4ed8;
        }
        
        .btn-secondary {
            background: var(--gray-light);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        [data-theme="dark"] .btn-secondary {
            background: var(--card-bg);
        }
        
        .btn-secondary:hover {
            background: var(--border-color);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-danger-outline {
            background: transparent;
            color: var(--red-dark);
            border: 2px solid var(--red-light);
        }
        
        .btn-danger-outline:hover {
            background: var(--red-light);
            color: var(--red-dark);
        }
        
        [data-theme="dark"] .btn-danger-outline {
            color: var(--red-dark);
            border-color: var(--red-dark);
        }
        
        [data-theme="dark"] .btn-danger-outline:hover {
            background: var(--red-light);
            color: #dc2626;
            border-color: var(--red-light);
        }

        /* ===== Filter Bar ===== */
        .filter-bar {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }
        
        .filter-bar form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1.5rem;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            flex-grow: 1;
        }
        
        .filter-group label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .filter-group label i {
            margin-right: 0.25rem;
        }
        
        .filter-group select {
            width: 100%;
            min-width: 150px;
            padding: 0.6rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.875rem;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            background: var(--bg-color);
        }
        
        .filter-bar .btn {
            padding: 0.6rem 1rem;
            min-height: auto;
            margin-top: auto; /* Aligns with selects */
        }

        /* Session Sections */
        .session-section {
            margin-bottom: 2.5rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
        }

        .session-grid {
            display: grid;
            grid-template-columns: 1fr; /* Single column */
            gap: 1.25rem;
        }

        /* New Card Design */
        .session-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease-in-out;
            border: 1px solid var(--border-color);
            display: flex;
            overflow: hidden;
        }

        .session-card:hover {
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
            transform: translateY(-3px);
            border-color: var(--primary-color);
        }
        
        .session-date-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            flex-shrink: 0;
            width: 90px;
            background: var(--bg-color);
            border-right: 1px solid var(--border-color);
        }
        
        .session-card.upcoming .session-date-box {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1e40af 100%);
            color: white;
            border-right: none;
        }
        
        [data-theme="dark"] .session-card.upcoming .session-date-box {
             background: var(--primary-color);
        }
        
        .session-date-box .day {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }
        
        .session-date-box .month {
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .session-content {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            width: 100%;
        }

        .session-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .session-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .session-partner {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        /* Badges */
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-scheduled { background: var(--blue-light); color: var(--blue-dark); }
        .badge-completed { background: var(--green-light); color: var(--green-dark); }
        .badge-cancelled { background: var(--red-light); color: var(--red-dark); }
        .badge-no_show { background: var(--gray-light); color: var(--gray-dark); }

        .session-details {
            display: flex;
            flex-wrap: wrap;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .detail-icon {
            width: 16px;
            height: 16px;
            color: var(--primary-color);
        }

        .session-actions {
            display: flex;
            gap: 0.75rem;
            padding-top: 1rem;
            margin-top: auto;
            border-top: 1px solid var(--border-color);
        }
        
        .session-footer {
            padding-top: 1rem;
            margin-top: auto;
            border-top: 1px solid var(--border-color);
        }

        .session-actions .btn {
            flex: 1;
            text-align: center;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        /* Rating Display */
        .rating-display {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .rating-display .stars {
            color: #f59e0b; /* Amber */
        }
        
        .rating-display .stars .fa-star:not(.filled) {
            color: var(--border-color);
        }
        
        .rating-display .rating-text {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
        }
        
        .rate-btn {
            width: 100%;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        @media (min-width: 768px) {
            .rate-btn {
                width: auto;
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px dashed var(--border-color);
        }

        .empty-state-icon {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            opacity: 0.6;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
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

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .page-header .btn {
                width: 100%;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }
            
            .filter-bar form {
                flex-direction: column;
                gap: 1rem;
            }
            .filter-group {
                width: 100%;
            }
            .filter-bar .btn {
                width: 100%;
            }

            .session-card {
                flex-direction: column;
            }
            
            .session-date-box {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--border-color);
                flex-direction: row;
                gap: 0.5rem;
            }
            
            .session-card.upcoming .session-date-box {
                border-bottom: none;
            }
            
            .session-content {
                padding: 1.25rem;
            }

            .session-details {
                flex-direction: column;
                gap: 0.75rem;
            }

            .session-title {
                font-size: 1.1rem;
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

            .page-header h1 {
                font-size: 1.25rem;
            }

            .session-content {
                padding: 1rem;
            }

            .session-title {
                font-size: 1rem;
            }

            .session-actions {
                flex-direction: column;
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
                            <h4 style="margin: 0; font-size: 1rem;"><i class="fas fa-bell"></i> Notifications</h4>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <div style="text-align: center; padding: 1.5rem; color: #999;"><i class="fas fa-spinner fa-spin"></i></div>
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
                            <a href="../profile/index.php" class="profile-dropdown-item"><i class="fas fa-user-circle"></i> <span>View Profile</span></a>
                            <?php if (in_array($user['role'], ['mentor'])): ?>
                            <a href="../profile/commission-payments.php" class="profile-dropdown-item"><i class="fas fa-wallet"></i> <span>Commissions</span></a>
                            <?php endif; ?>
                            <a href="../profile/settings.php" class="profile-dropdown-item"><i class="fas fa-sliders-h"></i> <span>Settings</span></a>
                            <button type="button" class="profile-dropdown-item" onclick="toggleTheme()"><i class="fas fa-sun" id="theme-icon"></i> <span id="theme-text">Toggle Theme</span></button>
                            <hr style="margin: 0.5rem 0; border: none; border-top: 1px solid var(--border-color);">
                            <a href="../auth/logout.php" class="profile-dropdown-item logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
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
                    <h1><i class="fas fa-history"></i> Session History</h1>
                    <p class="page-subtitle">View and manage your study sessions</p>
                </div>
                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> <span class="hide-on-small">Back to Sessions</span>
                    </a>
                <a href="schedule.php" class="btn">
                    <i class="fas fa-plus"></i> <span class="hide-on-small">New Session</span>
                </a>
                </div>
            </div>

            <div class="filter-bar">
                <form method="GET" action="">
                    <div class="filter-group">
                        <label for="period"><i class="fas fa-calendar-alt"></i> Period</label>
                        <select name="period" id="period" onchange="this.form.submit()">
                            <option value="all" <?php echo $filter_period == 'all' ? 'selected' : ''; ?>>All Time</option>
                            <option value="week" <?php echo $filter_period == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="month" <?php echo $filter_period == 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="quarter" <?php echo $filter_period == 'quarter' ? 'selected' : ''; ?>>Last 90 Days</option>
                            <option value="year" <?php echo $filter_period == 'year' ? 'selected' : ''; ?>>Last Year</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="status"><i class="fas fa-check-circle"></i> Status (Past)</label>
                        <select name="status" id="status" onchange="this.form.submit()">
                            <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="no_show" <?php echo $filter_status == 'no_show' ? 'selected' : ''; ?>>No Show</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="subject"><i class="fas fa-book"></i> Subject</label>
                        <select name="subject" id="subject" onchange="this.form.submit()">
                            <option value="">All Subjects</option>
                            <?php foreach ($user_subjects as $subject): ?>
                                <option value="<?php echo htmlspecialchars($subject); ?>" <?php echo $filter_subject == $subject ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subject); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <a href="history.php" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </form>
            </div>

            <div class="session-section">
                <h2 class="section-title">Upcoming Sessions</h2>
                <?php if (empty($upcoming_sessions)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="fas fa-calendar-plus"></i></div>
                        <h3>No Upcoming Sessions</h3>
                        <p>You don't have any sessions scheduled yet.</p>
                        <a href="schedule.php" class="btn">
                            <i class="fas fa-calendar-plus"></i> Schedule a Session
                        </a>
                    </div>
                <?php else: ?>
                    <div class="session-grid">
                        <?php foreach ($upcoming_sessions as $session): ?>
                            <div class="session-card upcoming">
                                <div class="session-date-box">
                                    <span class="day"><?php echo date('d', strtotime($session['session_date'])); ?></span>
                                    <span class="month"><?php echo date('M', strtotime($session['session_date'])); ?></span>
                                </div>
                                <div class="session-content">
                                    <div class="session-card-header">
                                        <div>
                                            <div class="session-title"><?php echo htmlspecialchars($session['subject']); ?></div>
                                            <div class="session-partner">with <?php echo htmlspecialchars($session['partner_name']); ?></div>
                                        </div>
                                        <span class="badge badge-scheduled">Scheduled</span>
                                    </div>
                                    <div class="session-details">
                                        <div class="detail-row">
                                            <i class="fas fa-clock detail-icon"></i>
                                            <span><?php echo date('g:i A', strtotime($session['start_time'])); ?> • <?php echo $session['duration_minutes']; ?> min</span>
                                        </div>
                                        <?php if ($session['location']): ?>
                                        <div class="detail-row">
                                            <i class="fas fa-map-marker-alt detail-icon"></i>
                                            <span><?php echo htmlspecialchars($session['location']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="session-actions">
                                        <a href="edit.php?id=<?php echo $session['id']; ?>" class="btn btn-outline">
                                            <i class="fas fa-edit"></i> Reschedule
                                        </a>
                                        <button class="btn btn-danger-outline" onclick="cancelSession(<?php echo $session['id']; ?>)">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="session-section">
                <h2 class="section-title">Past Sessions</h2>
                <?php if (empty($past_sessions) && $filter_period == 'all' && $filter_status == 'all' && $filter_subject == ''): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="fas fa-inbox"></i></div>
                        <h3>No Past Sessions</h3>
                        <p>Your completed or cancelled sessions will appear here.</p>
                    </div>
                <?php elseif (empty($past_sessions)): ?>
                     <div class="empty-state">
                        <div class="empty-state-icon"><i class="fas fa-filter"></i></div>
                        <h3>No Sessions Found</h3>
                        <p>No past sessions match your selected filters.</p>
                        <a href="history.php" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset Filters
                        </a>
                    </div>
                <?php else: ?>
                    <div class="session-grid">
                        <?php foreach ($past_sessions as $session): ?>
                            <div class="session-card past <?php echo $session['status']; ?>">
                                <div class="session-date-box">
                                    <span class="day"><?php echo date('d', strtotime($session['session_date'])); ?></span>
                                    <span class="month"><?php echo date('M', strtotime($session['session_date'])); ?></span>
                                </div>
                                <div class="session-content">
                                    <div class="session-card-header">
                                        <div>
                                            <div class="session-title"><?php echo htmlspecialchars($session['subject']); ?></div>
                                            <div class="session-partner">with <?php echo htmlspecialchars($session['partner_name']); ?></div>
                                        </div>
                                        <span class="badge badge-<?php echo $session['status']; ?>">
                                            <?php echo str_replace('_', ' ', $session['status']); ?>
                                        </span>
                                    </div>
                                    <div class="session-details">
                                        <div class="detail-row">
                                            <i class="fas fa-clock detail-icon"></i>
                                            <span><?php echo date('g:i A', strtotime($session['start_time'])); ?> • <?php echo $session['duration_minutes']; ?> min</span>
                                        </div>
                                        <?php if ($session['location']): ?>
                                        <div class="detail-row">
                                            <i class="fas fa-map-marker-alt detail-icon"></i>
                                            <span><?php echo htmlspecialchars($session['location']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="session-footer">
                                        <?php if ($session['status'] == 'completed'): ?>
                                            <?php if ($session['rating']): ?>
                                                <div class="rating-display">
                                                    <span class="rating-text">You rated:</span>
                                                    <span class="stars"><?php echo generate_stars($session['rating']); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <a href="rate.php?session_id=<?php echo $session['id']; ?>" class="btn btn-outline rate-btn">
                                                    <i class="fas fa-star"></i> Rate Session
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="rating-display">
                                                <span class="rating-text">Rating N/A</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let notificationDropdownOpen = false;
        let profileDropdownOpen = false;

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
        
        // Load theme on initial page load
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
        // --- End Theme Toggle JS ---

        // Mobile Menu Toggle
        document.getElementById('hamburger').addEventListener('click', function() {
            this.classList.toggle('active');
            document.getElementById('navLinks').classList.toggle('active');
        });

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

        function formatMessageTime(dateTime) {
            const dt = new Date(dateTime + ' UTC');
            return dt.toLocaleString(undefined, {
                year: 'numeric', month: 'short', day: 'numeric',
                hour: 'numeric', minute: '2-digit', hour12: true
            });
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
        }, 30000);

        function cancelSession(sessionId) {
            Swal.fire({
                title: 'Cancel Session?',
                text: "Are you sure you want to cancel this session?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#d1d5db',
                confirmButtonText: 'Yes, cancel it',
                cancelButtonText: 'No, keep it',
                background: 'var(--card-bg)', // For dark mode
                color: 'var(--text-primary)'   // For dark mode
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('cancel.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            session_id: sessionId,
                            cancellation_reason: 'Cancelled by user'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Session Cancelled',
                                text: 'The session was successfully cancelled.',
                                confirmButtonColor: '#2563eb',
                                background: 'var(--card-bg)',
                                color: 'var(--text-primary)'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Something went wrong. Please try again.',
                                confirmButtonColor: '#2563eb',
                                background: 'var(--card-bg)',
                                color: 'var(--text-primary)'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error cancelling session:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Request Failed',
                            text: 'Failed to cancel session. Please try again.',
                            confirmButtonColor: '#2563eb',
                            background: 'var(--card-bg)',
                            color: 'var(--text-primary)'
                        });
                    });
                }
            });
        }
    </script>
</body>
</html>