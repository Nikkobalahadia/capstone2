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

// Create activity_logs table if it doesn't exist
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        )
    ");
} catch (PDOException $e) {
    // Table already exists
}

// Get filter parameters
$action_filter = $_GET['action'] ?? 'all';
$user_filter = $_GET['user_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';

// Build query
$where_clauses = ["DATE(al.created_at) BETWEEN ? AND ?"];
$params = [$date_from, $date_to];

if ($action_filter !== 'all') {
    $where_clauses[] = "al.action = ?";
    $params[] = $action_filter;
}

if (!empty($user_filter)) {
    $where_clauses[] = "al.user_id = ?";
    $params[] = $user_filter;
}

if (!empty($search)) {
    $where_clauses[] = "(al.description LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

$query = "
    SELECT 
        al.*,
        CONCAT(u.first_name, ' ', u.last_name) as user_name,
        u.email as user_email,
        u.role as user_role
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $where_sql
    ORDER BY al.created_at DESC
    LIMIT 500
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get unique actions for filter
$actions = $db->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// Get statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_actions,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(DISTINCT DATE(created_at)) as active_days
    FROM activity_logs
    WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to'
")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .main-content { margin-left: 250px; padding: 20px; }
        .stat-card { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .log-entry { border-left: 3px solid #667eea; padding-left: 15px; margin-bottom: 15px; }
        .log-entry.admin { border-left-color: #dc3545; }
        .log-entry.mentor { border-left-color: #198754; }
        .log-entry.student { border-left-color: #0dcaf0; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Activity Logs</h1>
                    <p class="text-muted">Monitor user actions and system events</p>
                </div>
            </div>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="stat-card">
                        <div class="text-muted small">Total Actions</div>
                        <div class="h3 mb-0"><?php echo number_format($stats['total_actions']); ?></div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="stat-card">
                        <div class="text-muted small">Unique Users</div>
                        <div class="h3 mb-0"><?php echo number_format($stats['unique_users']); ?></div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="stat-card">
                        <div class="text-muted small">Active Days</div>
                        <div class="h3 mb-0"><?php echo number_format($stats['active_days']); ?></div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Action Type</label>
                            <select name="action" class="form-select">
                                <option value="all">All Actions</option>
                                <?php foreach ($actions as $action): ?>
                                    <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $action_filter === $action ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(str_replace('_', ' ', $action)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date From</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date To</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Search logs..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Activity Logs -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Activity Timeline</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($logs)): ?>
                        <p class="text-center text-muted py-4">No activity logs found for the selected filters.</p>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <div class="log-entry <?php echo $log['user_role'] ?? ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></strong>
                                        <?php if ($log['user_role']): ?>
                                            <span class="badge bg-secondary"><?php echo ucfirst($log['user_role']); ?></span>
                                        <?php endif; ?>
                                        <div class="text-muted small"><?php echo htmlspecialchars($log['user_email'] ?? ''); ?></div>
                                    </div>
                                    <div class="text-end">
                                        <div class="small text-muted"><?php echo date('M d, Y g:i A', strtotime($log['created_at'])); ?></div>
                                        <?php if ($log['ip_address']): ?>
                                            <div class="small text-muted">IP: <?php echo htmlspecialchars($log['ip_address']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <span class="badge bg-primary"><?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?></span>
                                    <?php if ($log['description']): ?>
                                        <div class="mt-1"><?php echo htmlspecialchars($log['description']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
