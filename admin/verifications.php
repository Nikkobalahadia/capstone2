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

// Handle AJAX requests for verify mentor action
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
                case 'verify_mentor':
                    $stmt = $db->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
                    $stmt->execute([$user_id]);
                    
                    $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'admin_verify_mentor', ?, ?)");
                    $log_stmt->execute([$user['id'], json_encode(['verified_mentor_id' => $user_id]), $_SERVER['REMOTE_ADDR']]);
                    
                    $response['success'] = true;
                    $response['message'] = 'Mentor verified successfully.';
                    break;
            }
        } catch (Exception $e) {
            $response['message'] = 'Failed to perform action: ' . $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
    }
}

$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;

// Validate per_page
$allowed_per_page = [10, 25, 50, 100];
if (!in_array($per_page, $allowed_per_page)) {
    $per_page = 25;
}

$where_conditions = ["u.role = 'mentor'"];
$params = [];

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination info
$count_query = "
    SELECT COUNT(DISTINCT u.id) as total
    FROM users u
    LEFT JOIN user_verification_documents uvd ON u.id = uvd.user_id
    WHERE $where_clause
";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$count_result = $count_stmt->fetch();
$total_users = $count_result['total'];

// Get users with document counts
$offset = 0;

$query = "
    SELECT u.*,
           COUNT(DISTINCT uvd.id) as total_documents,
           SUM(CASE WHEN uvd.status = 'pending' THEN 1 ELSE 0 END) as pending_documents,
           SUM(CASE WHEN uvd.status = 'approved' THEN 1 ELSE 0 END) as approved_documents,
           SUM(CASE WHEN uvd.status = 'rejected' THEN 1 ELSE 0 END) as rejected_documents,
           MAX(uvd.created_at) as last_upload_date
    FROM users u
    LEFT JOIN user_verification_documents uvd ON u.id = uvd.user_id
    WHERE $where_clause
    GROUP BY u.id
    HAVING total_documents > 0 OR '' = ''
    ORDER BY 
        CASE WHEN u.is_verified = 0 AND SUM(CASE WHEN uvd.status = 'pending' THEN 1 ELSE 0 END) > 0 THEN 0 ELSE 1 END,
        last_upload_date DESC
    LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;

$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

$stats_query = "
    SELECT 
        COUNT(DISTINCT CASE WHEN u.is_verified = 0 AND uvd.status = 'pending' THEN u.id END) as pending_users,
        COUNT(DISTINCT CASE WHEN u.is_verified = 1 THEN u.id END) as verified_users,
        COUNT(DISTINCT CASE WHEN u.is_verified = 0 THEN u.id END) as unverified_users,
        COUNT(CASE WHEN uvd.status = 'pending' THEN 1 END) as pending_docs,
        COUNT(CASE WHEN uvd.status = 'approved' THEN 1 END) as approved_docs,
        COUNT(CASE WHEN uvd.status = 'rejected' THEN 1 END) as rejected_docs
    FROM users u
    LEFT JOIN user_verification_documents uvd ON u.id = uvd.user_id
    WHERE u.role = 'mentor'
";
$stats = $db->query($stats_query)->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentor Verification - Study Buddy Admin</title>
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
        
        .priority-badge { animation: pulse 2s infinite; }
        
        .stat-card { 
            border-left: 4px solid; 
            transition: transform 0.2s; 
        }
        
        .stat-card:hover { 
            transform: translateY(-2px); 
        }
        
        .stat-card.warning { 
            border-left-color: #ffc107; 
        }
        
        .stat-card.success { 
            border-left-color: #28a745; 
        }
        
        .stat-card.danger { 
            border-left-color: #dc3545; 
        }
        
        .stat-card.info { 
            border-left-color: #17a2b8; 
        }
        
        .table-row-unverified { 
            background-color: #fff3cd; 
        }
        
        .action-buttons { 
            display: flex; 
            gap: 0.5rem; 
            flex-wrap: wrap; 
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
    </style>
</head>
<body>
    <?php include '../includes/admin-header.php'; ?>
    
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="mobile-overlay" id="mobileOverlay"></div>
    
    <div class="sidebar" id="sidebar">
        <div class="p-4">
            <h4 class="text-white mb-0">Admin Panel</h4>
            <small class="text-white-50">Study Mentorship Platform</small>
        </div>
        <nav class="nav flex-column px-2">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                <i class="fas fa-users me-2"></i> User Management
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'verifications.php' ? 'active' : ''; ?>" href="verifications.php">
                <i class="fas fa-user-check me-2"></i> Mentor Verification
            </a>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'matches.php' ? 'active' : ''; ?>" href="matches.php">
                <i class="fas fa-handshake me-2"></i> Matches
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sessions.php' ? 'active' : ''; ?>" href="sessions.php">
                <i class="fas fa-video me-2"></i> Sessions
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>" href="analytics.php">
                <i class="fas fa-chart-bar me-2"></i> Advanced Analytics
            </a>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'system-health.php' ? 'active' : ''; ?>" href="system-health.php">
                <i class="fas fa-heartbeat me-2"></i> System Health
            </a>

            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'financial-overview.php' ? 'active' : ''; ?>" href="financial-overview.php">
                <i class="fas fa-chart-pie me-2"></i> Financial Overview
            </a>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'referral-audit.php' ? 'active' : ''; ?>" href="referral-audit.php">
                <i class="fas fa-link me-2"></i> Referral Audit
            </a>


            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                <i class="fas fa-cog me-2"></i> System Settings
            </a>
        </nav>
    </div>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 text-gray-800">Mentor Verification</h1>
                    <div class="d-flex align-items-center gap-2">
                        <p class="text-muted mb-0">Review and approve verification documents from mentors.</p>
                    </div>
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

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stat-card warning shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-muted small mb-1">
                                        <i class="fas fa-hourglass-start me-1"></i>Pending Review
                                    </p>
                                    <h4 class="mb-0"><?php echo $stats['pending_users']; ?></h4>
                                    <small class="text-muted"><?php echo $stats['pending_docs']; ?> documents</small>
                                </div>
                                <i class="fas fa-clock fa-2x text-warning opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stat-card success shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-muted small mb-1">
                                        <i class="fas fa-check-circle me-1"></i>Verified
                                    </p>
                                    <h4 class="mb-0"><?php echo $stats['verified_users']; ?></h4>
                                    <small class="text-muted"><?php echo $stats['approved_docs']; ?> approved docs</small>
                                </div>
                                <i class="fas fa-check-circle fa-2x text-success opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stat-card danger shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-muted small mb-1">
                                        <i class="fas fa-times-circle me-1"></i>Rejected
                                    </p>
                                    <h4 class="mb-0"><?php echo $stats['rejected_docs']; ?></h4>
                                    <small class="text-muted">Requires resubmission</small>
                                </div>
                                <i class="fas fa-times-circle fa-2x text-danger opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stat-card info shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="text-muted small mb-1">
                                        <i class="fas fa-user-shield me-1"></i>Unverified
                                    </p>
                                    <h4 class="mb-0"><?php echo $stats['unverified_users']; ?></h4>
                                    <small class="text-muted">No documents submitted</small>
                                </div>
                                <i class="fas fa-user-shield fa-2x text-info opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Removed filter card - simplified search only -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label for="search" class="form-label">Search Mentors</label>
                            <input type="text" id="search" name="search" class="form-control" 
                                   placeholder="Name or email"
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="per_page" class="form-label">Per Page</label>
                            <select id="per_page" name="per_page" class="form-select" onchange="this.form.submit()">
                                <option value="10" <?php echo $per_page === 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $per_page === 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $per_page === 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $per_page === 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">Search</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-file-alt me-2"></i>Mentor Verification Requests (<span id="requestCount"><?php echo count($users); ?></span> of <?php echo $total_users; ?>)
                    </h6>
                    <small class="text-muted">Last updated: <span id="lastUpdate">--:--:--</span></small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Mentor</th>
                                    <th>Documents</th>
                                    <th>Status</th>
                                    <th>Last Upload</th>
                                    <th style="width: 200px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="verificationsTableBody">
                                <?php foreach ($users as $u): ?>
                                    <tr class="<?php echo ($u['pending_documents'] > 0 && !$u['is_verified']) ? 'table-row-unverified' : ''; ?>">
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
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick="viewDocuments(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>')">
                                                    <i class="fas fa-file-alt me-1"></i> Review
                                                </button>
                                                <?php if (!$u['is_verified']): ?>
                                                    <button class="btn btn-sm btn-success" 
                                                            onclick="verifyMentor(<?php echo $u['id']; ?>)">
                                                        <i class="fas fa-check me-1"></i> Verify
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($users)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-file-alt fa-3x mb-3"></i>
                                <p>No mentor verification requests found.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Documents Modal -->
    <div class="modal fade" id="documentsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-alt me-2"></i>Verification Documents - <span id="doc_user_name"></span>
                    </h5>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';
        
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
        
        function updateLastUpdate() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit' 
            });
            document.getElementById('lastUpdate').textContent = timeString;
        }
        
        function verifyMentor(userId) {
            Swal.fire({
                title: 'Verify Mentor?',
                text: 'Are you sure you want to verify this mentor?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, verify'
            }).then((result) => {
                if (!result.isConfirmed) return;
                
                document.getElementById('loadingOverlay').classList.add('show');
                
                const formData = new FormData();
                formData.append('csrf_token', CSRF_TOKEN);
                formData.append('action', 'verify_mentor');
                formData.append('user_id', userId);
                
                fetch('verifications.php?ajax=true', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: data.message,
                            icon: 'success',
                            confirmButtonColor: '#667eea'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message || 'Failed to verify mentor',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        title: 'Error',
                        text: 'Error verifying mentor: ' + error.message,
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                })
                .finally(() => {
                    document.getElementById('loadingOverlay').classList.remove('show');
                });
            });
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
                            '<div class="alert alert-warning"><i class="fas fa-info-circle me-2"></i>' + data.message + '</div>';
                    }
                })
                .catch(error => {
                    document.getElementById('documents_loading').style.display = 'none';
                    document.getElementById('documents_content').style.display = 'block';
                    document.getElementById('documents_content').innerHTML = 
                        '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error loading documents: ' + error.message + '</div>';
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
                'other': 'Other Document'
            };
            
            let html = '<div class="row">';
            
            documents.forEach(doc => {
                const statusColor = doc.status === 'approved' ? 'success' : (doc.status === 'rejected' ? 'danger' : 'warning');
                const statusIcon = doc.status === 'approved' ? 'check-circle' : (doc.status === 'rejected' ? 'times-circle' : 'clock');
                
                html += `
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 border">
                            <div class="card-header d-flex justify-content-between align-items-center bg-light">
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
                                        <p class="mb-0 text-muted">PDF Document</p>
                                    </div>`
                                }
                                
                                ${doc.description ? `<p class="text-muted small mb-2"><strong>Description:</strong> ${doc.description}</p>` : ''}
                                <p class="text-muted small mb-2"><strong>Uploaded:</strong> ${new Date(doc.created_at).toLocaleDateString()}</p>
                                ${doc.reviewed_at ? `<p class="text-muted small mb-2"><strong>Reviewed:</strong> ${new Date(doc.reviewed_at).toLocaleDateString()}</p>` : ''}
                                ${doc.rejection_reason ? `<div class="alert alert-danger small mb-3"><i class="fas fa-exclamation-triangle me-2"></i><strong>Rejection Reason:</strong> ${doc.rejection_reason}</div>` : ''}
                                
                                <div class="action-buttons mt-3">
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
                                        <button onclick="updateDocumentStatus(${doc.id}, ${userId}, 'pending')" class="btn btn-sm btn-warning">
                                            <i class="fas fa-undo me-1"></i>Revert
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
            Swal.fire({
                title: 'Confirm Action',
                text: `Are you sure you want to ${status} this document?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: status === 'approved' ? '#28a745' : '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, ' + status
            }).then((result) => {
                if (!result.isConfirmed) return;
                
                const formData = new FormData();
                formData.append('action', 'update_document_status');
                formData.append('document_id', docId);
                formData.append('status', status);
                formData.append('csrf_token', CSRF_TOKEN);
                
                fetch('update-document-status.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: 'Document ' + status + ' successfully.',
                            icon: 'success',
                            confirmButtonColor: '#667eea'
                        }).then(() => {
                            viewDocuments(userId, document.getElementById('doc_user_name').textContent);
                            setTimeout(() => location.reload(), 1500);
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message || 'Failed to update document',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        title: 'Error',
                        text: 'Error updating document: ' + error.message,
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                });
            });
        }
        
        function rejectDocument(docId, userId) {
            Swal.fire({
                title: 'Reject Document',
                input: 'textarea',
                inputLabel: 'Rejection Reason',
                inputPlaceholder: 'Please provide a reason for rejection...',
                inputAttributes: {
                    'aria-label': 'Rejection reason'
                },
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, reject',
                cancelButtonText: 'Cancel',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Please provide a rejection reason'
                    }
                }
            }).then((result) => {
                if (!result.isConfirmed) return;
                
                const formData = new FormData();
                formData.append('action', 'update_document_status');
                formData.append('document_id', docId);
                formData.append('status', 'rejected');
                formData.append('rejection_reason', result.value);
                formData.append('csrf_token', CSRF_TOKEN);
                
                fetch('update-document-status.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'Rejected!',
                            text: 'Document rejected successfully.',
                            icon: 'success',
                            confirmButtonColor: '#667eea'
                        }).then(() => {
                            viewDocuments(userId, document.getElementById('doc_user_name').textContent);
                            setTimeout(() => location.reload(), 1500);
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message || 'Failed to reject document',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        title: 'Error',
                        text: 'Error rejecting document: ' + error.message,
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                });
            });
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateLastUpdate();
            // Auto-update timestamp every second
            setInterval(updateLastUpdate, 1000);
        });
    </script>
    
</body>
</html>
