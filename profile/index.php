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

// Get user subjects
$db = getDB();
$subjects_stmt = $db->prepare("SELECT subject_name, proficiency_level FROM user_subjects WHERE user_id = ?");
$subjects_stmt->execute([$user['id']]);
$user_subjects = $subjects_stmt->fetchAll();

// Get user availability
$availability_stmt = $db->prepare("SELECT day_of_week, start_time, end_time FROM user_availability WHERE user_id = ? AND is_active = 1 ORDER BY FIELD(day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')");
$availability_stmt->execute([$user['id']]);
$availability = $availability_stmt->fetchAll();

// Get user statistics
$stats_stmt = $db->prepare("
    SELECT 
        (SELECT COUNT(*) FROM matches WHERE (student_id = ? OR mentor_id = ?) AND status = 'accepted') as active_matches,
        (SELECT COUNT(*) FROM sessions s JOIN matches m ON s.match_id = m.id WHERE (m.student_id = ? OR m.mentor_id = ?) AND s.status = 'completed') as completed_sessions,
        (SELECT AVG(rating) FROM session_ratings WHERE rated_id = ?) as avg_rating
");
$stats_stmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
$stats = $stats_stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - StudyConnect</title>
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
                    <li><a href="index.php">Profile</a></li>
                    <li><a href="../matches/index.php">Matches</a></li>
                    <li><a href="../sessions/index.php">Sessions</a></li>
                    <li><a href="../messages/index.php">Messages</a></li>
                    <li><a href="../auth/logout.php" class="btn btn-outline">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="container">
            <div class="grid grid-cols-3" style="gap: 2rem;">
                <!-- Profile Info -->
                <div style="grid-column: span 2;">
                    <div class="card mb-4">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <h3 class="card-title">Profile Information</h3>
                            <a href="edit.php" class="btn btn-primary">Edit Profile</a>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-2" style="gap: 2rem;">
                                <div>
                                    <h4 class="font-semibold mb-2">Basic Information</h4>
                                    <div class="mb-3">
                                        <strong>Name:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Role:</strong> <span class="text-primary"><?php echo ucfirst($user['role']); ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Grade Level:</strong> <?php echo htmlspecialchars($user['grade_level'] ?? 'Not set'); ?>
                                    </div>
                                    <?php if ($user['strand']): ?>
                                        <div class="mb-3">
                                            <strong>Strand:</strong> <?php echo htmlspecialchars($user['strand']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($user['course']): ?>
                                        <div class="mb-3">
                                            <strong>Course:</strong> <?php echo htmlspecialchars($user['course']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mb-3">
                                        <strong>Location:</strong> <?php echo htmlspecialchars($user['location'] ?? 'Not set'); ?>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="font-semibold mb-2">About Me</h4>
                                    <p class="text-secondary"><?php echo nl2br(htmlspecialchars($user['bio'] ?? 'No bio provided yet.')); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Subject Expertise -->
                    <div class="card mb-4">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <h3 class="card-title">Subject Expertise</h3>
                            <a href="subjects.php" class="btn btn-secondary">Manage Subjects</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($user_subjects)): ?>
                                <p class="text-secondary text-center">No subjects added yet. <a href="subjects.php" class="text-primary">Add subjects</a> to get matched with study partners.</p>
                            <?php else: ?>
                                <div class="grid grid-cols-2" style="gap: 1rem;">
                                    <?php foreach ($user_subjects as $subject): ?>
                                        <div style="padding: 1rem; background: #f8fafc; border-radius: 6px; border-left: 4px solid var(--primary-color);">
                                            <div class="font-medium"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                            <div class="text-sm text-secondary"><?php echo ucfirst($subject['proficiency_level']); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Availability -->
                    <div class="card">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <h3 class="card-title">Availability Schedule</h3>
                            <a href="availability.php" class="btn btn-secondary">Update Schedule</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($availability)): ?>
                                <p class="text-secondary text-center">No availability set yet. <a href="availability.php" class="text-primary">Set your schedule</a> to help others know when you're free.</p>
                            <?php else: ?>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <?php foreach ($availability as $slot): ?>
                                        <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f8fafc; border-radius: 6px;">
                                            <span class="font-medium"><?php echo ucfirst($slot['day_of_week']); ?></span>
                                            <span class="text-secondary"><?php echo date('g:i A', strtotime($slot['start_time'])) . ' - ' . date('g:i A', strtotime($slot['end_time'])); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Stats Sidebar -->
                <div>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">Profile Stats</h3>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color);">
                                    <?php echo $stats['active_matches'] ?? 0; ?>
                                </div>
                                <div class="text-secondary">Active Matches</div>
                            </div>
                            <div class="text-center mb-3">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--success-color);">
                                    <?php echo $stats['completed_sessions'] ?? 0; ?>
                                </div>
                                <div class="text-secondary">Completed Sessions</div>
                            </div>
                            <div class="text-center">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--warning-color);">
                                    <?php echo $stats['avg_rating'] ? number_format($stats['avg_rating'], 1) : 'N/A'; ?>
                                </div>
                                <div class="text-secondary">Average Rating</div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Account Status</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Verification:</strong>
                                <span class="<?php echo $user['is_verified'] ? 'text-success' : 'text-warning'; ?>">
                                    <?php echo $user['is_verified'] ? 'Verified' : 'Pending'; ?>
                                </span>
                            </div>
                            <div class="mb-3">
                                <strong>Account Status:</strong>
                                <span class="<?php echo $user['is_active'] ? 'text-success' : 'text-error'; ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            <div>
                                <strong>Member Since:</strong>
                                <span class="text-secondary"><?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
