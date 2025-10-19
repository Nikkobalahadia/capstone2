<?php
require_once '../config/config.php';
require_once '../config/notification_helper.php';
require_once '../includes/matchmaking.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user) {
    redirect('auth/login.php');
}

// Get unread notifications count
$unread_notifications = get_unread_count($user['id']);

$can_accept_matches = true;
$commission_block_message = '';

if ($user['role'] === 'mentor') {
    require_once '../config/commission_helper.php';
    $db = getDB();
    $overdue_info = check_overdue_commissions($user['id'], $db);
    
    if ($overdue_info['has_overdue']) {
        $can_accept_matches = false;
        $commission_block_message = "You have {$overdue_info['overdue_count']} overdue commission payment(s) totaling ₱" . number_format($overdue_info['total_overdue'], 2) . ". Please pay your commissions before accepting new matches.";
    }
}

$error = '';
$success = '';

// Initialize matchmaking engine
$db = getDB();
$matchmaker = new MatchmakingEngine($db);

// Handle match response
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        if (!$can_accept_matches && $_POST['response'] === 'accepted') {
            $error = $commission_block_message;
        } else {
            $match_id = (int)$_POST['match_id'];
            $response = $_POST['response'];
            
            try {
                $matchmaker->respondToMatch($match_id, $user['id'], $response);
                $success = 'Match ' . ($response === 'accepted' ? 'accepted' : 'declined') . ' successfully!';
            } catch (Exception $e) {
                $error = 'Failed to process response. Please try again.';
            }
        }
    }
}

// Get user's matches with partner ratings
$matches_query = "
    SELECT m.*, 
           CASE 
               WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
               ELSE CONCAT(u1.first_name, ' ', u1.last_name)
           END as partner_name,
           CASE 
               WHEN m.student_id = ? THEN u2.role
               ELSE u1.role
           END as partner_role,
           CASE 
               WHEN m.student_id = ? THEN u2.bio
               ELSE u1.bio
           END as partner_bio,
           CASE 
               WHEN m.student_id = ? THEN u2.location
               ELSE u1.location
           END as partner_location,
           CASE 
               WHEN m.student_id = ? THEN u2.grade_level
               ELSE u1.grade_level
           END as partner_grade_level,
           CASE 
               WHEN m.student_id = ? THEN u2.id
               ELSE u1.id
           END as partner_id,
           CASE 
               WHEN m.student_id = ? THEN u2.profile_picture
               ELSE u1.profile_picture
           END as partner_profile_picture,
           (SELECT AVG(sr.rating) 
            FROM session_ratings sr 
            WHERE sr.rated_id = CASE 
                WHEN m.student_id = ? THEN u2.id 
                ELSE u1.id 
            END) as partner_avg_rating,
           (SELECT COUNT(*) 
            FROM session_ratings sr 
            WHERE sr.rated_id = CASE 
                WHEN m.student_id = ? THEN u2.id 
                ELSE u1.id 
            END) as partner_rating_count
    FROM matches m
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE (m.student_id = ? OR m.mentor_id = ?)
    ORDER BY 
        CASE m.status 
            WHEN 'pending' THEN 1 
            WHEN 'accepted' THEN 2 
            ELSE 3 
        END,
        m.created_at DESC
";

$stmt = $db->prepare($matches_query);
$stmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
$matches = $stmt->fetchAll();

// Separate matches by status
$pending_matches = array_filter($matches, function($match) { return $match['status'] === 'pending'; });
$accepted_matches = array_filter($matches, function($match) { return $match['status'] === 'accepted'; });
$other_matches = array_filter($matches, function($match) { return !in_array($match['status'], ['pending', 'accepted']); });
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Matches - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Notification bell styles */
        .notification-bell {
            position: relative;
            display: inline-block;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: background-color 0.2s;
        }
        .notification-bell:hover {
            background-color: #f3f4f6;
        }
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background-color: #ef4444;
            color: white;
            border-radius: 10px;
            padding: 0.125rem 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            min-width: 18px;
            text-align: center;
        }
        .notification-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 0.5rem;
            width: 380px;
            max-height: 500px;
            overflow-y: auto;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .notification-dropdown.show {
            display: block;
        }
        .notification-header {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .notification-item-dropdown {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .notification-item-dropdown:hover {
            background-color: #f9fafb;
        }
        .notification-item-dropdown.unread {
            background-color: #eff6ff;
        }
        .notification-footer {
            padding: 0.75rem;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        /* Profile dropdown styles */
        .profile-menu {
            position: relative;
            display: inline-block;
        }
        .profile-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            cursor: pointer;
            font-size: 1.25rem;
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
        }
        .profile-icon:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .profile-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 0.5rem;
            width: 220px;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            z-index: 1000;
            overflow: hidden;
        }
        .profile-dropdown.show {
            display: block;
        }
        .profile-dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            text-align: center;
        }
        .profile-dropdown-header .user-name {
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }
        .profile-dropdown-header .user-role {
            font-size: 0.875rem;
            color: #6b7280;
            margin: 0.25rem 0 0 0;
        }
        .profile-dropdown-menu {
            padding: 0.5rem 0;
        }
        .profile-dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #374151;
            text-decoration: none;
            transition: background-color 0.2s;
            cursor: pointer;
            border: none;
            width: 100%;
            text-align: left;
            font-size: 0.95rem;
        }
        .profile-dropdown-item:hover {
            background-color: #f3f4f6;
        }
        .profile-dropdown-item i {
            width: 18px;
            text-align: center;
        }
        .profile-dropdown-divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 0.5rem 0;
        }
        .profile-dropdown-item.logout {
            color: #dc2626;
        }
        .profile-dropdown-item.logout:hover {
            background-color: #fee2e2;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s;
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.2s;
        }

        .modal-close:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .partner-profile-section {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
        }

        .partner-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .partner-avatar-placeholder {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 600;
            color: white;
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .info-section {
            margin-bottom: 1.5rem;
        }

        .info-section h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-section h4 i {
            color: #667eea;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: #6b7280;
        }

        .info-value {
            color: #1f2937;
            font-weight: 500;
        }

        /* Match card action buttons container */
        .match-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-left: 1.5rem;
            min-width: 160px;
            align-self: flex-start;
        }

        .match-actions .btn {
            width: 100%;
            justify-content: center;
            white-space: nowrap;
        }

        .awaiting-status {
            padding: 0.75rem 1rem;
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 6px;
            text-align: center;
        }

        /* Ensure match card layout is proper */
        .match-card-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }

        .match-info {
            flex: 1;
            min-width: 0;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="../dashboard.php" class="logo">StudyConnect</a>
                <ul class="nav-links">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <li><a href="index.php">Matches</a></li>
                    <li><a href="../sessions/index.php">Sessions</a></li>
                    <li><a href="../messages/index.php">Messages</a></li>
                    
                    <!-- Notification bell -->
                    <li style="position: relative;">
                        <div class="notification-bell" onclick="toggleNotifications(event)">
                            <i class="fas fa-bell" style="font-size: 1.25rem;"></i>
                            <?php if ($unread_notifications > 0): ?>
                                <span class="notification-badge" id="notificationBadge"><?php echo $unread_notifications; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="notification-header">
                                <h4 class="font-semibold">Notifications</h4>
                                <?php if ($unread_notifications > 0): ?>
                                    <button onclick="markAllRead(event)" class="btn btn-sm btn-outline">Mark all read</button>
                                <?php endif; ?>
                            </div>
                            <div class="notification-list" id="notificationList">
                                <div class="text-center py-4">
                                    <i class="fas fa-spinner fa-spin"></i> Loading...
                                </div>
                            </div>
                            <div class="notification-footer">
                                <a href="../notifications/index.php" class="text-primary font-medium">View All Notifications</a>
                            </div>
                        </div>
                    </li>
                    
                    <!-- Profile menu with dropdown -->
                    <li style="position: relative;">
                        <div class="profile-menu">
                            <button class="profile-icon" onclick="toggleProfileMenu(event)" title="Profile Menu">
                                <i class="fas fa-user"></i>
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
                                        <a href="profile/commission-payments.php" class="profile-dropdown-item">
                                            <i class="fas fa-money-bill-wave"></i>
                                            <span>Commission Payments</span>
                                        </a>
                                    <?php endif; ?>
                                    <a href="../profile/settings.php" class="profile-dropdown-item">
                                        <i class="fas fa-cog"></i>
                                        <span>Settings</span>
                                    </a>
                                    <div class="profile-dropdown-divider"></div>
                                    <a href="../auth/logout.php" class="profile-dropdown-item logout">
                                        <i class="fas fa-sign-out-alt"></i>
                                        <span>Logout</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <div>
                    <h1>My Matches</h1>
                    <p class="text-secondary">Manage your study partnerships and match requests.</p>
                </div>
                <a href="find.php" class="btn btn-primary">Find New Partners</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Commission warning banner -->
            <?php if (!$can_accept_matches): ?>
                <div class="alert alert-warning mb-4">
                    <div style="display: flex; align-items: start; gap: 1rem;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 1.5rem; margin-top: 0.25rem;"></i>
                        <div style="flex: 1;">
                            <h4 class="font-bold mb-2">Cannot Accept New Matches</h4>
                            <p class="mb-2"><?php echo $commission_block_message; ?></p>
                            <a href="../profile/commission-payments.php" class="btn btn-sm btn-warning mt-2">
                                <i class="fas fa-money-bill-wave"></i> Pay Commissions Now
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Pending Matches -->
            <?php if (!empty($pending_matches)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Pending Requests (<?php echo count($pending_matches); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($pending_matches as $match): ?>
                                <div style="padding: 1.5rem; border: 1px solid var(--border-color); border-radius: 8px; background: #fffbeb;">
                                    <div class="match-card-content">
                                        <div class="match-info">
                                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                                <?php if (!empty($match['partner_profile_picture']) && file_exists('../' . $match['partner_profile_picture'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($match['partner_profile_picture']); ?>" 
                                                         alt="<?php echo htmlspecialchars($match['partner_name']); ?>" 
                                                         style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid #fbbf24;">
                                                <?php else: ?>
                                                    <div style="width: 60px; height: 60px; background: var(--warning-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 1.25rem; border: 3px solid #fbbf24;">
                                                        <?php echo strtoupper(substr($match['partner_name'], 0, 2)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div style="flex: 1;">
                                                    <h4 class="font-semibold" style="margin-bottom: 0.5rem; font-size: 1.125rem;"><?php echo htmlspecialchars($match['partner_name']); ?></h4>
                                                    
                                                    <?php if ($match['partner_avg_rating'] && $match['partner_rating_count'] > 0): ?>
                                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                                            <?php
                                                            $rating = round($match['partner_avg_rating'], 1);
                                                            $fullStars = floor($rating);
                                                            $hasHalfStar = ($rating - $fullStars) >= 0.5;
                                                            ?>
                                                            <div style="display: flex; align-items: center; gap: 0.125rem;">
                                                                <?php for ($i = 0; $i < $fullStars; $i++): ?>
                                                                    <i class="fas fa-star" style="color: #fbbf24; font-size: 0.875rem;"></i>
                                                                <?php endfor; ?>
                                                                <?php if ($hasHalfStar): ?>
                                                                    <i class="fas fa-star-half-alt" style="color: #fbbf24; font-size: 0.875rem;"></i>
                                                                <?php endif; ?>
                                                                <?php for ($i = $fullStars + ($hasHalfStar ? 1 : 0); $i < 5; $i++): ?>
                                                                    <i class="far fa-star" style="color: #d1d5db; font-size: 0.875rem;"></i>
                                                                <?php endfor; ?>
                                                            </div>
                                                            <span style="color: #6b7280; font-size: 0.875rem; font-weight: 600;"><?php echo $rating; ?></span>
                                                            <span style="color: #9ca3af; font-size: 0.875rem;">(<?php echo $match['partner_rating_count']; ?> <?php echo $match['partner_rating_count'] == 1 ? 'review' : 'reviews'; ?>)</span>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                                                        <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.5rem; background: #fef3c7; border-radius: 4px; font-size: 0.75rem; color: #92400e; font-weight: 500;">
                                                            <i class="fas fa-graduation-cap"></i>
                                                            <?php echo ucfirst($match['partner_role']); ?>
                                                        </span>
                                                        <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.5rem; background: #dbeafe; border-radius: 4px; font-size: 0.75rem; color: #1e40af; font-weight: 500;">
                                                            <i class="fas fa-book"></i>
                                                            <?php echo htmlspecialchars($match['subject']); ?>
                                                        </span>
                                                        <?php if ($match['partner_location']): ?>
                                                            <span style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; color: #6b7280;">
                                                                <i class="fas fa-map-marker-alt"></i>
                                                                <?php echo htmlspecialchars($match['partner_location']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($match['partner_bio']): ?>
                                                <div style="padding: 0.75rem; background: #fffbeb; border-left: 3px solid #fbbf24; border-radius: 4px;">
                                                    <p style="color: #78716c; font-size: 0.875rem; line-height: 1.5; margin: 0;">
                                                        <i class="fas fa-quote-left" style="color: #fbbf24; margin-right: 0.25rem; font-size: 0.75rem;"></i>
                                                        <?php echo nl2br(htmlspecialchars(substr($match['partner_bio'], 0, 120))); ?><?php echo strlen($match['partner_bio']) > 120 ? '...' : ''; ?>
                                                        <i class="fas fa-quote-right" style="color: #fbbf24; margin-left: 0.25rem; font-size: 0.75rem;"></i>
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php 
                                        $is_receiver = false;
                                        if (isset($match['initiated_by'])) {
                                            $is_receiver = ($match['initiated_by'] != $user['id']);
                                        } else {
                                            if ($user['role'] === 'mentor' && $match['mentor_id'] == $user['id']) {
                                                $is_receiver = true;
                                            } elseif ($user['role'] === 'student' && $match['student_id'] == $user['id']) {
                                                $is_receiver = false;
                                            } elseif ($user['role'] === 'peer') {
                                                $is_receiver = ($match['mentor_id'] == $user['id']);
                                            }
                                        }
                                        ?>
                                        
                                        <?php if ($is_receiver): ?>
                                            <div class="match-actions">
                                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="openMatchModal(<?php echo htmlspecialchars(json_encode($match)); ?>)">
                                                    <i class="fas fa-eye"></i> View Details
                                                </button>
                                                
                                                <form method="POST" action="">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                                    <input type="hidden" name="response" value="accepted">
                                                    <button type="submit" class="btn btn-success btn-sm w-100" <?php echo !$can_accept_matches ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-check"></i> Accept
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" action="">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                                    <input type="hidden" name="response" value="rejected">
                                                    <button type="submit" class="btn btn-danger btn-sm w-100" onclick="return confirm('Are you sure you want to reject this match?')">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <div class="match-actions">
                                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="openMatchModal(<?php echo htmlspecialchars(json_encode($match)); ?>)">
                                                    <i class="fas fa-eye"></i> View Details
                                                </button>
                                                <div class="awaiting-status">
                                                    <i class="fas fa-clock text-warning"></i>
                                                    <div class="text-warning font-medium mt-1" style="font-size: 0.875rem;">Awaiting Response</div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Details section -->
                                    <div id="details-<?php echo $match['id']; ?>" class="match-details" style="display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="font-semibold mb-2">Partner Information</h6>
                                                <p><strong>Role:</strong> <?php echo ucfirst($match['partner_role']); ?></p>
                                                <p><strong>Location:</strong> <?php echo htmlspecialchars($match['partner_location'] ?: 'Not specified'); ?></p>
                                                <?php if ($match['partner_grade_level']): ?>
                                                    <p><strong>Grade Level:</strong> <?php echo htmlspecialchars($match['partner_grade_level']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="font-semibold mb-2">Match Details</h6>
                                                <p><strong>Subject:</strong> <?php echo htmlspecialchars($match['subject']); ?></p>
                                                <p><strong>Match Score:</strong> <?php echo $match['match_score']; ?>%</p>
                                                <p><strong>Requested:</strong> <?php echo date('M j, Y g:i A', strtotime($match['created_at'])); ?></p>
                                            </div>
                                        </div>
                                        <?php if ($match['partner_bio']): ?>
                                            <div class="mt-3">
                                                <h6 class="font-semibold mb-2">About Partner</h6>
                                                <p class="text-secondary"><?php echo nl2br(htmlspecialchars($match['partner_bio'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Active Matches -->
            <?php if (!empty($accepted_matches)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Active Partnerships (<?php echo count($accepted_matches); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-1" style="gap: 1rem;">
                            <?php foreach ($accepted_matches as $match): ?>
                                <div style="padding: 1.5rem; border: 1px solid var(--border-color); border-radius: 8px; background: #f0fdf4;">
                                    <div class="match-card-content">
                                        <div class="match-info">
                                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                                <?php if (!empty($match['partner_profile_picture']) && file_exists('../' . $match['partner_profile_picture'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($match['partner_profile_picture']); ?>" 
                                                         alt="<?php echo htmlspecialchars($match['partner_name']); ?>" 
                                                         style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                                                <?php else: ?>
                                                    <div style="width: 50px; height: 50px; background: var(--success-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                                        <?php echo strtoupper(substr($match['partner_name'], 0, 2)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <h4 class="font-semibold"><?php echo htmlspecialchars($match['partner_name']); ?></h4>
                                                    <div class="text-sm text-secondary">
                                                        <?php echo ucfirst($match['partner_role']); ?> • <?php echo htmlspecialchars($match['subject']); ?>
                                                        <?php if ($match['partner_location']): ?>
                                                            • <?php echo htmlspecialchars($match['partner_location']); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-sm text-success">
                                                        Active since <?php echo date('M j, Y', strtotime($match['updated_at'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="match-actions">
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="openMatchModal(<?php echo htmlspecialchars(json_encode($match)); ?>)">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                            <a href="../messages/chat.php?match_id=<?php echo $match['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-comment"></i> Message
                                            </a>
                                            <a href="../sessions/schedule.php?match_id=<?php echo $match['id']; ?>" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-calendar"></i> Schedule
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <!-- Details section -->
                                    <div id="details-<?php echo $match['id']; ?>" class="match-details" style="display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="font-semibold mb-2">Partner Information</h6>
                                                <p><strong>Role:</strong> <?php echo ucfirst($match['partner_role']); ?></p>
                                                <p><strong>Location:</strong> <?php echo htmlspecialchars($match['partner_location'] ?: 'Not specified'); ?></p>
                                                <?php if ($match['partner_grade_level']): ?>
                                                    <p><strong>Grade Level:</strong> <?php echo htmlspecialchars($match['partner_grade_level']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="font-semibold mb-2">Partnership Details</h6>
                                                <p><strong>Subject:</strong> <?php echo htmlspecialchars($match['subject']); ?></p>
                                                <p><strong>Match Score:</strong> <?php echo $match['match_score']; ?>%</p>
                                                <p><strong>Started:</strong> <?php echo date('M j, Y g:i A', strtotime($match['updated_at'])); ?></p>
                                            </div>
                                        </div>
                                        <?php if ($match['partner_bio']): ?>
                                            <div class="mt-3">
                                                <h6 class="font-semibold mb-2">About Partner</h6>
                                                <p class="text-secondary"><?php echo nl2br(htmlspecialchars($match['partner_bio'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Other Matches (Rejected/Completed) -->
            <?php if (!empty($other_matches)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Match History</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($other_matches as $match): ?>
                                <div style="padding: 1rem; border: 1px solid var(--border-color); border-radius: 8px; background: #f8fafc;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <div class="font-medium"><?php echo htmlspecialchars($match['partner_name']); ?></div>
                                            <div class="text-sm text-secondary">
                                                <?php echo htmlspecialchars($match['subject']); ?> • 
                                                <?php echo ucfirst($match['status']); ?> on <?php echo date('M j, Y', strtotime($match['updated_at'])); ?>
                                            </div>
                                        </div>
                                        <span class="text-sm text-secondary">Match Score: <?php echo $match['match_score']; ?>%</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($matches)): ?>
                <div class="card">
                    <div class="card-body text-center">
                        <h3>No matches yet</h3>
                        <p class="text-secondary mb-4">Start connecting with study partners to see your matches here.</p>
                        <a href="find.php" class="btn btn-primary">Find Study Partners</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Match Details Modal -->
    <div id="matchModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-circle"></i> Partner Details</h3>
                <button class="modal-close" onclick="closeMatchModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be injected here -->
            </div>
        </div>
    </div>
    
    <script>
        let notificationDropdownOpen = false;
        let profileDropdownOpen = false;

        function openMatchModal(match) {
            const modal = document.getElementById('matchModal');
            const modalBody = document.getElementById('modalBody');
            
            // Determine status badge
            let statusBadge = '';
            if (match.status === 'accepted') {
                statusBadge = '<span style="background: #10b981; color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.875rem; font-weight: 600;">Active</span>';
            } else if (match.status === 'pending') {
                statusBadge = '<span style="background: #f59e0b; color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.875rem; font-weight: 600;">Pending</span>';
            }
            
            // Build profile picture HTML
            let profilePicHtml = '';
            if (match.partner_profile_picture && match.partner_profile_picture !== '') {
                profilePicHtml = `<img src="../${escapeHtml(match.partner_profile_picture)}" alt="${escapeHtml(match.partner_name)}" class="partner-avatar-large">`;
            } else {
                const initials = match.partner_name.substring(0, 2).toUpperCase();
                const bgColor = match.status === 'accepted' ? '#10b981' : '#f59e0b';
                profilePicHtml = `<div class="partner-avatar-placeholder" style="background: ${bgColor};">${initials}</div>`;
            }
            
            modalBody.innerHTML = `
                <div class="partner-profile-section">
                    ${profilePicHtml}
                    <div style="flex: 1;">
                        <h3 style="margin: 0 0 0.5rem 0; font-size: 1.5rem; color: #1f2937;">${escapeHtml(match.partner_name)}</h3>
                        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                            <span style="color: #6b7280; font-weight: 500;">${escapeHtml(match.partner_role.charAt(0).toUpperCase() + match.partner_role.slice(1))}</span>
                            ${statusBadge}
                        </div>
                    </div>
                </div>

                <div class="info-section">
                    <h4><i class="fas fa-info-circle"></i> Basic Information</h4>
                    <div class="info-row">
                        <span class="info-label">Role</span>
                        <span class="info-value">${escapeHtml(match.partner_role.charAt(0).toUpperCase() + match.partner_role.slice(1))}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Location</span>
                        <span class="info-value">${escapeHtml(match.partner_location || 'Not specified')}</span>
                    </div>
                    ${match.partner_grade_level ? `
                    <div class="info-row">
                        <span class="info-label">Grade Level</span>
                        <span class="info-value">${escapeHtml(match.partner_grade_level)}</span>
                    </div>
                    ` : ''}
                </div>

                <div class="info-section">
                    <h4><i class="fas fa-handshake"></i> Match Information</h4>
                    <div class="info-row">
                        <span class="info-label">Subject</span>
                        <span class="info-value">${escapeHtml(match.subject)}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Match Score</span>
                        <span class="info-value">
                            <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 0.25rem 0.5rem; border-radius: 6px; font-weight: 600;">
                                ${match.match_score}%
                            </span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status</span>
                        <span class="info-value">${escapeHtml(match.status.charAt(0).toUpperCase() + match.status.slice(1))}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">${match.status === 'accepted' ? 'Started' : 'Requested'}</span>
                        <span class="info-value">${formatDate(match.status === 'accepted' ? match.updated_at : match.created_at)}</span>
                    </div>
                </div>

                ${match.partner_bio ? `
                <div class="info-section">
                    <h4><i class="fas fa-user"></i> About ${escapeHtml(match.partner_name.split(' ')[0])}</h4>
                    <p style="color: #6b7280; line-height: 1.6; margin: 0;">${escapeHtml(match.partner_bio).replace(/\n/g, '<br>')}</p>
                </div>
                ` : ''}

                ${match.status === 'accepted' ? `
                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                    <a href="../messages/chat.php?match_id=${match.id}" class="btn btn-primary" style="flex: 1; text-align: center;">
                        <i class="fas fa-comment"></i> Send Message
                    </a>
                    <a href="../sessions/schedule.php?match_id=${match.id}" class="btn btn-secondary" style="flex: 1; text-align: center;">
                        <i class="fas fa-calendar"></i> Schedule Session
                    </a>
                </div>
                ` : ''}
            `;
            
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeMatchModal() {
            const modal = document.getElementById('matchModal');
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
            return date.toLocaleDateString('en-US', options);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('matchModal');
            if (event.target === modal) {
                closeMatchModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeMatchModal();
            }
        });

        function toggleDetails(matchId) {
            const detailsElement = document.getElementById('details-' + matchId);
            const button = event.target.closest('button');
            
            if (detailsElement.style.display === 'none' || detailsElement.style.display === '') {
                detailsElement.style.display = 'block';
                button.innerHTML = '<i class="fas fa-eye-slash"></i> Hide Details';
                button.classList.remove('btn-outline-primary');
                button.classList.add('btn-outline-secondary');
            } else {
                detailsElement.style.display = 'none';
                button.innerHTML = '<i class="fas fa-eye"></i> View Details';
                button.classList.remove('btn-outline-secondary');
                button.classList.add('btn-outline-primary');
            }
        }

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
                    
                    if (data.notifications.length === 0) {
                        list.innerHTML = '<div class="text-center py-4 text-secondary">No notifications</div>';
                        return;
                    }
                    
                    list.innerHTML = data.notifications.slice(0, 5).map(notif => `
                        <div class="notification-item-dropdown ${!notif.is_read ? 'unread' : ''}" 
                             onclick="handleNotificationClick(${notif.id}, '${notif.link || ''}')">
                            <div style="display: flex; gap: 0.75rem;">
                                <div style="flex-shrink: 0;">
                                    <i class="fas ${getNotificationIcon(notif.type)} text-${getNotificationColor(notif.type)}-600"></i>
                                </div>
                                <div style="flex: 1;">
                                    <div class="font-medium text-sm mb-1">${escapeHtml(notif.title)}</div>
                                    <div class="text-xs text-secondary">${escapeHtml(notif.message)}</div>
                                    <div class="text-xs text-gray-400 mt-1">${timeAgo(notif.created_at)}</div>
                                </div>
                            </div>
                        </div>
                    `).join('');
                    
                    // Update badge
                    const badge = document.getElementById('notificationBadge');
                    if (data.unread_count > 0) {
                        if (badge) {
                            badge.textContent = data.unread_count;
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                });
        }

        function handleNotificationClick(notificationId, link) {
            fetch('../api/notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'mark_read',
                    notification_id: notificationId
                })
            }).then(() => {
                if (link) {
                    window.location.href = link;
                } else {
                    loadNotifications();
                }
            });
        }

        function markAllRead(event) {
            event.stopPropagation();
            fetch('../api/notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'mark_all_read'})
            }).then(() => {
                loadNotifications();
            });
        }

        function getNotificationIcon(type) {
            const icons = {
                'session_scheduled': 'fa-calendar-plus',
                'session_accepted': 'fa-check-circle',
                'session_rejected': 'fa-times-circle',
                'match_request': 'fa-handshake',
                'match_accepted': 'fa-user-check',
                'announcement': 'fa-bullhorn',
                'commission_due': 'fa-money-bill-wave'
            };
            return icons[type] || 'fa-bell';
        }

        function getNotificationColor(type) {
            const colors = {
                'session_accepted': 'success',
                'session_rejected': 'danger',
                'match_accepted': 'success',
                'announcement': 'primary',
                'commission_due': 'warning'
            };
            return colors[type] || 'secondary';
        }

        function escapeHtml(text) {
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
            return Math.floor(seconds / 86400) + 'd ago';
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (notificationDropdownOpen) {
                const dropdown = document.getElementById('notificationDropdown');
                dropdown.classList.remove('show');
                notificationDropdownOpen = false;
            }
            if (profileDropdownOpen) {
                const dropdown = document.getElementById('profileDropdown');
                dropdown.classList.remove('show');
                profileDropdownOpen = false;
            }
        });

        // Refresh notifications every 30 seconds
        setInterval(() => {
            if (notificationDropdownOpen) {
                loadNotifications();
            } else {
                // Just update the badge count
                fetch('../api/notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        const badge = document.getElementById('notificationBadge');
                        if (data.unread_count > 0) {
                            if (badge) {
                                badge.textContent = data.unread_count;
                            } else {
                                document.querySelector('.notification-bell').innerHTML += 
                                    `<span class="notification-badge" id="notificationBadge">${data.unread_count}</span>`;
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