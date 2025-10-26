<?php
require_once '../config/config.php';
$page_title = "Report a Problem or Feedback";

// Check if user is logged in
$user = null;
if (is_logged_in()) {
    $user = get_logged_in_user();
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_type = trim($_POST['report_type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $reported_user_id = !empty($_POST['reported_user_id']) ? intval($_POST['reported_user_id']) : null;

    if (empty($report_type) || empty($description)) {
        $error_message = 'Please select a report type and provide a description.';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO user_reports (reporter_id, reported_id, reason, description, status, created_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $user ? $user['id'] : null,
                $reported_user_id,
                $report_type,
                $description
            ]);
            
            $success_message = 'Thank you for your report. Our team will review it and take appropriate action.';
            $report_type = $description = $reported_user_id = '';
        } catch (Exception $e) {
            $error_message = 'An error occurred while submitting your report. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Study Buddy</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="../index.php" class="logo">Study Buddy</a>
                <ul class="nav-links">
                    <li><a href="../index.php">Home</a></li>
                    <?php if (is_logged_in()): ?>
                        <li><a href="../dashboard.php">Dashboard</a></li>
                        <li><a href="../auth/logout.php" class="btn btn-outline">Logout</a></li>
                    <?php else: ?>
                        <li><a href="../auth/login.php">Login</a></li>
                        <li><a href="../auth/register.php" class="btn btn-primary">Sign Up</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main class="py-8">
        <div class="container" style="max-width: 700px;">
            <h1 class="text-3xl font-bold mb-6">Report a Problem or Provide Feedback</h1>

            <?php if ($success_message): ?>
                <div class="alert alert-success mb-4" style="padding: 1rem; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 0.5rem; color: #155724;">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger mb-4" style="padding: 1rem; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 0.5rem; color: #721c24;">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <p class="text-secondary mb-6">Help us improve Study Buddy by reporting issues or sharing your feedback. All reports are reviewed by our team.</p>

                    <form method="POST" action="">
                        <div class="form-group mb-4">
                            <label for="report_type" class="form-label">What would you like to report?</label>
                            <select id="report_type" name="report_type" class="form-control" required>
                                <option value="">Select a type...</option>
                                <option value="inappropriate_behavior">Inappropriate Behavior</option>
                                <option value="harassment">Harassment or Bullying</option>
                                <option value="spam">Spam or Scam</option>
                                <option value="fake_profile">Fake Profile</option>
                                <option value="technical_issue">Technical Issue</option>
                                <option value="bug">Bug Report</option>
                                <option value="feature_request">Feature Request</option>
                                <option value="general_feedback">General Feedback</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <?php if ($user): ?>
                        <div class="form-group mb-4">
                            <label for="reported_user_id" class="form-label">User ID (if reporting a specific user)</label>
                            <input type="number" id="reported_user_id" name="reported_user_id" class="form-control" placeholder="Optional - Enter user ID if applicable">
                            <small class="text-secondary">You can find the user ID in their profile URL</small>
                        </div>
                        <?php endif; ?>

                        <div class="form-group mb-4">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="6" placeholder="Please provide as much detail as possible..." required><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                        </div>

                        <?php if (!$user): ?>
                        <div class="alert alert-info mb-4" style="padding: 1rem; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 0.5rem; color: #0c5460;">
                            <strong>Note:</strong> You're not logged in. Your report will be submitted anonymously. For better follow-up, consider <a href="../auth/login.php" style="color: #0c5460; text-decoration: underline;">logging in</a> first.
                        </div>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary">Submit Report</button>
                    </form>
                </div>
            </div>

            <div class="card mt-6">
                <div class="card-body" style="background: var(--background-light); border-left: 4px solid var(--warning-color);">
                    <h3 class="text-lg font-bold mb-2">Emergency Situations</h3>
                    <p class="text-secondary">If you're experiencing an emergency or immediate threat, please contact local authorities immediately. This form is for non-emergency reports only.</p>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
