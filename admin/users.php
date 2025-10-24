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

// Handle AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $response = ['success' => false, 'message' => ''];
        
        if (!verify_csrf_token($_POST['csrf_token'])) {
            $response['message'] = 'Invalid security token.';
            echo json_encode($response);
            exit;
        }
        
        $action = $_POST['action'];
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        
        try {
            switch ($action) {
                case 'create':
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
                    
                    $response['success'] = true;
                    $response['message'] = 'User created successfully.';
                    break;
                    
                case 'update':
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
                    
                    $response['success'] = true;
                    $response['message'] = 'User updated successfully.';
                    break;
                    
                case 'verify':
                    $stmt = $db->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
                    $stmt->execute([$user_id]);
                    
                    $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'admin_verify_user', ?, ?)");
                    $log_stmt->execute([$user['id'], json_encode(['verified_user_id' => $user_id]), $_SERVER['REMOTE_ADDR']]);
                    
                    $response['success'] = true;
                    $response['message'] = 'User verified successfully.';
                    break;
                    
                case 'activate':
                    $stmt = $db->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
                    $stmt->execute([$user_id]);
                    
                    $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'admin_activate_user', ?, ?)");
                    $log_stmt->execute([$user['id'], json_encode(['activated_user_id' => $user_id]), $_SERVER['REMOTE_ADDR']]);
                    
                    $response['success'] = true;
                    $response['message'] = 'User activated successfully.';
                    break;
                    
                case 'deactivate':
                    $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$user_id]);
                    
                    $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'admin_deactivate_user', ?, ?)");
                    $log_stmt->execute([$user['id'], json_encode(['deactivated_user_id' => $user_id]), $_SERVER['REMOTE_ADDR']]);
                    
                    $response['success'] = true;
                    $response['message'] = 'User deactivated successfully.';
                    break;
                    
                case 'delete':
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    
                    $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'admin_delete_user', ?, ?)");
                    $log_stmt->execute([$user['id'], json_encode(['deleted_user_id' => $user_id]), $_SERVER['REMOTE_ADDR']]);
                    
                    $response['success'] = true;
                    $response['message'] = 'User deleted successfully.';
                    break;
            }
        } catch (Exception $e) {
            $response['message'] = 'Failed to perform action: ' . $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
    }
    
    // Handle GET request for fetching users
    $sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
    $sort_dir = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'ASC' : 'DESC';
    $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;
    
    $allowed_per_page = [10, 25, 50, 100];
    if (!in_array($per_page, $allowed_per_page)) {
        $per_page = 25;
    }
    
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
        LIMIT $per_page
    ";
    
    $stmt = $db->prepare($users_query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'users' => $users]);
    exit;
}

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
        
        .main-content { 
            margin-left: 250px; 
            padding: 20px; 
            margin-top: 60px;
            transition: margin-left 0.3s ease-in-out;
            width: calc(100% - 250px);
        }
        
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
            
            .action-buttons {
                flex-direction: column;
                gap: 0.25rem !important;
            }
            
            .action-buttons form,
            .action-buttons button {
                width: 100%;
            }
            
            .table-responsive {
                font-size: 0.85rem;
            }
        }
        
        @media (min-width: 769px) {
            .mobile-menu-toggle {
                display: none !important;
            }
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
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
        
        .sortable-header { 
            cursor: pointer; 
            user-select: none; 
        }
        
        .sortable-header:hover { 
            background-color: #e9ecef; 
        }
        
        .action-buttons { 
            display: flex; 
            gap: 0.5rem; 
            flex-wrap: wrap; 
        }
        
        .action-buttons .btn { 
            padding: 0.375rem 0.65rem; 
            font-size: 0.85rem; 
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .loading-overlay.show {
            display: flex;
        }
        
        .spinner-border-custom {
            width: 3rem;
            height: 3rem;
            border-width: 0.3rem;
        }
        
        .real-time-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            background: #10b981;
            color: white;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .real-time-badge .pulse {
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        @media (max-width: 576px) {
            h1.h3 {
                font-size: 1.5rem;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            .btn {
                font-size: 0.875rem;
                padding: 0.5rem 0.75rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin-header.php'; ?>
    
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="mobile-overlay" id="mobileOverlay"></div>
    
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border spinner-border-custom text-light" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    
    <div class="sidebar" id="sidebar">
        <div class="p-4">
            <h4 class="text-white mb-0">Admin Panel</h4>
            <small class="text-white-50">Study Mentorship Platform</small>
        </div>
        <nav class="nav flex-column px-2">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            <a class="nav-link active" href="users.php">
                <i class="fas fa-users me-2"></i> User Management
            </a>
            <a class="nav-link" href="verifications.php">
                <i class="fas fa-user-check me-2"></i> Mentor Verification
            </a>
            <a class="nav-link" href="commissions.php">
                <i class="fas fa-money-bill-wave me-2"></i> Commission Payments
            </a>
            <a class="nav-link" href="analytics.php">
                <i class="fas fa-chart-bar me-2"></i> Advanced Analytics
            </a>
            <a class="nav-link" href="referral-audit.php">
                <i class="fas fa-link me-2"></i> Referral Audit
            </a>
            <a class="nav-link" href="activity-logs.php">
                <i class="fas fa-history me-2"></i> Activity Logs
            </a>
            <a class="nav-link" href="financial-overview.php">
                <i class="fas fa-chart-pie me-2"></i> Financial Overview
            </a>
            <a class="nav-link" href="matches.php">
                <i class="fas fa-handshake me-2"></i> Matches
            </a>
            <a class="nav-link" href="sessions.php">
                <i class="fas fa-video me-2"></i> Sessions
            </a>
            <a class="nav-link" href="announcements.php">
                <i class="fas fa-bullhorn me-2"></i> Announcements
            </a>
            <a class="nav-link" href="settings.php">
                <i class="fas fa-cog me-2"></i> System Settings
            </a>
        </nav>
    </div>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 text-gray-800">User Management</h1>
                    <div class="d-flex align-items-center gap-2">
                        <p class="text-muted mb-0">Manage user accounts, verification, and activity.</p>
                        <span class="real-time-badge">
                            <span class="pulse"></span>
                            Real-time
                        </span>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="fas fa-plus me-2"></i> Add User
                    </button>
                    <a href="?export=csv" class="btn btn-success">
                        <i class="fas fa-download me-2"></i> Export CSV
                    </a>
                </div>
            </div>

            <div id="alertContainer"></div>

            <div class="card shadow mb-4">
                <div class="card-body">
                    <form id="filterForm" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" id="search" name="search" class="form-control" 
                                   placeholder="Name, email, username">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="role" class="form-label">Role</label>
                            <select id="role" name="role" class="form-select">
                                <option value="">All Roles</option>
                                <option value="student">Students</option>
                                <option value="mentor">Mentors</option>
                                <option value="peer">Peers</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="verified">Verified</option>
                                <option value="unverified">Unverified</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="per_page" class="form-label">Per Page</label>
                            <select id="per_page" name="per_page" class="form-select">
                                <option value="10">10</option>
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <button type="button" onclick="clearFilters()" class="btn btn-secondary w-100">Clear</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Users (<span id="userCount">0</span>)</h6>
                    <small class="text-muted">Last updated: <span id="lastUpdate">--:--:--</span></small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="sortable-header" data-sort="first_name">
                                        User <i class="fas fa-sort text-muted"></i>
                                    </th>
                                    <th class="sortable-header" data-sort="role">
                                        Role <i class="fas fa-sort text-muted"></i>
                                    </th>
                                    <th>Status</th>
                                    <th>Activity</th>
                                    <th>Rating</th>
                                    <th class="sortable-header" data-sort="created_at">
                                        Joined <i class="fas fa-sort text-muted"></i>
                                    </th>
                                    <th style="min-width: 200px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-3 text-muted">Loading users...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="createUserForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
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
                <form id="editUserForm">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';
        let currentSort = 'created_at';
        let currentDir = 'desc';
        let autoRefreshInterval;
        
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
        
        // Load users
        function loadUsers(showLoading = true) {
            if (showLoading) {
                document.getElementById('loadingOverlay').classList.add('show');
            }
            
            const formData = new FormData(document.getElementById('filterForm'));
            const params = new URLSearchParams(formData);
            params.append('ajax', 'true');
            params.append('sort', currentSort);
            params.append('dir', currentDir);
            
            fetch('users.php?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderUsers(data.users);
                        updateLastUpdate();
                    }
                })
                .catch(error => {
                    console.error('Error loading users:', error);
                    showAlert('Failed to load users', 'danger');
                })
                .finally(() => {
                    document.getElementById('loadingOverlay').classList.remove('show');
                });
        }
        
        // Render users table
        function renderUsers(users) {
            const tbody = document.getElementById('usersTableBody');
            document.getElementById('userCount').textContent = users.length;
            
            if (users.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fas fa-users fa-3x mb-3"></i>
                            <p>No users found matching your criteria.</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = users.map(user => {
                const fullName = `${user.first_name} ${user.last_name}`;
                const initials = user.first_name.charAt(0) + user.last_name.charAt(0);
                const profilePic = user.profile_picture ? 
                    `<img src="../${user.profile_picture}" alt="${fullName}" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">` :
                    `<div style="width: 40px; height: 40px; background: #667eea; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.875rem;">${initials.toUpperCase()}</div>`;
                
                const roleBadge = user.role === 'student' ? 'bg-primary' : 
                                 (user.role === 'mentor' ? 'bg-success' : 'bg-info');
                
                const verifiedStatus = user.is_verified ? 
                    '<span class="text-success"><i class="fas fa-check-circle"></i> Verified</span>' :
                    '<span class="text-warning"><i class="fas fa-exclamation-circle"></i> Unverified</span>';
                
                const activeStatus = user.is_active ?
                    '<span class="text-success"><i class="fas fa-circle"></i> Active</span>' :
                    '<span class="text-danger"><i class="fas fa-circle"></i> Inactive</span>';
                
                const totalMatches = parseInt(user.matches_as_student) + parseInt(user.matches_as_mentor);
                
                const rating = user.avg_rating ? 
                    `<div class="small"><i class="fas fa-star text-warning"></i> ${parseFloat(user.avg_rating).toFixed(1)}/5</div>
                     <div class="small text-muted">(${user.rating_count})</div>` :
                    '<span class="text-muted small">No ratings</span>';
                
                const joinDate = new Date(user.created_at).toLocaleDateString('en-US', { 
                    month: 'short', day: 'numeric', year: 'numeric' 
                });
                
                return `
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                ${profilePic}
                                <div>
                                    <div class="fw-bold">${fullName}</div>
                                    <div class="small text-muted">${user.email}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge ${roleBadge}">${user.role.charAt(0).toUpperCase() + user.role.slice(1)}</span>
                        </td>
                        <td>
                            <div class="small">${verifiedStatus}</div>
                            <div class="small">${activeStatus}</div>
                        </td>
                        <td>
                            <div class="small">${totalMatches} matches</div>
                            <div class="small text-muted">${user.completed_sessions} sessions</div>
                        </td>
                        <td>${rating}</td>
                        <td><div class="small">${joinDate}</div></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-outline-primary" onclick='editUser(${JSON.stringify(user)})' title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                ${!user.is_verified ? `
                                    <button class="btn btn-sm btn-outline-success" onclick="performAction('verify', ${user.id})" title="Verify">
                                        <i class="fas fa-check"></i>
                                    </button>
                                ` : ''}
                                ${user.is_active ? `
                                    <button class="btn btn-sm btn-outline-warning" onclick="confirmAction('deactivate', ${user.id})" title="Deactivate">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                ` : `
                                    <button class="btn btn-sm btn-outline-success" onclick="performAction('activate', ${user.id})" title="Activate">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                `}
                                <button class="btn btn-sm btn-outline-danger" onclick="confirmAction('delete', ${user.id})" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }
        
        // Perform action
        function performAction(action, userId) {
            document.getElementById('loadingOverlay').classList.add('show');
            
            const formData = new FormData();
            formData.append('csrf_token', CSRF_TOKEN);
            formData.append('action', action);
            formData.append('user_id', userId);
            
            fetch('users.php?ajax=true', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    loadUsers(false);
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Failed to perform action', 'danger');
            })
            .finally(() => {
                document.getElementById('loadingOverlay').classList.remove('show');
            });
        }
        
        // Confirm action
        function confirmAction(action, userId) {
            const messages = {
                deactivate: {
                    title: 'Deactivate this user?',
                    text: 'This user will be unable to log in until reactivated.',
                    icon: 'warning',
                    confirmButtonColor: '#f59e0b',
                    confirmButtonText: 'Yes, deactivate'
                },
                delete: {
                    title: 'Are you sure?',
                    text: 'This action cannot be undone.',
                    icon: 'error',
                    confirmButtonColor: '#dc2626',
                    confirmButtonText: 'Yes, delete'
                }
            };
            
            const config = messages[action];
            
            Swal.fire({
                title: config.title,
                text: config.text,
                icon: config.icon,
                showCancelButton: true,
                confirmButtonColor: config.confirmButtonColor,
                cancelButtonColor: '#6b7280',
                confirmButtonText: config.confirmButtonText,
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    performAction(action, userId);
                }
            });
        }
        
        // Edit user
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_first_name').value = user.first_name;
            document.getElementById('edit_last_name').value = user.last_name;
            document.getElementById('edit_role').value = user.role;
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }
        
        // Show alert
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            alertContainer.appendChild(alert);
            
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
        
        // Update last update time
        function updateLastUpdate() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit' 
            });
            document.getElementById('lastUpdate').textContent = timeString;
        }
        
        // Clear filters
        function clearFilters() {
            document.getElementById('filterForm').reset();
            loadUsers();
        }
        
        // Handle create user form
        document.getElementById('createUserForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('csrf_token', CSRF_TOKEN);
            formData.append('action', 'create');
            
            document.getElementById('loadingOverlay').classList.add('show');
            
            fetch('users.php?ajax=true', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('createUserModal')).hide();
                    this.reset();
                    loadUsers(false);
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Failed to create user', 'danger');
            })
            .finally(() => {
                document.getElementById('loadingOverlay').classList.remove('show');
            });
        });
        
        // Handle edit user form
        document.getElementById('editUserForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('csrf_token', CSRF_TOKEN);
            formData.append('action', 'update');
            
            document.getElementById('loadingOverlay').classList.add('show');
            
            fetch('users.php?ajax=true', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
                    loadUsers(false);
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Failed to update user', 'danger');
            })
            .finally(() => {
                document.getElementById('loadingOverlay').classList.remove('show');
            });
        });
        
        // Handle filter changes
        document.getElementById('filterForm').addEventListener('change', function() {
            loadUsers();
        });
        
        // Handle search with debounce
        let searchTimeout;
        document.getElementById('search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadUsers();
            }, 500);
        });
        
        // Handle sortable headers
        document.querySelectorAll('.sortable-header').forEach(header => {
            header.addEventListener('click', function() {
                const sortBy = this.dataset.sort;
                
                if (currentSort === sortBy) {
                    currentDir = currentDir === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSort = sortBy;
                    currentDir = 'desc';
                }
                
                // Update icons
                document.querySelectorAll('.sortable-header i').forEach(icon => {
                    icon.className = 'fas fa-sort text-muted';
                });
                
                const icon = this.querySelector('i');
                icon.className = currentDir === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
                
                loadUsers();
            });
        });
        
        // Auto-refresh every 30 seconds
        function startAutoRefresh() {
            autoRefreshInterval = setInterval(() => {
                loadUsers(false);
            }, 30000);
        }
        
        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadUsers();
            startAutoRefresh();
        });
        
        // Stop auto-refresh when page is hidden
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopAutoRefresh();
            } else {
                startAutoRefresh();
                loadUsers(false);
            }
        });
    </script>
</body>
</html>