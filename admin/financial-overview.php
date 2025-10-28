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

// ==================================================================
// START: LOGIC FOR "FINANCIAL OVERVIEW" TAB
// ==================================================================

// Get date range
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');

// Commission statistics
$commission_stats = $db->prepare("
    SELECT 
        COUNT(*) as total_commissions,
        SUM(CASE WHEN payment_status = 'pending' THEN commission_amount ELSE 0 END) as pending_amount,
        SUM(CASE WHEN payment_status = 'submitted' THEN commission_amount ELSE 0 END) as submitted_amount,
        SUM(CASE WHEN payment_status = 'verified' THEN commission_amount ELSE 0 END) as verified_amount,
        SUM(CASE WHEN payment_status = 'rejected' THEN commission_amount ELSE 0 END) as rejected_amount,
        SUM(session_amount) as total_session_revenue,
        SUM(commission_amount) as total_commission_revenue
    FROM commission_payments
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$commission_stats->execute([$date_from, $date_to]);
$stats = $commission_stats->fetch();

// Monthly revenue trend (Last 12 months)
$monthly_revenue = $db->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(commission_amount) as commission_revenue,
        SUM(session_amount) as session_revenue,
        COUNT(*) as transaction_count
    FROM commission_payments
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
")->fetchAll();

// Top earning mentors (for selected date range)
$top_mentors = $db->prepare("
    SELECT 
        u.id,
        CONCAT(u.first_name, ' ', u.last_name) as mentor_name,
        u.email,
        COUNT(cp.id) as total_sessions,
        SUM(cp.session_amount) as total_earned,
        SUM(cp.commission_amount) as total_commission_paid,
        SUM(CASE WHEN cp.payment_status = 'verified' THEN cp.commission_amount ELSE 0 END) as verified_commissions,
        SUM(CASE WHEN cp.payment_status = 'pending' THEN cp.commission_amount ELSE 0 END) as pending_commissions
    FROM users u
    JOIN commission_payments cp ON u.id = cp.mentor_id
    WHERE DATE(cp.created_at) BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY total_earned DESC
    LIMIT 10
");
$top_mentors->execute([$date_from, $date_to]);
$mentors = $top_mentors->fetchAll();

// Daily revenue (last 30 days)
$daily_revenue = $db->query("
    SELECT 
        DATE(created_at) as date,
        SUM(commission_amount) as commission_revenue,
        SUM(session_amount) as session_revenue
    FROM commission_payments
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll();

// Breakdown by payment status (All time)
$breakdown = $db->query("
    SELECT 
        payment_status,
        COUNT(*) as count,
        SUM(commission_amount) as total_amount
    FROM commission_payments
    GROUP BY payment_status
")->fetchAll();

// ==================================================================
// END: LOGIC FOR "FINANCIAL OVERVIEW" TAB
// ==================================================================


// ==================================================================
// START: LOGIC FOR "COMMISSION PAYMENT" TAB
// ==================================================================

$cp_error = '';
$cp_success = '';

require_once '../config/commission_helper.php';
update_overdue_status($db);

// Handle commission verification/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $cp_error = 'Invalid security token.';
    } else {
        $payment_id = (int)($_POST['payment_id'] ?? 0);
        
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
            $cp_success = 'Commission payment verified successfully.';
        } elseif ($_POST['action'] === 'reject') {
            $rejection_reason = sanitize_input($_POST['rejection_reason']);
            if (empty($rejection_reason)) {
                $cp_error = 'A rejection reason is required.';
            } else {
                $stmt = $db->prepare("
                    UPDATE commission_payments 
                    SET payment_status = 'rejected',
                        rejection_reason = ?,
                        verified_by = ?,
                        verified_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$rejection_reason, $user['id'], $payment_id]);
                $cp_success = 'Commission payment rejected.';
            }
        
        } elseif ($_POST['action'] === 'suspend_mentor') {
            $mentor_id = (int)$_POST['mentor_id'];
            $stmt = $db->prepare("UPDATE users SET account_status = 'suspended' WHERE id = ? AND role = 'mentor'");
            $stmt->execute([$mentor_id]);
            
            if ($stmt->rowCount() > 0) {
                $cp_success = 'Mentor account suspended due to unpaid commissions.';
            } else {
                $cp_error = 'Failed to suspend mentor. The user ID might be incorrect, or the user does not have the "mentor" role.';
            }

        } elseif ($_POST['action'] === 'unsuspend_mentor') {
            $mentor_id = (int)$_POST['mentor_id'];
            $stmt = $db->prepare("UPDATE users SET account_status = 'active' WHERE id = ? AND role = 'mentor'");
            $stmt->execute([$mentor_id]);

            if ($stmt->rowCount() > 0) {
                $cp_success = 'Mentor account reactivated.';
            } else {
                $cp_error = 'Failed to reactivate mentor. The user ID might be incorrect, or the user does not have the "mentor" role.';
            }
        }
    }
}

// Get filter parameters for commission table
$cp_status_filter = $_GET['cp_status'] ?? 'all';
$cp_search = $_GET['cp_search'] ?? '';

// Build query for commission table
$cp_where_clauses = [];
$cp_params = [];

if ($cp_status_filter !== 'all') {
    $cp_where_clauses[] = "cp.payment_status = ?";
    $cp_params[] = $cp_status_filter;
}

if (!empty($cp_search)) {
    $cp_where_clauses[] = "(CONCAT(mentor.first_name, ' ', mentor.last_name) LIKE ? OR cp.reference_number LIKE ?)";
    $search_param = "%$cp_search%";
    $cp_params[] = $search_param;
    $cp_params[] = $search_param;
}

$cp_where_sql = !empty($cp_where_clauses) ? 'WHERE ' . implode(' AND ', $cp_where_clauses) : '';

$cp_query = "
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
    $cp_where_sql
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

$cp_stmt = $db->prepare($cp_query);
$cp_stmt->execute($cp_params);
$payments = $cp_stmt->fetchAll();

// Stats for commission table
$cp_stats_query = "
    SELECT 
        COALESCE(payment_status, 'pending') as payment_status,
        COUNT(*) as count,
        SUM(commission_amount) as total
    FROM commission_payments
    GROUP BY COALESCE(payment_status, 'pending')
";
$cp_stats_result = $db->query($cp_stats_query)->fetchAll();
$cp_stats = [];
$cp_amounts = [];
foreach ($cp_stats_result as $row) {
    $cp_stats[$row['payment_status']] = $row['count'];
    $cp_amounts[$row['payment_status']] = $row['total'];
}

$cp_overdue_query = "
    SELECT 
        COUNT(*) as overdue_count,
        SUM(commission_amount) as overdue_amount
    FROM commission_payments
    WHERE is_overdue = 1 AND payment_status != 'verified'
";
$cp_overdue_result = $db->query($cp_overdue_query)->fetch(PDO::FETCH_ASSOC);
$cp_total_overdue = $cp_overdue_result['overdue_count'] ?? 0;
$cp_amount_overdue = $cp_overdue_result['overdue_amount'] ?? 0;

$cp_total_pending = $cp_stats['pending'] ?? 0;
$cp_total_submitted = $cp_stats['submitted'] ?? 0;
$cp_total_verified = $cp_stats['verified'] ?? 0;
$cp_total_rejected = $cp_stats['rejected'] ?? 0;

$cp_amount_pending = $cp_amounts['pending'] ?? 0;
$cp_amount_submitted = $cp_amounts['submitted'] ?? 0;
$cp_amount_verified = $cp_amounts['verified'] ?? 0;

// ==================================================================
// END: LOGIC FOR "COMMISSION PAYMENT" TAB
// ==================================================================

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Overview - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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
        
        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
        }

        /* Commission Page Specific Styles */
        .stat-card { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-value { font-size: 2rem; font-weight: 700; }
        .stat-label { color: #6c757d; font-size: 0.875rem; }
        .badge-pending { background-color: #ffc107; color: #000; }
        .badge-submitted { background-color: #0dcaf0; color: #000; }
        .badge-verified { background-color: #198754; }
        .badge-rejected { background-color: #dc3545; }
        .badge-overdue { background-color: #dc3545; animation: pulse 2s infinite; }
        .badge-suspended { background-color: #6c757d; color: white; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        
        /* Tab Styles */
        .nav-tabs .nav-link {
            font-weight: 600;
            color: #6c757d;
        }
        .nav-tabs .nav-link.active {
            color: #667eea;
            border-color: #667eea;
            border-bottom-width: 3px;
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
                <h1 class="h3 mb-0">Financials</h1>
            </div>

            <?php if ($cp_error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $cp_error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($cp_success): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            title: 'Success!',
                            text: '<?php echo $cp_success; ?>',
                            icon: 'success',
                            confirmButtonColor: '#3085d6'
                        });
                    });
                </script>
            <?php endif; ?>

            <ul class="nav nav-tabs mb-4" id="financialTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab" aria-controls="overview" aria-selected="true">
                        <i class="fas fa-chart-pie me-2"></i>Financial Overview
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="commissions-tab" data-bs-toggle="tab" data-bs-target="#commissions" type="button" role="tab" aria-controls="commissions" aria-selected="false">
                        <i class="fas fa-money-bill-wave me-2"></i>Commission Payment
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="financialTabsContent">
                
                <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
                    
                    <div class="card shadow mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label">From</label>
                                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">To</label>
                                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-filter me-2"></i> Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card shadow-sm h-100"><div class="card-body">
                                <div class="text-xs text-uppercase mb-1">Total Session Revenue</div>
                                <div class="h5 mb-0">₱<?php echo number_format($stats['total_session_revenue'], 2); ?></div>
                            </div></div>
                        </div>
                        <div class="col-md-3">
                            <div class="card shadow-sm h-100"><div class="card-body">
                                <div class="text-xs text-uppercase mb-1">Total Commission Revenue</div>
                                <div class="h5 mb-0 text-success">₱<?php echo number_format($stats['total_commission_revenue'], 2); ?></div>
                            </div></div>
                        </div>
                        <div class="col-md-3">
                            <div class="card shadow-sm h-100"><div class="card-body">
                                <div class="text-xs text-uppercase mb-1">Pending Commissions</div>
                                <div class="h5 mb-0 text-warning">₱<?php echo number_format($stats['pending_amount'], 2); ?></div>
                            </div></div>
                        </div>
                        <div class="col-md-3">
                            <div class="card shadow-sm h-100"><div class="card-body">
                                <div class="text-xs text-uppercase mb-1">Total Transactions</div>
                                <div class="h5 mb-0"><?php echo $stats['total_commissions']; ?></div>
                            </div></div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="card shadow h-100">
                                <div class="card-header">Daily Revenue (Last 30 Days)</div>
                                <div class="card-body"><div class="chart-container">
                                    <canvas id="dailyRevenueChart"></canvas>
                                </div></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card shadow h-100">
                                <div class="card-header">Payment Status Breakdown (All Time)</div>
                                <div class="card-body"><div class="chart-container">
                                    <canvas id="statusChart"></canvas>
                                </div></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card shadow">
                        <div class="card-header">Top 10 Mentors (<?php echo date('M j, Y', strtotime($date_from)); ?> - <?php echo date('M j, Y', strtotime($date_to)); ?>)</div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Mentor</th>
                                            <th>Total Sessions</th>
                                            <th>Total Earned</th>
                                            <th>Verified Commission</th>
                                            <th>Pending Commission</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($mentors as $mentor): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($mentor['mentor_name']); ?></strong>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($mentor['email']); ?></div>
                                                </td>
                                                <td><?php echo $mentor['total_sessions']; ?></td>
                                                <td>₱<?php echo number_format($mentor['total_earned'], 2); ?></td>
                                                <td class="text-success">₱<?php echo number_format($mentor['verified_commissions'], 2); ?></td>
                                                <td class="text-warning">₱<?php echo number_format($mentor['pending_commissions'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
                
                <div class="tab-pane fade" id="commissions" role="tabpanel" aria-labelledby="commissions-tab">
                    
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">
                                    <i class="fas fa-exclamation-circle text-danger"></i> Overdue
                                </div>
                                <div class="stat-value text-danger"><?php echo $cp_total_overdue; ?></div>
                                <div class="text-muted small">₱<?php echo number_format($cp_amount_overdue, 2); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Pending</div>
                                <div class="stat-value text-warning"><?php echo $cp_total_pending; ?></div>
                                <div class="text-muted small">₱<?php echo number_format($cp_amount_pending, 2); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Awaiting Verification</div>
                                <div class="stat-value text-info"><?php echo $cp_total_submitted; ?></div>
                                <div class="text-muted small">₱<?php echo number_format($cp_amount_submitted, 2); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Verified</div>
                                <div class="stat-value text-success"><?php echo $cp_total_verified; ?></div>
                                <div class="text-muted small">₱<?php echo number_format($cp_amount_verified, 2); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    <select name="cp_status" class="form-select">
                                        <option value="all" <?php echo $cp_status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                        <option value="pending" <?php echo $cp_status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="submitted" <?php echo $cp_status_filter === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                        <option value="verified" <?php echo $cp_status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                        <option value="rejected" <?php echo $cp_status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Search</label>
                                    <input type="text" name="cp_search" class="form-control" placeholder="Search by mentor name or reference number" value="<?php echo htmlspecialchars($cp_search); ?>">
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
                                    <table class="table table-hover align-middle">
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
                                                        <form method="POST" id="commission-form-<?php echo $payment['id']; ?>" class="d-inline">
                                                            <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                            <input type="hidden" name="mentor_id" value="<?php echo $payment['mentor_id']; ?>">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                            <input type="hidden" name="action" id="action-input-<?php echo $payment['id']; ?>">
                                                            <input type="hidden" name="rejection_reason" id="reason-input-<?php echo $payment['id']; ?>">
                                                        </form>
                                                        
                                                        <?php if ($payment['payment_status'] === 'pending' || $payment['payment_status'] === 'submitted'): ?>
                                                            <button class="btn btn-success btn-sm mx-1" type="button" onclick="confirmVerify(<?php echo $payment['id']; ?>)" title="Verify">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <button class="btn btn-danger btn-sm mx-1" type="button" onclick="confirmReject(<?php echo $payment['id']; ?>)" title="Reject">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (($payment['payment_status'] === 'pending' || $payment['payment_status'] === 'rejected') && $payment['mentor_account_status'] !== 'suspended'): ?>
                                                            <button class="btn btn-warning btn-sm mx-1" type="button" onclick="confirmSuspend(<?php echo $payment['id']; ?>)" title="Suspend Mentor">
                                                                <i class="fas fa-ban"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($payment['mentor_account_status'] === 'suspended'): ?>
                                                            <button class="btn btn-info btn-sm mx-1" type="button" onclick="confirmUnsuspend(<?php echo $payment['id']; ?>)" title="Reactivate Mentor">
                                                                <i class="fas fa-unlock"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php 
                                                            $hasActions = ($payment['payment_status'] === 'pending' || $payment['payment_status'] === 'submitted') ||
                                                                          (($payment['payment_status'] === 'pending' || $payment['payment_status'] === 'rejected') && $payment['mentor_account_status'] !== 'suspended') ||
                                                                          ($payment['mentor_account_status'] === 'suspended');
                                                        ?>
                                                        <?php if (!$hasActions): ?>
                                                            <span class="text-muted small">No actions</span>
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
            </div> </div>
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

        // --- SweetAlert Confirmation Functions for Commissions ---
        function getForm(id) {
            return document.getElementById('commission-form-' + id);
        }
        
        function setAction(id, action) {
            document.getElementById('action-input-' + id).value = action;
        }

        function confirmVerify(id) {
            Swal.fire({
                title: 'Verify Payment?',
                text: "Are you sure you want to verify this commission payment?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, verify it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    setAction(id, 'verify');
                    getForm(id).submit();
                }
            });
        }
    
        async function confirmReject(id) {
            const { value: reason } = await Swal.fire({
                title: 'Enter Rejection Reason',
                input: 'text',
                inputLabel: 'Reason',
                inputPlaceholder: 'e.g., Invalid reference number',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Reject Payment',
                cancelButtonColor: '#6c757d',
                inputValidator: (value) => {
                    if (!value) {
                        return 'You must provide a reason to reject!'
                    }
                }
            });
            
            if (reason) {
                document.getElementById('reason-input-' + id).value = reason;
                setAction(id, 'reject');
                getForm(id).submit();
            }
        }

        function confirmSuspend(id) {
            Swal.fire({
                title: 'Suspend Mentor?',
                text: "Are you sure you want to suspend this mentor for non-payment?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, suspend mentor!'
            }).then((result) => {
                if (result.isConfirmed) {
                    setAction(id, 'suspend_mentor');
                    getForm(id).submit();
                }
            });
        }

        function confirmUnsuspend(id) {
            Swal.fire({
                title: 'Reactivate Mentor?',
                text: "Are you sure you want to reactivate this mentor's account?",
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#0dcaf0',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, reactivate!'
            }).then((result) => {
                if (result.isConfirmed) {
                    setAction(id, 'unsuspend_mentor');
                    getForm(id).submit();
                }
            });
        }

        // --- Chart.js scripts from financial-overview.php ---
        // Daily Revenue Chart
        const dailyRevenueCtx = document.getElementById('dailyRevenueChart').getContext('2d');
        const dailyData = <?php echo json_encode($daily_revenue); ?>;
        
        new Chart(dailyRevenueCtx, {
            type: 'line',
            data: {
                labels: dailyData.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                datasets: [{
                    label: 'Session Revenue',
                    data: dailyData.map(d => d.session_revenue),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.3,
                    fill: true
                }, {
                    label: 'Commission Revenue',
                    data: dailyData.map(d => d.commission_revenue),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ₱' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Payment Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusData = <?php echo json_encode($breakdown); ?>;
        
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusData.map(d => d.payment_status.charAt(0).toUpperCase() + d.payment_status.slice(1)),
                datasets: [{
                    data: statusData.map(d => d.total_amount),
                    backgroundColor: ['#ffc107', '#0dcaf0', '#198754', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ₱' + context.parsed.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // --- Tab Persistency ---
        // Keep the current tab active on page reload (e.g., after filtering)
        document.addEventListener('DOMContentLoaded', function() {
            var activeTab = '<?php echo isset($_GET['cp_status']) || isset($_GET['cp_search']) ? 'commissions-tab' : 'overview-tab'; ?>';
            var someTabTriggerEl = document.getElementById(activeTab);
            if (someTabTriggerEl) {
                var tab = new bootstrap.Tab(someTabTriggerEl);
                tab.show();
            }

            // When filters are used, stay on the correct tab
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('cp_status') || urlParams.has('cp_search')) {
                new bootstrap.Tab(document.getElementById('commissions-tab')).show();
            } else if (urlParams.has('date_from') || urlParams.has('date_to')) {
                new bootstrap.Tab(document.getElementById('overview-tab')).show();
            }
        });
    </script>
</body>
</html>