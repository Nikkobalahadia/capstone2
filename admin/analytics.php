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


// 2.1 Number of registered users (daily, weekly, monthly)
$user_registration_stats = $db->query("
    SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN created_at >= CURDATE() THEN 1 END) as today_registrations,
        COUNT(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_registrations,
        COUNT(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as month_registrations
    FROM users WHERE role != 'admin'
")->fetch();

// 2.2 Daily, weekly, and monthly active users
$active_users_stats = $db->query("
    SELECT 
        COUNT(DISTINCT CASE WHEN ual.created_at >= CURDATE() THEN ual.user_id END) as daily_active,
        COUNT(DISTINCT CASE WHEN ual.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN ual.user_id END) as weekly_active,
        COUNT(DISTINCT CASE WHEN ual.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN ual.user_id END) as monthly_active
    FROM user_activity_logs ual
    JOIN users u ON ual.user_id = u.id
    WHERE u.role != 'admin'
")->fetch();

// 2.3 Most requested subjects or strands
$popular_subjects = $db->query("
    SELECT 
        us.subject_name,
        COUNT(CASE WHEN u.role = 'student' THEN 1 END) as student_demand,
        COUNT(CASE WHEN u.role IN ('mentor', 'peer') THEN 1 END) as mentor_supply,
        COUNT(*) as total_count,
        ROUND(
            COUNT(CASE WHEN u.role = 'student' THEN 1 END) / 
            NULLIF(COUNT(CASE WHEN u.role IN ('mentor', 'peer') THEN 1 END), 0), 
            2
        ) as demand_supply_ratio
    FROM user_subjects us
    JOIN users u ON us.user_id = u.id
    WHERE u.role != 'admin' AND u.is_active = 1
    GROUP BY us.subject_name
    HAVING student_demand > 0 OR mentor_supply > 0
    ORDER BY student_demand DESC
    LIMIT 15
")->fetchAll();

$popular_strands = $db->query("
    SELECT 
        strand,
        COUNT(*) as count
    FROM users 
    WHERE strand IS NOT NULL AND strand != '' AND role != 'admin'
    GROUP BY strand
    ORDER BY count DESC
    LIMIT 10
")->fetchAll();

// 2.4 Peak hours of platform activity
$peak_hours = $db->query("
    SELECT 
        HOUR(created_at) as hour,
        COUNT(*) as activity_count,
        COUNT(DISTINCT user_id) as unique_users
    FROM user_activity_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY HOUR(created_at)
    ORDER BY hour
")->fetchAll();

// 2.5 Match success rates vs rematch requests
$match_analytics = $db->query("
    SELECT 
        COUNT(*) as total_matches,
        COUNT(CASE WHEN status = 'accepted' THEN 1 END) as successful_matches,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_matches,
        ROUND(COUNT(CASE WHEN status = 'accepted' THEN 1 END) * 100.0 / COUNT(*), 2) as success_rate
    FROM matches
")->fetch();

$rematch_requests = $db->query("
    SELECT 
        COUNT(*) as rematch_count
    FROM matches m1
    WHERE EXISTS (
        SELECT 1 FROM matches m2 
        WHERE m2.student_id = m1.student_id 
        AND m2.mentor_id = m1.mentor_id 
        AND m2.id != m1.id
    )
")->fetch();

// 2.6 Feedback trends and common user concerns

// 2.7 Session cancellation reasons and user inactivity
$session_cancellation_stats = $db->query("
    SELECT 
        status,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM sessions), 2) as percentage
    FROM sessions
    GROUP BY status
    ORDER BY count DESC
")->fetchAll();

$cancellation_reasons = $db->query("
    SELECT 
        cancellation_reason,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM sessions WHERE status = 'cancelled'), 2) as percentage
    FROM sessions 
    WHERE status = 'cancelled' AND cancellation_reason IS NOT NULL
    GROUP BY cancellation_reason
    ORDER BY count DESC
    LIMIT 10
")->fetchAll();


// Optimal time slots analysis
$optimal_time_slots = $db->query("
    SELECT 
        HOUR(s.start_time) as hour,
        COUNT(*) as session_count,
        COUNT(CASE WHEN s.status = 'completed' THEN 1 END) as completed_sessions,
        ROUND(COUNT(CASE WHEN s.status = 'completed' THEN 1 END) * 100.0 / COUNT(*), 2) as completion_rate
    FROM sessions s
    WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    GROUP BY HOUR(s.start_time)
    ORDER BY completion_rate DESC, session_count DESC
")->fetchAll();

// User journey analytics (registration to first session)
$user_journey = $db->query("
    SELECT 
        AVG(DATEDIFF(first_session.session_date, u.created_at)) as avg_days_to_first_session,
        COUNT(CASE WHEN first_session.session_date IS NOT NULL THEN 1 END) as users_with_sessions,
        COUNT(*) as total_users,
        ROUND(COUNT(CASE WHEN first_session.session_date IS NOT NULL THEN 1 END) * 100.0 / COUNT(*), 2) as conversion_rate
    FROM users u
    LEFT JOIN (
        SELECT 
            CASE WHEN u2.role = 'student' THEN m.student_id ELSE m.mentor_id END as user_id,
            MIN(s.session_date) as session_date
        FROM sessions s
        JOIN matches m ON s.match_id = m.id
        JOIN users u2 ON (u2.id = m.student_id OR u2.id = m.mentor_id)
        GROUP BY user_id
    ) first_session ON u.id = first_session.user_id
    WHERE u.role != 'admin' AND u.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
")->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Analytics - StudyConnect Admin</title>
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
        .metric-card { transition: transform 0.2s; }
        .metric-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            
            
            <main class="col-md-10 ms-sm-auto main-content">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-0 text-gray-800">Advanced Analytics</h1>
                            <p class="text-muted">Comprehensive platform performance metrics and insights.</p>
                        </div>
                        <div>
                            <button class="btn btn-primary" onclick="window.print()">
                                <i class="fas fa-download me-2"></i>Export Report
                            </button>
                        </div>
                    </div>

                    <!-- User Registration & Activity Metrics -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card metric-card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Registered Users</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($user_registration_stats['total_users']); ?></div>
                                            <div class="text-success small">+<?php echo $user_registration_stats['month_registrations']; ?> this month</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card metric-card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Monthly Active Users</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($active_users_stats['monthly_active']); ?></div>
                                            <div class="text-muted small"><?php echo $active_users_stats['weekly_active']; ?> weekly | <?php echo $active_users_stats['daily_active']; ?> today</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card metric-card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Match Success Rate</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $match_analytics['success_rate']; ?>%</div>
                                            <div class="text-muted small"><?php echo $rematch_requests['rematch_count']; ?> rematches</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-handshake fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card metric-card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">User Conversion Rate</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $user_journey['conversion_rate']; ?>%</div>
                                            <div class="text-muted small">Avg <?php echo round($user_journey['avg_days_to_first_session']); ?> days to first session</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-route fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Peak Hours Analysis -->
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Peak Activity Hours (Last 30 Days)</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="peakHoursChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Subject Demand vs Supply -->
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Subject Demand vs Supply</h6>
                                    <small class="text-muted">Students seeking help vs Mentors/Peers offering help</small>
                                </div>
                                <div class="card-body">
                                    <canvas id="demandSupplyChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Popular Strands -->
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Most Popular Strands</h6>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($popular_strands as $strand): ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span><?php echo htmlspecialchars($strand['strand']); ?></span>
                                                <span class="font-weight-bold"><?php echo $strand['count']; ?></span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-primary" style="width: <?php echo ($strand['count'] / $popular_strands[0]['count']) * 100; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Session Status Breakdown -->
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Session Status Distribution</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="sessionStatusChart" width="400" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Removed "Common User Concerns" section -->

                    <!-- Session Cancellation Reasons -->
                    <div class="row">
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Session Cancellation Reasons</h6>
                                    <small class="text-muted">Understanding why sessions are cancelled</small>
                                </div>
                                <div class="card-body">
                                    <canvas id="cancellationReasonsChart" width="400" height="300"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6 mb-4">
                            <div class="card shadow">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Cancellation Trends</h6>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($cancellation_reasons as $reason): ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span><?php echo htmlspecialchars($reason['cancellation_reason']); ?></span>
                                                <span class="font-weight-bold"><?php echo $reason['count']; ?> (<?php echo $reason['percentage']; ?>%)</span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-danger" style="width: <?php echo $reason['percentage']; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Optimal Time Slots -->
                    <div class="row">
                        <div class="col-lg-12 mb-4">
                            <div class="card shadow">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Optimal Time Slots for Sessions</h6>
                                    <small class="text-muted">Based on completion rates and session volume</small>
                                </div>
                                <div class="card-body">
                                    <canvas id="optimalTimeSlotsChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Removed "Inactive Users (30+ days)" section -->
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Peak Hours Chart
        const peakHoursCtx = document.getElementById('peakHoursChart').getContext('2d');
        const peakHoursData = <?php echo json_encode($peak_hours); ?>;
        
        new Chart(peakHoursCtx, {
            type: 'bar',
            data: {
                labels: peakHoursData.map(d => d.hour + ':00'),
                datasets: [{
                    label: 'Activity Count',
                    data: peakHoursData.map(d => d.activity_count),
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: '#667eea',
                    borderWidth: 1
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

        // Demand vs Supply Chart
        const demandSupplyCtx = document.getElementById('demandSupplyChart').getContext('2d');
        const demandSupplyData = <?php echo json_encode(array_slice($popular_subjects, 0, 10)); ?>;
        
        new Chart(demandSupplyCtx, {
            type: 'bar',
            data: {
                labels: demandSupplyData.map(d => d.subject_name),
                datasets: [{
                    label: 'Student Demand',
                    data: demandSupplyData.map(d => parseInt(d.student_demand)),
                    backgroundColor: 'rgba(239, 68, 68, 0.8)',
                    borderColor: '#ef4444',
                    borderWidth: 1
                }, {
                    label: 'Mentor/Peer Supply',
                    data: demandSupplyData.map(d => parseInt(d.mentor_supply)),
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: '#10b981',
                    borderWidth: 1
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
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                if (context.datasetIndex === 0) {
                                    const index = context.dataIndex;
                                    const ratio = demandSupplyData[index].demand_supply_ratio;
                                    return ratio ? `Demand/Supply Ratio: ${ratio}` : '';
                                }
                                return '';
                            }
                        }
                    }
                }
            }
        });

        // Session Status Chart
        const sessionStatusCtx = document.getElementById('sessionStatusChart').getContext('2d');
        const sessionStatusData = <?php echo json_encode($session_cancellation_stats); ?>;
        
        new Chart(sessionStatusCtx, {
            type: 'doughnut',
            data: {
                labels: sessionStatusData.map(d => d.status.charAt(0).toUpperCase() + d.status.slice(1)),
                datasets: [{
                    data: sessionStatusData.map(d => d.count),
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#6b7280', '#667eea']
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

        // Optimal Time Slots Chart
        const optimalTimeSlotsCtx = document.getElementById('optimalTimeSlotsChart').getContext('2d');
        const optimalTimeSlotsData = <?php echo json_encode($optimal_time_slots); ?>;
        
        new Chart(optimalTimeSlotsCtx, {
            type: 'line',
            data: {
                labels: optimalTimeSlotsData.map(d => d.hour + ':00'),
                datasets: [{
                    label: 'Completion Rate (%)',
                    data: optimalTimeSlotsData.map(d => parseFloat(d.completion_rate)),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    yAxisID: 'y',
                    tension: 0.4
                }, {
                    label: 'Session Count',
                    data: optimalTimeSlotsData.map(d => parseInt(d.session_count)),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    yAxisID: 'y1',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Completion Rate (%)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Session Count'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // Cancellation Reasons Chart
        const cancellationReasonsCtx = document.getElementById('cancellationReasonsChart').getContext('2d');
        const cancellationReasonsData = <?php echo json_encode($cancellation_reasons); ?>;
        
        new Chart(cancellationReasonsCtx, {
            type: 'doughnut',
            data: {
                labels: cancellationReasonsData.map(d => d.cancellation_reason),
                datasets: [{
                    data: cancellationReasonsData.map(d => d.count),
                    backgroundColor: [
                        '#ef4444', '#f97316', '#f59e0b', '#eab308', 
                        '#84cc16', '#22c55e', '#10b981', '#14b8a6',
                        '#06b6d4', '#0ea5e9'
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
