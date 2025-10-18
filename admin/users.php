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
$error = '';
$success = '';

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Username', 'Email', 'First Name', 'Last Name', 'Role', 'Verified', 'Active', 'Joined Date']);
    
    $export_query = "SELECT id, username, email, first_name, last_name, role, is_verified, is_active, created_at FROM users WHERE role != 'admin' ORDER BY created_at DESC";
    $export_stmt = $db->query($export_query);
    
    while ($row = $export_stmt->fetch()) {
        fputcsv($output, [
            $row['id'],
            $row['username'],
            $row['email'],
            $row['first_name'],
            $row['last_name'],
            $row['role'],
            $row['is_verified'] ? 'Yes' : 'No',
            $row['is_active'] ? 'Yes' : 'No',
            $row['created_at']
        ]);
    }
    
    fclose($output);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'];
        
        try {
            if ($action === 'create') {
                $username = sanitize_input($_POST['username']);
                $email = sanitize_input($_POST['email']);
                $first_name = sanitize_input($_POST['first_name']);
                $last_name = sanitize_input($_POST['last_name']);
                $role = sanitize_input($_POST['role']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("INSERT INTO users (username, email, first_name, last_name, role, password_hash, is_verified, is_active) VALUES (?, ?, ?, ?, ?, ?, 1, 1)");
                $stmt->execute([$username, $email, $first_name, $last_name, $role, $password]);
                
                $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'admin_create_user', ?, ?)");
                $log_stmt->execute([$user['id'], json_encode(['created_user_email' => $email]), $_SERVER['REMOTE_ADDR']]);
                
                $success = 'User created successfully.';
            } elseif ($action === 'update') {
                $user_id = (int)$_POST['user_id'];
                $username = sanitize_input($_POST['username']);
                $email = sanitize_input($_POST['email']);
                $first_name = sanitize_input($_POST['first_name']);
                $last_name = sanitize_input($_POST['last_name']);
                $role = sanitize_input($_POST['role']);
                
                $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $email, $first_name, $last_name, $role, $user_id]);
                
                if (!empty($_POST['password'])) {
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $pwd_stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $pwd_stmt->execute([$password, $user_id]);
                }
                
                $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'admin_update_user', ?, ?)");
                $log_stmt->execute([$user['id'], json_encode(['updated_user_id' => $user_id]), $_SERVER['REMOTE_ADDR']]);
                
                $success = 'User updated successfully.';
            } else {
                // Existing actions (verify, unverify, activate, deactivate, delete)
                $user_id = (int)$_POST['user_id'];
                
                switch ($action) {
                    case 'verify':
                        $stmt = $db->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
                        $stmt->execute([$user_id]);
                        
                        $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'admin_verify_user', ?, ?)");
                        $log_stmt->execute([$user['id'], json_encode(['verified_user_id' => $user_id]), $_SERVER['REMOTE_ADDR']]);
                        
                        $success = 'User verified successfully.';
                        break;
                        
                    case 'unverify':
                        $stmt = $db->prepare("UPDATE users SET is_verified = 0 WHERE id = ?");
                        $stmt->execute([$user_id]);
                        
                        $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'admin_unverify_user', ?, ?)");
                        $log_stmt->execute([$user['id'], json_encode(['unverified_user_id' => $user_id]), $_SERVER['REMOTE_ADDR']]);
                        
                        $success = 'User verification removed.';
                        break;
                        
                    case 'activate':
                        $stmt = $db->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
                        $stmt->execute([$user_id]);
                        
                        $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'admin_activate_user', ?, ?)");
                        $log_stmt->execute([$user['id'], json_encode(['activated_user_id' => $user_id]), $_SERVER['REMOTE_ADDR']]);
                        
                        $success = 'User activated successfully.';
                        break;
                        
                    case 'deactivate':
                        $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                        $stmt->execute([$user_id]);
                        
                        $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'admin_deactivate_user', ?, ?)");
                        $log_stmt->execute([$user['id'], json_encode(['deactivated_user_id' => $user_id]), $_SERVER['REMOTE_ADDR']]);
                        
                        $success = 'User deactivated successfully.';
                        break;
                        
                    case 'delete':
                        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        
                        $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'admin_delete_user', ?, ?)");
                        $log_stmt->execute([$user['id'], json_encode(['deleted_user_id' => $user_id]), $_SERVER['REMOTE_ADDR']]);
                        
                        $success = 'User deleted successfully.';
                        break;
                }
            }
        } catch (Exception $e) {
            $error = 'Failed to perform action: ' . $e->getMessage();
        }
    }
}

$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_dir = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'ASC' : 'DESC';
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;

// Validate per_page
$allowed_per_page = [10, 25, 50, 100];
if (!in_array($per_page, $allowed_per_page)) {
    $per_page = 25;
}

// Validate sort column
$allowed_sorts = ['first_name', 'last_name', 'email', 'role', 'created_at', 'is_verified', 'is_active'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'created_at';
}

// Get filters
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build query
$where_conditions = ["u.role != 'admin'"];
$params = [];

if ($role_filter) {
    $where_conditions[] = "u.role = ?";
    $params[] = $role_filter;
}

if ($status_filter === 'verified') {
    $where_conditions[] = "u.is_verified = 1";
} elseif ($status_filter === 'unverified') {
    $where_conditions[] = "u.is_verified = 0";
} elseif ($status_filter === 'active') {
    $where_conditions[] = "u.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $where_conditions[] = "u.is_active = 0";
}

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_clause = implode(' AND ', $where_conditions);

// Get users with statistics
$users_query = "
    SELECT u.*,
           u.profile_picture,
           COUNT(DISTINCT m1.id) as matches_as_student,
           COUNT(DISTINCT m2.id) as matches_as_mentor,
           COUNT(DISTINCT s.id) as completed_sessions,
           AVG(sr.rating) as avg_rating,
           COUNT(DISTINCT sr.id) as rating_count
    FROM users u
    LEFT JOIN matches m1 ON u.id = m1.student_id AND m1.status = 'accepted'
    LEFT JOIN matches m2 ON u.id = m2.mentor_id AND m2.status = 'accepted'
    LEFT JOIN matches m ON (u.id = m.student_id OR u.id = m.mentor_id) AND m.status = 'accepted'
    LEFT JOIN sessions s ON m.id = s.match_id AND s.status = 'completed'
    LEFT JOIN session_ratings sr ON u.id = sr.rated_id
    WHERE $where_clause
    GROUP BY u.id
    ORDER BY u.$sort_by $sort_dir
    LIMIT $per_page
";

$stmt = $db->prepare($users_query);
$stmt->execute($params);
$users = $stmt->fetchAll();

function getSortLink($column, $current_sort, $current_dir) {
    $params = $_GET;
    $params['sort'] = $column;
    $params['dir'] = ($current_sort === $column && $current_dir === 'DESC') ? 'asc' : 'desc';
    return '?' . http_build_query($params);
}

function getSortIcon($column, $current_sort, $current_dir) {
    if ($current_sort !== $column) return '<i class="fas fa-sort text-muted"></i>';
    return $current_dir === 'ASC' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - StudyConnect Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; border-radius: 8px; margin: 4px 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
        .sortable-header { cursor: pointer; user-select: none; }
        .sortable-header:hover { background-color: #e9ecef; }
        .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .action-buttons .btn { padding: 0.375rem 0.65rem; font-size: 0.85rem; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } .sidebar { display: none; } }
    </style>
</head>
<body>
    <?php require_once '../includes/admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">User Management</h1>
                    <p class="text-muted">Manage user accounts, verification, and activity.</p>
                </div>
                <div>
                    <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="fas fa-plus me-2"></i> Add New User
                    </button>
                    <a href="?export=csv" class="btn btn-success">
                        <i class="fas fa-download me-2"></i> Export to CSV
                    </a>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_by); ?>">
                        <input type="hidden" name="dir" value="<?php echo htmlspecialchars($sort_dir); ?>">
                        
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" id="search" name="search" class="form-control" 
                                   placeholder="Name, email, or username"
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="role" class="form-label">Role</label>
                            <select id="role" name="role" class="form-select">
                                <option value="">All Roles</option>
                                <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Students</option>
                                <option value="mentor" <?php echo $role_filter === 'mentor' ? 'selected' : ''; ?>>Mentors</option>
                                <option value="peer" <?php echo $role_filter === 'peer' ? 'selected' : ''; ?>>Peers</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                <option value="unverified" <?php echo $status_filter === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="per_page" class="form-label">Per Page</label>
                            <select id="per_page" name="per_page" class="form-select" onchange="this.form.submit()">
                                <option value="10" <?php echo $per_page === 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $per_page === 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $per_page === 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $per_page === 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                        
                        <div class="col-md-2">
                            <a href="users.php" class="btn btn-secondary w-100">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Users (<?php echo count($users); ?>)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="sortable-header" onclick="window.location='<?php echo getSortLink('first_name', $sort_by, $sort_dir); ?>'">
                                        User <?php echo getSortIcon('first_name', $sort_by, $sort_dir); ?>
                                    </th>
                                    <th class="sortable-header" onclick="window.location='<?php echo getSortLink('role', $sort_by, $sort_dir); ?>'">
                                        Role <?php echo getSortIcon('role', $sort_by, $sort_dir); ?>
                                    </th>
                                    <th>Status</th>
                                    <th>Activity</th>
                                    <th>Rating</th>
                                    <th class="sortable-header" onclick="window.location='<?php echo getSortLink('created_at', $sort_by, $sort_dir); ?>'">
                                        Joined <?php echo getSortIcon('created_at', $sort_by, $sort_dir); ?>
                                    </th>
                                    <th style="width: 280px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <?php if (!empty($u['profile_picture']) && file_exists('../' . $u['profile_picture'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($u['profile_picture']); ?>" 
                                                         alt="<?php echo htmlspecialchars($u['first_name']); ?>" 
                                                         style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                                <?php else: ?>
                                                    <div style="width: 40px; height: 40px; background: #667eea; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.875rem;">
                                                        <?php echo strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></div>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($u['email']); ?></div>
                                                    <div class="small text-muted">@<?php echo htmlspecialchars($u['username']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $u['role'] === 'student' ? 'bg-primary' : 
                                                    ($u['role'] === 'mentor' ? 'bg-success' : 'bg-info'); 
                                            ?>">
                                                <?php echo ucfirst($u['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <?php if ($u['is_verified']): ?>
                                                    <span class="text-success"><i class="fas fa-check-circle"></i> Verified</span>
                                                <?php else: ?>
                                                    <span class="text-warning"><i class="fas fa-exclamation-circle"></i> Unverified</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="small">
                                                <?php if ($u['is_active']): ?>
                                                    <span class="text-success"><i class="fas fa-circle"></i> Active</span>
                                                <?php else: ?>
                                                    <span class="text-danger"><i class="fas fa-circle"></i> Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small"><?php echo ($u['matches_as_student'] + $u['matches_as_mentor']); ?> matches</div>
                                            <div class="small text-muted"><?php echo $u['completed_sessions']; ?> sessions</div>
                                        </td>
                                        <td>
                                            <?php if ($u['avg_rating']): ?>
                                                <div class="small">
                                                    <i class="fas fa-star text-warning"></i> 
                                                    <?php echo number_format($u['avg_rating'], 1); ?>/5
                                                </div>
                                                <div class="small text-muted">(<?php echo $u['rating_count']; ?> reviews)</div>
                                            <?php else: ?>
                                                <span class="text-muted small">No ratings</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="small"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="editUser(<?php echo htmlspecialchars(json_encode($u)); ?>)"
                                                        title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <?php if (!$u['is_verified']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <input type="hidden" name="action" value="verify">
                                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Verify">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($u['is_active']): ?>
                                                    <form method="POST" onsubmit="return confirmDeactivate(this);">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <input type="hidden" name="action" value="deactivate">
                                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="Deactivate">
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <input type="hidden" name="action" value="activate">
                                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Activate">
                                                            <i class="fas fa-check-circle"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" onsubmit="return confirmDelete(this);">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($users)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-users fa-3x mb-3"></i>
                                <p>No users found matching your criteria.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role *</label>
                            <select name="role" class="form-select" required>
                                <option value="student">Student</option>
                                <option value="mentor">Mentor</option>
                                <option value="peer">Peer</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" id="edit_username" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role *</label>
                            <select name="role" id="edit_role" class="form-select" required>
                                <option value="student">Student</option>
                                <option value="mentor">Mentor</option>
                                <option value="peer">Peer</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="password" class="form-control" minlength="6">
                            <small class="text-muted">Leave blank to keep current password</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function confirmDeactivate(form) {
            event.preventDefault();

            Swal.fire({
                title: 'Deactivate this user?',
                text: "This user will be unable to log in until reactivated.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f59e0b',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, deactivate',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });

            return false;
        }

        function confirmDelete(form) {
            event.preventDefault();

            Swal.fire({
                title: 'Are you sure?',
                text: "Are you sure you want to delete this user? This action cannot be undone.",
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });

            return false;
        }

        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_first_name').value = user.first_name;
            document.getElementById('edit_last_name').value = user.last_name;
            document.getElementById('edit_role').value = user.role;
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>