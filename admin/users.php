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
$error = '';
$success = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'];
        $user_id = (int)$_POST['user_id'];
        
        try {
            switch ($action) {
                case 'verify':
                    $stmt = $db->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $success = 'User verified successfully.';
                    break;
                    
                case 'unverify':
                    $stmt = $db->prepare("UPDATE users SET is_verified = 0 WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $success = 'User verification removed.';
                    break;
                    
                case 'activate':
                    $stmt = $db->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $success = 'User activated successfully.';
                    break;
                    
                case 'deactivate':
                    $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $success = 'User deactivated successfully.';
                    break;
            }
        } catch (Exception $e) {
            $error = 'Failed to perform action. Please try again.';
        }
    }
}

// Get filters
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build query
$where_conditions = ["u.role != 'admin'"];
$params = [];

if ($role_filter) {
    $where_conditions[] = "u.role = ?";
    $params[] = $role_filter;
}

if ($status_filter === 'verified') {
    $where_conditions[] = "u.is_verified = 1";
} elseif ($status_filter === 'unverified') {
    $where_conditions[] = "u.is_verified = 0";
} elseif ($status_filter === 'active') {
    $where_conditions[] = "u.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $where_conditions[] = "u.is_active = 0";
}

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_clause = implode(' AND ', $where_conditions);

// Get users with statistics
$users_query = "
    SELECT u.*,
           COUNT(DISTINCT m1.id) as matches_as_student,
           COUNT(DISTINCT m2.id) as matches_as_mentor,
           COUNT(DISTINCT s.id) as completed_sessions,
           AVG(sr.rating) as avg_rating,
           COUNT(DISTINCT sr.id) as rating_count
    FROM users u
    LEFT JOIN matches m1 ON u.id = m1.student_id AND m1.status = 'accepted'
    LEFT JOIN matches m2 ON u.id = m2.mentor_id AND m2.status = 'accepted'
    LEFT JOIN matches m ON (u.id = m.student_id OR u.id = m.mentor_id) AND m.status = 'accepted'
    LEFT JOIN sessions s ON m.id = s.match_id AND s.status = 'completed'
    LEFT JOIN session_ratings sr ON u.id = sr.rated_id
    WHERE $where_clause
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT 50
";

$stmt = $db->prepare($users_query);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - StudyConnect Admin</title>
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
                <h1>User Management</h1>
                <p class="text-secondary">Manage user accounts, verification, and activity.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" id="search" name="search" class="form-input" 
                                   placeholder="Name, email, or username"
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="role" class="form-label">Role</label>
                            <select id="role" name="role" class="form-select">
                                <option value="">All Roles</option>
                                <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Students</option>
                                <option value="mentor" <?php echo $role_filter === 'mentor' ? 'selected' : ''; ?>>Mentors</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                <option value="unverified" <?php echo $status_filter === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="users.php" class="btn btn-secondary">Clear</a>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Users (<?php echo count($users); ?>)</h3>
                </div>
                <div class="card-body" style="padding: 0; overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background: #f8fafc; border-bottom: 1px solid var(--border-color);">
                            <tr>
                                <th style="padding: 1rem; text-align: left; font-weight: 600;">User</th>
                                <th style="padding: 1rem; text-align: left; font-weight: 600;">Role</th>
                                <th style="padding: 1rem; text-align: left; font-weight: 600;">Status</th>
                                <th style="padding: 1rem; text-align: left; font-weight: 600;">Activity</th>
                                <th style="padding: 1rem; text-align: left; font-weight: 600;">Rating</th>
                                <th style="padding: 1rem; text-align: left; font-weight: 600;">Joined</th>
                                <th style="padding: 1rem; text-align: left; font-weight: 600;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 1rem;">
                                        <div>
                                            <div class="font-medium"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></div>
                                            <div class="text-sm text-secondary"><?php echo htmlspecialchars($u['email']); ?></div>
                                            <div class="text-sm text-secondary">@<?php echo htmlspecialchars($u['username']); ?></div>
                                        </div>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <span style="background: <?php echo $u['role'] === 'student' ? '#dbeafe' : '#dcfce7'; ?>; color: <?php echo $u['role'] === 'student' ? '#1e40af' : '#166534'; ?>; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.875rem;">
                                            <?php echo ucfirst($u['role']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <div class="text-sm">
                                            <?php if ($u['is_verified']): ?>
                                                <span style="color: var(--success-color);">✓ Verified</span>
                                            <?php else: ?>
                                                <span style="color: var(--warning-color);">⚠ Unverified</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm">
                                            <?php if ($u['is_active']): ?>
                                                <span style="color: var(--success-color);">● Active</span>
                                            <?php else: ?>
                                                <span style="color: var(--error-color);">● Inactive</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <div class="text-sm">
                                            <?php echo ($u['matches_as_student'] + $u['matches_as_mentor']); ?> matches
                                        </div>
                                        <div class="text-sm text-secondary">
                                            <?php echo $u['completed_sessions']; ?> sessions
                                        </div>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <?php if ($u['avg_rating']): ?>
                                            <div class="text-sm">
                                                <?php echo number_format($u['avg_rating'], 1); ?>/5
                                            </div>
                                            <div class="text-sm text-secondary">
                                                (<?php echo $u['rating_count']; ?> reviews)
                                            </div>
                                        <?php else: ?>
                                            <span class="text-secondary">No ratings</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <div class="text-sm">
                                            <?php echo date('M j, Y', strtotime($u['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                            <?php if (!$u['is_verified']): ?>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="verify">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <button type="submit" class="btn btn-success" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">Verify</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="unverify">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <button type="submit" class="btn btn-warning" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">Unverify</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($u['is_active']): ?>
                                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to deactivate this user?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="deactivate">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <button type="submit" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">Deactivate</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <button type="submit" class="btn btn-success" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">Activate</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (empty($users)): ?>
                        <div style="padding: 2rem; text-align: center; color: var(--text-secondary);">
                            No users found matching your criteria.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
