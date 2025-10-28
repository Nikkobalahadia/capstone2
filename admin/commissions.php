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

require_once '../config/commission_helper.php';
update_overdue_status($db);

try {
    $db->query("SELECT 1 FROM commission_payments LIMIT 1");
    
    try {
        // Check if session_payment_id column exists
        $db->query("SELECT session_payment_id FROM commission_payments LIMIT 1");
        
        // Column exists, drop the foreign key constraint and make it nullable
        try {
            $db->exec("ALTER TABLE commission_payments DROP FOREIGN KEY commission_payments_ibfk_1");
        } catch (PDOException $e) {
            // Constraint might not exist or have different name, ignore
        }
        
        // Make session_payment_id nullable
        $db->exec("ALTER TABLE commission_payments MODIFY session_payment_id INT NULL");
    } catch (PDOException $e) {
        // session_payment_id column doesn't exist, which is fine
    }
    
    // Table exists, check if session_id column exists
    try {
        $db->query("SELECT session_id FROM commission_payments LIMIT 1");
    } catch (PDOException $e) {
        // session_id column doesn't exist, add it
        $db->exec("ALTER TABLE commission_payments ADD COLUMN session_id INT NULL AFTER mentor_id");
        $db->exec("ALTER TABLE commission_payments ADD FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE");
    }
    
    // Check if other columns exist
    $columns_to_check = [
        'session_amount' => "ALTER TABLE commission_payments ADD COLUMN session_amount DECIMAL(10,2) NOT NULL DEFAULT 0",
        'commission_amount' => "ALTER TABLE commission_payments ADD COLUMN commission_amount DECIMAL(10,2) NOT NULL DEFAULT 0",
        'commission_percentage' => "ALTER TABLE commission_payments ADD COLUMN commission_percentage DECIMAL(5,2) DEFAULT 10.00",
        'payment_status' => "ALTER TABLE commission_payments ADD COLUMN payment_status ENUM('pending', 'submitted', 'verified', 'rejected') DEFAULT 'pending'",
        'mentor_gcash_number' => "ALTER TABLE commission_payments ADD COLUMN mentor_gcash_number VARCHAR(20)",
        'reference_number' => "ALTER TABLE commission_payments ADD COLUMN reference_number VARCHAR(100)",
        'payment_date' => "ALTER TABLE commission_payments ADD COLUMN payment_date DATETIME",
        'verified_by' => "ALTER TABLE commission_payments ADD COLUMN verified_by INT",
        'verified_at' => "ALTER TABLE commission_payments ADD COLUMN verified_at DATETIME",
        'rejection_reason' => "ALTER TABLE commission_payments ADD COLUMN rejection_reason TEXT",
        'is_overdue' => "ALTER TABLE commission_payments ADD COLUMN is_overdue BOOLEAN DEFAULT FALSE"
    ];
    
    foreach ($columns_to_check as $column => $alter_sql) {
        try {
            $db->query("SELECT $column FROM commission_payments LIMIT 1");
        } catch (PDOException $e) {
            $db->exec($alter_sql);
        }
    }
    
} catch (PDOException $e) {
    // Table doesn't exist, create it
    $db->exec("
        CREATE TABLE IF NOT EXISTS commission_payments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            mentor_id INT NOT NULL,
            session_id INT NULL,
            session_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            commission_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            commission_percentage DECIMAL(5,2) DEFAULT 10.00,
            payment_status ENUM('pending', 'submitted', 'verified', 'rejected') DEFAULT 'pending',
            is_overdue BOOLEAN DEFAULT FALSE,
            mentor_gcash_number VARCHAR(20),
            reference_number VARCHAR(100),
            payment_date DATETIME,
            verified_by INT,
            verified_at DATETIME,
            rejection_reason TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
}

// Handle commission verification/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token.';
    } else {
        $payment_id = (int)($_POST['payment_id'] ?? 0); // Use payment_id if available
        
        if ($_POST['action'] === 'verify') {
            $stmt = $db->prepare("
                UPDATE commission_payments 
                SET payment_status = 'verified',
                    verified_by = ?,
                    verified_at = NOW(),
                    is_overdue = 0
                WHERE id = ?
            ");
            $stmt->execute([$user['id'], $payment_id]);
            $success = 'Commission payment verified successfully.';
        } elseif ($_POST['action'] === 'reject') {
            $rejection_reason = sanitize_input($_POST['rejection_reason']);
            $stmt = $db->prepare("
                UPDATE commission_payments 
                SET payment_status = 'rejected',
                    rejection_reason = ?,
                    verified_by = ?,
                    verified_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$rejection_reason, $user['id'], $payment_id]);
            $success = 'Commission payment rejected.';
        
        // ==================================================
        // START: CODE FIX
        // ==================================================
        } elseif ($_POST['action'] === 'suspend_mentor') {
            $mentor_id = (int)$_POST['mentor_id'];
            $stmt = $db->prepare("UPDATE users SET account_status = 'suspended' WHERE id = ? AND role = 'mentor'");
            $stmt->execute([$mentor_id]);
            
            if ($stmt->rowCount() > 0) {
                $success = 'Mentor account suspended due to unpaid commissions.';
            } else {
                $error = 'Failed to suspend mentor. The user ID might be incorrect, or the user does not have the "mentor" role.';
            }

        } elseif ($_POST['action'] === 'unsuspend_mentor') {
            $mentor_id = (int)$_POST['mentor_id'];
            $stmt = $db->prepare("UPDATE users SET account_status = 'active' WHERE id = ? AND role = 'mentor'");
            $stmt->execute([$mentor_id]);

            if ($stmt->rowCount() > 0) {
                $success = 'Mentor account reactivated.';
            } else {
                $error = 'Failed to reactivate mentor. The user ID might be incorrect, or the user does not have the "mentor" role.';
            }
        }
        // ==================================================
        // END: CODE FIX
        // ==================================================
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where_clauses = [];
$params = [];

if ($status_filter !== 'all') {
    $where_clauses[] = "cp.payment_status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_clauses[] = "(CONCAT(mentor.first_name, ' ', mentor.last_name) LIKE ? OR cp.reference_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$query = "
    SELECT 
        cp.*,
        cp.is_overdue,
        CONCAT(mentor.first_name, ' ', mentor.last_name) as mentor_name,
        mentor.email as mentor_email,
        mentor.account_status as mentor_account_status,
        CONCAT(verifier.first_name, ' ', verifier.last_name) as verifier_name,
        s.session_date,
        s.start_time,
        s.end_time,
        CONCAT(student.first_name, ' ', student.last_name) as student_name,
        student.email as student_email
    FROM commission_payments cp
    JOIN users mentor ON cp.mentor_id = mentor.id
    LEFT JOIN users verifier ON cp.verified_by = verifier.id
    LEFT JOIN sessions s ON cp.session_id = s.id
    LEFT JOIN matches m ON s.match_id = m.id
    LEFT JOIN users student ON m.student_id = student.id
    $where_sql
    ORDER BY 
        cp.is_overdue DESC,
        CASE cp.payment_status
            WHEN 'submitted' THEN 1
            WHEN 'pending' THEN 2
            WHEN 'verified' THEN 3
            WHEN 'rejected' THEN 4
        END,
        cp.created_at DESC
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

$stats_query = "
    SELECT 
        COALESCE(payment_status, 'pending') as payment_status,
        COUNT(*) as count,
        SUM(commission_amount) as total
    FROM commission_payments
    GROUP BY COALESCE(payment_status, 'pending')
";
$stats_result = $db->query($stats_query)->fetchAll();
$stats = [];
$amounts = [];
foreach ($stats_result as $row) {
    $stats[$row['payment_status']] = $row['count'];
    $amounts[$row['payment_status']] = $row['total'];
}

$overdue_query = "
    SELECT 
        COUNT(*) as overdue_count,
        SUM(commission_amount) as overdue_amount
    FROM commission_payments
    WHERE is_overdue = 1 AND payment_status != 'verified'
";
$overdue_result = $db->query($overdue_query)->fetch(PDO::FETCH_ASSOC);
$total_overdue = $overdue_result['overdue_count'] ?? 0;
$amount_overdue = $overdue_result['overdue_amount'] ?? 0;

try {
    $db->exec("UPDATE commission_payments SET payment_status = 'pending' WHERE payment_status IS NULL OR payment_status = ''");
} catch (PDOException $e) {
    // Ignore errors
}

$total_pending = $stats['pending'] ?? 0;
$total_submitted = $stats['submitted'] ?? 0;
$total_verified = $stats['verified'] ?? 0;
$total_rejected = $stats['rejected'] ?? 0;

$amount_pending = $amounts['pending'] ?? 0;
$amount_submitted = $amounts['submitted'] ?? 0;
$amount_verified = $amounts['verified'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commission Management - Admin</title>
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
        
        /* Card Styles */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .border-left-primary { border-left: 4px solid #2563eb; }
        .border-left-success { border-left: 4px solid #10b981; }
        .border-left-warning { border-left: 4px solid #f59e0b; }
        .border-left-info { border-left: 4px solid #06b6d4; }
        
        /* Chart Container */
        .chart-container {
            position: relative;
            height: 250px;
        }
        
        /* Responsive Typography */
        @media (max-width: 576px) {
            h1.h3 {
                font-size: 1.5rem;
            }
            
            .h5 {
                font-size: 1.1rem;
            }
            
            .card-body {
                padding: 1rem;
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
        
        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(90deg, #6a7ee8 0%, #8765c5 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .welcome-banner-text h2 {
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .welcome-banner-text p {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }
        .welcome-banner-time {
            text-align: right;
            font-size: 0.9rem;
            flex-shrink: 0;
            margin-left: 1rem;
        }
        .welcome-banner-time .time-box {
            background: rgba(255,255,255,0.15);
            padding: 8px 12px;
            border-radius: 8px;
            display: block;
            width: 100%;
            min-width: 190px;
        }
        .welcome-banner-time .time-box:first-child {
            margin-bottom: 8px;
        }
        .welcome-banner-time i {
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }
        
        /* Responsive banner */
        @media (max-width: 768px) {
            .welcome-banner {
                flex-direction: column;
                padding: 1.5rem;
                text-align: center;
            }
            .welcome-banner-time {
                text-align: center;
                margin-top: 1.5rem;
                margin-left: 0;
                width: 100%;
            }
            .welcome-banner-time .time-box {
                 display: inline-block;
                 width: auto;
            }
        }
        
        /* Quick Actions */
        .quick-action-card {
            display: block;
            text-decoration: none;
            color: #333;
            background: #fff;
            border-radius: 10px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            border: 1px solid #e3e6f0;
            height: 100%;
        }
        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-color: #667eea;
        }
        .quick-action-card .icon-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }
        .quick-action-card h5 {
            margin-bottom: 0.25rem;
            font-weight: 600;
        }
        .quick-action-card p {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0;
        }
        .bg-primary-light { background-color: rgba(37, 99, 235, 0.1); color: #2563eb; }
        .bg-success-light { background-color: rgba(16, 185, 129, 0.1); color: #10b981; }
        .bg-warning-light { background-color: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .bg-info-light { background-color: rgba(6, 182, 212, 0.1); color: #06b6d4; }
        
        /* Commission Page Specific Styles */
        .stat-card { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-value { font-size: 2rem; font-weight: 700; }
        .stat-label { color: #6c757d; font-size: 0.875rem; }
        .badge-pending { background-color: #ffc107; }
        .badge-submitted { background-color: #0dcaf0; }
        .badge-verified { background-color: #198754; }
        .badge-rejected { background-color: #dc3545; }
        .badge-overdue { background-color: #dc3545; animation: pulse 2s infinite; }
        .badge-suspended { background-color: #6c757d; color: white; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
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
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'commissions.php' ? 'active' : ''; ?>" href="commissions.php">
                <i class="fas fa-money-bill-wave me-2"></i> Commission Payments
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>" href="analytics.php">
                <i class="fas fa-chart-bar me-2"></i> Advanced Analytics
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'referral-audit.php' ? 'active' : ''; ?>" href="referral-audit.php">
                <i class="fas fa-link me-2"></i> Referral Audit
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'activity-logs.php' ? 'active' : ''; ?>" href="activity-logs.php">
                <i class="fas fa-history me-2"></i> Activity Logs
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'financial-overview.php' ? 'active' : ''; ?>" href="financial-overview.php">
                <i class="fas fa-chart-pie me-2"></i> Financial Overview
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'matches.php' ? 'active' : ''; ?>" href="matches.php">
                <i class="fas fa-handshake me-2"></i> Matches
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sessions.php' ? 'active' : ''; ?>" href="sessions.php">
                <i class="fas fa-video me-2"></i> Sessions
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : ''; ?>" href="announcements.php">
                <i class="fas fa-bullhorn me-2"></i> Announcements
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                <i class="fas fa-cog me-2"></i> System Settings
            </a>
        </nav>
    </div>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Commission Management</h1>
                    <p class="text-muted">Verify and manage mentor commission payments</p>
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

            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-label">
                            <i class="fas fa-exclamation-circle text-danger"></i> Overdue
                        </div>
                        <div class="stat-value text-danger"><?php echo $total_overdue; ?></div>
                        <div class="text-muted small">₱<?php echo number_format($amount_overdue, 2); ?></div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-label">Pending</div>
                        <div class="stat-value text-warning"><?php echo $total_pending; ?></div>
                        <div class="text-muted small">₱<?php echo number_format($amount_pending, 2); ?></div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-label">Awaiting Verification</div>
                        <div class="stat-value text-info"><?php echo $total_submitted; ?></div>
                        <div class="text-muted small">₱<?php echo number_format($amount_submitted, 2); ?></div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-label">Verified</div>
                        <div class="stat-value text-success"><?php echo $total_verified; ?></div>
                        <div class="text-muted small">₱<?php echo number_format($amount_verified, 2); ?></div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="submitted" <?php echo $status_filter === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Search by mentor name or reference number" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Commission Payments</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($payments)): ?>
                        <p class="text-center text-muted py-4">No commission payments found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Mentor</th>
                                        <th>Student</th>
                                        <th>Session Date</th>
                                        <th>Amount</th>
                                        <th>Commission</th>
                                        <th>Reference</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr <?php echo $payment['is_overdue'] && $payment['payment_status'] !== 'verified' ? 'style="background-color: #fff5f5;"' : ''; ?>>
                                            <td>
                                                <strong><?php echo htmlspecialchars($payment['mentor_name']); ?></strong>
                                                <div class="small text-muted"><?php echo htmlspecialchars($payment['mentor_email']); ?></div>
                                                <?php if (!empty($payment['mentor_gcash_number'])): ?>
                                                    <div class="small text-muted">GCash: <?php echo htmlspecialchars($payment['mentor_gcash_number']); ?></div>
                                                <?php endif; ?>
                                                <?php if ($payment['mentor_account_status'] === 'suspended'): ?>
                                                    <span class="badge badge-suspended">Suspended</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo !empty($payment['student_name']) ? htmlspecialchars($payment['student_name']) : '<span class="text-muted">N/A</span>'; ?></td>
                                            <td>
                                                <?php if (!empty($payment['session_date'])): ?>
                                                    <?php echo date('M d, Y', strtotime($payment['session_date'])); ?>
                                                    <?php if (!empty($payment['start_time'])): ?>
                                                        <div class="small text-muted"><?php echo date('g:i A', strtotime($payment['start_time'])); ?></div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>₱<?php echo number_format($payment['session_amount'] ?? 0, 2); ?></td>
                                            <td>
                                                <strong>₱<?php echo number_format($payment['commission_amount'], 2); ?></strong>
                                                <div class="small text-muted"><?php echo $payment['commission_percentage']; ?>%</div>
                                            </td>
                                            <td>
                                                <?php if (!empty($payment['reference_number'])): ?>
                                                    <code><?php echo htmlspecialchars($payment['reference_number']); ?></code>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($payment['is_overdue'] && $payment['payment_status'] !== 'verified'): ?>
                                                    <span class="badge badge-overdue">
                                                        <i class="fas fa-exclamation-circle"></i> OVERDUE
                                                    </span>
                                                <?php elseif ($payment['payment_status'] === 'pending'): ?>
                                                    <span class="badge badge-pending">Pending</span>
                                                <?php elseif ($payment['payment_status'] === 'submitted'): ?>
                                                    <span class="badge badge-submitted">Submitted</span>
                                                <?php elseif ($payment['payment_status'] === 'verified'): ?>
                                                    <span class="badge badge-verified">Verified</span>
                                                <?php elseif ($payment['payment_status'] === 'rejected'): ?>
                                                    <span class="badge badge-rejected">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($payment['payment_status'] === 'pending' || $payment['payment_status'] === 'submitted'): ?>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <button type="submit" name="action" value="verify" class="btn btn-sm btn-success">
                                                            <i class="fas fa-check"></i> Verify
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if (($payment['payment_status'] === 'pending' || $payment['payment_status'] === 'rejected') && $payment['mentor_account_status'] !== 'suspended'): ?>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                        
                                                        <input type="hidden" name="mentor_id" value="<?php echo $payment['mentor_id']; ?>">
                                                        
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <button type="submit" name="action" value="suspend_mentor" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-ban"></i> Suspend Mentor
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($payment['mentor_account_status'] === 'suspended'): ?>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                        
                                                        <input type="hidden" name="mentor_id" value="<?php echo $payment['mentor_id']; ?>">

                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <button type="submit" name="action" value="unsuspend_mentor" class="btn btn-sm btn-info">
                                                            <i class="fas fa-unlock"></i> Reactivate Mentor
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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