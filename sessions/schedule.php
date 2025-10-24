<?php
require_once '../config/config.php';
require_once '../config/notification_helper.php';
require_once '../lib/PHPMailer.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user) {
    redirect('auth/login.php');
}

$unread_notifications = get_unread_count($user['id']);
$error = '';
$success = '';
$match_id = isset($_GET['match_id']) ? (int)$_GET['match_id'] : 0;
$preselected_date = isset($_GET['date']) ? $_GET['date'] : '';

$db = getDB();

$prefs_stmt = $db->prepare("SELECT * FROM user_reminder_preferences WHERE user_id = ?");
$prefs_stmt->execute([$user['id']]);
$reminder_prefs = $prefs_stmt->fetch();

if (!$reminder_prefs) {
    $db->prepare("INSERT INTO user_reminder_preferences (user_id) VALUES (?)")->execute([$user['id']]);
    $prefs_stmt->execute([$user['id']]);
    $reminder_prefs = $prefs_stmt->fetch();
}

$settings_stmt = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('commission_percentage', 'coa_terms_url')");
$settings = [];
while ($row = $settings_stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$commission_percentage = $settings['commission_percentage'] ?? 10;
$terms_url = $settings['coa_terms_url'] ?? '/terms-and-conditions.php';

// Get user's active matches
$matches_query = "
    SELECT m.id, m.subject,
           CASE 
               WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
               ELSE CONCAT(u1.first_name, ' ', u1.last_name)
           END as partner_name,
           CASE 
               WHEN m.student_id = ? THEN u2.id
               ELSE u1.id
           END as partner_id
    FROM matches m
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE (m.student_id = ? OR m.mentor_id = ?) 
    AND m.status = 'accepted'
    ORDER BY partner_name
";

$stmt = $db->prepare($matches_query);
$stmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
$matches = $stmt->fetchAll();

$no_matches = empty($matches);

// Get existing sessions for calendar display
$sessions_query = "
    SELECT s.session_date, COUNT(*) as session_count
    FROM sessions s
    JOIN matches m ON s.match_id = m.id
    WHERE (m.student_id = ? OR m.mentor_id = ?)
    AND s.status = 'scheduled'
    AND s.session_date >= CURDATE()
    GROUP BY s.session_date
";
$sessions_stmt = $db->prepare($sessions_query);
$sessions_stmt->execute([$user['id'], $user['id']]);
$existing_sessions = $sessions_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Handle form submission
if (!$no_matches && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $selected_match_id = (int)$_POST['match_id'];
        $session_date = $_POST['session_date'];
        $start_time = $_POST['start_time'];
        $duration = (int)$_POST['duration'];
        $location = sanitize_input($_POST['location']);
        $notes = sanitize_input($_POST['notes']);
        $send_reminder = isset($_POST['send_reminder']) ? 1 : 0;
        $terms_accepted = isset($_POST['terms_accepted']) ? 1 : 0;
        
        if (empty($selected_match_id) || empty($session_date) || empty($start_time) || empty($duration)) {
            $error = 'Please fill in all required fields.';
        } elseif (!$terms_accepted) {
            $error = 'You must accept the terms and conditions to schedule a session.';
        } else {
            // Create DateTime objects for validation
            $now = new DateTime();
            $session_datetime = new DateTime($session_date . ' ' . $start_time);
            
            // Calculate minimum allowed time (1 hour from now)
            $minimum_time = clone $now;
            $minimum_time->add(new DateInterval('PT1H'));
            
            // Validate session is not in the past and is at least 1 hour ahead
            if ($session_datetime < $now) {
                $error = 'Session date and time cannot be in the past.';
            } elseif ($session_datetime < $minimum_time) {
                $error = 'Sessions must be scheduled at least 1 hour in advance. Please choose a later time.';
            } else {
                $start_datetime = new DateTime($session_date . ' ' . $start_time);
                $end_datetime = clone $start_datetime;
                $end_datetime->add(new DateInterval('PT' . $duration . 'M'));
                $end_time = $end_datetime->format('H:i:s');
                
                $match_info_stmt = $db->prepare("
                    SELECT m.student_id, m.mentor_id, u.hourly_rate
                    FROM matches m
                    JOIN users u ON u.id = CASE WHEN m.mentor_id != ? THEN m.mentor_id ELSE m.student_id END
                    WHERE m.id = ? AND (m.student_id = ? OR m.mentor_id = ?)
                ");
                $match_info_stmt->execute([$user['id'], $selected_match_id, $user['id'], $user['id']]);
                $match_info = $match_info_stmt->fetch();
                
                if (!$match_info) {
                    $error = 'Invalid match selection.';
                } else {
                    $partner_id = ($match_info['student_id'] == $user['id']) 
                        ? $match_info['mentor_id'] 
                        : $match_info['student_id'];
                    $mentor_id = $match_info['mentor_id'];
                    
                    $hourly_rate = $match_info['hourly_rate'] ?? 0;
                    $payment_amount = ($hourly_rate * $duration) / 60;
                    
                    $conflict_check = $db->prepare("
                        SELECT COUNT(*) as conflict_count,
                               GROUP_CONCAT(DISTINCT 
                                   CASE 
                                       WHEN m.student_id = ? OR m.mentor_id = ? THEN 'you'
                                       WHEN m.student_id = ? OR m.mentor_id = ? THEN 'partner'
                                   END
                               ) as conflicting_parties
                        FROM sessions s
                        JOIN matches m ON s.match_id = m.id
                        WHERE (m.student_id IN (?, ?) OR m.mentor_id IN (?, ?))
                        AND s.session_date = ?
                        AND s.status = 'scheduled'
                        AND (
                            (s.start_time < ? AND s.end_time > ?) OR
                            (s.start_time < ? AND s.end_time > ?) OR
                            (s.start_time >= ? AND s.end_time <= ?)
                        )
                    ");
                    $conflict_check->execute([
                        $user['id'], $user['id'],
                        $partner_id, $partner_id,
                        $user['id'], $partner_id, $user['id'], $partner_id,
                        $session_date,
                        $end_time, $start_time,
                        $end_time, $start_time,
                        $start_time, $end_time
                    ]);
                    $conflict = $conflict_check->fetch();
                    
                    if ($conflict['conflict_count'] > 0) {
                        $conflicting_parties = $conflict['conflicting_parties'];
                        if (strpos($conflicting_parties, 'you') !== false && strpos($conflicting_parties, 'partner') !== false) {
                            $error = 'Both you and your partner already have sessions scheduled during this time. Please choose a different time.';
                        } elseif (strpos($conflicting_parties, 'partner') !== false) {
                            $error = 'Your partner already has a session scheduled during this time. Please choose a different time.';
                        } else {
                            $error = 'You already have a session scheduled during this time. Please choose a different time.';
                        }
                    } else {
                        try {
                            $db->beginTransaction();
                            
                            $stmt = $db->prepare("
                                INSERT INTO sessions (match_id, session_date, start_time, end_time, location, notes, terms_accepted, terms_accepted_at, payment_amount) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                            ");
                            $stmt->execute([$selected_match_id, $session_date, $start_time, $end_time, $location, $notes, $terms_accepted, $payment_amount]);
                            $session_id = $db->lastInsertId();
                            
                            if ($payment_amount > 0) {
                                $commission_amount = ($payment_amount * $commission_percentage) / 100;
                                $commission_stmt = $db->prepare("
                                    INSERT INTO commission_payments (session_id, mentor_id, session_amount, commission_amount, commission_percentage, payment_status)
                                    VALUES (?, ?, ?, ?, ?, 'pending')
                                ");
                                $commission_stmt->execute([$session_id, $mentor_id, $payment_amount, $commission_amount, $commission_percentage]);
                            }
                            
                            $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'session_scheduled', ?, ?)");
                            $log_stmt->execute([$user['id'], json_encode(['match_id' => $selected_match_id, 'date' => $session_date]), $_SERVER['REMOTE_ADDR']]);
                            
                            $db->commit();
                            
                            $email_sent = false;
                            $email_error = '';
                            
                            try {
                                $partner_stmt = $db->prepare("SELECT u.id, u.email, u.first_name, u.last_name FROM users u WHERE u.id = ?");
                                $partner_stmt->execute([$partner_id]);
                                $partner = $partner_stmt->fetch();
                                
                                $match_stmt = $db->prepare("SELECT subject FROM matches WHERE id = ?");
                                $match_stmt->execute([$selected_match_id]);
                                $match = $match_stmt->fetch();
                                
                                $session_details = [
                                    'subject' => $match['subject'],
                                    'date' => $session_date,
                                    'start_time' => $start_time,
                                    'end_time' => $end_time,
                                    'location' => $location,
                                    'notes' => $notes,
                                    'partner_name' => $partner['first_name'] . ' ' . $partner['last_name']
                                ];
                                
                                $result1 = send_session_notification(
                                    $user['email'],
                                    $user['first_name'] . ' ' . $user['last_name'],
                                    $session_details
                                );
                                
                                $session_details['partner_name'] = $user['first_name'] . ' ' . $user['last_name'];
                                $result2 = send_session_notification(
                                    $partner['email'],
                                    $partner['first_name'] . ' ' . $partner['last_name'],
                                    $session_details
                                );
                                
                                $email_sent = $result1 && $result2;
                                
                                if (SMTP_USERNAME === 'your-email@gmail.com' || empty(SMTP_USERNAME)) {
                                    $email_error = 'SMTP not configured. Emails are being logged but not sent.';
                                }
                            } catch (Exception $e) {
                                $email_error = 'Failed to send email notifications: ' . $e->getMessage();
                            }
                            
                            if ($email_sent) {
                                $success = 'Session scheduled successfully! Email notifications sent to both participants.';
                            } else if (!empty($email_error)) {
                                $success = 'Session scheduled successfully! Note: ' . $email_error;
                            } else {
                                $success = 'Session scheduled successfully!';
                            }
                            
                            header("refresh:3;url=history.php");
                        } catch (Exception $e) {
                            $db->rollBack();
                            $error = 'Failed to schedule session. Please try again.';
                        }
                    }
                }
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
    <title>Schedule Session - StudyConnect</title>
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
            background: #f0f7ff;
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
        }

        .profile-icon:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
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
            text-align: center;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
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

        .btn-outline {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .schedule-container {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .calendar-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
            height: fit-content;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .calendar-header h3 {
            font-size: 1.125rem;
            color: #111827;
            font-weight: 600;
        }

        .calendar-nav {
            display: flex;
            gap: 0.5rem;
        }

        .calendar-nav button {
            background: #f3f4f6;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            font-size: 1.2rem;
            color: var(--text-secondary);
        }

        .calendar-nav button:hover {
            background: var(--primary-color);
            color: white;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }

        .calendar-day-header {
            text-align: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            padding: 0.5rem 0;
        }

        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .calendar-day:hover:not(.empty):not(.past) {
            background: #e0e7ff;
            transform: scale(1.05);
        }

        .calendar-day.empty {
            cursor: default;
            color: #d1d5db;
        }

        .calendar-day.past {
            color: #d1d5db;
            cursor: not-allowed;
        }

        .calendar-day.today {
            background: #dbeafe;
            color: var(--primary-color);
            font-weight: 600;
        }

        .calendar-day.selected {
            background: var(--primary-color);
            color: white;
            font-weight: 600;
        }

        .calendar-day.has-session::after {
            content: '';
            position: absolute;
            bottom: 3px;
            left: 50%;
            transform: translateX(-50%);
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #10b981;
        }

        .calendar-day.selected.has-session::after {
            background: white;
        }

        .form-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
        }

        .form-card h3 {
            font-size: 1.25rem;
            color: #111827;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            color: #111827;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: #f9fafb;
            border-radius: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-group label {
            font-size: 0.875rem;
            color: #374151;
            cursor: pointer;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .form-actions .btn {
            flex: 1;
            padding: 0.875rem;
            font-size: 1rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert i {
            font-size: 1.1rem;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        #time-validation-error {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .no-matches-card {
            background: white;
            border-radius: 12px;
            padding: 3rem 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
        }

        .no-matches-card h3 {
            font-size: 1.5rem;
            color: #111827;
            margin-bottom: 1rem;
        }

        .no-matches-card p {
            color: #6b7280;
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

            .schedule-container {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .calendar-card {
                padding: 1.25rem;
            }

            .form-card {
                padding: 1.5rem;
            }

            .form-row {
                grid-template-columns: 1fr;
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
        }

        @media (max-width: 480px) {
            .logo {
                font-size: 1rem;
            }

            .page-header h1 {
                font-size: 1.25rem;
            }

            .calendar-card {
                padding: 1rem;
            }

            .form-card {
                padding: 1.25rem;
            }

            .form-actions {
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
                <li><a href="../messages/index.php"><i class="fas fa-envelope"></i> Messages</a></li>
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

                <!-- Profile Menu -->
                <div class="profile-menu">
                    <button class="profile-icon" onclick="toggleProfileMenu(event)">
                        <i class="fas fa-user"></i>
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
                <h1><i class="fas fa-calendar-plus"></i> Schedule New Session</h1>
                <p class="page-subtitle">Plan your next study session with a partner</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error" style="max-width: 1200px; margin: 0 auto 1.5rem;"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" style="max-width: 1200px; margin: 0 auto 1.5rem;"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($no_matches): ?>
                <div class="no-matches-card">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">📚</div>
                    <h3>No Study Partners Yet</h3>
                    <p>You need to connect with a study partner before scheduling a session.</p>
                    <a href="../matches/find.php" class="btn">
                        <i class="fas fa-search"></i> Find a Study Partner
                    </a>
                </div>
            <?php else: ?>
                <div class="schedule-container">
                    <div class="calendar-card">
                        <div class="calendar-header">
                            <h3 id="calendarMonth">October 2025</h3>
                            <div class="calendar-nav">
                                <button onclick="previousMonth()" title="Previous Month">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button onclick="nextMonth()" title="Next Month">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        <div class="calendar-grid" id="calendarGrid">
                            <!-- Calendar will be generated by JavaScript -->
                        </div>
                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; color: #6b7280;">
                                <div style="display: flex; align-items: center; gap: 0.25rem;">
                                    <div style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></div>
                                    <span>Has sessions</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-card">
                        <h3>Session Details</h3>
                        <form method="POST" action="" id="sessionForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="session_date" id="session_date" value="<?php echo $preselected_date ?: date('Y-m-d'); ?>">
                            
                            <div class="form-group">
                                <label class="form-label">Study Partner & Subject *</label>
                                <select name="match_id" class="form-select" required>
                                    <option value="">Select a study partner</option>
                                    <?php foreach ($matches as $match): ?>
                                        <option value="<?php echo $match['id']; ?>" <?php echo $match_id == $match['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($match['subject']); ?> with <?php echo htmlspecialchars($match['partner_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Start Time *</label>
                                    <input type="time" name="start_time" id="start_time" class="form-input" value="14:00" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Duration (minutes) *</label>
                                    <select name="duration" class="form-select" required>
                                        <option value="30">30 minutes</option>
                                        <option value="45">45 minutes</option>
                                        <option value="60" selected>1 hour</option>
                                        <option value="90">1.5 hours</option>
                                        <option value="120">2 hours</option>
                                        <option value="180">3 hours</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Location</label>
                                <input type="text" name="location" class="form-input" placeholder="e.g., Library, Online (Zoom), Coffee Shop">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Session Notes</label>
                                <textarea name="notes" class="form-textarea" rows="3" placeholder="What topics will you cover? Any specific materials needed?"></textarea>
                            </div>

                            <div class="form-group">
                                <div class="checkbox-group" style="background: #fef3c7; border: 1px solid #fbbf24;">
                                    <input type="checkbox" name="terms_accepted" id="terms_accepted" value="1" required>
                                    <label for="terms_accepted">
                                        I agree to the <a href="<?php echo htmlspecialchars($terms_url); ?>" target="_blank" style="color: var(--primary-color); text-decoration: underline;">Terms and Conditions</a> including the Commission on Agreement (COA) policy *
                                    </label>
                                </div>
                                <?php if ($commission_percentage > 0): ?>
                                    <p style="font-size: 0.75rem; color: #92400e; margin-top: 0.5rem; padding-left: 1.75rem;">
                                        <i class="fas fa-info-circle"></i> Mentors are required to pay <?php echo $commission_percentage; ?>% commission to the admin after each paid session.
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="send_reminder" id="send_reminder" value="1" checked>
                                    <label for="send_reminder">
                                        <i class="fas fa-bell"></i> Send email reminder notification
                                    </label>
                                </div>
                            </div>

                            <div class="form-actions">
                                <a href="history.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <button type="submit" class="btn">
                                    <i class="fas fa-check"></i> Schedule Session
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        let notificationDropdownOpen = false;
        let profileDropdownOpen = false;
        let currentDate = new Date();
        const preselectedDate = '<?php echo $preselected_date ?: date('Y-m-d'); ?>';
        const existingSessions = <?php echo json_encode($existing_sessions); ?>;
        
        if (preselectedDate) {
            currentDate = new Date(preselectedDate);
        }

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

            // Initialize time validation
            initializeTimeValidation();
        });

        function initializeTimeValidation() {
            const dateInput = document.getElementById('session_date');
            const timeInput = document.getElementById('start_time');
            const form = document.getElementById('sessionForm');
            
            function validateDateTime() {
                const selectedDate = dateInput.value;
                const selectedTime = timeInput.value;
                
                if (!selectedDate || !selectedTime) return true;
                
                const now = new Date();
                const selectedDateTime = new Date(selectedDate + 'T' + selectedTime);
                
                // Calculate minimum time (1 hour from now)
                const minimumTime = new Date(now.getTime() + 60 * 60 * 1000);
                
                if (selectedDateTime < now) {
                    showValidationError('The selected date and time is in the past. Please choose a future time.');
                    return false;
                } else if (selectedDateTime < minimumTime) {
                    showValidationError('Sessions must be scheduled at least 1 hour in advance. Please choose a later time.');
                    return false;
                } else {
                    clearValidationError();
                    return true;
                }
            }
            
            function showValidationError(message) {
                clearValidationError();
                
                const errorDiv = document.createElement('div');
                errorDiv.id = 'time-validation-error';
                errorDiv.className = 'alert alert-error';
                errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + message;
                errorDiv.style.marginTop = '1rem';
                
                const durationGroup = document.querySelector('select[name="duration"]').closest('.form-group').parentNode;
                durationGroup.appendChild(errorDiv);
            }
            
            function clearValidationError() {
                const existingError = document.getElementById('time-validation-error');
                if (existingError) {
                    existingError.remove();
                }
            }
            
            if (dateInput) {
                dateInput.addEventListener('change', validateDateTime);
            }
            if (timeInput) {
                timeInput.addEventListener('change', validateDateTime);
                timeInput.addEventListener('blur', validateDateTime);
            }
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (!validateDateTime()) {
                        e.preventDefault();
                        const errorElement = document.getElementById('time-validation-error');
                        if (errorElement) {
                            errorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }
                });
            }
        }

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

        function renderCalendar() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            
            document.getElementById('calendarMonth').textContent = 
                currentDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const selectedDate = document.getElementById('session_date').value;
            
            let html = '';
            const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            dayHeaders.forEach(day => {
                html += `<div class="calendar-day-header">${day}</div>`;
            });
            
            for (let i = 0; i < firstDay; i++) {
                html += '<div class="calendar-day empty"></div>';
            }
            
            for (let day = 1; day <= daysInMonth; day++) {
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const dateObj = new Date(dateStr);
                dateObj.setHours(0, 0, 0, 0);
                
                const isToday = dateStr === new Date().toISOString().split('T')[0];
                const isSelected = dateStr === selectedDate;
                const isPast = dateObj < today;
                const hasSession = existingSessions[dateStr] > 0;
                
                let classes = 'calendar-day';
                if (isToday) classes += ' today';
                if (isSelected) classes += ' selected';
                if (isPast) classes += ' past';
                if (hasSession) classes += ' has-session';
                
                html += `<div class="${classes}" onclick="${!isPast ? `selectDate('${dateStr}')` : ''}" 
                         title="${hasSession ? existingSessions[dateStr] + ' session(s) scheduled' : (isPast ? 'Past date' : '')}">${day}</div>`;
            }
            
            document.getElementById('calendarGrid').innerHTML = html;
        }

        function selectDate(dateStr) {
            document.getElementById('session_date').value = dateStr;
            renderCalendar();
            
            const event = new Event('change');
            document.getElementById('session_date').dispatchEvent(event);
        }

        function previousMonth() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        }

        function nextMonth() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        }

        renderCalendar();
    </script>
</body>
</html>