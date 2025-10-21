<?php
require_once '../config/config.php';
require_once '../includes/matchmaking.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user) {
    redirect('auth/login.php');
}

$db = getDB();

$user_subjects_stmt = $db->prepare("SELECT DISTINCT subject_name FROM user_subjects WHERE user_id = ?");
$user_subjects_stmt->execute([$user['id']]);
$user_subjects = $user_subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

// Mentors and peers must be verified to find matches
if (($user['role'] === 'mentor' || $user['role'] === 'peer') && !$user['is_verified']) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <title>Verification Required - StudyConnect</title>
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
                    <i class="fas fa-book-open"></i> StudyConnect
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
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <title>Find Study Partners - StudyConnect</title>
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
            --shadow: 0 1px 3px rgba(0,0,0,0.05);
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
            color: var(--text-primary);
        }

        /* Header */
        .header {
            background: white;
            border-bottom: 1px solid var(--border-color);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
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

        /* Main */
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
            background: white;
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
            background: white;
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
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .match-subjects-title {
            color: #1e40af;
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
            background: #f9fafb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: left;
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
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 2rem 1.25rem;
            text-align: center;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--border-color);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1.5rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

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
            background: white;
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
        }

        .form-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .form-actions .btn {
            flex: 1;
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
                background: white;
                flex-direction: column;
                gap: 0;
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease;
                box-shadow: 0 10px 40px rgba(0,0,0,0.1);
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

            input, select, textarea, button {
                font-size: 16px;
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

            .btn-sm {
                padding: 0.375rem 0.5rem;
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header Navigation -->
    <header class="header">
        <div class="navbar">
            <!-- Mobile Hamburger -->
            <button class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <!-- Logo -->
            <a href="../dashboard.php" class="logo">
                <i class="fas fa-book-open"></i> StudyConnect
            </a>

            <!-- Desktop Navigation -->
            <ul class="nav-links" id="navLinks">
                <li><a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="index.php"><i class="fas fa-handshake"></i> Matches</a></li>
                <li><a href="../sessions/index.php"><i class="fas fa-calendar"></i> Sessions</a></li>
                <li><a href="../messages/index.php"><i class="fas fa-envelope"></i> Messages</a></li>
                <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
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
                    <div class="loading-spinner"></div>
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
                <div class="match-card" style="background: white; border-radius: 12px; border: 1px solid var(--border-color); padding: 2rem 1.25rem; text-align: center;">
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
                <div class="match-card" style="text-align: center; padding: 2rem 1.25rem;">
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

    <!-- Match Request Modal -->
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
        // Mobile Menu Toggle
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
        });

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
    </script>
</body>
</html>