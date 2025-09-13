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

// Handle match actions
if ($_POST['action'] ?? false) {
    $match_id = $_POST['match_id'] ?? 0;
    $action = $_POST['action'];
    
    if ($action === 'approve' && $match_id) {
        $stmt = $db->prepare("UPDATE matches SET status = 'accepted', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$match_id]);
        $success_message = "Match approved successfully.";
    } elseif ($action === 'reject' && $match_id) {
        $stmt = $db->prepare("UPDATE matches SET status = 'rejected', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$match_id]);
        $success_message = "Match rejected successfully.";
    }
}

// Get all matches with user details
$matches = $db->query("
    SELECT m.*, 
           s.first_name as student_first_name, s.last_name as student_last_name, s.email as student_email,
           mt.first_name as mentor_first_name, mt.last_name as mentor_last_name, mt.email as mentor_email
    FROM matches m
    JOIN users s ON m.student_id = s.id
    JOIN users mt ON m.mentor_id = mt.id
    ORDER BY m.created_at DESC
")->fetchAll();

// Get match statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_matches,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_matches,
        COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted_matches,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_matches,
        AVG(match_score) as avg_match_score
    FROM matches
")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Matches - StudyConnect Admin</title>
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
                    <li><a href="matches.php" class="active">Matches</a></li>
                    <li><a href="sessions.php">Sessions</a></li>
                    <li><a href="reports.php">Reports</a></li>
                    <li><a href="../auth/logout.php" class="btn btn-outline">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="container">
            <div class="mb-4">
                <h1>Manage Matches</h1>
                <p class="text-secondary">Monitor and manage student-mentor matches.</p>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success mb-4"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <!-- Match Statistics -->
            <div class="grid grid-cols-4 mb-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color);">
                            <?php echo $stats['total_matches']; ?>
                        </div>
                        <div class="text-secondary">Total Matches</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--warning-color);">
                            <?php echo $stats['pending_matches']; ?>
                        </div>
                        <div class="text-secondary">Pending</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--success-color);">
                            <?php echo $stats['accepted_matches']; ?>
                        </div>
                        <div class="text-secondary">Accepted</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--error-color);">
                            <?php echo $stats['avg_match_score'] ? number_format($stats['avg_match_score'], 1) : '0'; ?>%
                        </div>
                        <div class="text-secondary">Avg Match Score</div>
                    </div>
                </div>
            </div>

            <!-- Matches Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">All Matches</h3>
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
                                    <th>Match Score</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($matches as $match): ?>
                                    <tr>
                                        <td><?php echo $match['id']; ?></td>
                                        <td>
                                            <div class="font-medium"><?php echo htmlspecialchars($match['student_first_name'] . ' ' . $match['student_last_name']); ?></div>
                                            <div class="text-sm text-secondary"><?php echo htmlspecialchars($match['student_email']); ?></div>
                                        </td>
                                        <td>
                                            <div class="font-medium"><?php echo htmlspecialchars($match['mentor_first_name'] . ' ' . $match['mentor_last_name']); ?></div>
                                            <div class="text-sm text-secondary"><?php echo htmlspecialchars($match['mentor_email']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($match['subject']); ?></td>
                                        <td><?php echo number_format($match['match_score'], 1); ?>%</td>
                                        <td>
                                            <span class="badge <?php echo $match['status'] === 'accepted' ? 'badge-success' : ($match['status'] === 'pending' ? 'badge-warning' : 'badge-error'); ?>">
                                                <?php echo ucfirst($match['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($match['created_at'])); ?></td>
                                        <td>
                                            <?php if ($match['status'] === 'pending'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                                    <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
                                                    <button type="submit" name="action" value="reject" class="btn btn-sm btn-error">Reject</button>
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
