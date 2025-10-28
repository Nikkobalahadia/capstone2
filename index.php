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
    <title>Study Buddy - Peer-to-Peer Learning Platform</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">Study Buddy</a>
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
        <section class="hero-modern">
            <div class="container">
                <div class="hero-content">
                    <h1 class="hero-title">The Complete Platform for Peer Learning</h1>
                    <p class="hero-subtitle">Connect with thousands of students and mentors. Share knowledge, accelerate learning, and build meaningful academic relationships.</p>
                    <div class="hero-buttons">
                        <a href="auth/register.php?role=student" class="btn btn-primary">Start Learning</a>
                        <a href="auth/register.php?role=mentor" class="btn btn-outline">Become a Mentor</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="stats-section">
            <div class="container">
                <h2 class="section-title">Trusted by Students Worldwide</h2>
                <p class="section-subtitle">Join thousands of learners who have transformed their academic journey through peer-to-peer connections</p>
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-number" data-count="15000">0</span>
                        <span class="stat-label">Active Students</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number" data-count="2500">0</span>
                        <span class="stat-label">Expert Mentors</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number" data-count="50000">0</span>
                        <span class="stat-label">Study Sessions</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number" data-count="98">0</span>
                        <span class="stat-label">Success Rate %</span>
                    </div>
                </div>
            </div>
        </section>

        <section id="features" class="features-modern">
            <div class="container">
                <h2 class="section-title">Everything You Need to Excel</h2>
                <p class="section-subtitle">Powerful tools and features designed to make peer learning seamless and effective</p>
                <div class="features-grid">
                    <div class="feature-card">
                        <span class="feature-icon">🎯</span>
                        <h3 class="feature-title">Smart Matching System</h3>
                        <p class="feature-description">Connect with study partners based on subjects, proficiency levels, availability, and academic goals for effective peer learning.</p>
                    </div>
                    <div class="feature-card">
                        <span class="feature-icon">💬</span>
                        <h3 class="feature-title">Real-time Messaging</h3>
                        <p class="feature-description">Built-in messaging system to communicate with your study partners, coordinate sessions, and share resources seamlessly.</p>
                    </div>
                    <div class="feature-card">
                        <span class="feature-icon">📅</span>
                        <h3 class="feature-title">Session Scheduling</h3>
                        <p class="feature-description">Easy session scheduling with your matched partners. Set dates, times, locations, and session notes to organize your study sessions.</p>
                    </div>
                    <div class="feature-card">
                        <span class="feature-icon">⭐</span>
                        <h3 class="feature-title">Rating & Feedback System</h3>
                        <p class="feature-description">Rate your study sessions and provide feedback to help build a trusted community of learners and mentors.</p>
                    </div>
                    <div class="feature-card">
                        <span class="feature-icon">👥</span>
                        <h3 class="feature-title">Multi-Role Support</h3>
                        <p class="feature-description">Join as a student, mentor, or peer. Peers can both teach subjects they know well and learn new topics from others.</p>
                    </div>
                    <div class="feature-card">
                        <span class="feature-icon">🔒</span>
                        <h3 class="feature-title">Safe & Verified</h3>
                        <p class="feature-description">All mentors are verified, with rating systems and safety features to ensure a secure and trustworthy learning environment.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="about" class="features-modern" style="background: #f8f9fa;">
            <div class="container">
                <h2 class="section-title">About Study Buddy</h2>
                <p class="section-subtitle">Empowering students through collaborative learning and meaningful connections</p>
                <div class="features-grid">
                    <div class="feature-card">
                        <span class="feature-icon">🚀</span>
                        <h3 class="feature-title">Our Mission</h3>
                        <p class="feature-description">To revolutionize education by creating a platform where students can learn from each other, share knowledge, and grow together in a supportive community.</p>
                    </div>
                    <div class="feature-card">
                        <span class="feature-icon">🌟</span>
                        <h3 class="feature-title">Our Vision</h3>
                        <p class="feature-description">A world where quality education is accessible to everyone through peer-to-peer learning, breaking down barriers and fostering academic excellence.</p>
                    </div>
                    <div class="feature-card">
                        <span class="feature-icon">💡</span>
                        <h3 class="feature-title">Why Peer Learning?</h3>
                        <p class="feature-description">Studies show that teaching others is one of the most effective ways to learn. Our platform harnesses this power to benefit both learners and mentors.</p>
                    </div>
                    <div class="feature-card">
                        <span class="feature-icon">🤝</span>
                        <h3 class="feature-title">Community Driven</h3>
                        <p class="feature-description">Built by students, for students. Every feature is designed based on real feedback from our community of learners and educators.</p>
                    </div>
                    <div class="feature-card">
                        <span class="feature-icon">📚</span>
                        <h3 class="feature-title">Academic Excellence</h3>
                        <p class="feature-description">Our platform has helped thousands of students improve their grades, understand difficult concepts, and achieve their academic goals.</p>
                    </div>
                    <div class="feature-card">
                        <span class="feature-icon">🌍</span>
                        <h3 class="feature-title">Global Reach</h3>
                        <p class="feature-description">Connect with students and mentors from around the world, bringing diverse perspectives and learning experiences to your education.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="cta-section">
            <div class="container">
                <div class="cta-content">
                    <h2 class="cta-title">Ready to Transform Your Learning?</h2>
                    <p class="cta-subtitle">Join thousands of students and mentors who are already experiencing the power of peer learning</p>
                    <div class="hero-buttons">
                        <a href="auth/register.php?role=student" class="btn btn-primary">Get Started Free</a>
                        <a href="auth/register.php?role=mentor" class="btn btn-outline">Become a Mentor</a>
                    </div>
                    <p class="text-sm text-secondary" style="margin-top: 1rem; text-align: center;">
                        Students can upgrade to Peer status later from their profile
                    </p>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        // Animated counter for stats
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-number');
            
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-count'));
                const duration = 2000;
                const increment = target / (duration / 16);
                let current = 0;
                
                const updateCounter = () => {
                    if (current < target) {
                        current += increment;
                        counter.textContent = Math.floor(current).toLocaleString();
                        requestAnimationFrame(updateCounter);
                    } else {
                        counter.textContent = target.toLocaleString();
                    }
                };
                
                updateCounter();
            });
        }

        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    if (entry.target.classList.contains('stats-section')) {
                        animateCounters();
                        observer.unobserve(entry.target); // Only animate once
                    }
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe sections for scroll animations
        document.addEventListener('DOMContentLoaded', () => {
            const sections = document.querySelectorAll('.stats-section, .features-modern, .cta-section');
            sections.forEach((section, index) => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(30px)';
                section.style.transition = 'all 0.8s ease-out';
                section.style.transitionDelay = `${index * 0.1}s`;
                observer.observe(section);
            });

            // Add smooth hover effects to feature cards
            const featureCards = document.querySelectorAll('.feature-card');
            featureCards.forEach(card => {
                card.addEventListener('mouseenter', () => {
                    card.style.transform = 'translateY(-8px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'translateY(0) scale(1)';
                });
            });
        });

        // Add loading states to buttons
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (this.href && !this.href.includes('#')) {
                    this.style.opacity = '0.7';
                    this.style.pointerEvents = 'none';
                    setTimeout(() => {
                        this.style.opacity = '1';
                        this.style.pointerEvents = 'auto';
                    }, 1000);
                }
            });
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>