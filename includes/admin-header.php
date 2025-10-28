<?php
// Admin Header Component
// Displays top navigation bar with notifications and profile dropdown

if (!isset($user)) {
    $user = get_logged_in_user();
}

// Get unread notifications count
$db = getDB();
$unread_count = 0;
if ($user && $user['id']) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user['id']]);
    $result = $stmt->fetch();
    $unread_count = $result['count'] ?? 0;
}
?>

<style>
    .admin-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 999;
        width: 100%;
        height: 60px;
    }
    
    .admin-header .navbar-brand {
        font-weight: 700;
        font-size: 1.3rem;
        color: white !important;
    }
    
    .admin-header .nav-link {
        color: rgba(255,255,255,0.8) !important;
        transition: color 0.3s ease;
    }
    
    .admin-header .nav-link:hover {
        color: white !important;
    }
    
    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ef4444;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: bold;
    }
    
    .profile-dropdown {
        min-width: 250px;
    }
    
    .profile-dropdown .dropdown-header {
        border-bottom: 1px solid #e5e7eb;
        padding: 12px 16px;
    }
    
    .profile-dropdown .dropdown-item {
        padding: 10px 16px;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .profile-dropdown .dropdown-item:last-child {
        border-bottom: none;
    }
    
    .profile-dropdown .dropdown-item:hover {
        background-color: #f9fafb;
    }
    
    .admin-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 1.1rem;
    }
    
    .admin-status {
        display: inline-block;
        background: #10b981;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-left: 8px;
    }
</style>

<nav class="navbar navbar-expand-lg admin-header">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-graduation-cap me-2"></i>Study Buddy Admin
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav ms-auto">
                
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown">
                        <div class="admin-avatar">
                            <?php echo strtoupper(substr($user['first_name'] ?? 'A', 0, 1)); ?>
                        </div>
                        <span class="ms-2 d-none d-md-inline text-white">
                            <?php echo htmlspecialchars($user['first_name'] ?? 'Admin'); ?>
                            <span class="admin-status">Admin</span>
                        </span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end profile-dropdown" aria-labelledby="profileDropdown">
                        <div class="dropdown-header">
                            <div class="d-flex align-items-center">
                                <div class="admin-avatar" style="width: 50px; height: 50px; font-size: 1.3rem;">
                                    <?php echo strtoupper(substr($user['first_name'] ?? 'A', 0, 1)); ?>
                                </div>
                                <div class="ms-3">
                                    <div class="font-weight-bold">
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </div>
                                    <small class="text-muted">Administrator</small>
                                </div>
                            </div>
                        </div>
                        
                        <a class="dropdown-item" href="announcements.php">
                            <i class="fas fa-bullhorn me-2"></i> Announcements
                        </a>
                        <a class="dropdown-item" href="reports.php">
                            <i class="fas fa-chart-line me-2"></i> Reports
                        </a>
                        <a class="dropdown-item" href="settings.php">
                            <i class="fas fa-cog me-2"></i> Settings
                        </a>
                        <a class="dropdown-item" href="activity-logs.php">
                            <i class="fas fa-history me-2"></i> Activity Logs
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger" href="../auth/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>

<script>
// Load notifications dynamically
function loadAdminNotifications() {
    console.log('[v0] Loading admin notifications...');
    const apiPath = window.location.origin + '/api/notifications.php';
    console.log('[v0] API path:', apiPath);
    
    fetch(apiPath)
        .then(response => {
            console.log('[v0] Response status:', response.status);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('[v0] Notifications data received:', data);
            console.log('[v0] Unread count:', data.unread_count);
            
            const notificationsList = document.getElementById('notificationsList');
            const badge = document.getElementById('notificationBadge');
            
            if (!badge) {
                console.error('[v0] Badge element not found!');
                return;
            }
            
            if (data.unread_count && data.unread_count > 0) {
                badge.textContent = data.unread_count;
                badge.style.display = 'inline-flex';
                console.log('[v0] Badge shown with count:', data.unread_count);
            } else {
                badge.style.display = 'none';
                console.log('[v0] Badge hidden (count is 0)');
            }
            
            if (data.notifications && data.notifications.length > 0) {
                notificationsList.innerHTML = data.notifications.slice(0, 5).map(notif => {
                    let icon = '🔔';
                    if (notif.type === 'message') icon = '💬';
                    if (notif.type === 'announcement') {
                        if (notif.announcement_type === 'warning') icon = '⚠️';
                        else if (notif.announcement_type === 'alert') icon = '🚨';
                        else icon = 'ℹ️';
                    }
                    
                    return `
                        <a href="${notif.link || '#'}" class="dropdown-item ${notif.is_read ? '' : 'fw-bold'}">
                            <div class="d-flex justify-content-between align-items-start">
                                <div style="flex: 1;">
                                    <div class="small">${icon} ${notif.title || notif.sender_name || 'Notification'}</div>
                                    <small class="text-muted">${notif.message.substring(0, 50)}${notif.message.length > 50 ? '...' : ''}</small>
                                </div>
                                ${!notif.is_read ? '<span class="badge bg-primary rounded-pill ms-2">New</span>' : ''}
                            </div>
                            <small class="text-muted">${new Date(notif.created_at).toLocaleString()}</small>
                        </a>
                    `;
                }).join('');
            } else {
                notificationsList.innerHTML = '<div class="dropdown-item text-muted text-center py-3"><small>No notifications</small></div>';
            }
        })
        .catch(error => {
            console.error('[v0] Error loading notifications:', error);
            console.error('[v0] Error details:', error.message);
        });
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('[v0] Admin header DOM loaded');
    const badge = document.getElementById('notificationBadge');
    console.log('[v0] Badge element found:', badge ? 'YES' : 'NO');
    
    loadAdminNotifications();
    setInterval(loadAdminNotifications, 5000);
});
</script>