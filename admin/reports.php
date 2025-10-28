<?php
require_once '../config/config.php';
require_once '../config/notification_helper.php';

if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user || $user['role'] !== 'admin') {
    redirect('dashboard.php');
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $report_id = $_POST['report_id'] ?? 0;
    $action = $_POST['action'];
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    $report_stmt = $db->prepare("SELECT * FROM user_reports WHERE id = ?");
    $report_stmt->execute([$report_id]);
    $report = $report_stmt->fetch();
    
    if (!$report) {
        $error_message = "Report not found.";
    } else {
        try {
            if ($action === 'resolve' && $report_id) {
                $stmt = $db->prepare("UPDATE user_reports SET status = 'resolved', admin_notes = ?, resolved_at = NOW(), resolved_by = ? WHERE id = ?");
                $stmt->execute([$admin_notes, $user['id'], $report_id]);
                
                if (function_exists('log_activity')) {
                    log_activity($db, $user['id'], 'report_resolved', ['report_id' => $report_id]);
                }
                
                $success_message = "Report marked as resolved.";
            } elseif ($action === 'review' && $report_id) {
                $stmt = $db->prepare("UPDATE user_reports SET status = 'reviewed', admin_notes = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
                $stmt->execute([$admin_notes, $user['id'], $report_id]);
                
                if (function_exists('log_activity')) {
                    log_activity($db, $user['id'], 'report_reviewed', ['report_id' => $report_id]);
                }
                
                $success_message = "Report marked as reviewed.";
            } elseif ($action === 'dismiss' && $report_id) {
                $stmt = $db->prepare("UPDATE user_reports SET status = 'dismissed', admin_notes = ?, resolved_at = NOW(), resolved_by = ? WHERE id = ?");
                $stmt->execute([$admin_notes, $user['id'], $report_id]);
                
                if (function_exists('log_activity')) {
                    log_activity($db, $user['id'], 'report_dismissed', ['report_id' => $report_id]);
                }
                
                $success_message = "Report dismissed.";
            } elseif ($action === 'warn_user' && $report_id && isset($_POST['reported_user_id'])) {
                $reported_user_id = $_POST['reported_user_id'];
                
                create_notification(
                    $reported_user_id,
                    'account_warning',
                    'Warning from Admin',
                    'You have received a warning from the admin regarding your behavior. Please review our community guidelines. Admin notes: ' . $admin_notes,
                    '/profile/index.php'
                );
                
                create_notification(
                    $report['reporter_id'],
                    'report_resolved',
                    'Your Report Has Been Reviewed',
                    'The user you reported has been warned. Thank you for helping keep our community safe.',
                    '/notifications/index.php'
                );
                
                $stmt = $db->prepare("UPDATE user_reports SET status = 'resolved', admin_notes = ?, action_taken = 'warned', resolved_at = NOW(), resolved_by = ? WHERE id = ?");
                $stmt->execute([$admin_notes, $user['id'], $report_id]);
                
                if (function_exists('log_activity')) {
                    log_activity($db, $user['id'], 'user_warned', ['report_id' => $report_id, 'warned_user_id' => $reported_user_id]);
                }
                
                $success_message = "User has been warned and report resolved.";
            } elseif ($action === 'suspend_user' && $report_id && isset($_POST['reported_user_id'])) {
                $reported_user_id = $_POST['reported_user_id'];
                $suspension_days = $_POST['suspension_days'] ?? 7;
                
                $stmt = $db->prepare("UPDATE users SET is_active = 0, suspension_until = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?");
                $stmt->execute([$suspension_days, $reported_user_id]);
                
                create_notification(
                    $reported_user_id,
                    'account_suspended',
                    'Account Suspended',
                    "Your account has been suspended for $suspension_days days due to violation of community guidelines. Reason: " . $admin_notes,
                    '/profile/index.php'
                );
                
                create_notification(
                    $report['reporter_id'],
                    'report_resolved',
                    'Your Report Has Been Reviewed',
                    "The user you reported has been suspended for $suspension_days days. Thank you for helping keep our community safe.",
                    '/notifications/index.php'
                );
                
                $stmt = $db->prepare("UPDATE user_reports SET status = 'resolved', admin_notes = ?, action_taken = 'suspended', resolved_at = NOW(), resolved_by = ? WHERE id = ?");
                $stmt->execute([$admin_notes, $user['id'], $report_id]);
                
                if (function_exists('log_activity')) {
                    log_activity($db, $user['id'], 'user_suspended', ['report_id' => $report_id, 'suspended_user_id' => $reported_user_id, 'days' => $suspension_days]);
                }
                
                $success_message = "User has been suspended for $suspension_days days and report resolved.";
            } elseif ($action === 'ban_user' && $report_id && isset($_POST['reported_user_id'])) {
                $reported_user_id = $_POST['reported_user_id'];
                
                $stmt = $db->prepare("UPDATE users SET is_active = 0, is_banned = 1 WHERE id = ?");
                $stmt->execute([$reported_user_id]);
                
                create_notification(
                    $reported_user_id,
                    'account_suspended',
                    'Account Permanently Banned',
                    'Your account has been permanently banned due to severe violation of community guidelines. Reason: ' . $admin_notes,
                    '/profile/index.php'
                );
                
                create_notification(
                    $report['reporter_id'],
                    'report_resolved',
                    'Your Report Has Been Reviewed',
                    'The user you reported has been permanently banned. Thank you for helping keep our community safe.',
                    '/notifications/index.php'
                );
                
                $stmt = $db->prepare("UPDATE user_reports SET status = 'resolved', admin_notes = ?, action_taken = 'banned', resolved_at = NOW(), resolved_by = ? WHERE id = ?");
                $stmt->execute([$admin_notes, $user['id'], $report_id]);
                
                if (function_exists('log_activity')) {
                    log_activity($db, $user['id'], 'user_banned', ['report_id' => $report_id, 'banned_user_id' => $reported_user_id]);
                }
                
                $success_message = "User has been permanently banned and report resolved.";
            }
        } catch (PDOException $e) {
            $error_message = "Error processing action: " . $e->getMessage();
        }
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $status_filter = $_GET['status'] ?? 'all';
    $search = $_GET['search'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if ($status_filter !== 'all') {
        $where_conditions[] = "r.status = ?";
        $params[] = $status_filter;
    }
    
    if ($search) {
        $where_conditions[] = "(r.reason LIKE ? OR r.description LIKE ? OR reporter.first_name LIKE ? OR reporter.last_name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($date_from) {
        $where_conditions[] = "DATE(r.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = "DATE(r.created_at) <= ?";
        $params[] = $date_to;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="reports_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Report ID', 'Reporter', 'Reported User', 'Reason', 'Description', 'Status', 'Created At']);
    
    $export_query = "
        SELECT r.id, 
               CONCAT(reporter.first_name, ' ', reporter.last_name) as reporter_name,
               CONCAT(reported.first_name, ' ', reported.last_name) as reported_name,
               r.reason, r.description, r.status, r.created_at
        FROM user_reports r
        LEFT JOIN users reporter ON r.reporter_id = reporter.id
        LEFT JOIN users reported ON r.reported_id = reported.id
        $where_clause
        ORDER BY r.created_at DESC
    ";
    
    $export_stmt = $db->prepare($export_query);
    $export_stmt->execute($params);
    
    while ($row = $export_stmt->fetch()) {
        fputcsv($output, [
            $row['id'],
            $row['reporter_name'],
            $row['reported_name'] ?? 'N/A',
            $row['reason'],
            $row['description'],
            $row['status'],
            $row['created_at']
        ]);
    }
    
    fclose($output);
    exit;
}

$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if (!in_array($per_page, [10, 25, 50, 100])) {
    $per_page = 10;
}

$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "r.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(r.reason LIKE ? OR r.description LIKE ? OR reporter.first_name LIKE ? OR reporter.last_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($date_from) {
    $where_conditions[] = "DATE(r.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(r.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$count_stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM user_reports r
    LEFT JOIN users reporter ON r.reporter_id = reporter.id
    LEFT JOIN users reported ON r.reported_id = reported.id
    $where_clause
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);
$offset = ($page - 1) * $per_page;

// Build query with LIMIT and OFFSET directly in the SQL to avoid PDO binding issues
$query = "
    SELECT r.*, 
           CONCAT(reporter.first_name, ' ', reporter.last_name) as reporter_name,
           reporter.email as reporter_email,
           reporter.role as reporter_role,
           CONCAT(reported.first_name, ' ', reported.last_name) as reported_name,
           reported.email as reported_email,
           reported.role as reported_role,
           reported.is_active as reported_is_active,
           CONCAT(admin.first_name, ' ', admin.last_name) as resolved_by_name,
           (SELECT COUNT(*) FROM user_reports WHERE reported_id = r.reported_id AND status = 'resolved') as reported_user_report_count
    FROM user_reports r
    LEFT JOIN users reporter ON r.reporter_id = reporter.id
    LEFT JOIN users reported ON r.reported_id = reported.id
    LEFT JOIN users admin ON r.resolved_by = admin.id
    $where_clause
    ORDER BY 
        CASE WHEN r.status = 'pending' THEN 1 ELSE 2 END,
        r.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll();

$stats = $db->query("
    SELECT 
        COUNT(*) as total_reports,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_reports,
        COUNT(CASE WHEN status = 'reviewed' THEN 1 END) as reviewed_reports,
        COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_reports,
        COUNT(CASE WHEN status = 'dismissed' THEN 1 END) as dismissed_reports
    FROM user_reports
")->fetch();

$status_chart_data = [
    'Pending' => $stats['pending_reports'],
    'Reviewed' => $stats['reviewed_reports'],
    'Resolved' => $stats['resolved_reports'],
    'Dismissed' => $stats['dismissed_reports'],
];

$reason_stats = $db->query("
    SELECT reason, COUNT(*) as count
    FROM user_reports
    GROUP BY reason
    ORDER BY count DESC
")->fetchAll();

$reason_chart_labels = [];
$reason_chart_data = [];
foreach ($reason_stats as $reason_stat) {
    $reason_chart_labels[] = ucwords(str_replace('_', ' ', $reason_stat['reason']));
    $reason_chart_data[] = $reason_stat['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Feedback - Study Buddy Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f3f4f6;
            overflow-x: hidden;
            font-size: 14px;
        }
        
        .main-content { 
            margin-left: 0; 
            padding: 24px; 
            margin-top: 60px;
            width: 100%;
            max-width: 1600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            background: white;
            margin-bottom: 20px;
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 16px 20px;
            font-weight: 600;
            color: #1f2937;
            border-radius: 12px 12px 0 0 !important;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        #doughnut-chart-wrapper {
            max-width: 400px;
            margin: 0 auto;
            height: 100%;
        }
        
        .page-header {
            background: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        
        .page-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 4px;
        }
        
        .page-header p {
            font-size: 14px;
            color: #6b7280;
        }
        
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        
        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        
        .form-control, .form-select {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn {
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .btn-primary {
            background: #3b82f6;
        }
        
        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: #10b981;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .report-card { 
            transition: all 0.2s ease;
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .report-card:last-child {
            border-bottom: none;
        }
        
        .report-card:hover { 
            background: #f9fafb;
        }
        
        .badge {
            font-size: 12px;
            font-weight: 500;
            padding: 4px 10px;
            border-radius: 6px;
        }
        
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background: white;
            border-top: 1px solid #e5e7eb;
            border-radius: 0 0 12px 12px;
        }
        
        .pagination {
            margin: 0;
        }
        
        .page-link {
            border: 1px solid #e5e7eb;
            color: #374151;
            padding: 6px 12px;
            margin: 0 2px;
            border-radius: 6px;
            font-size: 13px;
        }
        
        .page-link:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }
        
        .page-item.active .page-link {
            background: #3b82f6;
            border-color: #3b82f6;
        }
        
        .page-item.disabled .page-link {
            background: #f9fafb;
            color: #9ca3af;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        
        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 4px;
        }
        
        .stat-card .stat-label {
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
            }
            
            .chart-container {
                height: 250px;
            }
        }
        
        .action-btn { 
            margin: 2px; 
        }
        
        .empty-state {
            padding: 60px 20px;
            text-align: center;
        }
        
        .empty-state i {
            color: #d1d5db;
            margin-bottom: 16px;
        }
        
        .empty-state p {
            color: #6b7280;
            font-size: 15px;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin-header.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary mb-2">
                            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                        </a>
                        <h1>User Reports Management</h1>
                        <p class="mb-0">Review and take action on user reports</p>
                    </div>
                    <a href="?export=csv&<?php echo http_build_query(['status' => $status_filter, 'search' => $search, 'date_from' => $date_from, 'date_to' => $date_to]); ?>" class="btn btn-success">
                        <i class="fas fa-download me-2"></i> Export to CSV
                    </a>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-value text-warning"><?php echo $stats['pending_reports']; ?></div>
                    <div class="stat-label">Pending Reports</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value text-info"><?php echo $stats['reviewed_reports']; ?></div>
                    <div class="stat-label">Reviewed Reports</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value text-success"><?php echo $stats['resolved_reports']; ?></div>
                    <div class="stat-label">Resolved Reports</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value text-secondary"><?php echo $stats['dismissed_reports']; ?></div>
                    <div class="stat-label">Dismissed Reports</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value text-primary"><?php echo $stats['total_reports']; ?></div>
                    <div class="stat-label">Total Reports</div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="m-0">Report Status Breakdown</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <div id="doughnut-chart-wrapper">
                                    <canvas id="reportsStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="m-0">Report Reasons Breakdown</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="reportsReasonChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="filter-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-3 col-lg-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="reviewed" <?php echo $status_filter === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                            <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            <option value="dismissed" <?php echo $status_filter === 'dismissed' ? 'selected' : ''; ?>>Dismissed</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <label class="form-label">Per Page</label>
                        <select name="per_page" class="form-select">
                            <option value="10" <?php echo $per_page === 10 ? 'selected' : ''; ?>>10</option>
                            <option value="25" <?php echo $per_page === 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $per_page === 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $per_page === 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search reports..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-6 col-lg-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="m-0">All Reports (<?php echo $total_records; ?> total, showing <?php echo count($reports); ?>)</h6>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($reports as $report): ?>
                        <div class="report-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="badge bg-<?php 
                                            echo $report['status'] === 'pending' ? 'warning' : 
                                                ($report['status'] === 'reviewed' ? 'info' : 
                                                ($report['status'] === 'dismissed' ? 'secondary' : 'success')); 
                                        ?> me-2">
                                            <?php echo ucfirst($report['status']); ?>
                                        </span>
                                        <span class="badge bg-danger me-2"><?php echo ucfirst(str_replace('_', ' ', $report['reason'])); ?></span>
                                        <?php if ($report['reported_user_report_count'] > 1): ?>
                                            <span class="badge bg-dark" title="This user has been reported <?php echo $report['reported_user_report_count']; ?> times">
                                                <i class="fas fa-exclamation-triangle me-1"></i>Repeat Offender (<?php echo $report['reported_user_report_count']; ?>)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <h5 class="mb-2">Report #<?php echo $report['id']; ?></h5>
                                    <p class="mb-2"><strong>Description:</strong> <?php echo htmlspecialchars($report['description']); ?></p>
                                    
                                    <div class="row mb-2">
                                        <div class="col-md-6">
                                            <div class="small">
                                                <strong><i class="fas fa-user me-1"></i>Reporter:</strong> 
                                                <?php echo htmlspecialchars($report['reporter_name']); ?> 
                                                <span class="badge bg-light text-dark"><?php echo ucfirst($report['reporter_role']); ?></span>
                                                <br>
                                                <span class="text-muted"><?php echo htmlspecialchars($report['reporter_email']); ?></span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="small">
                                                <strong><i class="fas fa-user-times me-1"></i>Reported User:</strong> 
                                                <?php echo htmlspecialchars($report['reported_name']); ?> 
                                                <span class="badge bg-light text-dark"><?php echo ucfirst($report['reported_role']); ?></span>
                                                <?php if (!$report['reported_is_active']): ?>
                                                    <span class="badge bg-danger">Suspended</span>
                                                <?php endif; ?>
                                                <br>
                                                <span class="text-muted"><?php echo htmlspecialchars($report['reported_email']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="small text-muted">
                                        <div><strong><i class="fas fa-clock me-1"></i>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?></div>
                                        <?php if ($report['resolved_by_name']): ?>
                                            <div><strong><i class="fas fa-user-check me-1"></i>Handled by:</strong> <?php echo htmlspecialchars($report['resolved_by_name']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($report['admin_notes']): ?>
                                            <div class="mt-2 p-2 bg-light rounded">
                                                <strong><i class="fas fa-sticky-note me-1"></i>Admin Notes:</strong> 
                                                <?php echo htmlspecialchars($report['admin_notes']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="ms-3 flex-shrink-0">
                                    <?php if ($report['status'] === 'pending' || $report['status'] === 'reviewed'): ?>
                                        <button type="button" class="btn btn-primary btn-sm action-btn mb-1 w-100" data-bs-toggle="modal" data-bs-target="#actionModal<?php echo $report['id']; ?>">
                                            <i class="fas fa-gavel me-1"></i>Take Action
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-info btn-sm action-btn w-100" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $report['id']; ?>">
                                        <i class="fas fa-eye me-1"></i>Details
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="actionModal<?php echo $report['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Take Action on Report #<?php echo $report['id']; ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Admin Notes</label>
                                            <textarea class="form-control" id="adminNotes<?php echo $report['id']; ?>" rows="3" placeholder="Add notes about your decision..."></textarea>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <form id="formReview<?php echo $report['id']; ?>" method="POST" class="d-inline">
                                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                <input type="hidden" name="admin_notes" id="notesReview<?php echo $report['id']; ?>">
                                                <input type="hidden" name="action" value="review">
                                                <button type="button" class="btn btn-info w-100" onclick="confirmAction('formReview<?php echo $report['id']; ?>', 'notesReview<?php echo $report['id']; ?>', 'adminNotes<?php echo $report['id']; ?>', 'Mark as Reviewed', 'Are you sure you want to mark this report as **Reviewed**? No punitive action will be taken.', 'info')">
                                                    <i class="fas fa-eye me-2"></i>Mark as Reviewed (No Action)
                                                </button>
                                            </form>
                                            
                                            <form id="formDismiss<?php echo $report['id']; ?>" method="POST" class="d-inline">
                                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                <input type="hidden" name="admin_notes" id="notesDismiss<?php echo $report['id']; ?>">
                                                <input type="hidden" name="action" value="dismiss">
                                                <button type="button" class="btn btn-secondary w-100" onclick="confirmAction('formDismiss<?php echo $report['id']; ?>', 'notesDismiss<?php echo $report['id']; ?>', 'adminNotes<?php echo $report['id']; ?>', 'Dismiss Report', 'Are you sure you want to **Dismiss** this report? This indicates the report is invalid or requires no action.', 'warning')">
                                                    <i class="fas fa-times me-2"></i>Dismiss Report (Invalid)
                                                </button>
                                            </form>
                                            
                                            <form id="formWarn<?php echo $report['id']; ?>" method="POST" class="d-inline">
                                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                <input type="hidden" name="reported_user_id" value="<?php echo $report['reported_id']; ?>">
                                                <input type="hidden" name="admin_notes" id="notesWarn<?php echo $report['id']; ?>">
                                                <input type="hidden" name="action" value="warn_user">
                                                <button type="button" class="btn btn-warning w-100" onclick="confirmAction('formWarn<?php echo $report['id']; ?>', 'notesWarn<?php echo $report['id']; ?>', 'adminNotes<?php echo $report['id']; ?>', 'Warn User', 'Are you sure you want to **Warn** the reported user? They will receive a notification with the admin notes.', 'warning')">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>Warn User
                                                </button>
                                            </form>
                                            
                                            <form id="formSuspend<?php echo $report['id']; ?>" method="POST" class="d-inline">
                                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                <input type="hidden" name="reported_user_id" value="<?php echo $report['reported_id']; ?>">
                                                <input type="hidden" name="admin_notes" id="notesSuspend<?php echo $report['id']; ?>">
                                                <input type="hidden" name="action" value="suspend_user">
                                                <div class="input-group mb-2">
                                                    <input type="number" name="suspension_days" id="suspensionDays<?php echo $report['id']; ?>" class="form-control" value="7" min="1" max="365" placeholder="Days">
                                                    <button type="button" class="btn btn-danger" onclick="confirmSuspension('formSuspend<?php echo $report['id']; ?>', 'notesSuspend<?php echo $report['id']; ?>', 'adminNotes<?php echo $report['id']; ?>', 'suspensionDays<?php echo $report['id']; ?>', 'Suspend User', 'Are you sure you want to **Suspend** this user? The duration will be taken from the input field.')">
                                                        <i class="fas fa-ban me-2"></i>Suspend User
                                                    </button>
                                                </div>
                                            </form>
                                            
                                            <form id="formBan<?php echo $report['id']; ?>" method="POST" class="d-inline">
                                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                <input type="hidden" name="reported_user_id" value="<?php echo $report['reported_id']; ?>">
                                                <input type="hidden" name="admin_notes" id="notesBan<?php echo $report['id']; ?>">
                                                <input type="hidden" name="action" value="ban_user">
                                                <button type="button" class="btn btn-dark w-100" onclick="confirmAction('formBan<?php echo $report['id']; ?>', 'notesBan<?php echo $report['id']; ?>', 'adminNotes<?php echo $report['id']; ?>', 'PERMANENT BAN', 'This action **PERMANENTLY BANS** the user. This cannot be undone. Are you absolutely sure?', 'error')">
                                                    <i class="fas fa-user-slash me-2"></i>Ban User Permanently
                                                </button>
                                            </form>
                                            
                                            <form id="formResolve<?php echo $report['id']; ?>" method="POST" class="d-inline">
                                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                <input type="hidden" name="admin_notes" id="notesResolve<?php echo $report['id']; ?>">
                                                <input type="hidden" name="action" value="resolve">
                                                <button type="button" class="btn btn-success w-100" onclick="confirmAction('formResolve<?php echo $report['id']; ?>', 'notesResolve<?php echo $report['id']; ?>', 'adminNotes<?php echo $report['id']; ?>', 'Mark as Resolved', 'Are you sure you want to mark this report as **Resolved**? Use this if action was taken outside of these quick buttons.', 'success')">
                                                    <i class="fas fa-check me-2"></i>Resolve (Action Taken Elsewhere)
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="detailsModal<?php echo $report['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Report Details #<?php echo $report['id']; ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <h6>Reporter Information</h6>
                                                <p><strong>Name:</strong> <?php echo htmlspecialchars($report['reporter_name']); ?></p>
                                                <p><strong>Email:</strong> <?php echo htmlspecialchars($report['reporter_email']); ?></p>
                                                <p><strong>Role:</strong> <?php echo ucfirst($report['reporter_role']); ?></p>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <h6>Reported User Information</h6>
                                                <p><strong>Name:</strong> <?php echo htmlspecialchars($report['reported_name']); ?></p>
                                                <p><strong>Email:</strong> <?php echo htmlspecialchars($report['reported_email']); ?></p>
                                                <p><strong>Role:</strong> <?php echo ucfirst($report['reported_role']); ?></p>
                                                <p><strong>Status:</strong> <?php echo $report['reported_is_active'] ? 'Active' : 'Suspended'; ?></p>
                                                <p><strong>Total Reports:</strong> <?php echo $report['reported_user_report_count']; ?></p>
                                            </div>
                                        </div>
                                        <hr>
                                        <h6>Report Details</h6>
                                        <p><strong>Reason:</strong> <?php echo ucfirst(str_replace('_', ' ', $report['reason'])); ?></p>
                                        <p><strong>Description:</strong> <?php echo htmlspecialchars($report['description']); ?></p>
                                        <p><strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?></p>
                                        <?php if ($report['admin_notes']): ?>
                                            <hr>
                                            <h6>Admin Notes</h6>
                                            <p><?php echo htmlspecialchars($report['admin_notes']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($reports)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox fa-3x"></i>
                            <p>No reports found matching your filters.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper">
                    <div class="text-muted small">
                        Showing <?php echo (($page - 1) * $per_page) + 1; ?> to <?php echo min($page * $per_page, $total_records); ?> of <?php echo $total_records; ?> reports
                    </div>
                    <nav>
                        <ul class="pagination mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $per_page; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1&per_page=' . $per_page . '&status=' . $status_filter . '&search=' . urlencode($search) . '&date_from=' . $date_from . '&date_to=' . $date_to . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                $active = $i === $page ? 'active' : '';
                                echo '<li class="page-item ' . $active . '"><a class="page-link" href="?page=' . $i . '&per_page=' . $per_page . '&status=' . $status_filter . '&search=' . urlencode($search) . '&date_from=' . $date_from . '&date_to=' . $date_to . '">' . $i . '</a></li>';
                            }
                            
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&per_page=' . $per_page . '&status=' . $status_filter . '&search=' . urlencode($search) . '&date_from=' . $date_from . '&date_to=' . $date_to . '">' . $total_pages . '</a></li>';
                            }
                            ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $per_page; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- SweetAlert Functions ---

        /**
         * Generic SweetAlert confirmation for actions.
         * @param {string} formId The ID of the form to submit.
         * @param {string} notesInputId The ID of the hidden input in the form to populate with admin notes.
         * @param {string} adminNotesTextareaId The ID of the textarea where admin notes are written.
         * @param {string} title The title of the Swal modal.
         * @param {string} text The body text of the Swal modal.
         * @param {string} icon The icon type ('success', 'warning', 'error', 'info', 'question').
         */
        function confirmAction(formId, notesInputId, adminNotesTextareaId, title, text, icon) {
            const adminNotes = document.getElementById(adminNotesTextareaId).value;
            
            Swal.fire({
                title: title,
                html: text + (adminNotes.trim() ? '<br><br>Current Admin Notes will be included.' : ''),
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Proceed!',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Populate the hidden admin_notes field and submit the form
                    document.getElementById(notesInputId).value = adminNotes;
                    document.getElementById(formId).submit();
                }
            });
        }
        
        /**
         * SweetAlert confirmation specifically for user suspension.
         * @param {string} formId The ID of the form to submit.
         * @param {string} notesInputId The ID of the hidden input in the form to populate with admin notes.
         * @param {string} adminNotesTextareaId The ID of the textarea where admin notes are written.
         * @param {string} suspensionDaysInputId The ID of the input field for suspension days.
         * @param {string} title The title of the Swal modal.
         * @param {string} text The body text of the Swal modal.
         */
        function confirmSuspension(formId, notesInputId, adminNotesTextareaId, suspensionDaysInputId, title, text) {
            const adminNotes = document.getElementById(adminNotesTextareaId).value;
            const suspensionDays = document.getElementById(suspensionDaysInputId).value;

            Swal.fire({
                title: title,
                html: text + `<br><br>Suspending for **${suspensionDays} days**. Current Admin Notes will be included.`,
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc3545', // Danger color
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Suspend!',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Populate the hidden admin_notes field and submit the form
                    document.getElementById(notesInputId).value = adminNotes;
                    document.getElementById(formId).submit();
                }
            });
        }

        // --- Chart Generation ---

        const statusData = <?php echo json_encode($status_chart_data); ?>;
        const reasonLabels = <?php echo json_encode($reason_chart_labels); ?>;
        const reasonData = <?php echo json_encode($reason_chart_data); ?>;
        const totalReports = <?php echo $stats['total_reports']; ?>;
        
        const reportsStatusCtx = document.getElementById('reportsStatusChart').getContext('2d');
        
        new Chart(reportsStatusCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(statusData),
                datasets: [{
                    data: Object.values(statusData),
                    backgroundColor: [
                        '#f59e0b',
                        '#06b6d4',
                        '#10b981',
                        '#6c757d'
                    ],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    title: {
                        display: true,
                        text: `Total Reports: ${totalReports}`,
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        padding: {
                            top: 10,
                            bottom: 10
                        }
                    }
                }
            }
        });
        
        const reportsReasonCtx = document.getElementById('reportsReasonChart').getContext('2d');
        
        new Chart(reportsReasonCtx, {
            type: 'bar',
            data: {
                labels: reasonLabels,
                datasets: [{
                    label: 'Number of Reports',
                    data: reasonData,
                    backgroundColor: [
                        '#ef4444', 
                        '#f59e0b',
                        '#8b5cf6',
                        '#06b6d4',
                        '#10b981',
                        '#2563eb'
                    ],
                    borderColor: [
                        '#b91c1c',
                        '#b45309',
                        '#6d28d9',
                        '#0891b2',
                        '#059669',
                        '#1d4ed8'
                    ],
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
                            precision: 0
                        },
                        title: {
                            display: true,
                            text: 'Count'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Reports by Reason'
                    }
                }
            }
        });
    </script>
</body>
</html>