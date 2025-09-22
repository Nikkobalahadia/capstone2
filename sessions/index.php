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

// Get user's sessions
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
           END as partner_role
    FROM sessions s
    JOIN matches m ON s.match_id = m.id
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE (m.student_id = ? OR m.mentor_id = ?)
    ORDER BY s.session_date DESC, s.start_time DESC
";

$stmt = $db->prepare($sessions_query);
$stmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
$sessions = $stmt->fetchAll();

// Separate sessions by status
$upcoming_sessions = array_filter($sessions, function($session) {
    return $session['status'] === 'scheduled' && 
           (strtotime($session['session_date'] . ' ' . $session['start_time']) > time());
});

$past_sessions_need_completion = array_filter($sessions, function($session) {
    return $session['status'] === 'scheduled' && 
           (strtotime($session['session_date'] . ' ' . $session['start_time']) <= time());
});

$past_sessions = array_filter($sessions, function($session) {
    return $session['status'] === 'completed';
});

$cancelled_sessions = array_filter($sessions, function($session) {
    return in_array($session['status'], ['cancelled', 'no_show']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Sessions - StudyConnect</title>
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
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <div>
                    <h1>My Sessions</h1>
                    <p class="text-secondary">Manage your study sessions and track your learning progress.</p>
                </div>
                <a href="history.php" class="btn btn-primary">Schedule New Session</a>
            </div>

            <!-- Upcoming Sessions -->
            <?php if (!empty($upcoming_sessions)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Upcoming Sessions (<?php echo count($upcoming_sessions); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($upcoming_sessions as $session): ?>
                                <div style="padding: 1.5rem; border: 1px solid var(--border-color); border-radius: 8px; background: #f0f9ff;">
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
                                                    <div class="text-sm font-medium" style="color: var(--primary-color);">
                                                        <?php echo date('l, M j, Y', strtotime($session['session_date'])); ?> • 
                                                        <?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($session['location']): ?>
                                                <div class="mb-2">
                                                    <strong>Location:</strong> <?php echo htmlspecialchars($session['location']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($session['notes']): ?>
                                                <div class="text-secondary">
                                                    <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($session['notes'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div style="display: flex; gap: 0.5rem; margin-left: 2rem;">
                                            <a href="edit.php?id=<?php echo $session['id']; ?>" class="btn btn-secondary">Edit</a>
                                            <a href="../messages/chat.php?match_id=<?php echo $session['match_id']; ?>" class="btn btn-outline">Message</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Past Sessions That Need Completion -->
            <?php if (!empty($past_sessions_need_completion)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Sessions Awaiting Completion (<?php echo count($past_sessions_need_completion); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($past_sessions_need_completion as $session): ?>
                                <div style="padding: 1.5rem; border: 1px solid var(--border-color); border-radius: 8px; background: #fff7ed;">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div style="flex: 1;">
                                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                                <div style="width: 50px; height: 50px; background: #f59e0b; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                                    <?php echo strtoupper(substr($session['partner_name'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <h4 class="font-semibold"><?php echo htmlspecialchars($session['partner_name']); ?></h4>
                                                    <div class="text-sm text-secondary">
                                                        <?php echo ucfirst($session['partner_role']); ?> • <?php echo htmlspecialchars($session['subject']); ?>
                                                    </div>
                                                    <div class="text-sm font-medium" style="color: #f59e0b;">
                                                        <?php echo date('M j, Y', strtotime($session['session_date'])); ?> • 
                                                        <?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div style="display: flex; gap: 0.5rem; margin-left: 2rem;">
                                            <a href="complete.php?id=<?php echo $session['id']; ?>" class="btn btn-success">Mark Complete</a>
                                            <a href="../messages/chat.php?match_id=<?php echo $session['match_id']; ?>" class="btn btn-outline">Message</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Past Sessions -->
            <?php if (!empty($past_sessions)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Past Sessions (<?php echo count($past_sessions); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach (array_slice($past_sessions, 0, 10) as $session): ?>
                                <div style="padding: 1.5rem; border: 1px solid var(--border-color); border-radius: 8px; background: #f8fafc;">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div style="flex: 1;">
                                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                                <div style="width: 50px; height: 50px; background: var(--success-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                                    <?php echo strtoupper(substr($session['partner_name'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <h4 class="font-semibold"><?php echo htmlspecialchars($session['partner_name']); ?></h4>
                                                    <div class="text-sm text-secondary">
                                                        <?php echo ucfirst($session['partner_role']); ?> • <?php echo htmlspecialchars($session['subject']); ?>
                                                    </div>
                                                    <div class="text-sm text-success">
                                                        <?php echo date('M j, Y', strtotime($session['session_date'])); ?> • 
                                                        <?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div style="margin-left: 2rem;">
                                            <?php
                                            // Check if user has rated this session
                                            $rating_stmt = $db->prepare("SELECT id FROM session_ratings WHERE session_id = ? AND rater_id = ?");
                                            $rating_stmt->execute([$session['id'], $user['id']]);
                                            $has_rated = $rating_stmt->fetch();
                                            ?>
                                            
                                            <?php if (!$has_rated): ?>
                                                <a href="rate.php?id=<?php echo $session['id']; ?>" class="btn btn-warning">Rate Session</a>
                                            <?php else: ?>
                                                <span class="text-success font-medium">Completed</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Cancelled Sessions -->
            <?php if (!empty($cancelled_sessions)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Cancelled Sessions</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($cancelled_sessions as $session): ?>
                                <div style="padding: 1rem; border: 1px solid var(--border-color); border-radius: 8px; background: #fef2f2;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <div class="font-medium"><?php echo htmlspecialchars($session['partner_name']); ?></div>
                                            <div class="text-sm text-secondary">
                                                <?php echo htmlspecialchars($session['subject']); ?> • 
                                                <?php echo date('M j, Y', strtotime($session['session_date'])); ?> • 
                                                <?php echo ucfirst($session['status']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($sessions)): ?>
                <div class="card">
                    <div class="card-body text-center">
                        <h3>No sessions yet</h3>
                        <p class="text-secondary mb-4">Schedule your first study session to start learning with your partners.</p>
                        <a href="schedule.php" class="btn btn-primary">Schedule Session</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
