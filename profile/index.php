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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
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

        /* Notification bell styles */
        .notification-bell {
            position: relative;
            display: inline-block;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: background-color 0.2s;
        }
        .notification-bell:hover {
            background-color: #f3f4f6;
        }
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background-color: #ef4444;
            color: white;
            border-radius: 10px;
            padding: 0.125rem 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            min-width: 18px;
            text-align: center;
        }
        .notification-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 0.5rem;
            width: 380px;
            max-height: 500px;
            overflow-y: auto;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .notification-dropdown.show {
            display: block;
        }
        .notification-header {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .notification-item-dropdown {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .notification-item-dropdown:hover {
            background-color: #f9fafb;
        }
        .notification-item-dropdown.unread {
            background-color: #eff6ff;
        }
        .notification-footer {
            padding: 0.75rem;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        /* Profile dropdown styles */
        .profile-menu {
            position: relative;
            display: inline-block;
        }
        .profile-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            cursor: pointer;
            font-size: 1.25rem;
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            padding: 0;
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
        .profile-icon.no-image {
            padding: 0.5rem;
        }
        .profile-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 0.5rem;
            width: 220px;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            z-index: 1000;
            overflow: hidden;
        }
        .profile-dropdown.show {
            display: block;
        }
        .profile-dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            text-align: center;
        }
        .profile-dropdown-header .user-name {
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }
        .profile-dropdown-header .user-role {
            font-size: 0.875rem;
            color: #6b7280;
            margin: 0.25rem 0 0 0;
        }
        .profile-dropdown-menu {
            padding: 0.5rem 0;
        }
        .profile-dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #374151;
            text-decoration: none;
            transition: background-color 0.2s;
            cursor: pointer;
            border: none;
            width: 100%;
            text-align: left;
            font-size: 0.95rem;
        }
        .profile-dropdown-item:hover {
            background-color: #f3f4f6;
        }
        .profile-dropdown-item i {
            width: 18px;
            text-align: center;
        }
        .profile-dropdown-divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 0.5rem 0;
        }
        .profile-dropdown-item.logout {
            color: #dc2626;
        }
        .profile-dropdown-item.logout:hover {
            background-color: #fee2e2;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="../dashboard.php" class="logo">StudyConnect</a>
                <ul class="nav-links">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <li><a href="../matches/index.php">Matches</a></li>
                    <li><a href="../sessions/index.php">Sessions</a></li>
                    <li><a href="../messages/index.php">Messages</a></li>
                    
                    <!-- Notification bell -->
                    <li style="position: relative;">
                        <div class="notification-bell" onclick="toggleNotifications(event)">
                            <i class="fas fa-bell" style="font-size: 1.25rem;"></i>
                            <?php if ($unread_notifications > 0): ?>
                                <span class="notification-badge" id="notificationBadge"><?php echo $unread_notifications; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="notification-header">
                                <h4 class="font-semibold">Notifications</h4>
                                <?php if ($unread_notifications > 0): ?>
                                    <button onclick="markAllRead(event)" class="btn btn-sm btn-outline">Mark all read</button>
                                <?php endif; ?>
                            </div>
                            <div class="notification-list" id="notificationList">
                                <div class="text-center py-4">
                                    <i class="fas fa-spinner fa-spin"></i> Loading...
                                </div>
                            </div>
                            <div class="notification-footer">
                                <a href="../notifications/index.php" class="text-primary font-medium">View All Notifications</a>
                            </div>
                        </div>
                    </li>

                    <!-- Profile menu with dropdown -->
                    <li style="position: relative;">
                        <div class="profile-menu">
                            <button class="profile-icon <?php echo empty($user['profile_picture']) || !file_exists('../' . $user['profile_picture']) ? 'no-image' : ''; ?>" onclick="toggleProfileMenu(event)" title="Profile Menu">
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
                                            <i class="fas fa-money-bill-wave"></i>
                                            <span>Commission Payments</span>
                                        </a>
                                    <?php endif; ?>
                                    <a href="settings.php" class="profile-dropdown-item">
                                        <i class="fas fa-cog"></i>
                                        <span>Settings</span>
                                    </a>
                                    <div class="profile-dropdown-divider"></div>
                                    <a href="../auth/logout.php" class="profile-dropdown-item logout">
                                        <i class="fas fa-sign-out-alt"></i>
                                        <span>Logout</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="container">
            <div class="grid grid-cols-3" style="gap: 2rem;">
                <!-- Profile Info -->
                <div style="grid-column: span 2;">
                    <div class="card mb-4">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
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
                                        <strong>Role:</strong> <span class="text-primary"><?php echo ucfirst($user['role']); ?></span>
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
                                                <span class="text-success font-semibold">₱<?php echo number_format($user['hourly_rate'], 2); ?>/hour</span>
                                            <?php else: ?>
                                                <span class="text-warning">Not set</span>
                                                <a href="edit.php" class="text-primary text-sm">(Set your rate)</a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h4 class="font-semibold mb-2">About Me</h4>
                                    <p class="text-secondary"><?php echo nl2br(htmlspecialchars($user['bio'] ?? 'No bio provided yet.')); ?></p>
                                    
                                    <?php if ($user['role'] === 'student' && $role_info): ?>
                                        <?php if (!empty($role_info['learning_goals'])): ?>
                                            <div class="mt-4">
                                                <h5 class="font-semibold mb-2">Learning Goals</h5>
                                                <p class="text-secondary"><?php echo nl2br(htmlspecialchars($role_info['learning_goals'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($role_info['preferred_learning_style'])): ?>
                                            <div class="mt-4">
                                                <h5 class="font-semibold mb-2">Preferred Learning Style</h5>
                                                <span class="badge badge-secondary"><?php echo ucfirst(str_replace('_', ' ', $role_info['preferred_learning_style'])); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($user['role'] === 'mentor' && $role_info): ?>
                                        <?php if (!empty($role_info['teaching_style'])): ?>
                                            <div class="mt-4">
                                                <h5 class="font-semibold mb-2">Teaching Style</h5>
                                                <p class="text-secondary"><?php echo nl2br(htmlspecialchars($role_info['teaching_style'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($user['role'] === 'peer' && $role_info): ?>
                                        <?php if (!empty($role_info['learning_goals'])): ?>
                                            <div class="mt-4">
                                                <h5 class="font-semibold mb-2">🎓 Learning Goals</h5>
                                                <p class="text-secondary"><?php echo nl2br(htmlspecialchars($role_info['learning_goals'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($role_info['preferred_learning_style'])): ?>
                                            <div class="mt-4">
                                                <h5 class="font-semibold mb-2">Learning Style</h5>
                                                <span class="badge badge-secondary"><?php echo ucfirst(str_replace('_', ' ', $role_info['preferred_learning_style'])); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($role_info['teaching_style'])): ?>
                                            <div class="mt-4">
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
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
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
                                    <h4 class="font-semibold mb-3" style="color: var(--success-color); display: flex; align-items: center; gap: 0.5rem;">
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
                                        <a href="subjects.php" class="text-primary">Add subjects</a> 
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
                            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                                <h3 class="card-title">
                                    <?php echo $user['role'] === 'peer' ? 'Study/Teaching Availability' : 'Teaching Availability'; ?>
                                </h3>
                                <a href="availability.php" class="btn btn-secondary">Update Schedule</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($availability)): ?>
                                    <p class="text-secondary text-center">
                                        No availability set yet. <a href="availability.php" class="text-primary">Set your schedule</a> 
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
                            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                                <h3 class="card-title">Mentor Verification</h3>
                                <a href="verification.php" class="btn btn-secondary">Manage Documents</a>
                            </div>
                            <div class="card-body">
                                <?php if ($user['is_verified']): ?>
                                    <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: #f0fdf4; border-radius: 0.5rem; border: 1px solid #bbf7d0;">
                                        <div style="color: var(--success-color); font-size: 1.5rem;">✓</div>
                                        <div>
                                            <div class="font-medium" style="color: var(--success-color);">
                                                Verified Mentor
                                            </div>
                                            <div class="text-sm text-secondary">
                                                Your mentor status has been verified by our admin team.
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: #fffbeb; border-radius: 0.5rem; border: 1px solid #fed7aa;">
                                        <div style="color: var(--warning-color); font-size: 1.5rem;">⚠</div>
                                        <div>
                                            <div class="font-medium" style="color: var(--warning-color);">Verification Pending</div>
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
                                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
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
                                <div style="font-size: 2rem; font-weight: 700; color: var(--success-color);">
                                    <?php echo $stats['completed_sessions'] ?? 0; ?>
                                </div>
                                <div class="text-secondary">Completed Sessions</div>
                            </div>
                            <div class="text-center">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--warning-color);">
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
                                <span class="<?php echo $user['is_verified'] ? 'text-success' : 'text-warning'; ?>">
                                    <?php echo $user['is_verified'] ? 'Verified' : 'Pending'; ?>
                                </span>
                            </div>
                            <div class="mb-3">
                                <strong>Account Status:</strong>
                                <span class="<?php echo $user['is_active'] ? 'text-success' : 'text-error'; ?>">
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

    <!-- Notification and Profile JavaScript -->
    <script>
        let notificationDropdownOpen = false;
        let profileDropdownOpen = false;

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
                    
                    if (data.notifications.length === 0) {
                        list.innerHTML = '<div class="text-center py-4 text-secondary">No notifications</div>';
                        return;
                    }
                    
                    list.innerHTML = data.notifications.slice(0, 5).map(notif => `
                        <div class="notification-item-dropdown ${!notif.is_read ? 'unread' : ''}" 
                             onclick="handleNotificationClick(${notif.id}, '${notif.link || ''}')">
                            <div style="display: flex; gap: 0.75rem;">
                                <div style="flex-shrink: 0;">
                                    <i class="fas ${getNotificationIcon(notif.type)} text-${getNotificationColor(notif.type)}-600"></i>
                                </div>
                                <div style="flex: 1;">
                                    <div class="font-medium text-sm mb-1">${escapeHtml(notif.title)}</div>
                                    <div class="text-xs text-secondary">${escapeHtml(notif.message)}</div>
                                    <div class="text-xs text-gray-400 mt-1">${timeAgo(notif.created_at)}</div>
                                </div>
                            </div>
                        </div>
                    `).join('');
                    
                    // Update badge
                    const badge = document.getElementById('notificationBadge');
                    if (data.unread_count > 0) {
                        if (badge) {
                            badge.textContent = data.unread_count;
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                });
        }

        function handleNotificationClick(notificationId, link) {
            fetch('../api/notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'mark_read',
                    notification_id: notificationId
                })
            }).then(() => {
                if (link) {
                    window.location.href = link;
                } else {
                    loadNotifications();
                }
            });
        }

        function markAllRead(event) {
            event.stopPropagation();
            fetch('../api/notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'mark_all_read'})
            }).then(() => {
                loadNotifications();
            });
        }

        function getNotificationIcon(type) {
            const icons = {
                'session_scheduled': 'fa-calendar-plus',
                'session_accepted': 'fa-check-circle',
                'session_rejected': 'fa-times-circle',
                'match_request': 'fa-handshake',
                'match_accepted': 'fa-user-check',
                'announcement': 'fa-bullhorn',
                'commission_due': 'fa-money-bill-wave'
            };
            return icons[type] || 'fa-bell';
        }

        function getNotificationColor(type) {
            const colors = {
                'session_accepted': 'success',
                'session_rejected': 'danger',
                'match_accepted': 'success',
                'announcement': 'primary',
                'commission_due': 'warning'
            };
            return colors[type] || 'secondary';
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
            return Math.floor(seconds / 86400) + 'd ago';
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (notificationDropdownOpen) {
                const dropdown = document.getElementById('notificationDropdown');
                dropdown.classList.remove('show');
                notificationDropdownOpen = false;
            }
            if (profileDropdownOpen) {
                const dropdown = document.getElementById('profileDropdown');
                dropdown.classList.remove('show');
                profileDropdownOpen = false;
            }
        });

        // Refresh notifications every 30 seconds
        setInterval(() => {
            if (notificationDropdownOpen) {
                loadNotifications();
            } else {
                // Just update the badge count
                fetch('../api/notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        const badge = document.getElementById('notificationBadge');
                        if (data.unread_count > 0) {
                            if (badge) {
                                badge.textContent = data.unread_count;
                            } else {
                                document.querySelector('.notification-bell').innerHTML += 
                                    `<span class="notification-badge" id="notificationBadge">${data.unread_count}</span>`;
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