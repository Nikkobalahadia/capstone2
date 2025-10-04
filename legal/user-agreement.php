<?php
require_once '../config/config.php';
$page_title = "User Agreement";
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
        <div class="container" style="max-width: 800px;">
            <h1 class="text-3xl font-bold mb-6">User Agreement</h1>
            <div class="card">
                <div class="card-body" style="line-height: 1.8;">
                    <p class="mb-4"><strong>Last Updated:</strong> <?php echo date('F d, Y'); ?></p>

                    <h2 class="text-xl font-bold mt-6 mb-3">1. Agreement to Terms</h2>
                    <p class="mb-4">By creating an account and using StudyConnect, you agree to this User Agreement and our Terms and Conditions. This agreement governs your use of our platform and services.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">2. User Responsibilities</h2>
                    <p class="mb-4">As a user of StudyConnect, you agree to:</p>
                    <ul style="list-style: disc; margin-left: 2rem; margin-bottom: 1rem;">
                        <li>Provide accurate and complete information during registration</li>
                        <li>Maintain the security of your account credentials</li>
                        <li>Respect other users and maintain professional conduct</li>
                        <li>Attend scheduled sessions or provide advance notice of cancellation</li>
                        <li>Provide honest feedback and ratings after sessions</li>
                        <li>Report any inappropriate behavior or content</li>
                    </ul>

                    <h2 class="text-xl font-bold mt-6 mb-3">3. Mentor/Peer Responsibilities</h2>
                    <p class="mb-4">If you are registered as a mentor or peer, you additionally agree to:</p>
                    <ul style="list-style: disc; margin-left: 2rem; margin-bottom: 1rem;">
                        <li>Accurately represent your knowledge and expertise</li>
                        <li>Provide quality educational support to matched students</li>
                        <li>Maintain appropriate boundaries and professional conduct</li>
                        <li>Keep your availability schedule up to date</li>
                        <li>Respond to match requests and messages in a timely manner</li>
                    </ul>

                    <h2 class="text-xl font-bold mt-6 mb-3">4. Student Responsibilities</h2>
                    <p class="mb-4">If you are registered as a student, you agree to:</p>
                    <ul style="list-style: disc; margin-left: 2rem; margin-bottom: 1rem;">
                        <li>Come prepared to study sessions</li>
                        <li>Respect your mentor's or peer's time and expertise</li>
                        <li>Actively participate in learning activities</li>
                        <li>Provide constructive feedback after sessions</li>
                    </ul>

                    <h2 class="text-xl font-bold mt-6 mb-3">5. Prohibited Activities</h2>
                    <p class="mb-4">You agree not to:</p>
                    <ul style="list-style: disc; margin-left: 2rem; margin-bottom: 1rem;">
                        <li>Use the platform for any illegal or unauthorized purpose</li>
                        <li>Harass, abuse, or harm other users</li>
                        <li>Share inappropriate, offensive, or harmful content</li>
                        <li>Attempt to access other users' accounts</li>
                        <li>Spam or send unsolicited messages</li>
                        <li>Misrepresent your identity, qualifications, or intentions</li>
                    </ul>

                    <h2 class="text-xl font-bold mt-6 mb-3">6. Content Ownership</h2>
                    <p class="mb-4">You retain ownership of any content you share on the platform. By sharing content, you grant StudyConnect a license to use, display, and distribute that content as necessary to provide our services.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">7. Account Termination</h2>
                    <p class="mb-4">We reserve the right to suspend or terminate your account if you violate this User Agreement, our Terms and Conditions, or engage in behavior that is harmful to other users or the platform.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">8. Dispute Resolution</h2>
                    <p class="mb-4">If you have a dispute with another user, we encourage you to resolve it directly. If you need assistance, you can contact our support team through the <a href="../support/report.php" style="color: var(--primary-color); text-decoration: underline;">Report a Problem</a> page.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">9. Contact Information</h2>
                    <p class="mb-4">For questions about this User Agreement, please visit our <a href="../support/contact.php" style="color: var(--primary-color); text-decoration: underline;">Contact Us</a> page.</p>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
