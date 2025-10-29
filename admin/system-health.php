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

// Date Range Filtering Logic
$default_start_date = date('Y-m-d', strtotime('-30 days'));
$default_end_date = date('Y-m-d');

$start_date = $_GET['start_date'] ?? $default_start_date;
$end_date = $_GET['end_date'] ?? $default_end_date;

function format_date_for_sql($date_string, $is_end = false) {
    $datetime = new DateTime($date_string);
    if ($is_end) {
        return $datetime->format('Y-m-d 23:59:59');
    }
    return $datetime->format('Y-m-d 00:00:00');
}

$sql_start_date = format_date_for_sql($start_date);
$sql_end_date = format_date_for_sql($end_date, true);

// Data Fetching Function
function getSystemData($db, $sql_start_date, $sql_end_date) {
    $data = [
        'alerts' => [],
        'performance_metrics' => [],
        'recommendations' => [],
        'system_status' => 'healthy'
    ];

    // Alert 1: Low verification rate
    $verification_rate = $db->query("
        SELECT 
            COUNT(CASE WHEN is_verified = 1 THEN 1 END) * 100.0 / COUNT(*) as rate
        FROM users WHERE role IN ('mentor', 'peer')
    ")->fetch();

    if ($verification_rate['rate'] < 70) {
        $data['alerts'][] = [
            'type' => 'warning',
            'icon' => 'fa-user-check',
            'title' => 'Low Mentor Verification Rate',
            'message' => 'Only ' . round($verification_rate['rate']) . '% of mentors are verified',
            'action' => 'verifications.php',
            'action_text' => 'Review Verifications',
            'severity' => 'medium'
        ];
        $data['system_status'] = 'warning';
    }

    // Alert 2: High match rejection rate
    $match_stats = $db->query("
        SELECT 
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) * 100.0 / COUNT(*) as rejection_rate
        FROM matches 
        WHERE created_at BETWEEN DATE_SUB('$sql_end_date', INTERVAL 7 DAY) AND '$sql_end_date'
    ")->fetch();

    if ($match_stats['rejection_rate'] > 30) {
        $data['alerts'][] = [
            'type' => 'danger',
            'icon' => 'fa-times-circle',
            'title' => 'High Match Rejection Rate',
            'message' => round($match_stats['rejection_rate']) . '% of matches are being rejected',
            'action' => 'matches.php',
            'action_text' => 'View Matches',
            'severity' => 'high'
        ];
        $data['system_status'] = 'critical';
    }

    // Alert 3: Pending commission payments
    $pending_commissions = $db->query("
        SELECT COUNT(*) as count FROM commission_payments WHERE payment_status = 'pending'
    ")->fetch();

    if ($pending_commissions['count'] > 10) {
        $data['alerts'][] = [
            'type' => 'warning',
            'icon' => 'fa-hourglass-half',
            'title' => 'Pending Commission Payments',
            'message' => $pending_commissions['count'] . ' payments awaiting verification',
            'action' => 'commissions.php',
            'action_text' => 'Process Payments',
            'severity' => 'medium'
        ];
    }

    // Alert 4: Unresolved user reports
    $unresolved_reports = $db->query("
        SELECT COUNT(*) as count FROM user_reports WHERE status = 'pending'
    ")->fetch();

    if ($unresolved_reports['count'] > 5) {
        $data['alerts'][] = [
            'type' => 'danger',
            'icon' => 'fa-flag',
            'title' => 'Unresolved User Reports',
            'message' => $unresolved_reports['count'] . ' reports pending review',
            'action' => 'reports.php',
            'action_text' => 'Review Reports',
            'severity' => 'high'
        ];
        $data['system_status'] = 'critical';
    }

    // Alert 5: Low session completion rate
    $session_completion = $db->query("
        SELECT 
            COUNT(CASE WHEN status = 'completed' THEN 1 END) * 100.0 / COUNT(*) as rate
        FROM sessions 
        WHERE created_at BETWEEN '$sql_start_date' AND '$sql_end_date'
    ")->fetch();

    if ($session_completion['rate'] < 60) {
        $data['alerts'][] = [
            'type' => 'warning',
            'icon' => 'fa-calendar-times',
            'title' => 'Low Session Completion Rate',
            'message' => 'Only ' . round($session_completion['rate']) . '% of sessions were completed',
            'action' => 'sessions.php',
            'action_text' => 'View Sessions',
            'severity' => 'medium'
        ];
    }

    // Get system performance metrics
    $data['performance_metrics'] = [
        'total_users' => $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'")->fetch()['count'],
        'active_users_in_period' => $db->query("
            SELECT COUNT(DISTINCT user_id) as count 
            FROM user_activity_logs 
            WHERE created_at BETWEEN '$sql_start_date' AND '$sql_end_date'
        ")->fetch()['count'],
        'pending_matches' => $db->query("SELECT COUNT(*) as count FROM matches WHERE status = 'pending'")->fetch()['count'],
        'avg_session_rating' => $db->query("
            SELECT AVG(rating) as avg 
            FROM session_ratings sr
            JOIN sessions s ON sr.session_id = s.id
            WHERE s.created_at BETWEEN '$sql_start_date' AND '$sql_end_date'
        ")->fetch()['avg'],
        'total_revenue' => $db->query("
            SELECT SUM(commission_amount) as total 
            FROM commission_payments 
            WHERE payment_status = 'verified' AND payment_date BETWEEN '$sql_start_date' AND '$sql_end_date'
        ")->fetch()['total'],
        'new_users' => $db->query("
            SELECT COUNT(*) as count 
            FROM users 
            WHERE created_at BETWEEN '$sql_start_date' AND '$sql_end_date'
        ")->fetch()['count'],
        'completed_sessions' => $db->query("
            SELECT COUNT(*) as count 
            FROM sessions 
            WHERE status = 'completed' AND created_at BETWEEN '$sql_start_date' AND '$sql_end_date'
        ")->fetch()['count']
    ];

    // Recommendation 1: Mentor shortage
    $mentor_student_ratio = $db->query("
        SELECT 
            COUNT(CASE WHEN role = 'mentor' THEN 1 END) as mentors,
            COUNT(CASE WHEN role = 'student' THEN 1 END) as students
        FROM users
    ")->fetch();

    if ($mentor_student_ratio['students'] > 0 && $mentor_student_ratio['mentors'] / $mentor_student_ratio['students'] < 0.2) {
        $data['recommendations'][] = [
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
        $data['recommendations'][] = [
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
        WHERE s.created_at BETWEEN '$sql_start_date' AND '$sql_end_date'
        GROUP BY price_range
        ORDER BY avg_rating DESC
        LIMIT 1
    ")->fetch();

    if ($avg_rating_by_price) {
        $data['recommendations'][] = [
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
        $data['recommendations'][] = [
            'priority' => 'medium',
            'icon' => 'fa-bell',
            'title' => 'Re-engage Inactive Users',
            'description' => $inactive_users['count'] . ' users have been inactive for 30+ days',
            'impact' => 'Send re-engagement campaigns to boost platform activity'
        ];
    }
    
    return $data;
}

// AJAX/JSON Endpoint
if (isset($_GET['fetch_data']) && $_GET['fetch_data'] === 'true') {
    header('Content-Type: application/json');
    $data = getSystemData($db, $sql_start_date, $sql_end_date);
    echo json_encode($data);
    exit;
}

// Fetch data for initial page load
$system_data = getSystemData($db, $sql_start_date, $sql_end_date);
$alerts = $system_data['alerts'];
$performance_metrics = $system_data['performance_metrics'];
$recommendations = $system_data['recommendations'];
$system_status = $system_data['system_status'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health & Alerts - StudyConnect Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
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
        
        /* Card Styles */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .border-left-primary { border-left: 4px solid #2563eb; }
        .border-left-success { border-left: 4px solid #10b981; }
        .border-left-warning { border-left: 4px solid #f59e0b; }
        .border-left-info { border-left: 4px solid #06b6d4; }
        .border-left-danger { border-left: 4px solid #ef4444; }
        .border-left-secondary { border-left: 4px solid #6b7280; }
        .border-left-purple { border-left: 4px solid #8b5cf6; }
        
        /* Enhanced Alert Cards */
        .alert-card {
            border-left: 4px solid;
            transition: all 0.3s;
            border-radius: 10px;
        }
        
        .alert-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .alert-danger {
            border-left-color: #ef4444;
            background: linear-gradient(90deg, rgba(239, 68, 68, 0.05) 0%, white 100%);
        }
        
        .alert-warning {
            border-left-color: #f59e0b;
            background: linear-gradient(90deg, rgba(245, 158, 11, 0.05) 0%, white 100%);
        }
        
        .alert-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .alert-danger .alert-icon {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .alert-warning .alert-icon {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        /* Recommendation Cards */
        .recommendation-card {
            border-left: 4px solid #667eea;
            transition: all 0.3s;
        }
        
        .recommendation-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.15);
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            animation: pulse-dot 2s infinite;
        }
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.2); }
        }
        
        .status-healthy {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-healthy .status-dot {
            background: #10b981;
        }
        
        .status-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-warning .status-dot {
            background: #f59e0b;
        }
        
        .status-critical {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-critical .status-dot {
            background: #ef4444;
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
        
        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .loading-overlay.active {
            display: flex;
        }
        
        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Auto-update indicator */
        .auto-update-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: #10b981;
            color: white;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .auto-update-badge .pulse {
            width: 6px;
            height: 6px;
            background: white;
            border-radius: 50%;
            animation: pulse-dot 2s infinite;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin-header.php'; ?>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>
    
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="mobile-overlay" id="mobileOverlay"></div>
    
    <div class="sidebar" id="sidebar">
        <div class="p-4">
            <h4 class="text-white mb-0">Admin Panel</h4>
            <small class="text-white-50">Study Mentorship Platform</small>
        </div>
        <nav class="nav flex-column px-2">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                <i class="fas fa-users me-2"></i> User Management
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'matches.php' ? 'active' : ''; ?>" href="matches.php">
                <i class="fas fa-handshake me-2"></i> Matches
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sessions.php' ? 'active' : ''; ?>" href="sessions.php">
                <i class="fas fa-video me-2"></i> Sessions
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>" href="analytics.php">
                <i class="fas fa-chart-bar me-2"></i> Advanced Analytics
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'system-health.php' ? 'active' : ''; ?>" href="system-health.php">
                <i class="fas fa-heartbeat me-2"></i> System Health
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'financial-overview.php' ? 'active' : ''; ?>" href="financial-overview.php">
                <i class="fas fa-chart-pie me-2"></i> Financial Overview
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'referral-audit.php' ? 'active' : ''; ?>" href="referral-audit.php">
                <i class="fas fa-link me-2"></i> Referral Audit
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                <i class="fas fa-cog me-2"></i> System Settings
            </a>
        </nav>
    </div>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 text-gray-800">System Health & Alerts</h1>
                    <p class="text-muted mb-0">Real-time monitoring and actionable recommendations</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="auto-update-badge">
                        <span class="pulse"></span>
                        Auto-updating
                    </div>
                    <div class="status-badge status-<?php echo $system_status; ?>" id="systemStatusBadge">
                        <span class="status-dot"></span>
                        <span id="statusText"><?php echo ucfirst($system_status); ?></span>
                    </div>
                    <small class="text-muted">
                        Updated: <strong id="lastUpdate">--:--:--</strong>
                    </small>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter me-2 text-primary"></i>Filter Data Range
                        </h5>
                        <button class="btn btn-sm btn-outline-secondary" onclick="resetToDefaults()">
                            <i class="fas fa-redo me-1"></i> Reset
                        </button>
                    </div>
                    <form id="date-filter-form" method="GET" class="row g-3 align-items-end" onsubmit="fetchSystemData(); return false;">
                        <div class="col-md-3 col-lg-2">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                value="<?php echo htmlspecialchars($start_date); ?>" required>
                        </div>
                        <div class="col-md-3 col-lg-2">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                value="<?php echo htmlspecialchars($end_date); ?>" required>
                        </div>
                        <div class="col-md-2 col-lg-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-1"></i>Filter
                            </button>
                        </div>
                        <div class="col-md-2 col-lg-2">
                            <a href="system-health.php" class="btn btn-secondary w-100">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </div>
                        <div class="col-12">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="setDateRange(7)">Last 7 Days</button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="setDateRange(30)">Last 30 Days</button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="setDateRange(90)">Last 90 Days</button>
                            </div>
                        </div>
                    </form>
                    <p class="mt-3 mb-0 text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        <small>Displaying data from <strong id="period-start"><?php echo htmlspecialchars($start_date); ?></strong> to <strong id="period-end"><?php echo htmlspecialchars($end_date); ?></strong></small>
                    </p>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="row mb-4" id="metrics-content">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Users</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="metric-total_users"><?php echo number_format($performance_metrics['total_users']); ?></div>
                                    <small class="text-muted">(All time)</small>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Users</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="metric-active_users_in_period"><?php echo number_format($performance_metrics['active_users_in_period']); ?></div>
                                    <small class="text-muted">(In period)</small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-check fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Matches</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="metric-pending_matches"><?php echo $performance_metrics['pending_matches']; ?></div>
                                    <small class="text-muted">(Current)</small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-handshake fa-2x text-gray-300"></i>
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
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="metric-avg_session_rating"><?php echo $performance_metrics['avg_session_rating'] ? number_format($performance_metrics['avg_session_rating'], 1) : 'N/A'; ?></div>
                                    <small class="text-muted">(In period)</small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-star fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Revenue</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="metric-total_revenue">₱<?php echo $performance_metrics['total_revenue'] ? number_format($performance_metrics['total_revenue'], 2) : '0.00'; ?></div>
                                    <small class="text-muted">(In period)</small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-peso-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">New Users</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="metric-new_users"><?php echo number_format($performance_metrics['new_users']); ?></div>
                                    <small class="text-muted">(In period)</small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-purple shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-purple text-uppercase mb-1">Completed Sessions</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="metric-completed_sessions"><?php echo number_format($performance_metrics['completed_sessions']); ?></div>
                                    <small class="text-muted">(In period)</small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-video fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Critical Alerts -->
            <div id="alerts-container" class="mb-4">
                <?php if (!empty($alerts)): ?>
                    <div class="card shadow mb-3">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>Critical Alerts
                                <span class="badge bg-danger ms-2"><?php echo count($alerts); ?></span>
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <?php foreach ($alerts as $alert): ?>
                                <div class="alert-card alert-<?php echo $alert['type']; ?> p-3 m-3">
                                    <div class="d-flex align-items-center">
                                        <div class="alert-icon">
                                            <i class="fas <?php echo $alert['icon']; ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1 fw-bold"><?php echo $alert['title']; ?></h6>
                                            <p class="mb-0 text-muted"><?php echo $alert['message']; ?></p>
                                        </div>
                                        <div class="text-end ms-3">
                                            <span class="badge bg-<?php echo $alert['severity'] === 'high' ? 'danger' : 'warning'; ?> mb-2">
                                                <?php echo ucfirst($alert['severity']); ?> Priority
                                            </span>
                                            <br>
                                            <a href="<?php echo $alert['action']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-arrow-right me-1"></i><?php echo $alert['action_text']; ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card shadow border-success">
                        <div class="card-body text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-check-circle text-success" style="font-size: 48px;"></i>
                            </div>
                            <h5 class="text-success mb-2">All Systems Operating Normally</h5>
                            <p class="text-muted mb-0">No critical alerts at this time. Keep up the great work!</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recommendations -->
            <div id="recommendations-container" class="mb-4">
                <?php if (!empty($recommendations)): ?>
                    <div class="card shadow">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-lightbulb me-2"></i>Smart Recommendations
                                <span class="badge bg-primary ms-2"><?php echo count($recommendations); ?></span>
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <?php foreach ($recommendations as $rec): ?>
                                <div class="recommendation-card p-3 m-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="alert-icon me-3" style="background: rgba(102, 126, 234, 0.1); color: #667eea;">
                                                    <i class="fas <?php echo $rec['icon']; ?>"></i>
                                                </div>
                                                <h6 class="mb-0 fw-bold"><?php echo $rec['title']; ?></h6>
                                            </div>
                                            <p class="text-muted mb-2"><?php echo $rec['description']; ?></p>
                                            <small class="text-success">
                                                <i class="fas fa-chart-line me-1"></i><?php echo $rec['impact']; ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?php echo $rec['priority'] === 'high' ? 'danger' : 'warning'; ?> ms-3">
                                            <?php echo ucfirst($rec['priority']); ?> Priority
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Metric Formatters
        const metricFormatters = {
            'total_users': (val) => val ? new Intl.NumberFormat().format(val) : '0',
            'active_users_in_period': (val) => val ? new Intl.NumberFormat().format(val) : '0',
            'pending_matches': (val) => val ? val : '0',
            'avg_session_rating': (val) => val ? parseFloat(val).toFixed(1) : 'N/A',
            'total_revenue': (val) => val ? '₱' + new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2 }).format(val) : '₱0.00',
            'new_users': (val) => val ? new Intl.NumberFormat().format(val) : '0',
            'completed_sessions': (val) => val ? new Intl.NumberFormat().format(val) : '0'
        };

        // Render Functions
        function renderMetrics(metrics) {
            for (const key in metrics) {
                const element = document.getElementById(`metric-${key}`);
                if (element) {
                    element.textContent = metricFormatters[key] ? metricFormatters[key](metrics[key]) : metrics[key];
                }
            }
        }

        function renderAlerts(alerts) {
            const container = document.getElementById('alerts-container');
            let html = '';

            if (alerts.length > 0) {
                html += `
                    <div class="card shadow mb-3">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>Critical Alerts
                                <span class="badge bg-danger ms-2">${alerts.length}</span>
                            </h6>
                        </div>
                        <div class="card-body p-0">
                `;
                alerts.forEach(alert => {
                    html += `
                        <div class="alert-card alert-${alert.type} p-3 m-3">
                            <div class="d-flex align-items-center">
                                <div class="alert-icon">
                                    <i class="fas ${alert.icon}"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 fw-bold">${alert.title}</h6>
                                    <p class="mb-0 text-muted">${alert.message}</p>
                                </div>
                                <div class="text-end ms-3">
                                    <span class="badge bg-${alert.severity === 'high' ? 'danger' : 'warning'} mb-2">
                                        ${alert.severity.charAt(0).toUpperCase() + alert.severity.slice(1)} Priority
                                    </span>
                                    <br>
                                    <a href="${alert.action}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-arrow-right me-1"></i>${alert.action_text}
                                    </a>
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div></div>';
            } else {
                html += `
                    <div class="card shadow border-success">
                        <div class="card-body text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-check-circle text-success" style="font-size: 48px;"></i>
                            </div>
                            <h5 class="text-success mb-2">All Systems Operating Normally</h5>
                            <p class="text-muted mb-0">No critical alerts at this time. Keep up the great work!</p>
                        </div>
                    </div>
                `;
            }

            container.innerHTML = html;
        }
        
        function renderRecommendations(recommendations) {
            const container = document.getElementById('recommendations-container');
            let html = '';
            
            if (recommendations.length > 0) {
                html += `
                    <div class="card shadow">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-lightbulb me-2"></i>Smart Recommendations
                                <span class="badge bg-primary ms-2">${recommendations.length}</span>
                            </h6>
                        </div>
                        <div class="card-body p-0">
                `;
                recommendations.forEach(rec => {
                    const badgeClass = rec.priority === 'high' ? 'danger' : 'warning';
                    html += `
                        <div class="recommendation-card p-3 m-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="alert-icon me-3" style="background: rgba(102, 126, 234, 0.1); color: #667eea;">
                                            <i class="fas ${rec.icon}"></i>
                                        </div>
                                        <h6 class="mb-0 fw-bold">${rec.title}</h6>
                                    </div>
                                    <p class="text-muted mb-2">${rec.description}</p>
                                    <small class="text-success">
                                        <i class="fas fa-chart-line me-1"></i>${rec.impact}
                                    </small>
                                </div>
                                <span class="badge bg-${badgeClass} ms-3">
                                    ${rec.priority.charAt(0).toUpperCase() + rec.priority.slice(1)} Priority
                                </span>
                            </div>
                        </div>
                    `;
                });
                html += '</div></div>';
            }
            
            container.innerHTML = html;
        }

        function updateSystemStatus(status) {
            const badge = document.getElementById('systemStatusBadge');
            const statusText = document.getElementById('statusText');
            
            badge.className = `status-badge status-${status}`;
            statusText.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        }

        function updateLastUpdate() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit' 
            });
            document.getElementById('lastUpdate').textContent = timeString;
        }

        function fetchSystemData() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            document.getElementById('period-start').textContent = startDate;
            document.getElementById('period-end').textContent = endDate;

            document.getElementById('loadingOverlay').classList.add('active');

            const url = `system-health.php?fetch_data=true&start_date=${startDate}&end_date=${endDate}`;

            fetch(url)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    renderMetrics(data.performance_metrics);
                    renderAlerts(data.alerts);
                    renderRecommendations(data.recommendations);
                    updateSystemStatus(data.system_status);
                    updateLastUpdate();
                    document.getElementById('loadingOverlay').classList.remove('active');
                })
                .catch(error => {
                    console.error('Error fetching real-time data:', error);
                    document.getElementById('loadingOverlay').classList.remove('active');
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to update data. Please try again.',
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
                });
                
            return false;
        }

        // Date Range Shortcuts
        function setDateRange(days) {
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - days);
            
            document.getElementById('end_date').value = endDate.toISOString().split('T')[0];
            document.getElementById('start_date').value = startDate.toISOString().split('T')[0];
            
            fetchSystemData();
        }

        function resetToDefaults() {
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - 30);
            
            document.getElementById('end_date').value = endDate.toISOString().split('T')[0];
            document.getElementById('start_date').value = startDate.toISOString().split('T')[0];
            
            fetchSystemData();
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateLastUpdate(); 
            setInterval(fetchSystemData, 15000); // Auto-update every 15 seconds
            
            document.getElementById('date-filter-form').onsubmit = function(event) {
                event.preventDefault();
                fetchSystemData();
            };
        });

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