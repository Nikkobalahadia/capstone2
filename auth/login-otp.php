<?php
require_once '../config/config.php';
require_once '../config/email.php';
require_once '../lib/PHPMailer.php';

$error = '';
$success = '';
$step = isset($_GET['step']) ? $_GET['step'] : 'email';

// Step 1: Request OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_otp'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = sanitize_input($_POST['email']);
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $db = getDB();
            
            $stmt = $db->prepare("SELECT id, is_active FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $error = "Couldn't find your account. Check your email address and try again.";
            } elseif (!$user['is_active']) {
                $error = 'Your account has been deactivated. Please contact support.';
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
                if (send_otp_email($email, $otp_code)) {
                    $_SESSION['otp_email'] = $email;
                    $success = 'A verification code has been sent to your email.';
                    $step = 'verify';
                } else {
                    $error = 'Failed to send verification code. Please try again.';
                }
            }
        }
    }
}

// Step 2: Verify OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $otp_code = sanitize_input($_POST['otp_code']);
        $email = $_SESSION['otp_email'] ?? '';
        
        if (empty($otp_code) || empty($email)) {
            $error = 'Invalid request. Please start over.';
        } else {
            $db = getDB();
            
            // Verify OTP
            $stmt = $db->prepare("
                SELECT id FROM otp_codes 
                WHERE email = ? AND otp_code = ? AND expires_at > NOW() AND is_used = 0
            ");
            $stmt->execute([$email, $otp_code]);
            $otp = $stmt->fetch();
            
            if (!$otp) {
                $error = 'Invalid or expired verification code.';
            } else {
                // Mark OTP as used
                $update_stmt = $db->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?");
                $update_stmt->execute([$otp['id']]);
                
                $user_stmt = $db->prepare("SELECT id, username, role, first_name, last_name FROM users WHERE email = ?");
                $user_stmt->execute([$email]);
                $user = $user_stmt->fetch();
                
                if (!$user) {
                    $error = 'User account not found. Please register first.';
                } else {
                    // Log successful login
                    $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'login_otp', ?, ?)");
                    $log_stmt->execute([$user['id'], json_encode(['method' => 'otp']), $_SERVER['REMOTE_ADDR']]);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    
                    // Clear OTP email from session
                    unset($_SESSION['otp_email']);
                    
                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        redirect('admin/dashboard.php');
                    } else {
                        redirect('dashboard.php');
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
    <title>Sign In with Email - StudyConnect</title>
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
                    <li><a href="register.php">Sign Up</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="min-height: 80vh; display: flex; align-items: center;">
        <div class="form-container">
            <?php if ($step === 'email'): ?>
                <h2 class="text-center mb-4">Sign In with Email</h2>
                <!-- Updated description to clarify this is for existing accounts only -->
                <p class="text-center text-secondary mb-4">Enter your email to receive a verification code</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" required 
                               placeholder="Enter your email"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    
                    <button type="submit" name="request_otp" class="btn btn-primary" style="width: 100%;">
                        Continue with Email
                    </button>
                </form>
                
                <div class="text-center mt-4">
                    <p class="text-secondary">Prefer password login? <a href="login.php" class="text-primary">Sign in with password</a></p>
                    <!-- Added link to registration for new users -->
                    <p class="text-secondary mt-2">Don't have an account? <a href="register.php" class="text-primary">Sign up here</a></p>
                </div>
                
            <?php else: ?>
                <h2 class="text-center mb-4">Enter Verification Code</h2>
                <p class="text-center text-secondary mb-4">
                    We sent a 6-digit code to<br>
                    <strong><?php echo htmlspecialchars($_SESSION['otp_email'] ?? ''); ?></strong>
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
                        Verify & Sign In
                    </button>
                </form>
                
                <div class="text-center mt-4">
                    <p class="text-secondary">Didn't receive the code? <a href="login-otp.php" class="text-primary">Resend code</a></p>
                    <p class="text-secondary mt-2"><a href="login-otp.php" class="text-primary">Use a different email</a></p>
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
