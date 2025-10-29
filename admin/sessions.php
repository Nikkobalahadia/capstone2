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

// --- Date Range Handling ---
// Set default dates: Last 30 days
$default_end_date = date('Y-m-d');
$default_start_date = date('Y-m-d', strtotime('-30 days'));

// Get date range from GET or use defaults
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : $default_start_date;
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : $default_end_date;

// Ensure end_date is at least the start_date
if ($end_date < $start_date) {
    $end_date = $start_date;
}
// --- End Date Range Handling ---

$status_filter = $_GET['status'] ?? 'all';
$sort_by = isset($_GET['sort']) && $_GET['sort'] === 'rating' ? 'rating' : 'date'; 

// --- Limit parameter handling ---
$allowed_limits = [10, 25, 50, 100, 200];
$limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], $allowed_limits) ? (int)$_GET['limit'] : 200;

// --- Build the base redirect URL with current filters ---
$redirect_url = 'sessions.php?status=' . urlencode($status_filter) . 
                '&start_date=' . urlencode($start_date) . 
                '&end_date=' . urlencode($end_date) . 
                '&sort=' . urlencode($sort_by) . 
                '&limit=' . urlencode($limit);


$where_conditions = [];
$params = [];

// Apply status filter
if ($status_filter !== 'all') {
    $where_conditions[] = "s.status = ?";
    $params[] = $status_filter;
}

// Apply date range filter (Always applied, default is 30 days)
$where_conditions[] = "s.session_date >= ?";
$params[] = $start_date;

$where_conditions[] = "s.session_date <= ?";
$params[] = $end_date;

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Determine the ORDER BY clause
$order_clause = $sort_by === 'rating' 
    ? 'sr.rating DESC, s.session_date DESC, s.start_time DESC' 
    : 's.session_date DESC, s.start_time DESC'; 

// --- CSV EXPORT LOGIC ---
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
        {$where_clause}
        ORDER BY {$order_clause}
    ";
    
    $export_stmt = $db->prepare($export_query);
    $export_stmt->execute($params); 
    
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

// --- Handle session actions (POST/Redirect/Get FIX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $session_id = $_POST['session_id'] ?? 0;
    $action = $_POST['action'];
    $session_success_message = ''; // Use a temporary variable for the message
    
    if ($action === 'cancel' && $session_id) {
        $stmt = $db->prepare("UPDATE sessions SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        if ($stmt->execute([$session_id])) {
            $session_success_message = "Session cancelled successfully.";
        } else {
            $session_success_message = "Error: Failed to cancel session.";
        }
    }
    elseif ($action === 'complete' && $session_id) {
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("UPDATE sessions SET status = 'completed', updated_at = NOW() WHERE id = ?");
            if (!$stmt->execute([$session_id])) {
                 throw new Exception("Database update failed for session status.");
            }
            
            // FIX: Changed u.user_type to u.role to fix the SQLSTATE[42S22] error
            $commission_stmt = $db->prepare("
                SELECT m.mentor_id, u.hourly_rate, u.role, s.start_time, s.end_time, s.session_date
                FROM sessions s
                JOIN matches m ON s.match_id = m.id
                JOIN users u ON m.mentor_id = u.id
                WHERE s.id = ?
            ");
            $commission_stmt->execute([$session_id]);
            $commission_data = $commission_stmt->fetch();
            
            // FIX: Changed $commission_data['user_type'] to $commission_data['role']
            if ($commission_data && $commission_data['role'] === 'mentor' && $commission_data['hourly_rate'] > 0) {
                // Calculate duration in hours
                $start = new DateTime($commission_data['session_date'] . ' ' . $commission_data['start_time']);
                $end = new DateTime($commission_data['session_date'] . ' ' . $commission_data['end_time']);
                $interval = $start->diff($end);
                $duration_hours = $interval->h + ($interval->i / 60);
                
                // Calculate session amount and commission (10%)
                $session_amount = $commission_data['hourly_rate'] * $duration_hours;
                $commission_amount = $session_amount * 0.10;
                
                // Insert commission payment record with correct column names
                $insert_commission = $db->prepare("
                    INSERT INTO commission_payments 
                    (mentor_id, session_id, session_amount, commission_amount, commission_percentage, payment_status, created_at) 
                    VALUES (?, ?, ?, ?, 10.00, 'pending', NOW())
                ");
                if ($insert_commission->execute([
                    $commission_data['mentor_id'],
                    $session_id,
                    $session_amount,
                    $commission_amount
                ])) {
                    $session_success_message = "Session marked as completed! Commission payment of ₱" . number_format($commission_amount, 2) . " has been recorded.";
                } else {
                    $session_success_message = "Session marked as completed, but commission creation failed.";
                }
            // FIX: Changed $commission_data['user_type'] to $commission_data['role']
            } elseif ($commission_data && $commission_data['role'] === 'peer') {
                $session_success_message = "Session marked as completed! (No commission for peer tutors)";
            } else {
                $session_success_message = "Session marked as completed! Note: No commission was created (mentor has no hourly rate set or is not a mentor/peer).";
            }
            
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            $session_success_message = "Error: " . $e->getMessage();
            error_log("Session completion error: " . $e->getMessage());
        }
    }
    
    // REDIRECT AFTER SUCCESSFUL POST ACTION (PRG Pattern)
    // Pass the message via a GET parameter to display on the next load
    $final_redirect = $redirect_url . '&success_message=' . urlencode($session_success_message);
    header("Location: " . $final_redirect);
    exit;
}

// Get success message from GET parameter after redirect
$success_message = $_GET['success_message'] ?? null;


// Get all sessions with enhanced details
$stmt = $db->prepare("
    SELECT s.*, 
           m.subject,
           st.first_name as student_first_name, st.last_name as student_last_name,
           mt.first_name as mentor_first_name, mt.last_name as mentor_last_name,
           sr.rating,
           sr.feedback,
           TIMESTAMPDIFF(MINUTE, s.start_time, s.end_time) as duration_minutes
    FROM sessions s
    JOIN matches m ON s.match_id = m.id
    JOIN users st ON m.student_id = st.id
    JOIN users mt ON m.mentor_id = mt.id
    LEFT JOIN session_ratings sr ON s.id = sr.session_id
    $where_clause
    ORDER BY {$order_clause}
    LIMIT {$limit}
");
$stmt->execute($params);
$sessions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sessions - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f8f9fa;
            overflow-x: hidden;
        }
        
        /* Sidebar Styles */
        .sidebar { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
            position: fixed; 
            width: 250px; 
            top: 60px; 
            left: 0; 
            z-index: 1000; 
            overflow-y: auto; 
            height: calc(100vh - 60px);
            transition: transform 0.3s ease-in-out;
        }
        
        .sidebar .nav-link { 
            color: rgba(255,255,255,0.8); 
            padding: 12px 20px; 
            border-radius: 8px; 
            margin: 4px 12px;
            transition: all 0.2s;
        }
        
        .sidebar .nav-link:hover, 
        .sidebar .nav-link.active { 
            background: rgba(255,255,255,0.1); 
            color: white; 
        }
        
        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content { 
            margin-left: 250px; 
            padding: 20px; 
            margin-top: 60px;
            transition: margin-left 0.3s ease-in-out;
            width: calc(100% - 250px);
        }
        
        /* Mobile Styles */
        @media (max-width: 768px) { 
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content { 
                margin-left: 0;
                width: 100%;
                padding: 15px;
            }
            
            .mobile-overlay {
                display: none;
                position: fixed;
                top: 60px;
                left: 0;
                width: 100%;
                height: calc(100vh - 60px);
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }
            
            .mobile-overlay.show {
                display: block;
            }
            
            /* Mobile toggle button */
            .mobile-menu-toggle {
                display: block !important;
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 998;
                width: 56px;
                height: 56px;
                border-radius: 50%;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                color: white;
                font-size: 20px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            }
        }
        
        @media (min-width: 769px) {
            .mobile-menu-toggle {
                display: none !important;
            }
        }
        
        /* Card Styles */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        /* Scrollbar Styling */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        
        /* Session Page Specific Styles */
        .badge-scheduled { background-color: #0dcaf0; color: #000; }
        .badge-completed { background-color: #198754; color: #fff; }
        .badge-cancelled { background-color: #dc3545; color: #fff; }
        .rating-stars .fa-star { color: #ffc107; }
        .rating-stars .fa-star.text-muted { color: #e0e0e0 !important; }
    </style>
</head>
<body>
    
    <?php include '../includes/admin-header.php'; ?>

    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="mobile-overlay" id="mobileOverlay"></div>
    
    <div class="sidebar" id="sidebar">
        <div class="p-4">
            <h4 class="text-white mb-0">Admin Panel</h4>
            <small class="text-white-50">Study Mentorship Platform</small>
        </div>
        <nav class="nav flex-column px-2">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                <i class="fas fa-users me-2"></i> User Management
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'matches.php' ? 'active' : ''; ?>" href="matches.php">
                <i class="fas fa-handshake me-2"></i> Matches
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sessions.php' ? 'active' : ''; ?>" href="sessions.php">
                <i class="fas fa-video me-2"></i> Sessions
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>" href="analytics.php">
                <i class="fas fa-chart-bar me-2"></i> Advanced Analytics
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'system-health.php' ? 'active' : ''; ?>" href="system-health.php">
                <i class="fas fa-heartbeat me-2"></i> System Health
            </a>

            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'financial-overview.php' ? 'active' : ''; ?>" href="financial-overview.php">
                <i class="fas fa-chart-pie me-2"></i> Financial Overview
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'referral-audit.php' ? 'active' : ''; ?>" href="referral-audit.php">
                <i class="fas fa-link me-2"></i> Referral Audit
            </a>

            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                <i class="fas fa-cog me-2"></i> System Settings
            </a>
        </nav>
    </div>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Manage Sessions</h1>
                <a href="?export=csv&status=<?php echo htmlspecialchars($status_filter); ?>&start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?>&sort=<?php echo htmlspecialchars($sort_by); ?>" class="btn btn-success">
                    <i class="fas fa-download me-2"></i> Export to CSV
                </a>
            </div>

            <?php if (isset($success_message)): // Display the success message after PRG redirect ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            title: 'Success!',
                            text: '<?php echo htmlspecialchars($success_message); ?>',
                            icon: 'success',
                            confirmButtonColor: '#3085d6'
                        });
                    });
                </script>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Sort By</label>
                            <select name="sort" class="form-select">
                                <option value="date" <?php echo $sort_by === 'date' ? 'selected' : ''; ?>>Latest Sessions</option>
                                <option value="rating" <?php echo $sort_by === 'rating' ? 'selected' : ''; ?>>Top Sessions (Rating)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="limit" class="form-label">Show Entries</label>
                            <select id="limit" name="limit" class="form-select">
                                <option value="10" <?php echo $limit === 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $limit === 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100</option>
                                <option value="200" <?php echo $limit === 200 ? 'selected' : ''; ?>>200</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Date Range (Session Date)</label>
                            <div class="input-group">
                                <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>" required>
                                <span class="input-group-text">to</span>
                                <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="alert alert-info small" role="alert">
                <i class="fas fa-info-circle me-1"></i> Displaying **<?php echo count($sessions); ?>** sessions from **<?php echo date('M j, Y', strtotime($start_date)); ?>** to **<?php echo date('M j, Y', strtotime($end_date)); ?>**. (Showing <?php echo $limit; ?> entries maximum)
            </div>

            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <?php echo $sort_by === 'rating' ? 'Top Rated Sessions' : 'Session Log'; ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Mentor</th>
                                    <th>Subject</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Rating</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($session['student_first_name'] . ' ' . $session['student_last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($session['mentor_first_name'] . ' ' . $session['mentor_last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($session['subject']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($session['session_date'])); ?></td>
                                        <td><?php echo date('g:i A', strtotime($session['start_time'])); ?> - <?php echo date('g:i A', strtotime($session['end_time'])); ?></td>
                                        <td><?php echo $session['duration_minutes']; ?> mins</td>
                                        <td>
                                            <?php if ($session['status'] === 'scheduled'): ?>
                                                <span class="badge badge-scheduled">Scheduled</span>
                                            <?php elseif ($session['status'] === 'completed'): ?>
                                                <span class="badge badge-completed">Completed</span>
                                            <?php elseif ($session['status'] === 'cancelled'): ?>
                                                <span class="badge badge-cancelled">Cancelled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="rating-stars">
                                            <?php if ($session['rating']): ?>
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= $session['rating'] ? '' : 'text-muted'; ?>"></i>
                                                <?php endfor; ?>
                                            <?php else: ?>
                                                <span class="text-muted small">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($session['status'] === 'scheduled'): ?>
                                                <form method="POST" action="" style="display: inline-block;" id="complete-form-<?php echo $session['id']; ?>">
                                                    <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                                    <input type="hidden" name="action" value="complete">
                                                    <button type="button" class="btn btn-success btn-sm me-1" onclick="confirmComplete(event, <?php echo $session['id']; ?>)" title="Mark as Done">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" action="" style="display: inline-block;" id="cancel-form-<?php echo $session['id']; ?>">
                                                    <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmCancel(event, <?php echo $session['id']; ?>)" title="Cancel Session">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Mobile Menu Toggle JS from dashboard.php
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        const mobileOverlay = document.getElementById('mobileOverlay');
        
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            mobileOverlay.classList.toggle('show');
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-bars');
            icon.classList.toggle('fa-times');
        });
        
        mobileOverlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            mobileOverlay.classList.remove('show');
            mobileMenuToggle.querySelector('i').classList.remove('fa-times');
            mobileMenuToggle.querySelector('i').classList.add('fa-bars');
        });
        
        // Close sidebar when clicking a link on mobile
        if (window.innerWidth <= 768) {
            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                link.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    mobileOverlay.classList.remove('show');
                    mobileMenuToggle.querySelector('i').classList.remove('fa-times');
                    mobileMenuToggle.querySelector('i').classList.add('fa-bars');
                });
            });
        }

        // --- SweetAlert Confirmation Function for Mark as Done ---
        function confirmComplete(event, sessionId) {
            event.preventDefault();
            const form = document.getElementById('complete-form-' + sessionId);
            
            Swal.fire({
                title: 'Mark as Completed?',
                text: "Are you sure you want to mark this session as completed? This will calculate and record commission payments.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, mark as done!'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }

        // --- SweetAlert Confirmation Function for Cancel ---
        function confirmCancel(event, sessionId) {
            event.preventDefault();
            const form = document.getElementById('cancel-form-' + sessionId);
            
            Swal.fire({
                title: 'Cancel Session?',
                text: "Are you sure you want to cancel this session? This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, cancel it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }
    </script>
</body>
</html>