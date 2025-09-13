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

$error = '';
$success = '';
$match_id = isset($_GET['match_id']) ? (int)$_GET['match_id'] : 0;

$db = getDB();

// Get user's active matches
$matches_query = "
    SELECT m.id, m.subject,
           CASE 
               WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
               ELSE CONCAT(u1.first_name, ' ', u1.last_name)
           END as partner_name,
           CASE 
               WHEN m.student_id = ? THEN u2.id
               ELSE u1.id
           END as partner_id
    FROM matches m
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE (m.student_id = ? OR m.mentor_id = ?) 
    AND m.status = 'accepted'
    ORDER BY partner_name
";

$stmt = $db->prepare($matches_query);
$stmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
$matches = $stmt->fetchAll();

if (empty($matches)) {
    redirect('../matches/find.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $selected_match_id = (int)$_POST['match_id'];
        $session_date = $_POST['session_date'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $location = sanitize_input($_POST['location']);
        $notes = sanitize_input($_POST['notes']);
        
        // Validation
        if (empty($selected_match_id) || empty($session_date) || empty($start_time) || empty($end_time)) {
            $error = 'Please fill in all required fields.';
        } elseif (strtotime($session_date) < strtotime(date('Y-m-d'))) {
            $error = 'Session date cannot be in the past.';
        } elseif (strtotime($start_time) >= strtotime($end_time)) {
            $error = 'End time must be after start time.';
        } else {
            // Verify user is part of the selected match
            $verify_stmt = $db->prepare("SELECT id FROM matches WHERE id = ? AND (student_id = ? OR mentor_id = ?) AND status = 'accepted'");
            $verify_stmt->execute([$selected_match_id, $user['id'], $user['id']]);
            
            if (!$verify_stmt->fetch()) {
                $error = 'Invalid match selection.';
            } else {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO sessions (match_id, session_date, start_time, end_time, location, notes) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$selected_match_id, $session_date, $start_time, $end_time, $location, $notes]);
                    
                    // Log activity
                    $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'session_scheduled', ?, ?)");
                    $log_stmt->execute([$user['id'], json_encode(['match_id' => $selected_match_id, 'date' => $session_date]), $_SERVER['REMOTE_ADDR']]);
                    
                    $success = 'Session scheduled successfully!';
                    
                    // Redirect after 2 seconds
                    header("refresh:2;url=index.php");
                    
                } catch (Exception $e) {
                    $error = 'Failed to schedule session. Please try again.';
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
    <title>Schedule Session - StudyConnect</title>
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
                <h2 class="text-center mb-4">Schedule Study Session</h2>
                <p class="text-center text-secondary mb-4">Plan a study session with one of your matched partners.</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-group">
                        <label for="match_id" class="form-label">Study Partner</label>
                        <select id="match_id" name="match_id" class="form-select" required>
                            <option value="">Select a partner</option>
                            <?php foreach ($matches as $match): ?>
                                <option value="<?php echo $match['id']; ?>" 
                                        <?php echo $match_id == $match['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($match['partner_name']); ?> - <?php echo htmlspecialchars($match['subject']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="session_date" class="form-label">Session Date</label>
                        <input type="date" id="session_date" name="session_date" class="form-input" required
                               min="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo isset($_POST['session_date']) ? $_POST['session_date'] : ''; ?>">
                    </div>
                    
                    <div class="grid grid-cols-2" style="gap: 1rem;">
                        <div class="form-group">
                            <label for="start_time" class="form-label">Start Time</label>
                            <input type="time" id="start_time" name="start_time" class="form-input" required
                                   value="<?php echo isset($_POST['start_time']) ? $_POST['start_time'] : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="end_time" class="form-label">End Time</label>
                            <input type="time" id="end_time" name="end_time" class="form-input" required
                                   value="<?php echo isset($_POST['end_time']) ? $_POST['end_time'] : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" id="location" name="location" class="form-input" 
                               placeholder="e.g., Library, Online (Zoom), Coffee Shop"
                               value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="notes" class="form-label">Session Notes (Optional)</label>
                        <textarea id="notes" name="notes" class="form-input" rows="4" 
                                  placeholder="What topics will you cover? Any materials to bring?"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">Schedule Session</button>
                        <a href="index.php" class="btn btn-secondary" style="flex: 1; text-align: center;">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Set default times if empty
        document.addEventListener('DOMContentLoaded', function() {
            const startTime = document.getElementById('start_time');
            const endTime = document.getElementById('end_time');
            
            if (!startTime.value) {
                startTime.value = '14:00'; // 2:00 PM
            }
            
            if (!endTime.value) {
                endTime.value = '16:00'; // 4:00 PM
            }
            
            // Auto-adjust end time when start time changes
            startTime.addEventListener('change', function() {
                if (this.value && !endTime.value) {
                    const start = new Date('2000-01-01 ' + this.value);
                    start.setHours(start.getHours() + 2); // Default 2-hour session
                    endTime.value = start.toTimeString().slice(0, 5);
                }
            });
        });
    </script>
</body>
</html>
