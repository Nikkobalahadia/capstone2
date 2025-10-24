<!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

<div class="sidebar position-fixed" style="width: 250px; z-index: 998; top: 60px; left: 0; height: calc(100vh - 60px); overflow-y: auto;">
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
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'commissions.php' ? 'active' : ''; ?>" href="commissions.php">
            <i class="fas fa-money-bill-wave me-2"></i> Commission Payments
        </a>

        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>" href="analytics.php">
            <i class="fas fa-chart-bar me-2"></i> Advanced Analytics
        </a>

        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'referral-audit.php' ? 'active' : ''; ?>" href="referral-audit.php">
            <i class="fas fa-link me-2"></i> Referral Audit
        </a>
        <!-- Added Activity Logs link -->
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'activity-logs.php' ? 'active' : ''; ?>" href="activity-logs.php">
            <i class="fas fa-history me-2"></i> Activity Logs
        </a>
        <!-- Added Financial Overview link -->
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'financial-overview.php' ? 'active' : ''; ?>" href="financial-overview.php">
            <i class="fas fa-chart-pie me-2"></i> Financial Overview
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'matches.php' ? 'active' : ''; ?>" href="matches.php">
            <i class="fas fa-handshake me-2"></i> Matches
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sessions.php' ? 'active' : ''; ?>" href="sessions.php">
            <i class="fas fa-video me-2"></i> Sessions
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : ''; ?>" href="announcements.php">
            <i class="fas fa-bullhorn me-2"></i> Announcements
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
            <i class="fas fa-cog me-2"></i> System Settings
        </a>
    </nav>
</div>

<script>
        // Mobile Menu Toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        const mobileOverlay = document.getElementById('mobileOverlay');
        
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            mobileOverlay.classList.toggle('show');
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-bars');
            icon.classList.toggle('fa-times');
        });
        
        mobileOverlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            mobileOverlay.classList.remove('show');
            mobileMenuToggle.querySelector('i').classList.remove('fa-times');
            mobileMenuToggle.querySelector('i').classList.add('fa-bars');
        });
        
        // Close sidebar when clicking a link on mobile
        if (window.innerWidth <= 768) {
            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                link.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    mobileOverlay.classList.remove('show');
                    mobileMenuToggle.querySelector('i').classList.remove('fa-times');
                    mobileMenuToggle.querySelector('i').classList.add('fa-bars');
                });
            });
        }
    </script>
