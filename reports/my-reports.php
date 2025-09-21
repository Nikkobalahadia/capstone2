<?php
require_once '../config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
$db = getDB();

// Get user's submitted reports
$stmt = $db->prepare("
    SELECT r.*, 
           CONCAT(reported.first_name, ' ', reported.last_name) as reported_name,
           reported.role as reported_role
    FROM user_reports r
    LEFT JOIN users reported ON r.reported_id = reported.id
    WHERE r.reporter_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$user['id']]);
$my_reports = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reports - StudyConnect</title>
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
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">My Reports</h1>
                <p class="text-gray-600">Track the status of your submitted reports and feedback.</p>
            </div>
            <a href="submit.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i>Submit New Report
            </a>
        </div>

        <?php if (empty($my_reports)): ?>
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <div class="text-gray-400 mb-4">
                <i class="fas fa-clipboard-list text-6xl"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No Reports Yet</h3>
            <p class="text-gray-600 mb-6">You haven't submitted any reports or feedback yet.</p>
            <a href="submit.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i>Submit Your First Report
            </a>
        </div>
        <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($my_reports as $report): ?>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <div class="flex items-center space-x-3 mb-2">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                <?php 
                                switch($report['status']) {
                                    case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                    case 'reviewed': echo 'bg-blue-100 text-blue-800'; break;
                                    case 'resolved': echo 'bg-green-100 text-green-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                <?php echo ucfirst($report['status']); ?>
                            </span>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                                <?php echo ucfirst($report['reason']); ?>
                            </span>
                            <span class="text-sm text-gray-500">
                                <?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?>
                            </span>
                        </div>
                        
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">
                            <?php 
                            $parts = explode(': ', $report['description'], 2);
                            echo htmlspecialchars($parts[0]); 
                            ?>
                        </h3>
                        
                        <p class="text-gray-600 mb-3">
                            <?php 
                            echo htmlspecialchars($parts[1] ?? $report['description']); 
                            ?>
                        </p>
                        
                        <?php if ($report['reported_name']): ?>
                        <p class="text-sm text-gray-500">
                            <strong>Regarding:</strong> <?php echo htmlspecialchars($report['reported_name']); ?> 
                            (<?php echo ucfirst($report['reported_role']); ?>)
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="ml-4">
                        <?php if ($report['status'] === 'pending'): ?>
                        <div class="flex items-center text-yellow-600">
                            <i class="fas fa-clock mr-2"></i>
                            <span class="text-sm font-medium">Under Review</span>
                        </div>
                        <?php elseif ($report['status'] === 'reviewed'): ?>
                        <div class="flex items-center text-blue-600">
                            <i class="fas fa-eye mr-2"></i>
                            <span class="text-sm font-medium">Being Processed</span>
                        </div>
                        <?php else: ?>
                        <div class="flex items-center text-green-600">
                            <i class="fas fa-check-circle mr-2"></i>
                            <span class="text-sm font-medium">Resolved</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Status Timeline -->
                <div class="border-t pt-4">
                    <div class="flex items-center space-x-4 text-sm">
                        <div class="flex items-center text-green-600">
                            <i class="fas fa-check-circle mr-1"></i>
                            <span>Submitted</span>
                        </div>
                        <div class="flex-1 border-t border-gray-200"></div>
                        <div class="flex items-center <?php echo in_array($report['status'], ['reviewed', 'resolved']) ? 'text-green-600' : 'text-gray-400'; ?>">
                            <i class="fas fa-eye mr-1"></i>
                            <span>Under Review</span>
                        </div>
                        <div class="flex-1 border-t border-gray-200"></div>
                        <div class="flex items-center <?php echo $report['status'] === 'resolved' ? 'text-green-600' : 'text-gray-400'; ?>">
                            <i class="fas fa-check-circle mr-1"></i>
                            <span>Resolved</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>

    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2024 StudyConnect. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
