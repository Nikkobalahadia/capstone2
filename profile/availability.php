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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $availability_data = $_POST['availability'] ?? [];
        
        try {
            $db = getDB();
            $db->beginTransaction();
            
            // Clear existing availability
            $clear_stmt = $db->prepare("DELETE FROM user_availability WHERE user_id = ?");
            $clear_stmt->execute([$user['id']]);
            
            // Add new availability
            $insert_stmt = $db->prepare("INSERT INTO user_availability (user_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)");
            
            foreach ($availability_data as $day => $times) {
                if (!empty($times['start']) && !empty($times['end'])) {
                    $insert_stmt->execute([$user['id'], $day, $times['start'], $times['end']]);
                }
            }
            
            $db->commit();
            $success = 'Availability updated successfully!';
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Failed to update availability. Please try again.';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Availability Schedule - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --success-color: #10b981;
            --text-primary: #1a1a1a;
            --text-secondary: #666;
            --border-color: #e5e5e5;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.1);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: #1a1a1a;
            min-height: 100vh;
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
            box-shadow: var(--shadow-sm);
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
            background: var(--gradient-primary);
            color: white;
            cursor: pointer;
            font-size: 1.1rem;
            border: none;
            transition: transform 0.2s, box-shadow 0.2s;
            overflow: hidden;
        }

        .profile-icon:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
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

        /* Main Content */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        main {
            padding: 2rem 0;
            margin-top: 60px;
            min-height: calc(100vh - 60px);
            display: flex;
            align-items: center;
        }

        .form-container {
            max-width: 800px;
            margin: 0 auto;
            width: 100%;
        }

        /* Page Header */
        .page-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header h1 i {
            -webkit-text-fill-color: #667eea;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* Card */
        .card {
            background: white;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            padding: 2.5rem;
            box-shadow: var(--shadow-lg);
        }

        /* Day Card */
        .day-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .day-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--gradient-primary);
            transition: width 0.3s;
        }

        .day-card:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .day-card:hover::before {
            width: 8px;
        }

        .day-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .day-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            flex-shrink: 0;
        }

        .day-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .time-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .time-group {
            position: relative;
        }

        .time-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .time-label i {
            color: var(--primary-color);
        }

        .time-input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.9375rem;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            font-weight: 500;
        }

        .time-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.875rem 1.75rem;
            border-radius: 10px;
            border: none;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            min-height: 48px;
            box-shadow: var(--shadow-sm);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        /* Alerts */
        .alert {
            padding: 1.125rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.9375rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
            box-shadow: var(--shadow-sm);
        }

        .alert i {
            font-size: 1.25rem;
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

        .alert-info {
            background: linear-gradient(135deg, #e0f2fe 0%, #dbeafe 100%);
            color: #1e40af;
            border: 2px solid #60a5fa;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .button-group .btn {
            flex: 1;
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
                padding: 1.5rem 0;
            }

            .container {
                padding: 0 0.75rem;
            }

            .page-header h1 {
                font-size: 1.75rem;
            }

            .page-subtitle {
                font-size: 0.95rem;
            }

            .card {
                padding: 1.5rem;
            }

            .day-card {
                padding: 1.25rem;
            }

            .day-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }

            .day-name {
                font-size: 1.125rem;
            }

            .time-inputs {
                grid-template-columns: 1fr;
            }

            .button-group {
                flex-direction: column;
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
                font-size: 1.5rem;
            }

            .card {
                padding: 1.25rem;
            }

            .day-card {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header Navigation -->
    <header class="header">
        <div class="navbar">
            <button class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <a href="../dashboard.php" class="logo">
                <i class="fas fa-book-open"></i> StudyConnect
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
                            <h4><i class="fas fa-bell"></i> Notifications</h4>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <div style="text-align: center; padding: 1.5rem; color: #999;">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                        <div class="notification-footer">
                            <a href="../notifications/index.php" style="color: var(--primary-color); text-decoration: none; font-weight: 600;">
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
                            <a href="index.php" class="profile-dropdown-item">
                                <i class="fas fa-user-circle"></i>
                                <span>View Profile</span>
                            </a>
                            <?php if (in_array($user['role'], ['mentor'])): ?>
                                <a href="commission-payments.php" class="profile-dropdown-item">
                                    <i class="fas fa-wallet"></i>
                                    <span>Commissions</span>
                                </a>
                            <?php endif; ?>
                            <a href="settings.php" class="profile-dropdown-item">
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
            <div class="form-container">
                <div class="page-header">
                    <h1><i class="fas fa-calendar-alt"></i> Set Your Availability</h1>
                    <p class="page-subtitle">Let others know when you're available for study sessions. Set your schedule for each day of the week.</p>
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
                
                <div class="card">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <?php foreach ($days as $day => $config): ?>
                            <div class="day-card">
                                <div class="day-header">
                                    <div class="day-icon" style="background: <?php echo $config['color']; ?>;">
                                        <i class="fas <?php echo $config['icon']; ?>"></i>
                                    </div>
                                    <div class="day-name"><?php echo ucfirst($day); ?></div>
                                </div>
                                <div class="time-inputs">
                                    <div class="time-group">
                                        <label for="<?php echo $day; ?>_start" class="time-label">
                                            <i class="fas fa-clock"></i> Start Time
                                        </label>
                                        <input type="time" 
                                               id="<?php echo $day; ?>_start" 
                                               name="availability[<?php echo $day; ?>][start]" 
                                               class="time-input" 
                                               value="<?php echo isset($existing_availability[$day]) ? $existing_availability[$day]['start'] : ''; ?>">
                                    </div>
                                    <div class="time-group">
                                        <label for="<?php echo $day; ?>_end" class="time-label">
                                            <i class="fas fa-clock"></i> End Time
                                        </label>
                                        <input type="time" 
                                               id="<?php echo $day; ?>_end" 
                                               name="availability[<?php echo $day; ?>][end]" 
                                               class="time-input"
                                               value="<?php echo isset($existing_availability[$day]) ? $existing_availability[$day]['end'] : ''; ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-lightbulb"></i>
                            <span><strong>Tip:</strong> Leave time fields empty for days when you're not available. You can always update this schedule later from your profile.</span>
                        </div>
                        
                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Availability
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        let notificationDropdownOpen = false;
        let profileDropdownOpen = false;

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

            // Validate time inputs
            const form = document.querySelector('form');
            form.addEventListener('submit', (e) => {
                let hasError = false;
                const dayCards = document.querySelectorAll('.day-card');
                
                dayCards.forEach(card => {
                    const startInput = card.querySelector('input[name*="[start]"]');
                    const endInput = card.querySelector('input[name*="[end]"]');
                    
                    // Reset borders
                    startInput.style.borderColor = '';
                    endInput.style.borderColor = '';
                    
                    // Check if one is filled but not the other
                    if ((startInput.value && !endInput.value) || (!startInput.value && endInput.value)) {
                        hasError = true;
                        startInput.style.borderColor = '#ef4444';
                        endInput.style.borderColor = '#ef4444';
                    }
                    
                    // Check if end time is before start time
                    if (startInput.value && endInput.value && endInput.value <= startInput.value) {
                        hasError = true;
                        endInput.style.borderColor = '#ef4444';
                    }
                });
                
                if (hasError) {
                    e.preventDefault();
                    alert('Please fix the time errors:\n- Fill both start and end times for each day\n- End time must be after start time');
                }
            });
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
                        list.innerHTML = '<div style="text-align: center; padding: 1.5rem; color: #999;"><i class="fas fa-bell-slash"></i><p style="margin-top: 0.5rem;">No notifications</p></div>';
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
    </script>
</body>
</html>