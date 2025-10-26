<?php
require_once '../config/config.php';
require_once '../config/notification_helper.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('../auth/login.php');
}

$user = get_logged_in_user();
if (!$user) {
    redirect('../auth/login.php');
}

$unread_notifications = get_unread_count($user['id']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $availability_data = $_POST['availability'] ?? [];
        $has_valid_data = false;
        
        try {
            $db = getDB();
            $db->beginTransaction();
            
            // Clear existing availability
            $clear_stmt = $db->prepare("DELETE FROM user_availability WHERE user_id = ?");
            $clear_stmt->execute([$user['id']]);
            
            // Add new availability
            $insert_stmt = $db->prepare("INSERT INTO user_availability (user_id, day_of_week, start_time, end_time, is_active) VALUES (?, ?, ?, ?, 1)");
            
            foreach ($availability_data as $day => $times) {
                // Basic server-side validation
                if (!empty($times['start']) && !empty($times['end'])) {
                    $start = strtotime($times['start']);
                    $end = strtotime($times['end']);
                    
                    // Handle 00:00:00 as 24:00:00 for end time
                    if ($times['end'] === '00:00:00') {
                        $end = strtotime('24:00:00');
                    }
                    
                    if ($end > $start) {
                        $insert_stmt->execute([$user['id'], $day, $times['start'], $times['end']]);
                        $has_valid_data = true;
                    }
                }
            }
            
            $db->commit();
            $success = 'Availability updated successfully!';
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Failed to update availability. Please try again. ' . $e->getMessage();
        }
    }
}

// Get existing availability
$db = getDB();
$availability_stmt = $db->prepare("SELECT day_of_week, start_time, end_time FROM user_availability WHERE user_id = ? AND is_active = 1");
$availability_stmt->execute([$user['id']]);
$existing_availability = [];
while ($row = $availability_stmt->fetch()) {
    $existing_availability[$row['day_of_week']] = [
        'start' => $row['start_time'],
        'end' => $row['end_time']
    ];
}

$days = [
    'monday' => ['icon' => 'fa-coffee', 'color' => '#ef4444'],
    'tuesday' => ['icon' => 'fa-code', 'color' => '#f59e0b'],
    'wednesday' => ['icon' => 'fa-book', 'color' => '#10b981'],
    'thursday' => ['icon' => 'fa-graduation-cap', 'color' => '#3b82f6'],
    'friday' => ['icon' => 'fa-star', 'color' => '#8b5cf6'],
    'saturday' => ['icon' => 'fa-sun', 'color' => '#f59e0b'],
    'sunday' => ['icon' => 'fa-moon', 'color' => '#6366f1']
];

/**
 * Helper function to generate time options in 30-min intervals.
 */
function generate_time_options($selected_val, $type = 'start') {
    $options_html = '<option value="">Not available</option>';
    $start = strtotime('00:00');
    $end_of_day = strtotime('24:00');
    
    $current = ($type === 'end') ? strtotime('+30 minutes', $start) : $start;
    $limit = ($type === 'start') ? strtotime('23:30') : $end_of_day;
    
    while ($current <= $limit) {
        $time_val = date('H:i:s', $current);
        $time_display = date('g:i A', $current);
        
        if ($time_val === '00:00:00' && $current > $start) {
            $time_display = '12:00 AM (Next Day)';
        }
        
        $selected = ($selected_val === $time_val) ? 'selected' : '';
        $options_html .= "<option value=\"$time_val\" $selected>$time_display</option>";
        
        $current = strtotime('+30 minutes', $current);
    }
    return $options_html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Availability Schedule - Study Buddy</title>
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
            --error-bg: #fee2e2;
            --error-border: #fca5a5;
            --error-text: #991b1b;
        }

        [data-theme="dark"] {
            --primary-color: #3b82f6;
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --border-color: #374151;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.3);
            --bg-color: #111827;
            --card-bg: #1f2937;
            --error-bg: #2f1d1d;
            --error-border: #fca5a5;
            --error-text: #fecaca;
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
            min-height: 100vh;
        }

        /* ===== HEADER & NAVIGATION (from chat.php) ===== */
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
        .nav-links a:hover,
        .nav-links a.active { /* Added active class */
            color: var(--primary-color);
        }

        .nav-actions {
            display: flex; 
            align-items: center; 
            gap: 1rem;
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
        
        /* Mobile Responsive Styles from chat.php */
        @media (max-width: 768px) {
            .hamburger {
                display: flex;
                flex: 1 0 0; /* take 1/3 width */
                justify-content: flex-start; /* align left */
            }
            .navbar {
                padding: 0.75rem 0.5rem;
            }
            .logo {
                font-size: 1.1rem;
                flex: 1 0 0; /* override desktop flex: 1 and take 1/3 width */
                text-align: center; /* center logo text */
                justify-content: center; /* center logo icon+text */
            }
            .nav-actions {
                flex: 1 0 0; /* take 1/3 width */
                justify-content: flex-end; /* align icons to the right */
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
            .notification-dropdown {
                width: calc(100vw - 2rem);
                right: -0.5rem;
            }
            input, select, textarea, button {
                font-size: 16px !important;
            }
        }

        @media (max-width: 480px) {
            .logo {
                font-size: 1rem;
            }
            .nav-actions {
                gap: 0.5rem;
            }
        }
    </style>
    
    <style>
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        main {
            padding: 2rem 0;
            margin-top: 60px;
        }
        
        /* Page Header */
        .page-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        .page-header p {
            font-size: 0.95rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Card */
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            transition: all 0.3s ease;
        }
        .card-body {
            padding: 1.5rem 2rem;
        }
        
        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success {
            background: #dcfce7;
            border: 1px solid #86efac;
            color: #166534;
        }
        .alert-error {
            background: var(--error-bg);
            border: 1px solid var(--error-border);
            color: var(--error-text);
        }

        /* Availability Day Card */
        .day-card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 1rem;
            display: grid;
            grid-template-columns: 80px 1fr auto;
            align-items: center;
            gap: 1.5rem;
            padding: 1rem;
            transition: all 0.3s ease;
        }
        
        /* NEW: Error state for day card */
        .day-card.error {
            border-color: var(--error-border);
            background: var(--error-bg);
        }
        [data-theme="dark"] .day-card.error {
            background: var(--error-bg);
        }

        .day-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            flex-shrink: 0;
            margin: 0 auto;
        }
        .day-header {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            text-transform: capitalize;
        }
        .time-inputs {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .time-inputs span {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        .time-input select {
            width: 140px; /* Increased width for 30-min slots */
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            color: var(--text-primary);
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .time-input select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        /* Button */
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
        }
        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
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
            border: 1px solid var(--border-color);
        }
        .btn-secondary:hover {
            background: rgba(0,0,0,0.1);
        }
        [data-theme="dark"] .btn-secondary:hover {
            background: rgba(255,255,255,0.1);
        }
        
        /* NEW: Utility bar for copy button */
        .availability-utils {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 1rem;
        }

        .btn-group {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        /* Responsive */
        @media (max-width: 640px) {
            .day-card {
                grid-template-columns: 1fr;
                gap: 1rem;
                text-align: center;
            }
            .time-inputs {
                flex-direction: column;
                gap: 0.75rem;
            }
            .time-input select {
                width: 100%;
            }
            .btn-group {
                flex-direction: column;
            }
            .btn {
                width: 100%;
            }
            .availability-utils {
                justify-content: stretch;
            }
            .availability-utils .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<script>
    (function() {
        const theme = localStorage.getItem('theme');
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.body.classList.add('dark-mode');
        } else {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    })();
</script>

<header class="header">
    <nav class="navbar">
        
        <button class="hamburger" id="hamburger-menu" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        
        <a href="../dashboard.php" class="logo">
            <i class="fas fa-brain"></i> Study Buddy
        </a>

        <ul class="nav-links">
            <li><a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="../find_match.php"><i class="fas fa-search"></i> Find a Match</a></li>
            <li><a href="../matches.php"><i class="fas fa-user-friends"></i> Matches</a></li>
            <li><a href="index.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
        </ul>

        <div class="nav-actions">
            <button class="notification-bell" id="notification-bell" aria-label="Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($unread_notifications > 0): ?>
                    <span class="notification-badge"><?php echo $unread_notifications; ?></span>
                <?php endif; ?>
            </button>
            
            <div class="notification-dropdown" id="notification-dropdown">
                <div class="notification-header">
                    <h3 style="font-weight: 600; font-size: 1rem;">Notifications</h3>
                    <button class="btn-sm btn-outline" id="mark-all-read" style="font-size: 0.8rem; padding: 0.25rem 0.5rem;">Mark all as read</button>
                </div>
                <div class="notification-list" id="notification-list">
                    <p style="padding: 1rem; text-align: center; color: var(--text-secondary);">Loading notifications...</p>
                </div>
                <div class="notification-footer">
                    <a href="../notifications.php">View all notifications</a>
                </div>
            </div>

            <div class="profile-menu">
                <button class="profile-icon" id="profile-icon" aria-label="Profile menu">
                    <?php if ($user['profile_picture'] && file_exists('../' . $user['profile_picture'])): ?>
                        <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile">
                    <?php else: ?>
                        <span><?php echo strtoupper(substr($user['first_name'], 0, 1)); ?></span>
                    <?php endif; ?>
                </button>
                <div class="profile-dropdown" id="profile-dropdown">
                    <div class="profile-dropdown-header">
                        <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="user-role"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></div>
                    </div>
                    <div class="profile-dropdown-menu">
                        <a href="index.php" class="profile-dropdown-item">
                            <i class="fas fa-user-circle"></i> View Profile
                        </a>
                        <a href="settings.php" class="profile-dropdown-item">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <button class="profile-dropdown-item" id="dark-mode-toggle">
                            <i class="fas fa-moon"></i> <span>Dark Mode</span>
                        </button>
                        <a href="../auth/logout.php" class="profile-dropdown-item logout">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</header>

<main>
    <div class="container">
        
        <div class="page-header">
            <h1>📅 Set Your Availability</h1>
            <p>Let matches know when you're free. Select your available time slots in 30-minute intervals for each day.</p>
        </div>

        <div id="form-error" class="alert alert-error" style="display: none;"></div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="availability-utils">
            <button type="button" id="copy-to-all" class="btn btn-secondary btn-sm">
                <i class="fas fa-copy"></i> Apply Monday to All
            </button>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="" id="availability-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <?php foreach ($days as $day => $props): 
                        $start_time = $existing_availability[$day]['start'] ?? '';
                        $end_time = $existing_availability[$day]['end'] ?? '';
                    ?>
                        <div class="day-card" data-day="<?php echo $day; ?>">
                            <div class="day-icon" style="background-color: <?php echo $props['color']; ?>;">
                                <i class="fas <?php echo $props['icon']; ?>"></i>
                            </div>
                            <div class="day-header">
                                <?php echo htmlspecialchars(ucfirst($day)); ?>
                            </div>
                            <div class="time-inputs">
                                <div class="time-input">
                                    <select name="availability[<?php echo $day; ?>][start]" aria-label="<?php echo $day; ?> start time" class="time-select start-time">
                                        <?php echo generate_time_options($start_time, 'start'); ?>
                                    </select>
                                </div>
                                <span>to</span>
                                <div class="time-input">
                                    <select name="availability[<?php echo $day; ?>][end]" aria-label="<?php echo $day; ?> end time" class="time-select end-time">
                                        <?php echo generate_time_options($end_time, 'end'); ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="btn-group">
                        <a href="index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Availability</button>
                    </div>
                </form>
            </div>
        </div>
        
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const hamburger = document.querySelector('.hamburger');
        const navLinks = document.querySelector('.nav-links');
        const profileIcon = document.getElementById('profile-icon');
        const profileDropdown = document.getElementById('profile-dropdown');
        const notificationBell = document.getElementById('notification-bell');
        const notificationDropdown = document.getElementById('notification-dropdown');
        const notificationList = document.getElementById('notification-list');
        const markAllReadBtn = document.getElementById('mark-all-read');
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        const darkModeToggleText = darkModeToggle.querySelector('span');
        const darkModeToggleIcon = darkModeToggle.querySelector('i');

        let notificationDropdownOpen = false;

        // Hamburger Menu
        if (hamburger) {
            hamburger.addEventListener('click', () => {
                hamburger.classList.toggle('active');
                navLinks.classList.toggle('active');
            });
        }

        // Profile Dropdown
        if (profileIcon) {
            profileIcon.addEventListener('click', (event) => {
                event.stopPropagation();
                profileDropdown.classList.toggle('show');
                notificationDropdown.classList.remove('show');
                notificationDropdownOpen = false;
            });
        }

        // Notification Dropdown
        if (notificationBell) {
            notificationBell.addEventListener('click', (event) => {
                event.stopPropagation();
                notificationDropdown.classList.toggle('show');
                profileDropdown.classList.remove('show');
                notificationDropdownOpen = notificationDropdown.classList.contains('show');
                
                if (notificationDropdownOpen) {
                    loadNotifications();
                }
            });
        }

        // Close dropdowns on outside click
        document.addEventListener('click', (event) => {
            if (profileDropdown && !profileDropdown.contains(event.target) && !profileIcon.contains(event.target)) {
                profileDropdown.classList.remove('show');
            }
            if (notificationDropdown && !notificationDropdown.contains(event.target) && !notificationBell.contains(event.target)) {
                notificationDropdown.classList.remove('show');
                notificationDropdownOpen = false;
            }
        });

        // Dark Mode Toggle
        if (darkModeToggle) {
            // Set initial state of the toggle
            if (document.body.classList.contains('dark-mode')) {
                darkModeToggleText.textContent = 'Light Mode';
                darkModeToggleIcon.classList.replace('fa-moon', 'fa-sun');
            } else {
                darkModeToggleText.textContent = 'Dark Mode';
                darkModeToggleIcon.classList.replace('fa-sun', 'fa-moon');
            }

            darkModeToggle.addEventListener('click', () => {
                document.body.classList.toggle('dark-mode');
                const isDarkMode = document.body.classList.contains('dark-mode');
                
                if (isDarkMode) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    localStorage.setItem('theme', 'dark');
                    darkModeToggleText.textContent = 'Light Mode';
                    darkModeToggleIcon.classList.replace('fa-moon', 'fa-sun');
                } else {
                    document.documentElement.setAttribute('data-theme', 'light');
                    localStorage.setItem('theme', 'light');
                    darkModeToggleText.textContent = 'Dark Mode';
                    darkModeToggleIcon.classList.replace('fa-sun', 'fa-moon');
                }
            });
        }

        // Notification Functions
        function loadNotifications() {
            notificationList.innerHTML = '<p style="padding: 1rem; text-align: center; color: var(--text-secondary);">Loading...</p>';
            fetch('../api/notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        notificationList.innerHTML = '';
                        if (data.notifications.length === 0) {
                            notificationList.innerHTML = '<p style="padding: 1rem; text-align: center; color: var(--text-secondary);">No new notifications.</p>';
                        } else {
                            data.notifications.forEach(item => {
                                const el = document.createElement('div');
                                el.className = 'notification-item-dropdown';
                                if (!item.is_read) {
                                    el.classList.add('unread');
                                }
                                el.dataset.id = item.id;
                                el.innerHTML = `
                                    <div style="font-size: 1.25rem; color: var(--primary-color); padding-top: 0.25rem;"><i class="fas ${item.icon}"></i></div>
                                    <div>
                                        <p style="color: var(--text-primary); margin-bottom: 0.25rem;">${item.message}</p>
                                        <small style="color: var(--text-secondary);">${item.time_ago}</small>
                                    </div>
                                `;
                                el.addEventListener('click', () => {
                                    window.location.href = item.link;
                                });
                                notificationList.appendChild(el);
                            });
                        }
                        updateNotificationBadge(data.unread_count);
                    }
                })
                .catch(err => {
                    notificationList.innerHTML = '<p style="padding: 1rem; text-align: center; color: #dc2626;">Failed to load notifications.</p>';
                });
        }

        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', () => {
                fetch('../api/notifications.php?action=mark_all_read', { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            loadNotifications();
                        }
                    });
            });
        }
        
        function updateNotificationBadge(count) {
            const badge = document.querySelector('.notification-badge');
            if (count > 0) {
                if (badge) {
                    badge.textContent = count;
                } else {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'notification-badge';
                    newBadge.textContent = count;
                    notificationBell.appendChild(newBadge);
                }
            } else if (badge) {
                badge.remove();
            }
        }

        // Auto-update notification badge
        setInterval(() => {
            if (!notificationDropdownOpen) {
                fetch('../api/notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateNotificationBadge(data.unread_count);
                        }
                    });
            } else {
                // If dropdown is open, refresh the list
                loadNotifications();
            }
        }, 30000); // Poll every 30 seconds

        
        // --- NEW AVAILABILITY SCRIPT ---

        const copyBtn = document.getElementById('copy-to-all');
        const availabilityForm = document.getElementById('availability-form');
        const errorDiv = document.getElementById('form-error');

        // "Apply Monday to All" functionality
        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                const mondayStart = document.querySelector('select[name="availability[monday][start]"]').value;
                const mondayEnd = document.querySelector('select[name="availability[monday][end]"]').value;
                
                const allDays = ['tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                
                allDays.forEach(day => {
                    document.querySelector(`select[name="availability[${day}][start]"]`).value = mondayStart;
                    document.querySelector(`select[name="availability[${day}][end]"]`).value = mondayEnd;
                });
            });
        }

        // Form Validation
        if (availabilityForm) {
            availabilityForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Stop submission to validate first
                
                let isValid = true;
                let errorMsg = '';
                const dayCards = document.querySelectorAll('.day-card');
                
                // Clear all previous errors
                errorDiv.style.display = 'none';
                errorDiv.textContent = '';
                dayCards.forEach(card => card.classList.remove('error'));
                
                dayCards.forEach(card => {
                    const startSelect = card.querySelector('.start-time');
                    const endSelect = card.querySelector('.end-time');
                    let startTime = startSelect.value;
                    let endTime = endSelect.value;
                    
                    // Rule 1: Cannot have one time selected but not the other
                    if ((startTime && !endTime) || (!startTime && endTime)) {
                        isValid = false;
                        errorMsg = 'Please select both a start and end time, or set both to "Not available".';
                        card.classList.add('error');
                    }
                    
                    // Rule 2: End time must be after start time
                    if (startTime && endTime) {
                        // Handle midnight case for "end" time
                        let endValue = (endTime === '00:00:00') ? '24:00:00' : endTime;
                        
                        if (startTime >= endValue) {
                            isValid = false;
                            errorMsg = 'End time must be after start time.';
                            card.classList.add('error');
                        }
                    }
                });
                
                if (isValid) {
                    availabilityForm.submit(); // All good, submit the form
                } else {
                    errorDiv.textContent = errorMsg;
                    errorDiv.style.display = 'flex';
                    // Scroll to the first error
                    const firstError = document.querySelector('.day-card.error');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            });
        }
    });
</script>

</body>
</html>