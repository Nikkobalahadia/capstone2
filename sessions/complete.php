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

$session_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$session_id) {
    redirect('index.php');
}

$db = getDB();

// Get session details and verify user access
$session_stmt = $db->prepare("
    SELECT s.*, m.subject,
           CASE 
               WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
               ELSE CONCAT(u1.first_name, ' ', u1.last_name)
           END as partner_name
    FROM sessions s
    JOIN matches m ON s.match_id = m.id
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE s.id = ? AND (m.student_id = ? OR m.mentor_id = ?) AND s.status = 'scheduled'
");
$session_stmt->execute([$user['id'], $session_id, $user['id'], $user['id']]);
$session = $session_stmt->fetch();

if (!$session) {
    redirect('index.php');
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        try {
            $db->beginTransaction();
            
            // Update session status to completed
            $stmt = $db->prepare("UPDATE sessions SET status = 'completed' WHERE id = ?");
            $stmt->execute([$session_id]);
            
            // Log activity
            $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'session_completed', ?, ?)");
            $log_stmt->execute([$user['id'], json_encode(['session_id' => $session_id]), $_SERVER['REMOTE_ADDR']]);
            
            $db->commit();
            
            $success = 'Session marked as completed successfully!';
            
            // Redirect after 2 seconds
            header("refresh:2;url=index.php");
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Failed to mark session as completed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Session - StudyConnect</title>
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
                    <li><a href="../profile/index.php">Profile</a></li>
                    <li><a href="../matches/index.php">Matches</a></li>
                    <li><a href="index.php">Sessions</a></li>
                    <li><a href="../messages/index.php">Messages</a></li>
                    <li><a href="../auth/logout.php" class="btn btn-outline">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="container">
            <div class="form-container" style="max-width: 500px;">
                <h2 class="text-center mb-4">Mark Session as Complete</h2>
                
                <!-- Session Details -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                            <div style="width: 50px; height: 50px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                <?php echo strtoupper(substr($session['partner_name'], 0, 2)); ?>
                            </div>
                            <div>
                                <h4 class="font-semibold"><?php echo htmlspecialchars($session['partner_name']); ?></h4>
                                <div class="text-sm text-secondary">
                                    <?php echo htmlspecialchars($session['subject']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-sm text-secondary">
                            <strong>Date:</strong> <?php echo date('l, M j, Y', strtotime($session['session_date'])); ?><br>
                            <strong>Time:</strong> <?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?>
                            <?php if ($session['location']): ?>
                                <br><strong>Location:</strong> <?php echo htmlspecialchars($session['location']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php else: ?>
                    <div class="alert alert-info mb-4">
                        <strong>Confirm Session Completion</strong><br>
                        By marking this session as complete, you confirm that the study session took place as scheduled. This action cannot be undone.
                    </div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div style="display: flex; gap: 1rem;">
                            <button type="submit" class="btn btn-success" style="flex: 1;" onclick="return confirm('Are you sure you want to mark this session as completed?');">Mark as Complete</button>
                            <a href="index.php" class="btn btn-secondary" style="flex: 1; text-align: center;">Cancel</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
