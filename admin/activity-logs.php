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
    LIMIT 500
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get unique actions for filter
$actions = $db->query("SELECT DISTINCT action FROM user_activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// Get statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_actions,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(DISTINCT DATE(created_at)) as active_days
    FROM user_activity_logs
    WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to'
")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale-1.0">
    <title>Activity Logs - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        /* Page-Specific Styles */
        .stat-card { 
            background: white; 
            border-radius: 10px; 
            padding: 1.5rem; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); 
            border: 1px solid #e3e6f0;
        }
        
        .log-entry { 
            border-left: 3px solid #667eea; 
            padding-left: 15px; 
            margin-bottom: 15px; 
            padding-bottom: 15px;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .log-entry:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .log-entry.admin { border-left-color: #dc3545; }
        .log-entry.mentor { border-left-color: #198754; }
        .log-entry.student { border-left-color: #0dcaf0; }
        .log-entry.peer { border-left-color: #fd7e14; }
        
    </style>
</head>
<body>
    <?php include '../includes/admin-header.php'; ?>
    <?php include '../includes/admin-sidebar.php'; ?>

    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="mobile-overlay" id="mobileOverlay"></div>
    <div class="main-content">
        <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Activity Logs</h1>
                    <p class="text-muted">Monitor user actions and system events</p>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="stat-card h-100">
                        <div class="text-muted small text-uppercase">Total Actions</div>
                        <div class="h3 mb-0 font-weight-bold"><?php echo number_format($stats['total_actions']); ?></div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="stat-card h-100">
                        <div class="text-muted small text-uppercase">Unique Users</div>
                        <div class="h3 mb-0 font-weight-bold"><?php echo number_format($stats['unique_users']); ?></div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="stat-card h-100">
                        <div class="text-muted small text-uppercase">Active Days</div>
                        <div class="h3 mb-0 font-weight-bold"><?php echo number_format($stats['active_days']); ?></div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
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
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Activity Timeline</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($logs)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-history fa-3x mb-3"></i>
                            <p>No activity logs found for the selected filters.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <div class="log-entry <?php echo $log['user_role'] ?? 'system'; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong class="text-dark"><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></strong>
                                        <?php if ($log['user_role']): ?>
                                            <span class="badge bg-secondary fw-normal"><?php echo ucfirst($log['user_role']); ?></span>
                                        <?php endif; ?>
                                        <div class="text-muted small"><?php echo htmlspecialchars($log['user_email'] ?? 'system@internal'); ?></div>
                                    </div>
                                    <div class="text-end flex-shrink-0 ms-3">
                                        <div class="small text-muted" title="<?php echo $log['created_at']; ?>"><?php echo date('M d, Y g:i A', strtotime($log['created_at'])); ?></div>
                                        <?php if ($log['ip_address']): ?>
                                            <div class="small text-muted">IP: <?php echo htmlspecialchars($log['ip_address']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <span class="badge bg-primary fw-normal"><?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?></span>
                                    <?php if ($log['details']): ?>
                                        <div class="mt-1 text-muted small bg-light p-2 rounded" style="font-family: monospace, monospace;">
                                            <?php echo htmlspecialchars($log['details']); ?>
                                        </div>
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
    </body>
</html>