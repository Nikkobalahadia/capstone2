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
                <h1>Analytics Reports</h1>
                <p class="text-secondary">Comprehensive platform analytics and insights.</p>
            </div>

            <!-- User Engagement -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">User Engagement Metrics</h3>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-2" style="gap: 2rem;">
                        <?php foreach ($engagement_metrics as $metric): ?>
                            <div style="padding: 1.5rem; border: 1px solid var(--border-color); border-radius: 8px;">
                                <h4 class="font-semibold mb-3"><?php echo ucfirst($metric['role']); ?>s</h4>
                                <div class="grid grid-cols-2" style="gap: 1rem;">
                                    <div>
                                        <div class="text-sm text-secondary">Total Users</div>
                                        <div class="font-semibold"><?php echo $metric['total_users']; ?></div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-secondary">Active (7d)</div>
                                        <div class="font-semibold"><?php echo $metric['active_7d']; ?></div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-secondary">Active (30d)</div>
                                        <div class="font-semibold"><?php echo $metric['active_30d']; ?></div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-secondary">Avg Sessions</div>
                                        <div class="font-semibold"><?php echo number_format($metric['avg_sessions_per_user'] ?? 0, 1); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Platform Growth Chart -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Platform Growth (Last 12 Months)</h3>
                </div>
                <div class="card-body">
                    <canvas id="growthChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Match Success Analysis -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Match Success Analysis</h3>
                </div>
                <div class="card-body">
                    <canvas id="matchChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Subject Demand Analysis -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Subject Demand vs Supply</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
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
                                    $statusClass = $ratio > 3 ? 'text-red-600' : ($ratio < 1.5 ? 'text-green-600' : 'text-yellow-600');
                                ?>
                                    <tr>
                                        <td class="font-medium"><?php echo htmlspecialchars($subject['subject_name']); ?></td>
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
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Peak Activity Hours (Last 30 Days)</h3>
                </div>
                <div class="card-body">
                    <canvas id="activityChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Session Analysis -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Session Completion Analysis</h3>
                </div>
                <div class="card-body">
                    <canvas id="sessionChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </main>

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
