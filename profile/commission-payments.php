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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Commission Payments - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --text-primary: #1a1a1a;
            --text-secondary: #666;
            --border-color: #e5e5e5;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.1);
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
            background: #fafafa;
            color: #1a1a1a;
        }

        /* ===== HEADER & NAVIGATION ===== */
        .header {
            background: white;
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
            background: #f0f0f0;
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
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            overflow: hidden;
            flex-direction: column;
        }

        .notification-dropdown.show {
            display: flex;
        }

        .notification-header {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
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
            border-bottom: 1px solid #f5f5f5;
            cursor: pointer;
            transition: background 0.15s;
            display: flex;
            gap: 0.75rem;
        }

        .notification-item-dropdown:hover {
            background: #fafafa;
        }

        .notification-item-dropdown.unread {
            background: #f0f7ff;
        }

        .notification-footer {
            padding: 0.75rem;
            text-align: center;
            border-top: 1px solid #f0f0f0;
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
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
        }

        .profile-dropdown.show {
            display: block;
        }

        .profile-dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
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
            color: #999;
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
            background: #f5f5f5;
            color: var(--primary-color);
        }

        .profile-dropdown-item.logout {
            color: #dc2626;
        }

        .profile-dropdown-item.logout:hover {
            background: #fee2e2;
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
            background: white;
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

        /* Card */
        .card {
            background: white;
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
            background: #1d4ed8;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-outline {
            background: white;
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
            padding: 1rem;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 500px;
            width: 100%;
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
            background: #f0f0f0;
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
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.875rem;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .gcash-number-display {
            background: #f3f4f6;
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
            background: #1d4ed8;
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
            margin: 0;
        }

        .info-box p::before {
            content: "✓";
            color: #10b981;
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
                background: white;
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
    <!-- Header Navigation -->
    <header class="header">
        <div class="navbar">
            <!-- Mobile Hamburger -->
            <button class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <!-- Logo -->
            <a href="../dashboard.php" class="logo">
                <i class="fas fa-book-open"></i> StudyConnect
            </a>

            <!-- Desktop Navigation -->
            <ul class="nav-links" id="navLinks">
                <li><a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="../matches/index.php"><i class="fas fa-handshake"></i> Matches</a></li>
                <li><a href="../sessions/index.php"><i class="fas fa-calendar"></i> Sessions</a></li>
                <li><a href="../messages/index.php"><i class="fas fa-envelope"></i> Messages</a></li>
            </ul>

            <!-- Right Icons -->
            <div style="display: flex; align-items: center; gap: 1rem;">
                <!-- Notifications -->
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
                            <div style="text-align: center; padding: 1.5rem; color: #999;">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                        <div class="notification-footer">
                            <a href="../notifications/index.php"><i class="fas fa-arrow-right"></i> View All</a>
                        </div>
                    </div>
                </div>

                <!-- Profile Menu -->
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
                            <hr style="margin: 0.5rem 0; border: none; border-top: 1px solid #f0f0f0;">
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
                        <i class="fas fa-inbox" style="font-size: 3rem; color: #e5e5e5; display: block; margin-bottom: 1rem;"></i>
                        No commission payments yet.
                    </p>
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

    <!-- Payment Modal -->
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
                        list.innerHTML = '<div style="text-align: center; padding: 1.5rem; color: #999;"><i class="fas fa-bell-slash"></i><p>No notifications</p></div>';
                        return;
                    }
                    
                    list.innerHTML = data.notifications.slice(0, 6).map(notif => `
                        <div class="notification-item-dropdown ${!notif.is_read ? 'unread' : ''}" 
                             onclick="handleNotificationClick(${notif.id}, '${notif.link || ''}')">
                            <i class="fas ${getNotificationIcon(notif.type)}" style="color: ${getNotificationColor(notif.type)};"></i>
                            <div>
                                <div style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.25rem;">${escapeHtml(notif.title)}</div>
                                <div style="font-size: 0.8rem; color: #666;">${escapeHtml(notif.message)}</div>
                                <div style="font-size: 0.75rem; color: #999; margin-top: 0.25rem;">${timeAgo(notif.created_at)}</div>
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
    </script>
</body>
</html>