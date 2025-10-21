<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo isset($page_title) ? $page_title : 'Admin Panel'; ?> - Study Mentorship Platform</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Responsive CSS -->
    <link rel="stylesheet" href="../assets/css/responsive.css">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --text-primary: #333;
            --card-background: #fff;
            --background-secondary: #f8f9fa;
            --border-color: #dee2e6;
            --shadow-lg: 0 1rem 3rem rgba(0,0,0,0.175);
            --shadow-md: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }

        * {
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            overflow-x: hidden;
            width: 100%;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        /* Fixed Header */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            height: 60px;
        }

        .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            height: 100%;
        }

        /* Hamburger Menu */
        .hamburger {
            display: none;
            flex-direction: column;
            cursor: pointer;
            gap: 5px;
            background: none;
            border: none;
            padding: 0.5rem;
            z-index: 1001;
        }

        .hamburger span {
            width: 25px;
            height: 3px;
            background-color: var(--text-primary);
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(8px, 8px);
        }

        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }

        /* Header Logo */
        .header-logo {
            font-size: 1.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
            white-space: nowrap;
            flex: 1;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: -250px;
            top: 60px;
            width: 250px;
            height: calc(100vh - 60px);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            transition: left 0.3s ease;
            z-index: 999;
            overflow-y: auto;
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.2);
        }

        .sidebar.active {
            left: 0;
        }

        .sidebar-header {
            text-align: center;
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h4 {
            color: white;
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .sidebar-header small {
            color: rgba(255, 255, 255, 0.7);
            display: block;
            margin-top: 0.25rem;
        }

        .sidebar .nav {
            padding: 1rem 0;
        }

        .sidebar .nav-item {
            margin: 0;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1.5rem;
            border-radius: 0;
            margin: 0;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.15);
            padding-left: 2rem;
        }

        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
        }

        .sidebar hr {
            margin: 1rem 0;
            border-color: rgba(255, 255, 255, 0.2);
        }

        .sidebar-footer {
            padding: 1rem;
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-footer .btn {
            width: 100%;
        }

        /* Main Content */
        .main-content {
            background-color: var(--background-secondary);
            min-height: 100vh;
            margin-left: 0;
            margin-top: 60px;
            padding: 1.5rem 1rem;
        }

        /* Overlay for mobile when sidebar is open */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 60px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* Desktop Styles */
        @media (min-width: 769px) {
            .hamburger {
                display: none !important;
            }

            .sidebar {
                position: static;
                left: auto;
                top: auto;
                width: 250px;
                height: auto;
                min-height: 100vh;
                transition: none;
                box-shadow: 1px 0 3px rgba(0, 0, 0, 0.1);
                margin-top: 60px;
            }

            .sidebar.active {
                left: 0;
            }

            .main-content {
                margin-left: 0;
                margin-top: 60px;
            }

            .container-fluid {
                display: grid;
                grid-template-columns: 250px 1fr;
                margin-top: 60px;
            }

            .sidebar-overlay {
                display: none !important;
            }

            .header {
                position: fixed;
                width: 100%;
            }

            body {
                padding-top: 60px;
            }
        }

        /* Mobile Styles */
        @media (max-width: 768px) {
            .header {
                height: 60px;
            }

            .hamburger {
                display: flex;
            }

            .header-logo {
                font-size: 1.1rem;
            }

            .main-content {
                padding: 1rem 0.75rem;
            }

            body {
                padding-top: 60px;
            }
        }

        /* Small screens */
        @media (max-width: 480px) {
            .header-logo {
                font-size: 1rem;
            }

            .sidebar {
                width: 200px;
                left: -200px;
            }

            .sidebar.active {
                left: 0;
            }

            .main-content {
                padding: 0.75rem 0.5rem;
            }
        }

        /* Landscape mode */
        @media (max-height: 600px) and (orientation: landscape) {
            .header {
                height: 50px;
            }

            .sidebar {
                top: 50px;
                height: calc(100vh - 50px);
            }

            body {
                padding-top: 50px;
            }

            .main-content {
                margin-top: 50px;
            }
        }

        /* Prevent zoom on inputs */
        input, select, textarea, button {
            font-size: 16px !important;
        }

        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }
    </style>
</head>
<body>
    <!-- Fixed Header -->
    <header class="header">
        <div class="header-container">
            <!-- Hamburger Menu (Mobile) -->
            <button class="hamburger" id="sidebarToggle" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <!-- Logo -->
            <h1 class="header-logo">AdminPanel</h1>

            <!-- Header Right Section -->
            <div style="display: flex; align-items: center; gap: 1rem;">
                <!-- Add header items here (notifications, user menu, etc.) -->
            </div>
        </div>
    </header>

    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="container-fluid">
        <div class="row" style="width: 100%; margin: 0;">
            <!-- Sidebar Navigation -->
            <nav class="sidebar" id="adminSidebar">
                <div class="sidebar-header">
                    <h4>Admin Panel</h4>
                    <small>Study Mentorship Platform</small>
                </div>

                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users"></i>
                            <span>User Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="monitoring.php">
                            <i class="fas fa-chart-line"></i>
                            <span>System Monitoring</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="analytics.php">
                            <i class="fas fa-chart-area"></i>
                            <span>Advanced Analytics</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports-inbox.php">
                            <i class="fas fa-inbox"></i>
                            <span>Reports & Feedback</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="session-tracking.php">
                            <i class="fas fa-calendar-check"></i>
                            <span>Session Tracking</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="matches.php">
                            <i class="fas fa-handshake"></i>
                            <span>Matches</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="sessions.php">
                            <i class="fas fa-calendar"></i>
                            <span>Sessions</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="referral-audit.php">
                            <i class="fas fa-gift"></i>
                            <span>Referral Audit</span>
                        </a>
                    </li>
                </ul>

                <hr>

                <div class="sidebar-footer">
                    <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="main-content" style="flex: 1; width: 100%;">
                <div class="p-4">
                    <!-- Page content goes here -->
                </div>
            </main>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/mobile-responsive.js"></script>
</body>
</html>