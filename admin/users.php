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

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'];
        $user_id = (int)$_POST['user_id'];
        
        try {
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
        } catch (Exception $e) {
            $error = 'Failed to perform action. Please try again.';
        }
    }
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
    ORDER BY u.created_at DESC
    LIMIT 50
";

$stmt = $db->prepare($users_query);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - StudyConnect Admin</title>
    <!-- Updated to use Bootstrap and purple admin theme -->
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
    <!-- Replaced horizontal header with purple sidebar navigation -->
    <div class="sidebar position-fixed" style="width: 250px; z-index: 1000;">
        <div class="p-4">
            <h4 class="text-white mb-0">Admin Panel</h4>
            <small class="text-white-50">Study Mentorship Platform</small>
        </div>
        <nav class="nav flex-column px-3">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            <a class="nav-link active" href="users.php">
                <i class="fas fa-users me-2"></i> User Management
            </a>
            <a class="nav-link" href="monitoring.php">
                <i class="fas fa-chart-line me-2"></i> System Monitoring
            </a>
            <a class="nav-link" href="reports-inbox.php">
                <i class="fas fa-inbox me-2"></i> Reports & Feedback
            </a>
            <a class="nav-link" href="session-tracking.php">
                <i class="fas fa-calendar-check me-2"></i> Session Tracking
            </a>
            <a class="nav-link" href="matches.php">
                <i class="fas fa-handshake me-2"></i> Matches
            </a>
            <a class="nav-link" href="sessions.php">
                <i class="fas fa-video me-2"></i> Sessions
            </a>
            <a class="nav-link" href="reports.php">
                <i class="fas fa-chart-bar me-2"></i> Reports
            </a>
        </nav>
        <div class="position-absolute bottom-0 w-100 p-3">
            <a href="../auth/logout.php" class="btn btn-outline-light btn-sm w-100">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </div>
    </div>

    <!-- Updated main content area to work with sidebar layout -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">User Management</h1>
                    <p class="text-muted">Manage user accounts, verification, and activity.</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3 align-items-end">
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
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>
                        <div class="col-md-2">
                            <a href="users.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Users (<?php echo count($users); ?>)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Activity</th>
                                    <th>Rating</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($u['email']); ?></div>
                                            <div class="small text-muted">@<?php echo htmlspecialchars($u['username']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $u['role'] === 'student' ? 'bg-primary' : 'bg-success'; ?>">
                                                <?php echo ucfirst($u['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <?php if ($u['is_verified']): ?>
                                                    <span class="text-success">✓ Verified</span>
                                                <?php else: ?>
                                                    <span class="text-warning">⚠ Unverified</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="small">
                                                <?php if ($u['is_active']): ?>
                                                    <span class="text-success">● Active</span>
                                                <?php else: ?>
                                                    <span class="text-danger">● Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small"><?php echo ($u['matches_as_student'] + $u['matches_as_mentor']); ?> matches</div>
                                            <div class="small text-muted"><?php echo $u['completed_sessions']; ?> sessions</div>
                                        </td>
                                        <td>
                                            <?php if ($u['avg_rating']): ?>
                                                <div class="small"><?php echo number_format($u['avg_rating'], 1); ?>/5</div>
                                                <div class="small text-muted">(<?php echo $u['rating_count']; ?> reviews)</div>
                                            <?php else: ?>
                                                <span class="text-muted">No ratings</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="small"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></div>
                                        </td>
                                        <td>
                                            <div class="btn-group-vertical btn-group-sm">
                                                <?php if (!$u['is_verified']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <input type="hidden" name="action" value="verify">
                                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                        <button type="submit" class="btn btn-success btn-sm">Verify</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <input type="hidden" name="action" value="unverify">
                                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                        <button type="submit" class="btn btn-warning btn-sm">Unverify</button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($u['is_active']): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to deactivate this user?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <input type="hidden" name="action" value="deactivate">
                                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">Deactivate</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <input type="hidden" name="action" value="activate">
                                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                        <button type="submit" class="btn btn-success btn-sm">Activate</button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <a href="user-profile.php?id=<?php echo $u['id']; ?>" class="btn btn-secondary btn-sm">View</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($users)): ?>
                            <div class="text-center py-4 text-muted">
                                No users found matching your criteria.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Added Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
