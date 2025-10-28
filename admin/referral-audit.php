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

$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$verified_filter = isset($_GET['verified']) ? $_GET['verified'] : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// --- START: New Date Range Handling ---
// Set default dates: Last 365 days
$default_end_date = date('Y-m-d');
$default_start_date = date('Y-m-d', strtotime('-365 days'));

// Get date range from GET or use defaults
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : $default_start_date;
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : $default_end_date;

// Ensure end_date is at least the start_date
if ($end_date < $start_date) {
    $end_date = $start_date;
}

// Prepare date range for SQL filtering (inclusive, so end date is end of day)
$sql_start_datetime = $start_date . ' 00:00:00';
$sql_end_datetime = $end_date . ' 23:59:59';
// --- END: New Date Range Handling ---


// --- START: Sorting Logic ---
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at'; // Default sort
$sort_direction = isset($_GET['sort_direction']) && in_array(strtoupper($_GET['sort_direction']), ['ASC', 'DESC']) ? strtoupper($_GET['sort_direction']) : 'DESC';

// Define allowed sort columns to prevent SQL injection
$allowed_sort_columns = [
    'created_at' => 'rc.created_at',
    'code' => 'rc.code',
    'uses' => 'rc.current_uses',
    'actual_referrals' => 'actual_referrals', // Actual referrals comes from subquery alias
    'max_uses' => 'rc.max_uses',
];

// Determine the actual column name for the SQL query
$sql_sort_column = isset($allowed_sort_columns[$sort_by]) ? $allowed_sort_columns[$sort_by] : 'rc.created_at';
// --- END: Sorting Logic ---


// --- START: Filtering Logic ---
$where_conditions = ['1=1'];
$params = [];

// Apply date range filter (to codes' creation date)
$where_conditions[] = "rc.created_at >= ?";
$params[] = $sql_start_datetime;

$where_conditions[] = "rc.created_at <= ?";
$params[] = $sql_end_datetime;

if ($status_filter === 'active') {
    $where_conditions[] = "rc.is_active = 1 AND (rc.expires_at IS NULL OR rc.expires_at > NOW()) AND rc.current_uses < rc.max_uses";
} elseif ($status_filter === 'expired') {
    $where_conditions[] = "rc.expires_at < NOW()";
} elseif ($status_filter === 'maxed') {
    $where_conditions[] = "rc.current_uses >= rc.max_uses";
} elseif ($status_filter === 'inactive') {
    $where_conditions[] = "rc.is_active = 0";
}

if ($verified_filter === 'verified') {
    $where_conditions[] = "u.is_verified = 1";
} elseif ($verified_filter === 'unverified') {
    $where_conditions[] = "u.is_verified = 0";
}

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR rc.code LIKE ?)";
    $search_param = "%$search%";
    // Append search params to $params
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_clause = implode(' AND ', $where_conditions);
// --- END: Filtering Logic ---


// --- START: Pagination Logic (UPDATED) ---
$allowed_limits = [10, 25, 50, 100];
$default_limit = 25;
// Validate and set the limit from GET or use the default
$limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], $allowed_limits) ? (int)$_GET['limit'] : $default_limit; 

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total count (for pagination)
$count_query = "
    SELECT COUNT(rc.id) 
    FROM referral_codes rc
    JOIN users u ON rc.created_by = u.id
    WHERE $where_clause
";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_codes_filtered = $count_stmt->fetchColumn();
$total_pages = ceil($total_codes_filtered / $limit);
// --- END: Pagination Logic ---


if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="referral_codes_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Code', 'Creator Name', 'Creator Email', 'Verified', 'Current Uses', 'Max Uses', 'Actual Referrals', 'Expires At', 'Status', 'Created Date']);
    
    // The export query uses the filtering clause with date range
    $export_query = "
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
        WHERE $where_clause
        ORDER BY $sql_sort_column $sort_direction
    ";
    
    $export_stmt = $db->prepare($export_query);
    $export_stmt->execute($params);
    
    while ($row = $export_stmt->fetch()) {
        $is_expired = $row['expires_at'] && strtotime($row['expires_at']) < time();
        $is_maxed = $row['current_uses'] >= $row['max_uses'];
        
        if (!$row['is_active']) {
            $status = 'Inactive';
        } elseif ($is_expired) {
            $status = 'Expired';
        } elseif ($is_maxed) {
            $status = 'Max Uses';
        } else {
            $status = 'Active';
        }
        
        fputcsv($output, [
            $row['code'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['email'],
            $row['is_verified'] ? 'Yes' : 'No',
            $row['current_uses'],
            $row['max_uses'],
            $row['actual_referrals'],
            $row['expires_at'] ? $row['expires_at'] : 'Never',
            $status,
            $row['created_at']
        ]);
    }
    
    fclose($output);
    exit;
}


// Get referral code statistics (total, NON-FILTERED stats for context)
$stats = $db->query("
    SELECT 
        COUNT(*) as total_codes,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_codes,
        COUNT(CASE WHEN expires_at < NOW() THEN 1 END) as expired_codes,
        SUM(current_uses) as total_uses,
        AVG(current_uses) as avg_uses_per_code
    FROM referral_codes
")->fetch();

// Get all referral codes with creator info (with LIMIT/OFFSET AND DATE FILTER)
$codes_query = "
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
    WHERE $where_clause
    ORDER BY $sql_sort_column $sort_direction
    LIMIT $limit OFFSET $offset
";

$stmt = $db->prepare($codes_query);
$stmt->execute($params);
$referral_codes = $stmt->fetchAll();

// Get referral usage trends (last 30 days) - This chart remains fixed on the last 30 days
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

// Helper to preserve current GET parameters for pagination links
function get_pagination_query_string($new_page) {
    $params = $_GET;
    $params['page'] = $new_page;
    unset($params['export']);
    return http_build_query($params);
}

// Helper to determine the opposite direction for a column
function get_sort_direction_for_column($current_col, $sort_by, $sort_direction) {
    if ($current_col === $sort_by) {
        return $sort_direction === 'ASC' ? 'DESC' : 'ASC';
    }
    // Default sort direction for a new column (e.g., uses) can be DESC
    return 'DESC'; 
}

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
        /* NEW STYLES FROM MATCHES.PHP */
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
        /* END NEW STYLES */
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

    <div class="mobile-overlay" id="mobileOverlay"></div>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">Referral Code Audit</h1>
                    <p class="text-muted">Monitor referral code usage and mentor activity.</p>
                </div>
                <div>
                    <a href="?export=csv&start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?><?php echo $status_filter ? '&status='.$status_filter : ''; ?><?php echo $verified_filter ? '&verified='.$verified_filter : ''; ?><?php echo $search ? '&search='.$search : ''; ?><?php echo $sort_by ? '&sort_by='.$sort_by : ''; ?><?php echo $sort_direction ? '&sort_direction='.$sort_direction : ''; ?>" class="btn btn-success">
                        <i class="fas fa-download me-2"></i> Export to CSV
                    </a>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Codes (System-wide)</div>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Codes (System-wide)</div>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Uses (System-wide)</div>
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
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Avg Uses/Code (System-wide)</div>
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

            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-lg-2 col-md-4">
                            <label for="start_date" class="form-label">Created From</label>
                            <input type="date" id="start_date" name="start_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($start_date); ?>" required>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label for="end_date" class="form-label">Created To</label>
                            <input type="date" id="end_date" name="end_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($end_date); ?>" required>
                        </div>
                        
                        <div class="col-lg-2 col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" id="search" name="search" class="form-control" 
                                   placeholder="Name, email, or code"
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="col-lg-2 col-md-4">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                <option value="maxed" <?php echo $status_filter === 'maxed' ? 'selected' : ''; ?>>Max Uses</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="col-lg-1 col-md-4">
                            <label for="verified" class="form-label">Creator</label>
                            <select id="verified" name="verified" class="form-select">
                                <option value="">All</option>
                                <option value="verified" <?php echo $verified_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                <option value="unverified" <?php echo $verified_filter === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
                            </select>
                        </div>
                        
                        <div class="col-lg-1 col-md-4">
                            <label for="limit" class="form-label">Show</label>
                            <select id="limit" name="limit" class="form-select">
                                <?php 
                                    // Use the $allowed_limits array defined in the PHP block
                                    foreach ($allowed_limits as $allowed_limit): 
                                ?>
                                    <option value="<?php echo $allowed_limit; ?>" <?php echo $limit === $allowed_limit ? 'selected' : ''; ?>>
                                        <?php echo $allowed_limit; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <button type="submit" class="btn btn-primary w-100 mb-1">Filter</button>
                            <a href="referral-audit.php" class="btn btn-secondary w-100">Clear</a>
                        </div>
                    </form>
                </div>
            </div>
            <div class="alert alert-info small" role="alert">
                <i class="fas fa-info-circle me-1"></i> Showing codes created from **<?php echo date('M j, Y', strtotime($start_date)); ?>** to **<?php echo date('M j, Y', strtotime($end_date)); ?>**.
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                All Referral Codes (Showing <?php echo count($referral_codes); ?> of <?php echo number_format($total_codes_filtered); ?>)
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <?php 
                                            // Helper to construct sort link
                                            function get_sort_link($column, $display_name, $current_sort_by, $current_sort_direction) {
                                                $new_direction = get_sort_direction_for_column($column, $current_sort_by, $current_sort_direction);
                                                $icon = $current_sort_by === $column ? 
                                                        ($current_sort_direction === 'ASC' ? '<i class="fas fa-sort-up ms-1"></i>' : '<i class="fas fa-sort-down ms-1"></i>') : 
                                                        '<i class="fas fa-sort ms-1 text-muted"></i>';
                                                $params = array_merge($_GET, ['sort_by' => $column, 'sort_direction' => $new_direction, 'page' => 1]);
                                                unset($params['export']);
                                                $query_string = http_build_query($params);
                                                
                                                echo '<th><a href="?' . $query_string . '" class="text-decoration-none d-block text-primary fw-bold">' . $display_name . $icon . '</a></th>';
                                            }
                                            ?>
                                            <?php get_sort_link('code', 'Code', $sort_by, $sort_direction); ?>
                                            <th>Creator</th>
                                            <?php get_sort_link('actual_referrals', 'Usage (Actual/Total)', $sort_by, $sort_direction); ?>
                                            <th>Expires</th>
                                            <th>Status</th>
                                            <?php get_sort_link('created_at', 'Created', $sort_by, $sort_direction); ?>
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
                                                    <div>
                                                        <strong><?php echo $code['actual_referrals']; ?></strong> / <?php echo $code['max_uses']; ?>
                                                    </div>
                                                    <div class="progress" style="height: 4px;">
                                                        <div class="progress-bar bg-success" style="width: <?php echo ($code['actual_referrals'] / $code['max_uses']) * 100; ?>%;" 
                                                             role="progressbar" aria-valuenow="<?php echo ($code['actual_referrals'] / $code['max_uses']) * 100; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                    <small class="text-muted">Total uses: <?php echo $code['current_uses']; ?></small>
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
                                
                                <?php if (empty($referral_codes)): ?>
                                    <div class="text-center py-5 text-muted">
                                        <i class="fas fa-ticket-alt fa-3x mb-3"></i>
                                        <p>No referral codes found matching your criteria.</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($total_pages > 1): ?>
                                <nav>
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?<?php echo get_pagination_query_string($page - 1); ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <?php if ($i == $page): ?>
                                                <li class="page-item active"><a class="page-link" href="?<?php echo get_pagination_query_string($i); ?>"><?php echo $i; ?></a></li>
                                            <?php elseif ($i <= 3 || $i > $total_pages - 3 || ($i >= $page - 1 && $i <= $page + 1)): ?>
                                                <li class="page-item"><a class="page-link" href="?<?php echo get_pagination_query_string($i); ?>"><?php echo $i; ?></a></li>
                                            <?php elseif ($i == 4 && $page > 4): ?>
                                                <li class="page-item disabled"><a class="page-link">...</a></li>
                                            <?php elseif ($i == $total_pages - 3 && $page < $total_pages - 3): ?>
                                                <li class="page-item disabled"><a class="page-link">...</a></li>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?<?php echo get_pagination_query_string($page + 1); ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    </ul>
                                 </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
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
                                            <strong><?php echo $referrer['actual_referrals']; ?> referrals</strong>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

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