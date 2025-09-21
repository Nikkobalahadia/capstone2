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

// Get comprehensive analytics
$analytics = [];

// Platform growth (last 12 months)
$growth_data = $db->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as new_users,
        SUM(COUNT(*)) OVER (ORDER BY DATE_FORMAT(created_at, '%Y-%m')) as total_users
    FROM users 
    WHERE role != 'admin' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
")->fetchAll();

// Match success analysis
$match_analysis = $db->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as total_requests,
        COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
        AVG(match_score) as avg_score
    FROM matches 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
")->fetchAll();

// Session completion rates
$session_analysis = $db->query("
    SELECT 
        DATE_FORMAT(session_date, '%Y-%m') as month,
        COUNT(*) as total_sessions,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
        COUNT(CASE WHEN status = 'no_show' THEN 1 END) as no_shows
    FROM sessions 
    WHERE session_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(session_date, '%Y-%m')
    ORDER BY month
")->fetchAll();

// Peak activity hours
$peak_hours = $db->query("
    SELECT 
        HOUR(created_at) as hour,
        COUNT(*) as activity_count
    FROM user_activity_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY HOUR(created_at)
    ORDER BY hour
")->fetchAll();

// Subject demand analysis
$subject_demand = $db->query("
    SELECT 
        us.subject_name,
        COUNT(CASE WHEN u.role = 'student' THEN 1 END) as student_demand,
        COUNT(CASE WHEN u.role = 'mentor' THEN 1 END) as mentor_supply,
        COUNT(DISTINCT m.id) as successful_matches
    FROM user_subjects us
    JOIN users u ON us.user_id = u.id
    LEFT JOIN matches m ON (
        (m.student_id = u.id OR m.mentor_id = u.id) 
        AND m.subject = us.subject_name 
        AND m.status = 'accepted'
    )
    GROUP BY us.subject_name
    HAVING COUNT(*) >= 5
    ORDER BY student_demand DESC
    LIMIT 15
")->fetchAll();

// User engagement metrics
$engagement_metrics = $db->query("
    SELECT 
        u.role,
        COUNT(DISTINCT u.id) as total_users,
        COUNT(DISTINCT CASE WHEN ual.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN u.id END) as active_7d,
        COUNT(DISTINCT CASE WHEN ual.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN u.id END) as active_30d,
        AVG(user_sessions.session_count) as avg_sessions_per_user
    FROM users u
    LEFT JOIN user_activity_logs ual ON u.id = ual.user_id
    LEFT JOIN (
        SELECT 
            CASE WHEN m.student_id = u.id THEN u.id ELSE u.id END as user_id,
            COUNT(s.id) as session_count
        FROM users u
        LEFT JOIN matches m ON (m.student_id = u.id OR m.mentor_id = u.id)
        LEFT JOIN sessions s ON m.id = s.match_id AND s.status = 'completed'
        GROUP BY u.id
    ) user_sessions ON u.id = user_sessions.user_id
    WHERE u.role != 'admin'
    GROUP BY u.role
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Reports - StudyConnect Admin</title>
    <!-- Updated to use Bootstrap and purple admin theme -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; border-radius: 8px; margin: 4px 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } .sidebar { display: none; } }
    </style>
</head>
<body>
    <!-- Replaced horizontal header with purple sidebar navigation -->
    <div class="sidebar position-fixed" style="width: 250px; z-index: 1000;">
        <div class="p-4">
            <h4 class="text-white mb-0">Admin Panel</h4>
            <small class="text-white-50">Study Mentorship Platform</small>
        </div>
        <nav class="nav flex-column px-3">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            <a class="nav-link" href="users.php">
                <i class="fas fa-users me-2"></i> User Management
            </a>
            <a class="nav-link" href="monitoring.php">
                <i class="fas fa-chart-line me-2"></i> System Monitoring
            </a>
            <a class="nav-link" href="reports-inbox.php">
                <i class="fas fa-inbox me-2"></i> Reports & Feedback
            </a>
            <a class="nav-link" href="session-tracking.php">
                <i class="fas fa-calendar-check me-2"></i> Session Tracking
            </a>
            <a class="nav-link" href="matches.php">
                <i class="fas fa-handshake me-2"></i> Matches
            </a>
            <a class="nav-link" href="sessions.php">
                <i class="fas fa-video me-2"></i> Sessions
            </a>
            <a class="nav-link active" href="reports.php">
                <i class="fas fa-chart-bar me-2"></i> Reports
            </a>
        </nav>
        <div class="position-absolute bottom-0 w-100 p-3">
            <a href="../auth/logout.php" class="btn btn-outline-light btn-sm w-100">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </div>
    </div>

    <!-- Updated main content area to work with sidebar layout -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">Analytics Reports</h1>
                    <p class="text-muted">Comprehensive platform analytics and insights.</p>
                </div>
            </div>

            <!-- User Engagement -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">User Engagement Metrics</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($engagement_metrics as $metric): ?>
                            <div class="col-md-6 mb-4">
                                <div class="border rounded p-3">
                                    <h6 class="font-weight-bold mb-3"><?php echo ucfirst($metric['role']); ?>s</h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="small text-muted">Total Users</div>
                                            <div class="font-weight-bold"><?php echo $metric['total_users']; ?></div>
                                        </div>
                                        <div class="col-6">
                                            <div class="small text-muted">Active (7d)</div>
                                            <div class="font-weight-bold"><?php echo $metric['active_7d']; ?></div>
                                        </div>
                                        <div class="col-6">
                                            <div class="small text-muted">Active (30d)</div>
                                            <div class="font-weight-bold"><?php echo $metric['active_30d']; ?></div>
                                        </div>
                                        <div class="col-6">
                                            <div class="small text-muted">Avg Sessions</div>
                                            <div class="font-weight-bold"><?php echo number_format($metric['avg_sessions_per_user'] ?? 0, 1); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Platform Growth Chart -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Platform Growth (Last 12 Months)</h6>
                </div>
                <div class="card-body">
                    <canvas id="growthChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Match Success Analysis -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Match Success Analysis</h6>
                </div>
                <div class="card-body">
                    <canvas id="matchChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Subject Demand Analysis -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Subject Demand vs Supply</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Subject</th>
                                    <th>Student Demand</th>
                                    <th>Mentor Supply</th>
                                    <th>Supply Ratio</th>
                                    <th>Successful Matches</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subject_demand as $subject): 
                                    $ratio = $subject['mentor_supply'] > 0 ? $subject['student_demand'] / $subject['mentor_supply'] : 999;
                                    $status = $ratio > 3 ? 'High Demand' : ($ratio < 1.5 ? 'Well Supplied' : 'Balanced');
                                    $statusClass = $ratio > 3 ? 'text-danger' : ($ratio < 1.5 ? 'text-success' : 'text-warning');
                                ?>
                                    <tr>
                                        <td class="font-weight-bold"><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                        <td><?php echo $subject['student_demand']; ?></td>
                                        <td><?php echo $subject['mentor_supply']; ?></td>
                                        <td><?php echo number_format($ratio, 1); ?>:1</td>
                                        <td><?php echo $subject['successful_matches']; ?></td>
                                        <td class="<?php echo $statusClass; ?>"><?php echo $status; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Peak Activity Hours -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Peak Activity Hours (Last 30 Days)</h6>
                </div>
                <div class="card-body">
                    <canvas id="activityChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Session Analysis -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Session Completion Analysis</h6>
                </div>
                <div class="card-body">
                    <canvas id="sessionChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Added Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Platform Growth Chart
        const growthCtx = document.getElementById('growthChart').getContext('2d');
        new Chart(growthCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($growth_data, 'month')); ?>,
                datasets: [{
                    label: 'New Users',
                    data: <?php echo json_encode(array_column($growth_data, 'new_users')); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Total Users',
                    data: <?php echo json_encode(array_column($growth_data, 'total_users')); ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Match Success Chart
        const matchCtx = document.getElementById('matchChart').getContext('2d');
        new Chart(matchCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($match_analysis, 'month')); ?>,
                datasets: [{
                    label: 'Accepted',
                    data: <?php echo json_encode(array_column($match_analysis, 'accepted')); ?>,
                    backgroundColor: '#10b981'
                }, {
                    label: 'Rejected',
                    data: <?php echo json_encode(array_column($match_analysis, 'rejected')); ?>,
                    backgroundColor: '#ef4444'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true
                    }
                }
            }
        });

        // Activity Hours Chart
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        new Chart(activityCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map(function($h) { return $h['hour'] . ':00'; }, $peak_hours)); ?>,
                datasets: [{
                    label: 'Activity Count',
                    data: <?php echo json_encode(array_column($peak_hours, 'activity_count')); ?>,
                    backgroundColor: '#8b5cf6'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Session Analysis Chart
        const sessionCtx = document.getElementById('sessionChart').getContext('2d');
        new Chart(sessionCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($session_analysis, 'month')); ?>,
                datasets: [{
                    label: 'Completed',
                    data: <?php echo json_encode(array_column($session_analysis, 'completed')); ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)'
                }, {
                    label: 'Cancelled',
                    data: <?php echo json_encode(array_column($session_analysis, 'cancelled')); ?>,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)'
                }, {
                    label: 'No Shows',
                    data: <?php echo json_encode(array_column($session_analysis, 'no_shows')); ?>,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
