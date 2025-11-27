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
        $commission_block_message = "You have {$overdue_info['overdue_count']} overdue commission payment(s) totaling ₱" . number_format($overdue_info['total_overdue'], 2) . ". Please pay your commissions before accepting new matches or find new partners.";
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

// --- START ADDITION: Fetch and embed partner availability data into $matches ---
foreach ($matches as &$match) {
    $partner_id = $match['partner_id'];
    
    // Fetch matched user's availability slots
    $availability_stmt = $db->prepare("
        SELECT day_of_week, start_time, end_time
        FROM user_availability
        WHERE user_id = ?
        ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), start_time
    ");
    $availability_stmt->execute([$partner_id]);
    $availability_slots = $availability_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group slots by day for easy JSON encoding
    $availability_by_day = [];
    foreach ($availability_slots as $slot) {
        // Format time for display in JS
        $availability_by_day[$slot['day_of_week']][] = date('g:i A', strtotime($slot['start_time'])) . ' - ' . date('g:i A', strtotime($slot['end_time']));
    }
    $match['partner_availability'] = $availability_by_day;
}
unset($match); // Break the reference
// --- END ADDITION ---


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

        /* ===== NAVBAR FIX: ACTIVE LINK STYLES ===== */
        .nav-links .active-nav a {
            color: var(--primary-color) !important;
            font-weight: 600;
            position: relative;
        }
        .nav-links .active-nav a::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -5px;
            width: 100%;
            height: 3px;
            background-color: var(--primary-color);
            border-radius: 2px;
        }
        /* ===== END NAVBAR FIX: ACTIVE LINK STYLES ===== */

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

        /* ===== MODIFICATION 1: ADDED THIS RULE ===== */ 
        .btn.disabled, .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none; /* This prevents clicks and hover effects */
        }
        /* ===== END OF MODIFICATION 1 ===== */ 

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 0.9rem;
        }

        .alert i {
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-error i {
            color: #ef4444;
        }

        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #dcfce7;
        }

        .alert-success i {
            color: #22c55e;
        }

        /* ===== CARDS & MATCHES ===== */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: #f9fafb;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .card-body {
            padding: 1.5rem;
        }

        .match-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .match-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem 1.5rem;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: white;
            transition: box-shadow 0.2s;
        }

        .match-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.08);
        }

        .match-details {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
            min-width: 0;
        }

        .match-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
            overflow: hidden;
            border: 2px solid var(--primary-color);
        }

        .match-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .match-info {
            min-width: 0;
            flex: 1;
        }

        .match-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .match-meta {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .match-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .match-status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            flex-shrink: 0;
        }

        .match-status-badge.accepted-badge {
            background: #dcfce7;
            color: #15803d;
        }

        .match-status-badge.pending-badge {
            background: #fef3c7; /* CHANGED: For light yellow */
            color: #92400e;
        }

        .match-status-badge.rejected-badge, .match-status-badge.cancelled-badge {
            background: #fee2e2;
            color: #b91c1c;
        }

        .match-actions {
            display: flex;
            gap: 0.5rem;
            flex-direction: row;
            flex-shrink: 0;
        }

        .match-actions .btn {
            width: auto;
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 2rem 0;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        /* ===== MODAL STYLES (Match Details) ===== */
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
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(to right, var(--primary-color), #1e40af);
            color: white;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            flex-shrink: 0;
        }

        .modal-header h3 {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.25rem;
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: white;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .modal-close:hover {
            opacity: 0.8;
        }

        .modal-body {
            padding: 1.5rem;
            flex-grow: 1;
        }

        .modal-details-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        .modal-partner-info {
            display: flex;
            gap: 1.5rem;
            align-items: flex-start;
        }

        .modal-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
            overflow: hidden;
            border: 3px solid var(--border-color);
        }

        .modal-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .modal-partner-details {
            flex-grow: 1;
        }

        .modal-partner-details h3 {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .modal-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.3rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .modal-badge.accepted {
            background: #dcfce7;
            color: #15803d;
        }

        .modal-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .modal-badge.rejected, .modal-badge.cancelled {
            background: #fee2e2;
            color: #b91c1c;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px dashed #f0f0f0;
        }

        .info-row span:first-child {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .info-row span:last-child {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        
        .info-section {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .info-section h4 {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.75rem;
        }

        .info-section p {
            font-size: 0.95rem;
            color: var(--text-primary);
            line-height: 1.6;
        }
        
        /* Media Queries */
        @media (max-width: 768px) {
            .navbar {
                padding: 0.75rem 0.5rem;
            }

            .hamburger {
                display: flex;
            }

            /* ===== START MOBILE NAVBAR FIX (find.php drop-down style) ===== */
            .nav-links {
                position: fixed;
                top: 60px; /* Below the header */
                left: 0;
                width: 100%; /* Full width */
                height: auto; /* Auto height based on content */
                max-height: calc(100vh - 60px); /* Max height to fit viewport minus header */
                background: white;
                flex-direction: column;
                padding: 0; 
                align-items: flex-start;
                gap: 0; 
                /* Toggled by JS using display: none/flex, no transform/transition for slide effect */
                display: none; /* Initially hidden, toggled to flex by JS */
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                z-index: 1005; /* Above content, below modal */
                overflow-y: auto; 
                border-top: 1px solid var(--border-color);
            }
            
            .nav-links.active {
                display: flex; /* Show the menu */
            }
            
            .nav-links li {
                width: 100%;
                border-bottom: 1px solid var(--border-color);
            }
            
            .nav-links li:last-child {
                border-bottom: none;
            }
            
            .nav-links li a {
                padding: 1rem 1.5rem; /* Increased padding for better touch targets */
                width: 100%;
                display: block;
            }
            /* ===== END MOBILE NAVBAR FIX ===== */

            .main {
                padding: 1rem 0;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                margin-bottom: 1.5rem;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .match-card {
                flex-direction: column;
                align-items: flex-start;
                padding: 1rem;
            }

            .match-details {
                margin-bottom: 1rem;
                width: 100%;
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
            
            /* ===== NAVBAR FIX: MOBILE ACTIVE LINK STYLES (Keep from previous change) ===== */
            .nav-links .active-nav a::after {
                display: none; /* Hide underline on mobile menu */
            }
            .nav-links .active-nav {
                background-color: rgba(37, 99, 235, 0.1); /* Subtle highlight on mobile */
                border-left: 4px solid var(--primary-color);
            }
            /* ===== END NAVBAR FIX: MOBILE ACTIVE LINK STYLES ===== */
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
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.5);
            background: #18181b;
        }

        [data-theme="dark"] body {
            background: #18181b;
            color: var(--text-primary);
        }

        [data-theme="dark"] .header,
        [data-theme="dark"] .card,
        [data-theme="dark"] .match-card,
        [data-theme="dark"] .modal-content,
        [data-theme="dark"] .notification-dropdown,
        [data-theme="dark"] .profile-dropdown {
            background: #27272a;
            border-color: var(--border-color);
        }

        [data-theme="dark"] .card-header,
        [data-theme="dark"] .notification-item-dropdown,
        [data-theme="dark"] .profile-dropdown-header,
        [data-theme="dark"] .profile-dropdown-menu {
            border-color: #374151;
            background: #27272a;
        }

        [data-theme="dark"] .nav-links a,
        [data-theme="dark"] .notification-bell {
            color: var(--text-secondary);
        }

        [data-theme="dark"] .nav-links a:hover,
        [data-theme="dark"] .notification-bell:hover {
            color: var(--primary-color);
            background: #374151;
        }
        
        /* ===== NAVBAR FIX: DARK MODE ACTIVE LINK STYLES ===== */
        [data-theme="dark"] .nav-links .active-nav a::after {
            background-color: var(--primary-color);
        }
        [data-theme="dark"] .nav-links .active-nav {
            border-left-color: var(--primary-color);
        }
        /* Mobile: use a darker background for active state */
        @media (max-width: 768px) {
            [data-theme="dark"] .nav-links .active-nav {
                background-color: rgba(59, 130, 246, 0.15); 
            }
        }
        /* ===== END NAVBAR FIX: DARK MODE ACTIVE LINK STYLES ===== */

        [data-theme="dark"] .profile-dropdown-item:hover {
            background: #3f3f46;
        }

        [data-theme="dark"] .alert-error {
            background: #450a0a;
            color: #fca5a5;
            border-color: #7f1d1d;
        }

        [data-theme="dark"] .alert-error i {
            color: #f87171;
        }

        [data-theme="dark"] .alert-success {
            background: #064e3b;
            color: #a7f3d0;
            border-color: #047857;
        }

        [data-theme="dark"] .alert-success i {
            color: #34d399;
        }

        [data-theme="dark"] .match-status-badge.accepted-badge,
        [data-theme="dark"] .modal-badge.accepted {
            background: #065f46;
            color: #d1fae5;
        }

        [data-theme="dark"] .match-status-badge.pending-badge,
        [data-theme="dark"] .modal-badge.pending {
            background: #fbbf24; /* CHANGED: */
            color: #78350f; /* CHANGED: */
        }
        
        [data-theme="dark"] .match-status-badge.rejected-badge,
        [data-theme="dark"] .match-status-badge.cancelled-badge,
        [data-theme="dark"] .modal-badge.rejected,
        [data-theme="dark"] .modal-badge.cancelled {
            background: #7f1d1d;
            color: #fecaca;
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
        
        [data-theme="dark"] .notification-list .notification-item-dropdown.unread {
            background: #312e81;
        }
        
        [data-theme="dark"] .profile-dropdown-item.logout:hover {
            background: #450a0a;
        }
        
        [data-theme="dark"] .btn-outline {
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            background: transparent;
        }
        
        [data-theme="dark"] .btn-outline:hover {
            background: #3f3f46;
        }
        
        [data-theme="dark"] .modal-body .info-section p {
            color: var(--text-secondary); /* Ensure message text is readable */
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
                <li class="active-nav"><a href="index.php"><i class="fas fa-handshake"></i> Matches</a></li>
                <li><a href="../sessions/index.php"><i class="fas fa-calendar"></i> Sessions</a></li>
                <li><a href="../messages/index.php"><i class="fas fa-envelope"></i> Messages</a></li>
                <?php if ($user['role'] === 'mentor' || $user['role'] === 'peer'): ?>
                <?php endif; ?>
            </ul>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                
                <div class="profile-menu" id="notificationMenu">
                    <button class="notification-bell" id="notificationBell" onclick="toggleNotifications(event)">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_notifications > 0): ?>
                            <span class="notification-badge"><?php echo $unread_notifications; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h4 style="margin: 0; font-weight: 600; color: var(--text-primary);">Notifications</h4>
                            <a href="../notifications/index.php" style="font-size: 0.85rem; color: var(--primary-color); text-decoration: none;">View All</a>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <div style="text-align: center; padding: 1.5rem; color: #999;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
                        </div>
                        <div class="notification-footer">
                            <button onclick="markAllAsRead()" class="btn btn-outline btn-sm" style="width: 100%;">Mark All As Read</button>
                        </div>
                    </div>
                </div>

                <div class="profile-menu" id="profileMenu">
                    <button class="profile-icon" onclick="toggleProfileMenu(event)">
                        <?php if ($user['profile_picture']): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </button>
                    <div class="profile-dropdown" id="profileDropdown">
                        <div class="profile-dropdown-header">
                            <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                            <div class="user-role"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></div>
                        </div>
                        <div class="profile-dropdown-menu">
                            <a href="../profile/index.php" class="profile-dropdown-item">
                                <i class="fas fa-user-circle"></i> <span>View Profile</span>
                            </a>
                            <?php if (in_array($user['role'], ['mentor', 'peer'])): ?>
                            <a href="../profile/commission-payments.php" class="profile-dropdown-item">
                                <i class="fas fa-wallet"></i> <span>Commissions</span>
                            </a>
                            <?php endif; ?>
                            <a href="../profile/settings.php" class="profile-dropdown-item">
                                <i class="fas fa-sliders-h"></i> <span>Settings</span>
                            </a>
                            <button class="profile-dropdown-item" id="theme-toggle-btn" style="cursor: pointer;">
                                <i class="fas fa-moon" id="theme-toggle-icon"></i> <span id="theme-toggle-text">Dark Mode</span>
                            </button>
                            <hr style="margin: 0.5rem 0; border: none; border-top: 1px solid #f0f0f0;">
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
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-handshake"></i> My Matches</h1>
                    <p class="page-subtitle">Manage your study partnerships and match requests</p>
                </div>
                <a href="find.php" class="btn btn-primary <?php echo !$can_accept_matches ? 'disabled' : ''; ?>" <?php if (!$can_accept_matches): ?> title="<?php echo htmlspecialchars(strip_tags($commission_block_message)); ?>" <?php endif; ?>>
                    <i class="fas fa-search"></i> Find New Partner
                </a>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $success; ?></div>
            </div>
            <?php endif; ?>

            <?php if (!$can_accept_matches): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <p style="margin: 0; font-weight: 500;">Action Blocked:</p>
                    <p style="margin: 0.25rem 0 0 0;"><?php echo htmlspecialchars($commission_block_message); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($pending_matches)): ?>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-hourglass-half" style="color: #f59e0b;"></i>
                    <h3 class="card-title">Pending Requests (<?php echo count($pending_matches); ?>)</h3>
                </div>
                <div class="card-body match-list">
                    <?php foreach ($pending_matches as $match): ?>
                    <?php 
                        $is_receiver = (int)$match['mentor_id'] === $user['id']; 
                        $status_text = $is_receiver ? 'Action Required' : 'Pending Your Partner';
                    ?>
                    <div class="match-card">
                        <div class="match-details">
                            <div class="match-avatar">
                                <?php if ($match['partner_profile_picture']): ?>
                                    <img src="<?php echo htmlspecialchars($match['partner_profile_picture']); ?>" alt="Profile Picture">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <div class="match-info">
                                <div class="match-name"><?php echo htmlspecialchars($match['partner_name']); ?></div>
                                <div class="match-meta">
                                    <span><i class="fas fa-tag"></i> <?php echo ucfirst(htmlspecialchars($match['partner_role'])); ?></span>
                                    <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($match['subject']); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="match-status-badge pending-badge" style="flex-shrink: 0; display: block; margin-right: 1rem; min-width: 140px; text-align: center;">
                            <?php if ($is_receiver): ?>
                                <i class="fas fa-exclamation-circle"></i> Action Required
                            <?php else: ?>
                                <i class="fas fa-hourglass-half"></i> Pending
                            <?php endif; ?>
                        </div>

                        <div class="match-actions">
                            <button type="button" class="btn btn-outline btn-sm" onclick="openMatchModal(<?php echo htmlspecialchars(json_encode($match)); ?>)">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <?php if ($is_receiver): ?>
                                <button type="button" class="btn btn-success btn-sm" onclick="confirmAcceptMatch(<?php echo $match['id']; ?>, '<?php echo generate_csrf_token(); ?>')" <?php echo !$can_accept_matches ? 'disabled' : ''; ?>>
                                    <i class="fas fa-check"></i> Accept
                                </button>
                                <button type="button" class="btn btn-danger btn-sm" onclick="confirmRejectMatch(<?php echo $match['id']; ?>, '<?php echo generate_csrf_token(); ?>')">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            <?php else: ?>
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
                <div class="card-body match-list">
                    <?php foreach ($accepted_matches as $match): ?>
                    <div class="match-card">
                        <div class="match-details">
                            <div class="match-avatar">
                                <?php if ($match['partner_profile_picture']): ?>
                                    <img src="<?php echo htmlspecialchars($match['partner_profile_picture']); ?>" alt="Profile Picture">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <div class="match-info">
                                <div class="match-name"><?php echo htmlspecialchars($match['partner_name']); ?></div>
                                <div class="match-meta">
                                    <span><i class="fas fa-tag"></i> <?php echo ucfirst(htmlspecialchars($match['partner_role'])); ?></span>
                                    <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($match['subject']); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="match-actions">
                            <a href="../messages/chat.php?match_id=<?php echo $match['id']; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-comment"></i> Chat
                            </a>
                            <button type="button" class="btn btn-outline btn-sm" onclick="openMatchModal(<?php echo htmlspecialchars(json_encode($match)); ?>)">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($other_matches)): ?>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-history" style="color: #666;"></i>
                    <h3 class="card-title">Other Matches (Rejected/Cancelled) (<?php echo count($other_matches); ?>)</h3>
                </div>
                <div class="card-body match-list">
                    <?php foreach ($other_matches as $match): ?>
                    <div class="match-card" style="opacity: 0.8;">
                        <div class="match-details">
                            <div class="match-avatar" style="border-color: #999;">
                                <?php if ($match['partner_profile_picture']): ?>
                                    <img src="<?php echo htmlspecialchars($match['partner_profile_picture']); ?>" alt="Profile Picture">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <div class="match-info">
                                <div class="match-name"><?php echo htmlspecialchars($match['partner_name']); ?></div>
                                <div class="match-meta">
                                    <span><i class="fas fa-tag"></i> <?php echo ucfirst(htmlspecialchars($match['partner_role'])); ?></span>
                                    <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($match['subject']); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="match-status-badge <?php echo $match['status']; ?>-badge" style="margin-right: 1rem; flex-shrink: 0;">
                            <span> 
                                <i class="fas fa-times-circle"></i> <?php echo ucfirst($match['status']); ?>
                            </span> 
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
        
        // FIX: Mobile menu toggle to use find.php's drop-down style but keeps body scrolling fix
        document.getElementById('hamburger').addEventListener('click', function() {
            const navLinks = document.getElementById('navLinks');
            navLinks.classList.toggle('active');
            this.classList.toggle('active');

            // FIX: Prevent background scrolling when mobile menu is open
            if (navLinks.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = 'auto';
            }
        });

        // Theme Toggle Logic
        document.addEventListener('DOMContentLoaded', () => {
            const themeToggleBtn = document.getElementById('theme-toggle-btn');
            const themeToggleIcon = document.getElementById('theme-toggle-icon');
            const themeToggleText = document.getElementById('theme-toggle-text');
            const currentTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', currentTheme);

            if (currentTheme === 'dark') {
                themeToggleIcon.classList.remove('fa-moon');
                themeToggleIcon.classList.add('fa-sun');
                themeToggleText.textContent = 'Light Mode';
            } else {
                themeToggleIcon.classList.remove('fa-sun');
                themeToggleIcon.classList.add('fa-moon');
                themeToggleText.textContent = 'Dark Mode';
            }

            themeToggleBtn.addEventListener('click', () => {
                let theme = document.documentElement.getAttribute('data-theme');
                if (theme === 'dark') {
                    theme = 'light';
                    themeToggleIcon.classList.remove('fa-sun');
                    themeToggleIcon.classList.add('fa-moon');
                    themeToggleText.textContent = 'Dark Mode';
                } else {
                    theme = 'dark';
                    themeToggleIcon.classList.remove('fa-moon');
                    themeToggleIcon.classList.add('fa-sun');
                    themeToggleText.textContent = 'Light Mode';
                }
                document.documentElement.setAttribute('data-theme', theme);
                localStorage.setItem('theme', theme);
            });
        });
        /* ADDED: ===== END THEME TOGGLE LOGIC ===== */

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
                    <div class="notification-item-dropdown ${!notif.is_read ? 'unread' : ''}" onclick="handleNotificationClick(${notif.id}, '${notif.link || ''}')">
                        <i class="fas ${getNotificationIcon(notif.type)}" style="color: ${getNotificationColor(notif.type)};"></i>
                        <div>
                            <div style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.25rem;">${escapeHtml(notif.title)}</div>
                            <p style="margin: 0; font-size: 0.85rem; color: var(--text-primary);">${escapeHtml(notif.message)}</p>
                            <small style="color: var(--text-secondary);">${formatMessageTime(notif.created_at)}</small>
                        </div>
                    </div>
                `).join('');

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
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                document.getElementById('notificationList').innerHTML = '<div style="text-align: center; padding: 1.5rem; color: #999;">Failed to load notifications.</div>';
            });
        }
        
        function handleNotificationClick(notificationId, link) {
            // Logic to mark as read and redirect
            fetch(`../api/notifications.php?mark_read=${notificationId}`, { method: 'POST' })
                .then(() => {
                    if (link) {
                        window.location.href = link;
                    }
                    loadNotifications(); // Reload to update badge
                })
                .catch(error => console.error('Error marking notification as read:', error));
        }

        function markAllAsRead() {
            fetch('../api/notifications.php?mark_all_read=true', { method: 'POST' })
                .then(() => {
                    loadNotifications(); // Reload to update badge
                })
                .catch(error => console.error('Error marking all notifications as read:', error));
        }

        function getNotificationIcon(type) {
            switch(type) {
                case 'match_request': return 'fa-handshake';
                case 'match_accepted': return 'fa-check-circle';
                case 'new_session': return 'fa-calendar-plus';
                case 'session_reminder': return 'fa-clock';
                default: return 'fa-info-circle';
            }
        }
        
        function getNotificationColor(type) {
             switch(type) {
                case 'match_request': return '#f59e0b';
                case 'match_accepted': return '#10b981';
                case 'new_session': return '#3b82f6';
                case 'session_reminder': return '#2563eb';
                default: return '#666';
            }
        }

        function formatMessageTime(dateTime) {
            const now = new Date();
            const date = new Date(dateTime.replace(' ', 'T') + 'Z'); // Treat as UTC
            const diffInSeconds = Math.floor((now - date) / 1000);

            if (diffInSeconds < 60) return `${diffInSeconds}s ago`;
            if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
            if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;

            const options = { month: 'short', day: 'numeric' };
            return date.toLocaleDateString(undefined, options);
        }
        
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, (m) => map[m]);
        }

        // Poll for new notifications every 30 seconds
        setInterval(function() {
            fetch('../api/notifications.php?unread_count_only=true')
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
        }, 30000);

        // --- START NEW SWEETALERT FUNCTIONS ---
        function submitMatchResponse(matchId, csrfToken, response) {
            // Dynamically create and submit a form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'index.php';
            
            const matchIdInput = document.createElement('input');
            matchIdInput.type = 'hidden';
            matchIdInput.name = 'match_id';
            matchIdInput.value = matchId;

            const responseInput = document.createElement('input');
            responseInput.type = 'hidden';
            responseInput.name = 'response';
            responseInput.value = response;

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken;

            form.appendChild(matchIdInput);
            form.appendChild(responseInput);
            form.appendChild(csrfInput);

            document.body.appendChild(form);
            form.submit();
        }

        function confirmAcceptMatch(matchId, csrfToken) {
            Swal.fire({
                title: 'Accept Match?',
                text: "By accepting, you confirm you want to start this partnership. You can start messaging and scheduling sessions.",
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-check"></i> Yes, Accept it!',
                cancelButtonText: '<i class="fas fa-times"></i> Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    submitMatchResponse(matchId, csrfToken, 'accepted');
                }
            });
        }

        function confirmRejectMatch(matchId, csrfToken) {
            Swal.fire({
                title: 'Reject Match?',
                text: "Rejecting this match will permanently decline the request. You can still find other partners later.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-thumbs-down"></i> Yes, Reject it!',
                cancelButtonText: '<i class="fas fa-undo"></i> Keep Pending'
            }).then((result) => {
                if (result.isConfirmed) {
                    submitMatchResponse(matchId, csrfToken, 'rejected');
                }
            });
        }
        // --- END NEW SWEETALERT FUNCTIONS ---

        function openMatchModal(match) {
            const modal = document.getElementById('matchModal');
            const modalBody = document.getElementById('modalBody');
            let statusBadge = '';
            let statusClass = '';

            if (match.status === 'accepted') { 
                statusClass = 'accepted'; 
                statusBadge = `<span class="modal-badge accepted"><i class="fas fa-check-circle"></i> Active</span>`; 
            } else if (match.status === 'pending') { 
                statusClass = 'pending'; 
                statusBadge = `<span class="modal-badge pending"><i class="fas fa-hourglass-half"></i> Pending</span>`; 
            } else if (match.status === 'rejected') {
                statusClass = 'rejected';
                statusBadge = `<span class="modal-badge rejected"><i class="fas fa-times-circle"></i> Rejected</span>`;
            } else if (match.status === 'cancelled') {
                statusClass = 'cancelled';
                statusBadge = `<span class="modal-badge cancelled"><i class="fas fa-ban"></i> Cancelled</span>`;
            }
            
            let profilePicHtml = '';
            if (match.partner_profile_picture) {
                profilePicHtml = `<div class="modal-avatar" style="border-color: var(--primary-color);"><img src="${match.partner_profile_picture}" alt="Profile Picture"></div>`;
            } else {
                profilePicHtml = `<div class="modal-avatar" style="border-color: var(--primary-color);"><i class="fas fa-user"></i></div>`;
            }

            const isReceiver = match.mentor_id === <?php echo $user['id']; ?>;
            const partnerFirstName = match.partner_name.split(' ')[0];
            const commissionBlockMessage = '<?php echo $can_accept_matches ? '' : htmlspecialchars(strip_tags($commission_block_message)); ?>';
            const canAcceptMatches = <?php echo $can_accept_matches ? 'true' : 'false'; ?>;

            // --- Displaying availables time from find.php logic ---
            let availabilityHtml = '';
            const availability = match.partner_availability;
            
            if (Object.keys(availability).length > 0) {
                let listItems = '';
                for (const day in availability) {
                    const times = availability[day].join(' | ');
                    // Adapted styles from find.php for consistency
                    listItems += `
                        <li style="padding: 0.25rem 0; display: flex; justify-content: space-between;">
                            <strong style="color: #1e40af; flex-shrink: 0; margin-right: 1rem;">${day}:</strong>
                            <span style="color: #60a5fa; text-align: right; flex-grow: 1;">${times}</span>
                        </li>
                    `;
                }
                availabilityHtml = `
                    <div class="match-availability info-section">
                        <div class="match-availability-title" style="font-weight: 600; font-size: 1rem; color: var(--text-primary); margin-bottom: 0.5rem;">
                            <i class="fas fa-calendar-check" style="color: var(--primary-color); margin-right: 0.5rem;"></i> ${partnerFirstName}'s Available Times:
                        </div>
                        <ul style="list-style: none; padding: 0; margin: 0.5rem 0 0.25rem; font-size: 0.9rem;">
                            ${listItems}
                        </ul>
                    </div>
                `;
            } else {
                availabilityHtml = `
                    <div class="match-availability warning info-section">
                        <div class="match-availability-title" style="font-weight: 600; font-size: 1rem; color: #f97316; margin-bottom: 0.5rem;">
                            <i class="fas fa-exclamation-triangle" style="color: #f97316; margin-right: 0.5rem;"></i> Availability Unknown:
                        </div>
                        <div class="match-availability-text" style="font-size: 0.9rem; color: var(--text-secondary);">
                            **${partnerFirstName}** has not set their availability yet. Consider messaging them to find a suitable time.
                        </div>
                    </div>
                `;
            }
            // --- End Displaying availables time from find.php logic ---


            modalBody.innerHTML = `
                <div class="modal-partner-info">
                    ${profilePicHtml}
                    <div class="modal-partner-details">
                        <h3 style="margin-bottom: 0.25rem;">${match.partner_name}</h3>
                        ${statusBadge}
                        <p style="font-size: 0.9rem; color: var(--text-secondary); margin-top: 0.5rem;">${match.partner_role}, ${match.partner_location || 'Location Unknown'}</p>
                    </div>
                </div>

                <div class="info-section">
                    <div class="info-row">
                        <span>Subject</span>
                        <span>${match.subject}</span>
                    </div>
                    <div class="info-row">
                        <span>Role</span>
                        <span>${match.partner_role.charAt(0).toUpperCase() + match.partner_role.slice(1)}</span>
                    </div>
                    <div class="info-row">
                        <span>Partner Rating</span>
                        <span>
                            ${match.partner_avg_rating ? 
                                `${parseFloat(match.partner_avg_rating).toFixed(1)} <i class="fas fa-star" style="color: #f59e0b;"></i> (${match.partner_rating_count} reviews)` : 
                                'N/A'
                            }
                        </span>
                    </div>
                    <div class="info-row" style="border-bottom: none;">
                        <span>Created On</span>
                        <span>${new Date(match.created_at).toLocaleDateString()}</span>
                    </div>
                </div>

                ${match.partner_bio ? `
                    <div class="info-section">
                        <h4>About ${partnerFirstName}:</h4>
                        <p>${match.partner_bio}</p>
                    </div>
                ` : ''}

                ${match.message ? `
                    <div class="info-section">
                        <h4>Initial Message:</h4>
                        <p style="font-style: italic; color: var(--text-secondary);">${match.message}</p>
                    </div>
                ` : ''}
                
                ${availabilityHtml} 

                ${match.status === 'pending' && isReceiver ? `
                    <div class="info-section" style="padding-bottom: 0;">
                        <h4 style="color: #f59e0b;">Action Required:</h4>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <button type="button" class="btn btn-success" style="width: 100%; flex: 1; min-width: 140px;" 
                                onclick="confirmAcceptMatch(${match.id}, '<?php echo generate_csrf_token(); ?>')" 
                                ${!canAcceptMatches ? `disabled title="${commissionBlockMessage}"` : ''}>
                                <i class="fas fa-check"></i> Accept Match
                            </button>
                            <button type="button" class="btn btn-danger" style="width: 100%; flex: 1; min-width: 140px;" 
                                onclick="confirmRejectMatch(${match.id}, '<?php echo generate_csrf_token(); ?>')">
                                <i class="fas fa-times"></i> Reject Match
                            </button>
                        </div>
                        ${!canAcceptMatches ? `<p style="color: #dc2626; font-size: 0.85rem; margin-top: 1rem; text-align: center;">${commissionBlockMessage}</p>` : ''}
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