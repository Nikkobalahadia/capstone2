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
            <a class="nav-link" href="monitoring.php">
                <i class="fas fa-chart-line me-2"></i> System Monitoring
            </a>
            <a class="nav-link" href="analytics.php">
                <i class="fas fa-chart-bar me-2"></i> Advanced Analytics
            </a>
            <a class="nav-link" href="session-tracking.php">
                <i class="fas fa-calendar-check me-2"></i> Session Tracking
            </a>
            <a class="nav-link active" href="matches.php">
                <i class="fas fa-handshake me-2"></i> Matches
            </a>
            <a class="nav-link" href="sessions.php">
                <i class="fas fa-video me-2"></i> Sessions
            </a>
            <a class="nav-link" href="reports.php">
                <i class="fas fa-chart-bar me-2"></i> Reports
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
                    <h1 class="h3 mb-0 text-gray-800">Manage Matches</h1>
                    <p class="text-muted">Monitor and manage student-mentor matches.</p>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success mb-4"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <!-- Match Statistics -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Matches</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_matches']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-handshake fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending_matches']; ?></div>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Accepted</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['accepted_matches']; ?></div>
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
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Avg Match Score</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['avg_match_score'] ? number_format($stats['avg_match_score'], 1) : '0.0'; ?>%</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-percentage fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Matches Table -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">All Matches</h6>
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
                                            <div class="fw-bold"><?php echo htmlspecialchars($match['student_first_name'] . ' ' . $match['student_last_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($match['student_email']); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($match['mentor_first_name'] . ' ' . $match['mentor_last_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($match['mentor_email']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($match['subject']); ?></td>
                                        <td><?php echo number_format($match['match_score'] ?? 0, 1); ?>%</td>
                                        <td>
                                            <span class="badge <?php echo $match['status'] === 'accepted' ? 'bg-success' : ($match['status'] === 'pending' ? 'bg-warning' : 'bg-danger'); ?>">
                                                <?php echo ucfirst($match['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($match['created_at'])); ?></td>
                                        <td>
                                            <?php if ($match['status'] === 'pending'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                                                    <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">Reject</button>
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
