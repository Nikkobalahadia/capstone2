<?php
require_once '../config/config.php';

$error = '';
$success = '';

if (isset($_GET['error'])) {
    $error = sanitize_input($_GET['error']);
}

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
            
            $stmt = $db->prepare("SELECT id, username, password_hash, role, first_name, last_name, is_verified, is_active, account_status FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                
                if (!$user['is_active']) {
                    $error = 'Your account has been deactivated. Please contact support.';
                } elseif ($user['account_status'] === 'suspended') {
                    $error = 'Your account has been suspended due to unpaid commissions. Please contact support.';
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
    <title>Login - Study Buddy</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8eef5 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #4F75FF;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logo i {
            font-size: 1.75rem;
        }

        .nav-links {
            display: flex;
            gap: 1.5rem;
            list-style: none;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #6b7280;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-links a:hover {
            color: #4F75FF;
        }

        .btn-primary-outline {
            padding: 0.5rem 1.25rem;
            border: 2px solid #4F75FF;
            border-radius: 8px;
            color: #4F75FF;
            transition: all 0.3s ease;
        }

        .btn-primary-outline:hover {
            background: #4F75FF;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 117, 255, 0.3);
        }

        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .form-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07), 0 10px 20px rgba(0, 0, 0, 0.05);
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            animation: slideUp 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .card-icon-decoration {
            position: absolute;
            top: -30px;
            right: -30px;
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #4F75FF 0%, #7b9cff 100%);
            border-radius: 50%;
            opacity: 0.1;
            z-index: 0;
        }

        .card-icon-decoration-2 {
            position: absolute;
            bottom: -40px;
            left: -40px;
            width: 140px;
            height: 140px;
            background: linear-gradient(135deg, #4F75FF 0%, #7b9cff 100%);
            border-radius: 50%;
            opacity: 0.08;
            z-index: 0;
        }

        .form-content {
            position: relative;
            z-index: 1;
        }

        .icon-badge {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #4F75FF 0%, #6b8fff 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 4px 12px rgba(79, 117, 255, 0.3);
            animation: iconFloat 3s ease-in-out infinite;
        }

        .icon-badge i {
            font-size: 2rem;
            color: white;
        }

        @keyframes iconFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .subtitle {
            text-align: center;
            color: #6b7280;
            margin-bottom: 1.75rem;
            font-size: 0.95rem;
        }

        .alert {
            padding: 0.875rem 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            animation: fadeIn 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #fafbfc;
        }

        .form-input:focus {
            outline: none;
            border-color: #4F75FF;
            background: white;
            box-shadow: 0 0 0 3px rgba(79, 117, 255, 0.1);
        }

        .form-input.input-error {
            border-color: #dc2626;
            background: #fef2f2;
        }

        .password-input-group {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0.5rem;
            font-size: 1rem;
        }

        .password-toggle:hover {
            color: #4F75FF;
        }

        .form-error-message {
            color: #dc2626;
            font-size: 0.85rem;
            margin-top: 0.5rem;
            display: block;
            min-height: 20px;
        }

        .btn-primary {
            width: 100%;
            padding: 0.875rem;
            background: #4F75FF;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary:hover:not(:disabled) {
            background: #3d5ecc;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(79, 117, 255, 0.3);
        }

        .btn-primary:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .text-center {
            text-align: center;
        }

        .links-section {
            margin-top: 1.75rem;
            padding-top: 1.75rem;
            border-top: 1px solid #e5e7eb;
        }

        .links-section p {
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }

        .links-section a {
            color: #4F75FF;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .links-section a:hover {
            color: #3d5ecc;
            text-decoration: underline;
        }

        @media (max-width: 640px) {
            .form-container {
                padding: 2rem 1.5rem;
            }

            h2 {
                font-size: 1.5rem;
            }

            .nav-links {
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container navbar">
            <a href="../index.php" class="logo">
                Study Buddy
            </a>
            <nav>
                <ul class="nav-links">
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="register.php" class="btn-primary-outline">Sign Up</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="form-container" role="region" aria-labelledby="form-title">
            <h2 id="form-title">Welcome Back</h2>
            <p class="subtitle">Sign in to your Study Buddy account</p>

            <div id="form-messages">
                <?php if ($error): ?>
                    <div class="alert alert-error" role="alert" aria-live="polite">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert" aria-live="polite">
                        <i class="fas fa-check-circle"></i>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <form method="POST" action="" id="login-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">

                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input" 
                           autocomplete="email" placeholder="john@example.com" required
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <div id="email-error" class="form-error-message" aria-live="assertive"></div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="password-input-group">
                        <input type="password" id="password" name="password" class="form-input" 
                               autocomplete="current-password" placeholder="••••••••" required>
                        <button type="button" class="password-toggle" id="password-toggle-btn" aria-label="Show password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="password-error" class="form-error-message" aria-live="assertive"></div>
                </div>

                <button type="submit" id="submit-btn" class="btn-primary">
                    Sign In
                </button>
            </form>

            <div class="links-section">
                <p class="text-center">
                    Don't have an account? <a href="register.php">Sign up here</a>
                </p>
                <p class="text-center">
                    <a href="forgot-password.php">Forgot your password?</a>
                </p>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.getElementById('password-toggle-btn');
            const loginForm = document.getElementById('login-form');
            const emailInput = document.getElementById('email');
            const emailError = document.getElementById('email-error');
            const passwordError = document.getElementById('password-error');
            const submitButton = document.getElementById('submit-btn');

            // Password Toggle
            if (toggleButton && passwordInput) {
                toggleButton.addEventListener('click', function() {
                    const icon = this.querySelector('i');
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                        this.setAttribute('aria-label', 'Hide password');
                    } else {
                        passwordInput.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                        this.setAttribute('aria-label', 'Show password');
                    }
                });
            }

            // Real-time validation
            emailInput.addEventListener('blur', function() {
                if (this.value && !validateEmail(this.value)) {
                    emailError.textContent = 'Please enter a valid email address.';
                    emailInput.classList.add('input-error');
                } else {
                    emailError.textContent = '';
                    emailInput.classList.remove('input-error');
                }
            });

            emailInput.addEventListener('input', function() {
                if (emailInput.classList.contains('input-error') && validateEmail(this.value)) {
                    emailError.textContent = '';
                    emailInput.classList.remove('input-error');
                }
            });

            passwordInput.addEventListener('input', function() {
                if (passwordInput.classList.contains('input-error') && this.value.trim() !== '') {
                    passwordError.textContent = '';
                    passwordInput.classList.remove('input-error');
                }
            });

            // Form Validation & Submission
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    let isValid = true;
                    
                    emailError.textContent = '';
                    passwordError.textContent = '';
                    emailInput.classList.remove('input-error');
                    passwordInput.classList.remove('input-error');
                    
                    if (!validateEmail(emailInput.value)) {
                        emailError.textContent = 'Please enter a valid email address.';
                        emailInput.classList.add('input-error');
                        isValid = false;
                    }
                    
                    if (passwordInput.value.trim() === '') {
                        passwordError.textContent = 'Please enter your password.';
                        passwordInput.classList.add('input-error');
                        isValid = false;
                    }

                    if (!isValid) {
                        e.preventDefault();
                    } else {
                        submitButton.disabled = true;
                        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
                    }
                });
            }
            // regex boi
            function validateEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(String(email).toLowerCase());
            }

            // Smooth input interactions
            const inputs = document.querySelectorAll('.form-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.transform = 'translateY(-1px)';
                });
                input.addEventListener('blur', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>