<?php
require_once '../config/config.php';
$page_title = "Terms and Conditions";
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
            <h1 class="text-3xl font-bold mb-6">Terms and Conditions</h1>
            <div class="card">
                <div class="card-body" style="line-height: 1.8;">
                    <p class="mb-4"><strong>Last Updated:</strong> <?php echo date('F d, Y'); ?></p>

                    <h2 class="text-xl font-bold mt-6 mb-3">1. Acceptance of Terms</h2>
                    <p class="mb-4">By accessing and using StudyConnect, you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to these Terms and Conditions, please do not use this platform.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">2. Use of Platform</h2>
                    <p class="mb-4">StudyConnect is a peer-to-peer learning platform designed to connect students, mentors, and peers for educational purposes. You agree to use the platform only for lawful purposes and in accordance with these Terms.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">3. User Accounts</h2>
                    <p class="mb-4">You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account. You must notify us immediately of any unauthorized use of your account.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">4. User Conduct</h2>
                    <p class="mb-4">Users must:</p>
                    <ul style="list-style: disc; margin-left: 2rem; margin-bottom: 1rem;">
                        <li>Provide accurate and truthful information</li>
                        <li>Respect other users and maintain professional conduct</li>
                        <li>Not engage in harassment, discrimination, or inappropriate behavior</li>
                        <li>Not share inappropriate or offensive content</li>
                        <li>Not attempt to hack, disrupt, or misuse the platform</li>
                    </ul>

                    <h2 class="text-xl font-bold mt-6 mb-3">5. Intellectual Property</h2>
                    <p class="mb-4">All content on StudyConnect, including text, graphics, logos, and software, is the property of StudyConnect or its content suppliers and is protected by intellectual property laws.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">6. Termination</h2>
                    <p class="mb-4">We reserve the right to terminate or suspend your account at any time, without prior notice, for conduct that we believe violates these Terms or is harmful to other users, us, or third parties.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">7. Changes to Terms</h2>
                    <p class="mb-4">We reserve the right to modify these Terms at any time. We will notify users of any material changes. Your continued use of the platform after such modifications constitutes your acceptance of the updated Terms.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">8. Contact Information</h2>
                    <p class="mb-4">If you have any questions about these Terms and Conditions, please contact us through our <a href="../support/contact.php" style="color: var(--primary-color); text-decoration: underline;">Contact Us</a> page.</p>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
