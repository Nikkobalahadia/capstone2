<?php
require_once '../config/config.php';

// Check if user is logged in and is admin
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user || $user['role'] !== 'admin') {
    redirect('dashboard.php');
}

$db = getDB();

// Handle session actions
if ($_POST['action'] ?? false) {
    $session_id = $_POST['session_id'] ?? 0;
    $action = $_POST['action'];
    
    if ($action === 'cancel' && $session_id) {
        $stmt = $db->prepare("UPDATE sessions SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$session_id]);
        $success_message = "Session cancelled successfully.";
    }
}

// Get all sessions with user and match details
$sessions = $db->query("
    SELECT s.*, 
           m.subject,
           st.first_name as student_first_name, st.last_name as student_last_name,
           mt.first_name as mentor_first_name, mt.last_name as mentor_last_name,
           sr.rating, sr.feedback
    FROM sessions s
    JOIN matches m ON s.match_id = m.id
    JOIN users st ON m.student_id = st.id
    JOIN users mt ON m.mentor_id = mt.id
    LEFT JOIN session_ratings sr ON s.id = sr.session_id
    ORDER BY s.session_date DESC, s.start_time DESC
")->fetchAll();

// Get session statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_sessions,
        COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled_sessions,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_sessions,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_sessions,
        COUNT(CASE WHEN session_date >= CURDATE() THEN 1 END) as upcoming_sessions
    FROM sessions
")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sessions - StudyConnect Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="dashboard.php" class="logo">StudyConnect Admin</a>
                <ul class="nav-links">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="users.php">Users</a></li>
                    <li><a href="matches.php">Matches</a></li>
                    <li><a href="sessions.php" class="active">Sessions</a></li>
                    <li><a href="reports.php">Reports</a></li>
                    <li><a href="../auth/logout.php" class="btn btn-outline">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="container">
            <div class="mb-4">
                <h1>Manage Sessions</h1>
                <p class="text-secondary">Monitor and manage tutoring sessions.</p>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success mb-4"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <!-- Session Statistics -->
            <div class="grid grid-cols-4 mb-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color);">
                            <?php echo $stats['total_sessions']; ?>
                        </div>
                        <div class="text-secondary">Total Sessions</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--warning-color);">
                            <?php echo $stats['scheduled_sessions']; ?>
                        </div>
                        <div class="text-secondary">Scheduled</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--success-color);">
                            <?php echo $stats['completed_sessions']; ?>
                        </div>
                        <div class="text-secondary">Completed</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--info-color);">
                            <?php echo $stats['upcoming_sessions']; ?>
                        </div>
                        <div class="text-secondary">Upcoming</div>
                    </div>
                </div>
            </div>

            <!-- Sessions Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">All Sessions</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Student</th>
                                    <th>Mentor</th>
                                    <th>Subject</th>
                                    <th>Date & Time</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Rating</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): ?>
                                    <tr>
                                        <td><?php echo $session['id']; ?></td>
                                        <td>
                                            <div class="font-medium"><?php echo htmlspecialchars($session['student_first_name'] . ' ' . $session['student_last_name']); ?></div>
                                        </td>
                                        <td>
                                            <div class="font-medium"><?php echo htmlspecialchars($session['mentor_first_name'] . ' ' . $session['mentor_last_name']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($session['subject']); ?></td>
                                        <td>
                                            <div><?php echo date('M j, Y', strtotime($session['session_date'])); ?></div>
                                            <div class="text-sm text-secondary"><?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?></div>
                                        </td>
                                        <td><?php echo $session['duration_minutes']; ?> min</td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $session['status'] === 'completed' ? 'badge-success' : 
                                                    ($session['status'] === 'scheduled' ? 'badge-info' : 
                                                    ($session['status'] === 'cancelled' ? 'badge-error' : 'badge-warning')); 
                                            ?>">
                                                <?php echo ucfirst($session['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($session['rating']): ?>
                                                <div class="font-medium"><?php echo $session['rating']; ?>/5</div>
                                                <?php if ($session['feedback']): ?>
                                                    <div class="text-sm text-secondary" title="<?php echo htmlspecialchars($session['feedback']); ?>">
                                                        <?php echo substr(htmlspecialchars($session['feedback']), 0, 30) . '...'; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-secondary">No rating</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($session['status'] === 'scheduled'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                                    <button type="submit" name="action" value="cancel" class="btn btn-sm btn-error" onclick="return confirm('Are you sure you want to cancel this session?')">Cancel</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-secondary">No actions</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
