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

// Get filter parameters
$action_filter = $_GET['action'] ?? 'all';
$user_filter = $_GET['user_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if (!in_array($per_page, [25, 50, 100, 250])) {
    $per_page = 25;
}

// Build query
$where_clauses = ["DATE(ual.created_at) BETWEEN ? AND ?"];
$params = [$date_from, $date_to];

if ($action_filter !== 'all') {
    $where_clauses[] = "ual.action = ?";
    $params[] = $action_filter;
}

if (!empty($user_filter)) {
    $where_clauses[] = "ual.user_id = ?";
    $params[] = $user_filter;
}

if (!empty($search)) {
    $where_clauses[] = "(ual.details LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR ual.action LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

// Count total records
$count_stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM user_activity_logs ual
    LEFT JOIN users u ON ual.user_id = u.id
    $where_sql
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);
$offset = ($page - 1) * $per_page;

// Get paginated logs
$query = "
    SELECT 
        ual.*,
        CONCAT(u.first_name, ' ', u.last_name) as user_name,
        u.email as user_email,
        u.role as user_role
    FROM user_activity_logs ual
    LEFT JOIN users u ON ual.user_id = u.id
    $where_sql
    ORDER BY ual.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get unique actions for filter
$actions = $db->query("SELECT DISTINCT action FROM user_activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_actions,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(DISTINCT DATE(created_at)) as active_days,
        COUNT(CASE WHEN action = 'login' THEN 1 END) as login_count,
        COUNT(CASE WHEN action LIKE '%create%' THEN 1 END) as create_count,
        COUNT(CASE WHEN action LIKE '%update%' THEN 1 END) as update_count,
        COUNT(CASE WHEN action LIKE '%delete%' THEN 1 END) as delete_count
    FROM user_activity_logs
    WHERE DATE(created_at) BETWEEN ? AND ?
";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$date_from, $date_to]);
$stats = $stats_stmt->fetch();

// Get action breakdown for chart
$action_breakdown = $db->prepare("
    SELECT action, COUNT(*) as count
    FROM user_activity_logs
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY action
    ORDER BY count DESC
    LIMIT 10
");
$action_breakdown->execute([$date_from, $date_to]);
$action_data = $action_breakdown->fetchAll();
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .badge {
            font-size: 11px;
            font-weight: 500;
            padding: 4px 8px;
            border-radius: 6px;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
        
        .log-entry { 
            border-left: 3px solid #667eea; 
            padding: 16px;
            padding-left: 20px;
            margin-bottom: 12px;
            background: #f9fafb;
            border-radius: 0 8px 8px 0;
            transition: all 0.2s;
        }
        
        .log-entry:hover {
            background: #f3f4f6;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        
        .log-entry:last-child {
            margin-bottom: 0;
        }
        
        .log-entry.admin { border-left-color: #dc3545; }
        .log-entry.mentor { border-left-color: #198754; }
        .log-entry.student { border-left-color: #0dcaf0; }
        .log-entry.peer { border-left-color: #fd7e14; }
        
        .log-details {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            background: white;
            padding: 10px;
            border-radius: 6px;
            margin-top: 8px;
            border: 1px solid #e5e7eb;
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
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
            }
            
            .chart-container {
                height: 250px;
            }
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
                        <h1>Activity Logs</h1>
                        <p class="mb-0">Monitor user actions and system events</p>
                    </div>
                    <div>
                        <button class="btn btn-success" onclick="window.print()">
                            <i class="fas fa-print me-2"></i> Print
                        </button>
                    </div>
                </div>
            </div>

            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-value text-primary"><?php echo number_format($stats['total_actions']); ?></div>
                    <div class="stat-label">Total Actions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value text-info"><?php echo number_format($stats['unique_users']); ?></div>
                    <div class="stat-label">Unique Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value text-success"><?php echo number_format($stats['active_days']); ?></div>
                    <div class="stat-label">Active Days</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value text-warning"><?php echo number_format($stats['login_count']); ?></div>
                    <div class="stat-label">Logins</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value text-success"><?php echo number_format($stats['create_count']); ?></div>
                    <div class="stat-label">Creates</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value text-info"><?php echo number_format($stats['update_count']); ?></div>
                    <div class="stat-label">Updates</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value text-danger"><?php echo number_format($stats['delete_count']); ?></div>
                    <div class="stat-label">Deletes</div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="m-0">Top 10 Actions Breakdown</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="actionsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="filter-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-3 col-lg-2">
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
                    <div class="col-md-3 col-lg-2">
                        <label class="form-label">Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <label class="form-label">Per Page</label>
                        <select name="per_page" class="form-select">
                            <option value="25" <?php echo $per_page === 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $per_page === 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $per_page === 100 ? 'selected' : ''; ?>>100</option>
                            <option value="250" <?php echo $per_page === 250 ? 'selected' : ''; ?>>250</option>
                        </select>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search logs..." value="<?php echo htmlspecialchars($search); ?>">
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
                    <h6 class="m-0">Activity Timeline (<?php echo $total_records; ?> total, showing <?php echo count($logs); ?>)</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($logs)): ?>
                        <div class="empty-state">
                            <i class="fas fa-history fa-3x"></i>
                            <p>No activity logs found for the selected filters.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <div class="log-entry <?php echo $log['user_role'] ?? 'system'; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <strong class="text-dark"><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></strong>
                                            <?php if ($log['user_role']): ?>
                                                <span class="badge bg-secondary"><?php echo ucfirst($log['user_role']); ?></span>
                                            <?php endif; ?>
                                            <span class="badge bg-primary"><?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?></span>
                                        </div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($log['user_email'] ?? 'system@internal'); ?></div>
                                    </div>
                                    <div class="text-end flex-shrink-0 ms-3">
                                        <div class="small text-muted" title="<?php echo $log['created_at']; ?>">
                                            <i class="fas fa-clock me-1"></i><?php echo date('M d, Y g:i A', strtotime($log['created_at'])); ?>
                                        </div>
                                        <?php if ($log['ip_address']): ?>
                                            <div class="small text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($log['ip_address']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($log['details']): ?>
                                    <div class="log-details">
                                        <?php echo htmlspecialchars($log['details']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper">
                    <div class="text-muted small">
                        Showing <?php echo (($page - 1) * $per_page) + 1; ?> to <?php echo min($page * $per_page, $total_records); ?> of <?php echo $total_records; ?> logs
                    </div>
                    <nav>
                        <ul class="pagination mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $per_page; ?>&action=<?php echo $action_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1&per_page=' . $per_page . '&action=' . $action_filter . '&date_from=' . $date_from . '&date_to=' . $date_to . '&search=' . urlencode($search) . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                $active = $i === $page ? 'active' : '';
                                echo '<li class="page-item ' . $active . '"><a class="page-link" href="?page=' . $i . '&per_page=' . $per_page . '&action=' . $action_filter . '&date_from=' . $date_from . '&date_to=' . $date_to . '&search=' . urlencode($search) . '">' . $i . '</a></li>';
                            }
                            
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&per_page=' . $per_page . '&action=' . $action_filter . '&date_from=' . $date_from . '&date_to=' . $date_to . '&search=' . urlencode($search) . '">' . $total_pages . '</a></li>';
                            }
                            ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $per_page; ?>&action=<?php echo $action_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>">
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
        const actionLabels = <?php echo json_encode(array_column($action_data, 'action')); ?>;
        const actionCounts = <?php echo json_encode(array_column($action_data, 'count')); ?>;
        
        const ctx = document.getElementById('actionsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: actionLabels.map(label => label.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())),
                datasets: [{
                    label: 'Number of Actions',
                    data: actionCounts,
                    backgroundColor: [
                        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                        '#06b6d4', '#ec4899', '#14b8a6', '#f97316', '#6366f1'
                    ],
                    borderColor: [
                        '#2563eb', '#059669', '#d97706', '#dc2626', '#7c3aed',
                        '#0891b2', '#db2777', '#0d9488', '#ea580c', '#4f46e5'
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
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>
</html>