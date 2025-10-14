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
        'rejection_reason' => "ALTER TABLE commission_payments ADD COLUMN rejection_reason TEXT"
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
        $payment_id = (int)$_POST['payment_id'];
        
        if ($_POST['action'] === 'verify') {
            $stmt = $db->prepare("
                UPDATE commission_payments 
                SET payment_status = 'verified',
                    verified_by = ?,
                    verified_at = NOW()
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
        }
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
        CONCAT(mentor.first_name, ' ', mentor.last_name) as mentor_name,
        mentor.email as mentor_email,
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
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; position: fixed; width: 250px; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; border-radius: 8px; margin: 4px 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
        .stat-card { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-value { font-size: 2rem; font-weight: 700; }
        .stat-label { color: #6c757d; font-size: 0.875rem; }
        .badge-pending { background-color: #ffc107; }
        .badge-submitted { background-color: #0dcaf0; }
        .badge-verified { background-color: #198754; }
        .badge-rejected { background-color: #dc3545; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } .sidebar { display: none; } }
    </style>
</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?>

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

            <!-- Statistics Cards -->
            <div class="row mb-4">
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
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-label">Rejected</div>
                        <div class="stat-value text-danger"><?php echo $total_rejected; ?></div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
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

            <!-- Payments Table -->
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
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($payment['mentor_name']); ?></strong>
                                                <div class="small text-muted"><?php echo htmlspecialchars($payment['mentor_email']); ?></div>
                                                <?php if (!empty($payment['mentor_gcash_number'])): ?>
                                                    <div class="small text-muted">GCash: <?php echo htmlspecialchars($payment['mentor_gcash_number']); ?></div>
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
                                                    <?php if (!empty($payment['payment_date'])): ?>
                                                        <div class="small text-muted"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $payment['payment_status']; ?>">
                                                    <?php echo ucfirst($payment['payment_status']); ?>
                                                </span>
                                                <?php if ($payment['payment_status'] === 'rejected' && $payment['rejection_reason']): ?>
                                                    <div class="small text-danger mt-1"><?php echo htmlspecialchars($payment['rejection_reason']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $status = $payment['payment_status'] ?? 'pending';
                                                $has_reference = !empty($payment['reference_number']);
                                                
                                                if ($has_reference && $status !== 'verified'): 
                                                ?>
                                                    <button class="btn btn-sm btn-success" onclick="verifyPayment(<?php echo $payment['id']; ?>)">
                                                        <i class="fas fa-check"></i> Verify
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="rejectPayment(<?php echo $payment['id']; ?>)">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                <?php elseif ($status === 'verified'): ?>
                                                    <span class="text-success small">
                                                        <i class="fas fa-check-circle"></i> Verified
                                                        <?php if ($payment['verifier_name']): ?>
                                                            by <?php echo htmlspecialchars($payment['verifier_name']); ?>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php elseif (!$has_reference): ?>
                                                    <span class="text-muted small">
                                                        <i class="fas fa-clock"></i> Awaiting mentor payment
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted small">-</span>
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

    <!-- Verify Modal -->
    <div class="modal fade" id="verifyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="verify">
                    <input type="hidden" name="payment_id" id="verifyPaymentId">
                    <div class="modal-header">
                        <h5 class="modal-title">Verify Commission Payment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to verify this commission payment?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Verify Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="payment_id" id="rejectPaymentId">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Commission Payment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Rejection Reason</label>
                            <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Enter reason for rejection..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function verifyPayment(paymentId) {
            document.getElementById('verifyPaymentId').value = paymentId;
            new bootstrap.Modal(document.getElementById('verifyModal')).show();
        }

        function rejectPayment(paymentId) {
            document.getElementById('rejectPaymentId').value = paymentId;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }
    </script>
</body>
</html>
