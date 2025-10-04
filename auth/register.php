<?php
require_once '../config/config.php';

$error = '';
$success = '';
$role = isset($_GET['role']) && in_array($_GET['role'], ['student', 'mentor']) ? $_GET['role'] : 'student';

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
                
                // Create user account
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                try {
                    $db->beginTransaction();
                    
                    $is_verified = 0;
                    
                    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role, first_name, last_name, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $password_hash, $role, $first_name, $last_name, $is_verified]);
                    $user_id = $db->lastInsertId();
                    
                    $referral_details = json_encode(['role' => $role, 'referral_used' => false]);
                    
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
                
                <!-- Removed peer option from role selection -->
                <div class="form-group">
                    <label for="role" class="form-label">I want to join as:</label>
                    <select id="role" name="role" class="form-select" required>
                        <option value="student" <?php echo $role === 'student' ? 'selected' : ''; ?>>Student (Looking for help)</option>
                        <option value="mentor" <?php echo $role === 'mentor' ? 'selected' : ''; ?>>Mentor (Ready to help others)</option>
                    </select>
                    <small class="text-secondary">Students can upgrade to Peer status later from their profile</small>
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
                
                <!-- Removed referral code field from registration -->
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
            </form>
            
            <div class="text-center mt-4">
                <p class="text-secondary">Already have an account? <a href="login.php" class="text-primary">Sign in here</a></p>
            </div>
        </div>
    </main>
</body>
</html>
