<?php
require_once '../config/config.php';
$page_title = "Contact Us";

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error_message = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // In a real application, you would send an email or store this in a database
        // For now, we'll just show a success message
        $success_message = 'Thank you for contacting us! We will get back to you within 24-48 hours.';
        
        // Clear form fields
        $name = $email = $subject = $message = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="../index.php" class="logo">StudyConnect</a>
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
            <h1 class="text-3xl font-bold mb-6">Contact Us</h1>

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
                    <p class="text-secondary mb-6">Have a question, suggestion, or need help? Fill out the form below and our support team will get back to you as soon as possible.</p>

                    <form method="POST" action="">
                        <div class="form-group mb-4">
                            <label for="name" class="form-label">Your Name</label>
                            <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                        </div>

                        <div class="form-group mb-4">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                        </div>

                        <div class="form-group mb-4">
                            <label for="subject" class="form-label">Subject</label>
                            <select id="subject" name="subject" class="form-control" required>
                                <option value="">Select a subject...</option>
                                <option value="general">General Inquiry</option>
                                <option value="technical">Technical Support</option>
                                <option value="account">Account Issues</option>
                                <option value="matching">Matching Problems</option>
                                <option value="feedback">Feedback or Suggestions</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="form-group mb-4">
                            <label for="message" class="form-label">Message</label>
                            <textarea id="message" name="message" class="form-control" rows="6" required><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>
                </div>
            </div>

            <div class="card mt-6">
                <div class="card-body" style="background: var(--background-light);">
                    <h3 class="text-lg font-bold mb-3">Other Ways to Reach Us</h3>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div>
                            <strong>Email:</strong> <a href="mailto:support@studyconnect.com" style="color: var(--primary-color);">support@studyconnect.com</a>
                        </div>
                        <div>
                            <strong>Response Time:</strong> We typically respond within 24-48 hours
                        </div>
                        <div>
                            <strong>Need immediate help?</strong> Check our <a href="faq.php" style="color: var(--primary-color); text-decoration: underline;">FAQ page</a> for quick answers
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
