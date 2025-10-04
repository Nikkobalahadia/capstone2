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
    // Redirect mentors and students away from offering mode
    redirect('find.php?mode=seeking');
}

// Initialize matchmaking engine
$matchmaker = new MatchmakingEngine($db);

// Handle match request (Accept)
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
                // Redirect to messages after 2 seconds
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
    // Trigger the matching algorithm again to find next match
    $_POST['find_match'] = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['find_match'])) {
    $loading = true;
    
    
    if (empty($user_subjects)) {
        $error = 'Please add subjects to your profile first to find matches.';
    } else {
        $all_potential_matches = [];
        $subject_match_counts = []; // Track how many subjects each user matches on
        
        if ($help_mode === 'offering' && $user['role'] === 'peer') {
            // OFFERING HELP MODE: Match user's TEACHING subjects with others' LEARNING subjects
            // Get user's teaching subjects (subjects they can teach)
            $teaching_subjects_stmt = $db->prepare("
                SELECT DISTINCT subject_name 
                FROM user_subjects 
                WHERE user_id = ? 
                AND proficiency_level IN ('advanced', 'expert')
            ");
            $teaching_subjects_stmt->execute([$user['id']]);
            $teaching_subjects = $teaching_subjects_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($teaching_subjects)) {
                $error = 'You need to have advanced or expert level subjects to offer help. Please update your profile.';
            } else {
                // Find users who want to learn these subjects
                foreach ($teaching_subjects as $subject) {
                    $subject_matches = $matchmaker->findStudentsNeedingHelp($user['id'], $subject, 50);
                    
                    // Track which subjects each match has in common
                    foreach ($subject_matches as $match) {
                        $match_id = $match['id'];
                        
                        // Skip passed users
                        if (in_array($match_id, $_SESSION['passed_users'])) {
                            continue;
                        }
                        
                        // Initialize or increment subject match count
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
            // LOOKING FOR HELP MODE: Match user's LEARNING subjects with others' TEACHING subjects
            // Use all user subjects to find mentors/peers who can teach them
            foreach ($user_subjects as $subject) {
                $subject_matches = $matchmaker->findMatches($user['id'], $subject, 50);
                
                // Track which subjects each match has in common
                foreach ($subject_matches as $match) {
                    $match_id = $match['id'];
                    
                    // Skip passed users
                    if (in_array($match_id, $_SESSION['passed_users'])) {
                        continue;
                    }
                    
                    // Initialize or increment subject match count
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
        
        // Sort by number of matching subjects (descending)
        uasort($subject_match_counts, function($a, $b) {
            return $b['count'] <=> $a['count']; // Descending order
        });
        
        // Convert back to match array with subject info
        $subject_matches = [];
        foreach ($subject_match_counts as $match_id => $data) {
            $match = $data['match_data'];
            $match['matched_subjects'] = $data['subjects'];
            $match['matched_subject_count'] = $data['count'];
            $subject_matches[] = $match;
        }
        
        // Tier 2: Sort by nearest location (secondary priority)
        if ($user['latitude'] && $user['longitude']) {
            // Use GPS-based distance sorting
            $location_sorted = $matchmaker->findNearestMatches($user['id'], 50);
            
            $location_sorted = array_filter($location_sorted, function($match) {
                return !in_array($match['id'], $_SESSION['passed_users']);
            });
            
            // Merge location data with subject matches while preserving subject priority
            $subject_location_matches = [];
            foreach ($subject_matches as $subject_match) {
                foreach ($location_sorted as $location_match) {
                    if ($location_match['id'] === $subject_match['id']) {
                        // Preserve subject match data and add distance
                        $subject_match['distance'] = $location_match['distance'] ?? null;
                        $subject_location_matches[] = $subject_match;
                        break;
                    }
                }
            }
            
            // If we have location data, sort by subject count first, then distance
            if (!empty($subject_location_matches)) {
                usort($subject_location_matches, function($a, $b) {
                    // First priority: number of matching subjects
                    if ($a['matched_subject_count'] !== $b['matched_subject_count']) {
                        return $b['matched_subject_count'] <=> $a['matched_subject_count'];
                    }
                    // Second priority: distance (if available)
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
            // Fallback to text-based location sorting
            usort($subject_matches, function($a, $b) use ($user) {
                // First priority: number of matching subjects
                if ($a['matched_subject_count'] !== $b['matched_subject_count']) {
                    return $b['matched_subject_count'] <=> $a['matched_subject_count'];
                }
                
                // Second priority: location similarity
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
        
        // Tier 3: Filter by availability (final filter)
        $final_matches = [];
        foreach ($filtered_matches as $match) {
            $availability_score = $matchmaker->calculateTimeAvailabilityScore($user['id'], $match['id']);
            if ($availability_score > 20) { // Only include matches with some availability overlap
                $match['availability_score'] = $availability_score;
                $final_matches[] = $match;
            }
        }
        
        // If no availability matches, relax the availability requirement
        if (empty($final_matches) && !empty($filtered_matches)) {
            $final_matches = array_slice($filtered_matches, 0, 1); // Take the best match
        }
        
        if (!empty($final_matches)) {
            $current_match = $final_matches[0]; // Get the best match
            $current_match['selected_subjects'] = $current_match['matched_subjects'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <title>Find Study Partners - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Updated styles for auto-matching interface */
        .auto-match-container {
            max-width: 500px;
            margin: 0 auto;
            text-align: center;
        }
        
        .match-prompt {
            background: white;
            border-radius: 20px;
            padding: 3rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .match-prompt h2 {
            color: #2d3748;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .match-prompt p {
            color: #718096;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .find-match-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 1rem 3rem;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .find-match-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
        
        .loading-state {
            background: white;
            border-radius: 20px;
            padding: 3rem 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #e2e8f0;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1.5rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loading-text {
            color: #4a5568;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        
        .loading-subtext {
            color: #718096;
            font-size: 0.9rem;
        }
        
        .match-card-container {
            max-width: 450px;
            margin: 0 auto;
        }
        
        .match-found-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .match-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .match-title {
            color: #48bb78;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 2rem;
            margin: 0 auto 1rem;
            border: 4px solid white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .profile-name {
            font-size: 1.4rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .profile-meta {
            color: #718096;
            margin-bottom: 1rem;
        }
        
        .match-score {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 1.5rem;
        }
        
        .subjects-common {
            background: #f0fff4;
            border: 2px solid #68d391;
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .subjects-title {
            color: #2f855a;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .subject-highlight {
            background: #68d391;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .match-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .action-btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        
        .pass-btn {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .pass-btn:hover {
            background: #cbd5e0;
            transform: translateY(-1px);
        }
        
        .accept-btn {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
            box-shadow: 0 4px 15px rgba(72, 187, 120, 0.4);
        }
        
        .accept-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(72, 187, 120, 0.6);
        }
        
        .view-details-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .view-details-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
        
        .no-match-found {
            background: white;
            border-radius: 20px;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .no-match-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .no-match-title {
            color: #2d3748;
            font-size: 1.3rem;
            margin-bottom: 1rem;
        }
        
        .no-match-text {
            color: #718096;
            margin-bottom: 2rem;
        }
        
        .try-again-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .mode-toggle {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 2rem;
        }
        
        .mode-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            border: 2px solid #e2e8f0;
            background: white;
            color: #4a5568;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .mode-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: transparent;
        }
        
        .pass-prompt {
            background: #fff5f5;
            border: 2px solid #fc8181;
            border-radius: 15px;
            padding: 1rem;
            margin: 1rem 0;
            text-align: center;
        }
        
        .pass-prompt-text {
            color: #c53030;
            font-weight: 500;
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
                    <li><a href="../profile/index.php">Profile</a></li>
                    <li><a href="index.php">Matches</a></li>
                    <li><a href="../sessions/index.php">Sessions</a></li>
                    <li><a href="../messages/index.php">Messages</a></li>
                    <li><a href="../auth/logout.php" class="btn btn-outline">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0; min-height: 100vh; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);">
        <div class="container">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="auto-match-container">
                <?php // Only show mode toggle to peers, not mentors ?>
                <?php if ($user['role'] === 'peer'): ?>
                    <div class="mode-toggle">
                        <a href="?mode=seeking" class="mode-btn <?php echo $help_mode === 'seeking' ? 'active' : ''; ?>">
                            🙋 Looking for Help
                        </a>
                        <a href="?mode=offering" class="mode-btn <?php echo $help_mode === 'offering' ? 'active' : ''; ?>">
                            ✅ Offering Help
                        </a>
                    </div>
                <?php endif; ?>

                <?php if (isset($_POST['pass_match'])): ?>
                    <div class="pass-prompt">
                        <div class="pass-prompt-text">Skipping this partner… Finding another with similar subjects and availability.</div>
                    </div>
                <?php endif; ?>

                <?php if ($loading && !$current_match && !$error): ?>
                    <div class="loading-state">
                        <div class="loading-spinner"></div>
                        <div class="loading-text">Looking for the best match…</div>
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
                                Finding students who need help with your expertise: 
                                <strong><?php echo implode(', ', array_slice($teaching_subjects, 0, 3)); ?><?php echo count($teaching_subjects) > 3 ? ', and more' : ''; ?></strong>. 
                                We'll connect you with students who want to learn these subjects and fit your schedule and location.
                            <?php else: ?>
                                Finding the best available match for you based on your profile subjects: 
                                <strong><?php echo implode(', ', array_slice($user_subjects, 0, 3)); ?><?php echo count($user_subjects) > 3 ? ', and more' : ''; ?></strong>. 
                                We'll connect you with mentors or peers who share any of these subjects and fit your schedule and location.
                            <?php endif; ?>
                        </div>
                    </div>
                    <script>
                        // Auto-refresh to show results after loading animation
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    </script>
                <?php elseif ($current_match): ?>
                    <div class="match-card-container">
                        <div class="match-found-card">
                            <div class="match-header">
                                <div class="match-title">Here's your match!</div>
                                <div class="profile-avatar">
                                    <?php if (!empty($current_match['profile_picture']) && file_exists('../' . $current_match['profile_picture'])): ?>
                                        <img src="../<?php echo htmlspecialchars($current_match['profile_picture']); ?>" 
                                             alt="<?php echo htmlspecialchars($current_match['first_name']); ?>" 
                                             style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid white; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($current_match['first_name'], 0, 1) . substr($current_match['last_name'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="profile-name">
                                    <?php echo htmlspecialchars($current_match['first_name'] . ' ' . $current_match['last_name']); ?>
                                </div>
                                <div class="profile-meta">
                                    <?php echo $current_match['role'] === 'peer' ? '🤝 Peer' : ucfirst($current_match['role']); ?> • 
                                    <?php echo htmlspecialchars($current_match['grade_level'] ?? 'Grade not set'); ?>
                                    <?php if ($current_match['location']): ?>
                                        • <?php echo htmlspecialchars($current_match['location']); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="match-score">
                                    <?php echo $current_match['match_score']; ?>% Match
                                    <?php if ($current_match['avg_rating']): ?>
                                        • ⭐ <?php echo number_format($current_match['avg_rating'], 1); ?>
                                    <?php endif; ?>
                                    <?php if ($current_match['matched_subject_count'] > 1): ?>
                                        • 🎯 <?php echo $current_match['matched_subject_count']; ?> subjects in common
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="subjects-common">
                                <div class="subjects-title">
                                    Subject<?php echo count($current_match['selected_subjects']) > 1 ? 's' : ''; ?> in Common:
                                </div>
                                <?php foreach ($current_match['selected_subjects'] as $subject): ?>
                                    <span class="subject-highlight" style="margin-right: 0.5rem; margin-bottom: 0.5rem; display: inline-block;">
                                        <?php echo htmlspecialchars($subject); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if ($current_match['bio']): ?>
                                <div style="background: #f7fafc; padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                                    <div style="font-weight: 600; color: #2d3748; margin-bottom: 0.5rem;">About:</div>
                                    <div style="color: #4a5568; line-height: 1.5;">
                                        <?php echo nl2br(htmlspecialchars(substr($current_match['bio'], 0, 150))); ?>
                                        <?php if (strlen($current_match['bio']) > 150): ?>...<?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($current_match['availability_slots'] > 0): ?>
                                <div style="background: #f0fff4; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; text-align: center;">
                                    📅 Available <?php echo $current_match['availability_slots']; ?> time slot<?php echo $current_match['availability_slots'] > 1 ? 's' : ''; ?> per week
                                </div>
                            <?php endif; ?>
                            
                            <div class="match-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="passed_user_id" value="<?php echo $current_match['id']; ?>">
                                    <button type="submit" name="pass_match" class="action-btn pass-btn">Pass</button>
                                </form>
                                
                                <button onclick="showMatchDetails()" class="action-btn view-details-btn">View Details</button>
                                
                                <button onclick="acceptMatch()" class="action-btn accept-btn">Accept</button>
                            </div>
                        </div>
                    </div>
                <?php elseif ($loading && empty($current_match) && !$error): ?>
                    <div class="no-match-found">
                        <div class="no-match-icon">😔</div>
                        <div class="no-match-title">No matches available right now</div>
                        <div class="no-match-text">
                            No matches available right now based on your profile. Please try again later.
                        </div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <button type="submit" name="find_match" class="try-again-btn">Try Again</button>
                        </form>
                        <div style="margin-top: 1rem;">
                            <a href="../profile/subjects.php" class="btn btn-secondary">Update Your Subjects</a>
                        </div>
                        <?php if (!empty($_SESSION['passed_users'])): ?>
                            <div style="margin-top: 1rem;">
                                <a href="?reset=1&mode=<?php echo $help_mode; ?>" class="btn btn-outline">Start Fresh (Reset Passed Users)</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="match-prompt">
                        <h2>Ready to find your perfect study partner?</h2>
                        <p>
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
                                We'll find students who need help with your expertise 
                                (<?php echo implode(', ', array_slice($teaching_subjects, 0, 3)); ?><?php echo count($teaching_subjects) > 3 ? ', and more' : ''; ?>), 
                                based on location and availability. Students needing help with multiple subjects you can teach will be prioritized.
                            <?php else: ?>
                                We'll find the best partner for you based on your profile subjects 
                                (<?php echo implode(', ', array_slice($user_subjects, 0, 3)); ?><?php echo count($user_subjects) > 3 ? ', and more' : ''; ?>), 
                                location, and availability. Users matching multiple subjects will be prioritized.
                            <?php endif; ?>
                        </p>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <button type="submit" name="find_match" class="find-match-btn">Find Match</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div id="matchModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 20px; width: 90%; max-width: 500px; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
            <h3 style="margin-bottom: 1.5rem; text-align: center; color: #2d3748;">
                <?php echo $help_mode === 'offering' ? 'Offer Help' : 'Send Match Request'; ?>
            </h3>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="mentor_id" id="modal_mentor_id">
                <input type="hidden" name="mentor_name" id="modal_mentor_name_hidden">
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #2d3748;">
                        <?php echo $help_mode === 'offering' ? 'Offering help to:' : 'Requesting match with:'; ?>
                        <span id="modal_mentor_name" style="color: #667eea;"></span>
                    </label>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label for="modal_subject" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #2d3748;">Subject</label>
                    <input type="text" id="modal_subject" name="subject" readonly style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 10px; background: #f7fafc;">
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <label for="modal_message" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #2d3748;">Message (Optional)</label>
                    <textarea id="modal_message" name="message" rows="3" style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 10px; resize: vertical;"
                              placeholder="<?php echo $help_mode === 'offering' ? 'Let them know how you can help...' : 'Introduce yourself and explain what you\'d like help with...'; ?>"></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" name="request_match" style="flex: 1; padding: 0.75rem; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer;">
                        <?php echo $help_mode === 'offering' ? 'Offer Help' : 'Send Request'; ?>
                    </button>
                    <button type="button" onclick="closeMatchModal()" style="flex: 1; padding: 0.75rem; background: #e2e8f0; color: #4a5568; border: none; border-radius: 10px; font-weight: 600; cursor: pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function acceptMatch() {
            <?php if ($current_match): ?>
                document.getElementById('modal_mentor_id').value = '<?php echo $current_match['id']; ?>';
                document.getElementById('modal_mentor_name').textContent = '<?php echo htmlspecialchars($current_match['first_name'] . ' ' . $current_match['last_name']); ?>';
                document.getElementById('modal_mentor_name_hidden').value = '<?php echo htmlspecialchars($current_match['first_name'] . ' ' . $current_match['last_name']); ?>';
                document.getElementById('modal_subject').value = '<?php echo htmlspecialchars($current_match['selected_subjects'][0]); ?>';
                document.getElementById('matchModal').style.display = 'block';
            <?php endif; ?>
        }
        
        function showMatchDetails() {
            // Could expand to show full profile details
            alert('Full profile details feature coming soon!');
        }
        
        function closeMatchModal() {
            document.getElementById('matchModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('matchModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeMatchModal();
            }
        });
    </script>

    <script src="../assets/js/hybrid-notifications.js"></script>
</body>
</html>
