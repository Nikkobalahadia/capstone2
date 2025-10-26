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
    $export_tab = isset($_GET['tab']) ? $_GET['tab'] : 'users';
    
    if ($export_tab === 'users') {
        // Export users data
        $export_query = "
            SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.role, 
                   u.is_verified, u.is_active, u.created_at,
                   COUNT(DISTINCT m1.id) + COUNT(DISTINCT m2.id) as total_matches,
                   COUNT(DISTINCT s.id) as completed_sessions,
                   AVG(sr.rating) as avg_rating
            FROM users u
            LEFT JOIN matches m1 ON u.id = m1.student_id AND m1.status = 'accepted'
            LEFT JOIN matches m2 ON u.id = m2.mentor_id AND m2.status = 'accepted'
            LEFT JOIN matches m ON (u.id = m.student_id OR u.id = m.mentor_id) AND m.status = 'accepted'
            LEFT JOIN sessions s ON m.id = s.match_id AND s.status = 'completed'
            LEFT JOIN session_ratings sr ON u.id = sr.rated_id
            WHERE u.role != 'admin'
            GROUP BY u.id
            ORDER BY u.created_at DESC
        ";
        
        $export_stmt = $db->query($export_query);
        $export_data = $export_stmt->fetchAll();
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Username', 'Email', 'First Name', 'Last Name', 'Role', 'Verified', 'Active', 'Total Matches', 'Completed Sessions', 'Avg Rating', 'Joined Date']);
        
        foreach ($export_data as $row) {
            fputcsv($output, [
                $row['id'],
                $row['username'],
                $row['email'],
                $row['first_name'],
                $row['last_name'],
                ucfirst($row['role']),
                $row['is_verified'] ? 'Yes' : 'No',
                $row['is_active'] ? 'Yes' : 'No',
                $row['total_matches'],
                $row['completed_sessions'],
                $row['avg_rating'] ? number_format($row['avg_rating'], 2) : 'N/A',
                date('Y-m-d H:i:s', strtotime($row['created_at']))
            ]);
        }
        
        fclose($output);
        exit;
    } elseif ($export_tab === 'verification') {
        // Export verification data
        $export_query = "
            SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.role, 
                   u.is_verified, u.created_at,
                   COUNT(DISTINCT uvd.id) as total_documents,
                   SUM(CASE WHEN uvd.status = 'pending' THEN 1 ELSE 0 END) as pending_documents,
                   SUM(CASE WHEN uvd.status = 'approved' THEN 1 ELSE 0 END) as approved_documents,
                   SUM(CASE WHEN uvd.status = 'rejected' THEN 1 ELSE 0 END) as rejected_documents,
                   MAX(uvd.created_at) as last_upload_date
            FROM users u
            LEFT JOIN user_verification_documents uvd ON u.id = uvd.user_id
            WHERE (u.role = 'mentor' OR u.role = 'peer')
            GROUP BY u.id
            HAVING total_documents > 0
            ORDER BY u.created_at DESC
        ";
        
        $export_stmt = $db->query($export_query);
        $export_data = $export_stmt->fetchAll();
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="verification_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Username', 'Email', 'First Name', 'Last Name', 'Role', 'Verified', 'Total Documents', 'Pending', 'Approved', 'Rejected', 'Last Upload', 'Joined Date']);
        
        foreach ($export_data as $row) {
            fputcsv($output, [
                $row['id'],
                $row['username'],
                $row['email'],
                $row['first_name'],
                $row['last_name'],
                ucfirst($row['role']),
                $row['is_verified'] ? 'Yes' : 'No',
                $row['total_documents'],
                $row['pending_documents'],
                $row['approved_documents'],
                $row['rejected_documents'],
                $row['last_upload_date'] ? date('Y-m-d H:i:s', strtotime($row['last_upload_date'])) : 'N/A',
                date('Y-m-d H:i:s', strtotime($row['created_at']))
            ]);
        }
        
        fclose($output);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_mentor') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        try {
            $user_id = (int)$_POST['user_id'];
            
            // Verify the mentor
            $stmt = $db->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
            $stmt->execute([$user_id]);
            
            // Log the activity
            $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'admin_verify_mentor', ?, ?)");
            $log_stmt->execute([$user['id'], json_encode(['verified_user_id' => $user_id]), $_SERVER['REMOTE_ADDR']]);
            
            $success = 'Mentor verified successfully!';
        } catch (Exception $e) {
            $error = 'Failed to verify mentor: ' . $e->getMessage();
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] !== 'verify_mentor') {
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

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'users';

// ... existing code for users query ...

$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_dir = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'ASC' : 'DESC';

$allowed_sorts = ['first_name', 'last_name', 'email', 'role', 'created_at', 'is_verified', 'is_active'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'created_at';
}

$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

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
    LIMIT 50
";

$stmt = $db->prepare($users_query);
$stmt->execute($params);
$users = $stmt->fetchAll();

$verification_where = ["(u.role = 'mentor' OR u.role = 'peer')"];
$verification_params = [];

$verification_role_filter = isset($_GET['verification_role']) ? $_GET['verification_role'] : '';
$verification_status_filter = isset($_GET['verification_status']) ? $_GET['verification_status'] : '';
$verification_search = isset($_GET['verification_search']) ? sanitize_input($_GET['verification_search']) : '';

if ($verification_role_filter) {
    $verification_where[] = "u.role = ?";
    $verification_params[] = $verification_role_filter;
}

if ($verification_status_filter === 'verified') {
    $verification_where[] = "u.is_verified = 1";
} elseif ($verification_status_filter === 'unverified') {
    $verification_where[] = "u.is_verified = 0";
} elseif ($verification_status_filter === 'pending') {
    $verification_where[] = "EXISTS (SELECT 1 FROM user_verification_documents uvd WHERE uvd.user_id = u.id AND uvd.status = 'pending')";
}

if ($verification_search) {
    $verification_where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $verification_search_param = "%$verification_search%";
    $verification_params = array_merge($verification_params, [$verification_search_param, $verification_search_param, $verification_search_param]);
}

$verification_where_clause = implode(' AND ', $verification_where);

$verification_query = "
    SELECT u.*,
           COUNT(DISTINCT uvd.id) as total_documents,
           SUM(CASE WHEN uvd.status = 'pending' THEN 1 ELSE 0 END) as pending_documents,
           SUM(CASE WHEN uvd.status = 'approved' THEN 1 ELSE 0 END) as approved_documents,
           SUM(CASE WHEN uvd.status = 'rejected' THEN 1 ELSE 0 END) as rejected_documents,
           MAX(uvd.created_at) as last_upload_date
    FROM users u
    LEFT JOIN user_verification_documents uvd ON u.id = uvd.user_id
    WHERE $verification_where_clause
    GROUP BY u.id
    HAVING total_documents > 0 OR ? = ''
    ORDER BY 
        CASE WHEN u.is_verified = 0 AND SUM(CASE WHEN uvd.status = 'pending' THEN 1 ELSE 0 END) > 0 THEN 0 ELSE 1 END,
        MAX(uvd.created_at) DESC
";

$verification_params[] = $verification_status_filter;
$verification_stmt = $db->prepare($verification_query);
$verification_stmt->execute($verification_params);
$verification_users = $verification_stmt->fetchAll();

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
    <title>User Management - Study Buddy Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; border-radius: 8px; margin: 4px 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        .main-content { margin-left: 250px; margin-top: 60px; padding: 20px; }
        .sortable-header { cursor: pointer; user-select: none; }
        .sortable-header:hover { background-color: #e9ecef; }
        .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .action-buttons .btn { padding: 0.375rem 0.65rem; font-size: 0.85rem; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } .sidebar { display: none; } }
    </style>
</head>
<body>
    <?php require_once '../includes/admin-header.php'; ?>
    <?php require_once '../includes/admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">User Management</h1>
                    <p class="text-muted">Manage user accounts, verification, and activity.</p>
                </div>
                <!-- Moved export button to header with conditional display -->
                <div>
                    <?php if ($active_tab === 'users'): ?>
                        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#createUserModal">
                            <i class="fas fa-plus me-2"></i> Add New User
                        </button>
                        <a href="?export=csv&tab=users" class="btn btn-success">
                            <i class="fas fa-download me-2"></i> Export Users
                        </a>
                    <?php else: ?>
                        <a href="?export=csv&tab=verification" class="btn btn-success">
                            <i class="fas fa-download me-2"></i> Export Verification Data
                        </a>
                    <?php endif; ?>
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

            <!-- Moved search/filter card before tabs -->
            <?php if ($active_tab === 'users'): ?>
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3 align-items-end">
                            <input type="hidden" name="tab" value="users">
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
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>
                            <div class="col-md-2">
                                <a href="?tab=users" class="btn btn-secondary">Clear</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3 align-items-end">
                            <input type="hidden" name="tab" value="verification">
                            
                            <div class="col-md-3">
                                <label for="verification_search" class="form-label">Search</label>
                                <input type="text" id="verification_search" name="verification_search" class="form-control" 
                                       placeholder="Name or email"
                                       value="<?php echo htmlspecialchars($verification_search); ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <label for="verification_role" class="form-label">Role</label>
                                <select id="verification_role" name="verification_role" class="form-select">
                                    <option value="">All Roles</option>
                                    <option value="mentor" <?php echo $verification_role_filter === 'mentor' ? 'selected' : ''; ?>>Mentors</option>
                                    <option value="peer" <?php echo $verification_role_filter === 'peer' ? 'selected' : ''; ?>>Peers</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="verification_status" class="form-label">Status</label>
                                <select id="verification_status" name="verification_status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $verification_status_filter === 'pending' ? 'selected' : ''; ?>>Pending Review</option>
                                    <option value="verified" <?php echo $verification_status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                    <option value="unverified" <?php echo $verification_status_filter === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>
                            <div class="col-md-2">
                                <a href="?tab=verification" class="btn btn-secondary">Clear</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Moved tabs after search card -->
            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?php echo $active_tab === 'users' ? 'active' : ''; ?>" 
                       href="?tab=users" role="tab">
                        <i class="fas fa-users me-2"></i>All Users
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?php echo $active_tab === 'verification' ? 'active' : ''; ?>" 
                       href="?tab=verification" role="tab">
                        <i class="fas fa-user-check me-2"></i>Mentor Verification
                        <?php
                        $pending_count = $db->query("SELECT COUNT(DISTINCT u.id) FROM users u 
                            LEFT JOIN user_verification_documents uvd ON u.id = uvd.user_id 
                            WHERE (u.role = 'mentor' OR u.role = 'peer') AND u.is_verified = 0 AND uvd.status = 'pending'")->fetchColumn();
                        if ($pending_count > 0):
                        ?>
                            <span class="badge bg-warning ms-2"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Users Tab -->
                <div class="tab-pane fade <?php echo $active_tab === 'users' ? 'show active' : ''; ?>">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Users (<?php echo count($users); ?>)</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="sortable-header" onclick="window.location='<?php echo getSortLink('first_name', $sort_by, $sort_dir) . '&tab=users'; ?>'">
                                                User <?php echo getSortIcon('first_name', $sort_by, $sort_dir); ?>
                                            </th>
                                            <th class="sortable-header" onclick="window.location='<?php echo getSortLink('role', $sort_by, $sort_dir) . '&tab=users'; ?>'">
                                                Role <?php echo getSortIcon('role', $sort_by, $sort_dir); ?>
                                            </th>
                                            <th>Status</th>
                                            <th>Activity</th>
                                            <th>Rating</th>
                                            <th class="sortable-header" onclick="window.location='<?php echo getSortLink('created_at', $sort_by, $sort_dir) . '&tab=users'; ?>'">
                                                Joined <?php echo getSortIcon('created_at', $sort_by, $sort_dir); ?>
                                            </th>
                                            <th style="width: 180px;">Actions</th>
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
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button class="btn btn-outline-primary" 
                                                                onclick="editUser(<?php echo htmlspecialchars(json_encode($u)); ?>)"
                                                                title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        
                                                        <?php if (!$u['is_verified']): ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                                <input type="hidden" name="action" value="verify">
                                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                                <button type="submit" class="btn btn-outline-success" title="Verify">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($u['is_active']): ?>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Deactivate this user?');">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                                <input type="hidden" name="action" value="deactivate">
                                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                                <button type="submit" class="btn btn-outline-warning" title="Deactivate">
                                                                    <i class="fas fa-ban"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                                <input type="hidden" name="action" value="activate">
                                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                                <button type="submit" class="btn btn-outline-success" title="Activate">
                                                                    <i class="fas fa-check-circle"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                            <button type="submit" class="btn btn-outline-danger" title="Delete">
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

                <!-- Mentor Verification Tab -->
                <div class="tab-pane fade <?php echo $active_tab === 'verification' ? 'show active' : ''; ?>">
                    <h5 class="mb-3">Mentor & Peer Verification</h5>

                    <?php
                    $stats_query = "
                        SELECT 
                            COUNT(DISTINCT CASE WHEN u.is_verified = 0 AND uvd.status = 'pending' THEN u.id END) as pending_users,
                            COUNT(DISTINCT CASE WHEN u.is_verified = 1 THEN u.id END) as verified_users,
                            COUNT(CASE WHEN uvd.status = 'pending' THEN 1 END) as pending_docs,
                            COUNT(CASE WHEN uvd.status = 'approved' THEN 1 END) as approved_docs
                        FROM users u
                        LEFT JOIN user_verification_documents uvd ON u.id = uvd.user_id
                        WHERE u.role IN ('mentor', 'peer')
                    ";
                    $stats = $db->query($stats_query)->fetch();
                    ?>
                    
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Review</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending_users']; ?> Users</div>
                                            <div class="small text-muted"><?php echo $stats['pending_docs']; ?> documents</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-clock fa-2x text-warning"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Verified</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['verified_users']; ?> Users</div>
                                            <div class="small text-muted"><?php echo $stats['approved_docs']; ?> approved docs</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-check-circle fa-2x text-success"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Verification Requests (<?php echo count($verification_users); ?>)</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>User</th>
                                            <th>Role</th>
                                            <th>Documents</th>
                                            <th>Status</th>
                                            <th>Last Upload</th>
                                            <th style="width: 250px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($verification_users as $u): ?>
                                            <tr class="<?php echo ($u['pending_documents'] > 0 && !$u['is_verified']) ? 'table-warning' : ''; ?>">
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
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $u['role'] === 'mentor' ? 'bg-success' : 'bg-info'; ?>">
                                                        <?php echo ucfirst($u['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <strong><?php echo $u['total_documents']; ?></strong> total
                                                    </div>
                                                    <?php if ($u['pending_documents'] > 0): ?>
                                                        <div class="small text-warning">
                                                            <i class="fas fa-clock"></i> <?php echo $u['pending_documents']; ?> pending
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($u['approved_documents'] > 0): ?>
                                                        <div class="small text-success">
                                                            <i class="fas fa-check"></i> <?php echo $u['approved_documents']; ?> approved
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($u['rejected_documents'] > 0): ?>
                                                        <div class="small text-danger">
                                                            <i class="fas fa-times"></i> <?php echo $u['rejected_documents']; ?> rejected
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($u['is_verified']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check-circle"></i> Verified
                                                        </span>
                                                    <?php else: ?>
                                                        <?php if ($u['pending_documents'] > 0): ?>
                                                            <span class="badge bg-warning priority-badge">
                                                                <i class="fas fa-exclamation-circle"></i> Needs Review
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">
                                                                <i class="fas fa-times-circle"></i> Unverified
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($u['last_upload_date']): ?>
                                                        <div class="small"><?php echo date('M j, Y', strtotime($u['last_upload_date'])); ?></div>
                                                        <div class="small text-muted"><?php echo date('g:i A', strtotime($u['last_upload_date'])); ?></div>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <button class="btn btn-sm btn-primary" 
                                                                onclick="viewDocuments(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>')">
                                                            <i class="fas fa-file-alt me-1"></i> Review Docs
                                                        </button>
                                                        
                                                        <!-- Added Verify Mentor button -->
                                                        <?php if (!$u['is_verified']): ?>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Verify this mentor? Make sure you have reviewed their documents.');">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                                <input type="hidden" name="action" value="verify_mentor">
                                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-success">
                                                                    <i class="fas fa-check-circle me-1"></i> Verify
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <?php if (empty($verification_users)): ?>
                                    <div class="text-center py-5 text-muted">
                                        <i class="fas fa-file-alt fa-3x mb-3"></i>
                                        <p>No verification requests found.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


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


    <div class="modal fade" id="documentsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Verification Documents - <span id="doc_user_name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="documents_loading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading documents...</p>
                    </div>
                    <div id="documents_content" style="display: none;"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_first_name').value = user.first_name;
            document.getElementById('edit_last_name').value = user.last_name;
            document.getElementById('edit_role').value = user.role;
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }
        
        function viewDocuments(userId, userName) {
            document.getElementById('doc_user_name').textContent = userName;
            document.getElementById('documents_loading').style.display = 'block';
            document.getElementById('documents_content').style.display = 'none';
            
            const modal = new bootstrap.Modal(document.getElementById('documentsModal'));
            modal.show();
            
            fetch('get-user-documents.php?user_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('documents_loading').style.display = 'none';
                    document.getElementById('documents_content').style.display = 'block';
                    
                    if (data.success) {
                        displayDocuments(data.documents, userId);
                    } else {
                        document.getElementById('documents_content').innerHTML = 
                            '<div class="alert alert-warning">' + data.message + '</div>';
                    }
                })
                .catch(error => {
                    document.getElementById('documents_loading').style.display = 'none';
                    document.getElementById('documents_content').style.display = 'block';
                    document.getElementById('documents_content').innerHTML = 
                        '<div class="alert alert-danger">Error loading documents: ' + error.message + '</div>';
                });
        }
        
        function displayDocuments(documents, userId) {
            if (documents.length === 0) {
                document.getElementById('documents_content').innerHTML = 
                    '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No verification documents uploaded yet.</div>';
                return;
            }
            
            const typeLabels = {
                'id': 'Government ID',
                'student_id': 'Student ID',
                'diploma': 'Diploma/Certificate',
                'transcript': 'Academic Transcript',
                'professional_cert': 'Professional Certification',
                'expertise_proof': 'Proof of Expertise',
                'other': 'Other'
            };
            
            let html = '<div class="row">';
            
            documents.forEach(doc => {
                const statusColor = doc.status === 'approved' ? 'success' : (doc.status === 'rejected' ? 'danger' : 'warning');
                const statusIcon = doc.status === 'approved' ? 'check-circle' : (doc.status === 'rejected' ? 'times-circle' : 'clock');
                
                html += `
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <strong>${typeLabels[doc.document_type] || doc.document_type}</strong>
                                <span class="badge bg-${statusColor}">
                                    <i class="fas fa-${statusIcon} me-1"></i>${doc.status.charAt(0).toUpperCase() + doc.status.slice(1)}
                                </span>
                            </div>
                            <div class="card-body">
                                ${doc.filename.match(/\.(jpg|jpeg|png|gif)$/i) ? 
                                    `<img src="../uploads/verification/${doc.filename}" class="img-fluid mb-3" style="max-height: 300px; width: 100%; object-fit: contain; border: 1px solid #dee2e6; border-radius: 4px;">` :
                                    `<div class="text-center py-5 bg-light rounded mb-3">
                                        <i class="fas fa-file-pdf fa-4x text-danger mb-2"></i>
                                        <p class="mb-0">PDF Document</p>
                                    </div>`
                                }
                                
                                ${doc.description ? `<p class="text-muted small mb-2"><strong>Description:</strong> ${doc.description}</p>` : ''}
                                <p class="text-muted small mb-2"><strong>Uploaded:</strong> ${new Date(doc.created_at).toLocaleDateString()}</p>
                                ${doc.reviewed_at ? `<p class="text-muted small mb-2"><strong>Reviewed:</strong> ${new Date(doc.reviewed_at).toLocaleDateString()}</p>` : ''}
                                ${doc.rejection_reason ? `<div class="alert alert-danger small mb-2"><strong>Rejection Reason:</strong> ${doc.rejection_reason}</div>` : ''}
                                
                                <div class="d-flex gap-2 mt-3">
                                    <a href="../uploads/verification/${doc.filename}" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-external-link-alt me-1"></i>Open
                                    </a>
                                    
                                    ${doc.status === 'pending' ? `
                                        <button onclick="updateDocumentStatus(${doc.id}, ${userId}, 'approved')" class="btn btn-sm btn-success">
                                            <i class="fas fa-check me-1"></i>Approve
                                        </button>
                                        <button onclick="rejectDocument(${doc.id}, ${userId})" class="btn btn-sm btn-danger">
                                            <i class="fas fa-times me-1"></i>Reject
                                        </button>
                                    ` : ''}
                                    
                                    ${doc.status === 'rejected' ? `
                                        <button onclick="updateDocumentStatus(${doc.id}, ${userId}, 'approved')" class="btn btn-sm btn-success">
                                            <i class="fas fa-check me-1"></i>Approve
                                        </button>
                                    ` : ''}
                                    
                                    ${doc.status === 'approved' ? `
                                        <button onclick="updateDocumentStatus(${doc.id}, ${userId}, 'rejected')" class="btn btn-sm btn-warning">
                                            <i class="fas fa-undo me-1"></i>Revoke
                                        </button>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            document.getElementById('documents_content').innerHTML = html;
        }
        
        function updateDocumentStatus(docId, userId, status) {
            if (!confirm(`Are you sure you want to ${status} this document?`)) return;
            
            const formData = new FormData();
            formData.append('action', 'update_document_status');
            formData.append('document_id', docId);
            formData.append('status', status);
            formData.append('csrf_token', '<?php echo generate_csrf_token(); ?>');
            
            fetch('update-document-status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    viewDocuments(userId, document.getElementById('doc_user_name').textContent);
                    if (status === 'approved') {
                        alert('Document approved successfully!');
                        setTimeout(() => location.reload(), 1500);
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error updating document: ' + error.message);
            });
        }
        
        function rejectDocument(docId, userId) {
            const reason = prompt('Please provide a reason for rejection:');
            if (!reason) return;
            
            const formData = new FormData();
            formData.append('action', 'update_document_status');
            formData.append('document_id', docId);
            formData.append('status', 'rejected');
            formData.append('rejection_reason', reason);
            formData.append('csrf_token', '<?php echo generate_csrf_token(); ?>');
            
            fetch('update-document-status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    viewDocuments(userId, document.getElementById('doc_user_name').textContent);
                    alert('Document rejected.');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error rejecting document: ' + error.message);
            });
        }
    </script>
</body>
</html>
