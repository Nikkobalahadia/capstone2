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

// --- Date Range Handling ---
// Set default dates: Last 30 days
$default_end_date = date('Y-m-d');
$default_start_date = date('Y-m-d', strtotime('-30 days'));

// Get date range from POST or use defaults
$start_date = isset($_POST['start_date']) && !empty($_POST['start_date']) ? $_POST['start_date'] : $default_start_date;
$end_date = isset($_POST['end_date']) && !empty($_POST['end_date']) ? $_POST['end_date'] : $default_end_date;

// Ensure end_date is at least the start_date
if ($end_date < $start_date) {
    $end_date = $start_date;
}

// Prepare date range for SQL filtering (inclusive, so end date is end of day)
$sql_start_datetime = $start_date . ' 00:00:00';
$sql_end_datetime = $end_date . ' 23:59:59';
// --- End Date Range Handling ---

$analytics = [];

// --- SQL Queries Updated with Date Range where applicable ---

// User statistics (Total counts remain system-wide, new users use date range)
$user_stats = $db->query("
    SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN role = 'student' THEN 1 END) as students,
        COUNT(CASE WHEN role = 'mentor' THEN 1 END) as mentors,
        COUNT(CASE WHEN is_verified = 1 THEN 1 END) as verified_users,
        COUNT(CASE WHEN created_at >= '{$sql_start_datetime}' AND created_at <= '{$sql_end_datetime}' THEN 1 END) as new_users_range,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_users_7d
    FROM users WHERE role != 'admin'
")->fetch();

// Match statistics (Using date range for total, active, pending, rejected)
$match_stats = $db->query("
    SELECT 
        COUNT(*) as total_matches,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_matches,
        COUNT(CASE WHEN status = 'accepted' THEN 1 END) as active_matches,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_matches,
        AVG(match_score) as avg_match_score
    FROM matches
    WHERE created_at >= '{$sql_start_datetime}' AND created_at <= '{$sql_end_datetime}'
")->fetch();

// Session statistics (Using date range for total, scheduled, completed, cancelled)
$session_stats = $db->query("
    SELECT 
        COUNT(*) as total_sessions,
        COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled_sessions,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_sessions,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_sessions,
        COUNT(CASE WHEN session_date >= CURDATE() THEN 1 END) as upcoming_sessions_system_wide
    FROM sessions
    WHERE created_at >= '{$sql_start_datetime}' AND created_at <= '{$sql_end_datetime}'
")->fetch();

// Rating statistics (Filtering ratings made within the date range)
$rating_stats = $db->query("
    SELECT 
        COUNT(*) as total_ratings,
        AVG(rating) as avg_rating,
        COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive_ratings
    FROM session_ratings
    WHERE created_at >= '{$sql_start_datetime}' AND created_at <= '{$sql_end_datetime}'
")->fetch();

// Activity statistics (Uses the date range)
$activity_stats = $db->query("
    SELECT 
        COUNT(CASE WHEN action = 'login' THEN 1 END) as logins,
        COUNT(CASE WHEN action = 'match_request' THEN 1 END) as match_requests,
        COUNT(CASE WHEN action = 'message_sent' THEN 1 END) as messages_sent,
        COUNT(CASE WHEN action = 'session_scheduled' THEN 1 END) as sessions_scheduled
    FROM user_activity_logs 
    WHERE created_at >= '{$sql_start_datetime}' AND created_at <= '{$sql_end_datetime}'
")->fetch();

// Most popular subjects (Uses the date range by joining with users, assuming user_subjects created_at relates to users)
// A more accurate query would be:
$popular_subjects = $db->query("
    SELECT us.subject_name, COUNT(*) as count
    FROM user_subjects us
    JOIN users u ON us.user_id = u.id
    WHERE u.created_at >= '{$sql_start_datetime}' AND u.created_at <= '{$sql_end_datetime}'
    GROUP BY us.subject_name 
    ORDER BY count DESC 
    LIMIT 10
")->fetchAll();


// Recent activity (System-wide or last X records, keep it recent/system-wide for utility)
$recent_activity = $db->query("
    SELECT ual.*, u.first_name, u.last_name, u.role
    FROM user_activity_logs ual
    JOIN users u ON ual.user_id = u.id
    ORDER BY ual.created_at DESC
    LIMIT 20
")->fetchAll();

// Daily active users (Uses the date range)
$daily_active = $db->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(DISTINCT user_id) as active_users
    FROM user_activity_logs 
    WHERE created_at >= '{$sql_start_datetime}' AND created_at <= '{$sql_end_datetime}'
    GROUP BY DATE(created_at)
    ORDER BY date DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Study Buddy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            
            /* Mobile toggle button */
            .mobile-menu-toggle {
                display: block !important;
                position: fixed;
                bottom: 22px;
                right: 22px;
                z-index: 1001; /* Higher than overlay */
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
        
        /* Date Filter Styles */
        .date-filter-form {
            background-color: #fff;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }
        .date-filter-form label {
            font-weight: 500;
            color: #4a5568;
            margin-right: 0.5rem;
        }
        .date-filter-form input[type="date"] {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 0.375rem 0.75rem;
            font-size: 0.9rem;
            max-width: 150px;
        }
        .date-filter-form button {
            border-radius: 6px;
            font-size: 0.9rem;
            padding: 0.4rem 1rem;
        }

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
            
            <div class="welcome-banner shadow-sm">
                <div class="welcome-banner-text">
                    <h2 class="h3">Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>! 👋</h2>
                    <p>Here's what's happening with your Study Buddy Portal today.</p>
                </div>
                <div class="welcome-banner-time">
                    <div class="time-box">
                        <i class="fas fa-calendar-alt"></i> <?php echo date('l, F j, Y'); ?>
                    </div>
                    <div class="time-box">
                        <i class="fas fa-clock"></i> <span id="current-time"><?php echo date('h:i A'); ?></span>
                    </div>
                </div>
            </div>

            <div class="date-filter-form mb-4">
                <form method="POST" class="d-flex align-items-center justify-content-between flex-wrap">
                    <h6 class="m-0 font-weight-bold text-primary mb-2 mb-md-0">Data Period:</h6>
                    <div class="d-flex align-items-center flex-wrap">
                        <label for="start_date">From:</label>
                        <input type="date" id="start_date" name="start_date" class="form-control me-3 mb-2 mb-sm-0" value="<?php echo htmlspecialchars($start_date); ?>">
                        
                        <label for="end_date">To:</label>
                        <input type="date" id="end_date" name="end_date" class="form-control me-3 mb-2 mb-sm-0" value="<?php echo htmlspecialchars($end_date); ?>">
                        
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i> Apply Filter</button>
                    </div>
                </form>
                <small class="d-block mt-2 text-muted">Showing data from **<?php echo date('M j, Y', strtotime($start_date)); ?>** to **<?php echo date('M j, Y', strtotime($end_date)); ?>**</small>
            </div>
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Users (System-wide)</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($user_stats['total_users']); ?></div>
                                    <div class="text-success small">+<?php echo $user_stats['new_users_range']; ?> new in range</div>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Matches (In Range)</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($match_stats['active_matches']); ?></div>
                                    <div class="text-muted small"><?php echo $match_stats['pending_matches']; ?> pending requests</div>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Completed Sessions (In Range)</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($session_stats['completed_sessions']); ?></div>
                                    <div class="text-muted small"><?php echo $session_stats['scheduled_sessions']; ?> scheduled in range</div>
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
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Avg Rating (In Range)</div>
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
                <div class="col-lg-8 mb-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Daily Active Users (In Date Range)</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="dailyActiveChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Most Popular Subjects (By New User Interest In Range)</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="subjectsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Platform Activity (In Date Range)</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <div class="mb-3">
                                        <div class="font-weight-bold small">User Logins</div>
                                        <div class="h5 text-primary"><?php echo number_format($activity_stats['logins']); ?></div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="font-weight-bold small">Match Requests</div>
                                        <div class="h5 text-success"><?php echo number_format($activity_stats['match_requests']); ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-3">
                                        <div class="font-weight-bold small">Messages Sent</div>
                                        <div class="h5 text-warning"><?php echo number_format($activity_stats['messages_sent']); ?></div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="font-weight-bold small">Sessions Scheduled</div>
                                        <div class="h5 text-info"><?php echo number_format($activity_stats['sessions_scheduled']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">User Breakdown (System-wide)</h6>
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
                                    <span class="small">Verified Mentors</span>
                                    <span class="font-weight-bold small"><?php echo $user_stats['verified_users']; ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo $user_stats['total_users'] > 0 ? ($user_stats['verified_users'] / $user_stats['total_users']) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Activity (Last 20 Events)</h6>
                            <a href="activity-logs.php" class="text-xs font-weight-bold text-info text-uppercase text-decoration-none">
                                View All <i class="fas fa-arrow-right fa-xs"></i>
                            </a>
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
                            <?php if (empty($recent_activity)): ?>
                                <div class="text-muted text-center py-3">No recent activity recorded.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-12">
                    <h4 class="mb-3">Quick Actions</h4>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <a href="users.php" class="quick-action-card shadow-sm">
                        <div class="icon-circle bg-primary-light">
                            <i class="fas fa-users"></i>
                        </div>
                        <h5>Manage Users</h5>
                        <p>View, edit, and search all users.</p>
                    </a>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <a href="verifications.php" class="quick-action-card shadow-sm">
                        <div class="icon-circle bg-success-light">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <h5>Mentor Verifications</h5>
                        <p>Approve or deny new mentor applications.</p>
                    </a>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <a href="announcements.php" class="quick-action-card shadow-sm">
                        <div class="icon-circle bg-info-light">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <h5>Post Announcement</h5>
                        <p>Create a new site-wide notification.</p>
                    </a>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <a href="settings.php" class="quick-action-card shadow-sm">
                        <div class="icon-circle bg-warning-light">
                            <i class="fas fa-cog"></i>
                        </div>
                        <h5>System Settings</h5>
                        <p>Configure platform commission and features.</p>
                    </a>
                </div>
            </div>
            </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Live Clock
        function updateTime() {
            const timeEl = document.getElementById('current-time');
            if (timeEl) {
                const now = new Date();
                timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
            }
        }
        setInterval(updateTime, 1000 * 30); // Update every 30 seconds
        updateTime(); // Initial call
        
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