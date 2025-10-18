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

$upcoming_query = "
    SELECT s.*, m.subject,
           CASE 
               WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
               ELSE CONCAT(u1.first_name, ' ', u1.last_name)
           END as partner_name,
           CASE 
               WHEN m.student_id = ? THEN u2.id
               ELSE u1.id
           END as partner_id,
           TIMESTAMPDIFF(MINUTE, s.start_time, s.end_time) as duration_minutes
    FROM sessions s
    JOIN matches m ON s.match_id = m.id
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE (m.student_id = ? OR m.mentor_id = ?)
    AND (s.session_date > CURDATE() OR (s.session_date = CURDATE() AND s.start_time > CURTIME()))
    AND s.status = 'scheduled'
    $subject_condition
    ORDER BY s.session_date ASC, s.start_time ASC
";

$past_query = "
    SELECT s.*, m.subject,
           CASE 
               WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
               ELSE CONCAT(u1.first_name, ' ', u1.last_name)
           END as partner_name,
           CASE 
               WHEN m.student_id = ? THEN u2.id
               ELSE u1.id
           END as partner_id,
           sr.rating, sr.feedback,
           TIMESTAMPDIFF(MINUTE, s.start_time, s.end_time) as duration_minutes
    FROM sessions s
    JOIN matches m ON s.match_id = m.id
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    LEFT JOIN session_ratings sr ON s.id = sr.session_id AND sr.rater_id = ?
    WHERE (m.student_id = ? OR m.mentor_id = ?)
    AND ((s.session_date < CURDATE() OR (s.session_date = CURDATE() AND s.start_time <= CURTIME())) OR s.status IN ('completed', 'cancelled', 'no_show'))
    $date_condition
    $status_condition
    $subject_condition
    ORDER BY s.session_date DESC, s.start_time DESC
";

$upcoming_params = array_merge([$user['id'], $user['id'], $user['id'], $user['id']], ($filter_subject ? [$filter_subject] : []));
$stmt = $db->prepare($upcoming_query);
$stmt->execute($upcoming_params);
$upcoming_sessions = $stmt->fetchAll();

$past_params = array_merge([$user['id'], $user['id'], $user['id'], $user['id'], $user['id']], $date_params);
$stmt = $db->prepare($past_query);
$stmt->execute($past_params);
$past_sessions = $stmt->fetchAll();

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

$page_title = "Session History";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - StudyConnect</title>
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

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        main {
            padding: 2rem 0;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2rem;
            color: #111827;
            display: flex;
            flex-direction: column;
        }

        .page-header .tagline {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 400;
            margin-top: 0.25rem;
        }

        .session-section {
            margin-bottom: 2.5rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 1rem;
        }

        .session-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.25rem;
        }

        .session-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
        }

        .session-card:hover {
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.15);
            transform: translateY(-2px);
        }

        .session-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .session-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.25rem;
        }

        .session-partner {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-upcoming {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .session-details {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #4b5563;
        }

        .detail-icon {
            width: 16px;
            height: 16px;
            color: #7c3aed;
        }

        .session-actions {
            display: flex;
            gap: 0.75rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        .session-actions .btn {
            flex: 1;
            text-align: center;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            color: #111827;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #6b7280;
            margin-bottom: 1.5rem;
        }

        .filters-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-select {
            padding: 0.625rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            color: #111827;
            background: white;
            cursor: pointer;
        }

        .form-select:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
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
                <h1>
                    SessionSync
                    <span class="tagline">Smart Scheduling Made Simple</span>
                </h1>
                <a href="schedule.php" class="btn btn-primary">+ New Session</a>
            </div>

            <!-- Upcoming Sessions -->
            <div class="session-section">
                <h2 class="section-title">Upcoming Sessions</h2>
                <?php if (empty($upcoming_sessions)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📅</div>
                        <h3>No Upcoming Sessions</h3>
                        <p>You don't have any sessions scheduled yet.</p>
                        <a href="schedule.php" class="btn btn-primary">Schedule a Session</a>
                    </div>
                <?php else: ?>
                    <div class="session-grid">
                        <?php foreach ($upcoming_sessions as $session): ?>
                            <div class="session-card">
                                <div class="session-card-header">
                                    <div>
                                        <div class="session-title"><?php echo htmlspecialchars($session['subject']); ?></div>
                                        <div class="session-partner">with <?php echo htmlspecialchars($session['partner_name']); ?></div>
                                    </div>
                                    <span class="badge badge-upcoming">upcoming</span>
                                </div>
                                <div class="session-details">
                                    <div class="detail-row">
                                        <svg class="detail-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <span><?php echo date('l, F j, Y', strtotime($session['session_date'])); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <svg class="detail-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span><?php echo date('g:i A', strtotime($session['start_time'])); ?> • <?php echo $session['duration_minutes']; ?> min</span>
                                    </div>
                                    <?php if ($session['location']): ?>
                                    <div class="detail-row">
                                        <svg class="detail-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        <span><?php echo htmlspecialchars($session['location']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="session-actions">
                                    <a href="edit.php?id=<?php echo $session['id']; ?>" class="btn btn-outline">Reschedule</a>
                                    <button class="btn btn-danger" onclick="cancelSession(<?php echo $session['id']; ?>)">Cancel</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

               

    <!-- Add SweetAlert2 CDN before the closing </body> -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function cancelSession(sessionId) {
    Swal.fire({
        title: 'Cancel Session?',
        text: "Are you sure you want to cancel this session?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#7c3aed',
        cancelButtonColor: '#d1d5db',
        confirmButtonText: 'Yes, cancel it',
        cancelButtonText: 'No, keep it'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('cancel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    cancellation_reason: 'Cancelled by user'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Session Cancelled',
                        text: 'The session was successfully cancelled.',
                        confirmButtonColor: '#7c3aed'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Something went wrong. Please try again.',
                        confirmButtonColor: '#7c3aed'
                    });
                }
            })
            .catch(error => {
                console.error('Error cancelling session:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Request Failed',
                    text: 'Failed to cancel session. Please try again.',
                    confirmButtonColor: '#7c3aed'
                });
            });
        }
    });
}
</script>

</body>
</html>
