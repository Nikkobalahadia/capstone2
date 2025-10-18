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
                
                // Log activity
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
                
                // Notify the reporter that action was taken
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
                
                // Notify the reporter that action was taken
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
                
                // Notify the reporter that action was taken
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

$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

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

$stmt = $db->prepare("
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
");
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
        .report-card { transition: all 0.3s ease; }
        .report-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .action-btn { margin: 2px; }
    </style>
</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">User Reports Management</h1>
                    <p class="text-muted">Review and take action on user reports</p>
                </div>
                <a href="?export=csv" class="btn btn-success">
                    <i class="fas fa-download me-2"></i> Export to CSV
                </a>
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
                <div class="col-xl-2 col-md-6 mb-3">
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
                <div class="col-xl-2 col-md-6 mb-3">
                    <div class="card border-left-secondary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Dismissed</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo $stats['dismissed_reports']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-6 mb-3">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total</div>
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
                                <option value="dismissed" <?php echo $status_filter === 'dismissed' ? 'selected' : ''; ?>>Dismissed</option>
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

            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">All Reports (<?php echo count($reports); ?>)</h6>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($reports as $report): ?>
                        <div class="p-4 border-bottom report-card">
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
                                <div class="ms-3">
                                    <?php if ($report['status'] === 'pending' || $report['status'] === 'reviewed'): ?>
                                        <button type="button" class="btn btn-primary btn-sm action-btn" data-bs-toggle="modal" data-bs-target="#actionModal<?php echo $report['id']; ?>">
                                            <i class="fas fa-gavel me-1"></i>Take Action
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-info btn-sm action-btn" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $report['id']; ?>">
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
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                <input type="hidden" name="admin_notes" id="notesReview<?php echo $report['id']; ?>">
                                                <button type="submit" name="action" value="review" class="btn btn-info w-100" onclick="document.getElementById('notesReview<?php echo $report['id']; ?>').value = document.getElementById('adminNotes<?php echo $report['id']; ?>').value">
                                                    <i class="fas fa-eye me-2"></i>Mark as Reviewed (No Action)
                                                </button>
                                            </form>
                                            
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                <input type="hidden" name="admin_notes" id="notesDismiss<?php echo $report['id']; ?>">
                                                <button type="submit" name="action" value="dismiss" class="btn btn-secondary w-100" onclick="document.getElementById('notesDismiss<?php echo $report['id']; ?>').value = document.getElementById('adminNotes<?php echo $report['id']; ?>').value">
                                                    <i class="fas fa-times me-2"></i>Dismiss Report (Invalid)
                                                </button>
                                            </form>
                                            
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                <input type="hidden" name="reported_user_id" value="<?php echo $report['reported_id']; ?>">
                                                <input type="hidden" name="admin_notes" id="notesWarn<?php echo $report['id']; ?>">
                                                <button type="submit" name="action" value="warn_user" class="btn btn-warning w-100" onclick="return confirm('Send warning to this user?') && (document.getElementById('notesWarn<?php echo $report['id']; ?>').value = document.getElementById('adminNotes<?php echo $report['id']; ?>').value, true)">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>Warn User
                                                </button>
                                            </form>
                                            
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                <input type="hidden" name="reported_user_id" value="<?php echo $report['reported_id']; ?>">
                                                <input type="hidden" name="admin_notes" id="notesSuspend<?php echo $report['id']; ?>">
                                                <div class="input-group mb-2">
                                                    <input type="number" name="suspension_days" class="form-control" value="7" min="1" max="365" placeholder="Days">
                                                    <button type="submit" name="action" value="suspend_user" class="btn btn-danger" onclick="return confirm('Suspend this user account?') && (document.getElementById('notesSuspend<?php echo $report['id']; ?>').value = document.getElementById('adminNotes<?php echo $report['id']; ?>').value, true)">
                                                        <i class="fas fa-ban me-2"></i>Suspend User
                                                    </button>
                                                </div>
                                            </form>
                                            
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                <input type="hidden" name="reported_user_id" value="<?php echo $report['reported_id']; ?>">
                                                <input type="hidden" name="admin_notes" id="notesBan<?php echo $report['id']; ?>">
                                                <button type="submit" name="action" value="ban_user" class="btn btn-dark w-100" onclick="return confirm('PERMANENTLY BAN this user? This action cannot be undone!') && (document.getElementById('notesBan<?php echo $report['id']; ?>').value = document.getElementById('adminNotes<?php echo $report['id']; ?>').value, true)">
                                                    <i class="fas fa-user-slash me-2"></i>Ban User Permanently
                                                </button>
                                            </form>
                                            
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                <input type="hidden" name="admin_notes" id="notesResolve<?php echo $report['id']; ?>">
                                                <button type="submit" name="action" value="resolve" class="btn btn-success w-100" onclick="document.getElementById('notesResolve<?php echo $report['id']; ?>').value = document.getElementById('adminNotes<?php echo $report['id']; ?>').value">
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
