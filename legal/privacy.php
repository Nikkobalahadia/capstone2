<?php
require_once '../config/config.php';
$page_title = "Privacy Policy";
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
            <h1 class="text-3xl font-bold mb-6">Privacy Policy</h1>
            <div class="card">
                <div class="card-body" style="line-height: 1.8;">
                    <p class="mb-4"><strong>Last Updated:</strong> <?php echo date('F d, Y'); ?></p>

                    <h2 class="text-xl font-bold mt-6 mb-3">1. Information We Collect</h2>
                    <p class="mb-4">We collect information that you provide directly to us, including:</p>
                    <ul style="list-style: disc; margin-left: 2rem; margin-bottom: 1rem;">
                        <li>Personal information (name, email, phone number)</li>
                        <li>Academic information (grade level, subjects, proficiency levels)</li>
                        <li>Profile information (bio, availability, location)</li>
                        <li>Communication data (messages, session notes, feedback)</li>
                        <li>Usage data (login times, features used, interactions)</li>
                    </ul>

                    <h2 class="text-xl font-bold mt-6 mb-3">2. How We Use Your Information</h2>
                    <p class="mb-4">We use the information we collect to:</p>
                    <ul style="list-style: disc; margin-left: 2rem; margin-bottom: 1rem;">
                        <li>Provide, maintain, and improve our services</li>
                        <li>Match you with appropriate study partners and mentors</li>
                        <li>Facilitate communication between users</li>
                        <li>Send you updates, notifications, and support messages</li>
                        <li>Monitor and analyze usage patterns to improve user experience</li>
                        <li>Detect, prevent, and address technical issues and security threats</li>
                    </ul>

                    <h2 class="text-xl font-bold mt-6 mb-3">3. Information Sharing</h2>
                    <p class="mb-4">We do not sell your personal information. We may share your information only in the following circumstances:</p>
                    <ul style="list-style: disc; margin-left: 2rem; margin-bottom: 1rem;">
                        <li>With other users as part of the matching and communication features</li>
                        <li>With your consent or at your direction</li>
                        <li>To comply with legal obligations</li>
                        <li>To protect the rights, property, and safety of StudyConnect and our users</li>
                    </ul>

                    <h2 class="text-xl font-bold mt-6 mb-3">4. Data Security</h2>
                    <p class="mb-4">We implement appropriate technical and organizational measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">5. Cookies and Tracking</h2>
                    <p class="mb-4">We use cookies and similar tracking technologies to track activity on our platform and store certain information. You can instruct your browser to refuse all cookies or to indicate when a cookie is being sent.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">6. Your Rights</h2>
                    <p class="mb-4">You have the right to:</p>
                    <ul style="list-style: disc; margin-left: 2rem; margin-bottom: 1rem;">
                        <li>Access and update your personal information</li>
                        <li>Request deletion of your account and data</li>
                        <li>Opt-out of certain data collection and communications</li>
                        <li>Request a copy of your data</li>
                    </ul>

                    <h2 class="text-xl font-bold mt-6 mb-3">7. Children's Privacy</h2>
                    <p class="mb-4">Our platform is designed for students of all ages. For users under 18, we recommend parental guidance and supervision when using the platform.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">8. Changes to Privacy Policy</h2>
                    <p class="mb-4">We may update this Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last Updated" date.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">9. Contact Us</h2>
                    <p class="mb-4">If you have any questions about this Privacy Policy, please contact us through our <a href="../support/contact.php" style="color: var(--primary-color); text-decoration: underline;">Contact Us</a> page.</p>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
