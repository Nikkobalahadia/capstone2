<?php
require_once '../config/config.php';
require_once '../config/email.php';
require_once '../lib/PHPMailer.php';

$error = '';
$success = '';
$step = isset($_GET['step']) ? $_GET['step'] : 'form';
$role = isset($_GET['role']) && in_array($_GET['role'], ['student', 'mentor']) ? $_GET['role'] : 'student';

// Step 1: Collect registration info and send OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
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
        
        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
            $error = 'Please fill in all required fields.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!in_array($role, ['student', 'mentor'])) {
            $error = 'Invalid role selected.';
        } else {
            $db = getDB();
            
            // Check if username or email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = 'Username or email already exists.';
            } else {
                $otp_code = str_pad(random_int(0, 999999), OTP_LENGTH, '0', STR_PAD_LEFT);
                $expires_at = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
                
                // Delete old OTPs for this email
                $delete_stmt = $db->prepare("DELETE FROM otp_codes WHERE email = ?");
                $delete_stmt->execute([$email]);
                
                // Insert new OTP
                $insert_stmt = $db->prepare("INSERT INTO otp_codes (email, otp_code, expires_at) VALUES (?, ?, ?)");
                $insert_stmt->execute([$email, $otp_code, $expires_at]);
                
                // Send OTP email
                $email_sent = send_otp_email($email, $otp_code);
                
                if ($email_sent) {
                    // Store registration data in session
                    $_SESSION['registration_data'] = [
                        'username' => $username,
                        'email' => $email,
                        'password' => $password,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'role' => $role
                    ];
                    
                    $success = 'A verification code has been sent to your email.';
                    $step = 'verify';
                } else {
                    $delete_failed = $db->prepare("DELETE FROM otp_codes WHERE email = ? AND otp_code = ?");
                    $delete_failed->execute([$email, $otp_code]);
                    
                    $error = 'Unable to send verification code to this email address. Please use a valid email address and try again.';
                }
            }
        }
    }
}

// Step 2: Verify OTP and create account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $otp_code = sanitize_input($_POST['otp_code']);
        $registration_data = $_SESSION['registration_data'] ?? null;
        
        if (empty($otp_code) || !$registration_data) {
            $error = 'Invalid request. Please start over.';
            $step = 'form';
        } else {
            $db = getDB();
            
            // Verify OTP
            $stmt = $db->prepare("
                SELECT id FROM otp_codes 
                WHERE email = ? AND otp_code = ? AND expires_at > NOW() AND is_used = 0
            ");
            $stmt->execute([$registration_data['email'], $otp_code]);
            $otp = $stmt->fetch();
            
            if (!$otp) {
                $error = 'Invalid or expired verification code.';
            } else {
                // Mark OTP as used
                $update_stmt = $db->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?");
                $update_stmt->execute([$otp['id']]);
                
                $password_hash = password_hash($registration_data['password'], PASSWORD_DEFAULT);
                
                try {
                    $db->beginTransaction();
                    
                    $is_verified = 1; // Email is verified via OTP
                    
                    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role, first_name, last_name, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $registration_data['username'],
                        $registration_data['email'],
                        $password_hash,
                        $registration_data['role'],
                        $registration_data['first_name'],
                        $registration_data['last_name'],
                        $is_verified
                    ]);
                    $user_id = $db->lastInsertId();
                    
                    $referral_details = json_encode(['role' => $registration_data['role'], 'referral_used' => false]);
                    
                    // Log registration
                    $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'register', ?, ?)");
                    $log_stmt->execute([$user_id, $referral_details, $_SERVER['REMOTE_ADDR']]);
                    
                    $db->commit();
                    
                    // Auto-login the user
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $registration_data['username'];
                    $_SESSION['role'] = $registration_data['role'];
                    $_SESSION['full_name'] = $registration_data['first_name'] . ' ' . $registration_data['last_name'];
                    
                    // Clear registration data
                    unset($_SESSION['registration_data']);
                    
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

// Restore form data if returning to form step
$form_data = $_SESSION['registration_data'] ?? [];
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
            <?php if ($step === 'form'): ?>
                <h2 class="text-center mb-4">Join StudyConnect</h2>
                <p class="text-center text-secondary mb-4">Create your account as a <?php echo ucfirst($role); ?></p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-group">
                        <label for="role" class="form-label">I want to join as:</label>
                        <select id="role" name="role" class="form-select" required>
                            <option value="student" <?php echo ($form_data['role'] ?? $role) === 'student' ? 'selected' : ''; ?>>Student (Looking for help)</option>
                            <option value="mentor" <?php echo ($form_data['role'] ?? $role) === 'mentor' ? 'selected' : ''; ?>>Mentor (Ready to help others)</option>
                        </select>
                        <small class="text-secondary">Students can upgrade to Peer status later from their profile</small>
                    </div>
                    
                    <div class="grid grid-cols-2" style="gap: 1rem;">
                        <div class="form-group">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-input" required 
                                   value="<?php echo htmlspecialchars($form_data['first_name'] ?? $_POST['first_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-input" required 
                                   value="<?php echo htmlspecialchars($form_data['last_name'] ?? $_POST['last_name'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" id="username" name="username" class="form-input" required 
                               value="<?php echo htmlspecialchars($form_data['username'] ?? $_POST['username'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" required 
                               value="<?php echo htmlspecialchars($form_data['email'] ?? $_POST['email'] ?? ''); ?>">
                        <small class="text-secondary">We'll send a verification code to this email</small>
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
                    
                    <button type="submit" name="register" class="btn btn-primary" style="width: 100%;">Continue</button>
                </form>
                
                <div class="text-center mt-4">
                    <p class="text-secondary">Already have an account? <a href="login.php" class="text-primary">Sign in here</a></p>
                </div>
                
            <?php else: ?>
                <!-- OTP verification step -->
                <h2 class="text-center mb-4">Verify Your Email</h2>
                <p class="text-center text-secondary mb-4">
                    We sent a 6-digit code to<br>
                    <strong><?php echo htmlspecialchars($form_data['email'] ?? ''); ?></strong>
                </p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-group">
                        <label for="otp_code" class="form-label">Verification Code</label>
                        <input type="text" id="otp_code" name="otp_code" class="form-input" 
                               required maxlength="6" pattern="[0-9]{6}"
                               placeholder="000000"
                               style="font-size: 24px; letter-spacing: 8px; text-align: center;">
                        <small class="text-secondary">Code expires in <?php echo OTP_EXPIRY_MINUTES; ?> minutes</small>
                    </div>
                    
                    <button type="submit" name="verify_otp" class="btn btn-primary" style="width: 100%;">
                        Verify & Create Account
                    </button>
                </form>
                
                <div class="text-center mt-4">
                    <p class="text-secondary">Didn't receive the code? <a href="register.php" class="text-primary">Resend code</a></p>
                    <p class="text-secondary mt-2"><a href="register.php" class="text-primary">Use a different email</a></p>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        // Auto-focus on OTP input
        document.addEventListener('DOMContentLoaded', function() {
            const otpInput = document.getElementById('otp_code');
            if (otpInput) {
                otpInput.focus();
                
                // Only allow numbers
                otpInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }
        });
    </script>
</body>
</html>
