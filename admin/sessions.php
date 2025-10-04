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
    <!-- Updated to use Bootstrap and purple admin theme -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; border-radius: 8px; margin: 4px 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } .sidebar { display: none; } }
    </style>
</head>
<body>
    <!-- Replaced horizontal header with purple sidebar navigation -->
    <div class="sidebar position-fixed" style="width: 250px; z-index: 1000;">
        <div class="p-4">
            <h4 class="text-white mb-0">Admin Panel</h4>
            <small class="text-white-50">Study Mentorship Platform</small>
        </div>
        <nav class="nav flex-column px-3">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            <a class="nav-link" href="users.php">
                <i class="fas fa-users me-2"></i> User Management
            </a>
            <a class="nav-link" href="analytics.php">
                <i class="fas fa-chart-bar me-2"></i> Advanced Analytics
            </a>
            <a class="nav-link" href="matches.php">
                <i class="fas fa-handshake me-2"></i> Matches
            </a>
            <a class="nav-link active" href="sessions.php">
                <i class="fas fa-video me-2"></i> Sessions
            </a>
            <a class="nav-link" href="referral-audit.php">
                <i class="fas fa-link me-2"></i> Referral Audit
            </a>
        </nav>
        <div class="position-absolute bottom-0 w-100 p-3">
            <a href="../auth/logout.php" class="btn btn-outline-light btn-sm w-100">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </div>
    </div>

    <!-- Updated main content area to work with sidebar layout -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">Manage Sessions</h1>
                    <p class="text-muted">Monitor and manage tutoring sessions.</p>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success mb-4"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <!-- Session Statistics -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Sessions</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_sessions']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-video fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Scheduled</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['scheduled_sessions']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Completed</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['completed_sessions']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Upcoming</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['upcoming_sessions']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sessions Table -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">All Sessions</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="table-light">
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
                                            <div class="fw-bold"><?php echo htmlspecialchars($session['student_first_name'] . ' ' . $session['student_last_name']); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($session['mentor_first_name'] . ' ' . $session['mentor_last_name']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($session['subject']); ?></td>
                                        <td>
                                            <div><?php echo date('M j, Y', strtotime($session['session_date'])); ?></div>
                                            <div class="small text-muted"><?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?></div>
                                        </td>
                                        <td>
                                            <?php 
                                            $duration = $session['duration_minutes'] ?? 0;
                                            if ($duration > 0) {
                                                echo $duration . ' min';
                                            } else {
                                                $start = strtotime($session['start_time']);
                                                $end = strtotime($session['end_time']);
                                                $calculated_duration = ($end - $start) / 60;
                                                echo $calculated_duration . ' min';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $session['status'] === 'completed' ? 'bg-success' : 
                                                    ($session['status'] === 'scheduled' ? 'bg-info' : 
                                                    ($session['status'] === 'cancelled' ? 'bg-danger' : 'bg-warning')); 
                                            ?>">
                                                <?php echo ucfirst($session['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($session['rating']): ?>
                                                <div class="fw-bold"><?php echo $session['rating']; ?>/5</div>
                                                <?php if ($session['feedback']): ?>
                                                    <div class="small text-muted" title="<?php echo htmlspecialchars($session['feedback']); ?>">
                                                        <?php echo substr(htmlspecialchars($session['feedback']), 0, 30) . '...'; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">No rating</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($session['status'] === 'scheduled'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                                    <button type="submit" name="action" value="cancel" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this session?')">Cancel</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">No actions</span>
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
    </div>

    <!-- Added Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
