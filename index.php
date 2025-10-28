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
    <style>
        /* Soft Light Blue Color Palette - Easy on the Eyes */
        :root {
            --primary-blue: #3B82F6;
            --primary-blue-dark: #2563EB;
            --primary-blue-light: #60A5FA;
            --bg-light: #F9FAFB;
            --bg-white: #ffffff;
            --text-primary: #111827;
            --text-secondary: #6B7280;
            --border-light: #E5E7EB;
            --success-green: #d1fae5;
            --success-text: #065f46;
            --warning-yellow: #fef3c7;
            --warning-text: #92400e;
            --danger-red: #dc2626;
            --shadow-sm: 0 1px 3px rgba(59, 130, 246, 0.08);
            --shadow-md: 0 4px 6px rgba(59, 130, 246, 0.12);
            --shadow-lg: 0 10px 20px rgba(59, 130, 246, 0.15);
        }

        body {
            background: var(--bg-light);
            font-family: 'Inter', sans-serif;
        }

        /* Header */
        .header {
            background: var(--bg-white);
            border-bottom: 1px solid var(--border-light);
            box-shadow: var(--shadow-sm);
        }

        .logo {
            color: var(--primary-blue);
            font-weight: 700;
            font-size: 1.25rem;
        }

        .logo::before {
            content: '📖 ';
        }

        .nav-links a:not(.btn) {
            color: var(--text-secondary);
            transition: color 0.3s ease;
            font-weight: 500;
        }

        .nav-links a:not(.btn):hover {
            color: var(--primary-blue);
        }

        /* Hero Section */
        .hero-modern {
            background: var(--primary-blue);
            position: relative;
            overflow: hidden;
            padding: 5rem 0;
        }

        .hero-title {
            color: white;
            font-weight: 700;
            font-size: 3rem;
            margin-bottom: 1.5rem;
        }

        .hero-subtitle {
            color: rgba(255, 255, 255, 0.95);
            font-size: 1.25rem;
            margin-bottom: 2rem;
        }

        /* Stats Section */
        .stats-section {
            background: var(--bg-white);
            padding: 4rem 0;
        }

        .stat-card {
            background: var(--bg-white);
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-5px);
            border-color: var(--primary-blue);
        }

        .stat-number {
            color: var(--primary-blue);
            font-size: 3rem;
            font-weight: 700;
            display: block;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-top: 0.5rem;
            display: block;
        }

        /* Section Titles */
        .section-title {
            color: var(--text-primary);
            font-size: 2.5rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 1rem;
        }

        .section-subtitle {
            color: var(--text-secondary);
            font-size: 1.125rem;
            text-align: center;
            margin-bottom: 3rem;
        }

        /* Features Section */
        #features {
            background: var(--bg-light);
            padding: 4rem 0;
        }

        .feature-card {
            background: var(--bg-white);
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            height: 100%;
        }

        .feature-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-5px);
            border-color: var(--primary-blue);
        }

        .feature-icon {
            font-size: 3rem;
            display: block;
            margin-bottom: 1rem;
        }

        .feature-title {
            color: var(--text-primary);
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        .feature-description {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* About Section */
        #about {
            background: var(--bg-white);
            padding: 4rem 0;
        }

        #about .feature-card {
            background: var(--bg-white);
        }

        /* CTA Section */
        .cta-section {
            background: var(--primary-blue);
            padding: 5rem 0;
            position: relative;
        }

        .cta-title {
            color: white;
            font-size: 2.5rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 1rem;
        }

        .cta-subtitle {
            color: rgba(255, 255, 255, 0.95);
            font-size: 1.125rem;
            text-align: center;
            margin-bottom: 2rem;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary-blue);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            background: var(--primary-blue-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .hero-modern .btn-primary,
        .cta-section .btn-primary {
            background: white;
            color: var(--primary-blue);
        }

        .hero-modern .btn-primary:hover,
        .cta-section .btn-primary:hover {
            background: var(--bg-light);
            color: var(--primary-blue);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary-blue);
            border: 2px solid var(--primary-blue);
        }

        .btn-outline:hover {
            background: var(--primary-blue);
            color: white;
        }

        .hero-modern .btn-outline,
        .cta-section .btn-outline {
            border: 2px solid white;
            color: white;
        }

        .hero-modern .btn-outline:hover,
        .cta-section .btn-outline:hover {
            background: white;
            color: var(--primary-blue);
        }

        /* Grid Layouts */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Navbar */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
            margin: 0;
            padding: 0;
        }

        /* Content Sections */
        .hero-content {
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }

        .cta-content {
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title, .cta-title {
                font-size: 2rem;
            }
            
            .section-title {
                font-size: 1.75rem;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-links {
                gap: 1rem;
            }
        }
    </style>
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

        <section id="about" class="features-modern">
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
                    <p style="margin-top: 1rem; text-align: center; color: rgba(255, 255, 255, 0.9); font-size: 0.875rem;">
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
                        observer.unobserve(entry.target);
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
                    card.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'translateY(0)';
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