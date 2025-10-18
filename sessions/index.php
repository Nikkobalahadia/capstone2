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

$db = getDB();

// Get unread notifications count
$unread_notifications = get_unread_count($user['id']);

// Get user's sessions
$sessions_query = "
    SELECT s.*, m.subject,
           CASE 
               WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
               ELSE CONCAT(u1.first_name, ' ', u1.last_name)
           END as partner_name,
           CASE 
               WHEN m.student_id = ? THEN u2.id
               ELSE u1.id
           END as partner_id,
           CASE 
               WHEN m.student_id = ? THEN u2.role
               ELSE u1.role
           END as partner_role,
           CASE 
               WHEN m.student_id = ? THEN u2.profile_picture
               ELSE u1.profile_picture
           END as partner_profile_picture
    FROM sessions s
    JOIN matches m ON s.match_id = m.id
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE (m.student_id = ? OR m.mentor_id = ?)
    ORDER BY s.session_date DESC, s.start_time DESC
";

$stmt = $db->prepare($sessions_query);
$stmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
$sessions = $stmt->fetchAll();

// Separate sessions by status
$upcoming_sessions = array_filter($sessions, function($session) {
    return $session['status'] === 'scheduled' && 
           (strtotime($session['session_date'] . ' ' . $session['start_time']) > time());
});

$past_sessions_need_completion = array_filter($sessions, function($session) {
    return $session['status'] === 'scheduled' && 
           (strtotime($session['session_date'] . ' ' . $session['start_time']) <= time());
});

$past_sessions = array_filter($sessions, function($session) {
    return $session['status'] === 'completed';
});

$cancelled_sessions = array_filter($sessions, function($session) {
    return in_array($session['status'], ['cancelled', 'no_show']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Sessions - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
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
        }
        .profile-icon:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
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
                    <li><a href="index.php">Sessions</a></li>
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
                            <button class="profile-icon" onclick="toggleProfileMenu(event)" title="Profile Menu">
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
                                        <a href="profile/commission-payments.php" class="profile-dropdown-item">
                                            <i class="fas fa-money-bill-wave"></i>
                                            <span>Commission Payments</span>
                                        </a>
                                    <?php endif; ?>
                                    <a href="../profile/settings.php" class="profile-dropdown-item">
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
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <div>
                    <h1>My Sessions</h1>
                    <p class="text-secondary">Manage your study sessions and track your learning progress.</p>
                </div>
                <a href="schedule.php" class="btn btn-primary">Schedule New Session</a>
            </div>

            <!-- Upcoming Sessions -->
            <?php if (!empty($upcoming_sessions)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Upcoming Sessions (<?php echo count($upcoming_sessions); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($upcoming_sessions as $session): ?>
                                <div style="padding: 1.5rem; border: 1px solid var(--border-color); border-radius: 8px; background: #f0f9ff;">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div style="flex: 1;">
                                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                                <?php if (!empty($session['partner_profile_picture']) && file_exists('../' . $session['partner_profile_picture'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($session['partner_profile_picture']); ?>" 
                                                         alt="<?php echo htmlspecialchars($session['partner_name']); ?>" 
                                                         style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                                                <?php else: ?>
                                                    <div style="width: 50px; height: 50px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                                        <?php echo strtoupper(substr($session['partner_name'], 0, 2)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <h4 class="font-semibold"><?php echo htmlspecialchars($session['partner_name']); ?></h4>
                                                    <div class="text-sm text-secondary">
                                                        <?php echo ucfirst($session['partner_role']); ?> • <?php echo htmlspecialchars($session['subject']); ?>
                                                    </div>
                                                    <div class="text-sm font-medium" style="color: var(--primary-color);">
                                                        <?php echo date('l, M j, Y', strtotime($session['session_date'])); ?> • 
                                                        <?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($session['location']): ?>
                                                <div class="mb-2">
                                                    <strong>Location:</strong> <?php echo htmlspecialchars($session['location']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($session['notes']): ?>
                                                <div class="text-secondary">
                                                    <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($session['notes'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div style="display: flex; gap: 0.5rem; margin-left: 2rem;">
                                            <a href="edit.php?id=<?php echo $session['id']; ?>" class="btn btn-secondary">Edit</a>
                                            <a href="../messages/chat.php?match_id=<?php echo $session['match_id']; ?>" class="btn btn-outline">Message</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Past Sessions That Need Completion -->
            <?php if (!empty($past_sessions_need_completion)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Sessions Awaiting Completion (<?php echo count($past_sessions_need_completion); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($past_sessions_need_completion as $session): ?>
                                <div style="padding: 1.5rem; border: 1px solid var(--border-color); border-radius: 8px; background: #fff7ed;">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div style="flex: 1;">
                                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                                <?php if (!empty($session['partner_profile_picture']) && file_exists('../' . $session['partner_profile_picture'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($session['partner_profile_picture']); ?>" 
                                                         alt="<?php echo htmlspecialchars($session['partner_name']); ?>" 
                                                         style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                                                <?php else: ?>
                                                    <div style="width: 50px; height: 50px; background: #f59e0b; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                                        <?php echo strtoupper(substr($session['partner_name'], 0, 2)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <h4 class="font-semibold"><?php echo htmlspecialchars($session['partner_name']); ?></h4>
                                                    <div class="text-sm text-secondary">
                                                        <?php echo ucfirst($session['partner_role']); ?> • <?php echo htmlspecialchars($session['subject']); ?>
                                                    </div>
                                                    <div class="text-sm font-medium" style="color: #f59e0b;">
                                                        <?php echo date('M j, Y', strtotime($session['session_date'])); ?> • 
                                                        <?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div style="display: flex; gap: 0.5rem; margin-left: 2rem;">
                                            <a href="complete.php?id=<?php echo $session['id']; ?>" class="btn btn-success">Mark Complete</a>
                                            <a href="../messages/chat.php?match_id=<?php echo $session['match_id']; ?>" class="btn btn-outline">Message</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Past Sessions -->
            <?php if (!empty($past_sessions)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Past Sessions (<?php echo count($past_sessions); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach (array_slice($past_sessions, 0, 10) as $session): ?>
                                <div style="padding: 1.5rem; border: 1px solid var(--border-color); border-radius: 8px; background: #f8fafc;">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div style="flex: 1;">
                                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                                <?php if (!empty($session['partner_profile_picture']) && file_exists('../' . $session['partner_profile_picture'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($session['partner_profile_picture']); ?>" 
                                                         alt="<?php echo htmlspecialchars($session['partner_name']); ?>" 
                                                         style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                                                <?php else: ?>
                                                    <div style="width: 50px; height: 50px; background: var(--success-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                                        <?php echo strtoupper(substr($session['partner_name'], 0, 2)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <h4 class="font-semibold"><?php echo htmlspecialchars($session['partner_name']); ?></h4>
                                                    <div class="text-sm text-secondary">
                                                        <?php echo ucfirst($session['partner_role']); ?> • <?php echo htmlspecialchars($session['subject']); ?>
                                                    </div>
                                                    <div class="text-sm text-success">
                                                        <?php echo date('M j, Y', strtotime($session['session_date'])); ?> • 
                                                        <?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div style="margin-left: 2rem;">
                                            <?php
                                            // Check if user has rated this session
                                            $rating_stmt = $db->prepare("SELECT id FROM session_ratings WHERE session_id = ? AND rater_id = ?");
                                            $rating_stmt->execute([$session['id'], $user['id']]);
                                            $has_rated = $rating_stmt->fetch();
                                            ?>
                                            
                                            <?php if (!$has_rated): ?>
                                                <a href="rate.php?id=<?php echo $session['id']; ?>" class="btn btn-warning">Rate Session</a>
                                            <?php else: ?>
                                                <span class="text-success font-medium">Completed</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Cancelled Sessions -->
            <?php if (!empty($cancelled_sessions)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Cancelled Sessions</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($cancelled_sessions as $session): ?>
                                <div style="padding: 1rem; border: 1px solid var(--border-color); border-radius: 8px; background: #fef2f2;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <div class="font-medium"><?php echo htmlspecialchars($session['partner_name']); ?></div>
                                            <div class="text-sm text-secondary">
                                                <?php echo htmlspecialchars($session['subject']); ?> • 
                                                <?php echo date('M j, Y', strtotime($session['session_date'])); ?> • 
                                                <?php echo ucfirst($session['status']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($sessions)): ?>
                <div class="card">
                    <div class="card-body text-center">
                        <h3>No sessions yet</h3>
                        <p class="text-secondary mb-4">Schedule your first study session to start learning with your partners.</p>
                        <a href="schedule.php" class="btn btn-primary">Schedule Session</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

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