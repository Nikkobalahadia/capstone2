<?php
require_once '../config/config.php';
$page_title = "Frequently Asked Questions";
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
        <div class="container" style="max-width: 900px;">
            <h1 class="text-3xl font-bold mb-6">Frequently Asked Questions</h1>

            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="text-lg font-bold mb-2">What is StudyConnect?</h3>
                    <p class="text-secondary">StudyConnect is a peer-to-peer learning platform that connects students with mentors and study partners. Our platform helps you find the right people to study with based on subjects, proficiency levels, availability, and location.</p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="text-lg font-bold mb-2">How do I create an account?</h3>
                    <p class="text-secondary">Click the "Sign Up" button in the top right corner and choose your role (Student, Mentor, or Peer). Fill in your information, complete your profile setup, and you're ready to start connecting!</p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="text-lg font-bold mb-2">What's the difference between Student, Mentor, and Peer roles?</h3>
                    <p class="text-secondary"><strong>Students</strong> are looking for help with their studies. <strong>Mentors</strong> are experienced individuals who can teach subjects they're proficient in. <strong>Peers</strong> can both teach subjects they're good at and learn subjects they want to improve in.</p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="text-lg font-bold mb-2">How does the matching system work?</h3>
                    <p class="text-secondary">Our smart matching algorithm considers your subjects, proficiency levels, availability, and location preferences to find the best study partners for you. You can browse matches and send connection requests to users you'd like to study with.</p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="text-lg font-bold mb-2">Is StudyConnect free to use?</h3>
                    <p class="text-secondary">Yes! StudyConnect is completely free for all users. Our mission is to make peer-to-peer learning accessible to everyone.</p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="text-lg font-bold mb-2">How do I schedule a study session?</h3>
                    <p class="text-secondary">Once you're matched with someone, you can schedule a session by going to the Sessions page, selecting your match, and choosing a date, time, and location that works for both of you.</p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="text-lg font-bold mb-2">Can I cancel a scheduled session?</h3>
                    <p class="text-secondary">Yes, you can cancel a session from the Sessions page. Please provide advance notice to your study partner out of courtesy.</p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="text-lg font-bold mb-2">How do I report inappropriate behavior?</h3>
                    <p class="text-secondary">If you encounter any inappropriate behavior, please use our <a href="report.php" style="color: var(--primary-color); text-decoration: underline;">Report a Problem</a> page to notify our team. We take all reports seriously and will investigate promptly.</p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="text-lg font-bold mb-2">How do I update my profile or subjects?</h3>
                    <p class="text-secondary">Go to your Profile page and click on "Edit Profile" or "Manage Subjects" to update your information, subjects, proficiency levels, and availability.</p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="text-lg font-bold mb-2">What if I can't find a match for my subject?</h3>
                    <p class="text-secondary">Try adjusting your search criteria or check back later as new users join the platform daily. You can also reach out to our support team for assistance.</p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="text-lg font-bold mb-2">How do ratings and feedback work?</h3>
                    <p class="text-secondary">After each study session, you can rate your experience and provide feedback. This helps build trust in the community and helps other users find quality study partners.</p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="text-lg font-bold mb-2">Is my personal information safe?</h3>
                    <p class="text-secondary">Yes, we take privacy and security seriously. Please review our <a href="../legal/privacy.php" style="color: var(--primary-color); text-decoration: underline;">Privacy Policy</a> to learn more about how we protect your data.</p>
                </div>
            </div>

            <div class="card">
                <div class="card-body" style="background: var(--background-light); border-left: 4px solid var(--primary-color);">
                    <h3 class="text-lg font-bold mb-2">Still have questions?</h3>
                    <p class="text-secondary mb-3">Can't find the answer you're looking for? Our support team is here to help!</p>
                    <a href="contact.php" class="btn btn-primary">Contact Support</a>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
