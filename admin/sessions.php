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

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sessions_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Session ID', 'Student', 'Mentor', 'Subject', 'Date', 'Start Time', 'End Time', 'Status', 'Location', 'Rating', 'Feedback']);
    
    $export_query = "
        SELECT s.id, 
               CONCAT(st.first_name, ' ', st.last_name) as student_name,
               CONCAT(mt.first_name, ' ', mt.last_name) as mentor_name,
               m.subject,
               s.session_date,
               s.start_time,
               s.end_time,
               s.status,
               s.location,
               sr.rating,
               sr.feedback
        FROM sessions s
        JOIN matches m ON s.match_id = m.id
        JOIN users st ON m.student_id = st.id
        JOIN users mt ON m.mentor_id = mt.id
        LEFT JOIN session_ratings sr ON s.id = sr.session_id
        ORDER BY s.session_date DESC, s.start_time DESC
    ";
    
    $export_stmt = $db->query($export_query);
    
    while ($row = $export_stmt->fetch()) {
        fputcsv($output, [
            $row['id'],
            $row['student_name'],
            $row['mentor_name'],
            $row['subject'],
            $row['session_date'],
            $row['start_time'],
            $row['end_time'],
            $row['status'],
            $row['location'] ?? 'N/A',
            $row['rating'] ?? 'N/A',
            $row['feedback'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

// Handle session actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $session_id = $_POST['session_id'] ?? 0;
    $action = $_POST['action'];
    
    if ($action === 'cancel' && $session_id) {
        $stmt = $db->prepare("UPDATE sessions SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$session_id]);
        $success_message = "Session cancelled successfully.";
    }
}

$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? 'all';

$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "s.status = ?";
    $params[] = $status_filter;
}

if ($date_filter !== 'all') {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(s.session_date) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "s.session_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "s.session_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get all sessions with enhanced details
$stmt = $db->prepare("
    SELECT s.*, 
           m.subject,
           st.first_name as student_first_name, st.last_name as student_last_name,
           mt.first_name as mentor_first_name, mt.last_name as mentor_last_name,
           sr.rating, sr.feedback,
           TIMESTAMPDIFF(MINUTE, s.start_time, s.end_time) as duration_minutes
    FROM sessions s
    JOIN matches m ON s.match_id = m.id
    JOIN users st ON m.student_id = st.id
    JOIN users mt ON m.mentor_id = mt.id
    LEFT JOIN session_ratings sr ON s.id = sr.session_id
    $where_clause
    ORDER BY s.session_date DESC, s.start_time DESC
");
$stmt->execute($params);
$sessions = $stmt->fetchAll();

// Get enhanced session statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_sessions,
        COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled_sessions,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_sessions,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_sessions,
        COUNT(CASE WHEN session_date >= CURDATE() THEN 1 END) as upcoming_sessions,
        AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as avg_duration
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
    <?php include '../includes/admin-sidebar.php'; ?>

    <!-- Updated main content area to work with sidebar layout -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">Manage Sessions</h1>
                    <p class="text-muted">Monitor and manage tutoring sessions.</p>
                </div>
                <div>
                    <a href="?export=csv" class="btn btn-success">
                        <i class="fas fa-download me-2"></i> Export to CSV
                    </a>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Session Statistics -->
            <div class="row mb-4">
                <div class="col-xl-2 col-md-4 mb-3">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Sessions</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $stats['total_sessions']; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 mb-3">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Scheduled</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $stats['scheduled_sessions']; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 mb-3">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Completed</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $stats['completed_sessions']; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 mb-3">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Cancelled</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $stats['cancelled_sessions']; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 mb-3">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Upcoming</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $stats['upcoming_sessions']; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 mb-3">
                    <div class="card border-left-secondary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Avg Duration</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo round($stats['avg_duration'] ?? 0); ?> min</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date Range</label>
                            <select name="date" class="form-select">
                                <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                                <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sessions Table -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">All Sessions (<?php echo count($sessions); ?>)</h6>
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
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Rating</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): ?>
                                    <tr>
                                        <td><?php echo $session['id']; ?></td>
                                        <td><?php echo htmlspecialchars($session['student_first_name'] . ' ' . $session['student_last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($session['mentor_first_name'] . ' ' . $session['mentor_last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($session['subject']); ?></td>
                                        <td>
                                            <div><?php echo date('M j, Y', strtotime($session['session_date'])); ?></div>
                                            <div class="small text-muted"><?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?></div>
                                        </td>
                                        <td><?php echo $session['duration_minutes'] ?? 0; ?> min</td>
                                        <td><?php echo htmlspecialchars($session['location'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $session['status'] === 'completed' ? 'success' : 
                                                    ($session['status'] === 'scheduled' ? 'info' : 
                                                    ($session['status'] === 'cancelled' ? 'danger' : 'warning')); 
                                            ?>">
                                                <?php echo ucfirst($session['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($session['rating']): ?>
                                                <div><?php echo $session['rating']; ?>/5 ⭐</div>
                                                <?php if ($session['feedback']): ?>
                                                    <div class="small text-muted" title="<?php echo htmlspecialchars($session['feedback']); ?>">
                                                        <?php echo substr(htmlspecialchars($session['feedback']), 0, 20) . '...'; ?>
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
                                                    <button type="submit" name="action" value="cancel" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this session?')">
                                                        <i class="fas fa-times me-1"></i>Cancel
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($sessions)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-4">
                                            No sessions found matching your filters.
                                        </td>
                                    </tr>
                                <?php endif; ?>
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
