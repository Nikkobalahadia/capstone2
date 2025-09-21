<?php
require_once '../config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
$db = getDB();

// Get user's report statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_reports,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_reports,
        COUNT(CASE WHEN status = 'reviewed' THEN 1 END) as reviewed_reports,
        COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_reports
    FROM user_reports 
    WHERE reporter_id = ?
");
$stmt->execute([$user['id']]);
$report_stats = $stmt->fetch();

// Get recent reports
$stmt = $db->prepare("
    SELECT r.*, 
           CONCAT(reported.first_name, ' ', reported.last_name) as reported_name
    FROM user_reports r
    LEFT JOIN users reported ON r.reported_id = reported.id
    WHERE r.reporter_id = ?
    ORDER BY r.created_at DESC
    LIMIT 3
");
$stmt->execute([$user['id']]);
$recent_reports = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Center - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="../dashboard.php" class="logo">StudyConnect</a>
                <ul class="nav-links">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <li><a href="../matches/">Matches</a></li>
                    <li><a href="../sessions/">Sessions</a></li>
                    <li><a href="../profile/">Profile</a></li>
                    <li><a href="../auth/logout.php" class="btn btn-outline">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Reports & Feedback Center</h1>
            <p class="text-gray-600">Submit reports, track feedback, and help improve the StudyConnect community.</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-clipboard-list text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-blue-600">Total Reports</p>
                        <p class="text-2xl font-bold text-blue-900"><?php echo $report_stats['total_reports']; ?></p>
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
                        <p class="text-sm font-medium text-yellow-600">Pending</p>
                        <p class="text-2xl font-bold text-yellow-900"><?php echo $report_stats['pending_reports']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-eye text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-blue-600">Under Review</p>
                        <p class="text-2xl font-bold text-blue-900"><?php echo $report_stats['reviewed_reports']; ?></p>
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
                        <p class="text-2xl font-bold text-green-900"><?php echo $report_stats['resolved_reports']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-plus-circle text-blue-500 mr-2"></i>Submit New Report
                </h2>
                <p class="text-gray-600 mb-6">Report issues, inappropriate behavior, or provide feedback to help improve our platform.</p>
                <div class="space-y-3">
                    <a href="submit.php?category=abuse" class="block w-full bg-red-50 border border-red-200 rounded-lg p-4 hover:bg-red-100 transition-colors">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                            <div>
                                <div class="font-medium text-red-900">Report Abuse</div>
                                <div class="text-sm text-red-600">Inappropriate behavior or harassment</div>
                            </div>
                        </div>
                    </a>
                    <a href="submit.php?category=technical" class="block w-full bg-blue-50 border border-blue-200 rounded-lg p-4 hover:bg-blue-100 transition-colors">
                        <div class="flex items-center">
                            <i class="fas fa-bug text-blue-500 mr-3"></i>
                            <div>
                                <div class="font-medium text-blue-900">Technical Issue</div>
                                <div class="text-sm text-blue-600">Platform bugs or technical problems</div>
                            </div>
                        </div>
                    </a>
                    <a href="submit.php?category=suggestion" class="block w-full bg-green-50 border border-green-200 rounded-lg p-4 hover:bg-green-100 transition-colors">
                        <div class="flex items-center">
                            <i class="fas fa-lightbulb text-green-500 mr-3"></i>
                            <div>
                                <div class="font-medium text-green-900">Suggestion</div>
                                <div class="text-sm text-green-600">Ideas for platform improvements</div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-history text-green-500 mr-2"></i>Recent Reports
                </h2>
                <?php if (empty($recent_reports)): ?>
                <div class="text-center py-8">
                    <div class="text-gray-400 mb-3">
                        <i class="fas fa-clipboard-list text-4xl"></i>
                    </div>
                    <p class="text-gray-600">No reports submitted yet.</p>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($recent_reports as $report): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex-1">
                                <div class="font-medium text-gray-900">
                                    <?php 
                                    $parts = explode(': ', $report['description'], 2);
                                    echo htmlspecialchars($parts[0]); 
                                    ?>
                                </div>
                                <div class="text-sm text-gray-600">
                                    <?php echo ucfirst($report['reason']); ?> • 
                                    <?php echo date('M j, Y', strtotime($report['created_at'])); ?>
                                </div>
                            </div>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                <?php 
                                switch($report['status']) {
                                    case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                    case 'reviewed': echo 'bg-blue-100 text-blue-800'; break;
                                    case 'resolved': echo 'bg-green-100 text-green-800'; break;
                                }
                                ?>">
                                <?php echo ucfirst($report['status']); ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="text-center pt-4">
                        <a href="my-reports.php" class="text-blue-600 hover:text-blue-800 font-medium">
                            View All Reports <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Help Section -->
        <div class="bg-gray-50 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-question-circle text-blue-500 mr-2"></i>Need Help?
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Report Guidelines</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Be specific and detailed</li>
                        <li>• Include relevant evidence</li>
                        <li>• Use appropriate categories</li>
                        <li>• Be respectful and professional</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Response Times</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• High Priority: 24 hours</li>
                        <li>• Medium Priority: 48 hours</li>
                        <li>• Low Priority: 72 hours</li>
                        <li>• Suggestions: 1 week</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Contact Support</h4>
                    <p class="text-sm text-gray-600 mb-2">For urgent issues, contact us directly:</p>
                    <p class="text-sm text-blue-600">support@studyconnect.com</p>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2024 StudyConnect. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
