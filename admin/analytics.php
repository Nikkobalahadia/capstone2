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

// --- START: Date Range Logic ---
$time_period = isset($_GET['period']) ? $_GET['period'] : 'monthly';
$start_date = isset($_GET['start_date']) ? sanitize_input($_GET['start_date']) : null;
$end_date = isset($_GET['end_date']) ? sanitize_input($_GET['end_date']) : null;

$date_range_where_clause = '';
$date_range_params = [];

if ($start_date && $end_date) {
    // Custom date range
    $date_range_where_clause = "created_at BETWEEN ? AND ?";
    $date_range_params = [$start_date, $end_date];
    $time_period = 'custom';
    $display_period = date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date));
} else {
    // Quick-select time period (safe to use direct SQL functions)
    $date_sql = '';
    switch($time_period) {
        case 'daily':
            $date_sql = "DATE_SUB(NOW(), INTERVAL 1 DAY)";
            $display_period = 'Last 24 Hours';
            break;
        case 'weekly':
            $date_sql = "DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $display_period = 'Last 7 Days';
            break;
        case 'yearly':
            $date_sql = "DATE_SUB(NOW(), INTERVAL 365 DAY)";
            $display_period = 'Last 365 Days';
            break;
        case 'monthly':
        default:
            $date_sql = "DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $time_period = 'monthly';
            $display_period = 'Last 30 Days';
    }
    // Use the safe, self-contained SQL function for non-custom queries
    $date_range_where_clause = "created_at >= $date_sql";
}
// --- END: Date Range Logic ---


// Helper function to execute a date-dependent query securely
function execute_date_query($db, $query_template, $is_custom, $params, $is_fetch_all = true) {
    $stmt = $db->prepare($query_template);
    if ($is_custom) {
        $stmt->execute($params);
    } else {
        // If not custom, the query already contains the necessary SQL date functions
        // and doesn't need external parameters
        $stmt->execute();
    }
    return $is_fetch_all ? $stmt->fetchAll() : $stmt->fetch();
}


// 2.1 Number of registered users (daily, weekly, monthly, yearly)
// NOTE: This query is kept as-is because it uses safe, hardcoded relative dates (CURDATE()).
$user_registration_stats = $db->query("
    SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN created_at >= CURDATE() THEN 1 END) as today_registrations,
        COUNT(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_registrations,
        COUNT(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as month_registrations,
        COUNT(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 365 DAY) THEN 1 END) as year_registrations
    FROM users WHERE role != 'admin'
")->fetch();

// 2.2 Daily, weekly, and monthly active users
// NOTE: This query is kept as-is because it uses safe, hardcoded relative dates (CURDATE()).
$active_users_stats = $db->query("
    SELECT 
        COUNT(DISTINCT CASE WHEN ual.created_at >= CURDATE() THEN ual.user_id END) as daily_active,
        COUNT(DISTINCT CASE WHEN ual.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN ual.user_id END) as weekly_active,
        COUNT(DISTINCT CASE WHEN ual.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN ual.user_id END) as monthly_active,
        COUNT(DISTINCT CASE WHEN ual.created_at >= DATE_SUB(CURDATE(), INTERVAL 365 DAY) THEN ual.user_id END) as yearly_active
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

// 2.4 Peak hours of platform activity (using the dynamic date range)
$peak_hours_query_template = "
    SELECT 
        HOUR(created_at) as hour,
        COUNT(*) as activity_count,
        COUNT(DISTINCT user_id) as unique_users
    FROM user_activity_logs
    WHERE " . $date_range_where_clause . "
    GROUP BY HOUR(created_at)
    ORDER BY hour
";
$peak_hours = execute_date_query($db, $peak_hours_query_template, $time_period === 'custom', $date_range_params);


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

$demand_by_level = $db->query("
    SELECT 
        us.subject_name,
        us.proficiency_level,
        COUNT(CASE WHEN u.role = 'student' THEN 1 END) as student_demand,
        COUNT(CASE WHEN u.role IN ('mentor', 'peer') THEN 1 END) as mentor_supply,
        ROUND(
            COUNT(CASE WHEN u.role = 'student' THEN 1 END) / 
            NULLIF(COUNT(CASE WHEN u.role IN ('mentor', 'peer') THEN 1 END), 0), 
            2
        ) as demand_supply_ratio
    FROM user_subjects us
    JOIN users u ON us.user_id = u.id
    WHERE u.role != 'admin' AND u.is_active = 1
    GROUP BY us.subject_name, us.proficiency_level
    ORDER BY us.subject_name, FIELD(us.proficiency_level, 'beginner', 'intermediate', 'advanced', 'expert')
")->fetchAll();

// Summary by level
$level_summary = $db->query("
    SELECT 
        us.proficiency_level,
        COUNT(CASE WHEN u.role = 'student' THEN 1 END) as student_demand,
        COUNT(CASE WHEN u.role IN ('mentor', 'peer') THEN 1 END) as mentor_supply,
        COUNT(*) as total_count
    FROM user_subjects us
    JOIN users u ON us.user_id = u.id
    WHERE u.role != 'admin' AND u.is_active = 1
    GROUP BY us.proficiency_level
    ORDER BY FIELD(us.proficiency_level, 'beginner', 'intermediate', 'advanced', 'expert')
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


// Feedback trends (using the dynamic date range)
$feedback_trends_query_template = "
    SELECT 
        reason,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM user_reports), 2) as percentage
    FROM user_reports
    WHERE " . $date_range_where_clause . "
    GROUP BY reason
    ORDER BY count DESC
    LIMIT 10
";
$feedback_trends = execute_date_query($db, $feedback_trends_query_template, $time_period === 'custom', $date_range_params);


// Commission revenue (using the dynamic date range)
$commission_revenue_query_template = "
    SELECT 
        COUNT(*) as total_payments,
        COUNT(CASE WHEN payment_status = 'verified' THEN 1 END) as verified_payments,
        COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_payments,
        SUM(CASE WHEN payment_status = 'verified' THEN commission_amount ELSE 0 END) as total_revenue,
        AVG(commission_amount) as avg_commission
    FROM commission_payments
    WHERE " . $date_range_where_clause . "
";
$commission_revenue = execute_date_query($db, $commission_revenue_query_template, $time_period === 'custom', $date_range_params, false);


// User growth trend (using the dynamic date range)
$user_growth_trend_query_template = "
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as new_users
    FROM users
    WHERE role != 'admin' AND " . $date_range_where_clause . "
    GROUP BY DATE(created_at)
    ORDER BY date ASC
";
$user_growth_trend = execute_date_query($db, $user_growth_trend_query_template, $time_period === 'custom', $date_range_params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Analytics - Study Buddy Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* Styles from dashboard.php */
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
        
        /* Card Styles */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .border-left-primary { border-left: 4px solid #2563eb; }
        .border-left-success { border-left: 4px solid #10b981; }
        .border-left-warning { border-left: 4px solid #f59e0b; }
        .border-left-info { border-left: 4px solid #06b6d4; }
        
        /* Chart Container */
        .chart-container {
            position: relative;
            height: 250px;
        }
        
        /* Responsive Typography */
        @media (max-width: 576px) {
            h1.h3 {
                font-size: 1.5rem;
            }
            
            .h5 {
                font-size: 1.1rem;
            }
            
            .card-body {
                padding: 1rem;
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
        
        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(90deg, #6a7ee8 0%, #8765c5 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .welcome-banner-text h2 {
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .welcome-banner-text p {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }
        .welcome-banner-time {
            text-align: right;
            font-size: 0.9rem;
            flex-shrink: 0;
            margin-left: 1rem;
        }
        .welcome-banner-time .time-box {
            background: rgba(255,255,255,0.15);
            padding: 8px 12px;
            border-radius: 8px;
            display: block;
            width: 100%;
            min-width: 190px;
        }
        .welcome-banner-time .time-box:first-child {
            margin-bottom: 8px;
        }
        .welcome-banner-time i {
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }
        
        /* Responsive banner */
        @media (max-width: 768px) {
            .welcome-banner {
                flex-direction: column;
                padding: 1.5rem;
                text-align: center;
            }
            .welcome-banner-time {
                text-align: center;
                margin-top: 1.5rem;
                margin-left: 0;
                width: 100%;
            }
            .welcome-banner-time .time-box {
                 display: inline-block;
                 width: auto;
            }
        }
        
        /* Quick Actions */
        .quick-action-card {
            display: block;
            text-decoration: none;
            color: #333;
            background: #fff;
            border-radius: 10px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            border: 1px solid #e3e6f0;
            height: 100%;
        }
        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-color: #667eea;
        }
        .quick-action-card .icon-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }
        .quick-action-card h5 {
            margin-bottom: 0.25rem;
            font-weight: 600;
        }
        .quick-action-card p {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0;
        }
        .bg-primary-light { background-color: rgba(37, 99, 235, 0.1); color: #2563eb; }
        .bg-success-light { background-color: rgba(16, 185, 129, 0.1); color: #10b981; }
        .bg-warning-light { background-color: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .bg-info-light { background-color: rgba(6, 182, 212, 0.1); color: #06b6d4; }

        /* Specific styles from analytics.php */
        .metric-card { transition: transform 0.2s; }
        .metric-card:hover { transform: translateY(-2px); }
        .nav-tabs .nav-link { color: #667eea; border: none; border-bottom: 3px solid transparent; }
        .nav-tabs .nav-link.active { color: #667eea; border-bottom: 3px solid #667eea; background: none; }
        .period-btn { margin: 0 5px; }
        .period-btn.active { background: #667eea; color: white; }
    </style>
</head>
<body>
    <?php include '../includes/admin-header.php'; ?>

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
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'verifications.php' ? 'active' : ''; ?>" href="verifications.php">
                <i class="fas fa-user-check me-2"></i> Mentor Verification
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'commissions.php' ? 'active' : ''; ?>" href="commissions.php">
                <i class="fas fa-money-bill-wave me-2"></i> Commission Payments
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>" href="analytics.php">
                <i class="fas fa-chart-bar me-2"></i> Advanced Analytics
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'referral-audit.php' ? 'active' : ''; ?>" href="referral-audit.php">
                <i class="fas fa-link me-2"></i> Referral Audit
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'activity-logs.php' ? 'active' : ''; ?>" href="activity-logs.php">
                <i class="fas fa-history me-2"></i> Activity Logs
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'financial-overview.php' ? 'active' : ''; ?>" href="financial-overview.php">
                <i class="fas fa-chart-pie me-2"></i> Financial Overview
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'matches.php' ? 'active' : ''; ?>" href="matches.php">
                <i class="fas fa-handshake me-2"></i> Matches
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sessions.php' ? 'active' : ''; ?>" href="sessions.php">
                <i class="fas fa-video me-2"></i> Sessions
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : ''; ?>" href="announcements.php">
                <i class="fas fa-bullhorn me-2"></i> Announcements
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                <i class="fas fa-cog me-2"></i> System Settings
            </a>
        </nav>
    </div>

    <div class="main-content">
        <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">Advanced Analytics</h1>
                    <p class="text-muted">Metrics for period: <strong><?php echo $display_period; ?></strong></p>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-download me-2"></i>Export Report
                    </button>
                </div>
            </div>

            <div class="mb-4 p-3 bg-white rounded shadow-sm card">
                <label class="mb-2"><strong>Time Period:</strong></label>
                <div class="row g-3">
                    <div class="col-md-5">
                        <a href="?period=daily" class="btn btn-sm period-btn <?php echo $time_period === 'daily' ? 'active' : 'btn-outline-primary'; ?>">Daily</a>
                        <a href="?period=weekly" class="btn btn-sm period-btn <?php echo $time_period === 'weekly' ? 'active' : 'btn-outline-primary'; ?>">Weekly</a>
                        <a href="?period=monthly" class="btn btn-sm period-btn <?php echo $time_period === 'monthly' ? 'active' : 'btn-outline-primary'; ?>">Monthly</a>
                        <a href="?period=yearly" class="btn btn-sm period-btn <?php echo $time_period === 'yearly' ? 'active' : 'btn-outline-primary'; ?>">Yearly</a>
                    </div>
                    <form method="GET" class="col-md-7 row g-3">
                        <input type="hidden" name="period" value="custom">
                        <div class="col-md-5">
                            <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date ?? ''); ?>">
                        </div>
                        <div class="col-md-5">
                            <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date ?? ''); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Go</button>
                        </div>
                    </form>
                </div>
            </div>

            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#user-analytics">User Analytics</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#platform-analytics">Platform & Engagement</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#demand-supply">Demand & Supply</a>
                </li>
            </ul>

            <div class="tab-content" id="myTabContent">
                
                <div class="tab-pane fade show active" id="user-analytics" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header">User Registration (All Time)</div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6 mb-3">
                                            <h5><?php echo $user_registration_stats['total_users']; ?></h5>
                                            <small class="text-muted">Total Users</small>
                                        </div>
                                        <div class="col-6 mb-3">
                                            <h5><?php echo $user_registration_stats['today_registrations']; ?></h5>
                                            <small class="text-muted">Today</small>
                                        </div>
                                        <div class="col-6 mb-3">
                                            <h5><?php echo $user_registration_stats['week_registrations']; ?></h5>
                                            <small class="text-muted">Last 7 Days</small>
                                        </div>
                                         <div class="col-6 mb-3">
                                            <h5><?php echo $user_registration_stats['month_registrations']; ?></h5>
                                            <small class="text-muted">Last 30 Days</small>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class
="chart-container" style="height: 200px;">
                                        <canvas id="userGrowthChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header">Active Users (All Time)</div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6 mb-3">
                                            <h5><?php echo $active_users_stats['daily_active']; ?></h5>
                                            <small class="text-muted">Daily Active</small>
                                        </div>
                                        <div class="col-6 mb-3">
                                            <h5><?php echo $active_users_stats['weekly_active']; ?></h5>
                                            <small class="text-muted">Weekly Active</small>
                                        </div>
                                        <div class="col-6 mb-3">
                                            <h5><?php echo $active_users_stats['monthly_active']; ?></h5>
                                            <small class="text-muted">Monthly Active</small>
                                        </div>
                                        <div class="col-6 mb-3">
                                            <h5><?php echo $active_users_stats['yearly_active']; ?></h5>
                                            <small class="text-muted">Yearly Active</small>
                                        </div>
                                    </div>
                                    <hr>
                                    <small><strong>User Journey (Last 90 Days)</strong></small>
                                    <div class="row mt-2">
                                        <div class="col-6">
                                            <h5><?php echo $user_journey['users_with_sessions']; ?> / <?php echo $user_journey['total_users']; ?></h5>
                                            <small class="text-muted">Users w/ 1st Session</small>
                                        </div>
                                        <div class="col-6">
                                            <h5><?php echo round($user_journey['avg_days_to_first_session'], 1); ?> Days</h5>
                                            <small class="text-muted">Avg. Time to 1st Session</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="platform-analytics" role="tabpanel">
                    <div class="row">
                        <div class="col-md-7 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header">Peak Activity Hours (<?php echo $display_period; ?>)</div>
                                <div class="card-body">
                                    <div class="chart-container" style="height: 300px;">
                                        <canvas id="peakHoursChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header">Match Success Rate (All Time)</div>
                                <div class="card-body text-center">
                                    <h1 class="display-4 fw-bold"><?php echo $match_analytics['success_rate']; ?>%</h1>
                                    <div class="row mt-4">
                                        <div class="col-4">
                                            <h5><?php echo $match_analytics['total_matches']; ?></h5>
                                            <small class="text-muted">Total</small>
                                        </div>
                                        <div class="col-4">
                                            <h5 class="text-success"><?php echo $match_analytics['successful_matches']; ?></h5>
                                            <small class="text-muted">Accepted</small>
                                        </div>
                                        <div class="col-4">
                                            <h5 class="text-danger"><?php echo $match_analytics['rejected_matches']; ?></h5>
                                            <small class="text-muted">Rejected</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header">Session Status (All Time)</div>
                                <div class="card-body">
                                    <div class="chart-container" style="height: 250px;">
                                        <canvas id="sessionStatusChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-7 mb-4">
                             <div class="card shadow h-100">
                                <div class="card-header">Top Cancellation Reasons (All Time)</div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tbody>
                                        <?php foreach ($cancellation_reasons as $reason): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($reason['cancellation_reason']); ?></td>
                                                <td class="text-end"><?php echo $reason['count']; ?></td>
                                                <td class="text-end text-muted"><?php echo $reason['percentage']; ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="demand-supply" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header">Top 15 Popular Subjects (All Time)</div>
                                <div class="card-body">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Demand</th>
                                                <th>Supply</th>
                                                <th>Ratio (D/S)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($popular_subjects as $subject): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                                <td><?php echo $subject['student_demand']; ?></td>
                                                <td><?php echo $subject['mentor_supply']; ?></td>
                                                <td>
                                                    <span class="badge <?php echo $subject['demand_supply_ratio'] > 1 ? 'bg-danger' : 'bg-success'; ?>">
                                                        <?php echo $subject['demand_supply_ratio'] ?? 'N/A'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header">Demand vs. Supply by Level</div>
                                <div class="card-body">
                                    <div class="chart-container" style="height: 300px;">
                                        <canvas id="supplyDemandByLevelChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="tab-pane fade" id="financial-analytics" role="tabpanel">
                     <div class="row">
                        <div class="col-md-12 mb-4">
                            <div class="card shadow">
                                <div class="card-header">Commission Revenue (<?php echo $display_period; ?>)</div>
                                <div class="card-body">
                                     <div class="row text-center">
                                        <div class="col-md-3">
                                            <h4 class="text-success">₱<?php echo number_format($commission_revenue['total_revenue'], 2); ?></h4>
                                            <small class="text-muted">Total Verified Revenue</small>
                                        </div>
                                        <div class="col-md-3">
                                            <h4><?php echo $commission_revenue['verified_payments']; ?></h4>
                                            <small class="text-muted">Verified Payments</small>
                                        </div>
                                        <div class="col-md-3">
                                            <h4><?php echo $commission_revenue['pending_payments']; ?></h4>
                                            <small class="text-muted">Pending Payments</small>
                                        </div>
                                        <div class="col-md-3">
                                            <h4>₱<?php echo number_format($commission_revenue['avg_commission'], 2); ?></h4>
                                            <small class="text-muted">Avg. Commission</small>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="chart-container" style="height: 250px;">
                                        <canvas id="commissionRevenueChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
            
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
    
    <script>
        // User Growth Chart
        const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
        const userGrowthData = <?php echo json_encode($user_growth_trend); ?>;
        
        new Chart(userGrowthCtx, {
            type: 'line',
            data: {
                labels: userGrowthData.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                datasets: [{
                    label: 'New Users',
                    data: userGrowthData.map(d => d.new_users),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });

        // Peak Hours Chart
        const peakHoursCtx = document.getElementById('peakHoursChart').getContext('2d');
        const peakHoursData = <?php echo json_encode($peak_hours); ?>;
        
        new Chart(peakHoursCtx, {
            type: 'bar',
            data: {
                labels: peakHoursData.map(d => `${d.hour}:00`),
                datasets: [{
                    label: 'Platform Activity',
                    data: peakHoursData.map(d => d.activity_count),
                    backgroundColor: '#3b82f6'
                }, {
                    label: 'Unique Users',
                    data: peakHoursData.map(d => d.unique_users),
                    backgroundColor: '#10b981'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: { 
                    x: { stacked: true },
                    y: { stacked: true, beginAtZero: true } 
                }
            }
        });

        // Session Status Chart
        const sessionStatusCtx = document.getElementById('sessionStatusChart').getContext('2d');
        const sessionStatusData = <?php echo json_encode($session_cancellation_stats); ?>;
        
        new Chart(sessionStatusCtx, {
            type: 'pie',
            data: {
                labels: sessionStatusData.map(d => d.status.charAt(0).toUpperCase() + d.status.slice(1)),
                datasets: [{
                    data: sessionStatusData.map(d => d.count),
                    backgroundColor: ['#0dcaf0', '#198754', '#dc3545', '#6c757d']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // Commission Revenue Chart (Dummy data as PHP is not executed here)
        // This will be populated by your PHP variable
        const commissionRevenueCtx = document.getElementById('commissionRevenueChart').getContext('2d');
        new Chart(commissionRevenueCtx, {
            type: 'doughnut',
            data: {
                labels: ['Verified', 'Pending', 'Rejected', 'Overdue'],
                datasets: [{
                    label: 'Commission Status',
                    data: [
                        <?php echo $commission_revenue['verified_payments'] ?? 0; ?>,
                        <?php echo $commission_revenue['pending_payments'] ?? 0; ?>,
                        0, // Assuming you add logic for this
                        0  // Assuming you add logic for this
                    ],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#8b5cf6']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // Supply vs Demand by Level Bar Chart
        const supplyDemandByLevelCtx = document.getElementById('supplyDemandByLevelChart').getContext('2d');
        const supplyDemandByLevelData = <?php echo json_encode($level_summary); ?>;
        
        new Chart(supplyDemandByLevelCtx, {
            type: 'bar',
            data: {
                labels: supplyDemandByLevelData.map(d => d.proficiency_level.charAt(0).toUpperCase() + d.proficiency_level.slice(1)),
                datasets: [{
                    label: 'Student Demand',
                    data: supplyDemandByLevelData.map(d => parseInt(d.student_demand)),
                    backgroundColor: 'rgba(239, 68, 68, 0.8)'
                }, {
                    label: 'Mentor/Peer Supply',
                    data: supplyDemandByLevelData.map(d => parseInt(d.mentor_supply)),
                    backgroundColor: 'rgba(16, 185, 129, 0.8)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: { 
                    y: { beginAtZero: true } 
                }
            }
        });
    </script>
</body>
</html>