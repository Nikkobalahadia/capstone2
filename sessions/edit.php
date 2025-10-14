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
            $cancellation_reason = sanitize_input($_POST['cancellation_reason'] ?? 'User cancelled session');
            
            try {
                $stmt = $db->prepare("
                    UPDATE sessions 
                    SET status = 'cancelled', 
                        cancellation_reason = ?, 
                        cancelled_by = ?, 
                        cancelled_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$cancellation_reason, $user['id'], $session_id]);
                
                // Log activity
                $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'session_cancelled', ?, ?)");
                $log_stmt->execute([$user['id'], json_encode(['session_id' => $session_id, 'reason' => $cancellation_reason]), $_SERVER['REMOTE_ADDR']]);
                
                redirect('index.php');
                
            } catch (Exception $e) {
                $error = 'Failed to cancel session. Please try again.';
            }
        } elseif ($action === 'complete') {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("UPDATE sessions SET status = 'completed' WHERE id = ?");
                $stmt->execute([$session_id]);
                
                // Get mentor info and calculate commission
                $commission_stmt = $db->prepare("
                    SELECT m.mentor_id, u.hourly_rate, s.start_time, s.end_time
                    FROM sessions s
                    JOIN matches m ON s.match_id = m.id
                    JOIN users u ON m.mentor_id = u.id
                    WHERE s.id = ?
                ");
                $commission_stmt->execute([$session_id]);
                $commission_data = $commission_stmt->fetch();
                
                if ($commission_data && $commission_data['hourly_rate'] > 0) {
                    // Calculate duration in hours
                    $start = new DateTime($commission_data['start_time']);
                    $end = new DateTime($commission_data['end_time']);
                    $duration_hours = $end->diff($start)->h + ($end->diff($start)->i / 60);
                    
                    // Calculate session amount and commission (10%)
                    $session_amount = $commission_data['hourly_rate'] * $duration_hours;
                    $commission_amount = $session_amount * 0.10;
                    
                    // Insert commission payment record
                    $insert_commission = $db->prepare("
                        INSERT INTO commission_payments 
                        (mentor_id, session_id, session_amount, amount, commission_rate, status, created_at) 
                        VALUES (?, ?, ?, ?, 0.10, 'pending', NOW())
                    ");
                    $insert_commission->execute([
                        $commission_data['mentor_id'],
                        $session_id,
                        $session_amount,
                        $commission_amount
                    ]);
                }
                
                // Log activity
                $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'session_completed', ?, ?)");
                $log_stmt->execute([$user['id'], json_encode(['session_id' => $session_id]), $_SERVER['REMOTE_ADDR']]);
                
                $db->commit();
                
                redirect('index.php');
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Failed to mark session as completed. Please try again.';
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
                
                <!-- Session Actions -->
                <div class="mt-4" style="border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                    <h4 class="mb-3">Session Actions</h4>
                    
                    <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                        <!-- Mark Complete button -->
                        <form method="POST" action="" style="flex: 1;" onsubmit="return confirm('Are you sure you want to mark this session as completed?');">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="complete">
                            <button type="submit" class="btn btn-success" style="width: 100%;">Mark as Complete</button>
                        </form>
                        
                        <!-- Enhanced cancel form with reason selection -->
                        <div style="flex: 1;">
                            <button type="button" class="btn btn-danger" style="width: 100%;" onclick="showCancelModal()">Cancel Session</button>
                        </div>
                    </div>
                    
                    <p class="text-secondary">Mark the session as complete once it has taken place, or cancel if it cannot happen as scheduled.</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Added cancellation reason modal -->
    <div id="cancelModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 8px; max-width: 500px; width: 90%;">
            <h3 style="margin-bottom: 1rem;">Cancel Session</h3>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="cancel">
                
                <div class="form-group">
                    <label for="cancellation_reason" class="form-label">Reason for cancellation:</label>
                    <select id="cancellation_reason" name="cancellation_reason" class="form-input" required>
                        <option value="">Select a reason...</option>
                        <option value="Student unavailable">Student unavailable</option>
                        <option value="Mentor unavailable">Mentor unavailable</option>
                        <option value="Technical issues">Technical issues</option>
                        <option value="Emergency">Emergency</option>
                        <option value="Rescheduling needed">Rescheduling needed</option>
                        <option value="Personal reasons">Personal reasons</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-danger" style="flex: 1;">Cancel Session</button>
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="hideCancelModal()">Keep Session</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showCancelModal() {
            document.getElementById('cancelModal').style.display = 'block';
        }
        
        function hideCancelModal() {
            document.getElementById('cancelModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('cancelModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideCancelModal();
            }
        });
    </script>
</body>
</html>
