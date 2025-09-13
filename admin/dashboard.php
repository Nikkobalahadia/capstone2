<?php
require_once '../config/config.php';

// Check if user is logged in and is admin
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user || $user['role'] !== 'admin') {
    redirect('dashboard.php');
}

$db = getDB();

// Get analytics data
$analytics = [];

// User statistics
$user_stats = $db->query("
    SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN role = 'student' THEN 1 END) as students,
        COUNT(CASE WHEN role = 'mentor' THEN 1 END) as mentors,
        COUNT(CASE WHEN is_verified = 1 THEN 1 END) as verified_users,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_30d,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_users_7d
    FROM users WHERE role != 'admin'
")->fetch();

// Match statistics
$match_stats = $db->query("
    SELECT 
        COUNT(*) as total_matches,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_matches,
        COUNT(CASE WHEN status = 'accepted' THEN 1 END) as active_matches,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_matches,
        AVG(match_score) as avg_match_score
    FROM matches
")->fetch();

// Session statistics
$session_stats = $db->query("
    SELECT 
        COUNT(*) as total_sessions,
        COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled_sessions,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_sessions,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_sessions,
        COUNT(CASE WHEN session_date >= CURDATE() THEN 1 END) as upcoming_sessions
    FROM sessions
")->fetch();

// Rating statistics
$rating_stats = $db->query("
    SELECT 
        COUNT(*) as total_ratings,
        AVG(rating) as avg_rating,
        COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive_ratings
    FROM session_ratings
")->fetch();

// Activity statistics (last 30 days)
$activity_stats = $db->query("
    SELECT 
        COUNT(CASE WHEN action = 'login' THEN 1 END) as logins,
        COUNT(CASE WHEN action = 'match_request' THEN 1 END) as match_requests,
        COUNT(CASE WHEN action = 'message_sent' THEN 1 END) as messages_sent,
        COUNT(CASE WHEN action = 'session_scheduled' THEN 1 END) as sessions_scheduled
    FROM user_activity_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch();

// Most popular subjects
$popular_subjects = $db->query("
    SELECT subject_name, COUNT(*) as count
    FROM user_subjects 
    GROUP BY subject_name 
    ORDER BY count DESC 
    LIMIT 10
")->fetchAll();

// Recent activity
$recent_activity = $db->query("
    SELECT ual.*, u.first_name, u.last_name, u.role
    FROM user_activity_logs ual
    JOIN users u ON ual.user_id = u.id
    ORDER BY ual.created_at DESC
    LIMIT 20
")->fetchAll();

// Daily active users (last 7 days)
$daily_active = $db->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(DISTINCT user_id) as active_users
    FROM user_activity_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="dashboard.php" class="logo">StudyConnect Admin</a>
                <ul class="nav-links">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="users.php">Users</a></li>
                    <li><a href="matches.php">Matches</a></li>
                    <li><a href="sessions.php">Sessions</a></li>
                    <li><a href="reports.php">Reports</a></li>
                    <li><a href="../auth/logout.php" class="btn btn-outline">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="container">
            <div class="mb-4">
                <h1>Admin Dashboard</h1>
                <p class="text-secondary">Monitor platform performance and user activity.</p>
            </div>

            <!-- Key Metrics -->
            <div class="grid grid-cols-4 mb-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 2.5rem; font-weight: 700; color: var(--primary-color); margin-bottom: 0.5rem;">
                            <?php echo number_format($user_stats['total_users']); ?>
                        </div>
                        <div class="text-secondary">Total Users</div>
                        <div class="text-sm text-success mt-1">
                            +<?php echo $user_stats['new_users_7d']; ?> this week
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 2.5rem; font-weight: 700; color: var(--success-color); margin-bottom: 0.5rem;">
                            <?php echo number_format($match_stats['active_matches']); ?>
                        </div>
                        <div class="text-secondary">Active Matches</div>
                        <div class="text-sm text-secondary mt-1">
                            <?php echo $match_stats['pending_matches']; ?> pending
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 2.5rem; font-weight: 700; color: var(--warning-color); margin-bottom: 0.5rem;">
                            <?php echo number_format($session_stats['completed_sessions']); ?>
                        </div>
                        <div class="text-secondary">Completed Sessions</div>
                        <div class="text-sm text-secondary mt-1">
                            <?php echo $session_stats['upcoming_sessions']; ?> upcoming
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 2.5rem; font-weight: 700; color: var(--error-color); margin-bottom: 0.5rem;">
                            <?php echo $rating_stats['avg_rating'] ? number_format($rating_stats['avg_rating'], 1) : 'N/A'; ?>
                        </div>
                        <div class="text-secondary">Avg Rating</div>
                        <div class="text-sm text-secondary mt-1">
                            <?php echo $rating_stats['total_ratings']; ?> total ratings
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-3" style="gap: 2rem;">
                <!-- Charts Column -->
                <div style="grid-column: span 2;">
                    <!-- Daily Active Users Chart -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">Daily Active Users (Last 7 Days)</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="dailyActiveChart" width="400" height="200"></canvas>
                        </div>
                    </div>

                    <!-- Platform Activity -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">Platform Activity (Last 30 Days)</h3>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-2" style="gap: 2rem;">
                                <div>
                                    <div class="mb-3">
                                        <div class="font-semibold">User Logins</div>
                                        <div style="font-size: 1.5rem; color: var(--primary-color);">
                                            <?php echo number_format($activity_stats['logins']); ?>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="font-semibold">Match Requests</div>
                                        <div style="font-size: 1.5rem; color: var(--success-color);">
                                            <?php echo number_format($activity_stats['match_requests']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div class="mb-3">
                                        <div class="font-semibold">Messages Sent</div>
                                        <div style="font-size: 1.5rem; color: var(--warning-color);">
                                            <?php echo number_format($activity_stats['messages_sent']); ?>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="font-semibold">Sessions Scheduled</div>
                                        <div style="font-size: 1.5rem; color: var(--error-color);">
                                            <?php echo number_format($activity_stats['sessions_scheduled']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Popular Subjects -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Most Popular Subjects</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="subjectsChart" width="400" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div>
                    <!-- User Breakdown -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">User Breakdown</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span>Students</span>
                                    <span class="font-semibold"><?php echo $user_stats['students']; ?></span>
                                </div>
                                <div style="background: #e5e7eb; height: 8px; border-radius: 4px;">
                                    <div style="background: var(--primary-color); height: 100%; width: <?php echo $user_stats['total_users'] > 0 ? ($user_stats['students'] / $user_stats['total_users']) * 100 : 0; ?>%; border-radius: 4px;"></div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span>Mentors</span>
                                    <span class="font-semibold"><?php echo $user_stats['mentors']; ?></span>
                                </div>
                                <div style="background: #e5e7eb; height: 8px; border-radius: 4px;">
                                    <div style="background: var(--success-color); height: 100%; width: <?php echo $user_stats['total_users'] > 0 ? ($user_stats['mentors'] / $user_stats['total_users']) * 100 : 0; ?>%; border-radius: 4px;"></div>
                                </div>
                            </div>
                            
                            <div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span>Verified</span>
                                    <span class="font-semibold"><?php echo $user_stats['verified_users']; ?></span>
                                </div>
                                <div style="background: #e5e7eb; height: 8px; border-radius: 4px;">
                                    <div style="background: var(--warning-color); height: 100%; width: <?php echo $user_stats['total_users'] > 0 ? ($user_stats['verified_users'] / $user_stats['total_users']) * 100 : 0; ?>%; border-radius: 4px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Match Success Rate -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">Match Success Rate</h3>
                        </div>
                        <div class="card-body">
                            <?php 
                            $total_responses = $match_stats['active_matches'] + $match_stats['rejected_matches'];
                            $success_rate = $total_responses > 0 ? ($match_stats['active_matches'] / $total_responses) * 100 : 0;
                            ?>
                            <div class="text-center mb-3">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--success-color);">
                                    <?php echo number_format($success_rate, 1); ?>%
                                </div>
                                <div class="text-secondary">Success Rate</div>
                            </div>
                            
                            <div class="text-sm text-secondary">
                                <div>Accepted: <?php echo $match_stats['active_matches']; ?></div>
                                <div>Rejected: <?php echo $match_stats['rejected_matches']; ?></div>
                                <div>Pending: <?php echo $match_stats['pending_matches']; ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Activity</h3>
                        </div>
                        <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach (array_slice($recent_activity, 0, 10) as $activity): ?>
                                <div style="padding: 0.75rem 0; border-bottom: 1px solid var(--border-color);">
                                    <div class="text-sm">
                                        <strong><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong>
                                        <span class="text-secondary">(<?php echo ucfirst($activity['role']); ?>)</span>
                                    </div>
                                    <div class="text-sm text-secondary">
                                        <?php echo ucfirst(str_replace('_', ' ', $activity['action'])); ?>
                                    </div>
                                    <div class="text-sm text-secondary">
                                        <?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Daily Active Users Chart
        const dailyActiveCtx = document.getElementById('dailyActiveChart').getContext('2d');
        const dailyActiveData = <?php echo json_encode(array_reverse($daily_active)); ?>;
        
        new Chart(dailyActiveCtx, {
            type: 'line',
            data: {
                labels: dailyActiveData.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                datasets: [{
                    label: 'Active Users',
                    data: dailyActiveData.map(d => d.active_users),
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Popular Subjects Chart
        const subjectsCtx = document.getElementById('subjectsChart').getContext('2d');
        const subjectsData = <?php echo json_encode($popular_subjects); ?>;
        
        new Chart(subjectsCtx, {
            type: 'doughnut',
            data: {
                labels: subjectsData.map(s => s.subject_name),
                datasets: [{
                    data: subjectsData.map(s => s.count),
                    backgroundColor: [
                        '#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                        '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6b7280'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>
