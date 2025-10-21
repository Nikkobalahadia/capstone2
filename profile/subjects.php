<?php
require_once '../config/config.php';
require_once '../includes/subjects_hierarchy.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('../auth/login.php');
}

$user = get_logged_in_user();
if (!$user) {
    redirect('../auth/login.php');
}

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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Manage Subjects - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --text-primary: #1a1a1a;
            --text-secondary: #666;
            --border-color: #e5e5e5;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.05);
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

        .nav-links a:hover,
        .nav-links a.active {
            color: var(--primary-color);
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
            padding: 0;
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
            font-weight: 600;
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
            align-items: flex-start;
        }

        .notification-item-dropdown:hover {
            background: #fafafa;
        }

        .notification-item-dropdown.unread {
            background: #f0f7ff;
        }

        .notification-item-dropdown i {
            flex-shrink: 0;
            margin-top: 2px;
        }

        .notification-footer {
            padding: 0.75rem;
            text-align: center;
            border-top: 1px solid #f0f0f0;
        }

        .notification-footer a {
            text-decoration: none;
            color: var(--primary-color);
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.2s;
        }

        .notification-footer a:hover {
            color: #1d4ed8;
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

        /* ===== MAIN CONTENT ===== */
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

        /* ===== ALERTS ===== */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #dcfce7;
            border: 1px solid #86efac;
            color: #166534;
        }

        .alert-success::before {
            content: '\f058';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
        }

        .alert-error {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }

        .alert-error::before {
            content: '\f06a';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
        }

        /* ===== GRID LAYOUT ===== */
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        /* ===== CARDS ===== */
        .card {
            background: white;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: box-shadow 0.2s;
        }

        .card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }

        .card-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
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

        /* ===== FORM ELEMENTS ===== */
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
            border-radius: 6px;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-control:disabled {
            background-color: #f5f5f5;
            color: #999;
            cursor: not-allowed;
        }

        .form-hint {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.4rem;
            display: block;
        }

        /* ===== BUTTONS ===== */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            border: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            min-height: 44px;
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: #e5e5e5;
        }

        .btn-outline {
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            background: white;
        }

        .btn-outline:hover {
            background: #f9f9f9;
            border-color: var(--text-primary);
        }

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            min-height: 36px;
        }

        .btn-full {
            width: 100%;
        }

        /* ===== SUBJECT ITEMS ===== */
        .subject-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 0.75rem;
            gap: 1rem;
            transition: all 0.2s;
        }

        .subject-item:hover {
            border-color: var(--primary-color);
            background: #f9fbff;
        }

        .subject-item.learning {
            background: #f0f7ff;
            border-color: #bfdbfe;
        }

        .subject-item.teaching {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }

        .subject-info {
            flex: 1;
            min-width: 0;
        }

        .subject-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            word-break: break-word;
        }

        .subject-level {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .proficiency-badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .proficiency-beginner {
            background: #fee2e2;
            color: #991b1b;
        }

        .proficiency-intermediate {
            background: #fed7aa;
            color: #92400e;
        }

        .proficiency-advanced {
            background: #dcfce7;
            color: #166534;
        }

        .proficiency-expert {
            background: #dbeafe;
            color: #1e40af;
        }

        .subject-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 2.5rem;
            color: #e5e5e5;
            margin-bottom: 0.75rem;
            display: block;
        }

        .empty-state p {
            font-size: 0.9rem;
        }

        /* ===== SECTION HEADER ===== */
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .section-header i {
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        /* ===== FOOTER ===== */
        .footer-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
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

            .grid {
                grid-template-columns: 1fr;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .subject-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .subject-actions {
                width: 100%;
                flex-direction: row;
            }

            .subject-actions .btn {
                flex: 1;
                min-width: 0;
            }

            .form-control,
            .btn,
            input,
            select,
            textarea {
                font-size: 16px !important;
            }

            .footer-actions {
                flex-direction: column;
            }

            .footer-actions .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.25rem;
                gap: 0.5rem;
            }

            .page-header h1 i {
                font-size: 1.5rem;
            }

            .card-body {
                padding: 1rem;
            }

            .btn-sm {
                padding: 0.375rem 0.5rem;
                font-size: 0.75rem;
            }

            .container {
                padding: 0 0.75rem;
            }

            main {
                padding: 1rem 0;
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
                <li><a href="index.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../matches/index.php"><i class="fas fa-handshake"></i> Matches</a></li>
                <li><a href="../sessions/index.php"><i class="fas fa-calendar"></i> Sessions</a></li>
            </ul>

            <!-- Right Icons -->
            <div style="display: flex; align-items: center; gap: 1rem;">
                <!-- Notifications -->
                <div style="position: relative;">
                    <button class="notification-bell" onclick="toggleNotifications(event)" title="Notifications">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
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
                            <a href="../notifications/index.php"><i class="fas fa-arrow-right"></i> View All</a>
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
                    <div style="padding: 0.5rem 0;">
                        <a href="index.php" class="profile-dropdown-item">
                            <i class="fas fa-user-circle"></i>
                            <span>View Profile</span>
                        </a>
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
    </header>

    <main>
        <div class="container">
            <div class="page-header">
                <h1><i class="fas fa-book"></i> Manage Your Subjects</h1>
                <p class="page-subtitle">
                    <?php if ($user['role'] === 'student'): ?>
                        Add subjects you want to learn and track your progress.
                    <?php elseif ($user['role'] === 'mentor'): ?>
                        Add subjects you can teach at Advanced or Expert level.
                    <?php else: ?>
                        Add subjects you want to learn or teach to connect with others.
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="grid">
                <!-- Add Subject Card -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Add New Subject</h3>
                        <p class="card-subtitle">
                            <?php if ($user['role'] === 'student'): ?>
                                Select a subject you'd like to learn or improve in.
                            <?php elseif ($user['role'] === 'mentor'): ?>
                                Add subjects you're qualified to teach.
                            <?php else: ?>
                                Choose a subject and your proficiency level.
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_subject">
                            
                            <div class="form-group">
                                <label for="main_subject" class="form-label">Main Subject</label>
                                <select id="main_subject" name="main_subject" class="form-control" required>
                                    <option value="">Select a subject</option>
                                    <?php foreach (getSubjectsHierarchy() as $main => $subtopics): ?>
                                        <option value="<?php echo htmlspecialchars($main); ?>">
                                            <?php echo htmlspecialchars($main); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="subtopic" class="form-label">Subtopic <span style="color: #999;">(Optional)</span></label>
                                <select id="subtopic" name="subtopic" class="form-control" disabled>
                                    <option value="">First select a main subject</option>
                                </select>
                                <span class="form-hint">Choose a specific area within your subject</span>
                            </div>

                            <div class="form-group">
                                <label for="proficiency_level" class="form-label">Proficiency Level</label>
                                <select id="proficiency_level" name="proficiency_level" class="form-control" required>
                                    <option value="">Select level</option>
                                    <?php if ($user['role'] === 'student'): ?>
                                        <option value="beginner">Beginner - Just starting out</option>
                                        <option value="intermediate">Intermediate - Some experience</option>
                                        <option value="advanced">Advanced - Strong knowledge</option>
                                        <option value="expert">Expert - Mastered the subject</option>
                                    <?php elseif ($user['role'] === 'mentor'): ?>
                                        <option value="advanced">Advanced - Strong knowledge</option>
                                        <option value="expert">Expert - Can mentor others</option>
                                    <?php else: ?>
                                        <optgroup label="Learning">
                                            <option value="beginner">Beginner - Just starting out</option>
                                            <option value="intermediate">Intermediate - Some experience</option>
                                        </optgroup>
                                        <optgroup label="Teaching">
                                            <option value="advanced">Advanced - Strong knowledge</option>
                                            <option value="expert">Expert - Can teach others</option>
                                        </optgroup>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary btn-full">
                                <i class="fas fa-plus"></i> Add Subject
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Your Subjects Card -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <?php if ($user['role'] === 'student'): ?>
                                Your Subjects (<?php echo count($user_subjects); ?>)
                            <?php elseif ($user['role'] === 'mentor'): ?>
                                Teaching Subjects (<?php echo count($teaching_subjects); ?>)
                            <?php else: ?>
                                Your Subjects
                            <?php endif; ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <!-- Peer: Learning & Teaching Sections -->
                        <?php if ($user['role'] === 'peer'): ?>
                            <div style="margin-bottom: 1.5rem;">
                                <div class="section-header">
                                    <i class="fas fa-book-open"></i>
                                    <span>Learning (<?php echo count($learning_subjects); ?>)</span>
                                </div>
                                <?php if (empty($learning_subjects)): ?>
                                    <div class="empty-state" style="padding: 1.5rem;">
                                        <i class="fas fa-inbox"></i>
                                        <p>No learning subjects yet</p>
                                    </div>
                                <?php else: ?>
                                    <div>
                                        <?php foreach ($learning_subjects as $subject): ?>
                                            <div class="subject-item learning">
                                                <div class="subject-info">
                                                    <div class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                                    <div class="subject-level">
                                                        <span class="proficiency-badge proficiency-<?php echo $subject['proficiency_level']; ?>">
                                                            <?php echo ucfirst($subject['proficiency_level']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="subject-actions">
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="action" value="remove_subject">
                                                        <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Remove this subject?')">
                                                            <i class="fas fa-trash"></i> Remove
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div style="border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                                <div class="section-header">
                                    <i class="fas fa-chalkboard-user"></i>
                                    <span>Teaching (<?php echo count($teaching_subjects); ?>)</span>
                                </div>
                                <?php if (empty($teaching_subjects)): ?>
                                    <div class="empty-state" style="padding: 1.5rem;">
                                        <i class="fas fa-inbox"></i>
                                        <p>No teaching subjects yet</p>
                                    </div>
                                <?php else: ?>
                                    <div>
                                        <?php foreach ($teaching_subjects as $subject): ?>
                                            <div class="subject-item teaching">
                                                <div class="subject-info">
                                                    <div class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                                    <div class="subject-level">
                                                        <span class="proficiency-badge proficiency-<?php echo $subject['proficiency_level']; ?>">
                                                            <?php echo ucfirst($subject['proficiency_level']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="subject-actions">
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="action" value="remove_subject">
                                                        <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Remove this subject?')">
                                                            <i class="fas fa-trash"></i> Remove
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                        <!-- Mentor: Teaching Only -->
                        <?php elseif ($user['role'] === 'mentor'): ?>
                            <?php if (empty($teaching_subjects)): ?>
                                <div class="empty-state" style="padding: 2rem;">
                                    <i class="fas fa-inbox"></i>
                                    <p>No teaching subjects yet. Add subjects to start mentoring!</p>
                                </div>
                            <?php else: ?>
                                <div>
                                    <?php foreach ($teaching_subjects as $subject): ?>
                                        <div class="subject-item teaching">
                                            <div class="subject-info">
                                                <div class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                                <div class="subject-level">
                                                    <span class="proficiency-badge proficiency-<?php echo $subject['proficiency_level']; ?>">
                                                        <?php echo ucfirst($subject['proficiency_level']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="subject-actions">
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="remove_subject">
                                                    <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Remove this subject?')">
                                                        <i class="fas fa-trash"></i> Remove
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                        <!-- Student: Learning Only -->
                        <?php else: ?>
                            <?php if (empty($user_subjects)): ?>
                                <div class="empty-state" style="padding: 2rem;">
                                    <i class="fas fa-inbox"></i>
                                    <p>No subjects added yet. Start by adding your first subject!</p>
                                </div>
                            <?php else: ?>
                                <div>
                                    <?php foreach ($user_subjects as $subject): ?>
                                        <div class="subject-item learning">
                                            <div class="subject-info">
                                                <div class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                                <div class="subject-level">
                                                    <span class="proficiency-badge proficiency-<?php echo $subject['proficiency_level']; ?>">
                                                        <?php echo ucfirst($subject['proficiency_level']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="subject-actions">
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="remove_subject">
                                                    <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Remove this subject?')">
                                                        <i class="fas fa-trash"></i> Remove
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="footer-actions">
                <a href="index.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Profile
                </a>
                <a href="../dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Dashboard
                </a>
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

        // Notification Toggle
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

        // Profile Menu Toggle
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

        // Load Notifications
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

        // Handle Notification Click
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

        // Get Notification Icon
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

        // Get Notification Color
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

        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Time Ago Format
        function timeAgo(dateString) {
            const date = new Date(dateString);
            const seconds = Math.floor((new Date() - date) / 1000);
            if (seconds < 60) return 'Just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
            if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
            return Math.floor(seconds / 604800) + 'w ago';
        }

        // Close Dropdowns on Outside Click
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

        // Refresh Notifications Badge Every 30 Seconds
        setInterval(() => {
            if (notificationDropdownOpen) {
                loadNotifications();
            } else {
                fetch('../api/notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        const badge = document.getElementById('notificationBadge');
                        if (data.unread_count > 0) {
                            badge.textContent = data.unread_count;
                            badge.style.display = 'block';
                        } else {
                            badge.style.display = 'none';
                        }
                    });
            }
        }, 30000);

        // Subjects hierarchy data
        const subjectsHierarchy = <?php echo json_encode(getSubjectsHierarchy()); ?>;

        // Cascading Dropdown for Subtopics
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
    </script>
</body>
</html>