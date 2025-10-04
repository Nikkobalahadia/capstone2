<?php
require_once '../config/config.php';
$page_title = "Our Team";
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
        <div class="container" style="max-width: 1000px;">
            <h1 class="text-3xl font-bold mb-6 text-center">Meet the Team</h1>
            <p class="text-secondary text-center mb-8" style="max-width: 600px; margin-left: auto; margin-right: auto;">
                StudyConnect was developed as a capstone project by a dedicated team of students passionate about making education more accessible through technology.
            </p>

            <div class="card mb-6">
                <div class="card-body">
                    <h2 class="text-2xl font-bold mb-6 text-center">Development Team</h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                        <div style="text-align: center;">
                            <div style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; font-size: 3rem; color: white;">
                                👨‍💻
                            </div>
                            <h3 class="font-bold mb-1">Team Member 1</h3>
                            <p class="text-secondary text-sm mb-2">Lead Developer</p>
                            <p class="text-secondary text-sm">Full-stack development, database design, and system architecture.</p>
                        </div>

                        <div style="text-align: center;">
                            <div style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, var(--secondary-color), var(--primary-color)); margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; font-size: 3rem; color: white;">
                                👩‍💻
                            </div>
                            <h3 class="font-bold mb-1">Team Member 2</h3>
                            <p class="text-secondary text-sm mb-2">Frontend Developer</p>
                            <p class="text-secondary text-sm">UI/UX design, responsive layouts, and user experience optimization.</p>
                        </div>

                        <div style="text-align: center;">
                            <div style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; font-size: 3rem; color: white;">
                                👨‍💻
                            </div>
                            <h3 class="font-bold mb-1">Team Member 3</h3>
                            <p class="text-secondary text-sm mb-2">Backend Developer</p>
                            <p class="text-secondary text-sm">API development, matching algorithm, and server-side logic.</p>
                        </div>

                        <div style="text-align: center;">
                            <div style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, var(--secondary-color), var(--primary-color)); margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; font-size: 3rem; color: white;">
                                👩‍💻
                            </div>
                            <h3 class="font-bold mb-1">Team Member 4</h3>
                            <p class="text-secondary text-sm mb-2">QA & Documentation</p>
                            <p class="text-secondary text-sm">Testing, quality assurance, and technical documentation.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-6">
                <div class="card-body">
                    <h2 class="text-2xl font-bold mb-4">Project Information</h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                        <div>
                            <h4 class="font-bold mb-2">Institution</h4>
                            <p class="text-secondary">[Your University/College Name]</p>
                        </div>
                        <div>
                            <h4 class="font-bold mb-2">Program</h4>
                            <p class="text-secondary">[Your Program Name]</p>
                        </div>
                        <div>
                            <h4 class="font-bold mb-2">Academic Year</h4>
                            <p class="text-secondary"><?php echo date('Y'); ?></p>
                        </div>
                        <div>
                            <h4 class="font-bold mb-2">Project Type</h4>
                            <p class="text-secondary">Capstone Project</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h2 class="text-2xl font-bold mb-4">Acknowledgments</h2>
                    <p class="text-secondary mb-4" style="line-height: 1.8;">
                        We would like to express our gratitude to our advisors, professors, and peers who provided valuable feedback and support throughout the development of StudyConnect. This project represents countless hours of research, development, testing, and refinement.
                    </p>
                    <p class="text-secondary" style="line-height: 1.8;">
                        Special thanks to all the beta testers who helped us identify issues and improve the platform before launch.
                    </p>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
