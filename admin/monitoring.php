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

// Get real-time statistics
$stmt = $db->query("SELECT COUNT(*) as total_online FROM user_activity_logs WHERE action = 'login' AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
$online_users = $stmt->fetch()['total_online'];

// Get login/logout activity for today
$stmt = $db->query("
    SELECT CONCAT(u.first_name, ' ', u.last_name) as name, u.email, ual.action, ual.ip_address, ual.created_at 
    FROM user_activity_logs ual 
    JOIN users u ON ual.user_id = u.id 
    WHERE ual.action IN ('login', 'logout') 
    AND DATE(ual.created_at) = CURDATE() 
    ORDER BY ual.created_at DESC 
    LIMIT 50
");
$login_activity = $stmt->fetchAll();

// Get suspicious activity
$stmt = $db->query("
    SELECT CONCAT(u.first_name, ' ', u.last_name) as name, u.email, COUNT(*) as report_count, 
           GROUP_CONCAT(DISTINCT ual.action) as actions,
           MAX(ual.created_at) as last_activity
    FROM user_activity_logs ual 
    JOIN users u ON ual.user_id = u.id 
    WHERE ual.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY ual.user_id 
    HAVING COUNT(*) > 50 OR COUNT(DISTINCT ual.action) > 10
    ORDER BY report_count DESC
");
$suspicious_activity = $stmt->fetchAll();

// Get system metrics
$stmt = $db->query("
    SELECT 
        action,
        COUNT(*) as count,
        DATE(created_at) as date
    FROM user_activity_logs 
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY action, DATE(created_at)
    ORDER BY date DESC, count DESC
");
$system_metrics = $stmt->fetchAll();

$page_title = "System Monitoring";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">System Usage Monitoring</h1>
        <div class="d-flex gap-2">
            <button onclick="refreshData()" class="btn btn-primary">
                <i class="fas fa-sync-alt me-2"></i>Refresh
            </button>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Real-time Stats -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Users Online</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="online-count"><?php echo $online_users; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Today's Logins</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count(array_filter($login_activity, fn($log) => $log['action'] === 'login')); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-sign-in-alt fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Suspicious Activity</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($suspicious_activity); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">System Load</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Normal</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Login/Logout Activity -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Recent Login Activity</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Action</th>
                            <th>IP Address</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($login_activity as $log): ?>
                        <tr>
                            <td>
                                <div class="font-weight-bold"><?php echo htmlspecialchars($log['name']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($log['email']); ?></div>
                            </td>
                            <td>
                                <span class="badge <?php echo $log['action'] === 'login' ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo ucfirst($log['action']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Suspicious Activity -->
    <?php if (!empty($suspicious_activity)): ?>
    <div class="card shadow mb-4 border-left-danger">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>Suspicious Activity Detected
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Activity Count</th>
                            <th>Actions</th>
                            <th>Last Activity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suspicious_activity as $activity): ?>
                        <tr class="table-warning">
                            <td>
                                <div class="font-weight-bold"><?php echo htmlspecialchars($activity['name']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($activity['email']); ?></div>
                            </td>
                            <td>
                                <span class="badge badge-danger">
                                    <?php echo $activity['report_count']; ?> activities
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($activity['actions']); ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($activity['last_activity'])); ?></td>
                            <td>
                                <button onclick="investigateUser('<?php echo $activity['email']; ?>')" class="btn btn-sm btn-outline-danger me-2">
                                    <i class="fas fa-search me-1"></i>Investigate
                                </button>
                                <button onclick="suspendUser('<?php echo $activity['email']; ?>')" class="btn btn-sm btn-outline-warning">
                                    <i class="fas fa-ban me-1"></i>Suspend
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- System Metrics Chart -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">System Activity Metrics (Last 7 Days)</h6>
        </div>
        <div class="card-body">
            <canvas id="systemMetricsChart" width="400" height="200"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// System metrics chart
const ctx = document.getElementById('systemMetricsChart').getContext('2d');
const metricsData = <?php echo json_encode($system_metrics); ?>;

// Process data for chart
const dates = [...new Set(metricsData.map(item => item.date))].sort();
const actions = [...new Set(metricsData.map(item => item.action))];

const datasets = actions.map((action, index) => {
    const colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4'];
    return {
        label: action.replace('_', ' ').toUpperCase(),
        data: dates.map(date => {
            const item = metricsData.find(m => m.date === date && m.action === action);
            return item ? item.count : 0;
        }),
        borderColor: colors[index % colors.length],
        backgroundColor: colors[index % colors.length] + '20',
        tension: 0.1
    };
});

new Chart(ctx, {
    type: 'line',
    data: {
        labels: dates,
        datasets: datasets
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'System Activity Over Time'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Auto-refresh functionality
function refreshData() {
    location.reload();
}

// Set auto-refresh every 30 seconds
setInterval(refreshData, 30000);

function investigateUser(email) {
    window.open(`user-profile.php?email=${encodeURIComponent(email)}`, '_blank');
}

function suspendUser(email) {
    if (confirm(`Are you sure you want to suspend the user: ${email}?`)) {
        // Add AJAX call to suspend user
        fetch('actions/suspend_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email: email })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('User suspended successfully');
                location.reload();
            } else {
                alert('Error suspending user: ' + data.message);
            }
        });
    }
}
</script>

<?php include '../includes/footer.php'; ?>
