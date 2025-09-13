<?php
require_once 'config/config.php';

// Check if user is logged in and redirect accordingly
if (is_logged_in()) {
    $user = get_logged_in_user();
    if ($user['role'] === 'admin') {
        redirect('admin/dashboard.php');
    } else {
        redirect('dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudyConnect - Peer-to-Peer Learning Platform</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">StudyConnect</a>
                <ul class="nav-links">
                    <li><a href="#features">Features</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="auth/login.php">Login</a></li>
                    <li><a href="auth/register.php" class="btn btn-primary">Sign Up</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <!-- Hero Section -->
        <section class="hero" style="padding: 4rem 0; text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <div class="container">
                <h1 style="font-size: 3rem; font-weight: 700; margin-bottom: 1rem;">Connect. Learn. Grow Together.</h1>
                <p style="font-size: 1.25rem; margin-bottom: 2rem; opacity: 0.9;">Join thousands of students and mentors in our peer-to-peer learning community</p>
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <a href="auth/register.php?role=student" class="btn btn-primary" style="background: white; color: #667eea;">Join as Student</a>
                    <a href="auth/register.php?role=mentor" class="btn btn-outline" style="border-color: white; color: white;">Become a Mentor</a>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" style="padding: 4rem 0;">
            <div class="container">
                <h2 class="text-center" style="font-size: 2.5rem; margin-bottom: 3rem;">Platform Features</h2>
                <div class="grid grid-cols-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">🎯</div>
                            <h3 class="card-title">Smart Matching</h3>
                            <p>Our algorithm matches you with the perfect study partner or mentor based on your subjects, schedule, and learning style.</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body text-center">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">💬</div>
                            <h3 class="card-title">Real-time Chat</h3>
                            <p>Stay connected with your study partners through our built-in messaging system and coordinate sessions easily.</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body text-center">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">📅</div>
                            <h3 class="card-title">Session Scheduling</h3>
                            <p>Schedule study sessions with integrated calendar sync and automatic reminders for both parties.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- About Section -->
        <section id="about" style="padding: 4rem 0; background-color: #f8fafc;">
            <div class="container">
                <div class="grid grid-cols-2" style="align-items: center; gap: 3rem;">
                    <div>
                        <h2 style="font-size: 2.5rem; margin-bottom: 1.5rem;">Empowering Collaborative Learning</h2>
                        <p style="font-size: 1.125rem; margin-bottom: 1.5rem;">StudyConnect bridges the gap between students seeking help and those ready to share their knowledge. Our platform creates meaningful connections that enhance academic success.</p>
                        <ul style="list-style: none; padding: 0;">
                            <li style="margin-bottom: 0.5rem;">✅ Verified mentors and students</li>
                            <li style="margin-bottom: 0.5rem;">✅ Location-based matching</li>
                            <li style="margin-bottom: 0.5rem;">✅ Session tracking and analytics</li>
                            <li style="margin-bottom: 0.5rem;">✅ Rating and feedback system</li>
                        </ul>
                    </div>
                    <div style="text-align: center;">
                        <div style="width: 300px; height: 200px; background: #e2e8f0; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                            <span style="color: #64748b; font-size: 1.125rem;">Platform Preview</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer style="background: #1e293b; color: white; padding: 2rem 0; text-align: center;">
        <div class="container">
            <p>&copy; 2024 StudyConnect. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
