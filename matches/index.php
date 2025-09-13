<?php
require_once '../config/config.php';
require_once '../includes/matchmaking.php';

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

// Initialize matchmaking engine
$db = getDB();
$matchmaker = new MatchmakingEngine($db);

// Handle match response
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $match_id = (int)$_POST['match_id'];
        $response = $_POST['response'];
        
        try {
            $matchmaker->respondToMatch($match_id, $user['id'], $response);
            $success = 'Match ' . ($response === 'accepted' ? 'accepted' : 'declined') . ' successfully!';
        } catch (Exception $e) {
            $error = 'Failed to process response. Please try again.';
        }
    }
}

// Get user's matches
$matches_query = "
    SELECT m.*, 
           CASE 
               WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
               ELSE CONCAT(u1.first_name, ' ', u1.last_name)
           END as partner_name,
           CASE 
               WHEN m.student_id = ? THEN u2.role
               ELSE u1.role
           END as partner_role,
           CASE 
               WHEN m.student_id = ? THEN u2.bio
               ELSE u1.bio
           END as partner_bio,
           CASE 
               WHEN m.student_id = ? THEN u2.location
               ELSE u1.location
           END as partner_location,
           CASE 
               WHEN m.student_id = ? THEN u2.grade_level
               ELSE u1.grade_level
           END as partner_grade_level,
           CASE 
               WHEN m.student_id = ? THEN u2.id
               ELSE u1.id
           END as partner_id
    FROM matches m
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE (m.student_id = ? OR m.mentor_id = ?)
    ORDER BY 
        CASE m.status 
            WHEN 'pending' THEN 1 
            WHEN 'accepted' THEN 2 
            ELSE 3 
        END,
        m.created_at DESC
";

$stmt = $db->prepare($matches_query);
$stmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
$matches = $stmt->fetchAll();

// Separate matches by status
$pending_matches = array_filter($matches, function($match) { return $match['status'] === 'pending'; });
$accepted_matches = array_filter($matches, function($match) { return $match['status'] === 'accepted'; });
$other_matches = array_filter($matches, function($match) { return !in_array($match['status'], ['pending', 'accepted']); });
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Matches - StudyConnect</title>
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
                    <li><a href="index.php">Matches</a></li>
                    <li><a href="../sessions/index.php">Sessions</a></li>
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
                    <h1>My Matches</h1>
                    <p class="text-secondary">Manage your study partnerships and match requests.</p>
                </div>
                <a href="find.php" class="btn btn-primary">Find New Partners</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Pending Matches -->
            <?php if (!empty($pending_matches)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Pending Requests (<?php echo count($pending_matches); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($pending_matches as $match): ?>
                                <div style="padding: 1.5rem; border: 1px solid var(--border-color); border-radius: 8px; background: #fffbeb;">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div style="flex: 1;">
                                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                                <div style="width: 50px; height: 50px; background: var(--warning-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                                    <?php echo strtoupper(substr($match['partner_name'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <h4 class="font-semibold"><?php echo htmlspecialchars($match['partner_name']); ?></h4>
                                                    <div class="text-sm text-secondary">
                                                        <?php echo ucfirst($match['partner_role']); ?> • <?php echo htmlspecialchars($match['subject']); ?>
                                                        <?php if ($match['partner_location']): ?>
                                                            • <?php echo htmlspecialchars($match['partner_location']); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-sm text-secondary">
                                                        Match Score: <?php echo $match['match_score']; ?>% • 
                                                        Requested <?php echo date('M j, Y', strtotime($match['created_at'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($match['partner_bio']): ?>
                                                <p class="text-secondary mb-3"><?php echo nl2br(htmlspecialchars(substr($match['partner_bio'], 0, 150))); ?><?php echo strlen($match['partner_bio']) > 150 ? '...' : ''; ?></p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Show response buttons only if the current user is the mentor (receiving the request) -->
                                        <?php if (($user['role'] === 'mentor' && $match['mentor_id'] == $user['id']) || 
                                                  ($user['role'] === 'student' && $match['student_id'] == $user['id'] && $match['mentor_id'] != $user['id'])): ?>
                                            <div style="display: flex; gap: 0.5rem; margin-left: 2rem;">
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                                    <input type="hidden" name="response" value="accepted">
                                                    <button type="submit" class="btn btn-success">Accept</button>
                                                </form>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                                    <input type="hidden" name="response" value="rejected">
                                                    <button type="submit" class="btn btn-danger">Decline</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <div style="margin-left: 2rem;">
                                                <span class="text-warning font-medium">Awaiting Response</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Active Matches -->
            <?php if (!empty($accepted_matches)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Active Partnerships (<?php echo count($accepted_matches); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-1" style="gap: 1rem;">
                            <?php foreach ($accepted_matches as $match): ?>
                                <div style="padding: 1.5rem; border: 1px solid var(--border-color); border-radius: 8px; background: #f0fdf4;">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div style="flex: 1;">
                                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                                <div style="width: 50px; height: 50px; background: var(--success-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                                    <?php echo strtoupper(substr($match['partner_name'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <h4 class="font-semibold"><?php echo htmlspecialchars($match['partner_name']); ?></h4>
                                                    <div class="text-sm text-secondary">
                                                        <?php echo ucfirst($match['partner_role']); ?> • <?php echo htmlspecialchars($match['subject']); ?>
                                                        <?php if ($match['partner_location']): ?>
                                                            • <?php echo htmlspecialchars($match['partner_location']); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-sm text-success">
                                                        Active since <?php echo date('M j, Y', strtotime($match['updated_at'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div style="display: flex; gap: 0.5rem; margin-left: 2rem;">
                                            <a href="../messages/chat.php?match_id=<?php echo $match['id']; ?>" class="btn btn-primary">Message</a>
                                            <a href="../sessions/schedule.php?match_id=<?php echo $match['id']; ?>" class="btn btn-secondary">Schedule</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Other Matches (Rejected/Completed) -->
            <?php if (!empty($other_matches)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Match History</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($other_matches as $match): ?>
                                <div style="padding: 1rem; border: 1px solid var(--border-color); border-radius: 8px; background: #f8fafc;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <div class="font-medium"><?php echo htmlspecialchars($match['partner_name']); ?></div>
                                            <div class="text-sm text-secondary">
                                                <?php echo htmlspecialchars($match['subject']); ?> • 
                                                <?php echo ucfirst($match['status']); ?> on <?php echo date('M j, Y', strtotime($match['updated_at'])); ?>
                                            </div>
                                        </div>
                                        <span class="text-sm text-secondary">Match Score: <?php echo $match['match_score']; ?>%</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($matches)): ?>
                <div class="card">
                    <div class="card-body text-center">
                        <h3>No matches yet</h3>
                        <p class="text-secondary mb-4">Start connecting with study partners to see your matches here.</p>
                        <a href="find.php" class="btn btn-primary">Find Study Partners</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
