<?php
require_once '../config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user) {
    redirect('auth/login.php');
}

// Only mentors and peers can access this page
if (!in_array($user['role'], ['mentor'])) {
    redirect('../dashboard.php');
}

$db = getDB();

try {
    $db->query("SELECT 1 FROM commission_payments LIMIT 1");
} catch (PDOException $e) {
    // Table doesn't exist, create it
    $db->exec("
        CREATE TABLE IF NOT EXISTS commission_payments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            mentor_id INT NOT NULL,
            session_id INT NOT NULL,
            session_amount DECIMAL(10,2) NOT NULL,
            commission_amount DECIMAL(10,2) NOT NULL,
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

// Get system settings
$settings_stmt = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('admin_gcash_number', 'commission_percentage')");
$settings = [];
while ($row = $settings_stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$admin_gcash = $settings['admin_gcash_number'] ?? '09123456789';
$commission_percentage = $settings['commission_percentage'] ?? 10;

$payments_stmt = $db->prepare("
    SELECT 
        cp.*,
        s.session_date,
        s.start_time,
        s.end_time,
        m.subject,
        student.first_name as student_first_name,
        student.last_name as student_last_name,
        CONCAT(student.first_name, ' ', student.last_name) as student_name
    FROM commission_payments cp
    LEFT JOIN sessions s ON cp.session_id = s.id
    LEFT JOIN matches m ON s.match_id = m.id
    LEFT JOIN users student ON m.student_id = student.id
    WHERE cp.mentor_id = ?
    ORDER BY 
        CASE COALESCE(cp.payment_status, 'pending')
            WHEN 'pending' THEN 1
            WHEN 'submitted' THEN 2
            WHEN 'verified' THEN 3
            WHEN 'rejected' THEN 4
        END,
        cp.created_at DESC
");
$payments_stmt->execute([$user['id']]);
$payments = $payments_stmt->fetchAll();

try {
    $db->exec("UPDATE commission_payments SET payment_status = 'pending' WHERE payment_status IS NULL OR payment_status = '' AND mentor_id = " . $user['id']);
} catch (PDOException $e) {
    // Ignore errors
}

$total_pending = 0;
$total_submitted = 0;
$total_verified = 0;

foreach ($payments as $payment) {
    $status = $payment['payment_status'] ?? 'pending';
    if ($status === 'pending' || empty($status)) {
        $total_pending += $payment['commission_amount'];
    } elseif ($status === 'submitted') {
        $total_submitted += $payment['commission_amount'];
    } elseif ($status === 'verified') {
        $total_verified += $payment['commission_amount'];
    }
}

// Handle payment submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token.';
    } else {
        $payment_id = (int)$_POST['payment_id'];
        
        // Verify payment belongs to user
        $verify_stmt = $db->prepare("SELECT * FROM commission_payments WHERE id = ? AND mentor_id = ?");
        $verify_stmt->execute([$payment_id, $user['id']]);
        $payment = $verify_stmt->fetch();
        
        if (!$payment) {
            $error = 'Invalid payment.';
        } elseif ($_POST['action'] === 'submit_payment') {
            $gcash_number = sanitize_input($_POST['gcash_number']);
            $reference_number = sanitize_input($_POST['reference_number']);
            
            if (empty($gcash_number) || empty($reference_number)) {
                $error = 'Please provide your GCash number and reference number.';
            } else {
                $update_stmt = $db->prepare("
                    UPDATE commission_payments 
                    SET payment_status = 'submitted',
                        mentor_gcash_number = ?,
                        reference_number = ?,
                        payment_date = NOW()
                    WHERE id = ?
                ");
                $update_stmt->execute([$gcash_number, $reference_number, $payment_id]);
                
                $success = 'Payment submitted successfully! Admin will verify your payment shortly.';
                
                // Refresh payments
                $payments_stmt->execute([$user['id']]);
                $payments = $payments_stmt->fetchAll();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commission Payments - StudyConnect</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
            min-height: 100vh;
        }

        .header {
            background: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #7c3aed;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #4b5563;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: #7c3aed;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: #7c3aed;
            color: white;
        }

        .btn-primary:hover {
            background: #6d28d9;
        }

        .btn-outline {
            background: white;
            color: #7c3aed;
            border: 2px solid #7c3aed;
        }

        .btn-outline:hover {
            background: #7c3aed;
            color: white;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        main {
            padding: 2rem 0;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2rem;
            color: #111827;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #6b7280;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.875rem;
            font-weight: 700;
            color: #111827;
        }

        .stat-card.pending .stat-value {
            color: #f59e0b;
        }

        .stat-card.submitted .stat-value {
            color: #3b82f6;
        }

        .stat-card.verified .stat-value {
            color: #10b981;
        }

        .payments-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .payments-card h2 {
            font-size: 1.25rem;
            color: #111827;
            margin-bottom: 1.5rem;
        }

        .payment-item {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }

        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .payment-info h3 {
            font-size: 1rem;
            color: #111827;
            margin-bottom: 0.25rem;
        }

        .payment-info p {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .payment-amount {
            text-align: right;
        }

        .payment-amount .amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: #7c3aed;
        }

        .payment-amount .commission {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .payment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        .detail-item {
            font-size: 0.875rem;
        }

        .detail-label {
            color: #6b7280;
            margin-bottom: 0.25rem;
        }

        .detail-value {
            color: #111827;
            font-weight: 500;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-submitted {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-verified {
            background: #d1fae5;
            color: #065f46;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .payment-actions {
            display: flex;
            gap: 0.75rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .modal-icon {
            width: 40px;
            height: 40px;
            background: #7c3aed;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }

        .modal-header h3 {
            font-size: 1.25rem;
            color: #111827;
        }

        .modal-close {
            margin-left: auto;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            color: #111827;
            font-family: 'Inter', sans-serif;
        }

        .form-input:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }

        .gcash-number-display {
            background: #f3f4f6;
            border: 2px solid #7c3aed;
            border-radius: 8px;
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }

        .gcash-number-display .number {
            font-size: 1.25rem;
            font-weight: 600;
            color: #7c3aed;
        }

        .copy-btn {
            background: #7c3aed;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .copy-btn:hover {
            background: #6d28d9;
        }

        .info-box {
            background: #f0fdf4;
            border: 1px solid #86efac;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.25rem;
        }

        .info-box p {
            font-size: 0.875rem;
            color: #065f46;
            display: flex;
            align-items: start;
            gap: 0.5rem;
        }

        .info-box p::before {
            content: "✓";
            color: #10b981;
            font-weight: 700;
        }

        .modal-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .modal-actions .btn {
            flex: 1;
            padding: 0.875rem;
            justify-content: center;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="../dashboard.php" class="logo">StudyConnect</a>
                <ul class="nav-links">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <li><a href="../matches/index.php">Matches</a></li>
                    <li><a href="../sessions/index.php">Sessions</a></li>
                    <li><a href="../messages/index.php">Messages</a></li>
                    <li><a href="../auth/logout.php" class="btn btn-outline">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="page-header">
                <h1>Commission Payments</h1>
                <p>Manage your commission payments to the admin</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card pending">
                    <div class="stat-label">Pending Payments</div>
                    <div class="stat-value">₱<?php echo number_format($total_pending, 2); ?></div>
                </div>
                <div class="stat-card submitted">
                    <div class="stat-label">Submitted (Awaiting Verification)</div>
                    <div class="stat-value">₱<?php echo number_format($total_submitted, 2); ?></div>
                </div>
                <div class="stat-card verified">
                    <div class="stat-label">Verified Payments</div>
                    <div class="stat-value">₱<?php echo number_format($total_verified, 2); ?></div>
                </div>
            </div>

            <div class="payments-card">
                <h2>Payment History</h2>
                
                <?php if (empty($payments)): ?>
                    <p style="text-align: center; color: #6b7280; padding: 2rem;">No commission payments yet.</p>
                <?php else: ?>
                    <?php foreach ($payments as $payment): ?>
                        <div class="payment-item">
                            <div class="payment-header">
                                <div class="payment-info">
                                    <h3><?php echo htmlspecialchars($payment['subject']); ?></h3>
                                    <p>with <?php echo htmlspecialchars($payment['student_name']); ?> • <?php echo date('M d, Y', strtotime($payment['session_date'])); ?> at <?php echo date('g:i A', strtotime($payment['start_time'])); ?></p>
                                </div>
                                <div class="payment-amount">
                                    <div class="amount">₱<?php echo number_format($payment['commission_amount'], 2); ?></div>
                                    <div class="commission"><?php echo $payment['commission_percentage']; ?>% of ₱<?php echo number_format($payment['session_amount'], 2); ?></div>
                                </div>
                            </div>

                            <div class="payment-details">
                                <div class="detail-item">
                                    <div class="detail-label">Status</div>
                                    <div class="detail-value">
                                        <?php 
                                        $status = $payment['payment_status'] ?? 'pending';
                                        if (empty($status)) $status = 'pending';
                                        $status_class = 'status-' . strtolower($status);
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if ($payment['payment_date']): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Payment Date</div>
                                        <div class="detail-value"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($payment['reference_number']): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Reference Number</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($payment['reference_number']); ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($payment['payment_status'] === 'rejected' && $payment['rejection_reason']): ?>
                                    <div class="detail-item" style="grid-column: 1 / -1;">
                                        <div class="detail-label">Rejection Reason</div>
                                        <div class="detail-value" style="color: #dc2626;"><?php echo htmlspecialchars($payment['rejection_reason']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php 
                            $status = $payment['payment_status'] ?? 'pending';
                            $can_pay = in_array($status, ['pending', 'rejected', '', null]) || empty($status);
                            if ($can_pay): 
                            ?>
                                <div class="payment-actions">
                                    <button onclick="openPaymentModal(<?php echo $payment['id']; ?>, <?php echo $payment['commission_amount']; ?>)" class="btn btn-success">
                                        Pay via GCash
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">₱</div>
                <h3>Pay via GCash</h3>
                <button class="modal-close" onclick="closePaymentModal()">×</button>
            </div>

            <p style="color: #6b7280; margin-bottom: 1.5rem; font-size: 0.875rem;">
                Send your commission payment to the admin's GCash number
            </p>

            <div class="form-group">
                <div class="form-label">Amount to Pay</div>
                <div style="font-size: 2rem; font-weight: 700; color: #10b981; margin-bottom: 1rem;" id="modalAmount">
                    ₱200
                </div>
            </div>

            <div class="form-group">
                <div class="form-label">Admin GCash Number</div>
                <div class="gcash-number-display">
                    <span class="number"><?php echo htmlspecialchars($admin_gcash); ?></span>
                    <button class="copy-btn" onclick="copyGCashNumber()">Copy</button>
                </div>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="submit_payment">
                <input type="hidden" name="payment_id" id="modalPaymentId">

                <div class="form-group">
                    <label class="form-label">Your GCash Number</label>
                    <input type="text" name="gcash_number" class="form-input" placeholder="09XX XXX XXXX" required pattern="[0-9]{11}" maxlength="11">
                </div>

                <div class="form-group">
                    <label class="form-label">GCash Reference Number</label>
                    <input type="text" name="reference_number" class="form-input" placeholder="Enter reference number" required>
                </div>

                <div class="info-box">
                    <p>After sending payment via GCash, enter your details and reference number for verification.</p>
                </div>

                <div class="modal-actions">
                    <button type="button" onclick="closePaymentModal()" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-success">Submit Payment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openPaymentModal(paymentId, amount) {
            document.getElementById('modalPaymentId').value = paymentId;
            document.getElementById('modalAmount').textContent = '₱' + amount.toFixed(2);
            document.getElementById('paymentModal').classList.add('active');
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').classList.remove('active');
        }

        function copyGCashNumber() {
            const number = '<?php echo $admin_gcash; ?>';
            navigator.clipboard.writeText(number).then(() => {
                alert('GCash number copied to clipboard!');
            });
        }

        // Close modal when clicking outside
        document.getElementById('paymentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePaymentModal();
            }
        });
    </script>
</body>
</html>
