<?php
require_once '../config/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password'];
        
        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, username, password_hash, role, first_name, last_name, is_verified, is_active FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                if (!$user['is_active']) {
                    $error = 'Your account has been deactivated. Please contact support.';
                } else {
                    $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'login', ?, ?)");
                    $log_stmt->execute([$user['id'], json_encode(['success' => true]), $_SERVER['REMOTE_ADDR']]);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    
                    if ($user['role'] === 'admin') {
                        redirect('admin/dashboard.php');
                    } else {
                        redirect('dashboard.php');
                    }
                }
            } else {
                $error = 'Invalid email or password.';
                
                if ($user) {
                    $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'login_failed', ?, ?)");
                    $log_stmt->execute([$user['id'], json_encode(['reason' => 'wrong_password']), $_SERVER['REMOTE_ADDR']]);
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
    <title>Login - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .password-input-group {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6b7280;
            transition: color 0.2s ease;
            background: none;
            border: none;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .password-toggle:hover {
            color: #374151;
        }
        
        .password-input-group .form-input {
            padding-right: 45px;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container navbar">
            <a href="../index.php" class="logo">StudyConnect</a>
            <ul class="nav-links">
                <li><a href="../index.php">Home</a></li>
                <li><a href="register.php">Sign Up</a></li>
            </ul>
        </div>
    </header>

    <main class="flex items-center justify-center min-h-[80vh]">
        <div class="form-container">
            <h2 class="text-center mb-4">Welcome Back</h2>
            <p class="text-center text-secondary mb-4">Sign in to your StudyConnect account</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">

                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input" autocomplete="email" placeholder="john@example.com" required
                        value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="password-input-group">
                        <input type="password" id="password" name="password" class="form-input" autocomplete="current-password" placeholder="••••••••" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-full">Sign In</button>
            </form>

            <div class="text-center mt-3">
                <p class="text-secondary">Or</p>
                <a href="login-otp.php" class="btn btn-secondary w-full mt-2">Sign In with Email Code</a>
            </div>

            <div class="text-center mt-4">
                <p class="text-secondary">
                    Don't have an account? <a href="register.php" class="text-primary">Sign up here</a>
                </p>
                <p class="text-secondary mt-2">
                    <a href="forgot-password.php" class="text-primary">Forgot your password?</a>
                </p>
            </div>
        </div>
    </main>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = event.target.closest('.password-toggle');
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>