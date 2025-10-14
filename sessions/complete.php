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
            
            $commission_stmt = $db->prepare("
                SELECT m.mentor_id, u.hourly_rate, s.start_time, s.end_time, s.session_date,
                       CONCAT(u.first_name, ' ', u.last_name) as mentor_name
                FROM sessions s
                JOIN matches m ON s.match_id = m.id
                JOIN users u ON m.mentor_id = u.id
                WHERE s.id = ?
            ");
            $commission_stmt->execute([$session_id]);
            $commission_data = $commission_stmt->fetch();
            
            // Debug: Log commission data
            error_log("Commission Debug - Session ID: $session_id");
            error_log("Commission Debug - Mentor ID: " . ($commission_data['mentor_id'] ?? 'NULL'));
            error_log("Commission Debug - Hourly Rate: " . ($commission_data['hourly_rate'] ?? 'NULL'));
            error_log("Commission Debug - Start Time: " . ($commission_data['start_time'] ?? 'NULL'));
            error_log("Commission Debug - End Time: " . ($commission_data['end_time'] ?? 'NULL'));
            
            if ($commission_data) {
                if ($commission_data['hourly_rate'] > 0) {
                    $start = new DateTime($commission_data['session_date'] . ' ' . $commission_data['start_time']);
                    $end = new DateTime($commission_data['session_date'] . ' ' . $commission_data['end_time']);
                    $interval = $start->diff($end);
                    $duration_hours = $interval->h + ($interval->i / 60);
                    
                    // Calculate session amount and commission (10%)
                    $session_amount = $commission_data['hourly_rate'] * $duration_hours;
                    $commission_amount = $session_amount * 0.10;
                    
                    error_log("Commission Debug - Duration: $duration_hours hours");
                    error_log("Commission Debug - Session Amount: $session_amount");
                    error_log("Commission Debug - Commission Amount: $commission_amount");
                    
                    try {
                        $insert_commission = $db->prepare("
                            INSERT INTO commission_payments 
                            (mentor_id, session_id, session_amount, commission_amount, commission_percentage, payment_status, created_at) 
                            VALUES (?, ?, ?, ?, 10.00, 'pending', NOW())
                        ");
                        $insert_commission->execute([
                            $commission_data['mentor_id'],
                            $session_id,
                            $session_amount,
                            $commission_amount
                        ]);
                        error_log("Commission created successfully for Mentor ID {$commission_data['mentor_id']}, Amount: ₱{$commission_amount}");
                        $success = 'Session marked as completed! Commission payment of ₱' . number_format($commission_amount, 2) . ' has been recorded.';
                    } catch (PDOException $e) {
                        error_log("Commission creation error: " . $e->getMessage());
                        $error = 'Session marked complete but commission creation failed: ' . $e->getMessage();
                    }
                } else {
                    error_log("No commission created: Mentor '{$commission_data['mentor_name']}' has no hourly rate set (Rate: " . ($commission_data['hourly_rate'] ?? 'NULL') . ")");
                    $success = 'Session marked as completed! Note: No commission was created because the mentor has not set an hourly rate.';
                }
            } else {
                error_log("No commission data found for session ID: $session_id");
                $success = 'Session marked as completed! Note: Could not retrieve commission data.';
            }
            
            // Log activity
            $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'session_completed', ?, ?)");
            $log_stmt->execute([$user['id'], json_encode(['session_id' => $session_id]), $_SERVER['REMOTE_ADDR']]);
            
            $db->commit();
            
            if (!$error && $success) {
                header("refresh:3;url=index.php");
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Failed to mark session as completed: ' . $e->getMessage();
            error_log("Session completion error: " . $e->getMessage());
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
                    <!-- Added Commission Payments link for mentors -->
                    <?php if ($user['role'] === 'mentor' || $user['role'] === 'peer'): ?>
                        <li><a href="../profile/commission-payments.php">Commission Payments</a></li>
                    <?php endif; ?>
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
