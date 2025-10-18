<?php
require_once '../config/config.php';
require_once '../config/notification_helper.php';

if (!is_logged_in()) {
    redirect('../auth/login.php');
}

$user = get_logged_in_user();
$db = getDB();

// Get filter
$filter = $_GET['filter'] ?? 'all';

// Build query based on filter
$query = "SELECT * FROM notifications WHERE user_id = ?";
$params = [$user['id']];

if ($filter === 'unread') {
    $query .= " AND is_read = FALSE";
}

$query .= " ORDER BY created_at DESC LIMIT 50";

$stmt = $db->prepare($query);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Get counts
$unread_count = get_unread_count($user['id']);
$total_stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ?");
$total_stmt->execute([$user['id']]);
$total_count = $total_stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .notification-item {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            transition: background-color 0.2s;
            cursor: pointer;
        }
        .notification-item:hover {
            background-color: #f9fafb;
        }
        .notification-item.unread {
            background-color: #eff6ff;
            border-left: 3px solid #3b82f6;
        }
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        .filter-tabs {
            display: flex;
            gap: 1rem;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 1.5rem;
        }
        .filter-tab {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        .filter-tab.active {
            border-bottom-color: #3b82f6;
            color: #3b82f6;
            font-weight: 600;
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
                    <li><a href="../profile/index.php">Profile</a></li>
                    <li><a href="../matches/index.php">Matches</a></li>
                    <li><a href="../sessions/index.php">Sessions</a></li>
                    <li><a href="../messages/index.php">Messages</a></li>
                    <?php if ($user['role'] === 'mentor'): ?>
                        <li><a href="../profile/commission-payments.php">Commission Payments</a></li>
                    <?php endif; ?>
                    <li><a href="index.php" class="active">Notifications</a></li>
                    <li>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span class="text-secondary">Hi, <?php echo htmlspecialchars($user['first_name']); ?>!</span>
                            <a href="../auth/logout.php" class="btn btn-outline" style="padding: 0.5rem 1rem;">Logout</a>
                        </div>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="py-8">
        <div class="container" style="max-width: 800px;">
            <div class="mb-6">
                <h1 class="text-3xl font-bold mb-2">Notifications</h1>
                <p class="text-secondary">Stay updated with your activities</p>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="filter-tabs">
                        <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                            All (<?php echo $total_count; ?>)
                        </a>
                        <a href="?filter=unread" class="filter-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                            Unread (<?php echo $unread_count; ?>)
                        </a>
                        <?php if ($unread_count > 0): ?>
                            <button onclick="markAllRead()" class="btn btn-sm btn-outline" style="margin-left: auto;">
                                <i class="fas fa-check-double"></i> Mark All Read
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-bell-slash text-gray-300" style="font-size: 4rem;"></i>
                            <p class="text-secondary mt-4">No notifications yet</p>
                        </div>
                    <?php else: ?>
                        <div>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" 
                                     onclick="handleNotificationClick(<?php echo $notification['id']; ?>, '<?php echo htmlspecialchars($notification['link'] ?? '', ENT_QUOTES); ?>')">
                                    <div style="display: flex; gap: 1rem; align-items: start;">
                                        <div class="notification-icon bg-<?php echo get_notification_color($notification['type']); ?>-100 text-<?php echo get_notification_color($notification['type']); ?>-600">
                                            <i class="fas <?php echo get_notification_icon($notification['type']); ?>"></i>
                                        </div>
                                        <div style="flex: 1;">
                                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.25rem;">
                                                <h4 class="font-semibold"><?php echo htmlspecialchars($notification['title']); ?></h4>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="badge badge-primary">New</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-secondary text-sm mb-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <span class="text-xs text-gray-400">
                                                <i class="far fa-clock"></i> 
                                                <?php echo time_ago($notification['created_at']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>

    <script>
        function handleNotificationClick(notificationId, link) {
            // Mark as read
            fetch('../api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_read',
                    notification_id: notificationId
                })
            }).then(() => {
                // Navigate to link if exists
                if (link) {
                    window.location.href = link;
                } else {
                    // Reload to update UI
                    location.reload();
                }
            });
        }

        function markAllRead() {
            if (confirm('Mark all notifications as read?')) {
                fetch('../api/notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'mark_all_read'
                    })
                }).then(() => {
                    location.reload();
                });
            }
        }
    </script>
</body>
</html>
