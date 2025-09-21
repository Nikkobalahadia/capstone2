<?php
require_once '../config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
$db = getDB();

$success_message = '';
$error_message = '';

// Handle form submission
if ($_POST) {
    $category = $_POST['category'];
    $subject = trim($_POST['subject']);
    $description = trim($_POST['description']);
    $reported_user_id = !empty($_POST['reported_user_id']) ? $_POST['reported_user_id'] : null;
    $priority = $_POST['priority'] ?? 'medium';
    
    // Validation
    if (empty($subject) || empty($description) || empty($category)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO user_reports (reporter_id, reported_id, reason, description, status) 
                VALUES (?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$user['id'], $reported_user_id, $category, $subject . ': ' . $description]);
            
            $success_message = 'Your report has been submitted successfully. Our admin team will review it shortly.';
            
            // Clear form data
            $_POST = [];
        } catch (Exception $e) {
            $error_message = 'There was an error submitting your report. Please try again.';
        }
    }
}

// Get users for reporting dropdown (mentors/students only)
$stmt = $db->prepare("
    SELECT id, CONCAT(first_name, ' ', last_name) as name, role 
    FROM users 
    WHERE role IN ('student', 'mentor') AND id != ? AND is_active = 1
    ORDER BY first_name, last_name
");
$stmt->execute([$user['id']]);
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Report - StudyConnect</title>
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
        <div class="max-w-2xl mx-auto">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Submit a Report</h1>
                <p class="text-gray-600">Help us maintain a safe and positive learning environment by reporting issues or providing feedback.</p>
            </div>

            <?php if ($success_message): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800"><?php echo htmlspecialchars($success_message); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-red-800"><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow-md p-6">
                <form method="POST" class="space-y-6">
                    <!-- Report Category -->
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700 mb-2">
                            Report Category <span class="text-red-500">*</span>
                        </label>
                        <select name="category" id="category" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select a category</option>
                            <option value="abuse" <?php echo ($_POST['category'] ?? '') === 'abuse' ? 'selected' : ''; ?>>
                                <i class="fas fa-exclamation-triangle"></i> Inappropriate Behavior/Abuse
                            </option>
                            <option value="technical" <?php echo ($_POST['category'] ?? '') === 'technical' ? 'selected' : ''; ?>>
                                <i class="fas fa-bug"></i> Technical Issue
                            </option>
                            <option value="spam" <?php echo ($_POST['category'] ?? '') === 'spam' ? 'selected' : ''; ?>>
                                <i class="fas fa-ban"></i> Spam/Unwanted Content
                            </option>
                            <option value="suggestion" <?php echo ($_POST['category'] ?? '') === 'suggestion' ? 'selected' : ''; ?>>
                                <i class="fas fa-lightbulb"></i> Suggestion/Feedback
                            </option>
                        </select>
                    </div>

                    <!-- Reported User (Optional) -->
                    <div>
                        <label for="reported_user_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Report About User (Optional)
                        </label>
                        <select name="reported_user_id" id="reported_user_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select a user (if applicable)</option>
                            <?php foreach ($users as $reported_user): ?>
                            <option value="<?php echo $reported_user['id']; ?>" <?php echo ($_POST['reported_user_id'] ?? '') == $reported_user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($reported_user['name']); ?> (<?php echo ucfirst($reported_user['role']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Only select a user if your report is about their behavior or actions.</p>
                    </div>

                    <!-- Subject -->
                    <div>
                        <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">
                            Subject <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="subject" id="subject" required 
                               value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
                               placeholder="Brief summary of your report"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                            Description <span class="text-red-500">*</span>
                        </label>
                        <textarea name="description" id="description" rows="5" required
                                  placeholder="Please provide detailed information about the issue or feedback..."
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <p class="text-sm text-gray-500 mt-1">Be as specific as possible to help us address your concern effectively.</p>
                    </div>

                    <!-- Priority -->
                    <div>
                        <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">
                            Priority Level
                        </label>
                        <select name="priority" id="priority" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="low" <?php echo ($_POST['priority'] ?? 'medium') === 'low' ? 'selected' : ''; ?>>Low - General feedback or minor issues</option>
                            <option value="medium" <?php echo ($_POST['priority'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Medium - Standard issues requiring attention</option>
                            <option value="high" <?php echo ($_POST['priority'] ?? 'medium') === 'high' ? 'selected' : ''; ?>>High - Urgent issues affecting safety or platform use</option>
                        </select>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-between items-center pt-4">
                        <a href="../dashboard.php" class="text-gray-600 hover:text-gray-800">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                        </a>
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            <i class="fas fa-paper-plane mr-2"></i>Submit Report
                        </button>
                    </div>
                </form>
            </div>

            <!-- Help Information -->
            <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-blue-900 mb-3">
                    <i class="fas fa-info-circle mr-2"></i>What happens after I submit a report?
                </h3>
                <ul class="text-blue-800 space-y-2">
                    <li><i class="fas fa-check mr-2"></i>Your report will be reviewed by our admin team within 24-48 hours</li>
                    <li><i class="fas fa-check mr-2"></i>We may contact you for additional information if needed</li>
                    <li><i class="fas fa-check mr-2"></i>You'll be notified once the issue has been resolved</li>
                    <li><i class="fas fa-check mr-2"></i>All reports are handled confidentially and professionally</li>
                </ul>
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
