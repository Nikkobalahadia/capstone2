<?php
require_once '../config/config.php';

if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user || $user['role'] !== 'admin') {
    redirect('dashboard.php');
}

$db = getDB();

// Get date range
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');

// Commission statistics
$commission_stats = $db->prepare("
    SELECT 
        COUNT(*) as total_commissions,
        SUM(CASE WHEN payment_status = 'pending' THEN commission_amount ELSE 0 END) as pending_amount,
        SUM(CASE WHEN payment_status = 'submitted' THEN commission_amount ELSE 0 END) as submitted_amount,
        SUM(CASE WHEN payment_status = 'verified' THEN commission_amount ELSE 0 END) as verified_amount,
        SUM(CASE WHEN payment_status = 'rejected' THEN commission_amount ELSE 0 END) as rejected_amount,
        SUM(session_amount) as total_session_revenue,
        SUM(commission_amount) as total_commission_revenue
    FROM commission_payments
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$commission_stats->execute([$date_from, $date_to]);
$stats = $commission_stats->fetch();

// Monthly revenue trend
$monthly_revenue = $db->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(commission_amount) as commission_revenue,
        SUM(session_amount) as session_revenue,
        COUNT(*) as transaction_count
    FROM commission_payments
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
")->fetchAll();

// Top earning mentors
$top_mentors = $db->prepare("
    SELECT 
        u.id,
        CONCAT(u.first_name, ' ', u.last_name) as mentor_name,
        u.email,
        COUNT(cp.id) as total_sessions,
        SUM(cp.session_amount) as total_earned,
        SUM(cp.commission_amount) as total_commission_paid,
        SUM(CASE WHEN cp.payment_status = 'verified' THEN cp.commission_amount ELSE 0 END) as verified_commissions,
        SUM(CASE WHEN cp.payment_status = 'pending' THEN cp.commission_amount ELSE 0 END) as pending_commissions
    FROM users u
    JOIN commission_payments cp ON u.id = cp.mentor_id
    WHERE DATE(cp.created_at) BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY total_earned DESC
    LIMIT 10
");
$top_mentors->execute([$date_from, $date_to]);
$mentors = $top_mentors->fetchAll();

// Payment status breakdown
$payment_breakdown = $db->prepare("
    SELECT 
        payment_status,
        COUNT(*) as count,
        SUM(commission_amount) as total_amount
    FROM commission_payments
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY payment_status
");
$payment_breakdown->execute([$date_from, $date_to]);
$breakdown = $payment_breakdown->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Overview - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; border-radius: 8px; margin: 4px 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
        .stat-card { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-value { font-size: 2rem; font-weight: 700; }
        .stat-label { color: #6c757d; font-size: 0.875rem; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?>
    <?php include '../includes/admin-header.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Financial Overview</h1>
                    <p class="text-muted">Track commission revenue and mentor earnings</p>
                </div>
            </div>

            <!-- Date Filter -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Date From</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date To</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i> Apply Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Revenue Statistics -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-label">Total Session Revenue</div>
                        <div class="stat-value text-primary">₱<?php echo number_format($stats['total_session_revenue'] ?? 0, 2); ?></div>
                        <div class="text-muted small"><?php echo $stats['total_commissions']; ?> sessions</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-label">Commission Revenue</div>
                        <div class="stat-value text-success">₱<?php echo number_format($stats['verified_amount'] ?? 0, 2); ?></div>
                        <div class="text-muted small">Verified payments</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-label">Pending Commissions</div>
                        <div class="stat-value text-warning">₱<?php echo number_format($stats['pending_amount'] ?? 0, 2); ?></div>
                        <div class="text-muted small">Awaiting payment</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-label">Submitted Commissions</div>
                        <div class="stat-value text-info">₱<?php echo number_format($stats['submitted_amount'] ?? 0, 2); ?></div>
                        <div class="text-muted small">Awaiting verification</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Revenue Trend Chart -->
                <div class="col-lg-8 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Monthly Revenue Trend</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="revenueChart" height="100"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Payment Status Breakdown -->
                <div class="col-lg-4 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Payment Status</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Earning Mentors -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top Earning Mentors</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($mentors)): ?>
                        <p class="text-center text-muted py-4">No mentor earnings data available for the selected period.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Mentor</th>
                                        <th>Sessions</th>
                                        <th>Total Earned</th>
                                        <th>Commission Paid</th>
                                        <th>Pending</th>
                                        <th>Payment Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mentors as $index => $mentor): ?>
                                        <tr>
                                            <td><strong>#<?php echo $index + 1; ?></strong></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($mentor['mentor_name']); ?></strong>
                                                <div class="small text-muted"><?php echo htmlspecialchars($mentor['email']); ?></div>
                                            </td>
                                            <td><?php echo $mentor['total_sessions']; ?></td>
                                            <td><strong>₱<?php echo number_format($mentor['total_earned'], 2); ?></strong></td>
                                            <td class="text-success">₱<?php echo number_format($mentor['verified_commissions'], 2); ?></td>
                                            <td class="text-warning">₱<?php echo number_format($mentor['pending_commissions'], 2); ?></td>
                                            <td>
                                                <?php 
                                                $payment_rate = $mentor['total_commission_paid'] > 0 
                                                    ? ($mentor['verified_commissions'] / $mentor['total_commission_paid']) * 100 
                                                    : 0;
                                                ?>
                                                <span class="badge <?php echo $payment_rate >= 80 ? 'bg-success' : ($payment_rate >= 50 ? 'bg-warning' : 'bg-danger'); ?>">
                                                    <?php echo number_format($payment_rate, 0); ?>%
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Revenue Trend Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueData = <?php echo json_encode(array_reverse($monthly_revenue)); ?>;
        
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: revenueData.map(d => {
                    const date = new Date(d.month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Commission Revenue',
                    data: revenueData.map(d => d.commission_revenue),
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Session Revenue',
                    data: revenueData.map(d => d.session_revenue),
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ₱' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Payment Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusData = <?php echo json_encode($breakdown); ?>;
        
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusData.map(d => d.payment_status.charAt(0).toUpperCase() + d.payment_status.slice(1)),
                datasets: [{
                    data: statusData.map(d => d.total_amount),
                    backgroundColor: ['#ffc107', '#0dcaf0', '#198754', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ₱' + context.parsed.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
