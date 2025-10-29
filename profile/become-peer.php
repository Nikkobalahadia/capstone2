<?php
require_once '../config/config.php';
require_once '../config/notification_helper.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user) {
    redirect('auth/login.php');
}

$unread_notifications = get_unread_count($user['id']);

// Only students can become peers
if ($user['role'] !== 'student') {
    redirect('../dashboard.php');
}

$error = '';
// $success will be set to 'upgrade_complete' on successful form submission
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $referral_code = sanitize_input($_POST['referral_code']);
        
        if (empty($referral_code)) {
            $error = 'Please enter a referral code from a mentor.';
        } else {
            $db = getDB();
            
            error_log("[v0] Peer verification started - User ID: {$user['id']}, Referral Code: {$referral_code}");
            
            // Validate referral code - must be from a verified mentor
            $ref_stmt = $db->prepare("
                SELECT rc.id, rc.created_by, rc.max_uses, rc.current_uses, u.role, u.is_verified
                FROM referral_codes rc
                JOIN users u ON rc.created_by = u.id
                WHERE rc.code = ? AND rc.is_active = 1
            ");
            $ref_stmt->execute([$referral_code]);
            $code_data = $ref_stmt->fetch();
            
            if (!$code_data) {
                $error = 'Invalid or expired referral code.';
                error_log("[v0] Peer verification FAILED (Code not found) - User ID: {$user['id']}, Code: {$referral_code}");
            } elseif ($code_data['role'] !== 'mentor' || !$code_data['is_verified']) {
                $error = 'This referral code is not from a verified mentor.';
                error_log("[v0] Peer verification FAILED (Code not from verified mentor) - User ID: {$user['id']}, Mentor ID: {$code_data['created_by']}");
            } elseif ($code_data['current_uses'] >= $code_data['max_uses']) {
                $error = 'This referral code has reached its maximum usage limit.';
                error_log("[v0] Peer verification FAILED (Code max uses) - User ID: {$user['id']}, Code ID: {$code_data['id']}");
            } else {
                // All checks passed. Upgrade the user.
                try {
                    $db->beginTransaction();
                    
                    // 1. Update user role AND verify the user
                    $update_user_stmt = $db->prepare("UPDATE users SET role = 'peer', is_verified = 1 WHERE id = ?");
                    $update_user_stmt->execute([$user['id']]);
                    
                    // 2. Increment code usage
                    $update_code_stmt = $db->prepare("UPDATE referral_codes SET current_uses = current_uses + 1 WHERE id = ?");
                    $update_code_stmt->execute([$code_data['id']]);
                    
                    // 3. Log the successful upgrade
                    // The table `referral_code_uses` is missing, so we keep this commented out to prevent a DB error.
                    // $log_stmt = $db->prepare("INSERT INTO referral_code_uses (code_id, used_by_id) VALUES (?, ?)");
                    // $log_stmt->execute([$code_data['id'], $user['id']]);

                    // 4. Create a notification for the mentor
                    $mentor_id = $code_data['created_by'];
                    $user_name = $user['first_name'] . ' ' . $user['last_name'];
                    $title = "Referral Code Used!";
                    $message = "{$user_name} used your code to become a Peer.";
                    $link = "profile/index.php?id={$user['id']}";
                    create_notification($mentor_id, 'referral_used', $title, $message, $link);

                    $db->commit();
                    
                    // Log success
                    error_log("[v0] Peer verification SUCCESS - User ID: {$user['id']} upgraded to peer and verified.");
                    
                    // 1. Update session data 
                    $_SESSION['user']['role'] = 'peer'; 
                    $_SESSION['user']['is_verified'] = 1;

                    // 2. Set the success flag for client-side handling
                    // This prevents the server-side redirect error and enables the SweetAlert
                    $success = 'upgrade_complete';
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    
                    $error = 'An error occurred during the upgrade. Please try again.';
                    error_log("[v0] Peer verification FAILED (DB Error) - User ID: {$user['id']}, Error: {$e->getMessage()}");
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="color-scheme" content="light dark">
    <title>Become a Peer - Study Buddy</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary-color: #2563eb;
            --text-primary: #1a1a1a;
            --text-secondary: #666;
            --border-color: #e5e5e5;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.1);
            --bg-input: #ffffff;
            --border-input: #d1d5db;
            --bg-input-focus: #ffffff;
            --border-input-focus: #2563eb;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            overflow-x: hidden;
            width: 100%;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #fafafa;
            color: #1a1a1a;
        }

        .header {
            background: white;
            border-bottom: 1px solid var(--border-color);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 60px;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            height: 100%;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

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

        .logo {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
            white-space: nowrap;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
            margin: 0;
            padding: 0;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: var(--primary-color);
        }

        .notification-bell {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            cursor: pointer;
            border-radius: 8px;
            background: transparent;
            border: none;
            transition: background 0.2s;
            font-size: 1.1rem;
            color: var(--text-secondary);
        }

        .notification-bell:hover {
            background: #f0f0f0;
            color: var(--primary-color);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 0.7rem;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
            border: 2px solid white;
        }

        .notification-dropdown {
            display: none;
            position: absolute;
            right: -10px;
            top: 100%;
            margin-top: 0.75rem;
            width: 380px;
            max-height: 450px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            overflow: hidden;
            flex-direction: column;
        }

        .notification-dropdown.show {
            display: flex;
        }

        .notification-header {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-list {
            max-height: 350px;
            overflow-y: auto;
        }

        .notification-item-dropdown {
            padding: 0.875rem;
            border-bottom: 1px solid #f5f5f5;
            cursor: pointer;
            transition: background 0.15s;
            display: flex;
            gap: 0.75rem;
        }

        .notification-item-dropdown:hover {
            background: #fafafa;
        }

        .notification-item-dropdown.unread {
            background: #fefbeb;
        }

        .notification-footer {
            padding: 0.75rem;
            text-align: center;
            border-top: 1px solid #f0f0f0;
        }

        .profile-menu {
            position: relative;
        }

        .profile-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #1e40af 100%);
            color: white;
            cursor: pointer;
            font-size: 1.1rem;
            border: none;
            transition: transform 0.2s, box-shadow 0.2s;
            overflow: hidden;
        }

        .profile-icon:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .profile-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 0.5rem;
            width: 240px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
        }

        .profile-dropdown.show {
            display: block;
        }

        .profile-dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            text-align: center;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
        }

        .user-role {
            font-size: 0.8rem;
            color: #999;
        }

        .profile-dropdown-menu {
            padding: 0.5rem 0;
        }

        .profile-dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            width: 100%;
            text-align: left;
            font-size: 0.9rem;
            background: transparent;
        }

        .profile-dropdown-item:hover {
            background: #f5f5f5;
            color: var(--primary-color);
        }

        .profile-dropdown-item.logout {
            color: #dc2626;
        }

        .profile-dropdown-item.logout:hover {
            background: #fee2e2;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        main {
            padding: 2rem 0;
            margin-top: 60px;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-primary);
        }
        
        .page-header h1 i {
            color: var(--primary-color);
        }

        .page-subtitle {
            font-size: 0.95rem;
            color: var(--text-secondary);
        }

        .card {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: #fafafa;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .card-body {
            padding: 1.25rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            min-height: 44px;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }
        
        .btn-outline {
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            background: transparent;
        }

        .btn-outline:hover {
            background: #f5f5f5;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-input);
            background: var(--bg-input);
            color: var(--text-primary);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--border-input-focus);
            background: var(--bg-input-focus);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }
        
        .form-input::placeholder {
            color: var(--text-secondary);
            opacity: 0.7;
        }
        
        .form-group small {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
            display: block;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
        }
        .form-actions .btn {
            flex: 1;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
        }
        
        .alert i {
            font-size: 1.1rem;
        }

        .alert-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert-success {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
        
        .info-box {
            margin-top: 2rem;
            padding: 1rem;
            background: #f0f9ff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.85rem;
            color: #0c4a6e;
        }
        
        .info-box i {
            font-size: 1rem;
            color: #0284c7;
        }
        
        .text-secondary {
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .hamburger {
                display: flex;
            }

            .navbar {
                padding: 0.75rem 0.5rem;
            }

            .logo {
                font-size: 1.1rem;
            }

            .nav-links {
                display: none;
                position: fixed;
                top: 60px;
                left: 0;
                right: 0;
                background: white;
                flex-direction: column;
                gap: 0;
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease;
                box-shadow: var(--shadow-lg);
                z-index: 999;
            }

            .nav-links.active {
                max-height: 500px;
                display: flex;
            }

            .nav-links a {
                padding: 1rem;
                border-bottom: 1px solid var(--border-color);
                display: block;
                text-align: left;
            }

            main {
                padding: 1rem 0;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }
            
            .page-header {
                max-width: 100% !important;
                margin-left: 0.75rem !important;
                margin-right: 0.75rem !important;
            }

            .card {
                 max-width: 100% !important;
                 margin-left: 0.75rem !important;
                 margin-right: 0.75rem !important;
            }

            .container {
                padding: 0;
            }

            .notification-dropdown {
                width: 320px;
                right: -60px;
            }

            input, select, textarea, button {
                font-size: 16px !important;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .logo {
                font-size: 1rem;
            }

            .page-header h1 {
                font-size: 1.25rem;
            }
            
            .card-body {
                padding: 1rem;
            }

            .notification-dropdown {
                width: calc(100vw - 20px);
                right: -10px;
            }
        }
        
        [data-theme="dark"] {
            --primary-color: #3b82f6;
            --text-primary: #e4e4e7;
            --text-secondary: #a1a1aa;
            --border-color: #374151;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.3);
            --bg-body: #111827;
            --bg-card: #1f2937;
            --bg-card-header: #374151;
            --bg-hover: #374151;
            --bg-input: #1f2937;
            --border-input: #374151;
            --bg-input-focus: #111827;
            --border-input-focus: #3b82f6;
        }
        
        [data-theme="dark"] body { 
            background: var(--bg-body); 
            color: var(--text-primary); 
        }
        
        [data-theme="dark"] .header,
        [data-theme="dark"] .card,
        [data-theme="dark"] .profile-dropdown,
        [data-theme="dark"] .notification-dropdown,
        [data-theme="dark"] .nav-links {
            background: var(--bg-card);
            border-color: var(--border-color);
        }
        
        [data-theme="dark"] .card-header { 
            background: var(--bg-card-header); 
            border-color: var(--border-color); 
        }
        
        [data-theme="dark"] .profile-dropdown-menu hr,
        [data-theme="dark"] .nav-links a,
        [data-theme="dark"] .notification-footer,
        [data-theme="dark"] .notification-header,
        [data-theme="dark"] .notification-item-dropdown,
        [data-theme="dark"] .profile-dropdown-header {
            border-color: var(--border-color);
        }
        
        [data-theme="dark"] .btn-outline {
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .btn-outline:hover,
        [data-theme="dark"] .notification-bell:hover,
        [data-theme="dark"] .profile-dropdown-item:hover,
        [data-theme="dark"] .notification-item-dropdown:hover {
            background: var(--bg-hover);
        }
        
        [data-theme="dark"] .profile-dropdown-item:hover { 
            color: var(--primary-color); 
        }
        [data-theme="dark"] .profile-dropdown-item.logout:hover { 
            background: #3f1212; 
        }
        
        [data-theme="dark"] .notification-item-dropdown.unread {
            background: var(--bg-card-header);
        }
        
        [data-theme="dark"] .alert-error {
            background: #3f1212;
            border-color: #dc2626;
            color: #fca5a5;
        }
        
        [data-theme="dark"] .alert-success {
            background: #062f1e;
            border-color: #16a34a;
            color: #a7f3d0;
        }
        
        [data-theme="dark"] .info-box {
            background: #1e3a8a;
            color: #dbeafe;
        }
        [data-theme="dark"] .info-box i {
            color: #93c5fd;
        }
        
        [data-theme="dark"] .hamburger span { 
            background-color: var(--text-primary); 
        }
        [data-theme="dark"] .profile-icon:hover { 
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); 
        }
        [data-theme="dark"] .user-role { 
            color: var(--text-secondary); 
        }
        
        [data-theme="dark"] .notification-list div[style*="color: #999"] { 
            color: var(--text-secondary) !important; 
        }
        [data-theme="dark"] .notification-list div[style*="color: #666"] { 
            color: var(--text-secondary) !important; 
        }
    </style>
    
    <script>
        (function() {
            let theme = localStorage.getItem('theme');
            if (!theme) {
                theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.removeAttribute('data-theme');
            }
        })();
    </script>
</head>
<body>
    <header class="header">
        <div class="navbar">
            <button class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <a href="../dashboard.php" class="logo">
                <i class="fas fa-book-open"></i> Study Buddy
            </a>

            <ul class="nav-links" id="navLinks">
                <li><a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="../matches/index.php"><i class="fas fa-handshake"></i> Matches</a></li>
                <li><a href="../sessions/index.php"><i class="fas fa-calendar"></i> Sessions</a></li>
                <li><a href="../messages/index.php"><i class="fas fa-envelope"></i> Messages</a></li>
            </ul>

            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="position: relative;">
                    <button class="notification-bell" onclick="toggleNotifications(event)" title="Notifications">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_notifications > 0): ?>
                            <span class="notification-badge"><?php echo $unread_notifications; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h4><i class="fas fa-bell"></i> Notifications</h4>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <div style="text-align: center; padding: 1.5rem; color: #999;">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                        <div class="notification-footer">
                            <a href="../notifications/index.php" style="font-size: 0.875rem; color: #2563eb; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-arrow-right"></i> View All
                            </a>
                        </div>
                    </div>
                </div>

                <div class="profile-menu">
                    <button class="profile-icon" onclick="toggleProfileMenu(event)">
                        <?php if (!empty($user['profile_picture']) && file_exists('../' . $user['profile_picture'])): ?>
                            <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </button>
                    <div class="profile-dropdown" id="profileDropdown">
                        <div class="profile-dropdown-header">
                            <p class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                            <p class="user-role"><?php echo ucfirst($user['role']); ?></p>
                        </div>
                        <div class="profile-dropdown-menu">
                            <a href="../profile/index.php" class="profile-dropdown-item">
                                <i class="fas fa-user-circle"></i>
                                <span>View Profile</span>
                            </a>
                            <?php if (in_array($user['role'], ['mentor'])): ?>
                                <a href="../profile/commission-payments.php" class="profile-dropdown-item">
                                    <i class="fas fa-wallet"></i>
                                    <span>Commissions</span>
                                </a>
                            <?php endif; ?>
                            <a href="../profile/settings.php" class="profile-dropdown-item">
                                <i class="fas fa-sliders-h"></i>
                                <span>Settings</span>
                            </a>
                            <button class="profile-dropdown-item" id="theme-toggle-btn" style="cursor: pointer;">
                                <i class="fas fa-moon" id="theme-toggle-icon"></i>
                                <span id="theme-toggle-text">Dark Mode</span>
                            </button>
                            <hr style="margin: 0.5rem 0; border: none; border-top: 1px solid #f0f0f0;">
                            <a href="../auth/logout.php" class="profile-dropdown-item logout">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="page-header" style="max-width: 600px; margin: 0 auto 2rem auto;">
                <h1><i class="fas fa-user-friends"></i> Become a Peer</h1>
                <p class="page-subtitle">Upgrade your student account to a peer account to start teaching.</p>
            </div>
            
            <div class="card" style="max-width: 600px; margin: 0 auto;">
                <div class="card-header">
                    <h3 class="card-title">Mentor Referral Code</h3>
                </div>
                <div class="card-body">
                    <p class="text-secondary" style="margin-bottom: 1.5rem;">
                        To become a peer, you must be referred by a verified mentor. Please enter their unique referral code below to upgrade your account.
                    </p>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?php echo $error; ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php 
                    // REMOVED: Old $success block to prevent conflicting message
                    /* if ($success): ?>
                         <div class="alert alert-success">
                             <i class="fas fa-check-circle"></i>
                            <span><?php echo $success; ?></span>
                        </div>
                    <?php endif; */ ?>
                    
                    <form action="become-peer.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="form-group">
                            <label for="referral_code" class="form-label">Mentor Referral Code</label>
                            <input type="text" id="referral_code" name="referral_code" class="form-input" required 
                                   placeholder="Enter the referral code from your mentor"
                                   value="<?php echo isset($_POST['referral_code']) ? htmlspecialchars($_POST['referral_code']) : ''; ?>">
                            <small class="text-secondary">
                                Ask a verified mentor for their referral code. They can generate one from their profile.
                            </small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-arrow-up"></i> Upgrade to Peer
                            </button>
                            <a href="../dashboard.php" class="btn btn-outline" style="text-align: center;">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                    
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <p style="margin: 0;">
                            Don't have a referral code? Connect with mentors in your subjects and ask them for a code!
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        let notificationDropdownOpen = false;
        let profileDropdownOpen = false;

        document.addEventListener("DOMContentLoaded", () => {
            const hamburger = document.querySelector(".hamburger");
            const navLinks = document.querySelector(".nav-links");
            
            if (hamburger) {
                hamburger.addEventListener("click", (e) => {
                    e.stopPropagation();
                    hamburger.classList.toggle("active");
                    navLinks.classList.toggle("active");
                });

                const links = navLinks.querySelectorAll("a");
                links.forEach((link) => {
                    link.addEventListener("click", () => {
                        hamburger.classList.remove("active");
                        navLinks.classList.remove("active");
                    });
                });

                document.addEventListener("click", (event) => {
                    if (hamburger && navLinks && !hamburger.contains(event.target) && !navLinks.contains(event.target)) {
                        hamburger.classList.remove("active");
                        navLinks.classList.remove("active");
                    }
                });
            }
            
            const themeToggleBtn = document.getElementById('theme-toggle-btn');
            const themeToggleIcon = document.getElementById('theme-toggle-icon');
            const themeToggleText = document.getElementById('theme-toggle-text');
            
            function setTheme(theme) {
                if (theme === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    localStorage.setItem('theme', 'dark');
                    if(themeToggleIcon) themeToggleIcon.classList.replace('fa-moon', 'fa-sun');
                    if(themeToggleText) themeToggleText.textContent = 'Light Mode';
                } else {
                    document.documentElement.removeAttribute('data-theme');
                    localStorage.setItem('theme', 'light');
                    if(themeToggleIcon) themeToggleIcon.classList.replace('fa-sun', 'fa-moon');
                    if(themeToggleText) themeToggleText.textContent = 'Dark Mode';
                }
            }

            let currentTheme = document.documentElement.hasAttribute('data-theme') ? 'dark' : 'light';
            setTheme(currentTheme);

            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    let newTheme = document.documentElement.hasAttribute('data-theme') ? 'light' : 'dark';
                    setTheme(newTheme);
                });
            }
            
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
                if (!localStorage.getItem('theme')) {
                    setTheme(e.matches ? 'dark' : 'light');
                }
            });
            
            // --- SweetAlert Success Handler ---
            const successStatus = '<?php echo $success; ?>';
            
            if (successStatus === 'upgrade_complete') {
                // Determine the destination URL based on your preference
                const redirectUrl = '../matches/find.php'; 
                
                // Ensure Swal is loaded (SweetAlert2)
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Success!',
                        html: 'You are now a **Peer**! Get started by finding students to match with.',
                        icon: 'success',
                        confirmButtonText: 'Go to Matches',
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then(() => {
                        // Redirect after the user closes the alert
                        window.location.href = redirectUrl;
                    });
                } else {
                    // Fallback plain alert and redirect if SweetAlert is missing/fails
                    alert('Success! You are now a Peer. Redirecting...');
                    window.location.href = redirectUrl;
                }
            }
        });

        function toggleNotifications(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('notificationDropdown');
            notificationDropdownOpen = !notificationDropdownOpen;
            
            if (notificationDropdownOpen) {
                dropdown.classList.add('show');
                document.getElementById('profileDropdown').classList.remove('show');
                profileDropdownOpen = false;
                loadNotifications();
            } else {
                dropdown.classList.remove('show');
            }
        }

        function toggleProfileMenu(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('profileDropdown');
            profileDropdownOpen = !profileDropdownOpen;
            
            if (profileDropdownOpen) {
                dropdown.classList.add('show');
                document.getElementById('notificationDropdown').classList.remove('show');
                notificationDropdownOpen = false;
            } else {
                dropdown.classList.remove('show');
            }
        }

        function loadNotifications() {
            fetch('../api/notifications.php')
                .then(response => response.json())
                .then(data => {
                    const list = document.getElementById('notificationList');
                    
                    if (!data.notifications || data.notifications.length === 0) {
                        list.innerHTML = '<div style="text-align: center; padding: 1.5rem; color: #999;"><i class="fas fa-bell-slash"></i><p>No notifications</p></div>';
                        return;
                    }
                    
                    list.innerHTML = data.notifications.slice(0, 6).map(notif => `
                        <div class="notification-item-dropdown ${!notif.is_read ? 'unread' : ''}" 
                             onclick="handleNotificationClick(${notif.id}, '${notif.link || ''}')">
                            <i class="fas ${getNotificationIcon(notif.type)}" style="color: ${getNotificationColor(notif.type)};"></i>
                            <div>
                                <div style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.25rem;">${escapeHtml(notif.title)}</div>
                                <div style="font-size: 0.8rem; color: #666;">${escapeHtml(notif.message)}</div>
                                <div style="font-size: 0.75rem; color: #999; margin-top: 0.25rem;">${timeAgo(notif.created_at)}</div>
                            </div>
                        </div>
                    `).join('');
                });
        }

        function handleNotificationClick(notificationId, link) {
            fetch('../api/notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'mark_read', notification_id: notificationId})
            }).then(() => {
                if (link) window.location.href = link;
                else loadNotifications();
            });
        }

        function getNotificationIcon(type) {
            const icons = {
                'session_scheduled': 'fa-calendar-check',
                'session_accepted': 'fa-check-circle',
                'session_rejected': 'fa-times-circle',
                'match_request': 'fa-handshake',
                'match_accepted': 'fa-user-check',
                'announcement': 'fa-megaphone',
                'commission_due': 'fa-file-invoice-dollar',
                'referral_used': 'fa-user-plus'
            };
            return icons[type] || 'fa-bell';
        }

        function getNotificationColor(type) {
            const colors = {
                'session_accepted': '#16a34a',
                'session_rejected': '#dc2626',
                'match_accepted': '#16a34a',
                'announcement': '#2563eb',
                'commission_due': '#d97706',
                'session_scheduled': '#2563eb',
                'match_request': '#2563eb',
                'referral_used': '#16a34a'
            };
            return colors[type] || '#666';
        }

        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function timeAgo(dateString) {
            const date = new Date(dateString);
            const seconds = Math.floor((new Date() - date) / 1000);
            if (seconds < 60) return 'Just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
            if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
            return Math.floor(seconds / 604800) + 'w ago';
        }

        document.addEventListener('click', function() {
            if (notificationDropdownOpen) {
                document.getElementById('notificationDropdown').classList.remove('show');
                notificationDropdownOpen = false;
            }
            if (profileDropdownOpen) {
                document.getElementById('profileDropdown').classList.remove('show');
                profileDropdownOpen = false;
            }
        });

        setInterval(() => {
            if (notificationDropdownOpen) {
                loadNotifications();
            } else {
                fetch('../api/notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        const badge = document.querySelector('.notification-badge');
                        if (data.unread_count > 0) {
                            if (badge) {
                                badge.textContent = data.unread_count;
                            } else {
                                const bell = document.querySelector('.notification-bell');
                                bell.innerHTML += `<span class="notification-badge">${data.unread_count}</span>`;
                            }
                        } else if (badge) {
                            badge.remove();
                        }
                    });
            }
        }, 30000);
    </script>
</body>
</html>