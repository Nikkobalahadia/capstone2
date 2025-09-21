history.php


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

$db = getDB();

// Get filter parameters
$filter_period = isset($_GET['period']) ? $_GET['period'] : 'all';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_subject = isset($_GET['subject']) ? sanitize_input($_GET['subject']) : '';

// Build date filter
$date_condition = '';
$date_params = [];

switch ($filter_period) {
    case 'week':
        $date_condition = 'AND s.session_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        break;
    case 'month':
        $date_condition = 'AND s.session_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        break;
    case 'quarter':
        $date_condition = 'AND s.session_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
        break;
    case 'year':
        $date_condition = 'AND s.session_date >= DATE_SUB(NOW(), INTERVAL 365 DAY)';
        break;
}

// Build status filter
$status_condition = '';
if ($filter_status !== 'all') {
    $status_condition = 'AND s.status = ?';
    $date_params[] = $filter_status;
}

// Build subject filter
$subject_condition = '';
if ($filter_subject) {
    $subject_condition = 'AND m.subject = ?';
    $date_params[] = $filter_subject;
}

// Get session history with detailed information
$sessions_query = "
    SELECT s.*, m.subject,
           CASE 
               WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
               ELSE CONCAT(u1.first_name, ' ', u1.last_name)
           END as partner_name,
           CASE 
               WHEN m.student_id = ? THEN u2.id
               ELSE u1.id
           END as partner_id,
           CASE 
               WHEN m.student_id = ? THEN u2.role
               ELSE u1.role
           END as partner_role,
           sr.rating, sr.feedback,
           TIMESTAMPDIFF(MINUTE, s.start_time, s.end_time) as duration_minutes
    FROM sessions s
    JOIN matches m ON s.match_id = m.id
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    LEFT JOIN session_ratings sr ON s.id = sr.session_id AND sr.rater_id = ?
    WHERE (m.student_id = ? OR m.mentor_id = ?)
    $date_condition
    $status_condition
    $subject_condition
    ORDER BY s.session_date DESC, s.start_time DESC
";

$params = array_merge([$user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id']], $date_params);
$stmt = $db->prepare($sessions_query);
$stmt->execute($params);
$sessions = $stmt->fetchAll();

// Get session statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_sessions,
        COUNT(CASE WHEN s.status = 'completed' THEN 1 END) as completed_sessions,
        COUNT(CASE WHEN s.status = 'cancelled' THEN 1 END) as cancelled_sessions,
        COUNT(CASE WHEN s.status = 'no_show' THEN 1 END) as no_show_sessions,
        SUM(CASE WHEN s.status = 'completed' THEN TIMESTAMPDIFF(MINUTE, s.start_time, s.end_time) ELSE 0 END) as total_minutes,
        AVG(sr.rating) as avg_rating_given,
        COUNT(DISTINCT m.subject) as subjects_studied
    FROM sessions s
    JOIN matches m ON s.match_id = m.id
    LEFT JOIN session_ratings sr ON s.id = sr.session_id AND sr.rater_id = ?
    WHERE (m.student_id = ? OR m.mentor_id = ?)
    $date_condition
    $subject_condition
";

$stats_params = array_merge([$user['id'], $user['id'], $user['id']], array_slice($date_params, ($filter_status !== 'all' ? 1 : 0)));
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute($stats_params);
$stats = $stats_stmt->fetch();

// Get user's subjects for filter
$subjects_stmt = $db->prepare("
    SELECT DISTINCT m.subject 
    FROM sessions s 
    JOIN matches m ON s.match_id = m.id 
    WHERE (m.student_id = ? OR m.mentor_id = ?)
    ORDER BY m.subject
");
$subjects_stmt->execute([$user['id'], $user['id']]);
$user_subjects = $subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

// Generate calendar data for the current month
$calendar_data = [];
$current_month = date('Y-m');
$calendar_sessions = $db->prepare("
    SELECT s.session_date, s.start_time, s.end_time, s.status, m.subject,
           CASE 
               WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
               ELSE CONCAT(u1.first_name, ' ', u1.last_name)
           END as partner_name
    FROM sessions s
    JOIN matches m ON s.match_id = m.id
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE (m.student_id = ? OR m.mentor_id = ?)
    AND DATE_FORMAT(s.session_date, '%Y-%m') = ?
    ORDER BY s.session_date, s.start_time
");
$calendar_sessions->execute([$user['id'], $user['id'], $user['id'], $current_month]);
$monthly_sessions = $calendar_sessions->fetchAll();

foreach ($monthly_sessions as $session) {
    $date = $session['session_date'];
    if (!isset($calendar_data[$date])) {
        $calendar_data[$date] = [];
    }
    $calendar_data[$date][] = $session;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session History - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #e5e7eb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        .calendar-header {
            background: #f8fafc;
            padding: 0.75rem;
            text-align: center;
            font-weight: 600;
            font-size: 0.875rem;
            color: #374151;
        }
        .calendar-day {
            background: white;
            min-height: 80px;
            padding: 0.5rem;
            position: relative;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .calendar-day:hover {
            background: #f9fafb;
        }
        .calendar-day.other-month {
            background: #f8fafc;
            color: #9ca3af;
        }
        .calendar-day.today {
            background: #eff6ff;
            border: 2px solid #3b82f6;
        }
        .calendar-day-number {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .calendar-session {
            background: #dbeafe;
            color: #1e40af;
            padding: 0.125rem 0.25rem;
            border-radius: 3px;
            font-size: 0.75rem;
            margin-bottom: 0.125rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .calendar-session.completed {
            background: #dcfce7;
            color: #166534;
        }
        .calendar-session.cancelled {
            background: #fee2e2;
            color: #991b1b;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
        }
        .export-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
    </style>
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
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <div>
                    <h1>Session History</h1>
                    <p class="text-secondary">Track your learning progress and session analytics.</p>
                </div>
                <div class="export-buttons">
                    <button onclick="exportToCalendar()" class="btn btn-secondary">📅 Export to Calendar</button>
                    <a href="schedule.php" class="btn btn-primary">Schedule New Session</a>
                </div>
            </div>

            <!-- Session Statistics -->
            <div class="grid grid-cols-4 mb-4">
                <div class="stats-card">
                    <div style="font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem;">
                        <?php echo $stats['total_sessions']; ?>
                    </div>
                    <div>Total Sessions</div>
                </div>
                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--success-color); margin-bottom: 0.5rem;">
                            <?php echo $stats['completed_sessions']; ?>
                        </div>
                        <div class="text-secondary">Completed</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color); margin-bottom: 0.5rem;">
                            <?php echo number_format(($stats['total_minutes'] ?? 0) / 60, 1); ?>
                        </div>
                        <div class="text-secondary">Hours Studied</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--warning-color); margin-bottom: 0.5rem;">
                            <?php echo $stats['avg_rating_given'] ? number_format($stats['avg_rating_given'], 1) : 'N/A'; ?>
                        </div>
                        <div class="text-secondary">Avg Rating Given</div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-3" style="gap: 2rem;">
                <!-- Calendar View -->
                <div style="grid-column: span 2;">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">📅 <?php echo date('F Y'); ?> Calendar</h3>
                        </div>
                        <div class="card-body">
                            <div class="calendar-grid">
                                <!-- Calendar headers -->
                                <div class="calendar-header">Sun</div>
                                <div class="calendar-header">Mon</div>
                                <div class="calendar-header">Tue</div>
                                <div class="calendar-header">Wed</div>
                                <div class="calendar-header">Thu</div>
                                <div class="calendar-header">Fri</div>
                                <div class="calendar-header">Sat</div>
                                
                                <?php
                                $first_day = date('Y-m-01');
                                $last_day = date('Y-m-t');
                                $start_date = date('Y-m-d', strtotime($first_day . ' -' . date('w', strtotime($first_day)) . ' days'));
                                $end_date = date('Y-m-d', strtotime($last_day . ' +' . (6 - date('w', strtotime($last_day))) . ' days'));
                                
                                $current_date = $start_date;
                                while ($current_date <= $end_date):
                                    $is_current_month = date('m', strtotime($current_date)) == date('m');
                                    $is_today = $current_date == date('Y-m-d');
                                    $day_sessions = $calendar_data[$current_date] ?? [];
                                ?>
                                    <div class="calendar-day <?php echo !$is_current_month ? 'other-month' : ''; ?> <?php echo $is_today ? 'today' : ''; ?>"
                                         onclick="showDayDetails('<?php echo $current_date; ?>')">
                                        <div class="calendar-day-number"><?php echo date('j', strtotime($current_date)); ?></div>
                                        <?php foreach (array_slice($day_sessions, 0, 2) as $session): ?>
                                            <div class="calendar-session <?php echo $session['status']; ?>" 
                                                 title="<?php echo htmlspecialchars($session['subject'] . ' with ' . $session['partner_name']); ?>">
                                                <?php echo date('g:i A', strtotime($session['start_time'])); ?> <?php echo htmlspecialchars($session['subject']); ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (count($day_sessions) > 2): ?>
                                            <div class="text-xs text-secondary">+<?php echo count($day_sessions) - 2; ?> more</div>
                                        <?php endif; ?>
                                    </div>
                                <?php
                                    $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
                                endwhile;
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters and Quick Stats -->
                <div>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">Filters</h3>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="">
                                <div class="form-group">
                                    <label for="period" class="form-label">Time Period</label>
                                    <select id="period" name="period" class="form-select">
                                        <option value="all" <?php echo $filter_period === 'all' ? 'selected' : ''; ?>>All Time</option>
                                        <option value="week" <?php echo $filter_period === 'week' ? 'selected' : ''; ?>>Last Week</option>
                                        <option value="month" <?php echo $filter_period === 'month' ? 'selected' : ''; ?>>Last Month</option>
                                        <option value="quarter" <?php echo $filter_period === 'quarter' ? 'selected' : ''; ?>>Last Quarter</option>
                                        <option value="year" <?php echo $filter_period === 'year' ? 'selected' : ''; ?>>Last Year</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="status" class="form-label">Status</label>
                                    <select id="status" name="status" class="form-select">
                                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                        <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="scheduled" <?php echo $filter_status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                        <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        <option value="no_show" <?php echo $filter_status === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="subject" class="form-label">Subject</label>
                                    <select id="subject" name="subject" class="form-select">
                                        <option value="">All Subjects</option>
                                        <?php foreach ($user_subjects as $subject): ?>
                                            <option value="<?php echo htmlspecialchars($subject); ?>" 
                                                    <?php echo $filter_subject === $subject ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($subject); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-primary" style="width: 100%;">Apply Filters</button>
                            </form>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Quick Actions</h3>
                        </div>
                        <div class="card-body">
                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                <button onclick="generateReport()" class="btn btn-secondary">📊 Generate Report</button>
                                <button onclick="exportToCSV()" class="btn btn-outline">📄 Export to CSV</button>
                                <a href="index.php" class="btn btn-outline">← Back to Sessions</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Session History List -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Session History (<?php echo count($sessions); ?> sessions)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($sessions)): ?>
                        <div class="text-center py-4">
                            <p class="text-secondary">No sessions found matching your criteria.</p>
                            <a href="schedule.php" class="btn btn-primary">Schedule Your First Session</a>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($sessions as $session): ?>
                                <div style="padding: 1.5rem; border: 1px solid var(--border-color); border-radius: 8px; background: <?php echo $session['status'] === 'completed' ? '#f0fdf4' : ($session['status'] === 'cancelled' ? '#fef2f2' : '#f8fafc'); ?>;">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div style="flex: 1;">
                                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                                <div style="width: 50px; height: 50px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                                    <?php echo strtoupper(substr($session['partner_name'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <h4 class="font-semibold"><?php echo htmlspecialchars($session['partner_name']); ?></h4>
                                                    <div class="text-sm text-secondary">
                                                        <?php echo ucfirst($session['partner_role']); ?> • <?php echo htmlspecialchars($session['subject']); ?>
                                                    </div>
                                                    <div class="text-sm font-medium">
                                                        <?php echo date('l, M j, Y', strtotime($session['session_date'])); ?> • 
                                                        <?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?>
                                                        (<?php echo $session['duration_minutes']; ?> min)
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($session['location']): ?>
                                                <div class="mb-2">
                                                    <strong>Location:</strong> <?php echo htmlspecialchars($session['location']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($session['notes']): ?>
                                                <div class="mb-2">
                                                    <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($session['notes'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($session['rating']): ?>
                                                <div class="mb-2">
                                                    <strong>Your Rating:</strong> <?php echo $session['rating']; ?>/5 ⭐
                                                    <?php if ($session['feedback']): ?>
                                                        <div class="text-sm text-secondary mt-1">"<?php echo htmlspecialchars($session['feedback']); ?>"</div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div style="margin-left: 2rem;">
                                            <span class="badge <?php 
                                                echo $session['status'] === 'completed' ? 'badge-success' : 
                                                    ($session['status'] === 'scheduled' ? 'badge-info' : 
                                                    ($session['status'] === 'cancelled' ? 'badge-error' : 'badge-warning')); 
                                            ?>">
                                                <?php echo ucfirst($session['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        function showDayDetails(date) {
            // This could open a modal with detailed session information for the day
            alert('Sessions for ' + date + ' - Feature coming soon!');
        }

        function exportToCalendar() {
            // Generate ICS file for calendar import
            const sessions = <?php echo json_encode($sessions); ?>;
            let icsContent = 'BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:-//StudyConnect//Session Calendar//EN\n';
            
            sessions.forEach(session => {
                if (session.status === 'scheduled' || session.status === 'completed') {
                    const startDateTime = new Date(session.session_date + 'T' + session.start_time);
                    const endDateTime = new Date(session.session_date + 'T' + session.end_time);
                    
                    icsContent += 'BEGIN:VEVENT\n';
                    icsContent += 'UID:' + session.id + '@studyconnect.com\n';
                    icsContent += 'DTSTART:' + formatDateForICS(startDateTime) + '\n';
                    icsContent += 'DTEND:' + formatDateForICS(endDateTime) + '\n';
                    icsContent += 'SUMMARY:Study Session - ' + session.subject + '\n';
                    icsContent += 'DESCRIPTION:Study session with ' + session.partner_name + '\n';
                    if (session.location) {
                        icsContent += 'LOCATION:' + session.location + '\n';
                    }
                    icsContent += 'END:VEVENT\n';
                }
            });
            
            icsContent += 'END:VCALENDAR';
            
            const blob = new Blob([icsContent], { type: 'text/calendar' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'studyconnect-sessions.ics';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        function formatDateForICS(date) {
            return date.toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';
        }

        function exportToCSV() {
            const sessions = <?php echo json_encode($sessions); ?>;
            let csvContent = 'Date,Time,Partner,Subject,Status,Duration,Location,Rating\n';
            
            sessions.forEach(session => {
                csvContent += [
                    session.session_date,
                    session.start_time + ' - ' + session.end_time,
                    '"' + session.partner_name + '"',
                    '"' + session.subject + '"',
                    session.status,
                    session.duration_minutes + ' min',
                    '"' + (session.location || '') + '"',
                    session.rating || ''
                ].join(',') + '\n';
            });
            
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'session-history.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        function generateReport() {
            const stats = <?php echo json_encode($stats); ?>;
            let reportContent = `
                <h2>Study Session Report</h2>
                <p><strong>Total Sessions:</strong> ${stats.total_sessions}</p>
                <p><strong>Completed Sessions:</strong> ${stats.completed_sessions}</p>
                <p><strong>Total Study Hours:</strong> ${(stats.total_minutes / 60).toFixed(1)}</p>
                <p><strong>Average Rating Given:</strong> ${stats.avg_rating_given ? stats.avg_rating_given.toFixed(1) : 'N/A'}</p>
                <p><strong>Subjects Studied:</strong> ${stats.subjects_studied}</p>
            `;
            
            const newWindow = window.open('', '_blank');
            newWindow.document.write(`
                <html>
                    <head><title>Study Session Report</title></head>
                    <body style="font-family: Arial, sans-serif; padding: 20px;">
                        ${reportContent}
                        <button onclick="window.print()">Print Report</button>
                    </body>
                </html>
            `);
        }
    </script>
</body>
</html>
