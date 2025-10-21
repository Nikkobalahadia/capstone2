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

// Get user subjects
$db = getDB();
if ($user['role'] === 'peer') {
    // Learning subjects (beginner/intermediate)
    $learning_stmt = $db->prepare("SELECT id, subject_name, proficiency_level, main_subject, subtopic FROM user_subjects WHERE user_id = ? AND proficiency_level IN ('beginner', 'intermediate') ORDER BY main_subject, subtopic");
    $learning_stmt->execute([$user['id']]);
    $learning_subjects = $learning_stmt->fetchAll();
    
    // Teaching subjects (advanced/expert)
    $teaching_stmt = $db->prepare("SELECT id, subject_name, proficiency_level, main_subject, subtopic FROM user_subjects WHERE user_id = ? AND proficiency_level IN ('advanced', 'expert') ORDER BY main_subject, subtopic");
    $teaching_stmt->execute([$user['id']]);
    $teaching_subjects = $teaching_stmt->fetchAll();
} else {
    // For students and mentors, keep original query
    $subjects_stmt = $db->prepare("SELECT id, subject_name, proficiency_level, main_subject, subtopic FROM user_subjects WHERE user_id = ? ORDER BY main_subject, subtopic");
    $subjects_stmt->execute([$user['id']]);
    $user_subjects = $subjects_stmt->fetchAll();
}

// Get user availability
$availability_stmt = $db->prepare("SELECT day_of_week, start_time, end_time FROM user_availability WHERE user_id = ? AND is_active = 1 ORDER BY FIELD(day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')");
$availability_stmt->execute([$user['id']]);
$availability = $availability_stmt->fetchAll();

// Get user statistics
$stats_stmt = $db->prepare("
    SELECT 
        (SELECT COUNT(*) FROM matches WHERE (student_id = ? OR mentor_id = ?) AND status = 'accepted') as active_matches,
        (SELECT COUNT(*) FROM sessions s JOIN matches m ON s.match_id = m.id WHERE (m.student_id = ? OR m.mentor_id = ?) AND s.status = 'completed') as completed_sessions,
        (SELECT AVG(rating) FROM session_ratings WHERE rated_id = ?) as avg_rating
");
$stats_stmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
$stats = $stats_stmt->fetch();

$feedback_stmt = $db->prepare("
    SELECT sr.*, 
           u.first_name, u.last_name, u.username, u.role,
           s.session_date,
           m.subject
    FROM session_ratings sr
    JOIN users u ON sr.rater_id = u.id
    JOIN sessions s ON sr.session_id = s.id
    JOIN matches m ON s.match_id = m.id
    WHERE sr.rated_id = ?
    ORDER BY sr.created_at DESC
    LIMIT 10
");
$feedback_stmt->execute([$user['id']]);
$feedbacks = $feedback_stmt->fetchAll();

$role_info = [];
if ($user['role'] === 'student') {
    // Get student-specific information
    $student_stmt = $db->prepare("SELECT learning_goals, preferred_learning_style FROM users WHERE id = ?");
    $student_stmt->execute([$user['id']]);
    $role_info = $student_stmt->fetch();
} elseif ($user['role'] === 'mentor') {
    // Get mentor-specific information  
    $mentor_stmt = $db->prepare("SELECT teaching_style FROM users WHERE id = ?");
    $mentor_stmt->execute([$user['id']]);
    $role_info = $mentor_stmt->fetch();
} elseif ($user['role'] === 'peer') {
    // Get peer-specific information (both learning and teaching)
    $peer_stmt = $db->prepare("SELECT learning_goals, preferred_learning_style, teaching_style FROM users WHERE id = ?");
    $peer_stmt->execute([$user['id']]);
    $role_info = $peer_stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>My Profile - StudyConnect</title>
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
        }

        /* Card Styles */
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
            justify-content: space-between;
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

        /* Button Styles */
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
            background: #f0f0f0;
            color: #1a1a1a;
        }

        .btn-secondary:hover {
            background: #e5e5e5;
        }

        /* Grid Styles */
        .grid {
            display: grid;
        }

        .grid-cols-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        .grid-cols-3 {
            grid-template-columns: repeat(3, 1fr);
        }

        /* Badge Styles */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge-primary {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-secondary {
            background: #f3f4f6;
            color: #4b5563;
        }

        /* Star Rating */
        .star-rating {
            color: #fbbf24;
            display: inline-flex;
            gap: 0.125rem;
        }

        .star-rating .star {
            font-size: 1rem;
        }

        .star-rating .star.filled {
            color: #f59e0b;
        }

        .star-rating .star.empty {
            color: #e5e7eb;
        }

        /* Text Utilities */
        .text-secondary {
            color: var(--text-secondary);
        }

        .text-center {
            text-align: center;
        }

        .font-semibold {
            font-weight: 600;
        }

        .font-medium {
            font-weight: 500;
        }

        .text-sm {
            font-size: 0.875rem;
        }

        .text-xs {
            font-size: 0.75rem;
        }

        .mb-2 {
            margin-bottom: 0.5rem;
        }

        .mb-3 {
            margin-bottom: 0.75rem;
        }

        .mb-4 {
            margin-bottom: 1rem;
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

            .container {
                padding: 0 0.75rem;
            }

            .grid-cols-2,
            .grid-cols-3 {
                grid-template-columns: 1fr;
            }

            .card-body {
                padding: 1rem;
            }

            input, select, textarea, button {
                font-size: 16px !important;
            }

            .notification-dropdown {
                width: calc(100vw - 2rem);
                right: 0;
                left: 1rem;
            }
        }

        @media (max-width: 480px) {
            .logo {
                font-size: 1rem;
            }

            .btn {
                padding: 0.625rem 1rem;
                font-size: 0.85rem;
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
                            <a href="../notifications/index.php"><i class="fas fa-arrow-right"></i> View All</a>
                        </div>
                    </div>
                </div>

                <!-- Profile Menu -->
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
            <div class="grid grid-cols-3" style="gap: 2rem;">
                <!-- Profile Info -->
                <div style="grid-column: span 2;">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">Profile Information</h3>
                            <a href="edit.php" class="btn btn-primary">Edit Profile</a>
                        </div>
                        <div class="card-body">
                            <!-- Add profile picture display section -->
                            <div style="display: flex; align-items: center; gap: 2rem; margin-bottom: 2rem; padding-bottom: 2rem; border-bottom: 1px solid var(--border-color);">
                                <div style="position: relative;">
                                    <?php if (!empty($user['profile_picture']) && file_exists('../' . $user['profile_picture'])): ?>
                                        <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                                             alt="Profile Picture" 
                                             style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--primary-color);">
                                    <?php else: ?>
                                        <div style="width: 120px; height: 120px; border-radius: 50%; background: var(--primary-color); display: flex; align-items: center; justify-content: center; color: white; font-size: 2.5rem; font-weight: 700;">
                                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h2 style="margin: 0 0 0.5rem 0; font-size: 1.75rem; font-weight: 700;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                                    <p style="margin: 0 0 0.5rem 0; color: var(--text-secondary); font-size: 1.1rem;">@<?php echo htmlspecialchars($user['username']); ?></p>
                                    <span class="badge <?php echo $user['role'] === 'peer' ? 'badge-warning' : 'badge-primary'; ?>">
                                        <?php echo $user['role'] === 'peer' ? '🤝 Peer (Learn & Teach)' : ucfirst($user['role']); ?>
                                    </span>
                                    <?php if ($user['is_verified']): ?>
                                        <span class="badge badge-success">Verified</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2" style="gap: 2rem;">
                                <div>
                                    <h4 class="font-semibold mb-2">Basic Information</h4>
                                    <div class="mb-3">
                                        <strong>Name:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Role:</strong> <span style="color: var(--primary-color);"><?php echo ucfirst($user['role']); ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Grade Level:</strong> <?php echo htmlspecialchars($user['grade_level'] ?? 'Not set'); ?>
                                    </div>
                                    <?php if ($user['strand']): ?>
                                        <div class="mb-3">
                                            <strong>Strand:</strong> <?php echo htmlspecialchars($user['strand']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($user['course']): ?>
                                        <div class="mb-3">
                                            <strong>Course:</strong> <?php echo htmlspecialchars($user['course']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mb-3">
                                        <strong>Location:</strong> <?php echo htmlspecialchars($user['location'] ?? 'Not set'); ?>
                                    </div>
                                    <?php if ($user['role'] === 'mentor'): ?>
                                        <div class="mb-3">
                                            <strong>Hourly Rate:</strong> 
                                            <?php if ($user['hourly_rate'] && $user['hourly_rate'] > 0): ?>
                                                <span style="color: #16a34a; font-weight: 600;">₱<?php echo number_format($user['hourly_rate'], 2); ?>/hour</span>
                                            <?php else: ?>
                                                <span style="color: #f59e0b;">Not set</span>
                                                <a href="edit.php" style="color: var(--primary-color); font-size: 0.875rem;">(Set your rate)</a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h4 class="font-semibold mb-2">About Me</h4>
                                    <p class="text-secondary"><?php echo nl2br(htmlspecialchars($user['bio'] ?? 'No bio provided yet.')); ?></p>
                                    
                                    <?php if ($user['role'] === 'student' && $role_info): ?>
                                        <?php if (!empty($role_info['learning_goals'])): ?>
                                            <div style="margin-top: 1rem;">
                                                <h5 class="font-semibold mb-2">Learning Goals</h5>
                                                <p class="text-secondary"><?php echo nl2br(htmlspecialchars($role_info['learning_goals'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($role_info['preferred_learning_style'])): ?>
                                            <div style="margin-top: 1rem;">
                                                <h5 class="font-semibold mb-2">Preferred Learning Style</h5>
                                                <span class="badge badge-secondary"><?php echo ucfirst(str_replace('_', ' ', $role_info['preferred_learning_style'])); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($user['role'] === 'mentor' && $role_info): ?>
                                        <?php if (!empty($role_info['teaching_style'])): ?>
                                            <div style="margin-top: 1rem;">
                                                <h5 class="font-semibold mb-2">Teaching Style</h5>
                                                <p class="text-secondary"><?php echo nl2br(htmlspecialchars($role_info['teaching_style'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($user['role'] === 'peer' && $role_info): ?>
                                        <?php if (!empty($role_info['learning_goals'])): ?>
                                            <div style="margin-top: 1rem;">
                                                <h5 class="font-semibold mb-2">🎓 Learning Goals</h5>
                                                <p class="text-secondary"><?php echo nl2br(htmlspecialchars($role_info['learning_goals'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($role_info['preferred_learning_style'])): ?>
                                            <div style="margin-top: 1rem;">
                                                <h5 class="font-semibold mb-2">Learning Style</h5>
                                                <span class="badge badge-secondary"><?php echo ucfirst(str_replace('_', ' ', $role_info['preferred_learning_style'])); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($role_info['teaching_style'])): ?>
                                            <div style="margin-top: 1rem;">
                                                <h5 class="font-semibold mb-2">👩‍🏫 Teaching Approach</h5>
                                                <p class="text-secondary"><?php echo nl2br(htmlspecialchars($role_info['teaching_style'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Subject Expertise -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">
                                <?php if ($user['role'] === 'peer'): ?>
                                    My Subjects
                                <?php elseif ($user['role'] === 'student'): ?>
                                    Subjects I Want to Learn
                                <?php else: ?>
                                    Subjects I Teach
                                <?php endif; ?>
                            </h3>
                            <a href="subjects.php" class="btn btn-secondary">Manage Subjects</a>
                        </div>
                        <div class="card-body">
                            <?php if ($user['role'] === 'peer'): ?>
                                <!-- Learning Subjects Section -->
                                <div style="margin-bottom: 2rem;">
                                    <h4 class="font-semibold mb-3" style="color: var(--primary-color); display: flex; align-items: center; gap: 0.5rem;">
                                        <span style="font-size: 1.25rem;">📚</span>
                                        Subjects I Want to Learn
                                    </h4>
                                    <?php if (empty($learning_subjects)): ?>
                                        <p class="text-secondary" style="padding: 1rem; background: #f8fafc; border-radius: 6px; font-style: italic;">
                                            No learning subjects added yet. Add beginner or intermediate level subjects to find mentors and peers who can help you learn.
                                        </p>
                                    <?php else: ?>
                                        <div class="grid grid-cols-2" style="gap: 1rem;">
                                            <?php foreach ($learning_subjects as $subject): ?>
                                                <div style="padding: 1rem; background: #eff6ff; border-radius: 6px; border-left: 4px solid #3b82f6;">
                                                    <div class="font-medium" style="color: #1e40af;"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                                    <?php if (!empty($subject['subtopic'])): ?>
                                                        <div class="text-xs text-secondary mb-1">
                                                            <?php echo htmlspecialchars($subject['main_subject']); ?> → <?php echo htmlspecialchars($subject['subtopic']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="text-sm" style="color: #60a5fa;">
                                                        Learning: <?php echo ucfirst($subject['proficiency_level']); ?> level
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Teaching Subjects Section -->
                                <div>
                                    <h4 class="font-semibold mb-3" style="color: #16a34a; display: flex; align-items: center; gap: 0.5rem;">
                                        <span style="font-size: 1.25rem;">👨‍🏫</span>
                                        Subjects I Can Teach
                                    </h4>
                                    <?php if (empty($teaching_subjects)): ?>
                                        <p class="text-secondary" style="padding: 1rem; background: #f8fafc; border-radius: 6px; font-style: italic;">
                                            No teaching subjects added yet. Add advanced or expert level subjects to help other students and peers learn.
                                        </p>
                                    <?php else: ?>
                                        <div class="grid grid-cols-2" style="gap: 1rem;">
                                            <?php foreach ($teaching_subjects as $subject): ?>
                                                <div style="padding: 1rem; background: #f0fdf4; border-radius: 6px; border-left: 4px solid #22c55e;">
                                                    <div class="font-medium" style="color: #15803d;"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                                    <?php if (!empty($subject['subtopic'])): ?>
                                                        <div class="text-xs text-secondary mb-1">
                                                            <?php echo htmlspecialchars($subject['main_subject']); ?> → <?php echo htmlspecialchars($subject['subtopic']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="text-sm" style="color: #4ade80;">
                                                        Teaching: <?php echo ucfirst($subject['proficiency_level']); ?> level
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                            <?php else: ?>
                                <!-- Original display for students and mentors -->
                                <?php if (empty($user_subjects)): ?>
                                    <p class="text-secondary text-center">
                                        No subjects added yet. 
                                        <a href="subjects.php" style="color: var(--primary-color);">Add subjects</a> 
                                        to get matched with 
                                        <?php if ($user['role'] === 'student'): ?>
                                            mentors and peers who can help you learn.
                                        <?php else: ?>
                                            students who need help.
                                        <?php endif; ?>
                                    </p>
                                <?php else: ?>
                                    <div class="grid grid-cols-2" style="gap: 1rem;">
                                        <?php foreach ($user_subjects as $subject): ?>
                                            <div style="padding: 1rem; background: #f8fafc; border-radius: 6px; border-left: 4px solid var(--primary-color);">
                                                <div class="font-medium"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                                <?php if (!empty($subject['subtopic'])): ?>
                                                    <div class="text-xs text-secondary mb-1">
                                                        <?php echo htmlspecialchars($subject['main_subject']); ?> → <?php echo htmlspecialchars($subject['subtopic']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="text-sm text-secondary">
                                                    <?php 
                                                    if ($user['role'] === 'mentor') {
                                                        echo 'Can teach: ' . ucfirst($subject['proficiency_level']);
                                                    } else {
                                                        echo 'Want to learn: ' . ucfirst($subject['proficiency_level']) . ' level';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Feedback & Reviews Section -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">Feedback & Reviews</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($feedbacks)): ?>
                                <p class="text-secondary text-center">
                                    No feedback received yet. Complete sessions to receive reviews from your study partners.
                                </p>
                            <?php else: ?>
                                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                                    <?php foreach ($feedbacks as $feedback): ?>
                                        <div style="padding: 1.5rem; background: #f8fafc; border-radius: 8px; border-left: 4px solid var(--primary-color);">
                                            <!-- Reviewer Info and Rating -->
                                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                                <div style="display: flex; align-items: center; gap: 1rem;">
                                                    <div style="width: 48px; height: 48px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 1.1rem;">
                                                        <?php echo strtoupper(substr($feedback['first_name'], 0, 1) . substr($feedback['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="font-semibold">
                                                            <?php echo htmlspecialchars($feedback['first_name'] . ' ' . $feedback['last_name']); ?>
                                                        </div>
                                                        <div class="text-sm text-secondary">
                                                            <?php echo ucfirst($feedback['role']); ?> • <?php echo htmlspecialchars($feedback['subject']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Star Rating Display -->
                                                <div style="text-align: right;">
                                                    <div class="star-rating">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <span class="star <?php echo $i <= $feedback['rating'] ? 'filled' : 'empty'; ?>">★</span>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <div class="text-sm text-secondary" style="margin-top: 0.25rem;">
                                                        <?php echo date('M j, Y', strtotime($feedback['created_at'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Feedback Text -->
                                            <?php if (!empty($feedback['feedback'])): ?>
                                                <div style="padding: 1rem; background: white; border-radius: 6px; margin-top: 0.75rem;">
                                                    <p style="margin: 0; color: #374151; line-height: 1.6;">
                                                        "<?php echo nl2br(htmlspecialchars($feedback['feedback'])); ?>"
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Session Date -->
                                            <div class="text-sm text-secondary" style="margin-top: 0.75rem;">
                                                Session Date: <?php echo date('F j, Y', strtotime($feedback['session_date'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if (count($feedbacks) >= 10): ?>
                                    <div class="text-center" style="margin-top: 1rem;">
                                        <p class="text-secondary text-sm">Showing 10 most recent reviews</p>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Availability -->
                    <?php if ($user['role'] === 'mentor' || $user['role'] === 'peer'): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <?php echo $user['role'] === 'peer' ? 'Study/Teaching Availability' : 'Teaching Availability'; ?>
                                </h3>
                                <a href="availability.php" class="btn btn-secondary">Update Schedule</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($availability)): ?>
                                    <p class="text-secondary text-center">
                                        No availability set yet. <a href="availability.php" style="color: var(--primary-color);">Set your schedule</a> 
                                        to help <?php echo $user['role'] === 'peer' ? 'others' : 'students'; ?> know when you're available for sessions.
                                    </p>
                                <?php else: ?>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <?php foreach ($availability as $slot): ?>
                                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f8fafc; border-radius: 6px;">
                                                <span class="font-medium"><?php echo ucfirst($slot['day_of_week']); ?></span>
                                                <span class="text-secondary"><?php echo date('g:i A', strtotime($slot['start_time'])) . ' - ' . date('g:i A', strtotime($slot['end_time'])); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Mentor Verification -->
                    <?php if ($user['role'] === 'mentor'): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title">Mentor Verification</h3>
                                <a href="verification.php" class="btn btn-secondary">Manage Documents</a>
                            </div>
                            <div class="card-body">
                                <?php if ($user['is_verified']): ?>
                                    <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: #f0fdf4; border-radius: 0.5rem; border: 1px solid #bbf7d0;">
                                        <div style="color: #16a34a; font-size: 1.5rem;">✓</div>
                                        <div>
                                            <div class="font-medium" style="color: #16a34a;">
                                                Verified Mentor
                                            </div>
                                            <div class="text-sm text-secondary">
                                                Your mentor status has been verified by our admin team.
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: #fffbeb; border-radius: 0.5rem; border: 1px solid #fed7aa;">
                                        <div style="color: #f59e0b; font-size: 1.5rem;">⚠</div>
                                        <div>
                                            <div class="font-medium" style="color: #f59e0b;">Verification Pending</div>
                                            <div class="text-sm text-secondary">
                                                Upload verification documents to become a verified mentor.
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Added referral codes section for verified mentors -->
                        <?php if ($user['is_verified']): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h3 class="card-title">Referral Codes</h3>
                                    <a href="referral-codes.php" class="btn btn-primary">Manage Codes</a>
                                </div>
                                <div class="card-body">
                                    <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: #f0f9ff; border-radius: 0.5rem; border: 1px solid #bae6fd;">
                                        <div style="color: var(--primary-color); font-size: 1.5rem;">🎯</div>
                                        <div>
                                            <div class="font-medium" style="color: var(--primary-color);">Share Your Expertise</div>
                                            <div class="text-sm text-secondary">
                                                Generate referral codes to invite other peers and co-teachers and help them get verified instantly.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Added "Become a Peer" section for students -->
                    <?php if ($user['role'] === 'student'): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title">🤝 Become a Peer</h3>
                            </div>
                            <div class="card-body">
                                <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: #f0f9ff; border-radius: 0.5rem; border: 1px solid #bae6fd;">
                                    <div style="color: var(--primary-color); font-size: 1.5rem;">🎓</div>
                                    <div style="flex: 1;">
                                        <div class="font-medium" style="color: var(--primary-color);">Ready to Help Others?</div>
                                        <div class="text-sm text-secondary">
                                            Upgrade to Peer status to both learn and teach. You'll need a referral code from a verified mentor.
                                        </div>
                                    </div>
                                    <a href="become-peer.php" class="btn btn-primary">Upgrade Now</a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Stats Sidebar -->
                <div>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">Profile Stats</h3>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color);">
                                    <?php echo $stats['active_matches'] ?? 0; ?>
                                </div>
                                <div class="text-secondary">Active Matches</div>
                            </div>
                            <div class="text-center mb-3">
                                <div style="font-size: 2rem; font-weight: 700; color: #16a34a;">
                                    <?php echo $stats['completed_sessions'] ?? 0; ?>
                                </div>
                                <div class="text-secondary">Completed Sessions</div>
                            </div>
                            <div class="text-center">
                                <div style="font-size: 2rem; font-weight: 700; color: #f59e0b;">
                                    <?php echo $stats['avg_rating'] ? number_format($stats['avg_rating'], 1) : 'N/A'; ?>
                                </div>
                                <div class="text-secondary">Average Rating</div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Account Status</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Verification:</strong>
                                <span style="color: <?php echo $user['is_verified'] ? '#16a34a' : '#f59e0b'; ?>;">
                                    <?php echo $user['is_verified'] ? 'Verified' : 'Pending'; ?>
                                </span>
                            </div>
                            <div class="mb-3">
                                <strong>Account Status:</strong>
                                <span style="color: <?php echo $user['is_active'] ? '#16a34a' : '#dc2626'; ?>;">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            <div>
                                <strong>Member Since:</strong>
                                <span class="text-secondary"><?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                            </div>
                        </div>
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

                // Close menu when clicking on links
                const links = navLinks.querySelectorAll("a");
                links.forEach((link) => {
                    link.addEventListener("click", () => {
                        hamburger.classList.remove("active");
                        navLinks.classList.remove("active");
                    });
                });

                // Close menu when clicking outside
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
    </script>
</body>
</html>