<div class="sidebar position-fixed" style="width: 250px; z-index: 1000;">
    <div class="p-4">
        <h4 class="text-white mb-0">Admin Panel</h4>
        <small class="text-white-50">Study Mentorship Platform</small>
    </div>
    <nav class="nav flex-column px-3">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
            <i class="fas fa-users me-2"></i> User Management
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'verifications.php' ? 'active' : ''; ?>" href="verifications.php">
            <i class="fas fa-user-check me-2"></i> Mentor Verification
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>" href="analytics.php">
            <i class="fas fa-chart-bar me-2"></i> Advanced Analytics
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'matches.php' ? 'active' : ''; ?>" href="matches.php">
            <i class="fas fa-handshake me-2"></i> Matches
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sessions.php' ? 'active' : ''; ?>" href="sessions.php">
            <i class="fas fa-video me-2"></i> Sessions
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'referral-audit.php' ? 'active' : ''; ?>" href="referral-audit.php">
            <i class="fas fa-link me-2"></i> Referral Audit
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : ''; ?>" href="announcements.php">
            <i class="fas fa-bullhorn me-2"></i> Announcements
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
            <i class="fas fa-cog me-2"></i> System Settings
        </a>
    </nav>
    <div class="position-absolute bottom-0 w-100 p-3">
        <a href="../auth/logout.php" class="btn btn-outline-light btn-sm w-100">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
    </div>
</div>
