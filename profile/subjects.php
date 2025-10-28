<?php
require_once '../config/config.php';
require_once '../includes/subjects_hierarchy.php';
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
$db = getDB();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_subject') {
            $main_subject = trim($_POST['main_subject']);
            $subtopic = !empty($_POST['subtopic']) ? trim($_POST['subtopic']) : null;
            $proficiency_level = $_POST['proficiency_level'];
            
            $valid_levels = ['beginner', 'intermediate', 'advanced', 'expert'];
            if ($user['role'] === 'mentor') {
                $valid_levels = ['advanced', 'expert'];
            }
            
            $subject_name = !empty($subtopic) ? $main_subject . ' - ' . $subtopic : $main_subject;
            
            if (!empty($main_subject) && in_array($proficiency_level, $valid_levels)) {
                $check_stmt = $db->prepare("SELECT id FROM user_subjects WHERE user_id = ? AND subject_name = ?");
                $check_stmt->execute([$user['id'], $subject_name]);
                
                if ($check_stmt->fetch()) {
                    $error = "You already have this subject in your list.";
                } else {
                    $insert_stmt = $db->prepare("INSERT INTO user_subjects (user_id, subject_name, proficiency_level, main_subject, subtopic) VALUES (?, ?, ?, ?, ?)");
                    if ($insert_stmt->execute([$user['id'], $subject_name, $proficiency_level, $main_subject, $subtopic])) {
                        $message = "Subject added successfully!";
                    } else {
                        $error = "Failed to add subject. Please try again.";
                    }
                }
            } else {
                $error = "Please provide a valid subject and proficiency level.";
            }
        } elseif ($_POST['action'] === 'remove_subject') {
            $subject_id = (int)$_POST['subject_id'];
            $delete_stmt = $db->prepare("DELETE FROM user_subjects WHERE id = ? AND user_id = ?");
            if ($delete_stmt->execute([$subject_id, $user['id']])) {
                $message = "Subject removed successfully!";
            } else {
                $error = "Failed to remove subject. Please try again.";
            }
        } elseif ($_POST['action'] === 'update_subject') {
            $subject_id = (int)$_POST['subject_id'];
            $proficiency_level = $_POST['proficiency_level'];
            
            $valid_levels = ['beginner', 'intermediate', 'advanced', 'expert'];
            if ($user['role'] === 'mentor') {
                $valid_levels = ['advanced', 'expert'];
            }
            
            if (in_array($proficiency_level, $valid_levels)) {
                $update_stmt = $db->prepare("UPDATE user_subjects SET proficiency_level = ? WHERE id = ? AND user_id = ?");
                if ($update_stmt->execute([$proficiency_level, $subject_id, $user['id']])) {
                    $message = "Subject updated successfully!";
                } else {
                    $error = "Failed to update subject. Please try again.";
                }
            } else {
                $error = "Please select a valid proficiency level.";
            }
        }
    }
}

// Fetch user subjects based on role
if ($user['role'] === 'peer') {
    $learning_stmt = $db->prepare("SELECT * FROM user_subjects WHERE user_id = ? AND proficiency_level IN ('beginner', 'intermediate') ORDER BY subject_name");
    $learning_stmt->execute([$user['id']]);
    $learning_subjects = $learning_stmt->fetchAll();
    
    $teaching_stmt = $db->prepare("SELECT * FROM user_subjects WHERE user_id = ? AND proficiency_level IN ('advanced', 'expert') ORDER BY subject_name");
    $teaching_stmt->execute([$user['id']]);
    $teaching_subjects = $teaching_stmt->fetchAll();
} elseif ($user['role'] === 'mentor') {
    $teaching_stmt = $db->prepare("SELECT * FROM user_subjects WHERE user_id = ? ORDER BY subject_name");
    $teaching_stmt->execute([$user['id']]);
    $teaching_subjects = $teaching_stmt->fetchAll();
} else {
    $subjects_stmt = $db->prepare("SELECT * FROM user_subjects WHERE user_id = ? ORDER BY subject_name");
    $subjects_stmt->execute([$user['id']]);
    $user_subjects = $subjects_stmt->fetchAll();
}

$all_subjects = getSubjectsHierarchy();

// Determine proficiency levels for the form
$proficiency_options = [
    'beginner' => 'Beginner (I want to learn)',
    'intermediate' => 'Intermediate (I have some knowledge)',
    'advanced' => 'Advanced (I can teach this)',
    'expert' => 'Expert (I have mastery in this)'
];

if ($user['role'] === 'mentor') {
    $proficiency_options = [
        'advanced' => 'Advanced (I can teach this)',
        'expert' => 'Expert (I have mastery in this)'
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Manage Subjects - Study Buddy</title>
    
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
        .nav-links a.active {
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        main {
            padding: 2rem 0;
            margin-top: 60px;
            min-height: calc(100vh - 60px);
        }
        .page-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
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
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }

        /* Grid */
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        /* Card */
        .card {
            background: var(--card-bg);
            border-radius: 10px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: box-shadow 0.2s, background-color 0.3s ease, border-color 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }
        
        .card-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        [data-theme="dark"] .card-header {
            background: rgba(0, 0, 0, 0.2);
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }
        .card-subtitle {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
            line-height: 1.5;
        }
        .card-body {
            padding: 1.25rem;
        }

        /* Form */
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            background: var(--card-bg);
            color: var(--text-primary);
            transition: border-color 0.2s, box-shadow 0.2s, background-color 0.3s ease, color 0.3s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .form-control:disabled {
            background: var(--bg-color);
            color: var(--text-secondary);
            cursor: not-allowed;
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
            border: 1px solid var(--border-color);
        }
        .btn-secondary:hover {
            background: rgba(0,0,0,0.1);
        }
        [data-theme="dark"] .btn-secondary:hover {
            background: rgba(255,255,255,0.1);
        }
        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }
        .btn-danger:hover {
            background: #fecaca;
        }
        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            min-height: 36px;
        }

        /* Subject List */
        .subject-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .subject-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        .subject-item:last-child {
            border-bottom: none;
        }
        .subject-info {
            flex: 1;
            margin-right: 1rem;
        }
        .subject-name {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        .subject-level {
            font-size: 0.85rem;
            color: var(--text-secondary);
            text-transform: capitalize;
        }
        .subject-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        .empty-state i {
            font-size: 2.5rem;
            opacity: 0.5;
            margin-bottom: 1rem;
            display: block;
        }

        .hide-on-small {
            display: inline;
        }
        
        /* Mobile Responsive */
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
            .notification-dropdown {
                width: calc(100vw - 2rem);
                right: -0.5rem;
            }
            .grid {
                grid-template-columns: 1fr;
            }
            main {
                padding: 1rem 0;
            }
            .page-header h1 {
                font-size: 1.5rem;
            }
            .container {
                padding: 0 0.75rem;
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
        }
    </style>
</head>
<body>

<header class="header">
    <nav class="navbar">
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
                        <div style="text-align: center; padding: 1.5rem; color: #999;">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
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
                        <a href="index.php" class="profile-dropdown-item">
                            <i class="fas fa-user-circle"></i> <span>View Profile</span>
                        </a>
                        <?php if (in_array($user['role'], ['mentor'])): ?>
                            <a href="commission-payments.php" class="profile-dropdown-item">
                                <i class="fas fa-wallet"></i> <span>Commissions</span>
                            </a>
                        <?php endif; ?>
                        <a href="settings.php" class="profile-dropdown-item">
                            <i class="fas fa-sliders-h"></i> <span>Settings</span>
                        </a>
                        <button type="button" class="profile-dropdown-item" onclick="toggleTheme()">
                            <i class="fas fa-sun" id="theme-icon"></i> <span id="theme-text">Toggle Theme</span>
                        </button>
                        <hr style="margin: 0.5rem 0; border: none; border-top: 1px solid var(--border-color);">
                        <a href="../auth/logout.php" class="profile-dropdown-item logout">
                            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
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
            <div>
                <h1><i class="fas fa-book"></i> Manage Your Subjects</h1>
                <?php if ($user['role'] === 'peer'): ?>
                    <p class="page-subtitle">Add subjects you want to learn and subjects you can teach.</p>
                <?php elseif ($user['role'] === 'mentor'): ?>
                    <p class="page-subtitle">Add the subjects you are an expert in and can mentor.</p>
                <?php endif; ?>
            </div>
            <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> <span class="hide-on-small">Back to Profile</span></a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $message; ?></div>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h2 class="card-title">Add New Subject</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_subject">
                    <div class="grid">
                        <div class="form-group">
                            <label for="main_subject" class="form-label">Subject Category</label>
                            <select id="main_subject" name="main_subject" class="form-control" required>
                                <option value="">Select a subject category...</option>
                                <?php foreach ($all_subjects as $subject => $subtopics): ?>
                                    <option value="<?php echo htmlspecialchars($subject); ?>"><?php echo htmlspecialchars($subject); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="subtopic" class="form-label">Subtopic (Optional)</label>
                            <select id="subtopic" name="subtopic" class="form-control" disabled>
                                <option value="">Select subtopic (optional)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="proficiency_level" class="form-label">Your Proficiency</label>
                        <select id="proficiency_level" name="proficiency_level" class="form-control" required>
                            <option value="">Select your level...</option>
                            <?php foreach ($proficiency_options as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Subject</button>
                </form>
            </div>
        </div>
        
        <?php if ($user['role'] === 'peer'): ?>
            <div class="grid">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Subjects I'm Learning</h2>
                        <p class="card-subtitle">Topics you are a beginner or intermediate in.</p>
                    </div>
                    <div class="card-body">
                        <?php if (empty($learning_subjects)): ?>
                            <div class="empty-state">
                                <i class="fas fa-search"></i>
                                You haven't added any subjects to learn yet.
                            </div>
                        <?php else: ?>
                            <ul class="subject-list">
                                <?php foreach ($learning_subjects as $subject): ?>
                                    <li class="subject-item">
                                        <div class="subject-info">
                                            <div class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                            <div class="subject-level"><?php echo htmlspecialchars($subject['proficiency_level']); ?></div>
                                        </div>
                                        <div class="subject-actions">
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="action" value="remove_subject">
                                                <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove this subject?');"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Subjects I'm Teaching</h2>
                        <p class="card-subtitle">Topics you are advanced or expert in.</p>
                    </div>
                    <div class="card-body">
                        <?php if (empty($teaching_subjects)): ?>
                            <div class="empty-state">
                                <i class="fas fa-chalkboard-teacher"></i>
                                You haven't added any subjects to teach yet.
                            </div>
                        <?php else: ?>
                            <ul class="subject-list">
                                <?php foreach ($teaching_subjects as $subject): ?>
                                    <li class="subject-item">
                                        <div class="subject-info">
                                            <div class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                            <div class="subject-level"><?php echo htmlspecialchars($subject['proficiency_level']); ?></div>
                                        </div>
                                        <div class="subject-actions">
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="action" value="remove_subject">
                                                <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove this subject?');"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        <?php elseif ($user['role'] === 'mentor'): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Subjects I'm Mentoring</h2>
                    <p class="card-subtitle">Topics you are advanced or expert in.</p>
                </div>
                <div class="card-body">
                    <?php if (empty($teaching_subjects)): ?>
                        <div class="empty-state">
                            <i class="fas fa-chalkboard-teacher"></i>
                            You haven't added any subjects to mentor yet.
                        </div>
                    <?php else: ?>
                        <ul class="subject-list">
                            <?php foreach ($teaching_subjects as $subject): ?>
                                <li class="subject-item">
                                    <div class="subject-info">
                                        <div class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                        <div class="subject-level"><?php echo htmlspecialchars($subject['proficiency_level']); ?></div>
                                    </div>
                                    <div class="subject-actions">
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="action" value="remove_subject">
                                            <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove this subject?');"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</main>

<script>
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

    let notificationDropdownOpen = false;
    let profileDropdownOpen = false;

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

    function loadNotifications() {
        const list = document.getElementById('notificationList');
        list.innerHTML = '<div style="text-align: center; padding: 1.5rem; color: #999;"><i class="fas fa-spinner fa-spin"></i></div>';
        
        fetch('../api/notifications.php?mark_read=true')
            .then(response => response.json())
            .then(data => {
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
            })
            .catch(() => {
                list.innerHTML = '<div style="text-align: center; padding: 1.5rem; color: #999;">Failed to load</div>';
            });
    }

    function formatMessageTime(dateTime) {
        const dt = new Date(dateTime + ' UTC');
        return dt.toLocaleString(undefined, {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: 'numeric', minute: '2-digit', hour12: true
        });
    }

    document.addEventListener('click', function(event) {
        const hamburger = document.getElementById('hamburger');
        const navLinks = document.getElementById('navLinks');
        
        if (hamburger && navLinks && !hamburger.contains(event.target) && !navLinks.contains(event.target)) {
            hamburger.classList.remove('active');
            navLinks.classList.remove('active');
        }
        
        if (notificationDropdownOpen) {
            const notifDropdown = document.getElementById('notificationDropdown');
            const notifBell = document.querySelector('.notification-bell');
            if (!notifDropdown.contains(event.target) && !notifBell.contains(event.target)) {
                notificationDropdownOpen = false;
                notifDropdown.classList.remove('show');
            }
        }
        
        if (profileDropdownOpen) {
            const profDropdown = document.getElementById('profileDropdown');
            const profIcon = document.querySelector('.profile-icon');
            if (!profDropdown.contains(event.target) && !profIcon.contains(event.target)) {
                profileDropdownOpen = false;
                profDropdown.classList.remove('show');
            }
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

    // Subjects hierarchy data and cascading dropdown
    document.addEventListener('DOMContentLoaded', function() {
        const subjectsHierarchy = <?php echo json_encode(getSubjectsHierarchy()); ?>;
        const mainSubjectSelect = document.getElementById('main_subject');
        const subtopicSelect = document.getElementById('subtopic');
        
        if (mainSubjectSelect) {
            mainSubjectSelect.addEventListener('change', function() {
                const selectedSubject = this.value;
                
                subtopicSelect.innerHTML = '<option value="">Select subtopic (optional)</option>';
                subtopicSelect.disabled = true;
                
                if (selectedSubject && subjectsHierarchy[selectedSubject]) {
                    subtopicSelect.disabled = false;
                    
                    subjectsHierarchy[selectedSubject].forEach(function(subtopic) {
                        const option = document.createElement('option');
                        option.value = subtopic;
                        option.textContent = subtopic;
                        subtopicSelect.appendChild(option);
                    });
                }
            });
        }
    });
</script>

</body>
</html>