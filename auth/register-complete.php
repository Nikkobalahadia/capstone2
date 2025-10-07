<?php
require_once '../config/config.php';

// Check if email is verified
if (!isset($_SESSION['verified_email'])) {
    redirect('login-otp.php');
}

$error = '';
$success = '';
$email = $_SESSION['verified_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Sanitize inputs
        $username = sanitize_input($_POST['username']);
        $first_name = sanitize_input($_POST['first_name']);
        $last_name = sanitize_input($_POST['last_name']);
        $role = sanitize_input($_POST['role']);
        
        // Validation
        if (empty($username) || empty($first_name) || empty($last_name)) {
            $error = 'Please fill in all required fields.';
        } elseif (!in_array($role, ['student', 'mentor'])) {
            $error = 'Invalid role selected.';
        } else {
            $db = getDB();
            
            // Check if username already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username already exists. Please choose another.';
            } else {
                try {
                    $db->beginTransaction();
                    
                    // Create user account (no password needed for OTP-only accounts)
                    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role, first_name, last_name, is_verified) VALUES (?, ?, ?, ?, ?, ?, 1)");
                    // Use a random hash as placeholder since they're using OTP login
                    $placeholder_hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
                    $stmt->execute([$username, $email, $placeholder_hash, $role, $first_name, $last_name]);
                    $user_id = $db->lastInsertId();
                    
                    // Log registration
                    $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'register_otp', ?, ?)");
                    $log_stmt->execute([$user_id, json_encode(['method' => 'otp', 'role' => $role]), $_SERVER['REMOTE_ADDR']]);
                    
                    $db->commit();
                    
                    // Auto-login the user
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = $role;
                    $_SESSION['full_name'] = $first_name . ' ' . $last_name;
                    
                    // Clear verified email
                    unset($_SESSION['verified_email']);
                    
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
    <title>Complete Your Profile - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="../index.php" class="logo">StudyConnect</a>
            </nav>
        </div>
    </header>

    <main style="min-height: 80vh; display: flex; align-items: center;">
        <div class="form-container" style="max-width: 500px;">
            <h2 class="text-center mb-4">Complete Your Profile</h2>
            <p class="text-center text-secondary mb-4">
                Email verified: <strong><?php echo htmlspecialchars($email); ?></strong>
            </p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-group">
                    <label for="role" class="form-label">I want to join as:</label>
                    <select id="role" name="role" class="form-select" required>
                        <option value="student">Student (Looking for help)</option>
                        <option value="mentor">Mentor (Ready to help others)</option>
                    </select>
                    <small class="text-secondary">Students can upgrade to Peer status later</small>
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
                    <small class="text-secondary">Choose a unique username for your profile</small>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Complete Registration</button>
            </form>
        </div>
    </main>
</body>
</html>
