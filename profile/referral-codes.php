<?php
require_once '../config/config.php';
require_once '../config/notification_helper.php';

// Check if user is logged in and is a verified mentor or peer
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user || !in_array($user['role'], ['mentor', 'peer']) || !$user['is_verified']) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

// Handle referral code generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_code'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $max_uses = 1;
        $expires_days = 3; 

        $db = getDB();

        $monthly_limit = 5;
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));

        $count_stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM referral_codes 
            WHERE created_by = ? 
            AND created_at >= ?
        ");
        $count_stmt->execute([$user['id'], $thirty_days_ago]);
        $current_count = $count_stmt->fetchColumn();

        if ($current_count >= $monthly_limit) {
            $error = "Generation limit reached: You can only generate a maximum of {$monthly_limit} codes every 30 days.";
        } else {
            do {
                $prefix = strtoupper($user['role']);
                $code = $prefix . strtoupper(substr(uniqid(), -6));
                $check_stmt = $db->prepare("SELECT id FROM referral_codes WHERE code = ?");
                $check_stmt->execute([$code]);
            } while ($check_stmt->fetch());
            
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_days} days"));
            $stmt = $db->prepare("INSERT INTO referral_codes (code, created_by, max_uses, expires_at) VALUES (?, ?, ?, ?)");
            
            if ($stmt->execute([$code, $user['id'], $max_uses, $expires_at])) {
                $success = "Referral code '<strong>{$code}</strong>' generated successfully!";
            } else {
                $error = 'Failed to generate referral code. Please try again.';
            }
        }
    }
}

// Handle code deactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_code'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $code_id = (int)$_POST['deactivate_code'];
        $db = getDB();
        
        $stmt = $db->prepare("UPDATE referral_codes SET is_active = 0 WHERE id = ? AND created_by = ?");
        if ($stmt->execute([$code_id, $user['id']])) {
            $success = 'Referral code deactivated successfully.';
        } else {
            $error = 'Failed to deactivate referral code.';
        }
    }
}

// Get user's referral codes
$db = getDB();
$codes_stmt = $db->prepare("
    SELECT rc.*
    FROM referral_codes rc
    WHERE rc.created_by = ?
    ORDER BY rc.created_at DESC
");
$codes_stmt->execute([$user['id']]);
$referral_codes = $codes_stmt->fetchAll();

// Get referred users
$referred_stmt = $db->prepare("
    SELECT DISTINCT u.first_name, u.last_name, u.email, u.role, u.created_at, u.is_verified,
           ual.details
    FROM users u
    JOIN user_activity_logs ual ON u.id = ual.user_id
    WHERE ual.action = 'register'
    AND JSON_EXTRACT(ual.details, '$.referral_code') IN (
        SELECT code FROM referral_codes WHERE created_by = ?
    )
    ORDER BY u.created_at DESC
");
$referred_stmt->execute([$user['id']]);
$referred_users = $referred_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Referral Codes - Study Buddy</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary-color: #2563eb;
            --text-primary: #1a1a1a;
            --text-secondary: #666;
            --border-color: #e5e5e5;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.1);
            --bg-color: #fafafa;
            --card-bg: white;
        }

        [data-theme="dark"] {
            --primary-color: #3b82f6;
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --border-color: #374151;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.3);
            --bg-color: #111827;
            --card-bg: #1f2937;
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
            background: var(--bg-color);
            color: var(--text-primary);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            padding: 0.5rem 0;
            margin-bottom: 1.5rem;
            transition: color 0.2s;
        }
        .back-button:hover {
            color: var(--primary-color);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        main {
            padding: 2rem 0;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header h1 i {
            color: var(--primary-color);
        }

        .page-subtitle {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

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
            background: var(--primary-color);
            color: white;
        }

        .btn:hover {
            background: #1d4ed8;
        }
        
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            min-height: auto;
        }
        
        .btn-icon {
            padding: 0.5rem;
            width: 36px;
            height: 36px;
            font-size: 0.9rem;
            min-height: auto;
        }

        .card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .card-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-color);
        }
        [data-theme="dark"] .card-header {
            background: #1f2937;
            border-bottom: 1px solid #374151;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body {
            padding: 1.5rem;
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
            transition: all 0.2s;
            background: var(--card-bg);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        [data-theme="dark"] .alert-error {
            background: #3f1212;
            color: #fca5a5;
            border-color: #dc2626;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        [data-theme="dark"] .alert-success {
            background: #062f1e;
            color: #a7f3d0;
            border-color: #16a34a;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .table-container {
            overflow-x: auto;
            border: 1px solid var(--border-color);
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            padding: 0.875rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-color);
        }
        [data-theme="dark"] th {
            background: #374151;
        }

        td {
            padding: 0.875rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.875rem;
            color: var(--text-primary);
        }
        
        tr:last-child td {
            border-bottom: none;
        }

        .code-badge {
            background: #eef2ff;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-weight: 600;
            font-family: 'Courier New', monospace;
            color: var(--primary-color);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        [data-theme="dark"] .code-badge {
            background: #3730a3;
            color: #e0e7ff;
        }

        .copy-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--primary-color);
            padding: 0.25rem;
            transition: transform 0.2s;
        }

        .copy-btn:hover {
            transform: scale(1.1);
        }

        .progress-bar-container {
            height: 6px;
            background: var(--border-color);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), #60a5fa);
            border-radius: 3px;
            transition: width 0.3s;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        [data-theme="dark"] .badge-success {
            background: #064e3b;
            color: #6ee7b7;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        [data-theme="dark"] .badge-warning {
            background: #78350f;
            color: #fef08a;
        }

        .badge-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        [data-theme="dark"] .badge-secondary {
            background: #4b5563;
            color: #e5e7eb;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        [data-theme="dark"] .badge-info {
            background: #1e3a8a;
            color: #bfdbfe;
        }

        .info-box {
            background: #eef2ff;
            border: 1px solid #e0e7ff;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        [data-theme="dark"] .info-box {
            background: #1e3a8a;
            border-color: #312e81;
        }

        .info-box h4 {
            font-size: 0.95rem;
            font-weight: 600;
            color: #312e81;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        [data-theme="dark"] .info-box h4 {
            color: #dbeafe;
        }

        .info-box ol {
            margin-left: 1.25rem;
            font-size: 0.875rem;
            color: #374151;
        }
        [data-theme="dark"] .info-box ol {
            color: #e0e7ff;
        }
        
        .info-box li {
            margin-bottom: 0.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            main {
                padding: 1rem 0;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .container {
                padding: 0 0.75rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }

            input, select, textarea, button {
                font-size: 16px !important;
            }
            
            .card-body {
                padding: 1rem;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>

<script>
    (function() {
        const theme = localStorage.getItem('theme');
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.body.classList.add('dark-mode');
        } else {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    })();
</script>

<main>
    <div class="container">
        <a href="index.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Profile
        </a>
        
        <div class="page-header">
            <h1><i class="fas fa-ticket-alt"></i> Referral Codes</h1>
            <p class="page-subtitle">Manage your referral codes and track their usage.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
             <div class="alert alert-success">
                 <i class="fas fa-check-circle"></i>
                <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($referral_codes); ?></div>
                <div class="stat-label">Total Codes Generated</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    echo count(array_filter($referral_codes, fn($c) => $c['is_active'] && strtotime($c['expires_at']) > time() && $c['current_uses'] < $c['max_uses'])); 
                    ?>
                </div>
                <div class="stat-label">Active Codes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($referred_users); ?></div>
                <div class="stat-label">Total Users Referred</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-cogs"></i> Generate New Code</h3>
            </div>
            <div class="card-body">
                <form action="referral-codes.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Max Uses</label>
                            <p class="form-input" style="background: var(--bg-color); border: none; font-weight: 600;">1 use (Single Use)</p>
                            <input type="hidden" name="max_uses" value="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Expires In</label>
                            <p class="form-input" style="background: var(--bg-color); border: none; font-weight: 600;">3 Days</p>
                            <input type="hidden" name="expires_days" value="3">
                        </div>
                    </div>
                    <button type="submit" name="generate_code" class="btn btn-sm">
                        <i class="fas fa-plus"></i> Generate Code
                    </button>
                    <div class="info-box" style="margin-top: 1rem; background: #fffbe6; border-color: #fde68a;">
                        <p style="font-size: 0.875rem; color: #92400e; font-weight: 600;">
                            <i class="fas fa-exclamation-triangle"></i> Generation Limit: You can only generate a maximum of 5 codes per 30 days.
                        </p>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-list"></i> Your Codes</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Status</th>
                                <th>Usage</th>
                                <th>Expires</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($referral_codes)): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state" style="padding: 1rem;">
                                            <i class="fas fa-ticket-alt"></i>
                                            <p style="margin-bottom: 0;">You haven't generated any codes yet.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($referral_codes as $code): ?>
                                <?php
                                $is_expired = strtotime($code['expires_at']) < time();
                                $is_maxed_out = $code['current_uses'] >= $code['max_uses'];
                                $is_active = $code['is_active'] && !$is_expired && !$is_maxed_out;
                                ?>
                                <tr>
                                    <td>
                                        <div class="code-badge">
                                            <span><?php echo htmlspecialchars($code['code']); ?></span>
                                            <button class="copy-btn" title="Copy code" onclick="copyCode('<?php echo htmlspecialchars($code['code']); ?>')">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($is_active): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php elseif (!$code['is_active']): ?>
                                            <span class="badge badge-secondary">Deactivated</span>
                                        <?php elseif ($is_expired): ?>
                                            <span class="badge badge-warning">Expired</span>
                                        <?php elseif ($is_maxed_out): ?>
                                            <span class="badge badge-info">Max Uses</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span><?php echo $code['current_uses']; ?> / <?php echo $code['max_uses']; ?> uses</span>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar" style="width: <?php echo ($code['current_uses'] / $code['max_uses']) * 100; ?>%;"></div>
                                        </div>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($code['expires_at'])); ?></td>
                                    <td>
                                        <form method="POST" action="referral-codes.php" style="display: inline-block;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="deactivate_code" value="<?php echo $code['id']; ?>">
                                            <button type="button" class="btn btn-sm btn-icon btn-danger" 
                                                    title="Deactivate code"
                                                    onclick="confirmDeactivate(this, '<?php echo htmlspecialchars($code['code']); ?>')"
                                                    <?php echo !$is_active ? 'disabled' : ''; ?>>
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-users"></i> Users Referred</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Referred With</th>
                                <th>Date Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($referred_users)): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state" style="padding: 1rem;">
                                            <i class="fas fa-user-times"></i>
                                            <p style="margin-bottom: 0;">No users have registered with your codes yet.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($referred_users as $ref_user): ?>
                                <?php $details = json_decode($ref_user['details'], true); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ref_user['first_name'] . ' ' . $ref_user['last_name']); ?></td>
                                    <td><span class="badge badge-info"><?php echo ucfirst($ref_user['role']); ?></span></td>
                                    <td>
                                        <?php if ($ref_user['is_verified']): ?>
                                            <span class="badge badge-success">Verified</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Not Verified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code class="code-badge" style="font-size: 0.8rem;"><?php echo htmlspecialchars($details['referral_code'] ?? 'N/A'); ?></code>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($ref_user['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="info-box" style="margin: 1.5rem; border-radius: 8px;">
                    <h4><i class="fas fa-info-circle"></i> How Referrals Work</h4>
                    <ol>
                        <li>Only users who register as a 'Student' can use a referral code.</li>
                        <li>When a student registers with your code, they are automatically upgraded to 'Peer'.</li>
                        <li>This table shows all users who have successfully registered using any of your codes.</li>
                    </ol>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
    function copyCode(code) {
        navigator.clipboard.writeText(code).then(() => {
            Swal.fire({
                icon: 'success',
                title: 'Copied!',
                text: `Code "${code}" copied to clipboard`,
                timer: 2000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }).catch(err => {
            Swal.fire({
                icon: 'error',
                title: 'Failed to copy',
                text: 'Please copy the code manually',
                timer: 2000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        });
    }
    
    function confirmDeactivate(button, code) {
        Swal.fire({
            title: 'Are you sure you want to deactivate this code?',
            html: `You are about to deactivate code: <strong>${code}</strong><br>This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, deactivate it',
            confirmButtonColor: '#dc2626',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                button.closest('form').submit();
            }
        });
    }
</script>

</body>
</html>