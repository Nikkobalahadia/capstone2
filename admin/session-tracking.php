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

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? 'all';

// Build query with filters
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

// Get sessions with detailed information
$stmt = $db->prepare("
    SELECT s.*, 
           CONCAT(student.first_name, ' ', student.last_name) as student_name, 
           student.email as student_email,
           CONCAT(mentor.first_name, ' ', mentor.last_name) as mentor_name,
           mentor.email as mentor_email,
           m.subject as subject_name,
           CASE 
               WHEN s.status = 'completed' AND s.student_attended = 1 AND s.mentor_attended = 1 THEN 'Both Attended'
               WHEN s.status = 'completed' AND s.student_attended = 1 AND s.mentor_attended = 0 THEN 'Student Only'
               WHEN s.status = 'completed' AND s.student_attended = 0 AND s.mentor_attended = 1 THEN 'Mentor Only'
               WHEN s.status = 'completed' AND s.student_attended = 0 AND s.mentor_attended = 0 THEN 'No Show'
               ELSE 'N/A'
           END as attendance_status
    FROM sessions s
    LEFT JOIN matches m ON s.match_id = m.id
    LEFT JOIN users student ON m.student_id = student.id
    LEFT JOIN users mentor ON m.mentor_id = mentor.id
    $where_clause
    ORDER BY s.session_date DESC, s.start_time DESC
");
$stmt->execute($params);
$sessions = $stmt->fetchAll();

// Get session statistics
$stmt = $db->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM sessions 
    GROUP BY status
");
$status_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get attendance statistics
$stmt = $db->query("
    SELECT 
        CASE 
            WHEN student_attended = 1 AND mentor_attended = 1 THEN 'Both Attended'
            WHEN student_attended = 1 AND mentor_attended = 0 THEN 'Student Only'
            WHEN student_attended = 0 AND mentor_attended = 1 THEN 'Mentor Only'
            WHEN student_attended = 0 AND mentor_attended = 0 THEN 'No Show'
        END as attendance_type,
        COUNT(*) as count
    FROM sessions 
    WHERE status = 'completed'
    GROUP BY attendance_type
");
$attendance_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get repeat session statistics
$stmt = $db->query("
    SELECT 
        CONCAT(m.student_id, '-', m.mentor_id) as pair_id,
        CONCAT(student.first_name, ' ', student.last_name) as student_name,
        CONCAT(mentor.first_name, ' ', mentor.last_name) as mentor_name,
        COUNT(*) as session_count
    FROM sessions s
    LEFT JOIN matches m ON s.match_id = m.id
    LEFT JOIN users student ON m.student_id = student.id
    LEFT JOIN users mentor ON m.mentor_id = mentor.id
    WHERE s.status = 'completed'
    GROUP BY m.student_id, m.mentor_id
    HAVING COUNT(*) > 1
    ORDER BY session_count DESC
    LIMIT 10
");
$repeat_sessions = $stmt->fetchAll();

// Get average ratings
$stmt = $db->query("
    SELECT 
        AVG(sr.rating) as avg_rating,
        COUNT(*) as rated_sessions
    FROM session_ratings sr
    JOIN sessions s ON sr.session_id = s.id
    WHERE s.status = 'completed'
");
$rating_stats = $stmt->fetch();

include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Session Tracking Interface</h1>
        <a href="dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-calendar text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-blue-600">Scheduled</p>
                    <p class="text-2xl font-bold text-blue-900"><?php echo $status_stats['scheduled'] ?? 0; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-clock text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-yellow-600">Ongoing</p>
                    <p class="text-2xl font-bold text-yellow-900"><?php echo $status_stats['ongoing'] ?? 0; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-green-50 border border-green-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-check text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-green-600">Completed</p>
                    <p class="text-2xl font-bold text-green-900"><?php echo $status_stats['completed'] ?? 0; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-red-50 border border-red-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-times text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-red-600">Cancelled</p>
                    <p class="text-2xl font-bold text-red-900"><?php echo $status_stats['cancelled'] ?? 0; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-purple-50 border border-purple-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-star text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-purple-600">Avg Rating</p>
                    <p class="text-2xl font-bold text-purple-900">
                        <?php echo $rating_stats['avg_rating'] ? number_format($rating_stats['avg_rating'], 1) : 'N/A'; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Attendance Overview</h3>
            <div class="space-y-3">
                <?php foreach ($attendance_stats as $type => $count): ?>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-600"><?php echo $type; ?></span>
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                        <?php 
                        switch($type) {
                            case 'Both Attended': echo 'bg-green-100 text-green-800'; break;
                            case 'Student Only': echo 'bg-blue-100 text-blue-800'; break;
                            case 'Mentor Only': echo 'bg-yellow-100 text-yellow-800'; break;
                            case 'No Show': echo 'bg-red-100 text-red-800'; break;
                        }
                        ?>">
                        <?php echo $count; ?> sessions
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Repeat Pairs</h3>
            <div class="space-y-3">
                <?php foreach ($repeat_sessions as $pair): ?>
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($pair['student_name']); ?></p>
                        <p class="text-xs text-gray-500">with <?php echo htmlspecialchars($pair['mentor_name']); ?></p>
                    </div>
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                        <?php echo $pair['session_count']; ?> sessions
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="ongoing" <?php echo $status_filter === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                <select name="date" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                    <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-filter mr-2"></i>Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Sessions List -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Session Details</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Session</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Participants</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rating</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($sessions as $session): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($session['subject_name'] ?? 'N/A'); ?></div>
                            <div class="text-sm text-gray-500">
                                <?php echo date('M j, Y g:i A', strtotime($session['session_date'] . ' ' . $session['start_time'])); ?>
                                <?php 
                                if ($session['start_time'] && $session['end_time']) {
                                    $start = new DateTime($session['start_time']);
                                    $end = new DateTime($session['end_time']);
                                    $duration = $start->diff($end);
                                    echo ' (' . ($duration->h * 60 + $duration->i) . ' min)';
                                }
                                ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <div><strong>Student:</strong> <?php echo htmlspecialchars($session['student_name']); ?></div>
                                <div><strong>Mentor:</strong> <?php echo htmlspecialchars($session['mentor_name']); ?></div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                <?php 
                                switch($session['status']) {
                                    case 'scheduled': echo 'bg-blue-100 text-blue-800'; break;
                                    case 'ongoing': echo 'bg-yellow-100 text-yellow-800'; break;
                                    case 'completed': echo 'bg-green-100 text-green-800'; break;
                                    case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                <?php echo ucfirst($session['status']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($session['status'] === 'completed'): ?>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                <?php 
                                switch($session['attendance_status']) {
                                    case 'Both Attended': echo 'bg-green-100 text-green-800'; break;
                                    case 'Student Only': echo 'bg-blue-100 text-blue-800'; break;
                                    case 'Mentor Only': echo 'bg-yellow-100 text-yellow-800'; break;
                                    case 'No Show': echo 'bg-red-100 text-red-800'; break;
                                }
                                ?>">
                                <?php echo $session['attendance_status']; ?>
                            </span>
                            <?php else: ?>
                            <span class="text-sm text-gray-500">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php 
                            $rating_stmt = $db->prepare("SELECT AVG(rating) as avg_rating FROM session_ratings WHERE session_id = ?");
                            $rating_stmt->execute([$session['id']]);
                            $rating_data = $rating_stmt->fetch();
                            
                            if ($rating_data && $rating_data['avg_rating']): ?>
                            <div class="text-sm text-gray-900">
                                Average: <?php echo number_format($rating_data['avg_rating'], 1); ?>/5 ⭐
                            </div>
                            <?php else: ?>
                            <span class="text-sm text-gray-500">No ratings</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <a href="../sessions/view.php?id=<?php echo $session['id']; ?>" 
                               class="text-blue-600 hover:text-blue-900 mr-3">
                                <i class="fas fa-eye mr-1"></i>View
                            </a>
                            <?php if ($session['status'] === 'scheduled'): ?>
                            <button onclick="cancelSession(<?php echo $session['id']; ?>)" 
                                    class="text-red-600 hover:text-red-900">
                                <i class="fas fa-times mr-1"></i>Cancel
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function cancelSession(sessionId) {
    if (confirm('Are you sure you want to cancel this session?')) {
        fetch('../sessions/cancel.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ session_id: sessionId, admin_cancel: true })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Session cancelled successfully');
                location.reload();
            } else {
                alert('Error cancelling session: ' + data.message);
            }
        });
    }
}
</script>

<?php include '../includes/footer.php'; ?>
