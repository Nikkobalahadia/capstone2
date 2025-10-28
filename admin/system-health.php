<?php
require_once '../config/config.php';

if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user || $user['role'] !== 'admin') {
    redirect('dashboard.php');
}

$db = getDB();

// Get critical alerts
$alerts = [];

// Alert 1: Low verification rate
$verification_rate = $db->query("
    SELECT 
        COUNT(CASE WHEN is_verified = 1 THEN 1 END) * 100.0 / COUNT(*) as rate
    FROM users WHERE role IN ('mentor', 'peer')
")->fetch();

if ($verification_rate['rate'] < 70) {
    $alerts[] = [
        'type' => 'warning',
        'icon' => 'fa-user-check',
        'title' => 'Low Mentor Verification Rate',
        'message' => 'Only ' . round($verification_rate['rate']) . '% of mentors are verified',
        'action' => 'verifications.php',
        'action_text' => 'Review Verifications'
    ];
}

// Alert 2: High match rejection rate
$match_stats = $db->query("
    SELECT 
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) * 100.0 / COUNT(*) as rejection_rate
    FROM matches WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetch();

if ($match_stats['rejection_rate'] > 30) {
    $alerts[] = [
        'type' => 'danger',
        'icon' => 'fa-times-circle',
        'title' => 'High Match Rejection Rate',
        'message' => round($match_stats['rejection_rate']) . '% of matches are being rejected',
        'action' => 'matches.php',
        'action_text' => 'View Matches'
    ];
}

// Alert 3: Pending commission payments
$pending_commissions = $db->query("
    SELECT COUNT(*) as count FROM commission_payments WHERE payment_status = 'pending'
")->fetch();

if ($pending_commissions['count'] > 10) {
    $alerts[] = [
        'type' => 'warning',
        'icon' => 'fa-hourglass-half',
        'title' => 'Pending Commission Payments',
        'message' => $pending_commissions['count'] . ' payments awaiting verification',
        'action' => 'commissions.php',
        'action_text' => 'Process Payments'
    ];
}

// Alert 4: Unresolved user reports
$unresolved_reports = $db->query("
    SELECT COUNT(*) as count FROM user_reports WHERE status = 'pending'
")->fetch();

if ($unresolved_reports['count'] > 5) {
    $alerts[] = [
        'type' => 'danger',
        'icon' => 'fa-flag',
        'title' => 'Unresolved User Reports',
        'message' => $unresolved_reports['count'] . ' reports pending review',
        'action' => 'reports.php',
        'action_text' => 'Review Reports'
    ];
}

// Alert 5: Low session completion rate
$session_completion = $db->query("
    SELECT 
        COUNT(CASE WHEN status = 'completed' THEN 1 END) * 100.0 / COUNT(*) as rate
    FROM sessions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch();

if ($session_completion['rate'] < 60) {
    $alerts[] = [
        'type' => 'warning',
        'icon' => 'fa-calendar-times',
        'title' => 'Low Session Completion Rate',
        'message' => 'Only ' . round($session_completion['rate']) . '% of sessions are completed',
        'action' => 'sessions.php',
        'action_text' => 'View Sessions'
    ];
}

// Get system performance metrics
$performance_metrics = [
    'total_users' => $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'")->fetch()['count'],
    'active_today' => $db->query("SELECT COUNT(DISTINCT user_id) as count FROM user_activity_logs WHERE DATE(created_at) = CURDATE()")->fetch()['count'],
    'pending_matches' => $db->query("SELECT COUNT(*) as count FROM matches WHERE status = 'pending'")->fetch()['count'],
    'avg_session_rating' => $db->query("SELECT AVG(rating) as avg FROM session_ratings")->fetch()['avg'],
    'total_revenue' => $db->query("SELECT SUM(commission_amount) as total FROM commission_payments WHERE payment_status = 'verified'")->fetch()['total']
];

// Get recommendations
$recommendations = [];

// Recommendation 1: Mentor shortage
$mentor_student_ratio = $db->query("
    SELECT 
        COUNT(CASE WHEN role = 'mentor' THEN 1 END) as mentors,
        COUNT(CASE WHEN role = 'student' THEN 1 END) as students
    FROM users
")->fetch();

if ($mentor_student_ratio['students'] > 0 && $mentor_student_ratio['mentors'] / $mentor_student_ratio['students'] < 0.2) {
    $recommendations[] = [
        'priority' => 'high',
        'icon' => 'fa-user-tie',
        'title' => 'Recruit More Mentors',
        'description' => 'Current mentor-to-student ratio is ' . round(($mentor_student_ratio['mentors'] / $mentor_student_ratio['students']) * 100) . '%. Consider recruiting more mentors.',
        'impact' => 'Improve match success rate and reduce wait times'
    ];
}

// Recommendation 2: Popular subjects with low supply
$subject_gaps = $db->query("
    SELECT 
        us.subject_name,
        COUNT(CASE WHEN u.role = 'student' THEN 1 END) as demand,
        COUNT(CASE WHEN u.role IN ('mentor', 'peer') THEN 1 END) as supply
    FROM user_subjects us
    JOIN users u ON us.user_id = u.id
    WHERE u.is_active = 1
    GROUP BY us.subject_name
    HAVING demand > supply * 2
    ORDER BY demand DESC
    LIMIT 5
")->fetchAll();

if (!empty($subject_gaps)) {
    $recommendations[] = [
        'priority' => 'high',
        'icon' => 'fa-book',
        'title' => 'Subject Supply Gaps',
        'description' => 'High demand for: ' . implode(', ', array_map(fn($s) => $s['subject_name'], $subject_gaps)),
        'impact' => 'Recruit mentors in these subjects to improve match rates'
    ];
}

// Recommendation 3: Optimize pricing
$avg_rating_by_price = $db->query("
    SELECT 
        ROUND(u.hourly_rate / 100) * 100 as price_range,
        AVG(sr.rating) as avg_rating,
        COUNT(*) as session_count
    FROM session_ratings sr
    JOIN sessions s ON sr.session_id = s.id
    JOIN matches m ON s.match_id = m.id
    JOIN users u ON m.mentor_id = u.id
    GROUP BY price_range
    ORDER BY avg_rating DESC
    LIMIT 1
")->fetch();

if ($avg_rating_by_price) {
    $recommendations[] = [
        'priority' => 'medium',
        'icon' => 'fa-dollar-sign',
        'title' => 'Pricing Optimization',
        'description' => 'Mentors in the ₱' . $avg_rating_by_price['price_range'] . ' range have the highest ratings',
        'impact' => 'Consider promoting this price point to new mentors'
    ];
}

// Recommendation 4: Engagement improvement
$inactive_users = $db->query("
    SELECT COUNT(DISTINCT u.id) as count FROM users u
    WHERE u.role != 'admin' AND u.is_active = 1
    AND u.id NOT IN (
        SELECT DISTINCT user_id FROM user_activity_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    )
")->fetch();

if ($inactive_users['count'] > 0) {
    $recommendations[] = [
        'priority' => 'medium',
        'icon' => 'fa-bell',
        'title' => 'Re-engage Inactive Users',
        'description' => $inactive_users['count'] . ' users have been inactive for 30+ days',
        'impact' => 'Send re-engagement campaigns to boost platform activity'
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health & Alerts - StudyConnect Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- NEW RESPONSIVE STYLES --- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f8f9fa;
            overflow-x: hidden;
        }
        
        /* Sidebar Styles */
        .sidebar { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
            position: fixed; 
            width: 250px; 
            top: 60px; 
            left: 0; 
            z-index: 1000; 
            overflow-y: auto; 
            height: calc(100vh - 60px);
            transition: transform 0.3s ease-in-out;
        }
        
        .sidebar .nav-link { 
            color: rgba(255,255,255,0.8); 
            padding: 12px 20px; 
            border-radius: 8px; 
            margin: 4px 12px;
            transition: all 0.2s;
        }
        
        .sidebar .nav-link:hover, 
        .sidebar .nav-link.active { 
            background: rgba(255,255,255,0.1); 
            color: white; 
        }
        
        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content { 
            margin-left: 250px; 
            padding: 20px; 
            margin-top: 60px;
            transition: margin-left 0.3s ease-in-out;
            width: calc(100% - 250px);
        }
        
        /* Mobile Styles */
        @media (max-width: 768px) { 
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content { 
                margin-left: 0;
                width: 100%;
                padding: 15px;
            }
            
            .mobile-overlay {
                display: none;
                position: fixed;
                top: 60px;
                left: 0;
                width: 100%;
                height: calc(100vh - 60px);
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }
            
            .mobile-overlay.show {
                display: block;
            }
            
            /* Mobile toggle button */
            .mobile-menu-toggle {
                display: block !important;
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 998;
                width: 56px;
                height: 56px;
                border-radius: 50%;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                color: white;
                font-size: 20px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            }
        }
        
        @media (min-width: 769px) {
            .mobile-menu-toggle {
                display: none !important;
            }
        }
        
        /* Scrollbar Styling */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }
        /* --- END OF NEW STYLES --- */

        /* --- PAGE-SPECIFIC STYLES (Kept from original) --- */
        .alert-card { border-left: 4px solid; transition: transform 0.2s; }
        .alert-card:hover { transform: translateX(5px); }
        .alert-danger { border-left-color: #ef4444; background: rgba(239, 68, 68, 0.05); }
        .alert-warning { border-left-color: #f59e0b; background: rgba(245, 158, 11, 0.05); }
        .recommendation-card { border-left: 4px solid #667eea; }
        .metric-box { text-align: center; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .metric-value { font-size: 28px; font-weight: bold; color: #667eea; }
        .metric-label { color: #6b7280; font-size: 14px; margin-top: 5px; }
    </style>
</head>
<body>
    <?php include '../includes/admin-header.php'; ?>
    <?php include '../includes/admin-sidebar.php'; ?>

    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="mobile-overlay" id="mobileOverlay"></div>
    <div class="main-content">
        <div class="container-fluid">
            <div class="mb-4">
                <h1 class="h3 mb-0 text-gray-800">System Health & Alerts</h1>
                <p class="text-muted">Real-time system status and actionable recommendations</p>
            </div>

            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="metric-box">
                        <div class="metric-value"><?php echo number_format($performance_metrics['total_users']); ?></div>
                        <div class="metric-label">Total Users</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="metric-box">
                        <div class="metric-value"><?php echo $performance_metrics['active_today']; ?></div>
                        <div class="metric-label">Active Today</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="metric-box">
                        <div class="metric-value"><?php echo $performance_metrics['pending_matches']; ?></div>
                        <div class="metric-label">Pending Matches</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="metric-box">
                        <div class="metric-value"><?php echo $performance_metrics['avg_session_rating'] ? number_format($performance_metrics['avg_session_rating'], 1) : 'N/A'; ?></div>
                        <div class="metric-label">Avg Rating</div>
                    </div>
                </div>
            </div>

            <?php if (!empty($alerts)): ?>
                <div class="mb-4">
                    <h5 class="mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Critical Alerts</h5>
                    <?php foreach ($alerts as $alert): ?>
                        <div class="card alert-card alert-<?php echo $alert['type']; ?> mb-3">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">
                                        <i class="fas <?php echo $alert['icon']; ?> me-2"></i><?php echo $alert['title']; ?>
                                    </h6>
                                    <p class="card-text text-muted mb-0"><?php echo $alert['message']; ?></p>
                                </div>
                                <a href="<?php echo $alert['action']; ?>" class="btn btn-sm btn-primary">
                                    <?php echo $alert['action_text']; ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-success mb-4">
                    <i class="fas fa-check-circle me-2"></i>All systems operating normally. No critical alerts.
                </div>
            <?php endif; ?>

            <?php if (!empty($recommendations)): ?>
                <div class="mb-4">
                    <h5 class="mb-3"><i class="fas fa-lightbulb me-2"></i>Recommendations</h5>
                    <?php foreach ($recommendations as $rec): ?>
                        <div class="card recommendation-card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title">
                                            <i class="fas <?php echo $rec['icon']; ?> me-2"></i><?php echo $rec['title']; ?>
                                        </h6>
                                        <p class="card-text text-muted"><?php echo $rec['description']; ?></p>
                                        <small class="text-success"><i class="fas fa-arrow-up me-1"></i><?php echo $rec['impact']; ?></small>
                                    </div>
                                    <span class="badge bg-<?php echo $rec['priority'] === 'high' ? 'danger' : 'warning'; ?>">
                                        <?php echo ucfirst($rec['priority']); ?> Priority
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Mobile Menu Toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        const mobileOverlay = document.getElementById('mobileOverlay');
        
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            mobileOverlay.classList.toggle('show');
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-bars');
            icon.classList.toggle('fa-times');
        });
        
        mobileOverlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            mobileOverlay.classList.remove('show');
            mobileMenuToggle.querySelector('i').classList.remove('fa-times');
            mobileMenuToggle.querySelector('i').classList.add('fa-bars');
        });
        
        // Close sidebar when clicking a link on mobile
        if (window.innerWidth <= 768) {
            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                link.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    mobileOverlay.classList.remove('show');
                    mobileMenuToggle.querySelector('i').classList.remove('fa-times');
                    mobileMenuToggle.querySelector('i').classList.add('fa-bars');
                });
            });
        }
    </script>
    </body>
</html>