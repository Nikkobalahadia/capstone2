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

$unread_notifications = get_unread_count($user['id']);

$db = getDB();

$user_subjects_stmt = $db->prepare("SELECT DISTINCT subject_name FROM user_subjects WHERE user_id = ?");
$user_subjects_stmt->execute([$user['id']]);
$user_subjects = $user_subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

// Mentors and peers must be verified to find matches
if (($user['role'] === 'mentor' || $user['role'] === 'peer' || $user['role'] === 'student') && !$user['is_verified']) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <title>Verification Required - StudyBuddy</title>
        <link rel="stylesheet" href="../assets/css/style.css">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="../assets/css/responsive.css">
        <style>
            body {
                font-family: 'Inter', sans-serif;
                background: #fafafa;
                margin: 0;
                padding: 0;
            }
            .verification-required {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #fafafa;
                padding: 2rem 1rem;
            }
            
            .verification-card {
                background: white;
                border-radius: 12px;
                padding: 3rem 2rem;
                max-width: 500px;
                border: 1px solid #f0f0f0;
            }
            
            .verification-icon {
                width: 80px;
                height: 80px;
                background: #fef3c7;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1.5rem;
                font-size: 2.5rem;
            }
            
            .verification-title {
                color: #1a1a1a;
                font-size: 1.5rem;
                font-weight: 700;
                margin-bottom: 1rem;
                text-align: center;
            }
            
            .verification-text {
                color: #666;
                line-height: 1.6;
                margin-bottom: 2rem;
                text-align: center;
                font-size: 0.95rem;
            }
            
            .verification-text ul {
                text-align: left;
                color: #666;
                margin-bottom: 2rem;
                line-height: 1.8;
                padding: 0 1rem;
            }
            
            .verification-actions {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .btn-verify {
                background: #2563eb;
                color: white;
                padding: 0.75rem 1.5rem;
                border-radius: 8px;
                font-weight: 600;
                text-decoration: none;
                display: inline-block;
                text-align: center;
                border: none;
                cursor: pointer;
                transition: all 0.2s;
                min-height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .btn-verify:hover {
                background: #1d4ed8;
            }
            
            .btn-back {
                color: #666;
                text-decoration: none;
                font-weight: 500;
                text-align: center;
                padding: 0.75rem;
                transition: color 0.2s;
            }
            
            .btn-back:hover {
                color: #2563eb;
            }
        </style>
    </head>
    <body>
        <header class="header" style="background: white; border-bottom: 1px solid #e5e5e5; position: fixed; top: 0; left: 0; right: 0; z-index: 100; height: 60px;">
            <div style="max-width: 1400px; margin: 0 auto; padding: 0 1rem; height: 100%; display: flex; align-items: center;">
                <a href="../dashboard.php" style="font-size: 1.25rem; font-weight: 700; color: #2563eb; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-book-open"></i> Study Buddy
                </a>
            </div>
        </header>
        <div class="verification-required">
            <div class="verification-card">
                <div class="verification-icon"><i class="fas fa-lock"></i></div>
                <h1 class="verification-title">Verification Required</h1>
                <p class="verification-text">
                    To ensure the quality and safety of our community, <?php echo $user['role'] === 'mentor' ? 'mentors' : 'peers'; ?> 
                    must be verified before finding matches.
                </p>
                <p class="verification-text">Once verified, you'll be able to:</p>
                <ul style="margin: 0 0 1.5rem 0; padding: 0 1rem;">
                    <li>Find and match with students</li>
                    <li>Offer help in your areas of expertise</li>
                    <li>Build your reputation</li>
                    <li>Schedule study sessions</li>
                </ul>
                <div class="verification-actions">
                    <a href="../dashboard.php" class="btn-verify">
                        <i class="fas fa-arrow-left"></i> Go to Dashboard
                    </a>
                    <a href="../profile/index.php" class="btn-back">View My Profile</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if (!isset($_SESSION['passed_users'])) {
    $_SESSION['passed_users'] = [];
}

if (isset($_GET['reset']) || (isset($_GET['mode']) && $_GET['mode'] !== ($_SESSION['last_mode'] ?? ''))) {
    $_SESSION['passed_users'] = [];
    $_SESSION['last_mode'] = $_GET['mode'] ?? 'seeking';
}

$error = '';
$success = '';
$current_match = null;
$loading = false;

$help_mode = isset($_GET['mode']) && in_array($_GET['mode'], ['seeking', 'offering']) ? $_GET['mode'] : 'seeking';

if ($help_mode === 'offering' && $user['role'] !== 'peer') {
    redirect('find.php?mode=seeking');
}

$matchmaker = new MatchmakingEngine($db);

// Handle match request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_match'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $mentor_id = (int)$_POST['mentor_id'];
        $subject = sanitize_input($_POST['subject']);
        $message = sanitize_input($_POST['message']);
        
        try {
            require_once '../includes/hybrid_matchmaking.php';
            $hybridMatcher = new HybridMatchmakingEngine($db);
            
            $result = $hybridMatcher->createHybridMatch($user['id'], $mentor_id, $subject, $message);
            
            $_SESSION['passed_users'] = [];
            
            if ($result['delivery_method'] === 'realtime') {
                $success = "Great choice! You've been matched with " . htmlspecialchars($_POST['mentor_name']) . ". Redirecting you to chat…";
                echo "<script>setTimeout(() => { window.location.href = '../messages/index.php'; }, 2000);</script>";
            } else {
                $success = "Great choice! You've been matched with " . htmlspecialchars($_POST['mentor_name']) . ". They will see your request when they log in.";
            }
        } catch (Exception $e) {
            error_log("Match request error: " . $e->getMessage());
            $error = 'Failed to send match request. Please try again.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pass_match'])) {
    $passed_user_id = (int)$_POST['passed_user_id'];
    
    if (!in_array($passed_user_id, $_SESSION['passed_users'])) {
        $_SESSION['passed_users'][] = $passed_user_id;
    }
    
    try {
        $rejection_stmt = $db->prepare("
            INSERT INTO user_rejections (rejector_id, rejected_id) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE rejected_at = CURRENT_TIMESTAMP
        ");
        $rejection_stmt->execute([$user['id'], $passed_user_id]);
    } catch (Exception $e) {
        error_log("Failed to store rejection: " . $e->getMessage());
    }
    
    $_POST['find_match'] = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['find_match'])) {
    $loading = true;
    
    if (empty($user_subjects)) {
        $error = 'Please add subjects to your profile first to find matches.';
    } else {
        $all_potential_matches = [];
        $subject_match_counts = [];
        
        $rejected_users_stmt = $db->prepare("
            SELECT rejected_id 
            FROM user_rejections 
            WHERE rejector_id = ? 
            AND expires_at > NOW()
        ");
        $rejected_users_stmt->execute([$user['id']]);
        $rejected_users = $rejected_users_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $all_rejected_users = array_unique(array_merge($_SESSION['passed_users'], $rejected_users));
        
        if ($help_mode === 'offering' && $user['role'] === 'peer') {
            $teaching_subjects_stmt = $db->prepare("
                SELECT DISTINCT subject_name 
                FROM user_subjects 
                WHERE user_id = ? 
                AND proficiency_level IN ('advanced', 'expert')
            ");
            $teaching_subjects_stmt->execute([$user['id']]);
            $teaching_subjects = $teaching_subjects_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($teaching_subjects)) {
                $error = 'You need advanced or expert level subjects to offer help. Please update your profile.';
            } else {
                foreach ($teaching_subjects as $subject) {
                    $subject_matches = $matchmaker->findStudentsNeedingHelp($user['id'], $subject, 50);
                    
                    foreach ($subject_matches as $match) {
                        $match_id = $match['id'];
                        
                        if (in_array($match_id, $all_rejected_users)) {
                            continue;
                        }
                        
                        if (!isset($subject_match_counts[$match_id])) {
                            $subject_match_counts[$match_id] = [
                                'count' => 0,
                                'subjects' => [],
                                'match_data' => $match
                            ];
                        }
                        
                        $subject_match_counts[$match_id]['count']++;
                        $subject_match_counts[$match_id]['subjects'][] = $subject;
                    }
                }
            }
        } else {
            foreach ($user_subjects as $subject) {
                $subject_matches = $matchmaker->findMatches($user['id'], $subject, 50);
                
                foreach ($subject_matches as $match) {
                    $match_id = $match['id'];
                    
                    if (in_array($match_id, $all_rejected_users)) {
                        continue;
                    }
                    
                    if (!isset($subject_match_counts[$match_id])) {
                        $subject_match_counts[$match_id] = [
                            'count' => 0,
                            'subjects' => [],
                            'match_data' => $match
                        ];
                    }
                    
                    $subject_match_counts[$match_id]['count']++;
                    $subject_match_counts[$match_id]['subjects'][] = $subject;
                }
            }
        }
        
        uasort($subject_match_counts, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });
        
        $subject_matches = [];
        foreach ($subject_match_counts as $match_id => $data) {
            $match = $data['match_data'];
            $match['matched_subjects'] = $data['subjects'];
            $match['matched_subject_count'] = $data['count'];
            $subject_matches[] = $match;
        }
        
        if ($user['latitude'] && $user['longitude']) {
            $location_sorted = $matchmaker->findNearestMatches($user['id'], 50);
            
            $location_sorted = array_filter($location_sorted, function($match) use ($all_rejected_users) {
                return !in_array($match['id'], $all_rejected_users);
            });
            
            $subject_location_matches = [];
            foreach ($subject_matches as $subject_match) {
                foreach ($location_sorted as $location_match) {
                    if ($location_match['id'] === $subject_match['id']) {
                        $subject_match['distance'] = $location_match['distance'] ?? null;
                        $subject_location_matches[] = $subject_match;
                        break;
                    }
                }
            }
            
            if (!empty($subject_location_matches)) {
                usort($subject_location_matches, function($a, $b) {
                    if ($a['matched_subject_count'] !== $b['matched_subject_count']) {
                        return $b['matched_subject_count'] <=> $a['matched_subject_count'];
                    }
                    if (isset($a['distance']) && isset($b['distance'])) {
                        return $a['distance'] <=> $b['distance'];
                    }
                    return 0;
                });
                $filtered_matches = $subject_location_matches;
            } else {
                $filtered_matches = $subject_matches;
            }
        } else {
            usort($subject_matches, function($a, $b) use ($user) {
                if ($a['matched_subject_count'] !== $b['matched_subject_count']) {
                    return $b['matched_subject_count'] <=> $a['matched_subject_count'];
                }
                
                $similarity_a = 0;
                $similarity_b = 0;
                if ($user['location'] && $a['location']) {
                    similar_text(strtolower($user['location']), strtolower($a['location']), $similarity_a);
                }
                if ($user['location'] && $b['location']) {
                    similar_text(strtolower($user['location']), strtolower($b['location']), $similarity_b);
                }
                return $similarity_b <=> $similarity_a;
            });
            $filtered_matches = $subject_matches;
        }
        
        $final_matches = [];
        foreach ($filtered_matches as $match) {
            $availability_score = $matchmaker->calculateTimeAvailabilityScore($user['id'], $match['id']);
            if ($availability_score > 20) {
                $match['availability_score'] = $availability_score;
                $final_matches[] = $match;
            }
        }
        
        if (empty($final_matches) && !empty($filtered_matches)) {
            $final_matches = array_slice($filtered_matches, 0, 1);
        }
        
        if (!empty($final_matches)) {
            $current_match = $final_matches[0];
            $current_match['selected_subjects'] = $current_match['matched_subjects'];
            $loading = false; // Match found, stop loading
        } else {
            // No match found, but we want the 2-second timeout to complete before showing the "No Match" state
            // Keep $loading = true for the first 2 seconds, then let the JS auto-submit the form
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
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <title>Find Study Partners - StudyBuddy</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --text-primary: #1a1a1a;
            --text-secondary: #666;
            --border-color: #e5e5e5;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.1);
            --bg-color: #fafafa;
            --card-bg: white;
        }

        [data-theme="dark"] {
            --primary-color: #3b82f6;
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --border-color: #374151;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.3);
            --bg-color: #111827;
            --card-bg: #1f2937;
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
            background: var(--bg-color);
            color: var(--text-primary);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* ===== HEADER & NAVIGATION ===== */
        .header {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 60px;
            transition: background-color 0.3s ease, border-color 0.3s ease;
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

        /* Logo */
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

        /* Navigation Links */
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

        /* Notification Bell */
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
            background: var(--border-color);
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
            border: 2px solid var(--card-bg);
        }

        .notification-dropdown {
            display: none;
            position: absolute;
            right: -10px;
            top: 100%;
            margin-top: 0.75rem;
            width: 380px;
            max-height: 450px;
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            overflow: hidden;
            flex-direction: column;
            border: 1px solid var(--border-color);
        }

        .notification-dropdown.show {
            display: flex;
        }

        .notification-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
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
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background 0.15s;
            display: flex;
            gap: 0.75rem;
        }

        .notification-item-dropdown:hover {
            background: var(--border-color);
        }

        .notification-item-dropdown.unread {
            background: rgba(37, 99, 235, 0.1);
        }

        .notification-footer {
            padding: 0.75rem;
            text-align: center;
            border-top: 1px solid var(--border-color);
        }

        .notification-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
        }

        /* Profile Menu */
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
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            border: 1px solid var(--border-color);
        }

        .profile-dropdown.show {
            display: block;
        }

        .profile-dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
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
            color: var(--text-secondary);
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
            background: var(--border-color);
            color: var(--primary-color);
        }

        .profile-dropdown-item.logout {
            color: #dc2626;
        }

        .profile-dropdown-item.logout:hover {
            background: rgba(220, 38, 38, 0.1);
        }

        /* Main Content */
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        main {
            padding: 2rem 0;
            margin-top: 60px;
        }

        /* Page Header */
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
        }

        .page-header h1 i {
            color: var(--primary-color);
        }

        .page-subtitle {
            font-size: 0.95rem;
            color: var(--text-secondary);
        }

        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }

        .alert-error {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }

        .alert-success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        /* Mode Toggle */
        .mode-toggle {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }

        .mode-btn {
            flex: 1;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .mode-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* Match Card */
        .match-card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 2rem 1.25rem;
            text-align: center;
        }

        .match-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), #1e40af);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 2rem;
            margin: 0 auto 1rem;
            border: 3px solid var(--border-color);
        }

        .match-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--border-color);
        }

        .match-name {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .match-meta {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .match-score {
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .match-subjects {
            background: rgba(37, 99, 235, 0.1);
            border: 1px solid rgba(37, 99, 235, 0.2);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .match-subjects-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .subject-tag {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            margin: 0.25rem;
        }

        .match-bio {
            background: rgba(0, 0, 0, 0.02);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: left;
        }

        [data-theme="dark"] .match-bio {
            background: rgba(255, 255, 255, 0.05);
        }

        .match-bio-title {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .match-bio-text {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .match-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .match-actions .btn {
            flex: 1;
        }

        /* Loading */
        .loading-state {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 2rem 1.25rem;
            text-align: center;
        }

        /* NEW PULSING ANIMATION STYLES */
        .pulsing-icon-container {
            margin: 0 auto 1.5rem;
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px; /* Base size for icon */
            height: 80px;
        }

        .pulsing-icon {
            font-size: 3rem; /* Icon size */
            color: var(--primary-color);
            position: absolute;
            animation: pulse-search 2s infinite ease-in-out; /* The animation */
        }

        @keyframes pulse-search {
            0% {
                transform: scale(0.95);
                opacity: 0.7;
            }
            50% {
                transform: scale(1.1);
                opacity: 1;
            }
            100% {
                transform: scale(0.95);
                opacity: 0.7;
            }
        }
        /* END NEW PULSING ANIMATION STYLES */

        .loading-text {
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .loading-subtext {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Buttons */
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

        .btn-success {
            background: #16a34a;
            color: white;
        }

        .btn-outline {
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            background: transparent;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 2rem 1.5rem;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }

        .modal-header {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-primary);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 16px;
            background: var(--card-bg);
            color: var(--text-primary);
        }

        .form-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .form-actions .btn {
            flex: 1;
        }

        .hide-on-small {
            display: inline;
        }

        /* Mobile */
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
                background: var(--card-bg);
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

            .match-card {
                padding: 1.5rem 1rem;
            }

            .mode-toggle {
                flex-direction: column;
            }

            .mode-btn {
                width: 100%;
            }

            .match-actions {
                flex-direction: column;
            }

            .match-actions .btn {
                width: 100%;
            }

            .modal-content {
                padding: 1.5rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
            }

            .notification-dropdown {
                width: calc(100vw - 2rem);
                right: -0.5rem;
            }

            input, select, textarea, button {
                font-size: 16px !important;
            }

            .hide-on-small {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .logo {
                font-size: 1rem;
            }

            .page-header h1 {
                font-size: 1.25rem;
            }

            .page-header {
                margin-bottom: 1.5rem;
            }

            .match-avatar {
                width: 70px;
                height: 70px;
                font-size: 1.75rem;
            }

            .match-name {
                font-size: 1.1rem;
            }

            .match-card {
                padding: 1rem;
            }

            .container {
                padding: 0 0.75rem;
            }

            .modal-content {
                padding: 1.25rem;
            }
        }
    </style>
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
                <li><a href="index.php"><i class="fas fa-handshake"></i> Matches</a></li>
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
                            <h4 style="margin: 0; font-size: 1rem;"><i class="fas fa-bell"></i> Notifications</h4>
                        </div>
                        <div class="notification-list" id="notificationList">
                             <div style="text-align: center; padding: 1.5rem; color: #999;">
                                 <i class="fas fa-spinner fa-spin"></i>
                             </div>
                        </div>
                        <div class="notification-footer">
                            <a href="../notifications/index.php"><i class="fas fa-arrow-right"></i> View All</a>
                        </div>
                    </div>
                </div>

                <div class="profile-menu">
                    <button class="profile-icon" onclick="toggleProfileMenu(event)">
                        <?php if (!empty($user['profile_picture']) && file_exists('../' . $user['profile_picture'])): ?>
                            <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </button>
                    <div class="profile-dropdown" id="profileDropdown">
                        <div class="profile-dropdown-header">
                            <p class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                            <p class="user-role"><?php echo ucfirst($user['role']); ?></p>
                        </div>
                        <div class="profile-dropdown-menu">
                            <a href="../profile/index.php" class="profile-dropdown-item">
                                <i class="fas fa-user-circle"></i> <span>View Profile</span>
                            </a>
                            <?php if (in_array($user['role'], ['mentor', 'peer'])): ?>
                            <a href="../profile/commission-payments.php" class="profile-dropdown-item">
                                <i class="fas fa-wallet"></i> <span>Commissions</span>
                            </a>
                            <?php endif; ?>
                            <a href="../profile/settings.php" class="profile-dropdown-item">
                                <i class="fas fa-sliders-h"></i> <span>Settings</span>
                            </a>
                            <button type="button" class="profile-dropdown-item" onclick="toggleTheme()">
                                <i class="fas fa-sun" id="theme-icon"></i> <span id="theme-text">Toggle Theme</span>
                            </button>
                            <hr style="margin: 0.5rem 0; border: none; border-top: 1px solid var(--border-color);">
                            <a href="../auth/logout.php" class="profile-dropdown-item logout">
                                <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="page-header">
                <h1><i class="fas fa-search"></i> Find Study Partners</h1>
                <p class="page-subtitle">
                    <?php if ($help_mode === 'offering'): ?>
                        Share your expertise and help students learn
                    <?php else: ?>
                        Connect with mentors and peers to accelerate your learning
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo $error; ?></div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo $success; ?></div>
                </div>
            <?php endif; ?>

            <?php if ($user['role'] === 'peer'): ?>
                <div class="mode-toggle">
                    <a href="?mode=seeking" class="mode-btn <?php echo $help_mode === 'seeking' ? 'active' : ''; ?>">
                        <i class="fas fa-hand-paper"></i> Looking for Help
                    </a>
                    <a href="?mode=offering" class="mode-btn <?php echo $help_mode === 'offering' ? 'active' : ''; ?>">
                        <i class="fas fa-user-tie"></i> Offering Help
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($loading && !$current_match && !$error): ?>
                <div class="loading-state">
                    <div class="pulsing-icon-container">
                        <i class="fas fa-graduation-cap pulsing-icon"></i>
                    </div>
                    <div class="loading-text">Finding your perfect match...</div>
                    <div class="loading-subtext">
                        <?php if ($help_mode === 'offering'): ?>
                            <?php
                            $teaching_subjects_stmt = $db->prepare("
                                SELECT DISTINCT subject_name 
                                FROM user_subjects 
                                WHERE user_id = ? 
                                AND proficiency_level IN ('advanced', 'expert')
                            ");
                            $teaching_subjects_stmt->execute([$user['id']]);
                            $teaching_subjects = $teaching_subjects_stmt->fetchAll(PDO::FETCH_COLUMN);
                            ?>
                            Looking for students who need help with: <strong><?php echo implode(', ', array_slice($teaching_subjects, 0, 3)); ?><?php echo count($teaching_subjects) > 3 ? '...' : ''; ?></strong>
                        <?php else: ?>
                            Looking for mentors/peers who can teach: <strong><?php echo implode(', ', array_slice($user_subjects, 0, 3)); ?><?php echo count($user_subjects) > 3 ? '...' : ''; ?></strong>
                        <?php endif; ?>
                    </div>
                    <form id="autoContinueForm" method="POST" style="display:none;">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="find_match" value="1">
                    </form>
                </div>

            <?php elseif ($current_match): ?>
                <div class="match-card">
                    <div class="match-avatar">
                        <?php if (!empty($current_match['profile_picture']) && file_exists('../' . $current_match['profile_picture'])): ?>
                            <img src="../<?php echo htmlspecialchars($current_match['profile_picture']); ?>" alt="<?php echo htmlspecialchars($current_match['first_name']); ?>">
                        <?php else: ?>
                            <?php echo strtoupper(substr($current_match['first_name'], 0, 1) . substr($current_match['last_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>

                    <div class="match-name"><?php echo htmlspecialchars($current_match['first_name'] . ' ' . $current_match['last_name']); ?></div>
                    <div class="match-meta">
                        <?php echo $current_match['role'] === 'peer' ? '🤝 Peer' : ucfirst($current_match['role']); ?>
                        <?php if ($current_match['grade_level']): ?>
                            • <?php echo htmlspecialchars($current_match['grade_level']); ?>
                        <?php endif; ?>
                        <?php if ($current_match['location']): ?>
                            • <?php echo htmlspecialchars($current_match['location']); ?>
                        <?php endif; ?>
                    </div>

                    <div class="match-score">
                        <?php echo $current_match['match_score']; ?>% Match
                        <?php if ($current_match['avg_rating']): ?>
                            • ⭐ <?php echo number_format($current_match['avg_rating'], 1); ?>
                        <?php endif; ?>
                    </div>

                    <div class="match-subjects">
                        <div class="match-subjects-title">Common Subjects:</div>
                        <?php foreach ($current_match['selected_subjects'] as $subject): ?>
                            <span class="subject-tag"><?php echo htmlspecialchars($subject); ?></span>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($current_match['bio']): ?>
                        <div class="match-bio">
                            <div class="match-bio-title">About <?php echo htmlspecialchars(explode(' ', $current_match['first_name'])[0]); ?>:</div>
                            <div class="match-bio-text"><?php echo nl2br(htmlspecialchars(substr($current_match['bio'], 0, 200))); ?><?php if (strlen($current_match['bio']) > 200): ?>...<?php endif; ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($current_match['hourly_rate'] && $current_match['hourly_rate'] > 0): ?>
                        <div style="background: #dcfce7; border-radius: 8px; padding: 0.75rem; margin-bottom: 1.5rem; font-weight: 600; color: #166534;">
                            💰 ₱<?php echo number_format($current_match['hourly_rate'], 2); ?>/hour
                        </div>
                    <?php elseif ($current_match['role'] === 'mentor' || $current_match['role'] === 'peer'): ?>
                        <div style="background: #eff6ff; border-radius: 8px; padding: 0.75rem; margin-bottom: 1.5rem; font-weight: 600; color: #1e40af;">
                            🎁 Free Tutoring
                        </div>
                    <?php endif; ?>

                    <div class="match-actions">
                        <form method="POST" style="flex: 1;">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="passed_user_id" value="<?php echo $current_match['id']; ?>">
                            <button type="submit" name="pass_match" class="btn btn-outline" style="width: 100%;">
                                <i class="fas fa-arrow-right"></i> Skip
                            </button>
                        </form>
                        <button onclick="acceptMatch()" class="btn btn-success" style="flex: 1;">
                            <i class="fas fa-check"></i> Connect
                        </button>
                    </div>
                </div>

            <?php elseif (!empty($user_subjects)): ?>
                <div class="match-card">
                    <h2 style="color: var(--text-primary); margin-bottom: 1rem; font-size: 1.25rem; font-weight: 600;">Ready to connect?</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem; line-height: 1.6;">
                        We'll find the best study partner for you based on your interests, location, and availability.
                    </p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <button type="submit" name="find_match" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-search"></i> Find Match
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <div class="match-card">
                    <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;">📚</div>
                    <div style="color: var(--text-primary); font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem;">Add subjects to your profile</div>
                    <div style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.9rem;">
                        You need to add subjects to your profile before finding matches.
                    </div>
                    <a href="../profile/subjects.php" class="btn btn-primary" style="display: inline-flex;">
                        <i class="fas fa-plus"></i> Add Subjects
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div id="matchModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-envelope"></i> Send Match Request
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="mentor_id" id="modal_mentor_id">
                <input type="hidden" name="mentor_name" id="modal_mentor_name">

                <div class="form-group">
                    <label>Connecting with: <strong id="modal_display_name" style="color: var(--primary-color);"></strong></label>
                </div>

                <div class="form-group">
                    <label for="modal_subject">Subject</label>
                    <input type="text" id="modal_subject" name="subject" readonly>
                </div>

                <div class="form-group">
                    <label for="modal_message">Message (Optional)</label>
                    <textarea id="modal_message" name="message" rows="3" placeholder="Tell them a bit about yourself..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" name="request_match" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Request
                    </button>
                    <button type="button" onclick="closeMatchModal()" class="btn btn-outline">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // --- Theme Toggle JS ---
        const body = document.body;
        const themeIcon = document.getElementById('theme-icon');
        const themeText = document.getElementById('theme-text');
        
        function setTheme(theme) {
            body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            updateThemeToggleUI(theme);
        }
        
        function updateThemeToggleUI(theme) {
            if (theme === 'dark') {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
                themeText.textContent = 'Light Mode';
            } else {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
                themeText.textContent = 'Dark Mode';
            }
        }
        
        function toggleTheme() {
            const currentTheme = body.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            setTheme(newTheme);
        }
        
        // Load theme on initial page load
        (function loadTheme() {
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            let initialTheme = 'light';
            
            if (savedTheme) {
                initialTheme = savedTheme;
            } else if (prefersDark) {
                initialTheme = 'dark';
            }
            
            setTheme(initialTheme);
        })();
        // --- End Theme Toggle JS ---

        let profileDropdownOpen = false;
        let notificationDropdownOpen = false;

        function toggleProfileMenu(event) {
            event.stopPropagation();
            if (notificationDropdownOpen) {
                document.getElementById('notificationDropdown').classList.remove('show');
                notificationDropdownOpen = false;
            }
            const dropdown = document.getElementById('profileDropdown');
            profileDropdownOpen = !profileDropdownOpen;
            dropdown.classList.toggle('show');
        }
        
        function toggleNotifications(event) {
            event.stopPropagation();
            if (profileDropdownOpen) {
                document.getElementById('profileDropdown').classList.remove('show');
                profileDropdownOpen = false;
            }
            const dropdown = document.getElementById('notificationDropdown');
            notificationDropdownOpen = !notificationDropdownOpen;
            dropdown.classList.toggle('show');
            if (notificationDropdownOpen) {
                loadNotifications();
            }
        }
        
        async function loadNotifications() {
            const list = document.getElementById('notificationList');
            list.innerHTML = '<div style="text-align: center; padding: 1.5rem; color: #999;"><i class="fas fa-spinner fa-spin"></i></div>';
            
            try {
                const response = await fetch('../api/notifications.php?mark_read=true');
                const data = await response.json();
                
                if (data.notifications && data.notifications.length > 0) {
                    list.innerHTML = '';
                    data.notifications.forEach(n => {
                        list.innerHTML += `
                            <div class="notification-item-dropdown ${n.is_read == 0 ? 'unread' : ''}" onclick="window.location.href='${n.link}'">
                                <div style="flex-shrink: 0;"><i class="fas fa-info-circle" style="color: var(--primary-color);"></i></div>
                                <div>
                                    <p style="margin: 0; font-size: 0.9rem; color: var(--text-primary);">${n.message}</p>
                                    <small style="color: var(--text-secondary);">${formatMessageTime(n.created_at)}</small>
                                </div>
                            </div>
                        `;
                    });
                    document.querySelector('.notification-badge')?.remove();
                } else {
                    list.innerHTML = '<div style="text-align: center; padding: 1.5rem; color: #999;">No notifications</div>';
                }
            } catch (e) {
                list.innerHTML = '<div style="text-align: center; padding: 1.5rem; color: #999;">Failed to load</div>';
            }
        }

        function formatMessageTime(dateTime) {
            const dt = new Date(dateTime + ' UTC');
            return dt.toLocaleString(undefined, {
                year: 'numeric', month: 'short', day: 'numeric',
                hour: 'numeric', minute: '2-digit', hour12: true
            });
        }

        // Mobile Menu Toggle
        document.getElementById('hamburger').addEventListener('click', function() {
            this.classList.toggle('active');
            document.getElementById('navLinks').classList.toggle('active');
        });
        
        document.addEventListener('click', function(event) {
            const hamburger = document.getElementById('hamburger');
            const navLinks = document.getElementById('navLinks');
            
            if (hamburger && navLinks && !hamburger.contains(event.target) && !navLinks.contains(event.target)) {
                hamburger.classList.remove('active');
                navLinks.classList.remove('active');
            }
            
            if (notificationDropdownOpen) {
                const notifDropdown = document.getElementById('notificationDropdown');
                const notifBell = document.querySelector('.notification-bell');
                if (!notifDropdown.contains(event.target) && !notifBell.contains(event.target)) {
                    notificationDropdownOpen = false;
                    notifDropdown.classList.remove('show');
                }
            }
            
            if (profileDropdownOpen) {
                const profDropdown = document.getElementById('profileDropdown');
                const profIcon = document.querySelector('.profile-icon');
                if (!profDropdown.contains(event.target) && !profIcon.contains(event.target)) {
                    profileDropdownOpen = false;
                    profDropdown.classList.remove('show');
                }
            }
        });

        // Periodic notification check
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
                                const newBadge = document.createElement('span');
                                newBadge.className = 'notification-badge';
                                newBadge.textContent = data.unread_count;
                                bell.appendChild(newBadge);
                            }
                        } else if (badge) {
                            badge.remove();
                        }
                    });
            }
        }, 30000);

        function acceptMatch() {
            <?php if ($current_match): ?>
                document.getElementById('modal_mentor_id').value = '<?php echo $current_match['id']; ?>';
                document.getElementById('modal_mentor_name').value = '<?php echo htmlspecialchars($current_match['first_name'] . ' ' . $current_match['last_name']); ?>';
                document.getElementById('modal_display_name').textContent = '<?php echo htmlspecialchars($current_match['first_name'] . ' ' . $current_match['last_name']); ?>';
                document.getElementById('modal_subject').value = '<?php echo htmlspecialchars($current_match['selected_subjects'][0]); ?>';
                document.getElementById('matchModal').classList.add('show');
            <?php endif; ?>
        }

        function closeMatchModal() {
            document.getElementById('matchModal').classList.remove('show');
        }

        document.getElementById('matchModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeMatchModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMatchModal();
            }
        });

        // --- NEW: Auto-submit form after 2 seconds if loading is active ---
        (function autoContinueSearch() {
            const loadingState = document.querySelector('.loading-state');
            const autoForm = document.getElementById('autoContinueForm');
            
            // Only run the timeout if the loading-state is present
            if (loadingState && autoForm) {
                setTimeout(() => {
                    // Check if a match card is visible (meaning a match was found server-side)
                    if (!document.querySelector('.match-card')) {
                         autoForm.submit();
                    }
                }, 2000); // 2000 milliseconds = 2 seconds
            }
        })();
        // --- END NEW SCRIPT ---
    </script>
</body>
</html>