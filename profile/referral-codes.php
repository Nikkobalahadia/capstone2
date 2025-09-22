<?php
require_once '../config/config.php';

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
        $max_uses = (int)$_POST['max_uses'];
        $expires_days = (int)$_POST['expires_days'];
        
        if ($max_uses < 1 || $max_uses > 50) {
            $error = 'Maximum uses must be between 1 and 50.';
        } elseif ($expires_days < 1 || $expires_days > 30) {
            $error = 'Expiration must be between 1 and 30 days.';
        } else {
            $db = getDB();
            
            // Generate unique referral code
            do {
                $prefix = strtoupper($user['role']);
                $code = $prefix . strtoupper(substr(uniqid(), -6));
                $check_stmt = $db->prepare("SELECT id FROM referral_codes WHERE code = ?");
                $check_stmt->execute([$code]);
            } while ($check_stmt->fetch());
            
            // Create referral code
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_days} days"));
            $stmt = $db->prepare("INSERT INTO referral_codes (code, created_by, max_uses, expires_at) VALUES (?, ?, ?, ?)");
            
            if ($stmt->execute([$code, $user['id'], $max_uses, $expires_at])) {
                $success = "Referral code '{$code}' generated successfully!";
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
        $code_id = (int)$_POST['code_id'];
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
    SELECT rc.*, 
           COUNT(u.id) as users_referred
    FROM referral_codes rc
    LEFT JOIN users u ON u.id IN (
        SELECT user_id FROM user_activity_logs 
        WHERE action = 'register' 
        AND JSON_EXTRACT(details, '$.referral_used') = true
        AND created_at >= rc.created_at
    )
    WHERE rc.created_by = ?
    GROUP BY rc.id
    ORDER BY rc.created_at DESC
");
$codes_stmt->execute([$user['id']]);
$referral_codes = $codes_stmt->fetchAll();

// Get referred users
$referred_stmt = $db->prepare("
    SELECT u.first_name, u.last_name, u.email, u.role, u.created_at, u.is_verified
    FROM users u
    JOIN user_activity_logs ual ON u.id = ual.user_id
    WHERE ual.action = 'register' 
    AND JSON_EXTRACT(ual.details, '$.referral_used') = true
    AND ual.created_at >= (
        SELECT MIN(created_at) FROM referral_codes WHERE created_by = ?
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Codes - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="../dashboard.php" class="logo">StudyConnect</a>
                <ul class="nav-links">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <li><a href="index.php">Profile</a></li>
                    <li><a href="../matches/index.php">Matches</a></li>
                    <li><a href="../sessions/index.php">Sessions</a></li>
                    <li><a href="../messages/index.php">Messages</a></li>
                    <li><a href="../auth/logout.php" class="btn btn-outline">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="container">
            <div class="mb-4">
                <a href="index.php" class="text-primary" style="text-decoration: none;">← Back to Profile</a>
                <h1 style="margin: 0.5rem 0;">Referral Codes</h1>
                <p class="text-secondary">
                    <?php if ($user['role'] === 'mentor'): ?>
                        Generate referral codes to invite co-teachers and help them get verified faster.
                    <?php else: ?>
                        Generate referral codes to invite fellow students and peers to join the platform.
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="grid grid-cols-3" style="gap: 2rem;">
                <!-- Generate New Code -->
                <div style="grid-column: span 2;">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">Generate New Referral Code</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="generate_code" value="1">
                                
                                <div class="grid grid-cols-2" style="gap: 1rem;">
                                    <div class="form-group">
                                        <label for="max_uses" class="form-label">Maximum Uses</label>
                                        <select id="max_uses" name="max_uses" class="form-select" required>
                                            <option value="1">1 use (Single invite)</option>
                                            <option value="5" selected>5 uses</option>
                                            <option value="10">10 uses</option>
                                            <option value="25">25 uses</option>
                                            <option value="50">50 uses</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="expires_days" class="form-label">Expires In</label>
                                        <select id="expires_days" name="expires_days" class="form-select" required>
                                            <option value="7" selected>7 days</option>
                                            <option value="14">14 days</option>
                                            <option value="30">30 days</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Generate Referral Code</button>
                            </form>
                        </div>
                    </div>

                    <!-- Active Referral Codes -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Your Referral Codes</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($referral_codes)): ?>
                                <p class="text-secondary text-center">No referral codes generated yet.</p>
                            <?php else: ?>
                                <div style="overflow-x: auto;">
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <thead>
                                            <tr style="border-bottom: 1px solid var(--border-color);">
                                                <th style="padding: 0.75rem; text-align: left;">Code</th>
                                                <th style="padding: 0.75rem; text-align: left;">Usage</th>
                                                <th style="padding: 0.75rem; text-align: left;">Expires</th>
                                                <th style="padding: 0.75rem; text-align: left;">Status</th>
                                                <th style="padding: 0.75rem; text-align: left;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($referral_codes as $code): ?>
                                                <tr style="border-bottom: 1px solid var(--border-color);">
                                                    <td style="padding: 0.75rem;">
                                                        <code style="background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: 600;">
                                                            <?php echo htmlspecialchars($code['code']); ?>
                                                        </code>
                                                    </td>
                                                    <td style="padding: 0.75rem;">
                                                        <?php echo $code['current_uses']; ?> / <?php echo $code['max_uses']; ?>
                                                        <div class="progress" style="height: 4px; margin-top: 4px;">
                                                            <div class="progress-bar" style="width: <?php echo ($code['current_uses'] / $code['max_uses']) * 100; ?>%;"></div>
                                                        </div>
                                                    </td>
                                                    <td style="padding: 0.75rem;">
                                                        <?php echo $code['expires_at'] ? date('M j, Y', strtotime($code['expires_at'])) : 'Never'; ?>
                                                    </td>
                                                    <td style="padding: 0.75rem;">
                                                        <?php 
                                                        $is_expired = $code['expires_at'] && strtotime($code['expires_at']) < time();
                                                        $is_maxed = $code['current_uses'] >= $code['max_uses'];
                                                        
                                                        if (!$code['is_active']) {
                                                            echo '<span class="badge badge-secondary">Inactive</span>';
                                                        } elseif ($is_expired) {
                                                            echo '<span class="badge badge-warning">Expired</span>';
                                                        } elseif ($is_maxed) {
                                                            echo '<span class="badge badge-info">Max Uses Reached</span>';
                                                        } else {
                                                            echo '<span class="badge badge-success">Active</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td style="padding: 0.75rem;">
                                                        <?php if ($code['is_active'] && !$is_expired && !$is_maxed): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                                <input type="hidden" name="deactivate_code" value="1">
                                                                <input type="hidden" name="code_id" value="<?php echo $code['id']; ?>">
                                                                <button type="submit" class="btn btn-secondary btn-sm" 
                                                                        onclick="return confirm('Are you sure you want to deactivate this code?')">
                                                                    Deactivate
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

                <!-- Sidebar -->
                <div>
                    <!-- Statistics -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">Referral Statistics</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Total Codes Generated:</strong><br>
                                <span class="text-primary"><?php echo count($referral_codes); ?></span>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Active Codes:</strong><br>
                                <span class="text-success">
                                    <?php echo count(array_filter($referral_codes, function($c) { 
                                        return $c['is_active'] && 
                                               (!$c['expires_at'] || strtotime($c['expires_at']) > time()) &&
                                               $c['current_uses'] < $c['max_uses']; 
                                    })); ?>
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Total Referrals:</strong><br>
                                <span class="text-warning"><?php echo count($referred_users); ?></span>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Verified Referrals:</strong><br>
                                <span class="text-info">
                                    <?php echo count(array_filter($referred_users, function($u) { return $u['is_verified']; })); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- How It Works -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">How Referral Codes Work</h3>
                        </div>
                        <div class="card-body">
                            <ol style="margin-left: 1rem; font-size: 0.875rem;">
                                <li class="mb-2">Generate a unique referral code with custom usage limits and expiration</li>
                                <li class="mb-2">
                                    <?php if ($user['role'] === 'mentor'): ?>
                                        Share the code with co-teachers you want to invite
                                    <?php else: ?>
                                        Share the code with fellow students and peers you want to invite
                                    <?php endif; ?>
                                </li>
                                <li class="mb-2">When they register using your code, they get auto-verified status</li>
                                <li class="mb-2">You can track all your referrals and their verification status</li>
                                <li>Codes expire automatically or when usage limit is reached</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Referred Users -->
            <?php if ($referred_users): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h3 class="card-title">Users You've Referred</h3>
                    </div>
                    <div class="card-body">
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <th style="padding: 0.75rem; text-align: left;">Name</th>
                                        <th style="padding: 0.75rem; text-align: left;">Email</th>
                                        <th style="padding: 0.75rem; text-align: left;">Role</th>
                                        <th style="padding: 0.75rem; text-align: left;">Joined</th>
                                        <th style="padding: 0.75rem; text-align: left;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($referred_users as $referred): ?>
                                        <tr style="border-bottom: 1px solid var(--border-color);">
                                            <td style="padding: 0.75rem;">
                                                <?php echo htmlspecialchars($referred['first_name'] . ' ' . $referred['last_name']); ?>
                                            </td>
                                            <td style="padding: 0.75rem;">
                                                <?php echo htmlspecialchars($referred['email']); ?>
                                            </td>
                                            <td style="padding: 0.75rem;">
                                                <?php echo ucfirst($referred['role']); ?>
                                            </td>
                                            <td style="padding: 0.75rem;">
                                                <?php echo date('M j, Y', strtotime($referred['created_at'])); ?>
                                            </td>
                                            <td style="padding: 0.75rem;">
                                                <?php if ($referred['is_verified']): ?>
                                                    <span class="badge badge-success">Verified</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning">Unverified</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
