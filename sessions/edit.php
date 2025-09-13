<?php
require_once '../config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_current_user();
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
           END as partner_name,
           CASE 
               WHEN m.student_id = ? THEN u2.id
               ELSE u1.id
           END as partner_id
    FROM sessions s
    JOIN matches m ON s.match_id = m.id
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE s.id = ? AND (m.student_id = ? OR m.mentor_id = ?) AND s.status = 'scheduled'
");
$session_stmt->execute([$user['id'], $user['id'], $session_id, $user['id'], $user['id']]);
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
        $action = $_POST['action'];
        
        if ($action === 'update') {
            $session_date = $_POST['session_date'];
            $start_time = $_POST['start_time'];
            $end_time = $_POST['end_time'];
            $location = sanitize_input($_POST['location']);
            $notes = sanitize_input($_POST['notes']);
            
            // Validation
            if (empty($session_date) || empty($start_time) || empty($end_time)) {
                $error = 'Please fill in all required fields.';
            } elseif (strtotime($session_date) < strtotime(date('Y-m-d'))) {
                $error = 'Session date cannot be in the past.';
            } elseif (strtotime($start_time) >= strtotime($end_time)) {
                $error = 'End time must be after start time.';
            } else {
                try {
                    $stmt = $db->prepare("
                        UPDATE sessions 
                        SET session_date = ?, start_time = ?, end_time = ?, location = ?, notes = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$session_date, $start_time, $end_time, $location, $notes, $session_id]);
                    
                    $success = 'Session updated successfully!';
                    
                    // Refresh session data
                    $session['session_date'] = $session_date;
                    $session['start_time'] = $start_time;
                    $session['end_time'] = $end_time;
                    $session['location'] = $location;
                    $session['notes'] = $notes;
                    
                } catch (Exception $e) {
                    $error = 'Failed to update session. Please try again.';
                }
            }
        } elseif ($action === 'cancel') {
            try {
                $stmt = $db->prepare("UPDATE sessions SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$session_id]);
                
                // Log activity
                $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'session_cancelled', ?, ?)");
                $log_stmt->execute([$user['id'], json_encode(['session_id' => $session_id]), $_SERVER['REMOTE_ADDR']]);
                
                redirect('index.php');
                
            } catch (Exception $e) {
                $error = 'Failed to cancel session. Please try again.';
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
    <title>Edit Session - StudyConnect</title>
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
            <div class="form-container" style="max-width: 600px;">
                <h2 class="text-center mb-4">Edit Session</h2>
                
                <!-- Session Details -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div style="display: flex; align-items: center; gap: 1rem;">
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
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="update">
                    
                    <div class="form-group">
                        <label for="session_date" class="form-label">Session Date</label>
                        <input type="date" id="session_date" name="session_date" class="form-input" required
                               min="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo $session['session_date']; ?>">
                    </div>
                    
                    <div class="grid grid-cols-2" style="gap: 1rem;">
                        <div class="form-group">
                            <label for="start_time" class="form-label">Start Time</label>
                            <input type="time" id="start_time" name="start_time" class="form-input" required
                                   value="<?php echo $session['start_time']; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="end_time" class="form-label">End Time</label>
                            <input type="time" id="end_time" name="end_time" class="form-input" required
                                   value="<?php echo $session['end_time']; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" id="location" name="location" class="form-input" 
                               placeholder="e.g., Library, Online (Zoom), Coffee Shop"
                               value="<?php echo htmlspecialchars($session['location'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="notes" class="form-label">Session Notes (Optional)</label>
                        <textarea id="notes" name="notes" class="form-input" rows="4" 
                                  placeholder="What topics will you cover? Any materials to bring?"><?php echo htmlspecialchars($session['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">Update Session</button>
                        <a href="index.php" class="btn btn-secondary" style="flex: 1; text-align: center;">Cancel</a>
                    </div>
                </form>
                
                <!-- Cancel Session -->
                <div class="mt-4" style="border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                    <h4 class="mb-3">Cancel Session</h4>
                    <p class="text-secondary mb-3">If you need to cancel this session, click the button below. Your partner will be notified.</p>
                    
                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to cancel this session?');">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn btn-danger">Cancel Session</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
