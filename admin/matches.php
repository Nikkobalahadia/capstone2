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

// --- NEW: Limit parameter handling ---
$allowed_limits = [10, 25, 50, 100];
$limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], $allowed_limits) ? (int)$_GET['limit'] : 100;

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="matches_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Match ID', 'Student Name', 'Student Email', 'Mentor Name', 'Mentor Email', 'Subject', 'Match Score', 'Status', 'Created Date']);
    
    // Determine sorting for export
    $export_sort = isset($_GET['sort']) && $_GET['sort'] === 'score' ? 'm.match_score DESC, m.created_at DESC' : 'm.created_at DESC';

    // Export query does not use LIMIT to get all data
    $export_query = "
        SELECT m.id, m.subject, m.match_score, m.status, m.created_at,
               s.first_name as student_first_name, s.last_name as student_last_name, s.email as student_email,
               mt.first_name as mentor_first_name, mt.last_name as mentor_last_name, mt.email as mentor_email
        FROM matches m
        JOIN users s ON m.student_id = s.id
        JOIN users mt ON m.mentor_id = mt.id
        ORDER BY {$export_sort}
    ";
    $export_stmt = $db->query($export_query);
    
    while ($row = $export_stmt->fetch()) {
        fputcsv($output, [
            $row['id'],
            $row['student_first_name'] . ' ' . $row['student_last_name'],
            $row['student_email'],
            $row['mentor_first_name'] . ' ' . $row['mentor_last_name'],
            $row['mentor_email'],
            $row['subject'],
            number_format($row['match_score'], 1),
            $row['status'],
            $row['created_at']
        ]);
    }
    
    fclose($output);
    exit;
}

// Handle match actions
if ($_POST['action'] ?? false) {
    $match_id = $_POST['match_id'] ?? 0;
    $action = $_POST['action'];
    
    if ($action === 'approve' && $match_id) {
        $stmt = $db->prepare("UPDATE matches SET status = 'accepted', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$match_id]);
        $success_message = "Match approved successfully.";
    } elseif ($action === 'reject' && $match_id) {
        $stmt = $db->prepare("UPDATE matches SET status = 'rejected', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$match_id]);
        $success_message = "Match rejected successfully.";
    }
}

$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$sort_by = isset($_GET['sort']) && $_GET['sort'] === 'score' ? 'score' : 'date';

// Build query with filters
$where_conditions = ['1=1'];
$params = [];

if ($status_filter) {
    $where_conditions[] = "m.status = ?";
    $params[] = $status_filter;
}

if ($date_from) {
    $where_conditions[] = "DATE(m.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(m.created_at) <= ?";
    $params[] = $date_to;
}

if ($search) {
    $where_conditions[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR mt.first_name LIKE ? OR mt.last_name LIKE ? OR mt.email LIKE ? OR m.subject LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param]);
}

$where_clause = implode(' AND ', $where_conditions);

// Determine the ORDER BY clause based on the new sort parameter
$order_clause = $sort_by === 'score' 
    ? 'm.match_score DESC, m.created_at DESC' // Top Matches by Score
    : 'm.created_at DESC'; // Latest Matches by Date

// Get all matches with user details
$matches_query = "
    SELECT m.*, 
           s.first_name as student_first_name, s.last_name as student_last_name, s.email as student_email,
           mt.first_name as mentor_first_name, mt.last_name as mentor_last_name, mt.email as mentor_email
    FROM matches m
    JOIN users s ON m.student_id = s.id
    JOIN users mt ON m.mentor_id = mt.id
    WHERE $where_clause
    ORDER BY {$order_clause}
    LIMIT {$limit}
";

$stmt = $db->prepare($matches_query);
$stmt->execute($params);
$matches = $stmt->fetchAll();

// Get match statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_matches,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_matches,
        COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted_matches,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_matches,
        AVG(match_score) as avg_match_score
    FROM matches
")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Matches - Study Buddy Admin</title>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">Manage Matches</h1>
                    <p class="text-muted">Monitor and manage student-mentor matches.</p>
                </div>
                <div>
                    <a href="?export=csv<?php echo $status_filter ? '&status='.$status_filter : ''; ?><?php echo $date_from ? '&date_from='.$date_from : ''; ?><?php echo $date_to ? '&date_to='.$date_to : ''; ?><?php echo $search ? '&search='.$search : ''; ?><?php echo $sort_by ? '&sort='.$sort_by : ''; ?>" class="btn btn-success">
                        <i class="fas fa-download me-2"></i> Export to CSV
                    </a>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            title: 'Success!',
                            text: '<?php echo $success_message; ?>',
                            icon: 'success',
                            confirmButtonColor: '#3085d6'
                        });
                    });
                </script>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Matches</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_matches']; ?></div>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending_matches']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Accepted</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['accepted_matches']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Avg Match Score</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['avg_match_score'] ? number_format($stats['avg_match_score'], 1) : '0'; ?>%</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-percentage fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" id="search" name="search" class="form-control" 
                                   placeholder="Name, email, or subject"
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="accepted" <?php echo $status_filter === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="sort" class="form-label">Sort By</label>
                            <select id="sort" name="sort" class="form-select">
                                <option value="date" <?php echo $sort_by === 'date' ? 'selected' : ''; ?>>Latest Matches</option>
                                <option value="score" <?php echo $sort_by === 'score' ? 'selected' : ''; ?>>Top Matches (Score)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="limit" class="form-label">Show Entries</label>
                            <select id="limit" name="limit" class="form-select">
                                <option value="10" <?php echo $limit === 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $limit === 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" id="date_from" name="date_from" class="form-control" 
                                   value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" id="date_to" name="date_to" class="form-control" 
                                   value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>
                        <div class="col-md-1">
                            <a href="matches.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <?php echo $sort_by === 'score' ? 'Top Matches by Score' : 'Latest Matches'; ?> (Displaying <?php echo count($matches); ?> of the Top <?php echo $limit; ?>)
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Student</th>
                                    <th>Mentor</th>
                                    <th>Subject</th>
                                    <th>Match Score</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($matches as $match): ?>
                                    <tr>
                                        <td><?php echo $match['id']; ?></td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($match['student_first_name'] . ' ' . $match['student_last_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($match['student_email']); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($match['mentor_first_name'] . ' ' . $match['mentor_last_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($match['mentor_email']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($match['subject']); ?></td>
                                        <td><?php echo number_format($match['match_score'], 1); ?>%</td>
                                        <td>
                                            <span class="badge <?php echo $match['status'] === 'accepted' ? 'bg-success' : ($match['status'] === 'pending' ? 'bg-warning text-dark' : 'bg-danger'); ?>">
                                                <?php echo ucfirst($match['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($match['created_at'])); ?></td>
                                        <td>
                                            <?php if ($match['status'] === 'pending'): ?>
                                                <form method="POST" id="match-form-<?php echo $match['id']; ?>" class="d-inline">
                                                    <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                                    <input type="hidden" name="action" id="action-input-<?php echo $match['id']; ?>">
                                                    
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" onclick="confirmApprove(<?php echo $match['id']; ?>)" class="btn btn-success">
                                                            <i class="fas fa-check me-1"></i> Approve
                                                        </button>
                                                        <button type="button" onclick="confirmReject(<?php echo $match['id']; ?>)" class="btn btn-danger">
                                                            <i class="fas fa-times me-1"></i> Reject
                                                        </button>
                                                    </div>
                                                </form>
                                                <?php else: ?>
                                                <span class="text-muted small">No actions</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($matches)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-handshake fa-3x mb-3"></i>
                                <p>No matches found matching your criteria.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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

        // --- SweetAlert Confirmation Functions ---
        
        function confirmApprove(matchId) {
            Swal.fire({
                title: 'Approve Match?',
                text: "Are you sure you want to approve this match?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754', // Bootstrap success color
                cancelButtonColor: '#6c757d',  // Bootstrap secondary color
                confirmButtonText: 'Yes, approve it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Set the hidden action input
                    document.getElementById('action-input-' + matchId).value = 'approve';
                    // Submit the form
                    document.getElementById('match-form-' + matchId).submit();
                }
            });
        }
    
        function confirmReject(matchId) {
            Swal.fire({
                title: 'Reject Match?',
                text: "Are you sure you want to reject this match?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545', // Bootstrap danger color
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, reject it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Set the hidden action input
                    document.getElementById('action-input-' + matchId).value = 'reject';
                    // Submit the form
                    document.getElementById('match-form-' + matchId).submit();
                }
            });
        }
    </script>
</body>
</html>