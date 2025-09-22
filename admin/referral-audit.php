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

// Get referral code statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_codes,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_codes,
        COUNT(CASE WHEN expires_at < NOW() THEN 1 END) as expired_codes,
        SUM(current_uses) as total_uses,
        AVG(current_uses) as avg_uses_per_code
    FROM referral_codes
")->fetch();

// Get all referral codes with creator info
$codes_stmt = $db->query("
    SELECT rc.*, 
           u.first_name, u.last_name, u.email, u.is_verified,
           COALESCE(referred_count.actual_referrals, 0) as actual_referrals
    FROM referral_codes rc
    JOIN users u ON rc.created_by = u.id
    LEFT JOIN (
        SELECT 
            JSON_EXTRACT(ual.details, '$.referral_code_id') as code_id,
            COUNT(*) as actual_referrals
        FROM user_activity_logs ual
        WHERE ual.action = 'register' 
        AND JSON_EXTRACT(ual.details, '$.referral_used') = true
        AND JSON_EXTRACT(ual.details, '$.referral_code_id') IS NOT NULL
        GROUP BY JSON_EXTRACT(ual.details, '$.referral_code_id')
    ) referred_count ON referred_count.code_id = rc.id
    ORDER BY rc.created_at DESC
");
$referral_codes = $codes_stmt->fetchAll();

// Get referral usage trends (last 30 days)
$trends = $db->query("
    SELECT 
        DATE(ual.created_at) as date,
        COUNT(*) as registrations_with_referral
    FROM user_activity_logs ual
    WHERE ual.action = 'register' 
    AND JSON_EXTRACT(ual.details, '$.referral_used') = true
    AND ual.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(ual.created_at)
    ORDER BY date DESC
")->fetchAll();

// Get top referrers
$top_referrers = $db->query("
    SELECT 
        u.first_name, u.last_name, u.email,
        COUNT(rc.id) as codes_generated,
        SUM(rc.current_uses) as total_uses,
        COALESCE(referred_count.actual_referrals, 0) as actual_referrals
    FROM users u
    JOIN referral_codes rc ON u.id = rc.created_by
    LEFT JOIN (
        SELECT 
            JSON_EXTRACT(ual.details, '$.referred_by') as referrer_id,
            COUNT(*) as actual_referrals
        FROM user_activity_logs ual
        WHERE ual.action = 'register' 
        AND JSON_EXTRACT(ual.details, '$.referral_used') = true
        AND JSON_EXTRACT(ual.details, '$.referred_by') IS NOT NULL
        GROUP BY JSON_EXTRACT(ual.details, '$.referred_by')
    ) referred_count ON referred_count.referrer_id = u.id
    GROUP BY u.id
    ORDER BY actual_referrals DESC, total_uses DESC
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Code Audit - Admin Panel</title>
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
    <!-- Added consistent purple sidebar navigation -->
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
            <a class="nav-link" href="session-tracking.php">
                <i class="fas fa-calendar-check me-2"></i> Session Tracking
            </a>
            <a class="nav-link" href="matches.php">
                <i class="fas fa-handshake me-2"></i> Matches
            </a>
            <a class="nav-link" href="sessions.php">
                <i class="fas fa-video me-2"></i> Sessions
            </a>
            <a class="nav-link" href="reports.php">
                <i class="fas fa-chart-bar me-2"></i> Reports
            </a>
            <a class="nav-link active" href="referral-audit.php">
                <i class="fas fa-share-alt me-2"></i> Referral Audit
            </a>
        </nav>
        <div class="position-absolute bottom-0 w-100 p-3">
            <a href="../auth/logout.php" class="btn btn-outline-light btn-sm w-100">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">Referral Code Audit</h1>
                    <p class="text-muted">Monitor referral code usage and mentor activity.</p>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Codes</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_codes']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-ticket-alt fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Codes</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['active_codes']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Uses</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_uses']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-plus fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Avg Uses/Code</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['avg_uses_per_code'], 1); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Referral Codes Table -->
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">All Referral Codes</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Creator</th>
                                            <th>Usage</th>
                                            <th>Expires</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($referral_codes as $code): ?>
                                            <tr>
                                                <td>
                                                    <code class="bg-light p-1 rounded"><?php echo htmlspecialchars($code['code']); ?></code>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($code['first_name'] . ' ' . $code['last_name']); ?></strong>
                                                        <?php if ($code['is_verified']): ?>
                                                            <i class="fas fa-check-circle text-success ms-1" title="Verified"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($code['email']); ?></small>
                                                </td>
                                                <td>
                                                    <div><?php echo $code['current_uses']; ?> / <?php echo $code['max_uses']; ?></div>
                                                    <div class="progress" style="height: 4px;">
                                                        <div class="progress-bar" style="width: <?php echo ($code['current_uses'] / $code['max_uses']) * 100; ?>%;"></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($code['expires_at']): ?>
                                                        <?php echo date('M j, Y', strtotime($code['expires_at'])); ?>
                                                        <?php if (strtotime($code['expires_at']) < time()): ?>
                                                            <br><small class="text-danger">Expired</small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Never</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $is_expired = $code['expires_at'] && strtotime($code['expires_at']) < time();
                                                    $is_maxed = $code['current_uses'] >= $code['max_uses'];
                                                    
                                                    if (!$code['is_active']) {
                                                        echo '<span class="badge bg-secondary">Inactive</span>';
                                                    } elseif ($is_expired) {
                                                        echo '<span class="badge bg-warning">Expired</span>';
                                                    } elseif ($is_maxed) {
                                                        echo '<span class="badge bg-info">Max Uses</span>';
                                                    } else {
                                                        echo '<span class="badge bg-success">Active</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($code['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Top Referrers -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Top Referrers</h6>
                        </div>
                        <div class="card-body">
                            <?php foreach ($top_referrers as $index => $referrer): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; font-size: 12px;">
                                            #<?php echo $index + 1; ?>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="font-weight-bold"><?php echo htmlspecialchars($referrer['first_name'] . ' ' . $referrer['last_name']); ?></div>
                                        <div class="small text-muted">
                                            <?php echo $referrer['codes_generated']; ?> codes, 
                                            <?php echo $referrer['actual_referrals']; ?> referrals
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Usage Trends -->
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Referral Registrations (Last 30 Days)</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="trendsChart" width="400" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Referral trends chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        const trendsData = <?php echo json_encode(array_reverse($trends)); ?>;
        
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: trendsData.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                datasets: [{
                    label: 'Referral Registrations',
                    data: trendsData.map(d => d.registrations_with_referral),
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
    </script>
</body>
</html>
