<?php
require_once 'config/config.php';
require_once 'config/commission_helper.php';
require_once 'config/notification_helper.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user) {
    redirect('auth/login.php');
}

$commission_warning = null;
if ($user['role'] === 'mentor') {
    $db = getDB();
    $overdue_info = check_overdue_commissions($user['id'], $db);
    
    if ($overdue_info['has_overdue']) {
        $commission_warning = $overdue_info;
        
        // Check if account should be suspended
        if (should_suspend_mentor($user['id'], $db)) {
            $commission_warning['suspended'] = true;
        }
    }
}

$unread_notifications = get_unread_count($user['id']);

// Get user statistics
$db = getDB();

// Get match count
$match_stmt = $db->prepare("SELECT COUNT(*) as count FROM matches WHERE (student_id = ? OR mentor_id = ?) AND status = 'accepted'");
$match_stmt->execute([$user['id'], $user['id']]);
$match_count = $match_stmt->fetch()['count'];

// Get session count
$session_stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM sessions s 
    JOIN matches m ON s.match_id = m.id 
    WHERE (m.student_id = ? OR m.mentor_id = ?) AND s.status = 'completed'
");
$session_stmt->execute([$user['id'], $user['id']]);
$session_count = $session_stmt->fetch()['count'];

// Get recent matches
$recent_matches_stmt = $db->prepare("
    SELECT m.*, 
           CASE 
               WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
               ELSE CONCAT(u1.first_name, ' ', u1.last_name)
           END as partner_name,
           CASE 
               WHEN m.student_id = ? THEN u2.role
               ELSE u1.role
           END as partner_role
    FROM matches m
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE (m.student_id = ? OR m.mentor_id = ?) 
    ORDER BY m.created_at DESC 
    LIMIT 5
");
$recent_matches_stmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
$recent_matches = $recent_matches_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - StudyConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
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
                <a href="dashboard.php" class="logo">StudyConnect</a>
                <ul class="nav-links">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="matches/index.php">Matches</a></li>
                    <li><a href="sessions/index.php">Sessions</a></li>
                    <li><a href="messages/index.php">Messages</a></li>

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
                                <a href="notifications/index.php" class="text-primary font-medium">View All Notifications</a>
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
                                    <a href="profile/index.php" class="profile-dropdown-item">
                                        <i class="fas fa-user-circle"></i>
                                        <span>View Profile</span>
                                    </a>
                                    <?php if (in_array($user['role'], ['mentor'])): ?>
                                        <a href="profile/commission-payments.php" class="profile-dropdown-item">
                                            <i class="fas fa-money-bill-wave"></i>
                                            <span>Commission Payments</span>
                                        </a>
                                    <?php endif; ?>
                                    <a href="profile/settings.php" class="profile-dropdown-item">
                                        <i class="fas fa-cog"></i>
                                        <span>Settings</span>
                                    </a>
                                    <div class="profile-dropdown-divider"></div>
                                    <a href="auth/logout.php" class="profile-dropdown-item logout">
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

    <main class="py-8">
        <div class="container">
            <div class="mb-6">
                <h1 class="text-3xl font-bold mb-2">Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
                <p class="text-secondary">
                    <?php if ($user['role'] === 'peer'): ?>
                        Here's what's happening with your learning and teaching journey.
                    <?php else: ?>
                        Here's what's happening with your learning journey.
                    <?php endif; ?>
                </p>
            </div>

            <!-- Commission payment warnings for mentors -->
            <?php if ($commission_warning): ?>
                <div class="alert <?php echo isset($commission_warning['suspended']) ? 'alert-error' : 'alert-warning'; ?> mb-6">
                    <div style="display: flex; align-items: start; gap: 1rem;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 1.5rem; margin-top: 0.25rem;"></i>
                        <div style="flex: 1;">
                            <?php if (isset($commission_warning['suspended'])): ?>
                                <h4 class="font-bold mb-2">Account Suspended - Unpaid Commissions</h4>
                                <p class="mb-2">Your account has been suspended due to unpaid commission payments for over 30 days. You cannot accept new sessions until all overdue commissions are paid.</p>
                            <?php else: ?>
                                <h4 class="font-bold mb-2">Overdue Commission Payments</h4>
                                <p class="mb-2">You have <?php echo $commission_warning['overdue_count']; ?> overdue commission payment(s) totaling ₱<?php echo number_format($commission_warning['total_overdue'], 2); ?>.</p>
                                <p class="mb-2">Oldest unpaid commission: <?php echo $commission_warning['oldest_days']; ?> days overdue.</p>
                                <?php if ($commission_warning['oldest_days'] > 21): ?>
                                    <p class="mb-2"><strong>Warning:</strong> Your account will be suspended if commissions remain unpaid after 30 days.</p>
                                <?php endif; ?>
                            <?php endif; ?>
                            <a href="profile/commission-payments.php" class="btn btn-sm <?php echo isset($commission_warning['suspended']) ? 'btn-error' : 'btn-warning'; ?> mt-2">
                                <i class="fas fa-money-bill-wave"></i> Pay Commissions Now
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-3 gap-6 mb-8">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="text-3xl font-bold text-primary mb-2">
                            <?php echo $match_count; ?>
                        </div>
                        <div class="text-secondary">Active Matches</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body text-center">
                        <div class="text-3xl font-bold text-success mb-2">
                            <?php echo $session_count; ?>
                        </div>
                        <div class="text-secondary">Completed Sessions</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body text-center">
                        <div class="text-3xl font-bold text-warning mb-2">
                            <?php echo $user['role'] === 'peer' ? '🤝 Peer' : ucfirst($user['role']); ?>
                        </div>
                        <div class="text-secondary">Your Role</div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-8">
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="flex flex-col gap-3">
                            <?php if ($user['role'] === 'student'): ?>
                                <a href="matches/find.php" class="btn btn-primary">Find a Mentor</a>
                                <a href="sessions/schedule.php" class="btn btn-secondary">Schedule Session</a>
                            <?php elseif ($user['role'] === 'peer'): ?>
                                <a href="matches/find.php" class="btn btn-primary">Find Study Partners</a>
                                <a href="matches/index.php" class="btn btn-secondary">View Match Requests</a>
                                <a href="profile/availability.php" class="btn btn-outline">Update Availability</a>
                            <?php else: ?>
                                <a href="matches/index.php" class="btn btn-primary">View Match Requests</a>
                                <a href="profile/availability.php" class="btn btn-secondary">Update Availability</a>
                            <?php endif; ?>
                            <a href="profile/subjects.php" class="btn btn-outline">Manage Subjects</a>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Matches</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_matches)): ?>
                            <p class="text-secondary text-center py-4">No matches yet. Start connecting with others!</p>
                        <?php else: ?>
                            <div class="flex flex-col gap-3">
                                <?php foreach ($recent_matches as $match): ?>
                                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg border">
                                        <div>
                                            <div class="font-medium text-primary"><?php echo htmlspecialchars($match['partner_name']); ?></div>
                                            <div class="text-sm text-secondary">
                                                <?php echo htmlspecialchars($match['subject']); ?> • 
                                                <?php echo $match['partner_role'] === 'peer' ? '🤝 Peer' : ucfirst($match['partner_role']); ?>
                                            </div>
                                        </div>
                                        <span class="text-sm px-2 py-1 rounded-full <?php echo $match['status'] === 'accepted' ? 'bg-green-100 text-green-800' : ($match['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); ?>">
                                            <?php echo ucfirst($match['status']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

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
            fetch('api/notifications.php')
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
            fetch('api/notifications.php', {
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
            fetch('api/notifications.php', {
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
                fetch('api/notifications.php')
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