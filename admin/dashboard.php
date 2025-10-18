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

// Optional: Log admin access (uncomment if you have this function)
// log_admin_action($user['id'], 'dashboard_access');
$alerts = [];
$warnings = [];

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
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_users_7d,
        COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_users
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
        COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive_ratings,
        COUNT(CASE WHEN rating < 3 THEN 1 END) as low_ratings
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
    SELECT ual.*, u.first_name, u.last_name, u.email, u.role
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

// System health checks
$db_status = 'healthy';
try {
    $db->query("SELECT 1");
} catch (Exception $e) {
    $db_status = 'error';
    $alerts[] = 'Database connection issue';
}

// Check for low ratings
if ($rating_stats['low_ratings'] > 0) {
    $warnings[] = $rating_stats['low_ratings'] . ' mentors have ratings below 3.0';
}

// Check for pending matches
if ($match_stats['pending_matches'] > 50) {
    $warnings[] = $match_stats['pending_matches'] . ' pending matches need review';
}

// Check user verification rate
$verification_rate = $user_stats['total_users'] > 0 ? ($user_stats['verified_users'] / $user_stats['total_users']) * 100 : 0;
if ($verification_rate < 60) {
    $warnings[] = 'User verification rate is only ' . round($verification_rate) . '%';
}

// Calculate growth metrics
$previous_week_users = $db->query("
    SELECT COUNT(*) as count
    FROM users 
    WHERE role != 'admin' 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) 
    AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetch();

$user_growth = $user_stats['new_users_7d'] - ($previous_week_users['count'] ?? 0);
$growth_percent = $previous_week_users['count'] > 0 ? (($user_growth / $previous_week_users['count']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - StudyConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; color: #333; }
        
        .sidebar { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar .nav-link { 
            color: rgba(255,255,255,0.8); 
            padding: 12px 20px; 
            border-radius: 8px; 
            margin: 4px 8px; 
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { 
            background: rgba(255,255,255,0.15); 
            color: white;
            transform: translateX(5px);
        }
        
        .main-content { 
            margin-left: 250px; 
            padding: 30px; 
            min-height: 100vh;
        }
        
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .stat-card {
            border-radius: 12px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .border-left-primary { border-left: 4px solid #2563eb !important; }
        .border-left-success { border-left: 4px solid #10b981 !important; }
        .border-left-warning { border-left: 4px solid #f59e0b !important; }
        .border-left-info { border-left: 4px solid #06b6d4 !important; }
        .border-left-danger { border-left: 4px solid #ef4444 !important; }
        
        .text-primary { color: #2563eb; }
        .text-success { color: #10b981; }
        .text-warning { color: #f59e0b; }
        .text-info { color: #06b6d4; }
        .text-danger { color: #ef4444; }
        
        .badge-sm { font-size: 0.75rem; padding: 0.35rem 0.65rem; }
        
        .alert-section {
            margin-bottom: 20px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
        
        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.9rem;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            display: inline-block;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            color: white;
            font-size: 0.8rem;
        }
        
        .growth-positive { color: #10b981; }
        .growth-negative { color: #ef4444; }
        
        .system-status {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .status-healthy { background-color: #10b981; }
        .status-warning { background-color: #f59e0b; }
        .status-error { background-color: #ef4444; }
        
        @media (max-width: 768px) { 
            .main-content { margin-left: 0; padding: 15px; } 
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
            .sidebar.active { transform: translateX(0); }
            .header-section { flex-direction: column; align-items: flex-start; }
        }
        
        .card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .btn-action {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .pagination {
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="header-section">
                <div>
                    <h1 class="h3 mb-0">Admin Dashboard</h1>
                    <p class="text-muted mb-0">Welcome, <?php echo htmlspecialchars($user['first_name']); ?></p>
                </div>
                <div>
                    <small class="text-muted">Last updated: <?php echo date('M j, Y g:i A'); ?></small>
                    <br>
                    <span class="system-status status-<?php echo $db_status; ?>"></span>
                    <small>System: <?php echo ucfirst($db_status); ?></small>
                </div>
            </div>

            <!-- Alerts Section -->
            <?php if (!empty($alerts)): ?>
            <div class="alert-section">
                <?php foreach ($alerts as $alert): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($alert); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Warnings Section -->
            <?php if (!empty($warnings)): ?>
            <div class="alert-section">
                <?php foreach ($warnings as $warning): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($warning); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Key Metrics -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card border-left-primary shadow-sm h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Users</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($user_stats['total_users']); ?></div>
                                    <div class="text-success small">
                                        <i class="fas fa-arrow-up"></i> +<?php echo $user_stats['new_users_7d']; ?> this week
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-3x text-muted opacity-25"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card border-left-success shadow-sm h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Matches</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($match_stats['active_matches']); ?></div>
                                    <div class="text-muted small"><?php echo $match_stats['pending_matches']; ?> pending</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-handshake fa-3x text-muted opacity-25"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card border-left-warning shadow-sm h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Completed Sessions</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($session_stats['completed_sessions']); ?></div>
                                    <div class="text-muted small"><?php echo $session_stats['upcoming_sessions']; ?> upcoming</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-check fa-3x text-muted opacity-25"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card border-left-info shadow-sm h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Avg Rating</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo $rating_stats['avg_rating'] ? number_format($rating_stats['avg_rating'], 1) : 'N/A'; ?></div>
                                    <div class="text-muted small"><?php echo $rating_stats['total_ratings']; ?> ratings</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-star fa-3x text-muted opacity-25"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Secondary Metrics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card border-left-danger shadow-sm">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Verification Rate</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo round($verification_rate); ?>%</div>
                            <div class="text-muted small"><?php echo $user_stats['verified_users']; ?> verified</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Match Success Rate</div>
                            <div class="h5 mb-0 font-weight-bold">
                                <?php echo $match_stats['total_matches'] > 0 ? round(($match_stats['active_matches'] / $match_stats['total_matches']) * 100) : 0; ?>%
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Inactive Users</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo number_format($user_stats['inactive_users']); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">User Growth</div>
                            <div class="h5 mb-0 font-weight-bold">
                                <span class="<?php echo $growth_percent >= 0 ? 'growth-positive' : 'growth-negative'; ?>">
                                    <?php echo $growth_percent >= 0 ? '+' : ''; ?><?php echo round($growth_percent); ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Charts Column -->
                <div class="col-lg-8">
                    <!-- Daily Active Users Chart -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-chart-line"></i> Daily Active Users (Last 7 Days)
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="dailyActiveChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Popular Subjects -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-book"></i> Most Popular Subjects
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="subjectsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Platform Activity -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-fire"></i> Platform Activity (Last 30 Days)
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <div class="mb-3">
                                        <div class="font-weight-bold small">User Logins</div>
                                        <div class="h6 text-primary mb-0"><?php echo number_format($activity_stats['logins']); ?></div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="font-weight-bold small">Match Requests</div>
                                        <div class="h6 text-success mb-0"><?php echo number_format($activity_stats['match_requests']); ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-3">
                                        <div class="font-weight-bold small">Messages Sent</div>
                                        <div class="h6 text-warning mb-0"><?php echo number_format($activity_stats['messages_sent']); ?></div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="font-weight-bold small">Sessions Scheduled</div>
                                        <div class="h6 text-info mb-0"><?php echo number_format($activity_stats['sessions_scheduled']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- User Breakdown -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-pie-chart"></i> User Breakdown
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="small">Students</span>
                                    <span class="font-weight-bold small"><?php echo $user_stats['students']; ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-primary" style="width: <?php echo $user_stats['total_users'] > 0 ? ($user_stats['students'] / $user_stats['total_users']) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="small">Mentors</span>
                                    <span class="font-weight-bold small"><?php echo $user_stats['mentors']; ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $user_stats['total_users'] > 0 ? ($user_stats['mentors'] / $user_stats['total_users']) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="small">Verified Users</span>
                                    <span class="font-weight-bold small"><?php echo $user_stats['verified_users']; ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo $user_stats['total_users'] > 0 ? ($user_stats['verified_users'] / $user_stats['total_users']) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card shadow-sm">
                        <div class="card-header py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-history"></i> Recent Activity
                                </h6>
                                <a href="activity-log.php" class="text-primary small">View All</a>
                            </div>
                        </div>
                        <div class="card-body" style="max-height: 450px; overflow-y: auto;">
                            <?php if (empty($recent_activity)): ?>
                            <p class="text-muted text-center py-3">No recent activity</p>
                            <?php else: ?>
                                <?php foreach (array_slice($recent_activity, 0, 10) as $activity): ?>
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong class="small"><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong>
                                            <span class="badge badge-sm badge-light"><?php echo ucfirst($activity['role']); ?></span>
                                        </div>
                                        <small class="text-muted"><?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?></small>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <?php echo ucfirst(str_replace('_', ' ', $activity['action'])); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 5,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                },
                plugins: {
                    legend: { display: false }
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
                    legend: { position: 'bottom' }
                }
            }
        });

        // Mobile sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('active');
            }
        });
    </script>
</body>
</html>