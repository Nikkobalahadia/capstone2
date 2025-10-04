<?php
require_once '../config/config.php';
$page_title = "Blog & Updates";
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
            <h1 class="text-3xl font-bold mb-6">Blog & Updates</h1>
            <p class="text-secondary mb-8">Stay updated with the latest news, features, and study tips from StudyConnect.</p>

            <div class="card mb-6">
                <div class="card-body">
                    <div class="text-sm text-secondary mb-2"><?php echo date('F d, Y'); ?></div>
                    <h2 class="text-2xl font-bold mb-3">Welcome to StudyConnect!</h2>
                    <p class="text-secondary mb-4" style="line-height: 1.8;">
                        We're excited to announce the official launch of StudyConnect, your new platform for peer-to-peer learning! Whether you're a student looking for help, a mentor ready to share your knowledge, or a peer wanting to both teach and learn, StudyConnect is here to connect you with the perfect study partners.
                    </p>
                    <p class="text-secondary" style="line-height: 1.8;">
                        Our smart matching algorithm considers your subjects, proficiency levels, availability, and location to find the best matches for you. Join our growing community today and experience the power of collaborative learning!
                    </p>
                </div>
            </div>

            <div class="card mb-6">
                <div class="card-body">
                    <div class="text-sm text-secondary mb-2"><?php echo date('F d, Y', strtotime('-7 days')); ?></div>
                    <h2 class="text-2xl font-bold mb-3">5 Tips for Effective Peer-to-Peer Learning</h2>
                    <p class="text-secondary mb-4" style="line-height: 1.8;">
                        Peer-to-peer learning can be incredibly effective when done right. Here are our top 5 tips to make the most of your study sessions:
                    </p>
                    <ol style="list-style: decimal; margin-left: 2rem; margin-bottom: 1rem; line-height: 1.8;">
                        <li class="mb-2"><strong>Come Prepared:</strong> Review the material beforehand and prepare specific questions.</li>
                        <li class="mb-2"><strong>Set Clear Goals:</strong> Define what you want to achieve in each session.</li>
                        <li class="mb-2"><strong>Active Participation:</strong> Engage actively in discussions and ask questions.</li>
                        <li class="mb-2"><strong>Teach to Learn:</strong> Explaining concepts to others reinforces your own understanding.</li>
                        <li class="mb-2"><strong>Provide Feedback:</strong> Give constructive feedback to help improve future sessions.</li>
                    </ol>
                </div>
            </div>

            <div class="card mb-6">
                <div class="card-body">
                    <div class="text-sm text-secondary mb-2"><?php echo date('F d, Y', strtotime('-14 days')); ?></div>
                    <h2 class="text-2xl font-bold mb-3">How to Create an Effective Study Schedule</h2>
                    <p class="text-secondary mb-4" style="line-height: 1.8;">
                        One of the keys to successful learning is having a well-organized study schedule. Here's how to create one that works for you:
                    </p>
                    <ul style="list-style: disc; margin-left: 2rem; margin-bottom: 1rem; line-height: 1.8;">
                        <li class="mb-2">Identify your peak productivity hours and schedule difficult subjects during those times</li>
                        <li class="mb-2">Break study sessions into manageable chunks (45-50 minutes) with short breaks</li>
                        <li class="mb-2">Use StudyConnect's availability feature to coordinate with study partners</li>
                        <li class="mb-2">Include time for review and practice, not just new material</li>
                        <li class="mb-2">Be flexible and adjust your schedule as needed</li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-body" style="background: var(--background-light); text-align: center;">
                    <h3 class="text-lg font-bold mb-2">More Content Coming Soon!</h3>
                    <p class="text-secondary">We're working on more blog posts, study tips, and platform updates. Check back regularly for new content!</p>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
