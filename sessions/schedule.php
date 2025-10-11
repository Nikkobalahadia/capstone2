<?php
require_once '../config/config.php';
require_once '../lib/PHPMailer.php';

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
$preselected_date = isset($_GET['date']) ? $_GET['date'] : '';

$db = getDB();

$prefs_stmt = $db->prepare("SELECT * FROM user_reminder_preferences WHERE user_id = ?");
$prefs_stmt->execute([$user['id']]);
$reminder_prefs = $prefs_stmt->fetch();

if (!$reminder_prefs) {
    // Create default preferences
    $db->prepare("INSERT INTO user_reminder_preferences (user_id) VALUES (?)")->execute([$user['id']]);
    $prefs_stmt->execute([$user['id']]);
    $reminder_prefs = $prefs_stmt->fetch();
}

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

$no_matches = empty($matches);

// Handle form submission
if (!$no_matches && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $selected_match_id = (int)$_POST['match_id'];
        $session_date = $_POST['session_date'];
        $start_time = $_POST['start_time'];
        $duration = (int)$_POST['duration'];
        $location = sanitize_input($_POST['location']);
        $notes = sanitize_input($_POST['notes']);
        $send_reminder = isset($_POST['send_reminder']) ? 1 : 0;
        
        // Calculate end time based on duration
        $start_datetime = new DateTime($session_date . ' ' . $start_time);
        $end_datetime = clone $start_datetime;
        $end_datetime->add(new DateInterval('PT' . $duration . 'M'));
        $end_time = $end_datetime->format('H:i:s');
        
        // Validation
        if (empty($selected_match_id) || empty($session_date) || empty($start_time) || empty($duration)) {
            $error = 'Please fill in all required fields.';
        } elseif (strtotime($session_date) < strtotime(date('Y-m-d'))) {
            $error = 'Session date cannot be in the past.';
        } else {
            $match_info_stmt = $db->prepare("
                SELECT student_id, mentor_id 
                FROM matches 
                WHERE id = ? AND (student_id = ? OR mentor_id = ?)
            ");
            $match_info_stmt->execute([$selected_match_id, $user['id'], $user['id']]);
            $match_info = $match_info_stmt->fetch();
            
            if (!$match_info) {
                $error = 'Invalid match selection.';
            } else {
                // Determine partner ID
                $partner_id = ($match_info['student_id'] == $user['id']) 
                    ? $match_info['mentor_id'] 
                    : $match_info['student_id'];
                
                $conflict_check = $db->prepare("
                    SELECT COUNT(*) as conflict_count,
                           GROUP_CONCAT(DISTINCT 
                               CASE 
                                   WHEN m.student_id = ? OR m.mentor_id = ? THEN 'you'
                                   WHEN m.student_id = ? OR m.mentor_id = ? THEN 'partner'
                               END
                           ) as conflicting_parties
                    FROM sessions s
                    JOIN matches m ON s.match_id = m.id
                    WHERE (m.student_id IN (?, ?) OR m.mentor_id IN (?, ?))
                    AND s.session_date = ?
                    AND s.status = 'scheduled'
                    AND (
                        (s.start_time < ? AND s.end_time > ?) OR
                        (s.start_time < ? AND s.end_time > ?) OR
                        (s.start_time >= ? AND s.end_time <= ?)
                    )
                ");
                $conflict_check->execute([
                    $user['id'], $user['id'],
                    $partner_id, $partner_id,
                    $user['id'], $partner_id, $user['id'], $partner_id,
                    $session_date,
                    $end_time, $start_time,
                    $end_time, $start_time,
                    $start_time, $end_time
                ]);
                $conflict = $conflict_check->fetch();
                
                if ($conflict['conflict_count'] > 0) {
                    $conflicting_parties = $conflict['conflicting_parties'];
                    if (strpos($conflicting_parties, 'you') !== false && strpos($conflicting_parties, 'partner') !== false) {
                        $error = 'Both you and your partner already have sessions scheduled during this time. Please choose a different time.';
                    } elseif (strpos($conflicting_parties, 'partner') !== false) {
                        $error = 'Your partner already has a session scheduled during this time. Please choose a different time.';
                    } else {
                        $error = 'You already have a session scheduled during this time. Please choose a different time.';
                    }
                } else {
                    try {
                        $db->beginTransaction();
                        
                        $stmt = $db->prepare("
                            INSERT INTO sessions (match_id, session_date, start_time, end_time, location, notes) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$selected_match_id, $session_date, $start_time, $end_time, $location, $notes]);
                        $session_id = $db->lastInsertId();
                        
                        if ($send_reminder) {
                            $session_datetime = strtotime($session_date . ' ' . $start_time);
                            
                            if ($reminder_prefs['enable_24h_reminder']) {
                                $reminder_time = date('Y-m-d H:i:s', $session_datetime - (24 * 3600));
                                $db->prepare("INSERT INTO session_reminders (session_id, user_id, reminder_type, reminder_time) VALUES (?, ?, '24_hours', ?)")
                                   ->execute([$session_id, $user['id'], $reminder_time]);
                            }
                            
                            if ($reminder_prefs['enable_1h_reminder']) {
                                $reminder_time = date('Y-m-d H:i:s', $session_datetime - 3600);
                                $db->prepare("INSERT INTO session_reminders (session_id, user_id, reminder_type, reminder_time) VALUES (?, ?, '1_hour', ?)")
                                   ->execute([$session_id, $user['id'], $reminder_time]);
                            }
                        }
                        
                        $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'session_scheduled', ?, ?)");
                        $log_stmt->execute([$user['id'], json_encode(['match_id' => $selected_match_id, 'date' => $session_date]), $_SERVER['REMOTE_ADDR']]);
                        
                        $db->commit();
                        
                        $email_sent = false;
                        $email_error = '';
                        
                        try {
                            // Get partner information
                            $partner_stmt = $db->prepare("
                                SELECT u.id, u.email, u.first_name, u.last_name
                                FROM users u
                                WHERE u.id = ?
                            ");
                            $partner_stmt->execute([$partner_id]);
                            $partner = $partner_stmt->fetch();
                            
                            // Get match subject
                            $match_stmt = $db->prepare("SELECT subject FROM matches WHERE id = ?");
                            $match_stmt->execute([$selected_match_id]);
                            $match = $match_stmt->fetch();
                            
                            // Prepare session details for email
                            $session_details = [
                                'subject' => $match['subject'],
                                'date' => $session_date,
                                'start_time' => $start_time,
                                'end_time' => $end_time,
                                'location' => $location,
                                'notes' => $notes,
                                'partner_name' => $partner['first_name'] . ' ' . $partner['last_name']
                            ];
                            
                            error_log("[v0] Attempting to send session notification to: " . $user['email']);
                            $result1 = send_session_notification(
                                $user['email'],
                                $user['first_name'] . ' ' . $user['last_name'],
                                $session_details
                            );
                            error_log("[v0] Email to user result: " . ($result1 ? 'success' : 'failed'));
                            
                            // Send email to partner with updated partner name
                            $session_details['partner_name'] = $user['first_name'] . ' ' . $user['last_name'];
                            error_log("[v0] Attempting to send session notification to: " . $partner['email']);
                            $result2 = send_session_notification(
                                $partner['email'],
                                $partner['first_name'] . ' ' . $partner['last_name'],
                                $session_details
                            );
                            error_log("[v0] Email to partner result: " . ($result2 ? 'success' : 'failed'));
                            
                            $email_sent = $result1 && $result2;
                            
                            // Check if SMTP is configured
                            if (SMTP_USERNAME === 'your-email@gmail.com' || empty(SMTP_USERNAME)) {
                                $email_error = 'SMTP not configured. Emails are being logged but not sent. Configure SMTP in config/email.php to send real emails.';
                                error_log("[v0] " . $email_error);
                            }
                        } catch (Exception $e) {
                            $email_error = 'Failed to send email notifications: ' . $e->getMessage();
                            error_log("[v0] Email error: " . $email_error);
                        }
                        
                        if ($email_sent) {
                            $success = 'Session scheduled successfully! Email notifications sent to both participants.';
                        } else if (!empty($email_error)) {
                            $success = 'Session scheduled successfully! Note: ' . $email_error;
                        } else {
                            $success = 'Session scheduled successfully!';
                        }
                        
                        header("refresh:3;url=history.php");
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = 'Failed to schedule session. Please try again.';
                    }
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
            min-height: 100vh;
        }

        .header {
            background: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #7c3aed;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #4b5563;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: #7c3aed;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: #7c3aed;
            color: white;
        }

        .btn-primary:hover {
            background: #6d28d9;
        }

        .btn-outline {
            background: white;
            color: #7c3aed;
            border: 2px solid #7c3aed;
        }

        .btn-outline:hover {
            background: #7c3aed;
            color: white;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        main {
            padding: 2rem 0;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2rem;
            color: #111827;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #6b7280;
        }

        .schedule-container {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 2rem;
            max-width: 1000px;
            margin: 0 auto;
        }

        .calendar-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            height: fit-content;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .calendar-header h3 {
            font-size: 1.125rem;
            color: #111827;
        }

        .calendar-nav {
            display: flex;
            gap: 0.5rem;
        }

        .calendar-nav button {
            background: #f3f4f6;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .calendar-nav button:hover {
            background: #e5e7eb;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }

        .calendar-day-header {
            text-align: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            padding: 0.5rem 0;
        }

        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .calendar-day:hover {
            background: #f3f4f6;
        }

        .calendar-day.empty {
            cursor: default;
            color: #d1d5db;
        }

        .calendar-day.today {
            background: #ede9fe;
            color: #7c3aed;
            font-weight: 600;
        }

        .calendar-day.selected {
            background: #7c3aed;
            color: white;
            font-weight: 600;
        }

        .form-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .form-card h3 {
            font-size: 1.25rem;
            color: #111827;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            color: #111827;
            font-family: 'Inter', sans-serif;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: #f9fafb;
            border-radius: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-group label {
            font-size: 0.875rem;
            color: #374151;
            cursor: pointer;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .form-actions .btn {
            flex: 1;
            padding: 0.875rem;
            font-size: 1rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .no-matches-card {
            background: white;
            border-radius: 12px;
            padding: 3rem 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
        }

        .no-matches-card h3 {
            font-size: 1.5rem;
            color: #111827;
            margin-bottom: 1rem;
        }

        .no-matches-card p {
            color: #6b7280;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="../dashboard.php" class="logo">SessionSync</a>
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

    <main>
        <div class="container">
            <div class="page-header">
                <h1>Schedule New Session</h1>
                <p>Plan your next study session with a partner</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error" style="max-width: 1000px; margin: 0 auto 1.5rem;"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" style="max-width: 1000px; margin: 0 auto 1.5rem;"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($no_matches): ?>
                <div class="no-matches-card">
                    <h3>No Study Partners Yet</h3>
                    <p>You need to connect with a study partner before scheduling a session.</p>
                    <a href="../matches/find.php" class="btn btn-primary">Find a Study Partner</a>
                </div>
            <?php else: ?>
                <div class="schedule-container">
                    <div class="calendar-card">
                        <div class="calendar-header">
                            <h3 id="calendarMonth">October 2025</h3>
                            <div class="calendar-nav">
                                <button onclick="previousMonth()">‹</button>
                                <button onclick="nextMonth()">›</button>
                            </div>
                        </div>
                        <div class="calendar-grid" id="calendarGrid">
                             Calendar will be generated by JavaScript 
                        </div>
                    </div>

                    <div class="form-card">
                        <h3>Session Details</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="session_date" id="session_date" value="<?php echo $preselected_date ?: date('Y-m-d'); ?>">
                            
                            <div class="form-group">
                                <label class="form-label">Session Title</label>
                                <select name="match_id" class="form-select" required>
                                    <option value="">Select a study partner</option>
                                    <?php foreach ($matches as $match): ?>
                                        <option value="<?php echo $match['id']; ?>" <?php echo $match_id == $match['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($match['subject']); ?> with <?php echo htmlspecialchars($match['partner_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Time</label>
                                    <input type="time" name="start_time" class="form-input" value="14:00" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Duration (min)</label>
                                    <input type="number" name="duration" class="form-input" value="60" min="15" step="15" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Location (Optional)</label>
                                <input type="text" name="location" class="form-input" placeholder="e.g., Library, Online (Zoom)">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Notes (Optional)</label>
                                <textarea name="notes" class="form-textarea" rows="3" placeholder="What topics will you cover?"></textarea>
                            </div>

                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="send_reminder" id="send_reminder" value="1" checked>
                                    <label for="send_reminder">Send reminder notification</label>
                                </div>
                            </div>

                            <div class="form-actions">
                                <a href="history.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Schedule Session</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        let currentDate = new Date();
        const preselectedDate = '<?php echo $preselected_date ?: date('Y-m-d'); ?>';
        
        if (preselectedDate) {
            currentDate = new Date(preselectedDate);
        }

        function renderCalendar() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            
            document.getElementById('calendarMonth').textContent = 
                currentDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const today = new Date();
            const selectedDate = document.getElementById('session_date').value;
            
            let html = '';
            const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            dayHeaders.forEach(day => {
                html += `<div class="calendar-day-header">${day}</div>`;
            });
            
            for (let i = 0; i < firstDay; i++) {
                html += '<div class="calendar-day empty"></div>';
            }
            
            for (let day = 1; day <= daysInMonth; day++) {
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const isToday = dateStr === today.toISOString().split('T')[0];
                const isSelected = dateStr === selectedDate;
                const isPast = new Date(dateStr) < new Date(today.toISOString().split('T')[0]);
                
                let classes = 'calendar-day';
                if (isToday) classes += ' today';
                if (isSelected) classes += ' selected';
                if (isPast) classes += ' empty';
                
                html += `<div class="${classes}" onclick="${!isPast ? `selectDate('${dateStr}')` : ''}">${day}</div>`;
            }
            
            document.getElementById('calendarGrid').innerHTML = html;
        }

        function selectDate(dateStr) {
            document.getElementById('session_date').value = dateStr;
            renderCalendar();
        }

        function previousMonth() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        }

        function nextMonth() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        }

        renderCalendar();
    </script>
</body>
</html>
