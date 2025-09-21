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
                    // Log successful login
                    $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'login', ?, ?)");
                    $log_stmt->execute([$user['id'], json_encode(['success' => true]), $_SERVER['REMOTE_ADDR']]);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    
                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        redirect('admin/dashboard.php');
                    } else {
                        redirect('dashboard.php');
                    }
                }
            } else {
                $error = 'Invalid email or password.';
                
                // Log failed login attempt
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
            <h2 class="text-center mb-4">Welcome Back</h2>
            <p class="text-center text-secondary mb-4">Sign in to your StudyConnect account</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-input" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Sign In</button>
            </form>
            
            <div class="text-center mt-4">
                <p class="text-secondary">Don't have an account? <a href="register.php" class="text-primary">Sign up here</a></p>
                <p class="text-secondary mt-2"><a href="forgot-password.php" class="text-primary">Forgot your password?</a></p>
            </div>
        </div>
    </main>
</body>
</html>
