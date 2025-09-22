<?php
require_once '../config/config.php';

$error = '';
$success = '';
$role = isset($_GET['role']) && in_array($_GET['role'], ['student', 'mentor', 'peer']) ? $_GET['role'] : 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Sanitize inputs
        $username = sanitize_input($_POST['username']);
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $first_name = sanitize_input($_POST['first_name']);
        $last_name = sanitize_input($_POST['last_name']);
        $role = sanitize_input($_POST['role']);
        $referral_code = sanitize_input($_POST['referral_code']);
        
        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
            $error = 'Please fill in all required fields.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $db = getDB();
            
            // Check if username or email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = 'Username or email already exists.';
            } else {
                // Validate referral code if provided
                $referral_valid = true;
                $referral_creator_id = null;
                if (!empty($referral_code)) {
                    $ref_stmt = $db->prepare("SELECT id, created_by, max_uses, current_uses FROM referral_codes WHERE code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())");
                    $ref_stmt->execute([$referral_code]);
                    $referral = $ref_stmt->fetch();
                    
                    if (!$referral || $referral['current_uses'] >= $referral['max_uses']) {
                        $error = 'Invalid or expired referral code.';
                        $referral_valid = false;
                    } else {
                        $referral_creator_id = $referral['created_by'];
                    }
                }
                
                if ($referral_valid) {
                    // Create user account
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    try {
                        $db->beginTransaction();
                        
                        $is_verified = !empty($referral_code) && $referral_creator_id ? 1 : 0;
                        
                        $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role, first_name, last_name, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$username, $email, $password_hash, $role, $first_name, $last_name, $is_verified]);
                        $user_id = $db->lastInsertId();
                        
                        // Update referral code usage if used
                        if (!empty($referral_code) && isset($referral)) {
                            $update_ref = $db->prepare("UPDATE referral_codes SET current_uses = current_uses + 1 WHERE id = ?");
                            $update_ref->execute([$referral['id']]);
                            
                            // Instead, log the referral usage in activity logs with more details
                            $referral_details = json_encode([
                                'role' => $role, 
                                'referral_used' => true,
                                'referral_code' => $referral_code,
                                'referral_code_id' => $referral['id'],
                                'referred_by' => $referral_creator_id
                            ]);
                        } else {
                            $referral_details = json_encode(['role' => $role, 'referral_used' => false]);
                        }
                        
                        // Log registration
                        $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'register', ?, ?)");
                        $log_stmt->execute([$user_id, $referral_details, $_SERVER['REMOTE_ADDR']]);
                        
                        $db->commit();
                        
                        $success = 'Account created successfully! You can now log in.';
                        
                        // Auto-login the user
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['username'] = $username;
                        $_SESSION['role'] = $role;
                        $_SESSION['full_name'] = $first_name . ' ' . $last_name;
                        
                        // Redirect to profile setup
                        redirect('profile/setup.php');
                        
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = 'Registration failed. Please try again.';
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
    <title>Sign Up - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="../index.php" class="logo">StudyConnect</a>
                <ul class="nav-links">
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="login.php">Login</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="form-container" style="max-width: 500px;">
            <h2 class="text-center mb-4">Join StudyConnect</h2>
            <p class="text-center text-secondary mb-4">Create your account as a <?php echo ucfirst($role); ?></p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-group">
                    <label for="role" class="form-label">I want to join as:</label>
                    <select id="role" name="role" class="form-select" required>
                        <option value="student" <?php echo $role === 'student' ? 'selected' : ''; ?>>Student (Looking for help)</option>
                        <option value="mentor" <?php echo $role === 'mentor' ? 'selected' : ''; ?>>Mentor (Ready to help others)</option>
                        <option value="peer" <?php echo $role === 'peer' ? 'selected' : ''; ?>>Peer (Can both teach and learn)</option>
                    </select>
                    <small class="text-secondary">
                        <strong>Peer:</strong> Perfect if you're good at some subjects but need help with others. You can both teach and learn!
                    </small>
                </div>
                
                <div class="grid grid-cols-2" style="gap: 1rem;">
                    <div class="form-group">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" id="first_name" name="first_name" class="form-input" required 
                               value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" id="last_name" name="last_name" class="form-input" required 
                               value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" id="username" name="username" class="form-input" required 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-input" required minlength="8">
                    <small class="text-secondary">Must be at least 8 characters long</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label for="referral_code" class="form-label">Referral Code (Optional)</label>
                    <input type="text" id="referral_code" name="referral_code" class="form-input" 
                           value="<?php echo isset($_POST['referral_code']) ? htmlspecialchars($_POST['referral_code']) : ''; ?>">
                    <small class="text-secondary">Enter a referral code from a verified teacher if you have one</small>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
            </form>
            
            <div class="text-center mt-4">
                <p class="text-secondary">Already have an account? <a href="login.php" class="text-primary">Sign in here</a></p>
            </div>
        </div>
    </main>
</body>
</html>
