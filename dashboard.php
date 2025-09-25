<?php
require_once 'config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user) {
    redirect('auth/login.php');
}

// Get user statistics
$db = getDB();

// Get match count
$match_stmt = $db->prepare("SELECT COUNT(*) as count FROM matches WHERE (student_id = ? OR mentor_id = ?) AND status = 'accepted'");
$match_stmt->execute([$user['id'], $user['id']]);
$match_count = $match_stmt->fetch()['count'];

// Get session count
$session_stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM sessions s 
    JOIN matches m ON s.match_id = m.id 
    WHERE (m.student_id = ? OR m.mentor_id = ?) AND s.status = 'completed'
");
$session_stmt->execute([$user['id'], $user['id']]);
$session_count = $session_stmt->fetch()['count'];

// Get recent matches
$recent_matches_stmt = $db->prepare("
    SELECT m.*, 
           CASE 
               WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
               ELSE CONCAT(u1.first_name, ' ', u1.last_name)
           END as partner_name,
           CASE 
               WHEN m.student_id = ? THEN u2.role
               ELSE u1.role
           END as partner_role
    FROM matches m
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE (m.student_id = ? OR m.mentor_id = ?) 
    ORDER BY m.created_at DESC 
    LIMIT 5
");
$recent_matches_stmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
$recent_matches = $recent_matches_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - StudyConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="dashboard.php" class="logo">StudyConnect</a>
                <ul class="nav-links">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="profile/index.php">Profile</a></li>
                    <li><a href="matches/index.php">Matches</a></li>
                    <li><a href="sessions/index.php">Sessions</a></li>
                    <li><a href="messages/index.php">Messages</a></li>
                    <li>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span class="text-secondary">Hi, <?php echo htmlspecialchars($user['first_name']); ?>!</span>
                            <a href="auth/logout.php" class="btn btn-outline" style="padding: 0.5rem 1rem;">Logout</a>
                        </div>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="container">
            <div class="mb-4">
                <h1>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
                <p class="text-secondary">
                    <?php if ($user['role'] === 'peer'): ?>
                    <?php else: ?>
                    <?php endif; ?>
                </p>
            </div>

            <div class="grid grid-cols-3 mb-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color); margin-bottom: 0.5rem;">
                            <?php echo $match_count; ?>
                        </div>
                        <div class="text-secondary">Active Matches</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--success-color); margin-bottom: 0.5rem;">
                            <?php echo $session_count; ?>
                        </div>
                        <div class="text-secondary">Completed Sessions</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--warning-color); margin-bottom: 0.5rem;">
                            <?php echo $user['role'] === 'peer' ? '🤝 Peer' : ucfirst($user['role']); ?>
                        </div>
                        <div class="text-secondary">Your Role</div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2" style="gap: 2rem;">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php if ($user['role'] === 'student'): ?>
                                <a href="matches/find.php" class="btn btn-primary">Find a Mentor</a>
                                <a href="sessions/schedule.php" class="btn btn-secondary">Schedule Session</a>
                            <?php elseif ($user['role'] === 'peer'): ?>
                                <a href="matches/find.php" class="btn btn-primary">Find Study Partners</a>
                                <a href="matches/index.php" class="btn btn-secondary">View Match Requests</a>
                                <a href="profile/availability.php" class="btn btn-outline">Update Availability</a>
                            <?php else: ?>
                                <a href="matches/index.php" class="btn btn-primary">View Match Requests</a>
                                <a href="profile/availability.php" class="btn btn-secondary">Update Availability</a>
                            <?php endif; ?>
                            <a href="profile/subjects.php" class="btn btn-outline">Manage Subjects</a>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Matches</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_matches)): ?>
                            <p class="text-secondary text-center">No matches yet. Start connecting with others!</p>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                <?php foreach ($recent_matches as $match): ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: #f8fafc; border-radius: 6px;">
                                        <div>
                                            <div class="font-medium"><?php echo htmlspecialchars($match['partner_name']); ?></div>
                                            <div class="text-sm text-secondary">
                                                <?php echo htmlspecialchars($match['subject']); ?> • 
                                                <?php echo $match['partner_role'] === 'peer' ? '🤝 Peer' : ucfirst($match['partner_role']); ?>
                                            </div>
                                        </div>
                                        <span class="text-sm <?php echo $match['status'] === 'accepted' ? 'text-success' : ($match['status'] === 'pending' ? 'text-warning' : 'text-secondary'); ?>">
                                            <?php echo ucfirst($match['status']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
