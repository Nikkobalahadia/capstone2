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
    <!-- Updated to use Bootstrap and purple admin theme -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; position: fixed; width: 250px; top: 60px; left: 0; z-index: 998; overflow-y: auto; height: calc(100vh - 60px); }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; border-radius: 8px; margin: 4px 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        .main-content { margin-left: 250px; padding: 20px; margin-top: 60px; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } .sidebar { display: none; } }
    </style>
</head>
<body>
    <?php include '../includes/admin-header.php'; ?>
    
    <!-- Sidebar -->
    <?php include '../includes/admin-sidebar.php'; ?>

    <!-- Updated main content area to work with sidebar layout -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">Admin Dashboard</h1>
                    <p class="text-muted">Monitor platform performance and user activity.</p>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Users</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($user_stats['total_users']); ?></div>
                                    <div class="text-success small">+<?php echo $user_stats['new_users_7d']; ?> this week</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Matches</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($match_stats['active_matches']); ?></div>
                                    <div class="text-muted small"><?php echo $match_stats['pending_matches']; ?> pending</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-handshake fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Completed Sessions</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($session_stats['completed_sessions']); ?></div>
                                    <div class="text-muted small"><?php echo $session_stats['upcoming_sessions']; ?> upcoming</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Avg Rating</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $rating_stats['avg_rating'] ? number_format($rating_stats['avg_rating'], 1) : 'N/A'; ?></div>
                                    <div class="text-muted small"><?php echo $rating_stats['total_ratings']; ?> total ratings</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-star fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Charts Column -->
                <div class="col-lg-8">
                    <!-- Daily Active Users Chart -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Daily Active Users (Last 7 Days)</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="dailyActiveChart" width="400" height="200"></canvas>
                        </div>
                    </div>

                    <!-- Popular Subjects -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Most Popular Subjects</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="subjectsChart" width="400" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Platform Activity -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Platform Activity (Last 30 Days)</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <div class="mb-3">
                                        <div class="font-weight-bold">User Logins</div>
                                        <div class="h5 text-primary"><?php echo number_format($activity_stats['logins']); ?></div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="font-weight-bold">Match Requests</div>
                                        <div class="h5 text-success"><?php echo number_format($activity_stats['match_requests']); ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-3">
                                        <div class="font-weight-bold">Messages Sent</div>
                                        <div class="h5 text-warning"><?php echo number_format($activity_stats['messages_sent']); ?></div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="font-weight-bold">Sessions Scheduled</div>
                                        <div class="h5 text-info"><?php echo number_format($activity_stats['sessions_scheduled']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- User Breakdown -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">User Breakdown</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Students</span>
                                    <span class="font-weight-bold"><?php echo $user_stats['students']; ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-primary" style="width: <?php echo $user_stats['total_users'] > 0 ? ($user_stats['students'] / $user_stats['total_users']) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Mentors</span>
                                    <span class="font-weight-bold"><?php echo $user_stats['mentors']; ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $user_stats['total_users'] > 0 ? ($user_stats['mentors'] / $user_stats['total_users']) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Verified</span>
                                    <span class="font-weight-bold"><?php echo $user_stats['verified_users']; ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo $user_stats['total_users'] > 0 ? ($user_stats['verified_users'] / $user_stats['total_users']) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6>
                        </div>
                        <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach (array_slice($recent_activity, 0, 10) as $activity): ?>
                                <div class="border-bottom py-2">
                                    <div class="small">
                                        <strong><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong>
                                        <span class="text-muted">(<?php echo ucfirst($activity['role']); ?>)</span>
                                    </div>
                                    <div class="small text-muted">
                                        <?php echo ucfirst(str_replace('_', ' ', $activity['action'])); ?>
                                    </div>
                                    <div class="small text-muted">
                                        <?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Added Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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
