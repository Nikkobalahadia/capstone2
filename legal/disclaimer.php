<?php
require_once '../config/config.php';
$page_title = "Disclaimer";
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
            <h1 class="text-3xl font-bold mb-6">Disclaimer</h1>
            <div class="card">
                <div class="card-body" style="line-height: 1.8;">
                    <p class="mb-4"><strong>Last Updated:</strong> <?php echo date('F d, Y'); ?></p>

                    <h2 class="text-xl font-bold mt-6 mb-3">1. Platform Purpose</h2>
                    <p class="mb-4">StudyConnect is a peer-to-peer learning platform designed to connect students, mentors, and peers for educational support and collaboration. The platform serves as a tool for facilitating connections and is not responsible for the quality, accuracy, or outcomes of individual study sessions.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">2. No Guarantee of Academic Outcomes</h2>
                    <p class="mb-4">StudyConnect does not guarantee any specific academic results, grades, or learning outcomes. The platform is a tool for connecting learners and does not replace formal education, professional tutoring services, or academic institutions.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">3. User-Generated Content</h2>
                    <p class="mb-4">All content shared by users, including study materials, advice, and information, is provided by individual users and not by StudyConnect. We do not verify, endorse, or guarantee the accuracy, completeness, or quality of user-generated content.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">4. Mentor Verification</h2>
                    <p class="mb-4">While we implement verification processes for mentors, StudyConnect does not conduct comprehensive background checks or verify all qualifications. Users are responsible for exercising their own judgment when selecting study partners and mentors.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">5. User Interactions</h2>
                    <p class="mb-4">StudyConnect is not responsible for the conduct, behavior, or actions of users on or off the platform. Users interact with each other at their own risk. We encourage users to report any inappropriate behavior through our reporting system.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">6. Safety and Security</h2>
                    <p class="mb-4">While we implement security measures to protect user data and privacy, we cannot guarantee absolute security. Users are responsible for maintaining the confidentiality of their account credentials and for all activities under their account.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">7. Third-Party Links</h2>
                    <p class="mb-4">Our platform may contain links to third-party websites or services. StudyConnect is not responsible for the content, privacy policies, or practices of any third-party sites or services.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">8. Platform Availability</h2>
                    <p class="mb-4">We strive to maintain platform availability but do not guarantee uninterrupted access. The platform may be unavailable due to maintenance, technical issues, or other factors beyond our control.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">9. Limitation of Liability</h2>
                    <p class="mb-4">To the fullest extent permitted by law, StudyConnect shall not be liable for any indirect, incidental, special, consequential, or punitive damages resulting from your use of or inability to use the platform.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">10. Changes to Disclaimer</h2>
                    <p class="mb-4">We reserve the right to modify this disclaimer at any time. Changes will be effective immediately upon posting to the platform.</p>

                    <h2 class="text-xl font-bold mt-6 mb-3">11. Contact Information</h2>
                    <p class="mb-4">If you have questions about this disclaimer, please contact us through our <a href="../support/contact.php" style="color: var(--primary-color); text-decoration: underline;">Contact Us</a> page.</p>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
