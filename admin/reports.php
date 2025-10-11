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

// Handle report actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $report_id = $_POST['report_id'] ?? 0;
    $action = $_POST['action'];
    
    if ($action === 'resolve' && $report_id) {
        $stmt = $db->prepare("UPDATE user_reports SET status = 'resolved' WHERE id = ?");
        $stmt->execute([$report_id]);
        $success_message = "Report marked as resolved.";
    } elseif ($action === 'review' && $report_id) {
        $stmt = $db->prepare("UPDATE user_reports SET status = 'reviewed' WHERE id = ?");
        $stmt->execute([$report_id]);
        $success_message = "Report marked as reviewed.";
    }
}

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
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
        ORDER BY r.created_at DESC
    ";
    
    $export_stmt = $db->query($export_query);
    
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

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query with filters
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

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get reports
$stmt = $db->prepare("
    SELECT r.*, 
           CONCAT(reporter.first_name, ' ', reporter.last_name) as reporter_name,
           reporter.email as reporter_email,
           CONCAT(reported.first_name, ' ', reported.last_name) as reported_name,
           reported.email as reported_email
    FROM user_reports r
    LEFT JOIN users reporter ON r.reporter_id = reporter.id
    LEFT JOIN users reported ON r.reported_id = reported.id
    $where_clause
    ORDER BY 
        CASE WHEN r.status = 'pending' THEN 1 ELSE 2 END,
        r.created_at DESC
");
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Get statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_reports,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_reports,
        COUNT(CASE WHEN status = 'reviewed' THEN 1 END) as reviewed_reports,
        COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_reports
    FROM user_reports
")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Feedback - StudyConnect Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
    <?php include '../includes/admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Reports & Feedback</h1>
                    <p class="text-muted">Manage user reports and feedback submissions</p>
                </div>
                <a href="?export=csv" class="btn btn-success">
                    <i class="fas fa-download me-2"></i> Export to CSV
                </a>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

             Statistics Cards 
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo $stats['pending_reports']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-exclamation-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Reviewed</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo $stats['reviewed_reports']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-eye fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Resolved</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo $stats['resolved_reports']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Reports</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo $stats['total_reports']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-flag fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

             Filters 
            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="reviewed" <?php echo $status_filter === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Search reports..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

             Reports List 
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">All Reports (<?php echo count($reports); ?>)</h6>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($reports as $report): ?>
                        <div class="p-4 border-bottom">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="badge bg-<?php 
                                            echo $report['status'] === 'pending' ? 'warning' : 
                                                ($report['status'] === 'reviewed' ? 'info' : 'success'); 
                                        ?> me-2">
                                            <?php echo ucfirst($report['status']); ?>
                                        </span>
                                        <span class="badge bg-secondary"><?php echo ucfirst($report['reason']); ?></span>
                                    </div>
                                    <h5 class="mb-2">Report #<?php echo $report['id']; ?></h5>
                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($report['description']); ?></p>
                                    <div class="small text-muted">
                                        <div><strong>Reporter:</strong> <?php echo htmlspecialchars($report['reporter_name']); ?> (<?php echo htmlspecialchars($report['reporter_email']); ?>)</div>
                                        <?php if ($report['reported_name']): ?>
                                            <div><strong>Reported User:</strong> <?php echo htmlspecialchars($report['reported_name']); ?> (<?php echo htmlspecialchars($report['reported_email']); ?>)</div>
                                        <?php endif; ?>
                                        <div><strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?></div>
                                    </div>
                                </div>
                                <div class="ms-3">
                                    <?php if ($report['status'] !== 'resolved'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                            <button type="submit" name="action" value="resolve" class="btn btn-success btn-sm mb-1">
                                                <i class="fas fa-check me-1"></i>Resolve
                                            </button>
                                        </form>
                                        <?php if ($report['status'] === 'pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                <button type="submit" name="action" value="review" class="btn btn-info btn-sm mb-1">
                                                    <i class="fas fa-eye me-1"></i>Review
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($reports)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>No reports found matching your filters.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
