<?php
require_once '../config/config.php';
$page_title = "About Us";
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
            <h1 class="text-3xl font-bold mb-6">About StudyConnect</h1>

            <div class="card mb-6">
                <div class="card-body">
                    <h2 class="text-2xl font-bold mb-4">Our Mission</h2>
                    <p class="text-secondary mb-4" style="line-height: 1.8;">
                        StudyConnect was created with a simple yet powerful mission: to make quality education accessible to everyone through the power of peer-to-peer learning. We believe that every student has unique knowledge and skills to share, and that learning is most effective when it happens collaboratively.
                    </p>
                    <p class="text-secondary" style="line-height: 1.8;">
                        Our platform connects students, mentors, and peers from around the world, creating a vibrant community where knowledge flows freely and everyone has the opportunity to both teach and learn.
                    </p>
                </div>
            </div>

            <div class="card mb-6">
                <div class="card-body">
                    <h2 class="text-2xl font-bold mb-4">Our Vision</h2>
                    <p class="text-secondary" style="line-height: 1.8;">
                        We envision a world where geographical boundaries, financial constraints, and social barriers no longer limit access to quality education. Through StudyConnect, we're building a global learning community where students can find the perfect study partner, regardless of where they are or what they're studying.
                    </p>
                </div>
            </div>

            <div class="card mb-6">
                <div class="card-body">
                    <h2 class="text-2xl font-bold mb-4">What We Offer</h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 1.5rem;">
                        <div style="padding: 1.5rem; background: var(--background-light); border-radius: 0.5rem;">
                            <h3 class="font-bold mb-2">Smart Matching</h3>
                            <p class="text-secondary">Our intelligent algorithm connects you with the perfect study partners based on subjects, proficiency, and availability.</p>
                        </div>
                        <div style="padding: 1.5rem; background: var(--background-light); border-radius: 0.5rem;">
                            <h3 class="font-bold mb-2">Flexible Roles</h3>
                            <p class="text-secondary">Whether you're a student seeking help, a mentor sharing expertise, or a peer doing both, we've got you covered.</p>
                        </div>
                        <div style="padding: 1.5rem; background: var(--background-light); border-radius: 0.5rem;">
                            <h3 class="font-bold mb-2">Safe Community</h3>
                            <p class="text-secondary">Built-in safety features, user verification, and rating systems ensure a trustworthy learning environment.</p>
                        </div>
                        <div style="padding: 1.5rem; background: var(--background-light); border-radius: 0.5rem;">
                            <h3 class="font-bold mb-2">Free Access</h3>
                            <p class="text-secondary">StudyConnect is completely free because we believe education should be accessible to everyone.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-6">
                <div class="card-body">
                    <h2 class="text-2xl font-bold mb-4">Our Values</h2>
                    <ul style="list-style: none; padding: 0; display: flex; flex-direction: column; gap: 1rem;">
                        <li style="display: flex; gap: 1rem;">
                            <span style="font-size: 1.5rem;">🎯</span>
                            <div>
                                <strong>Excellence:</strong> We strive for excellence in everything we do, from our platform features to our user support.
                            </div>
                        </li>
                        <li style="display: flex; gap: 1rem;">
                            <span style="font-size: 1.5rem;">🤝</span>
                            <div>
                                <strong>Community:</strong> We foster a supportive, inclusive community where everyone feels welcome and valued.
                            </div>
                        </li>
                        <li style="display: flex; gap: 1rem;">
                            <span style="font-size: 1.5rem;">🔒</span>
                            <div>
                                <strong>Trust:</strong> We prioritize user safety, privacy, and security in every aspect of our platform.
                            </div>
                        </li>
                        <li style="display: flex; gap: 1rem;">
                            <span style="font-size: 1.5rem;">💡</span>
                            <div>
                                <strong>Innovation:</strong> We continuously improve and innovate to provide the best learning experience possible.
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-body" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); color: white; text-align: center;">
                    <h2 class="text-2xl font-bold mb-4">Join Our Community</h2>
                    <p class="mb-6" style="opacity: 0.9;">Be part of a global movement that's transforming education through peer-to-peer learning.</p>
                    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                        <a href="../auth/register.php" class="btn" style="background: white; color: var(--primary-color);">Get Started</a>
                        <a href="team.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid white;">Meet the Team</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
