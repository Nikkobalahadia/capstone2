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
    header('Content-Disposition: attachment; filename="matches_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Match ID', 'Student Name', 'Student Email', 'Mentor Name', 'Mentor Email', 'Subject', 'Match Score', 'Status', 'Created Date']);
    
    $export_query = "
        SELECT m.id, m.subject, m.match_score, m.status, m.created_at,
               s.first_name as student_first_name, s.last_name as student_last_name, s.email as student_email,
               mt.first_name as mentor_first_name, mt.last_name as mentor_last_name, mt.email as mentor_email
        FROM matches m
        JOIN users s ON m.student_id = s.id
        JOIN users mt ON m.mentor_id = mt.id
        ORDER BY m.created_at DESC
    ";
    $export_stmt = $db->query($export_query);
    
    while ($row = $export_stmt->fetch()) {
        fputcsv($output, [
            $row['id'],
            $row['student_first_name'] . ' ' . $row['student_last_name'],
            $row['student_email'],
            $row['mentor_first_name'] . ' ' . $row['mentor_last_name'],
            $row['mentor_email'],
            $row['subject'],
            number_format($row['match_score'], 1),
            $row['status'],
            $row['created_at']
        ]);
    }
    
    fclose($output);
    exit;
}

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

$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build query with filters
$where_conditions = ['1=1'];
$params = [];

if ($status_filter) {
    $where_conditions[] = "m.status = ?";
    $params[] = $status_filter;
}

if ($date_from) {
    $where_conditions[] = "DATE(m.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(m.created_at) <= ?";
    $params[] = $date_to;
}

if ($search) {
    $where_conditions[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR mt.first_name LIKE ? OR mt.last_name LIKE ? OR mt.email LIKE ? OR m.subject LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param]);
}

$where_clause = implode(' AND ', $where_conditions);

// Get all matches with user details
$matches_query = "
    SELECT m.*, 
           s.first_name as student_first_name, s.last_name as student_last_name, s.email as student_email,
           mt.first_name as mentor_first_name, mt.last_name as mentor_last_name, mt.email as mentor_email
    FROM matches m
    JOIN users s ON m.student_id = s.id
    JOIN users mt ON m.mentor_id = mt.id
    WHERE $where_clause
    ORDER BY m.created_at DESC
    LIMIT 100
";

$stmt = $db->prepare($matches_query);
$stmt->execute($params);
$matches = $stmt->fetchAll();

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
    <title>Manage Matches - Study Buddy Admin</title>
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
    <?php include '../includes/admin-header.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">Manage Matches</h1>
                    <p class="text-muted">Monitor and manage student-mentor matches.</p>
                </div>
                <div>
                    <a href="?export=csv<?php echo $status_filter ? '&status='.$status_filter : ''; ?><?php echo $date_from ? '&date_from='.$date_from : ''; ?><?php echo $date_to ? '&date_to='.$date_to : ''; ?><?php echo $search ? '&search='.$search : ''; ?>" class="btn btn-success">
                        <i class="fas fa-download me-2"></i> Export to CSV
                    </a>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success mb-4"><?php echo $success_message; ?></div>
            <?php endif; ?>

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
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['avg_match_score'] ? number_format($stats['avg_match_score'], 1) : '0'; ?>%</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-percentage fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" id="search" name="search" class="form-control" 
                                   placeholder="Name, email, or subject"
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="accepted" <?php echo $status_filter === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" id="date_from" name="date_from" class="form-control" 
                                   value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" id="date_to" name="date_to" class="form-control" 
                                   value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>
                        <div class="col-md-1">
                            <a href="matches.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">All Matches (<?php echo count($matches); ?>)</h6>
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
                                        <td><?php echo number_format($match['match_score'], 1); ?>%</td>
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
                        
                        <?php if (empty($matches)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-handshake fa-3x mb-3"></i>
                                <p>No matches found matching your criteria.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
