<?php
require_once '../config/config.php';
require_once '../config/notification_helper.php';

if (!is_logged_in()) redirect('auth/login.php');
$user = get_logged_in_user();
if (!$user) redirect('auth/login.php');

$session_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$session_id) redirect('index.php');

$db = getDB();

// Get session details and verify user access
$session_stmt = $db->prepare("
    SELECT s.*, m.subject, m.student_id, m.mentor_id,
           CASE WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
                ELSE CONCAT(u1.first_name, ' ', u1.last_name) END as partner_name,
           CASE WHEN m.student_id = ? THEN u2.id ELSE u1.id END as partner_id
    FROM sessions s
    JOIN matches m ON s.match_id = m.id
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE s.id = ? AND (m.student_id = ? OR m.mentor_id = ?)
");
$session_stmt->execute([$user['id'], $user['id'], $session_id, $user['id'], $user['id']]);
$session = $session_stmt->fetch();

if (!$session) redirect('index.php');

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            $db->beginTransaction();
            
            if ($action === 'mark_completed') {
                // Mark session as completed
                $update_stmt = $db->prepare("UPDATE sessions SET status = 'completed', updated_at = NOW() WHERE id = ?");
                $update_stmt->execute([$session_id]);
                
                if ($update_stmt->rowCount() > 0) {
                    
                    // --- START COMMISSION LOGIC (Copied from complete.php) ---
                    $commission_stmt = $db->prepare("
                        SELECT m.mentor_id, u.hourly_rate, u.role, s.start_time, s.end_time, s.session_date
                        FROM sessions s
                        JOIN matches m ON s.match_id = m.id
                        JOIN users u ON m.mentor_id = u.id
                        WHERE s.id = ?
                    ");
                    $commission_stmt->execute([$session_id]);
                    $commission_data = $commission_stmt->fetch();
                    
                    if ($commission_data) {
                        if ($commission_data['role'] === 'mentor' && $commission_data['hourly_rate'] > 0) {
                            $start = new DateTime($commission_data['session_date'] . ' ' . $commission_data['start_time']);
                            $end = new DateTime($commission_data['session_date'] . ' ' . $commission_data['end_time']);
                            $interval = $start->diff($end);
                            $duration_hours = $interval->h + ($interval->i / 60);
                            
                            $session_amount = $commission_data['hourly_rate'] * $duration_hours;
                            $commission_amount = $session_amount * 0.10;
                            
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
                            } catch (PDOException $e) {
                                // Throw exception to be caught by the outer try/catch block
                                throw new Exception('Session marked complete but commission creation failed: ' . $e->getMessage());
                            }
                        }
                        // No 'else' needed for peer or mentor with 0 rate
                    } else {
                        // Log a warning but don't stop the transaction
                        error_log("Commission creation warning: Could not retrieve commission data for session $session_id.");
                    }
                    // --- END COMMISSION LOGIC ---

                    // Create notification for partner
                    create_notification(
                        $session['partner_id'],
                        'session_completed',
                        'Session Completed',
                        'A session for ' . $session['subject'] . ' has been marked as completed.',
                        '../sessions/rate.php?id=' . $session_id
                    );
                    
                    // Log activity
                    $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'session_completed', ?, ?)");
                    $log_stmt->execute([$user['id'], json_encode(['session_id' => $session_id]), $_SERVER['REMOTE_ADDR']]);
                    
                    $db->commit();
                    $success = 'Session marked as completed successfully! Redirecting to rate the session...';
                    header("refresh:2;url=rate.php?id=" . $session_id);
                } else {
                    throw new Exception('Failed to update session status');
                }
                
            } elseif ($action === 'update') {
                // Update session details
                $session_date = sanitize_input($_POST['session_date']);
                $start_time = sanitize_input($_POST['start_time']);
                $end_time = sanitize_input($_POST['end_time']);
                $location = sanitize_input($_POST['location']);
                $notes = sanitize_input($_POST['notes']);
                
                // Validation
                if (empty($session_date) || empty($start_time) || empty($end_time)) {
                    throw new Exception('Please fill in all required fields.');
                }
                
                if (strtotime($end_time) <= strtotime($start_time)) {
                    throw new Exception('End time must be after start time.');
                }
                
                $update_stmt = $db->prepare("
                    UPDATE sessions 
                    SET session_date = ?, start_time = ?, end_time = ?, location = ?, notes = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $update_stmt->execute([$session_date, $start_time, $end_time, $location, $notes, $session_id]);
                
                if ($update_stmt->rowCount() > 0) {
                    // Create notification for partner
                    create_notification(
                        $session['partner_id'],
                        'session_updated',
                        'Session Updated',
                        'A session for ' . $session['subject'] . ' has been updated.',
                        '../sessions/index.php'
                    );
                    
                    $db->commit();
                    $success = 'Session updated successfully!';
                    
                    // Refresh session data
                    $session_stmt->execute([$user['id'], $user['id'], $session_id, $user['id'], $user['id']]);
                    $session = $session_stmt->fetch();
                } else {
                    throw new Exception('No changes were made to the session.');
                }
                
            } elseif ($action === 'cancel') {
                // Cancel session
                $cancel_reason = sanitize_input($_POST['cancel_reason']);
                
                if (empty($cancel_reason)) {
                    throw new Exception('Please provide a reason for cancellation.');
                }
                
                $update_stmt = $db->prepare("
                    UPDATE sessions 
                    SET status = 'cancelled', cancellation_reason = ?, cancelled_by = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $update_stmt->execute([$cancel_reason, $user['id'], $session_id]);
                
                if ($update_stmt->rowCount() > 0) {
                    // Create notification for partner
                    create_notification(
                        $session['partner_id'],
                        'session_cancelled',
                        'Session Cancelled',
                        'A session for ' . $session['subject'] . ' has been cancelled.',
                        '../sessions/index.php'
                    );
                    
                    $db->commit();
                    $success = 'Session cancelled successfully!';
                    header("refresh:2;url=index.php");
                } else {
                    throw new Exception('Failed to cancel session');
                }
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = $e->getMessage();
            error_log("Session edit error: " . $e->getMessage()); // Log for debugging
        }
    }
}

$unread_notifications = get_unread_count($user['id']);

// Check if session has already ended (for completion notice)
$session_ended = strtotime($session['session_date'] . ' ' . $session['end_time']) <= time();
$can_mark_completed = in_array($session['status'], ['scheduled']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Edit Session - Study Buddy</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --text-primary: #1a1a1a;
            --text-secondary: #666;
            --border-color: #e5e5e5;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .header {
            background: white;
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: var(--primary-color);
        }

        main {
            padding: 2rem 0;
        }

        .form-container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: var(--shadow-lg);
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.9375rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .btn {
            padding: 0.875rem 1.75rem;
            border-radius: 10px;
            border: none;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: var(--text-primary);
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .button-group .btn {
            flex: 1;
        }

        .alert {
            padding: 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #fecaca;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #a7f3d0;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 2px solid #93c5fd;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 2px solid #fcd34d;
        }

        .info-box {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 2px solid #60a5fa;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .info-box h4 {
            color: var(--primary-color);
            font-size: 1rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-box p {
            color: #1e40af;
            font-size: 0.9375rem;
            line-height: 1.6;
            margin: 0;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
        }

        .modal-header {
            margin-bottom: 1.5rem;
        }

        .modal-header h3 {
            font-size: 1.5rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        @media (max-width: 768px) {
            .form-container {
                padding: 1.5rem;
            }

            .button-group {
                flex-direction: column;
            }

            .nav-links {
                display: none;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="../dashboard.php" class="logo">
                    <i class="fas fa-book-open"></i> Study Buddy
                </a>
                <ul class="nav-links">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <li><a href="index.php">Sessions</a></li>
                    <li><a href="../auth/logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="form-container">
                <div class="page-header">
                    <h1><i class="fas fa-edit"></i> Edit Session</h1>
                    <p style="color: var(--text-secondary);">Update session details or manage session status</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($can_mark_completed && !$session_ended): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <span><strong>Note:</strong> You can mark this session as completed even if it hasn't occurred yet. This is useful if you completed the session early or want to close it out.</span>
                    </div>
                <?php endif; ?>

                <?php if ($can_mark_completed && $session_ended): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-clock"></i>
                        <span><strong>Session Ended:</strong> This session has passed its scheduled time. Please mark it as completed or cancel it.</span>
                    </div>
                <?php endif; ?>

                <div class="info-box">
                    <h4><i class="fas fa-user"></i> Session Partner</h4>
                    <p><strong><?php echo htmlspecialchars($session['partner_name']); ?></strong> - <?php echo htmlspecialchars($session['subject']); ?></p>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="update">

                    <div class="form-group">
                        <label for="session_date" class="form-label">
                            <i class="fas fa-calendar"></i> Session Date
                        </label>
                        <input type="date" 
                               id="session_date" 
                               name="session_date" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($session['session_date']); ?>" 
                               required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="start_time" class="form-label">
                                <i class="fas fa-clock"></i> Start Time
                            </label>
                            <input type="time" 
                                   id="start_time" 
                                   name="start_time" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($session['start_time']); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="end_time" class="form-label">
                                <i class="fas fa-clock"></i> End Time
                            </label>
                            <input type="time" 
                                   id="end_time" 
                                   name="end_time" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($session['end_time']); ?>" 
                                   required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="location" class="form-label">
                            <i class="fas fa-map-marker-alt"></i> Location
                        </label>
                        <input type="text" 
                               id="location" 
                               name="location" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($session['location'] ?? ''); ?>" 
                               placeholder="e.g., Library, Online, Room 101">
                    </div>

                    <div class="form-group">
                        <label for="notes" class="form-label">
                            <i class="fas fa-sticky-note"></i> Notes
                        </label>
                        <textarea id="notes" 
                                  name="notes" 
                                  class="form-input" 
                                  rows="4" 
                                  placeholder="Add any additional notes or instructions..."><?php echo htmlspecialchars($session['notes'] ?? ''); ?></textarea>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>

                <?php if ($can_mark_completed): ?>
                    <hr style="margin: 2rem 0; border: none; border-top: 2px dashed var(--border-color);">
                    
                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to mark this session as completed? You will be redirected to rate the session.');">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="mark_completed">
                        
                        <button type="submit" class="btn btn-success" style="width: 100%;">
                            <i class="fas fa-check-circle"></i> Mark as Completed
                        </button>
                    </form>

                    <button onclick="openCancelModal()" class="btn btn-danger" style="width: 100%; margin-top: 1rem;">
                        <i class="fas fa-times-circle"></i> Cancel Session
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: var(--danger-color);"></i> Cancel Session</h3>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="cancel">
                
                <div class="form-group">
                    <label for="cancel_reason" class="form-label">Reason for Cancellation</label>
                    <textarea id="cancel_reason" 
                              name="cancel_reason" 
                              class="form-input" 
                              rows="4" 
                              placeholder="Please provide a reason for cancelling this session..." 
                              required></textarea>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times-circle"></i> Confirm Cancel
                    </button>
                    <button type="button" onclick="closeCancelModal()" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Go Back
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCancelModal() {
            document.getElementById('cancelModal').classList.add('active');
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('cancelModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCancelModal();
            }
        });

        // Validate end time is after start time
        document.getElementById('end_time').addEventListener('change', function() {
            const startTime = document.getElementById('start_time').value;
            const endTime = this.value;
            
            if (startTime && endTime && endTime <= startTime) {
                alert('End time must be after start time');
                this.value = '';
            }
        });
    </script>
</body>
</html>