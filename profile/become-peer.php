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

// Only students can become peers
if ($user['role'] !== 'student') {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $referral_code = sanitize_input($_POST['referral_code']);
        
        if (empty($referral_code)) {
            $error = 'Please enter a referral code from a mentor.';
        } else {
            $db = getDB();
            
            error_log("[v0] Peer verification started - User ID: {$user['id']}, Referral Code: {$referral_code}");
            
            // Validate referral code - must be from a verified mentor
            $ref_stmt = $db->prepare("
                SELECT rc.id, rc.created_by, rc.max_uses, rc.current_uses, u.role, u.is_verified
                FROM referral_codes rc
                JOIN users u ON rc.created_by = u.id
                WHERE rc.code = ? 
                AND rc.is_active = 1 
                AND (rc.expires_at IS NULL OR rc.expires_at > NOW())
                AND u.role IN ('mentor', 'peer')
                AND u.is_verified = 1
            ");
            
            if (!$ref_stmt->execute([$referral_code])) {
                error_log("[v0] Referral code query failed: " . print_r($ref_stmt->errorInfo(), true));
                $error = 'Database error. Please try again.';
            } else {
                $referral = $ref_stmt->fetch();
                error_log("[v0] Referral lookup result: " . ($referral ? 'Found' : 'Not found'));
                
                if (!$referral) {
                    $error = 'Invalid referral code. Please make sure you have a valid code from a verified mentor.';
                } elseif ($referral['current_uses'] >= $referral['max_uses']) {
                    $error = 'This referral code has reached its maximum usage limit.';
                } else {
                    try {
                        $db->beginTransaction();
                        
                        error_log("[v0] Starting peer upgrade for user ID: {$user['id']}");
                        
                        $update_stmt = $db->prepare("UPDATE users SET role = 'peer', is_verified = 1 WHERE id = ?");
                        $result = $update_stmt->execute([$user['id']]);
                        
                        if (!$result) {
                            error_log("[v0] ERROR: Failed to update user role - " . print_r($update_stmt->errorInfo(), true));
                            throw new Exception("Failed to update user role. Error: " . print_r($update_stmt->errorInfo(), true));
                        }
                        
                        $rows_affected = $update_stmt->rowCount();
                        error_log("[v0] Update query affected {$rows_affected} rows");
                        
                        if ($rows_affected === 0) {
                            throw new Exception("No rows were updated. User may not exist.");
                        }
                        
                        $verify_stmt = $db->prepare("SELECT role, is_verified FROM users WHERE id = ?");
                        $verify_stmt->execute([$user['id']]);
                        $verify_result = $verify_stmt->fetch();
                        
                        if (!$verify_result) {
                            throw new Exception("Could not verify user after update");
                        }
                        
                        error_log("[v0] After update - Role: {$verify_result['role']}, Verified: {$verify_result['is_verified']}");
                        
                        if ($verify_result['role'] !== 'peer' || $verify_result['is_verified'] != 1) {
                            throw new Exception("Verification failed: Role is {$verify_result['role']}, Verified is {$verify_result['is_verified']}");
                        }
                        
                        // Update referral code usage
                        $update_ref = $db->prepare("UPDATE referral_codes SET current_uses = current_uses + 1 WHERE id = ?");
                        $update_ref->execute([$referral['id']]);
                        
                        // Log the upgrade
                        $upgrade_details = json_encode([
                            'previous_role' => 'student',
                            'new_role' => 'peer',
                            'referral_code' => $referral_code,
                            'referral_code_id' => $referral['id'],
                            'referred_by' => $referral['created_by'],
                            'auto_verified' => true
                        ]);
                        
                        $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'upgrade_to_peer', ?, ?)");
                        $log_stmt->execute([$user['id'], $upgrade_details, $_SERVER['REMOTE_ADDR']]);
                        
                        $db->commit();
                        
                        $_SESSION['role'] = 'peer';
                        $_SESSION['is_verified'] = 1;
                        
                        // Refresh user data from database
                        $refresh_stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                        $refresh_stmt->execute([$user['id']]);
                        $updated_user = $refresh_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($updated_user) {
                            // Update ALL session variables with fresh data
                            foreach ($updated_user as $key => $value) {
                                $_SESSION[$key] = $value;
                            }
                        }
                        
                        error_log("[v0] Session refreshed - Role: {$_SESSION['role']}, Verified: {$_SESSION['is_verified']}");
                        
                        $success = 'Congratulations! You are now a verified Peer. You can now both learn and teach on StudyConnect!';
                        
                        // Redirect to profile to see updated status
                        header("refresh:2;url=index.php");
                        
                    } catch (Exception $e) {
                        $db->rollBack();
                        error_log("[v0] Error upgrading to peer: " . $e->getMessage());
                        $error = 'Failed to upgrade to peer status: ' . $e->getMessage();
                    }
                }
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
    <title>Become a Peer - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Replaced admin header with proper user header -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="../dashboard.php" class="logo">StudyConnect</a>
                <ul class="nav-links">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <li><a href="../matches/index.php">Matches</a></li>
                    <li><a href="../sessions/index.php">Sessions</a></li>
                    <li><a href="../messages/index.php">Messages</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="container">
            <div class="form-container" style="max-width: 600px;">
                <div style="text-align: center; margin-bottom: 2rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🤝</div>
                    <h2 class="mb-4">Become a Peer</h2>
                    <p class="text-secondary">
                        Upgrade your account to Peer status and start helping other students while continuing to learn yourself!
                    </p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div style="background: #f8fafc; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; border-left: 4px solid var(--primary-color);">
                    <h4 class="font-semibold mb-3">What is a Peer?</h4>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <li style="padding: 0.5rem 0; display: flex; align-items: start; gap: 0.5rem;">
                            <span style="color: var(--success-color); font-weight: bold;">✓</span>
                            <span>Learn from mentors and other peers in subjects you want to improve</span>
                        </li>
                        <li style="padding: 0.5rem 0; display: flex; align-items: start; gap: 0.5rem;">
                            <span style="color: var(--success-color); font-weight: bold;">✓</span>
                            <span>Teach and help other students in subjects you excel at</span>
                        </li>
                        <li style="padding: 0.5rem 0; display: flex; align-items: start; gap: 0.5rem;">
                            <span style="color: var(--success-color); font-weight: bold;">✓</span>
                            <span>Build your teaching experience and help your community</span>
                        </li>
                        <li style="padding: 0.5rem 0; display: flex; align-items: start; gap: 0.5rem;">
                            <span style="color: var(--success-color); font-weight: bold;">✓</span>
                            <span>Access both student and mentor features</span>
                        </li>
                    </ul>
                </div>
                
                <div style="background: #fffbeb; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; border-left: 4px solid var(--warning-color);">
                    <h4 class="font-semibold mb-2" style="color: var(--warning-color);">Requirement</h4>
                    <p class="text-secondary" style="margin: 0;">
                        To become a peer, you need a <strong>referral code from a verified mentor</strong>. 
                        This ensures that peers are recommended by trusted mentors in our community.
                    </p>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-group">
                        <label for="referral_code" class="form-label">Mentor Referral Code</label>
                        <input type="text" id="referral_code" name="referral_code" class="form-input" required 
                               placeholder="Enter the referral code from your mentor"
                               value="<?php echo isset($_POST['referral_code']) ? htmlspecialchars($_POST['referral_code']) : ''; ?>">
                        <small class="text-secondary">
                            Ask a verified mentor for their referral code. They can generate one from their profile.
                        </small>
                    </div>
                    
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">Upgrade to Peer</button>
                        <a href="index.php" class="btn btn-outline" style="flex: 1; text-align: center;">Cancel</a>
                    </div>
                </form>
                
                <div style="margin-top: 2rem; padding: 1rem; background: #f0f9ff; border-radius: 8px; text-align: center;">
                    <p class="text-sm text-secondary" style="margin: 0;">
                        Don't have a referral code? Connect with mentors in your subjects and ask them for a code!
                    </p>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
