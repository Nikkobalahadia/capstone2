<?php
require_once '../config/config.php';
require_once '../config/notification_helper.php';

// Check if user is admin
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user || $user['role'] !== 'admin') {
    redirect('dashboard.php');
}

$db = getDB();
$user_id = $user['id'];

// Get current time period (default: last 30 days)
$time_period = $_GET['period'] ?? '30days';
$date_filter = '';

switch ($time_period) {
    case '7days':
        $date_filter = "DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case '90days':
        $date_filter = "DATE_SUB(NOW(), INTERVAL 90 DAY)";
        break;
    case '1year':
        $date_filter = "DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
    default: // 30days
        $date_filter = "DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

// 1. MENTOR PERFORMANCE RECOMMENDATIONS
$mentor_recommendations = [];
$stmt = $db->prepare("
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        u.profile_picture,
        COUNT(DISTINCT m.id) as total_matches,
        SUM(CASE WHEN m.status = 'completed' THEN 1 ELSE 0 END) as completed_matches,
        AVG(sr.rating) as avg_rating,
        COUNT(DISTINCT CASE WHEN s.status = 'completed' THEN s.id END) as completed_sessions,
        COUNT(DISTINCT CASE WHEN s.status = 'cancelled' THEN s.id END) as cancelled_sessions
    FROM users u
    LEFT JOIN matches m ON (u.id = m.mentor_id AND m.created_at > $date_filter)
    LEFT JOIN sessions s ON m.id = s.match_id
    LEFT JOIN session_ratings sr ON (s.id = sr.session_id AND sr.rated_id = u.id)
    WHERE u.role = 'mentor' AND u.is_active = TRUE
    GROUP BY u.id
    ORDER BY avg_rating DESC, completed_sessions DESC
");
$stmt->execute();
$mentors = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($mentors as $mentor) {
    $completion_rate = $mentor['total_matches'] > 0 ? ($mentor['completed_matches'] / $mentor['total_matches']) * 100 : 0;
    $cancellation_rate = $mentor['total_matches'] > 0 ? ($mentor['cancelled_sessions'] / $mentor['total_matches']) * 100 : 0;
    
    $recommendation = [];
    $recommendation['mentor_id'] = $mentor['id'];
    $recommendation['name'] = $mentor['first_name'] . ' ' . $mentor['last_name'];
    $recommendation['rating'] = $mentor['avg_rating'] !== null ? round($mentor['avg_rating'], 2) : 0;
    $recommendation['completed_sessions'] = $mentor['completed_sessions'];
    $recommendation['completion_rate'] = round($completion_rate, 2);
    $recommendation['cancellation_rate'] = round($cancellation_rate, 2);
    
    // Generate recommendation
    $avg_rating = $mentor['avg_rating'] ?? 0;
    if ($avg_rating >= 4.5 && $completion_rate >= 90) {
        $recommendation['action'] = 'PROMOTE';
        $recommendation['reason'] = 'Excellent performance with high ratings and completion rate. Consider featuring as top mentor.';
        $recommendation['priority'] = 'high';
    } elseif ($avg_rating < 3.5 || $cancellation_rate > 30) {
        $recommendation['action'] = 'SUPPORT';
        $recommendation['reason'] = 'Performance needs improvement. Offer training or mentoring support.';
        $recommendation['priority'] = 'high';
    } elseif ($mentor['total_matches'] < 5) {
        $recommendation['action'] = 'ENGAGE';
        $recommendation['reason'] = 'Low match count. Encourage profile optimization and promotion.';
        $recommendation['priority'] = 'medium';
    } else {
        $recommendation['action'] = 'MONITOR';
        $recommendation['reason'] = 'Stable performance. Continue monitoring.';
        $recommendation['priority'] = 'low';
    }
    
    $mentor_recommendations[] = $recommendation;
}

// 2. STUDENT CHURN RISK ANALYSIS
$churn_risk = [];
$stmt = $db->prepare("
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        u.profile_picture,
        COUNT(DISTINCT m.id) as total_matches,
        COUNT(DISTINCT CASE WHEN m.status = 'rejected' THEN m.id END) as rejected_matches,
        COUNT(DISTINCT s.id) as total_sessions,
        COUNT(DISTINCT CASE WHEN s.status = 'cancelled' THEN s.id END) as cancelled_sessions,
        DATEDIFF(NOW(), MAX(s.session_date)) as days_since_last_session,
        DATEDIFF(NOW(), u.updated_at) as days_since_last_activity
    FROM users u
    LEFT JOIN matches m ON (u.id = m.student_id AND m.created_at > $date_filter)
    LEFT JOIN sessions s ON m.id = s.match_id
    WHERE u.role = 'student' AND u.is_active = TRUE
    GROUP BY u.id
    HAVING total_matches > 0
    ORDER BY days_since_last_activity DESC
");
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($students as $student) {
    $rejection_rate = $student['total_matches'] > 0 ? ($student['rejected_matches'] / $student['total_matches']) * 100 : 0;
    $cancellation_rate = $student['total_sessions'] > 0 ? ($student['cancelled_sessions'] / $student['total_sessions']) * 100 : 0;
    
    $risk = [];
    $risk['student_id'] = $student['id'];
    $risk['name'] = $student['first_name'] . ' ' . $student['last_name'];
    $risk['days_inactive'] = $student['days_since_last_activity'] ?? 0;
    $risk['rejection_rate'] = round($rejection_rate, 2);
    $risk['cancellation_rate'] = round($cancellation_rate, 2);
    
    // Calculate churn risk score (0-100)
    $risk_score = 0;
    $days_inactive = $student['days_since_last_activity'] ?? 0;
    if ($days_inactive > 30) $risk_score += 40;
    elseif ($days_inactive > 14) $risk_score += 20;
    
    if ($rejection_rate > 50) $risk_score += 30;
    elseif ($rejection_rate > 25) $risk_score += 15;
    
    if ($cancellation_rate > 50) $risk_score += 30;
    elseif ($cancellation_rate > 25) $risk_score += 15;
    
    $risk['risk_score'] = min($risk_score, 100);
    
    if ($risk_score >= 70) {
        $risk['level'] = 'CRITICAL';
        $risk['action'] = 'Send re-engagement email and offer incentives';
    } elseif ($risk_score >= 50) {
        $risk['level'] = 'HIGH';
        $risk['action'] = 'Send personalized message and suggest new mentors';
    } elseif ($risk_score >= 30) {
        $risk['level'] = 'MEDIUM';
        $risk['action'] = 'Monitor activity and send occasional updates';
    } else {
        $risk['level'] = 'LOW';
        $risk['action'] = 'Continue normal engagement';
    }
    
    $churn_risk[] = $risk;
}

// 3. OPTIMAL PRICING RECOMMENDATIONS
$pricing_recommendations = [];
$stmt = $db->prepare("
    SELECT 
        us.subject_name,
        COUNT(DISTINCT m.id) as demand_count,
        COUNT(DISTINCT CASE WHEN m.status = 'accepted' THEN m.id END) as successful_matches,
        COUNT(DISTINCT CASE WHEN m.status = 'rejected' THEN m.id END) as rejected_matches,
        AVG(u.hourly_rate) as avg_rate,
        MIN(u.hourly_rate) as min_rate,
        MAX(u.hourly_rate) as max_rate,
        AVG(sr.rating) as avg_rating
    FROM user_subjects us
    LEFT JOIN users u ON us.user_id = u.id AND u.role = 'mentor'
    LEFT JOIN matches m ON (us.subject_name = m.subject AND m.created_at > $date_filter)
    LEFT JOIN sessions s ON m.id = s.match_id
    LEFT JOIN session_ratings sr ON (s.id = sr.session_id AND sr.rated_id = u.id)
    WHERE us.subject_name IS NOT NULL
    GROUP BY us.subject_name
    ORDER BY demand_count DESC
");
$stmt->execute();
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($subjects as $subject) {
    $success_rate = $subject['demand_count'] > 0 ? ($subject['successful_matches'] / $subject['demand_count']) * 100 : 0;
    
    $pricing = [];
    $pricing['subject'] = $subject['subject_name'];
    $pricing['demand'] = $subject['demand_count'];
    $pricing['success_rate'] = round($success_rate, 2);
    $pricing['current_avg_rate'] = $subject['avg_rate'] !== null ? round($subject['avg_rate'], 2) : 0;
    $pricing['min_rate'] = $subject['min_rate'] !== null ? round($subject['min_rate'], 2) : 0;
    $pricing['max_rate'] = $subject['max_rate'] !== null ? round($subject['max_rate'], 2) : 0;
    $pricing['avg_rating'] = $subject['avg_rating'] !== null ? round($subject['avg_rating'], 2) : 0;
    
    // Pricing recommendation based on demand and success rate
    $avg_rate = $subject['avg_rate'] ?? 0;
    if ($subject['demand_count'] > 50 && $success_rate > 80) {
        $pricing['recommendation'] = 'INCREASE_RATE';
        $pricing['suggested_rate'] = $avg_rate > 0 ? round($avg_rate * 1.15, 2) : 0;
        $pricing['reason'] = 'High demand with excellent success rate. Market can support higher rates.';
    } elseif ($subject['demand_count'] < 10 && $success_rate < 50) {
        $pricing['recommendation'] = 'DECREASE_RATE';
        $pricing['suggested_rate'] = $avg_rate > 0 ? round($avg_rate * 0.85, 2) : 0;
        $pricing['reason'] = 'Low demand and success rate. Lower rates may attract more students.';
    } else {
        $pricing['recommendation'] = 'MAINTAIN_RATE';
        $pricing['suggested_rate'] = $avg_rate > 0 ? round($avg_rate, 2) : 0;
        $pricing['reason'] = 'Current pricing is optimal for market conditions.';
    }
    
    $pricing_recommendations[] = $pricing;
}

// 4. OPTIMAL MATCHING RECOMMENDATIONS
$matching_recommendations = [];
$stmt = $db->prepare("
    SELECT 
        s.id as student_id,
        s.first_name as student_first,
        s.last_name as student_last,
        m.id as mentor_id,
        m.first_name as mentor_first,
        m.last_name as mentor_last,
        COUNT(DISTINCT match_record.id) as existing_matches,
        AVG(sr.rating) as avg_rating,
        GROUP_CONCAT(DISTINCT us.subject_name) as mentor_subjects
    FROM users s
    CROSS JOIN users m
    LEFT JOIN matches match_record ON (s.id = match_record.student_id AND m.id = match_record.mentor_id)
    LEFT JOIN user_subjects us ON m.id = us.user_id
    LEFT JOIN sessions sess ON match_record.id = sess.match_id
    LEFT JOIN session_ratings sr ON (sess.id = sr.session_id AND sr.rated_id = m.id)
    WHERE s.role = 'student' 
    AND m.role = 'mentor'
    AND s.is_active = TRUE
    AND m.is_active = TRUE
    AND match_record.id IS NULL
    AND s.created_at > $date_filter
    GROUP BY s.id, m.id
    HAVING avg_rating >= 4.0 OR avg_rating IS NULL
    ORDER BY avg_rating DESC
    LIMIT 20
");
$stmt->execute();
$potential_matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($potential_matches as $match) {
    $recommendation = [];
    $recommendation['student_name'] = $match['student_first'] . ' ' . $match['student_last'];
    $recommendation['mentor_name'] = $match['mentor_first'] . ' ' . $match['mentor_last'];
    $recommendation['mentor_rating'] = $match['avg_rating'] !== null ? round($match['avg_rating'], 2) : 0;
    $recommendation['mentor_subjects'] = $match['mentor_subjects'];
    $recommendation['confidence'] = $match['avg_rating'] ? round($match['avg_rating'] * 20, 2) : 75;
    $recommendation['reason'] = 'High-rated mentor with relevant expertise. Strong potential for successful match.';
    
    $matching_recommendations[] = $recommendation;
}

// 5. GROWTH OPPORTUNITIES
$growth_opportunities = [];

// High-demand subjects with few mentors
$stmt = $db->prepare("
    SELECT 
        m.subject,
        COUNT(DISTINCT m.id) as demand,
        COUNT(DISTINCT m.mentor_id) as mentor_count,
        AVG(sr.rating) as avg_rating
    FROM matches m
    LEFT JOIN sessions s ON m.id = s.match_id
    LEFT JOIN session_ratings sr ON (s.id = sr.session_id AND sr.rated_id = m.mentor_id)
    WHERE m.created_at > $date_filter
    GROUP BY m.subject
    HAVING demand > 10 AND mentor_count < 5
    ORDER BY demand DESC
");
$stmt->execute();
$high_demand_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($high_demand_subjects as $subject) {
    $opportunity = [];
    $opportunity['type'] = 'RECRUIT_MENTORS';
    $opportunity['subject'] = $subject['subject'];
    $opportunity['demand'] = $subject['demand'];
    $opportunity['current_mentors'] = $subject['mentor_count'];
    $opportunity['action'] = 'Recruit ' . ceil($subject['demand'] / 10) . ' more mentors for ' . $subject['subject'];
    $opportunity['impact'] = 'Could serve ' . ($subject['demand'] * 2) . ' additional students';
    
    $growth_opportunities[] = $opportunity;
}

// Peak hours analysis
$stmt = $db->prepare("
    SELECT 
        HOUR(s.start_time) as hour,
        COUNT(DISTINCT s.id) as session_count,
        COUNT(DISTINCT CASE WHEN s.status = 'completed' THEN s.id END) as completed
    FROM sessions s
    WHERE s.created_at > $date_filter
    GROUP BY HOUR(s.start_time)
    ORDER BY session_count DESC
    LIMIT 3
");
$stmt->execute();
$peak_hours = $stmt->fetchAll(PDO::FETCH_ASSOC);

$peak_hours_str = implode(', ', array_map(function($h) { return $h['hour'] . ':00'; }, $peak_hours));
$growth_opportunities[] = [
    'type' => 'OPTIMIZE_SCHEDULING',
    'action' => 'Promote peak hours: ' . $peak_hours_str,
    'impact' => 'Increase mentor availability during high-demand hours'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptive Analytics - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { background-color: #2c3e50; color: white; min-height: 100vh; }
        .sidebar a { color: #ecf0f1; text-decoration: none; padding: 10px 15px; display: block; }
        .sidebar a:hover { background-color: #34495e; }
        .card { border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .badge-promote { background-color: #27ae60; }
        .badge-support { background-color: #e74c3c; }
        .badge-engage { background-color: #f39c12; }
        .badge-monitor { background-color: #3498db; }
        .risk-critical { background-color: #e74c3c; color: white; }
        .risk-high { background-color: #f39c12; color: white; }
        .risk-medium { background-color: #f1c40f; color: #333; }
        .risk-low { background-color: #27ae60; color: white; }
        .recommendation-card { border-left: 4px solid #3498db; }
        .metric-box { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; }
        .metric-box.success { background: linear-gradient(135deg, #27ae60 0%, #229954 100%); }
        .metric-box.warning { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); }
        .metric-box.danger { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); }
        .tab-content { margin-top: 20px; }
        .action-btn { margin: 5px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <h4 class="p-3">Admin Panel</h4>
                <a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
                <a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a>
                <a href="prescriptive-analytics.php" class="active" style="background-color: #34495e;"><i class="fas fa-lightbulb"></i> Prescriptive Analytics</a>
                <a href="users.php"><i class="fas fa-users"></i> Users</a>
                <a href="commissions.php"><i class="fas fa-money-bill"></i> Commissions</a>
                <a href="reports.php"><i class="fas fa-flag"></i> Reports</a>
                <a href="verifications.php"><i class="fas fa-check-circle"></i> Verifications</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-lightbulb"></i> Prescriptive Analytics</h1>
                    <div>
                        <select class="form-select d-inline-block w-auto" onchange="location.href='?period=' + this.value">
                            <option value="7days" <?= $time_period === '7days' ? 'selected' : '' ?>>Last 7 Days</option>
                            <option value="30days" <?= $time_period === '30days' ? 'selected' : '' ?>>Last 30 Days</option>
                            <option value="90days" <?= $time_period === '90days' ? 'selected' : '' ?>>Last 90 Days</option>
                            <option value="1year" <?= $time_period === '1year' ? 'selected' : '' ?>>Last Year</option>
                        </select>
                    </div>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#mentors">Mentor Performance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#churn">Churn Risk</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#pricing">Pricing Strategy</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#matching">Smart Matching</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#growth">Growth Opportunities</a>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- MENTOR PERFORMANCE TAB -->
                    <div id="mentors" class="tab-pane fade show active">
                        <h3 class="mt-4">Mentor Performance Recommendations</h3>
                        <div class="row">
                            <?php foreach ($mentor_recommendations as $rec): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card recommendation-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="card-title"><?= htmlspecialchars($rec['name']) ?></h5>
                                                <p class="text-muted">Rating: <strong><?= $rec['rating'] ?>/5</strong> | Sessions: <strong><?= $rec['completed_sessions'] ?></strong></p>
                                            </div>
                                            <span class="badge badge-<?= strtolower($rec['action']) ?>"><?= $rec['action'] ?></span>
                                        </div>
                                        <p class="card-text"><?= htmlspecialchars($rec['reason']) ?></p>
                                        <small class="text-muted">
                                            Completion Rate: <?= $rec['completion_rate'] ?>% | 
                                            Cancellation Rate: <?= $rec['cancellation_rate'] ?>%
                                        </small>
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-primary action-btn">View Profile</button>
                                            <button class="btn btn-sm btn-secondary action-btn">Send Message</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- CHURN RISK TAB -->
                    <div id="churn" class="tab-pane fade">
                        <h3 class="mt-4">Student Churn Risk Analysis</h3>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Risk Level</th>
                                        <th>Risk Score</th>
                                        <th>Days Inactive</th>
                                        <th>Rejection Rate</th>
                                        <th>Recommended Action</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($churn_risk as $risk): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($risk['name']) ?></td>
                                        <td><span class="badge risk-<?= strtolower($risk['level']) ?>"><?= $risk['level'] ?></span></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" style="width: <?= $risk['risk_score'] ?>%">
                                                    <?= $risk['risk_score'] ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= $risk['days_inactive'] ?> days</td>
                                        <td><?= $risk['rejection_rate'] ?>%</td>
                                        <td><?= htmlspecialchars($risk['action']) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning">Send Email</button>
                                            <button class="btn btn-sm btn-info">View Profile</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- PRICING STRATEGY TAB -->
                    <div id="pricing" class="tab-pane fade">
                        <h3 class="mt-4">Pricing Strategy Recommendations</h3>
                        <div class="row">
                            <?php foreach ($pricing_recommendations as $pricing): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($pricing['subject']) ?></h5>
                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <small class="text-muted">Demand</small>
                                                <p class="h5"><?= $pricing['demand'] ?></p>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Success Rate</small>
                                                <p class="h5"><?= $pricing['success_rate'] ?>%</p>
                                            </div>
                                        </div>
                                        <div class="alert alert-info">
                                            <strong>Recommendation: <?= $pricing['recommendation'] ?></strong><br>
                                            Current Rate: ₱<?= $pricing['current_avg_rate'] ?>/hr<br>
                                            Suggested Rate: ₱<?= $pricing['suggested_rate'] ?>/hr<br>
                                            <small><?= htmlspecialchars($pricing['reason']) ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- SMART MATCHING TAB -->
                    <div id="matching" class="tab-pane fade">
                        <h3 class="mt-4">Smart Matching Recommendations</h3>
                        <p class="text-muted">Top <?= count($matching_recommendations) ?> potential matches based on compatibility and mentor ratings</p>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Student</th>
                                        <th>Mentor</th>
                                        <th>Mentor Rating</th>
                                        <th>Expertise</th>
                                        <th>Confidence</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($matching_recommendations as $match): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($match['student_name']) ?></td>
                                        <td><?= htmlspecialchars($match['mentor_name']) ?></td>
                                        <td>
                                            <span class="badge bg-success"><?= $match['mentor_rating'] ?>/5</span>
                                        </td>
                                        <td><?= htmlspecialchars(substr($match['mentor_subjects'], 0, 50)) ?>...</td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $match['confidence'] ?>%">
                                                    <?= $match['confidence'] ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary">Create Match</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- GROWTH OPPORTUNITIES TAB -->
                    <div id="growth" class="tab-pane fade">
                        <h3 class="mt-4">Growth Opportunities</h3>
                        <div class="row">
                            <?php foreach ($growth_opportunities as $opp): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="fas fa-rocket"></i> <?= $opp['type'] ?>
                                        </h5>
                                        <?php if (isset($opp['subject'])): ?>
                                            <p class="card-text">
                                                <strong>Subject:</strong> <?= htmlspecialchars($opp['subject']) ?><br>
                                                <strong>Current Demand:</strong> <?= $opp['demand'] ?> requests<br>
                                                <strong>Current Mentors:</strong> <?= $opp['current_mentors'] ?>
                                            </p>
                                        <?php endif; ?>
                                        <p class="card-text"><strong>Action:</strong> <?= htmlspecialchars($opp['action']) ?></p>
                                        <p class="text-success"><strong>Potential Impact:</strong> <?= htmlspecialchars($opp['impact']) ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
