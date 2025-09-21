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

// Handle report actions
if ($_POST) {
    $report_id = $_POST['report_id'];
    $action = $_POST['action'];
    
    if ($action === 'resolve') {
        $stmt = $db->prepare("UPDATE user_reports SET status = 'resolved' WHERE id = ?");
        $stmt->execute([$report_id]);
    } elseif ($action === 'escalate') {
        $stmt = $db->prepare("UPDATE user_reports SET status = 'reviewed' WHERE id = ?");
        $stmt->execute([$report_id]);
    }
    
    header('Location: reports-inbox.php');
    exit;
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$category_filter = $_GET['category'] ?? 'all';
$priority_filter = $_GET['priority'] ?? 'all';

// Build query with filters
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "r.status = ?";
    $params[] = $status_filter;
}

if ($category_filter !== 'all') {
    $where_conditions[] = "r.reason = ?";
    $params[] = $category_filter;
}

if ($priority_filter !== 'all') {
    $where_conditions[] = "r.priority = ?";
    $params[] = $priority_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get reports with user information
$stmt = $db->prepare("
    SELECT r.*, 
           CONCAT(reporter.first_name, ' ', reporter.last_name) as reporter_name, 
           reporter.email as reporter_email,
           CONCAT(reported.first_name, ' ', reported.last_name) as reported_name,
           reported.email as reported_email
    FROM user_reports r
    LEFT JOIN users reporter ON r.reporter_id = reporter.id
    LEFT JOIN users reported ON r.reported_id = reported.id
    $where_clause
    ORDER BY r.created_at DESC
");
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Get statistics
$stmt = $db->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM user_reports 
    GROUP BY status
");
$status_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $db->query("
    SELECT 
        reason,
        COUNT(*) as count
    FROM user_reports 
    GROUP BY reason
");
$category_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Reports & Feedback Inbox</h1>
        <a href="dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-red-50 border border-red-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-circle text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-red-600">Pending Reports</p>
                    <p class="text-2xl font-bold text-red-900"><?php echo $status_stats['pending'] ?? 0; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-fire text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-yellow-600">Reviewed Issues</p>
                    <p class="text-2xl font-bold text-yellow-900"><?php echo $status_stats['reviewed'] ?? 0; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-green-50 border border-green-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-circle text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-green-600">Resolved</p>
                    <p class="text-2xl font-bold text-green-900"><?php echo $status_stats['resolved'] ?? 0; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-chart-bar text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-blue-600">Total Reports</p>
                    <p class="text-2xl font-bold text-blue-900"><?php echo array_sum($status_stats); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="reviewed" <?php echo $status_filter === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                    <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Reason</label>
                <select name="category" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>All Reasons</option>
                    <option value="abuse" <?php echo $category_filter === 'abuse' ? 'selected' : ''; ?>>Abuse</option>
                    <option value="technical" <?php echo $category_filter === 'technical' ? 'selected' : ''; ?>>Technical Issue</option>
                    <option value="suggestion" <?php echo $category_filter === 'suggestion' ? 'selected' : ''; ?>>Suggestion</option>
                    <option value="spam" <?php echo $category_filter === 'spam' ? 'selected' : ''; ?>>Spam</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                <select name="priority" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="all" <?php echo $priority_filter === 'all' ? 'selected' : ''; ?>>All Priorities</option>
                    <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                    <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-filter mr-2"></i>Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Reports List -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Reports & Feedback</h2>
        </div>
        <div class="divide-y divide-gray-200">
            <?php foreach ($reports as $report): ?>
            <div class="p-6 hover:bg-gray-50">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center space-x-3 mb-2">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                <?php 
                                switch($report['status']) {
                                    case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                    case 'reviewed': echo 'bg-yellow-100 text-yellow-800'; break;
                                    case 'resolved': echo 'bg-green-100 text-green-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                <?php echo ucfirst($report['status']); ?>
                            </span>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                <?php echo ucfirst($report['reason']); ?>
                            </span>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                <?php 
                                switch($report['priority']) {
                                    case 'high': echo 'bg-red-100 text-red-800'; break;
                                    case 'medium': echo 'bg-yellow-100 text-yellow-800'; break;
                                    case 'low': echo 'bg-green-100 text-green-800'; break;
                                }
                                ?>">
                                <?php echo ucfirst($report['priority']); ?> Priority
                            </span>
                        </div>
                        
                        <h3 class="text-lg font-medium text-gray-900 mb-2"><?php echo htmlspecialchars($report['subject']); ?></h3>
                        <p class="text-gray-600 mb-3"><?php echo htmlspecialchars($report['description']); ?></p>
                        
                        <div class="text-sm text-gray-500 space-y-1">
                            <p><strong>Reporter:</strong> <?php echo htmlspecialchars($report['reporter_name']); ?> (<?php echo htmlspecialchars($report['reporter_email']); ?>)</p>
                            <?php if ($report['reported_id']): ?>
                            <p><strong>Reported User:</strong> <?php echo htmlspecialchars($report['reported_name']); ?> (<?php echo htmlspecialchars($report['reported_email']); ?>)</p>
                            <?php endif; ?>
                            <p><strong>Reason:</strong> <?php echo htmlspecialchars($report['reason']); ?></p>
                            <p><strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?></p>
                        </div>
                    </div>
                    
                    <?php if ($report['status'] !== 'resolved'): ?>
                    <div class="flex space-x-2 ml-4">
                        <form method="POST" class="inline">
                            <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                            <input type="hidden" name="action" value="resolve">
                            <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700">
                                <i class="fas fa-check mr-1"></i>Resolve
                            </button>
                        </form>
                        
                        <?php if ($report['status'] !== 'reviewed'): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                            <input type="hidden" name="action" value="escalate">
                            <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700">
                                <i class="fas fa-exclamation-triangle mr-1"></i>Escalate
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <?php if ($report['reported_id']): ?>
                        <a href="user-profile.php?id=<?php echo $report['reported_id']; ?>" 
                           class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                            <i class="fas fa-user mr-1"></i>View User
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
