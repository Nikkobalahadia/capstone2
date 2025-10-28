<?php
require_once '../config/config.php';
require_once '../config/notification_helper.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user) {
    redirect('auth/login.php');
}

// Only mentors can access this page
if (!in_array($user['role'], ['mentor'])) {
    redirect('../dashboard.php');
}

$unread_notifications = get_unread_count($user['id']);

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
            is_overdue TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
}

try {
    $db->query("SELECT is_overdue FROM commission_payments LIMIT 1");
} catch (PDOException $e) {
    $db->exec("ALTER TABLE commission_payments ADD COLUMN is_overdue TINYINT(1) DEFAULT 0");
}

// Get system settings
$settings_stmt = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('admin_gcash_number', 'commission_percentage')");
$settings = [];
while ($row = $settings_stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$admin_gcash = $settings['admin_gcash_number'] ?? '09123456789';
$commission_percentage = $settings['commission_percentage'] ?? 10;

// Fix any NULL payment statuses
try {
    $db->exec("UPDATE commission_payments SET payment_status = 'pending' WHERE payment_status IS NULL OR payment_status = ''");
} catch (PDOException $e) {
    // Ignore errors
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
        $payment_check = $verify_stmt->fetch();
        
        if (!$payment_check) {
            $error = 'Invalid payment or unauthorized access.';
        } elseif ($_POST['action'] === 'submit_payment') {
            $gcash_number = sanitize_input($_POST['gcash_number']);
            $reference_number = sanitize_input($_POST['reference_number']);
            
            if (empty($gcash_number) || empty($reference_number)) {
                $error = 'Please provide your GCash number and reference number.';
            } else {
                try {
                    $update_stmt = $db->prepare("
                        UPDATE commission_payments 
                        SET payment_status = 'submitted',
                            mentor_gcash_number = ?,
                            reference_number = ?,
                            payment_date = NOW(),
                            updated_at = NOW()
                        WHERE id = ? AND mentor_id = ?
                    ");
                    $result = $update_stmt->execute([$gcash_number, $reference_number, $payment_id, $user['id']]);
                    
                    if ($result && $update_stmt->rowCount() > 0) {
                        $success = 'Payment submitted successfully! Admin will verify your payment shortly.';
                        
                        // Create notification for admin
                        try {
                            $notif_stmt = $db->prepare("
                                INSERT INTO notifications (user_id, title, message, type, link, created_at)
                                SELECT id, 'New Payment Submission', 
                                       CONCAT(?, ' has submitted a payment for verification'),
                                       'commission_payment',
                                       'admin/commission-management.php',
                                       NOW()
                                FROM users WHERE role = 'admin'
                            ");
                            $notif_stmt->execute([$user['first_name'] . ' ' . $user['last_name']]);
                        } catch (PDOException $e) {
                            // Notification creation failed, but payment update succeeded
                        }
                    } else {
                        $error = 'Failed to update payment. Please try again or contact support.';
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Get all payments for this mentor
$payments_stmt = $db->prepare("
    SELECT 
        cp.*,
        cp.is_overdue,
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
        cp.is_overdue DESC,
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

// Calculate totals
$total_pending = 0;
$total_submitted = 0;
$total_verified = 0;
$total_overdue = 0;

foreach ($payments as $payment) {
    $status = $payment['payment_status'] ?? 'pending';
    if ($payment['is_overdue'] && $status !== 'verified') {
        $total_overdue += $payment['commission_amount'];
    }
    if ($status === 'pending' || empty($status)) {
        $total_pending += $payment['commission_amount'];
    } elseif ($status === 'submitted') {
        $total_submitted += $payment['commission_amount'];
    } elseif ($status === 'verified') {
        $total_verified += $payment['commission_amount'];
    }
}
?>
<!DOCTYPE html>
<html lang="en"> <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Commission Payments - Study Buddy</title>

    <script>
        (function() {
            try {
                const theme = localStorage.getItem('theme');
                if (theme === 'dark') {
                    document.documentElement.classList.add('dark');
                } else if (theme === 'light') {
                    document.documentElement.classList.remove('dark');
                } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {
                console.error("Failed to load theme:", e);
            }
        })();
    </script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-color-hover: #1d4ed8;

            /* Light Mode */
            --text-primary: #1a1a1a;
            --text-secondary: #666;
            --border-color: #e5e5e5;
            --bg-primary: #fafafa;
            --bg-surface: #ffffff;
            --bg-hover: #f0f0f0;
            --bg-subtle: #f3f4f6;

            --shadow-lg: 0 10px 40px rgba(0,0,0,0.1);

            /* Status: Pending */
            --pending-bg: #fef3c7;
            --pending-text: #92400e;
            /* Status: Submitted */
            --submitted-bg: #dbeafe;
            --submitted-text: #1e40af;
            /* Status: Verified */
            --verified-bg: #d1fae5;
            --verified-text: #065f46;
            /* Status: Rejected / Overdue */
            --rejected-bg: #fee2e2;
            --rejected-text: #991b1b;
            --rejected-border: #fecaca;

            /* Alert: Success */
            --success-bg: #d1fae5;
            --success-text: #065f46;
            --success-border: #a7f3d0;
            /* Alert: Error */
            --error-bg: #fee2e2;
            --error-text: #991b1b;
            --error-border: #fecaca;

            /* Info Box */
            --info-bg: #f0fdf4;
            --info-border: #86efac;
            --info-text: #065f46;
            --info-icon: #10b981;
        }

        html.dark {
            --primary-color: #3b82f6;
            --primary-color-hover: #2563eb;

            /* Dark Mode */
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --border-color: #374151;
            --bg-primary: #111827;
            --bg-surface: #1f2937;
            --bg-hover: #374151;
            --bg-subtle: #374151;
            
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.3);

            /* Status: Pending */
            --pending-bg: #713f12;
            --pending-text: #fef3c7;
            /* Status: Submitted */
            --submitted-bg: #1e40af;
            --submitted-text: #dbeafe;
            /* Status: Verified */
            --verified-bg: #065f46;
            --verified-text: #d1fae5;
            /* Status: Rejected / Overdue */
            --rejected-bg: #991b1b;
            --rejected-text: #fee2e2;
            --rejected-border: #7f1d1d;
            
            /* Alert: Success */
            --success-bg: #064e3b;
            --success-text: #a7f3d0;
            --success-border: #065f46;
            /* Alert: Error */
            --error-bg: #450a0a;
            --error-text: #fecaca;
            --error-border: #991b1b;

            /* Info Box */
            --info-bg: #052e16;
            --info-border: #166534;
            --info-text: #a7f3d0;
            --info-icon: #34d399;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            overflow-x: hidden;
            width: 100%;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: background 0.2s, color 0.2s;
        }

        /* ===== HEADER & NAVIGATION ===== */
        .header {
            background: var(--bg-surface);
            border-bottom: 1px solid var(--border-color);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 60px;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            height: 100%;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        /* Hamburger Menu */
        .hamburger {
            display: none;
            flex-direction: column;
            cursor: pointer;
            gap: 5px;
            background: none;
            border: none;
            padding: 0.5rem;
            z-index: 1001;
        }

        .hamburger span {
            width: 25px;
            height: 3px;
            background-color: var(--text-primary);
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(8px, 8px);
        }

        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }

        /* Logo */
        .logo {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
            white-space: nowrap;
        }

        /* Navigation Links */
        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
            margin: 0;
            padding: 0;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: var(--primary-color);
        }

        /* Notification Bell */
        .notification-bell {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            cursor: pointer;
            border-radius: 8px;
            background: transparent;
            border: none;
            transition: background 0.2s;
            font-size: 1.1rem;
            color: var(--text-secondary);
        }

        .notification-bell:hover {
            background: var(--bg-hover);
            color: var(--primary-color);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 0.7rem;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
            border: 2px solid white;
        }

        .notification-dropdown {
            display: none;
            position: absolute;
            right: -10px;
            top: 100%;
            margin-top: 0.75rem;
            width: 380px;
            max-height: 450px;
            background: var(--bg-surface);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            overflow: hidden;
            flex-direction: column;
            border: 1px solid var(--border-color);
        }

        .notification-dropdown.show {
            display: flex;
        }

        .notification-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-list {
            max-height: 350px;
            overflow-y: auto;
        }

        .notification-item-dropdown {
            padding: 0.875rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background 0.15s;
            display: flex;
            gap: 0.75rem;
        }

        .notification-item-dropdown:hover {
            background: var(--bg-hover);
        }

        .notification-item-dropdown.unread {
            background: var(--bg-subtle);
        }

        html.dark .notification-item-dropdown.unread {
            background: rgba(59, 130, 246, 0.1);
        }
        
        html.dark .notification-item-dropdown:hover {
            background: #2a384c;
        }
        
        .notification-footer {
            padding: 0.75rem;
            text-align: center;
            border-top: 1px solid var(--border-color);
        }

        /* Profile Menu */
        .profile-menu {
            position: relative;
        }

        .profile-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #1e40af 100%);
            color: white;
            cursor: pointer;
            font-size: 1.1rem;
            border: none;
            transition: transform 0.2s, box-shadow 0.2s;
            overflow: hidden;
        }

        .profile-icon:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .profile-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 0.5rem;
            width: 240px;
            background: var(--bg-surface);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            border: 1px solid var(--border-color);
        }

        .profile-dropdown.show {
            display: block;
        }

        .profile-dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            text-align: center;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
        }

        .user-role {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .profile-dropdown-menu {
            padding: 0.5rem 0;
        }

        .profile-dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            width: 100%;
            text-align: left;
            font-size: 0.9rem;
            background: transparent;
        }

        .profile-dropdown-item:hover {
            background: var(--bg-hover);
            color: var(--primary-color);
        }

        .profile-dropdown-item.logout {
            color: #dc2626;
        }

        .profile-dropdown-item.logout:hover {
            background: var(--rejected-bg);
        }
        
        html.dark .profile-dropdown-item.logout:hover {
            background: #450a0a;
        }
        
        /* Main Content */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        main {
            padding: 2rem 0;
            margin-top: 60px;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header h1 i {
            color: var(--primary-color);
        }

        .page-subtitle {
            font-size: 0.95rem;
            color: var(--text-secondary);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-surface);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--text-primary);
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

        .stat-card.overdue .stat-value {
            color: #dc2626;
        }

        /* Card */
        .card {
            background: var(--bg-surface);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .card h2 {
            font-size: 1.25rem;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
        }

        /* Payment Item */
        .payment-item {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }

        .payment-item.overdue {
            background: var(--rejected-bg);
            border-color: var(--rejected-border);
        }
        
        html.dark .payment-item.overdue {
             background: #450a0a;
             border-color: #7f1d1d;
        }

        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
            gap: 1rem;
        }

        .payment-info h3 {
            font-size: 1rem;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .payment-info p {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .payment-amount {
            text-align: right;
            flex-shrink: 0;
        }

        .payment-amount .amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .payment-amount .commission {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .payment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .detail-item {
            font-size: 0.875rem;
        }

        .detail-label {
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }

        .detail-value {
            color: var(--text-primary);
            font-weight: 500;
            word-wrap: break-word;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-pending {
            background: var(--pending-bg);
            color: var(--pending-text);
        }

        .status-submitted {
            background: var(--submitted-bg);
            color: var(--submitted-text);
        }

        .status-verified {
            background: var(--verified-bg);
            color: var(--verified-text);
        }

        .status-rejected {
            background: var(--rejected-bg);
            color: var(--rejected-text);
        }

        .status-overdue {
            background: var(--rejected-bg);
            color: var(--rejected-text);
            font-weight: 700;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            min-height: 44px;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-color-hover);
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-outline {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }

        .payment-actions {
            display: flex;
            gap: 0.75rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .alert-error {
            background: var(--error-bg);
            color: var(--error-text);
            border: 1px solid var(--error-border);
        }

        .alert-success {
            background: var(--success-bg);
            color: var(--success-text);
            border: 1px solid var(--success-border);
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
            padding: 1rem;
        }
        
        html.dark .modal {
            background: rgba(0, 0, 0, 0.7);
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--bg-surface);
            border-radius: 12px;
            padding: 2rem;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid var(--border-color);
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
            background: var(--primary-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }

        .modal-header h3 {
            font-size: 1.25rem;
            color: var(--text-primary);
            flex: 1;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
        }

        .modal-close:hover {
            background: var(--bg-hover);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.875rem;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            background: transparent;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        html.dark .form-input:focus {
             box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        
        .form-input::placeholder {
            color: var(--text-secondary);
        }

        .gcash-number-display {
            background: var(--bg-subtle);
            border: 2px solid var(--primary-color);
            border-radius: 8px;
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .gcash-number-display .number {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
            word-break: break-all;
        }

        .copy-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .copy-btn:hover {
            background: var(--primary-color-hover);
        }

        .info-box {
            background: var(--info-bg);
            border: 1px solid var(--info-border);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.25rem;
        }

        .info-box p {
            font-size: 0.875rem;
            color: var(--info-text);
            display: flex;
            align-items: start;
            gap: 0.5rem;
            margin: 0;
        }

        .info-box p::before {
            content: "✓";
            color: var(--info-icon);
            font-weight: 700;
            flex-shrink: 0;
        }

        .modal-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .modal-actions .btn {
            flex: 1;
            padding: 0.875rem;
        }
        
        hr {
            border: none;
            border-top: 1px solid var(--border-color) !important;
        }

        /* ===== MOBILE RESPONSIVE ===== */
        @media (max-width: 768px) {
            .hamburger {
                display: flex;
            }

            .navbar {
                padding: 0.75rem 0.5rem;
            }

            .logo {
                font-size: 1.1rem;
            }

            .nav-links {
                display: none;
                position: fixed;
                top: 60px;
                left: 0;
                right: 0;
                background: var(--bg-surface);
                flex-direction: column;
                gap: 0;
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease;
                box-shadow: var(--shadow-lg);
                z-index: 999;
            }

            .nav-links.active {
                max-height: 500px;
                display: flex;
            }

            .nav-links a {
                padding: 1rem;
                border-bottom: 1px solid var(--border-color);
                display: block;
                text-align: left;
            }

            main {
                padding: 1rem 0;
            }

            .container {
                padding: 0 0.75rem;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .payment-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .payment-amount {
                text-align: left;
            }

            .payment-details {
                grid-template-columns: 1fr;
            }

            .payment-actions {
                flex-direction: column;
            }

            .payment-actions .btn {
                width: 100%;
            }

            .notification-dropdown {
                width: calc(100vw - 2rem);
                right: 0;
                left: 1rem;
            }

            .modal-content {
                padding: 1.5rem;
            }

            .gcash-number-display {
                flex-direction: column;
                align-items: stretch;
            }

            .gcash-number-display .number {
                font-size: 1rem;
                text-align: center;
            }

            .copy-btn {
                width: 100%;
            }

            .modal-actions {
                flex-direction: column;
            }

            input, select, textarea, button {
                font-size: 16px !important;
            }
        }

        @media (max-width: 480px) {
            .logo {
                font-size: 1rem;
            }

            .page-header h1 {
                font-size: 1.25rem;
            }

            .stat-value {
                font-size: 1.5rem;
            }

            .payment-amount .amount {
                font-size: 1.25rem;
            }

            .card {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="navbar">
            <button class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <a href="../dashboard.php" class="logo">
                <i class="fas fa-book-open"></i> Study Buddy
            </a>

            <ul class="nav-links" id="navLinks">
                <li><a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="../matches/index.php"><i class="fas fa-handshake"></i> Matches</a></li>
                <li><a href="../sessions/index.php"><i class="fas fa-calendar"></i> Sessions</a></li>
                <li><a href="../messages/index.php"><i class="fas fa-envelope"></i> Messages</a></li>
            </ul>

            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="position: relative;">
                    <button class="notification-bell" onclick="toggleNotifications(event)" title="Notifications">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_notifications > 0): ?>
                            <span class="notification-badge"><?php echo $unread_notifications; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h4><i class="fas fa-bell"></i> Notifications</h4>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <div style="text-align: center; padding: 1.5rem; color: var(--text-secondary);">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                        <div class="notification-footer">
                            <a href="../notifications/index.php"><i class="fas fa-arrow-right"></i> View All</a>
                        </div>
                    </div>
                </div>

                <div class="profile-menu">
                    <button class="profile-icon" onclick="toggleProfileMenu(event)">
                        <?php if (!empty($user['profile_picture']) && file_exists('../' . $user['profile_picture'])): ?>
                            <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </button>
                    <div class="profile-dropdown" id="profileDropdown">
                        <div class="profile-dropdown-header">
                            <p class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                            <p class="user-role"><?php echo ucfirst($user['role']); ?></p>
                        </div>
                        <div class="profile-dropdown-menu">
                            <a href="../profile/index.php" class="profile-dropdown-item">
                                <i class="fas fa-user-circle"></i>
                                <span>View Profile</span>
                            </a>
                            <a href="commission-payments.php" class="profile-dropdown-item">
                                <i class="fas fa-wallet"></i>
                                <span>Commissions</span>
                            </a>
                            <a href="../profile/settings.php" class="profile-dropdown-item">
                                <i class="fas fa-sliders-h"></i>
                                <span>Settings</span>
                            </a>
                            <button class="profile-dropdown-item" id="theme-toggle-btn" onclick="toggleTheme(event)">
                                <i class="fas fa-moon"></i>
                                <span>Dark Mode</span>
                            </button>
                            <hr style="margin: 0.5rem 0;">
                            <a href="../auth/logout.php" class="profile-dropdown-item logout">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="page-header">
                <h1><i class="fas fa-wallet"></i> Commission Payments</h1>
                <p class="page-subtitle">Manage your commission payments to the admin</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card overdue">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-label">Overdue Payments</div>
                    <div class="stat-value">₱<?php echo number_format($total_overdue, 2); ?></div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-label">Pending Payments</div>
                    <div class="stat-value">₱<?php echo number_format($total_pending, 2); ?></div>
                </div>
                <div class="stat-card submitted">
                    <div class="stat-icon">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div class="stat-label">Submitted (Awaiting Verification)</div>
                    <div class="stat-value">₱<?php echo number_format($total_submitted, 2); ?></div>
                </div>
                <div class="stat-card verified">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-label">Verified Payments</div>
                    <div class="stat-value">₱<?php echo number_format($total_verified, 2); ?></div>
                </div>
            </div>

            <div class="card">
                <h2>Payment History</h2>
                
                <?php if (empty($payments)): ?>
                    <p style="text-align: center; color: var(--text-secondary); padding: 2rem;">
                        <i class="fas fa-inbox" style="font-size: 3rem; color: var(--border-color); display: block; margin-bottom: 1rem;"></i>
                        No commission payments yet.
                    </p>
                <?php else: ?>
                    <?php foreach ($payments as $payment): ?>
                        <div class="payment-item <?php echo ($payment['is_overdue'] && $payment['payment_status'] !== 'verified') ? 'overdue' : ''; ?>">
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
                                        
                                        if ($payment['is_overdue'] && $status !== 'verified') {
                                            $status_class = 'status-overdue';
                                            $display_status = 'OVERDUE';
                                        } else {
                                            $status_class = 'status-' . strtolower($status);
                                            $display_status = ucfirst($status);
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $display_status; ?>
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

                                <?php if ($payment['mentor_gcash_number'] && in_array($payment['payment_status'], ['submitted', 'verified', 'rejected'])): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Your Submitted GCash</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($payment['mentor_gcash_number']); ?></div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($payment['payment_status'] === 'verified' && $payment['verified_at']): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Verified On</div>
                                        <div class="detail-value"><?php echo date('M d, Y', strtotime($payment['verified_at'])); ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($payment['payment_status'] === 'rejected' && $payment['rejection_reason']): ?>
                                    <div class="detail-item" style="grid-column: 1 / -1;">
                                        <div class="detail-label">Rejection Reason</div>
                                        <div class="detail-value" style="color: var(--rejected-text);"><?php echo htmlspecialchars($payment['rejection_reason']); ?></div>
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
                                        <i class="fas fa-credit-card"></i> Pay via GCash
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">₱</div>
                <h3>Pay via GCash</h3>
                <button class="modal-close" onclick="closePaymentModal()">×</button>
            </div>

            <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.875rem;">
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
                    <button class="copy-btn" onclick="copyGCashNumber()">
                        <i class="fas fa-copy"></i> Copy
                    </button>
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
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Submit Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let notificationDropdownOpen = false;
        let profileDropdownOpen = false;

        // Mobile Menu Toggle
        document.addEventListener("DOMContentLoaded", () => {
            const hamburger = document.querySelector(".hamburger");
            const navLinks = document.querySelector(".nav-links");
            
            if (hamburger) {
                hamburger.addEventListener("click", (e) => {
                    e.stopPropagation();
                    hamburger.classList.toggle("active");
                    navLinks.classList.toggle("active");
                });

                // Close menu when clicking on links
                const links = navLinks.querySelectorAll("a");
                links.forEach((link) => {
                    link.addEventListener("click", () => {
                        hamburger.classList.remove("active");
                        navLinks.classList.remove("active");
                    });
                });

                // Close menu when clicking outside
                document.addEventListener("click", (event) => {
                    if (hamburger && navLinks && !hamburger.contains(event.target) && !navLinks.contains(event.target)) {
                        hamburger.classList.remove("active");
                        navLinks.classList.remove("active");
                    }
                });
            }
            
            // Set initial theme toggle button state
            const isDarkMode = document.documentElement.classList.contains('dark');
            updateThemeUI(isDarkMode);
        });

        function toggleNotifications(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('notificationDropdown');
            notificationDropdownOpen = !notificationDropdownOpen;
            
            if (notificationDropdownOpen) {
                dropdown.classList.add('show');
                document.getElementById('profileDropdown').classList.remove('show');
                profileDropdownOpen = false;
                loadNotifications();
            } else {
                dropdown.classList.remove('show');
            }
        }

        function toggleProfileMenu(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('profileDropdown');
            profileDropdownOpen = !profileDropdownOpen;
            
            if (profileDropdownOpen) {
                dropdown.classList.add('show');
                document.getElementById('notificationDropdown').classList.remove('show');
                notificationDropdownOpen = false;
            } else {
                dropdown.classList.remove('show');
            }
        }

        function loadNotifications() {
            fetch('../api/notifications.php')
                .then(response => response.json())
                .then(data => {
                    const list = document.getElementById('notificationList');
                    
                    if (!data.notifications || data.notifications.length === 0) {
                        list.innerHTML = '<div style="text-align: center; padding: 1.5rem; color: var(--text-secondary);"><i class="fas fa-bell-slash"></i><p>No notifications</p></div>';
                        return;
                    }
                    
                    list.innerHTML = data.notifications.slice(0, 6).map(notif => `
                        <div class="notification-item-dropdown ${!notif.is_read ? 'unread' : ''}" 
                             onclick="handleNotificationClick(${notif.id}, '${notif.link || ''}')">
                            <i class="fas ${getNotificationIcon(notif.type)}" style="color: ${getNotificationColor(notif.type)};"></i>
                            <div>
                                <div style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.25rem;">${escapeHtml(notif.title)}</div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">${escapeHtml(notif.message)}</div>
                                <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">${timeAgo(notif.created_at)}</div>
                            </div>
                        </div>
                    `).join('');
                });
        }

        function handleNotificationClick(notificationId, link) {
            fetch('../api/notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'mark_read', notification_id: notificationId})
            }).then(() => {
                if (link) window.location.href = link;
                else loadNotifications();
            });
        }

        function getNotificationIcon(type) {
            const icons = {
                'session_scheduled': 'fa-calendar-check',
                'session_accepted': 'fa-check-circle',
                'session_rejected': 'fa-times-circle',
                'match_request': 'fa-handshake',
                'match_accepted': 'fa-user-check',
                'announcement': 'fa-megaphone',
                'commission_due': 'fa-file-invoice-dollar'
            };
            return icons[type] || 'fa-bell';
        }

        function getNotificationColor(type) {
            const colors = {
                'session_accepted': '#16a34a',
                'session_rejected': '#dc2626',
                'match_accepted': '#16a34a',
                'announcement': '#2563eb',
                'commission_due': '#d97706',
                'session_scheduled': '#2563eb',
                'match_request': '#2563eb'
            };
            return colors[type] || '#666';
        }

        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function timeAgo(dateString) {
            const date = new Date(dateString);
            const seconds = Math.floor((new Date() - date) / 1000);
            if (seconds < 60) return 'Just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
            if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
            return Math.floor(seconds / 604800) + 'w ago';
        }

        document.addEventListener('click', function() {
            if (notificationDropdownOpen) {
                document.getElementById('notificationDropdown').classList.remove('show');
                notificationDropdownOpen = false;
            }
            if (profileDropdownOpen) {
                document.getElementById('profileDropdown').classList.remove('show');
                profileDropdownOpen = false;
            }
        });

        setInterval(() => {
            if (notificationDropdownOpen) {
                loadNotifications();
            } else {
                fetch('../api/notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        const badge = document.querySelector('.notification-badge');
                        if (data.unread_count > 0) {
                            if (badge) {
                                badge.textContent = data.unread_count;
                            } else {
                                const bell = document.querySelector('.notification-bell');
                                bell.innerHTML += `<span class="notification-badge">${data.unread_count}</span>`;
                            }
                        } else if (badge) {
                            badge.remove();
                        }
                    });
            }
        }, 30000);

        // Payment Modal Functions
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
                const btn = event.target.closest('.copy-btn');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                }, 2000);
            });
        }

        // Close modal when clicking outside
        document.getElementById('paymentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePaymentModal();
            }
        });

        // ===== START: THEME TOGGLE SCRIPT =====
        const themeToggleBtn = document.getElementById('theme-toggle-btn');
        const themeIcon = themeToggleBtn.querySelector('i');
        const themeText = themeToggleBtn.querySelector('span');

        /**
         * Updates the theme toggle button's icon and text
         * @param {boolean} isDarkMode - Whether dark mode is active
         */
        function updateThemeUI(isDarkMode) {
            if (isDarkMode) {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
                themeText.textContent = 'Light Mode';
            } else {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
                themeText.textContent = 'Dark Mode';
            }
        }

        /**
         * Toggles the theme and saves the preference to localStorage
         * @param {Event} event - The click event
         */
        function toggleTheme(event) {
            event.stopPropagation(); // Stop it from closing the profile menu
            const isDarkMode = document.documentElement.classList.toggle('dark');
            
            try {
                if (isDarkMode) {
                    localStorage.setItem('theme', 'dark');
                } else {
                    localStorage.setItem('theme', 'light');
                }
            } catch (e) {
                console.error("Failed to save theme preference:", e);
            }
            
            updateThemeUI(isDarkMode);
        }
        // ===== END: THEME TOGGLE SCRIPT =====
    </script>
</body>
</html>