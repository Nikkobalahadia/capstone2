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
$error = '';
$success = '';

$csrf_token = generate_csrf_token();

$role_map = [
    'all' => null,
    'students' => 'student',
    'mentors' => 'mentor',
    'peers' => 'peer',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'];
        
        try {
            if ($action === 'create') {
                $target_audience = $_POST['target_audience'] ?? null;

                if (!isset($role_map[$target_audience])) {
                    $error = 'Invalid target audience selected.';
                } else {
                    $target_role = $role_map[$target_audience];
                    
                    $stmt = $db->prepare("INSERT INTO announcements (title, message, type, target_audience, is_active, created_by) VALUES (?, ?, ?, ?, 1, ?)");
                    $stmt->execute([
                        sanitize_input($_POST['title']),
                        sanitize_input($_POST['message']),
                        $_POST['type'],
                        $target_audience,
                        $user['id']
                    ]);
                    
                    $announcement_id = $db->lastInsertId();
                    $title = sanitize_input($_POST['title']);
                    $message = sanitize_input($_POST['message']);
                    
                    if ($target_role === null) {
                        $users_stmt = $db->query("SELECT id FROM users WHERE is_active = 1");
                    } else {
                        $users_stmt = $db->prepare("SELECT id FROM users WHERE role = ? AND is_active = 1");
                        $users_stmt->execute([$target_role]);
                    }
                    
                    $users = $users_stmt->fetchAll();
                    
                    foreach ($users as $target_user) {
                        create_notification(
                            $target_user['id'],
                            'announcement',
                            $title,
                            $message,
                            '/admin/announcements.php'
                        );
                    }
                    
                    $success = 'Announcement created and notifications sent to ' . count($users) . ' users.';
                }

            } elseif ($action === 'delete') {
                $stmt = $db->prepare("DELETE FROM announcements WHERE id = ?");
                $stmt->execute([(int)$_POST['announcement_id']]);
                $success = 'Announcement deleted successfully.';
            } elseif ($action === 'toggle') {
                $stmt = $db->prepare("UPDATE announcements SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([(int)$_POST['announcement_id']]);
                $success = 'Announcement status updated.';
            }
        } catch (Exception $e) {
            $error = 'Failed to perform action. Please try again.';
        }
    }
}

// Pagination and filtering
$status_filter = $_GET['status'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';
$audience_filter = $_GET['audience'] ?? 'all';
$search = $_GET['search'] ?? '';
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if (!in_array($per_page, [12, 24, 48, 96])) {
    $per_page = 12;
}

$where_conditions = [];
$params = [];

if ($status_filter === 'active') {
    $where_conditions[] = "a.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $where_conditions[] = "a.is_active = 0";
}

if ($type_filter !== 'all') {
    $where_conditions[] = "a.type = ?";
    $params[] = $type_filter;
}

if ($audience_filter !== 'all') {
    $where_conditions[] = "a.target_audience = ?";
    $params[] = $audience_filter;
}

if ($search) {
    $where_conditions[] = "(a.title LIKE ? OR a.message LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$count_stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM announcements a
    $where_clause
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);
$offset = ($page - 1) * $per_page;

$query = "
    SELECT a.*, u.first_name, u.last_name
    FROM announcements a
    JOIN users u ON a.created_by = u.id
    $where_clause
    ORDER BY a.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$announcements = $stmt->fetchAll();

// Get statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_announcements,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_announcements,
        COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_announcements,
        COUNT(CASE WHEN type = 'info' THEN 1 END) as info_type,
        COUNT(CASE WHEN type = 'warning' THEN 1 END) as warning_type,
        COUNT(CASE WHEN type = 'alert' THEN 1 END) as alert_type
    FROM announcements
")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Admin</title>
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
            transition: all 0.2s;
        }
        
        .card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
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
            font-size: 12px;
            font-weight: 500;
            padding: 4px 10px;
            border-radius: 6px;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: white;
            border-radius: 12px;
            margin-top: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
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
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        
        .empty-state i {
            color: #d1d5db;
            margin-bottom: 16px;
        }
        
        .empty-state p {
            color: #6b7280;
            font-size: 15px;
        }
        
        .announcement-card {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .announcement-card .card-body {
            flex: 1;
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
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
                        <h1>Announcements</h1>
                        <p class="mb-0">Create and manage platform-wide announcements</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                        <i class="fas fa-plus me-2"></i> New Announcement
                    </button>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-value text-primary"><?php echo $stats['total_announcements']; ?></div>
                    <div class="stat-label">Total Announcements</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value text-success"><?php echo $stats['active_announcements']; ?></div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value text-secondary"><?php echo $stats['inactive_announcements']; ?></div>
                    <div class="stat-label">Inactive</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value text-info"><?php echo $stats['info_type']; ?></div>
                    <div class="stat-label">Info Type</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value text-warning"><?php echo $stats['warning_type']; ?></div>
                    <div class="stat-label">Warning Type</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value text-danger"><?php echo $stats['alert_type']; ?></div>
                    <div class="stat-label">Alert Type</div>
                </div>
            </div>

            <div class="filter-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-3 col-lg-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="info" <?php echo $type_filter === 'info' ? 'selected' : ''; ?>>Info</option>
                            <option value="warning" <?php echo $type_filter === 'warning' ? 'selected' : ''; ?>>Warning</option>
                            <option value="alert" <?php echo $type_filter === 'alert' ? 'selected' : ''; ?>>Alert</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <label class="form-label">Audience</label>
                        <select name="audience" class="form-select">
                            <option value="all" <?php echo $audience_filter === 'all' ? 'selected' : ''; ?>>All Audiences</option>
                            <option value="all" <?php echo $audience_filter === 'all' ? 'selected' : ''; ?>>All Users</option>
                            <option value="students" <?php echo $audience_filter === 'students' ? 'selected' : ''; ?>>Students</option>
                            <option value="mentors" <?php echo $audience_filter === 'mentors' ? 'selected' : ''; ?>>Mentors</option>
                            <option value="peers" <?php echo $audience_filter === 'peers' ? 'selected' : ''; ?>>Peers</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <label class="form-label">Per Page</label>
                        <select name="per_page" class="form-select">
                            <option value="12" <?php echo $per_page === 12 ? 'selected' : ''; ?>>12</option>
                            <option value="24" <?php echo $per_page === 24 ? 'selected' : ''; ?>>24</option>
                            <option value="48" <?php echo $per_page === 48 ? 'selected' : ''; ?>>48</option>
                            <option value="96" <?php echo $per_page === 96 ? 'selected' : ''; ?>>96</option>
                        </select>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search announcements..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-6 col-lg-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <div class="row">
                <?php foreach ($announcements as $announcement): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card announcement-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-<?php echo $announcement['type'] === 'info' ? 'primary' : ($announcement['type'] === 'warning' ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst($announcement['type']); ?>
                                    </span>
                                    <span class="badge bg-secondary"><?php echo ucfirst($announcement['target_audience']); ?></span>
                                    <?php if ($announcement['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                        <button type="submit" class="btn btn-outline-secondary" title="<?php echo $announcement['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="fas fa-<?php echo $announcement['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this announcement?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($announcement['message'])); ?></p>
                                <div class="small text-muted mt-3">
                                    <i class="fas fa-user me-1"></i>
                                    <?php echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']); ?>
                                    <br>
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($announcements)): ?>
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="fas fa-bullhorn fa-3x"></i>
                            <p>No announcements found matching your filters.</p>
                            <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#createModal">
                                <i class="fas fa-plus me-2"></i> Create First Announcement
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination-wrapper">
                <div class="text-muted small">
                    Showing <?php echo (($page - 1) * $per_page) + 1; ?> to <?php echo min($page * $per_page, $total_records); ?> of <?php echo $total_records; ?> announcements
                </div>
                <nav>
                    <ul class="pagination mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $per_page; ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&audience=<?php echo $audience_filter; ?>&search=<?php echo urlencode($search); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1&per_page=' . $per_page . '&status=' . $status_filter . '&type=' . $type_filter . '&audience=' . $audience_filter . '&search=' . urlencode($search) . '">1</a></li>';
                            if ($start_page > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            $active = $i === $page ? 'active' : '';
                            echo '<li class="page-item ' . $active . '"><a class="page-link" href="?page=' . $i . '&per_page=' . $per_page . '&status=' . $status_filter . '&type=' . $type_filter . '&audience=' . $audience_filter . '&search=' . urlencode($search) . '">' . $i . '</a></li>';
                        }
                        
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&per_page=' . $per_page . '&status=' . $status_filter . '&type=' . $type_filter . '&audience=' . $audience_filter . '&search=' . urlencode($search) . '">' . $total_pages . '</a></li>';
                        }
                        ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $per_page; ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&audience=<?php echo $audience_filter; ?>&search=<?php echo urlencode($search); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Create Announcement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-control" rows="4" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-select" required>
                                <option value="info">Info</option>
                                <option value="warning">Warning</option>
                                <option value="alert">Alert</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Target Audience</label>
                            <select name="target_audience" class="form-select" required>
                                <option value="all">All Users</option>
                                <option value="students">Students Only</option>
                                <option value="mentors">Mentors Only</option>
                                <option value="peers">Peers Only</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>