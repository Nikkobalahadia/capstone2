<?php
require_once '../config/config.php';
require_once '../includes/subjects_hierarchy.php';

// --- [NEW] AJAX Action Handler ---
// This block handles async requests from the JavaScript
if (isset($_POST['action']) && $_POST['action'] === 'check_referral') {
    header('Content-Type: application/json');
    $code = sanitize_input($_POST['code'] ?? '');
    $response = ['valid' => false, 'message' => 'Please enter a code.'];

    if (empty($code)) {
        echo json_encode($response);
        exit;
    }

    $db = getDB();
    $ref_stmt = $db->prepare("SELECT id, created_by, max_uses, current_uses FROM referral_codes WHERE code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())");
    $ref_stmt->execute([$code]);
    $referral = $ref_stmt->fetch();

    if ($referral) {
        if ($referral['current_uses'] < $referral['max_uses']) {
            $response = ['valid' => true, 'message' => 'Referral code is valid!'];
        } else {
            $response = ['valid' => false, 'message' => 'This referral code has reached its maximum uses.'];
        }
    } else {
        $response = ['valid' => false, 'message' => 'Invalid or expired referral code.'];
    }
    echo json_encode($response);
    exit;
}
// --- [End of AJAX Handler] ---


// Check if user is logged in
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user) {
    redirect('auth/login.php');
}

// [IMPROVED] Use an array for errors
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors['csrf'] = 'Invalid security token. Please try again.';
    } else {
        $role = $user['role'];
        
        // Common fields
        $location = sanitize_input($_POST['location']);
        $bio = sanitize_input($_POST['bio']);
        $subjects = $_POST['subjects'] ?? [];
        
        $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $location_accuracy = !empty($_POST['location_accuracy']) ? (int)$_POST['location_accuracy'] : null;
        
        $referral_code = null;
        $referral = null;
        if ($role === 'mentor' && !empty($_POST['referral_code'])) {
            $referral_code = sanitize_input($_POST['referral_code']);
            
            // Validate referral code
            $db = getDB();
            $ref_stmt = $db->prepare("SELECT id, created_by, max_uses, current_uses FROM referral_codes WHERE code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())");
            $ref_stmt->execute([$referral_code]);
            $referral = $ref_stmt->fetch();
            
            if (!$referral || $referral['current_uses'] >= $referral['max_uses']) {
                $errors['referral_code'] = 'Invalid or expired referral code.';
            }
        }
        
        // Role-specific fields
        if ($role === 'student' || $role === 'peer') {
            $grade_level = sanitize_input($_POST['grade_level']);
            $strand = sanitize_input($_POST['strand'] ?? '');
            $course = sanitize_input($_POST['course'] ?? '');
            $learning_goals = sanitize_input($_POST['learning_goals']);
            $preferred_learning_style = sanitize_input($_POST['preferred_learning_style']);
        }
        
        if ($role === 'mentor' || $role === 'peer') {
            $teaching_style = sanitize_input($_POST['teaching_style']);
            $availability = $_POST['availability'] ?? [];
            $hourly_rate = !empty($_POST['hourly_rate']) ? (float)$_POST['hourly_rate'] : null;

            // [NEW] Server-side availability validation
            foreach ($availability as $avail_data) {
                $parts = explode('|', $avail_data);
                if (count($parts) === 3 && !empty($parts[1]) && !empty($parts[2])) {
                    if ($parts[2] <= $parts[1]) { // end_time <= start_time
                        $errors['availability'] = 'End time must be after start time for all slots.';
                        break;
                    }
                }
            }
        }
        
        $learning_subjects = [];
        $teaching_subjects = [];
        
        if ($role === 'peer') {
            $learning_subjects = $_POST['learning_subjects'] ?? [];
            $teaching_subjects = $_POST['teaching_subjects'] ?? [];
            $subjects = array_merge($learning_subjects, $teaching_subjects);
            $hourly_rate = !empty($_POST['hourly_rate']) ? (float)$_POST['hourly_rate'] : null;
        }

        $profile_picture_path = null;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file = $_FILES['profile_picture'];
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowed_types)) {
                $errors['profile_picture'] = 'Please upload a valid image file (JPG, PNG, or GIF).';
            } elseif ($file['size'] > $max_size) {
                $errors['profile_picture'] = 'Image file size must be less than 5MB.';
            } else {
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'profile_' . $user['id'] . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $profile_picture_path = 'uploads/profiles/' . $filename;
                } else {
                    $errors['profile_picture'] = 'Failed to upload profile picture. Please try again.';
                }
            }
        }
        
        // [IMPROVED] Field validation using the $errors array
        if (empty($errors)) {
            if ($role === 'student') {
                if (empty($grade_level)) $errors['grade_level'] = 'Grade level is required.';
                if (empty($location)) $errors['location'] = 'Location is required.';
                if (empty($bio)) $errors['bio'] = 'Bio is required.';
                if (empty($learning_goals)) $errors['learning_goals'] = 'Learning goals are required.';
            } elseif ($role === 'mentor') {
                if (empty($location)) $errors['location'] = 'Location is required.';
                if (empty($bio)) $errors['bio'] = 'Bio is required.';
                if (empty($teaching_style)) $errors['teaching_style'] = 'Teaching style is required.';
            } elseif ($role === 'peer') {
                if (empty($grade_level)) $errors['grade_level'] = 'Grade level is required.';
                if (empty($location)) $errors['location'] = 'Location is required.';
                if (empty($bio)) $errors['bio'] = 'Bio is required.';
                if (empty($learning_goals)) $errors['learning_goals'] = 'Learning goals are required.';
                if (empty($teaching_style)) $errors['teaching_style'] = 'Teaching style is required.';
                
                // [NEW] Peer-specific subject validation
                if (empty($learning_subjects)) {
                    $errors['learning_subjects'] = 'Please select at least one learning subject.';
                }
                if (empty($teaching_subjects)) {
                    $errors['teaching_subjects'] = 'Please select at least one teaching subject.';
                }
            }
            
            // [IMPROVED] General subject check only for non-peers
            if ($role !== 'peer' && empty($subjects)) {
                $errors['subjects'] = 'Please select at least one subject.';
            }
            
            if (empty($errors)) {
                try {
                    $db = getDB();
                    $db->beginTransaction();
                    
                    if ($role === 'mentor' && !empty($referral_code) && $referral) {
                        // Update referral code usage
                        $update_ref = $db->prepare("UPDATE referral_codes SET current_uses = current_uses + 1 WHERE id = ?");
                        $update_ref->execute([$referral['id']]);
                        
                        // Mark mentor as verified if they used a valid referral code
                        $verify_stmt = $db->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
                        $verify_stmt->execute([$user['id']]);
                        
                        // Log the referral usage
                        $referral_details = json_encode([
                            'role' => $role,
                            'referral_used' => true,
                            'referral_code' => $referral_code,
                            'referral_code_id' => $referral['id'],
                            'referred_by' => $referral['created_by']
                        ]);
                        
                        $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'referral_code_used', ?, ?)");
                        $log_stmt->execute([$user['id'], $referral_details, $_SERVER['REMOTE_ADDR']]);
                         } elseif ($role === 'mentor' || $role === 'peer') {
                        $unverify_stmt = $db->prepare("UPDATE users SET is_verified = 0 WHERE id = ?");
                        $unverify_stmt->execute([$user['id']]);
                    } elseif ($role === 'student') {
                        $unverify_stmt = $db->prepare("UPDATE users SET is_verified = 0 WHERE id = ?");
                        $unverify_stmt->execute([$user['id']]);

                    }
                    
                    if ($role === 'student') {
                        if ($profile_picture_path) {
                            $stmt = $db->prepare("UPDATE users SET grade_level = ?, strand = ?, course = ?, location = ?, latitude = ?, longitude = ?, location_accuracy = ?, bio = ?, learning_goals = ?, preferred_learning_style = ?, profile_picture = ? WHERE id = ?");
                            $stmt->execute([$grade_level, $strand, $course, $location, $latitude, $longitude, $location_accuracy, $bio, $learning_goals, $preferred_learning_style, $profile_picture_path, $user['id']]);
                        } else {
                            $stmt = $db->prepare("UPDATE users SET grade_level = ?, strand = ?, course = ?, location = ?, latitude = ?, longitude = ?, location_accuracy = ?, bio = ?, learning_goals = ?, preferred_learning_style = ? WHERE id = ?");
                            $stmt->execute([$grade_level, $strand, $course, $location, $latitude, $longitude, $location_accuracy, $bio, $learning_goals, $preferred_learning_style, $user['id']]);
                        }
                    } elseif ($role === 'mentor') {
                        if ($profile_picture_path) {
                            $stmt = $db->prepare("UPDATE users SET location = ?, latitude = ?, longitude = ?, location_accuracy = ?, bio = ?, teaching_style = ?, hourly_rate = ?, profile_picture = ? WHERE id = ?");
                            $stmt->execute([$location, $latitude, $longitude, $location_accuracy, $bio, $teaching_style, $hourly_rate, $profile_picture_path, $user['id']]);
                        } else {
                            $stmt = $db->prepare("UPDATE users SET location = ?, latitude = ?, longitude = ?, location_accuracy = ?, bio = ?, teaching_style = ?, hourly_rate = ? WHERE id = ?");
                            $stmt->execute([$location, $latitude, $longitude, $location_accuracy, $bio, $teaching_style, $hourly_rate, $user['id']]);
                        }
                        
                        if (!empty($availability)) {
                            // Clear existing availability
                            $clear_avail = $db->prepare("DELETE FROM user_availability WHERE user_id = ?");
                            $clear_avail->execute([$user['id']]);
                            
                            // Add new availability
                            $avail_stmt = $db->prepare("INSERT INTO user_availability (user_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)");
                            foreach ($availability as $avail_data) {
                                $avail_parts = explode('|', $avail_data);
                                if (count($avail_parts) === 3) {
                                    $avail_stmt->execute([$user['id'], $avail_parts[0], $avail_parts[1], $avail_parts[2]]);
                                }
                            }
                        }
                    } elseif ($role === 'peer') {
                        if ($profile_picture_path) {
                            $stmt = $db->prepare("UPDATE users SET grade_level = ?, strand = ?, course = ?, location = ?, latitude = ?, longitude = ?, location_accuracy = ?, bio = ?, learning_goals = ?, preferred_learning_style = ?, teaching_style = ?, hourly_rate = ?, profile_picture = ? WHERE id = ?");
                            $stmt->execute([$grade_level, $strand, $course, $location, $latitude, $longitude, $location_accuracy, $bio, $learning_goals, $preferred_learning_style, $teaching_style, $hourly_rate, $profile_picture_path, $user['id']]);
                        } else {
                            $stmt = $db->prepare("UPDATE users SET grade_level = ?, strand = ?, course = ?, location = ?, latitude = ?, longitude = ?, location_accuracy = ?, bio = ?, learning_goals = ?, preferred_learning_style = ?, teaching_style = ?, hourly_rate = ? WHERE id = ?");
                            $stmt->execute([$grade_level, $strand, $course, $location, $latitude, $longitude, $location_accuracy, $bio, $learning_goals, $preferred_learning_style, $teaching_style, $hourly_rate, $user['id']]);
                        }
                        
                        if (!empty($availability)) {
                            // Clear existing availability
                            $clear_avail = $db->prepare("DELETE FROM user_availability WHERE user_id = ?");
                            $clear_avail->execute([$user['id']]);
                            
                            // Add new availability
                            $avail_stmt = $db->prepare("INSERT INTO user_availability (user_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)");
                            foreach ($availability as $avail_data) {
                                $avail_parts = explode('|', $avail_data);
                                if (count($avail_parts) === 3) {
                                    $avail_stmt->execute([$user['id'], $avail_parts[0], $avail_parts[1], $avail_parts[2]]);
                                }
                            }
                        }
                    }
                    
                    // Clear existing subjects
                    $clear_stmt = $db->prepare("DELETE FROM user_subjects WHERE user_id = ?");
                    $clear_stmt->execute([$user['id']]);
                    
                    // Add new subjects
                    $subject_stmt = $db->prepare("INSERT INTO user_subjects (user_id, subject_name, proficiency_level, main_subject, subtopic) VALUES (?, ?, ?, ?, ?)");
                    foreach ($subjects as $subject_data) {
                        $subject_parts = explode('|', $subject_data);
                        if (count($subject_parts) === 3) {
                            $main_subject = $subject_parts[0];
                            $subtopic = $subject_parts[1]; 
                            $proficiency_level = $subject_parts[2];
                            
                            $subject_stmt->execute([$user['id'], $subtopic, $proficiency_level, $main_subject, $subtopic]);
                        } elseif (count($subject_parts) === 2) {
                            $subject_name = $subject_parts[0];
                            $proficiency_level = $subject_parts[1];
                            
                            $subject_stmt->execute([$user['id'], $subject_name, $proficiency_level, null, null]);
                        }
                    }
                    
                    $db->commit();
                    $success = 'Profile updated successfully!';
                    
                    if ($latitude && $longitude) {
                        try {
                            require_once '../includes/matchmaking.php';
                            $matchmaker = new MatchmakingEngine($db);
                            
                            // Find nearest matches based on location
                            $nearest_matches = $matchmaker->findNearestMatches($user['id'], 5); // Find 5 nearest matches
                            
                            if (!empty($nearest_matches)) {
                                $success .= ' Found ' . count($nearest_matches) . ' nearby study partners!';
                                
                                // Log the automatic matching
                                $match_details = json_encode([
                                    'auto_match' => true,
                                    'location_based' => true,
                                    'matches_found' => count($nearest_matches),
                                    'coordinates' => ['lat' => $latitude, 'lng' => $longitude]
                                ]);
                                
                                $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'auto_location_match', ?, ?)");
                                $log_stmt->execute([$user['id'], $match_details, $_SERVER['REMOTE_ADDR']]);
                            }
                        } catch (Exception $e) {
                            error_log("Auto-matching error: " . $e->getMessage());
                            // Don't fail the profile save if matching fails
                        }
                    }
                    
                    // Redirect to dashboard after 2 seconds
                    header("refresh:2;url=" . BASE_URL . "dashboard.php");
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $errors['db'] = 'Failed to update profile. Please try again.';
                }
            }
        }
    }
}

// --- [Original PHP data fetching - UNCHANGED] ---
// Get existing profile data
$db = getDB();
$subjects_stmt = $db->prepare("SELECT subject_name, proficiency_level FROM user_subjects WHERE user_id = ?");
$subjects_stmt->execute([$user['id']]);
$existing_subjects = $subjects_stmt->fetchAll();

// Get existing availability for mentors
$existing_availability = [];
if ($user['role'] === 'mentor' || $user['role'] === 'peer') {
    $avail_stmt = $db->prepare("SELECT day_of_week, start_time, end_time FROM user_availability WHERE user_id = ?");
    $avail_stmt->execute([$user['id']]);
    $existing_availability = $avail_stmt->fetchAll();
}

$subjectsHierarchy = getSubjectsHierarchy();
$common_subjects = getMainSubjects();

// --- [Original PHP step logic - UNCHANGED] ---
$role = $user['role'];
$steps = [];
$steps[] = 'Personal Info'; // Step 1

if ($role === 'student' || $role === 'peer') {
    $steps[] = 'Academic Info'; // Step 2 (Student, Peer)
}
if ($role === 'mentor' || $role === 'peer') {
    $steps[] = 'Teaching Info'; // Step 2 (Mentor) or 3 (Peer)
}
if ($role === 'mentor' || $role === 'peer') {
    $steps[] = 'Availability'; // Step 3 (Mentor) or 4 (Peer)
}
if ($role === 'student') {
    $steps[] = 'Learning Subjects'; // Step 3 (Student)
}
if ($role === 'mentor') {
    $steps[] = 'Teaching Subjects'; // Step 4 (Mentor)
}
if ($role === 'peer') {
    $steps[] = 'Your Subjects'; // Step 5 (Peer)
}

$steps[] = 'Final Details'; // Last step
$total_steps = count($steps);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Setup - Study Buddy</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        .wizard-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding: 0;
            counter-reset: step;
        }
        .wizard-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
        }
        .wizard-step::before {
            counter-increment: step;
            content: counter(step);
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            background-color: #f1f5f9;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.25rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .wizard-step::after {
            content: '';
            position: absolute;
            top: 1.5rem;
            left: 50%;
            width: 100%;
            height: 2px;
            background-color: #e2e8f0;
            z-index: -1;
            transform: translateX(-50%);
            transition: all 0.3s ease;
        }
        .wizard-step:first-child::after {
            width: 50%;
            left: 75%;
        }
        .wizard-step:last-child::after {
            width: 50%;
            left: 25%;
        }
        .wizard-step-title {
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: #64748b;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .wizard-step.active::before {
            background-color: var(--primary-color-light);
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .wizard-step.active .wizard-step-title {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .wizard-step.completed::before {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        .wizard-step.completed::after {
            background-color: var(--primary-color);
        }
        .wizard-step.completed .wizard-step-title {
            color: #334155;
        }
        
        .wizard-form-step {
            display: none;
            animation: fadeIn 0.5s;
        }
        .wizard-form-step.active {
            display: block;
        }
        
        .wizard-nav {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
            border-top: 1px solid #e2e8f0;
            padding-top: 1.5rem;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* [NEW] Error styling */
        .form-error-text {
            color: #dc2626; /* red-600 */
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .form-input.is-invalid,
        .form-select.is-invalid {
            border-color: #dc2626;
            box-shadow: 0 0 0 1px #dc2626;
        }
        
        /* Utility classes */
        .mb-3 { margin-bottom: 1rem; }
        .mb-4 { margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="../dashboard.php" class="logo">Study Buddy</a>
                <ul class="nav-links">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <li><a href="../auth/logout.php" class="btn btn-outline">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="container">
            <div class="form-container" style="max-width: 700px;">
                 <?php if ($user['role'] === 'peer'): ?>
                    <h2 class="text-center mb-4">🤝 Set up your peer profile</h2>
                    <p class="text-center text-secondary mb-4">Set up your profile for both learning and teaching - connect with peers who can help you learn and students you can mentor.</p>
                <?php elseif ($user['role'] === 'mentor'): ?>
                    <h2 class="text-center mb-4">👩‍🏫 Set up your mentor profile</h2>
                    <p class="text-center text-secondary mb-4">Set up your mentor profile so students can find and learn from you.</p>
                <?php else: ?>
                    <h2 class="text-center mb-4">🎓 Set up your student profile</h2>
                    <p class="text-center text-secondary mb-4">Set up your student profile so mentors can guide you better.</p>
                <?php endif; ?>
                
                <div class="wizard-steps">
                    <?php foreach ($steps as $index => $title): ?>
                        <div class="wizard-step" data-step="<?php echo $index + 1; ?>">
                            <div class="wizard-step-title"><?php echo htmlspecialchars($title); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (!empty($errors['csrf']) || !empty($errors['db'])): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($errors['csrf'] ?? $errors['db'] ?? 'An unknown error occurred.'); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data" id="setup-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <?php $step_idx = 1; ?>

                    <div class="wizard-form-step" data-step="<?php echo $step_idx++; ?>">
                        <h3 class="mb-3">Step 1: Personal Info</h3>
                        <p class="text-secondary mb-4">Let's start with the basics. Upload a photo so others can recognize you.</p>
                        
                        <div class="form-group">
                            <label for="profile_picture" class="form-label">Profile Picture (Optional)</label>
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div id="current-picture" style="width: 80px; height: 80px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold; color: #64748b;">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                </div>
                                <div style="flex: 1;">
                                    <input type="file" id="profile_picture" name="profile_picture" class="form-input <?php echo isset($errors['profile_picture']) ? 'is-invalid' : ''; ?>" accept="image/*">
                                    <p class="text-sm text-secondary mt-1">Upload a profile picture (JPG, PNG, or GIF, max 5MB)</p>
                                    <?php if (isset($errors['profile_picture'])): ?>
                                        <p class="form-error-text"><?php echo htmlspecialchars($errors['profile_picture']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div id="image-preview" style="margin-top: 1rem; display: none;">
                                <img id="preview-img" src="/placeholder.svg" alt="Preview" style="width: 150px; height: 150px; object-fit: cover; border-radius: 8px; border: 2px solid #e2e8f0;">
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($user['role'] === 'student' || $user['role'] === 'peer'): ?>
                    <div class="wizard-form-step" data-step="<?php echo $step_idx++; ?>">
                        <h3 class="mb-3">Academic Info</h3>
                        <p class="text-secondary mb-4">Tell us about your academic level so we can find the right matches.</p>
                        
                        <div class="form-group">
                            <label for="grade_level" class="form-label">Grade Level / Year Level</label>
                            <select id="grade_level" name="grade_level" class="form-select <?php echo isset($errors['grade_level']) ? 'is-invalid' : ''; ?>" required>
                                <option value="">Select Grade Level</option>
                                <option value="Grade 7" <?php echo ($user['grade_level'] ?? '') === 'Grade 7' ? 'selected' : ''; ?>>Grade 7</option>
                                <option value="Grade 8" <?php echo ($user['grade_level'] ?? '') === 'Grade 8' ? 'selected' : ''; ?>>Grade 8</option>
                                <option value="Grade 9" <?php echo ($user['grade_level'] ?? '') === 'Grade 9' ? 'selected' : ''; ?>>Grade 9</option>
                                <option value="Grade 10" <?php echo ($user['grade_level'] ?? '') === 'Grade 10' ? 'selected' : ''; ?>>Grade 10</option>
                                <option value="Grade 11" <?php echo ($user['grade_level'] ?? '') === 'Grade 11' ? 'selected' : ''; ?>>Grade 11</option>
                                <option value="Grade 12" <?php echo ($user['grade_level'] ?? '') === 'Grade 12' ? 'selected' : ''; ?>>Grade 12</option>
                                <option value="1st Year College" <?php echo ($user['grade_level'] ?? '') === '1st Year College' ? 'selected' : ''; ?>>1st Year College</option>
                                <option value="2nd Year College" <?php echo ($user['grade_level'] ?? '') === '2nd Year College' ? 'selected' : ''; ?>>2nd Year College</option>
                                <option value="3rd Year College" <?php echo ($user['grade_level'] ?? '') === '3rd Year College' ? 'selected' : ''; ?>>3rd Year College</option>
                                <option value="4th Year College" <?php echo ($user['grade_level'] ?? '') === '4th Year College' ? 'selected' : ''; ?>>4th Year College</option>
                            </select>
                            <p class="form-error-text" id="error-grade_level"></p>
                            <?php if (isset($errors['grade_level'])): ?>
                                <p class="form-error-text"><?php echo htmlspecialchars($errors['grade_level']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="grid grid-cols-2" style="gap: 1rem;">
                            <div class="form-group">
                                <label for="strand" class="form-label">Strand (if SHS)</label>
                                <select id="strand" name="strand" class="form-select">
                                    <option value="">Select Strand</option>
                                    <option value="STEM" <?php echo ($user['strand'] ?? '') === 'STEM' ? 'selected' : ''; ?>>STEM</option>
                                    <option value="ABM" <?php echo ($user['strand'] ?? '') === 'ABM' ? 'selected' : ''; ?>>ABM</option>
                                    <option value="HUMSS" <?php echo ($user['strand'] ?? '') === 'HUMSS' ? 'selected' : ''; ?>>HUMSS</option>
                                    <option value="GAS" <?php echo ($user['strand'] ?? '') === 'GAS' ? 'selected' : ''; ?>>GAS</option>
                                    <option value="TVL" <?php echo ($user['strand'] ?? '') === 'TVL' ? 'selected' : ''; ?>>TVL</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="course" class="form-label">Course (if College)</label>
                                <input type="text" id="course" name="course" class="form-input" 
                                       placeholder="e.g., BS Computer Science"
                                       value="<?php echo htmlspecialchars($user['course'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="learning_goals" class="form-label">Learning Goals / Challenges</label>
                            <textarea id="learning_goals" name="learning_goals" class="form-input <?php echo isset($errors['learning_goals']) ? 'is-invalid' : ''; ?>" rows="3" required
                                      placeholder="What do you want to achieve? What challenges are you facing?"><?php echo htmlspecialchars($user['learning_goals'] ?? ''); ?></textarea>
                            <p class="form-error-text" id="error-learning_goals"></p>
                            <?php if (isset($errors['learning_goals'])): ?>
                                <p class="form-error-text"><?php echo htmlspecialchars($errors['learning_goals']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="preferred_learning_style" class="form-label">Preferred Learning Style (Optional)</label>
                            <select id="preferred_learning_style" name="preferred_learning_style" class="form-select">
                                <option value="">Select Learning Style</option>
                                <option value="Visual" <?php echo ($user['preferred_learning_style'] ?? '') === 'Visual' ? 'selected' : ''; ?>>Visual (diagrams, charts, images)</option>
                                <option value="Auditory" <?php echo ($user['preferred_learning_style'] ?? '') === 'Auditory' ? 'selected' : ''; ?>>Auditory (listening, discussion)</option>
                                <option value="Kinesthetic" <?php echo ($user['preferred_learning_style'] ?? '') === 'Kinesthetic' ? 'selected' : ''; ?>>Kinesthetic (hands-on, practice)</option>
                                <option value="Reading/Writing" <?php echo ($user['preferred_learning_style'] ?? '') === 'Reading/Writing' ? 'selected' : ''; ?>>Reading/Writing (notes, text)</option>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($user['role'] === 'mentor' || $user['role'] === 'peer'): ?>
                    <div class="wizard-form-step" data-step="<?php echo $step_idx++; ?>">
                        <h3 class="mb-3">Teaching Profile</h3>
                        <p class="text-secondary mb-4">Describe your teaching style and set your rate.</p>
                        
                        <?php if ($user['role'] === 'mentor'): ?>
                            <div class="form-group">
                                <label for="referral_code" class="form-label">Referral Code (Optional)</label>
                                <div style="display: flex; gap: 0.5rem;">
                                    <input type="text" id="referral_code" name="referral_code" class="form-input <?php echo isset($errors['referral_code']) ? 'is-invalid' : ''; ?>" 
                                           placeholder="Enter referral code if you have one"
                                           value="<?php echo htmlspecialchars($_POST['referral_code'] ?? ''); ?>" style="flex: 1;">
                                    <button type="button" class="btn btn-secondary" id="check-referral-btn" style="white-space: nowrap;">Check</button>
                                </div>
                                 <p class="text-sm text-secondary mt-1">Using a referral code verifies your account and may unlock benefits.</p>
                                 <div id="referral-status" class="text-sm mt-1"></div>
                                 <?php if (isset($errors['referral_code'])): ?>
                                    <p class="form-error-text"><?php echo htmlspecialchars($errors['referral_code']); ?></p>
                                 <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="teaching_style" class="form-label">
                                <?php echo $user['role'] === 'peer' ? 'Teaching approach / mentoring style' : 'Short bio / teaching style'; ?>
                            </label>
                            <textarea id="teaching_style" name="teaching_style" class="form-input <?php echo isset($errors['teaching_style']) ? 'is-invalid' : ''; ?>" rows="4" required
                                      placeholder="Describe your teaching approach, experience, and what makes you a great mentor..."><?php echo htmlspecialchars($user['teaching_style'] ?? ''); ?></textarea>
                            <p class="form-error-text" id="error-teaching_style"></p>
                            <?php if (isset($errors['teaching_style'])): ?>
                                <p class="form-error-text"><?php echo htmlspecialchars($errors['teaching_style']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="hourly_rate" class="form-label">Hourly Rate (Optional)</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #718096; font-weight: 600;">₱</span>
                                <input type="number" id="hourly_rate" name="hourly_rate" class="form-input" 
                                       style="padding-left: 2.5rem;"
                                       min="0" 
                                       step="0.01" 
                                       placeholder="e.g., 150.00"
                                       value="<?php echo htmlspecialchars($user['hourly_rate'] ?? ''); ?>">
                            </div>
                            <p class="text-sm text-secondary mt-1">
                                Set your hourly rate for tutoring sessions. Leave blank if you're offering free help. 
                                This will be displayed to students when they find you.
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($user['role'] === 'mentor' || $user['role'] === 'peer'): ?>
                    <div class="wizard-form-step" data-step="<?php echo $step_idx++; ?>">
                        <h3 class="mb-3">Availability</h3>
                        <p class="text-secondary mb-4">Set your available days and time slots for tutoring sessions.</p>

                        <div class="form-group">
                            <label class="form-label">Availability (days & times)</label>
                            <p class="text-sm text-secondary mb-3">Set your available days and time slots for tutoring sessions. End time must be after start time.</p>
                            
                            <div id="availability-container">
                                <?php if (!empty($existing_availability)): ?>
                                    <?php foreach ($existing_availability as $avail): ?>
                                        <div class="availability-row" style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center;">
                                            <select class="availability-day form-select" style="flex: 1;" required>
                                                <option value="">Select Day</option>
                                                <option value="monday" <?php echo ($avail['day_of_week'] ?? '') === 'monday' ? 'selected' : ''; ?>>Monday</option>
                                                <option value="tuesday" <?php echo ($avail['day_of_week'] ?? '') === 'tuesday' ? 'selected' : ''; ?>>Tuesday</option>
                                                <option value="wednesday" <?php echo ($avail['day_of_week'] ?? '') === 'wednesday' ? 'selected' : ''; ?>>Wednesday</option>
                                                <option value="thursday" <?php echo ($avail['day_of_week'] ?? '') === 'thursday' ? 'selected' : ''; ?>>Thursday</option>
                                                <option value="friday" <?php echo ($avail['day_of_week'] ?? '') === 'friday' ? 'selected' : ''; ?>>Friday</option>
                                                <option value="saturday" <?php echo ($avail['day_of_week'] ?? '') === 'saturday' ? 'selected' : ''; ?>>Saturday</option>
                                                <option value="sunday" <?php echo ($avail['day_of_week'] ?? '') === 'sunday' ? 'selected' : ''; ?>>Sunday</option>
                                            </select>
                                            <input type="time" class="availability-start form-input" style="flex: 1;" value="<?php echo htmlspecialchars($avail['start_time']); ?>" required>
                                            <input type="time" class="availability-end form-input" style="flex: 1;" value="<?php echo htmlspecialchars($avail['end_time']); ?>" required>
                                            <input type="hidden" name="availability[]" class="availability-combined" value="<?php echo htmlspecialchars($avail['day_of_week'] . '|' . $avail['start_time'] . '|' . $avail['end_time']); ?>">
                                            <button type="button" class="btn btn-danger remove-availability-btn" style="padding: 0.5rem;">Remove</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="availability-row" style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center;">
                                        <select class="availability-day form-select" style="flex: 1;" required>
                                            <option value="">Select Day</option>
                                            <option value="monday">Monday</option>
                                            <option value="tuesday">Tuesday</option>
                                            <option value="wednesday">Wednesday</option>
                                            <option value="thursday">Thursday</option>
                                            <option value="friday">Friday</option>
                                            <option value="saturday">Saturday</option>
                                            <option value="sunday">Sunday</option>
                                        </select>
                                        <input type="time" class="availability-start form-input" style="flex: 1;" required>
                                        <input type="time" class="availability-end form-input" style="flex: 1;" required>
                                        <input type="hidden" name="availability[]" class="availability-combined" value="">
                                        <button type="button" class="btn btn-danger remove-availability-btn" style="padding: 0.5rem;">Remove</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <p class="form-error-text" id="error-availability"></p>
                            <?php if (isset($errors['availability'])): ?>
                                <p class="form-error-text"><?php echo htmlspecialchars($errors['availability']); ?></p>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-secondary" id="add-availability-btn">Add Time Slot</button>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($user['role'] === 'student'): ?>
                    <div class="wizard-form-step" data-step="<?php echo $step_idx++; ?>">
                        <h3 class="mb-3">Learning Subjects</h3>
                        <p class="text-secondary mb-4">What subjects do you need help with? Select at least one.</p>

                        <div class="form-group">
                            <label class="form-label">Subjects you want to learn</label>
                            <p class="text-sm text-secondary mb-3">Start by selecting a main subject (e.g., Mathematics). Once you choose, the system will automatically display related subtopics (e.g., Algebra, Calculus, Geometry) for you to refine your expertise or learning preference.</p>
                            <div class="example-hint" style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid var(--primary-color);">
                                <strong>👉 Example:</strong><br>
                                <span style="color: #64748b;">Main Subject Dropdown:</span> <strong>Mathematics</strong><br>
                                <span style="color: #64748b;">Subtopic Dropdown (auto-loaded):</span> <strong>Algebra, Calculus, Geometry</strong>
                            </div>
                        
                            <div id="subjects-container">
                                <?php if (!empty($existing_subjects)): ?>
                                    <?php foreach ($existing_subjects as $subject): ?>
                                        <div class="subject-row" style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center; flex-wrap: wrap;">
                                            <div style="display: flex; gap: 0.5rem; flex: 2; flex-wrap: wrap;">
                                                <?php
                                                // Find main subject for existing subject
                                                $main_subject_found = '';
                                                foreach ($subjectsHierarchy as $main => $subtopics) {
                                                    if (in_array($subject['subject_name'], $subtopics)) {
                                                        $main_subject_found = $main;
                                                        break;
                                                    }
                                                }
                                                ?>
                                                <select class="main-subject-select form-select" style="flex: 1; min-width: 150px;" required>
                                                    <option value="">Select Main Subject</option>
                                                    <?php foreach (getMainSubjects() as $subj): ?>
                                                        <option value="<?php echo htmlspecialchars($subj); ?>" <?php echo $main_subject_found === $subj ? 'selected' : ''; ?>><?php echo htmlspecialchars($subj); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <select class="subtopic-select form-select" style="flex: 1; min-width: 150px;" onchange="updateProficiencyLevels(this)">
                                                    <?php if ($main_subject_found): ?>
                                                        <option value="">Select Subtopic</option>
                                                        <?php foreach (getSubtopics($main_subject_found) as $subtopic): ?>
                                                            <option value="<?php echo htmlspecialchars($subtopic); ?>" <?php echo $subject['subject_name'] === $subtopic ? 'selected' : ''; ?>><?php echo htmlspecialchars($subtopic); ?></option>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <option value="<?php echo htmlspecialchars($subject['subject_name']); ?>" selected><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                                                    <?php endif; ?>
                                                </select>
                                                <select name="subjects[]" class="proficiency-select form-select" style="flex: 1; min-width: 120px;" required>
                                                    <option value="<?php echo htmlspecialchars($main_subject_found . '|' . $subject['subject_name'] . '|' . $subject['proficiency_level']); ?>" selected>
                                                        <?php echo htmlspecialchars($subject['subject_name'] . ' - ' . ucfirst($subject['proficiency_level'])); ?>
                                                    </option>
                                                </select>
                                            </div>
                                            <button type="button" class="btn btn-danger remove-subject-btn" style="padding: 0.5rem;">Remove</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="subject-row" style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center; flex-wrap: wrap;">
                                        <div style="display: flex; gap: 0.5rem; flex: 2; flex-wrap: wrap;">
                                            <select class="main-subject-select form-select" style="flex: 1; min-width: 150px;" required>
                                                <option value="">Select Main Subject</option>
                                                <?php foreach (getMainSubjects() as $subject): ?>
                                                    <option value="<?php echo htmlspecialchars($subject); ?>"><?php echo htmlspecialchars($subject); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <select class="subtopic-select form-select" style="flex: 1; min-width: 150px;" disabled>
                                                <option value="">Select Subtopic First</option>
                                            </select>
                                            <select name="subjects[]" class="proficiency-select form-select" style="flex: 1; min-width: 120px;" disabled required>
                                                <option value="">Select Level</option>
                                            </select>
                                        </div>
                                        <button type="button" class="btn btn-danger remove-subject-btn" style="padding: 0.5rem;">Remove</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <p class="form-error-text" id="error-subjects"></p>
                            <?php if (isset($errors['subjects'])): ?>
                                <p class="form-error-text"><?php echo htmlspecialchars($errors['subjects']); ?></p>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-secondary" id="add-subject-btn">Add Another Subject</button>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($user['role'] === 'mentor'): ?>
                    <div class="wizard-form-step" data-step="<?php echo $step_idx++; ?>">
                        <h3 class="mb-3">Teaching Subjects</h3>
                        <p class="text-secondary mb-4">What subjects can you teach? Select at least one.</p>
                        
                        <div class="form-group">
                            <label class="form-label">Subjects you can teach</label>
                            <p class="text-sm text-secondary mb-3">Start by selecting a main subject (e.g., Mathematics). Once you choose, the system will automatically display related subtopics (e.g., Algebra, Calculus, Geometry) for you to refine your expertise or learning preference.</p>
                            <div class="example-hint" style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid var(--primary-color);">
                                <strong>👉 Example:</strong><br>
                                <span style="color: #64748b;">Main Subject Dropdown:</span> <strong>Mathematics</strong><br>
                                <span style="color: #64748b;">Subtopic Dropdown (auto-loaded):</span> <strong>Algebra, Calculus, Geometry</strong>
                            </div>
                            
                            <div id="subjects-container">
                                <?php if (!empty($existing_subjects)): ?>
                                    <?php foreach ($existing_subjects as $subject): ?>
                                        <div class="subject-row" style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center; flex-wrap: wrap;">
                                            <div style="display: flex; gap: 0.5rem; flex: 2; flex-wrap: wrap;">
                                                <?php
                                                // Find main subject for existing subject
                                                $main_subject_found = '';
                                                foreach ($subjectsHierarchy as $main => $subtopics) {
                                                    if (in_array($subject['subject_name'], $subtopics)) {
                                                        $main_subject_found = $main;
                                                        break;
                                                    }
                                                }
                                                ?>
                                                <select class="main-subject-select form-select" style="flex: 1; min-width: 150px;" required>
                                                    <option value="">Select Main Subject</option>
                                                    <?php foreach (getMainSubjects() as $subj): ?>
                                                        <option value="<?php echo htmlspecialchars($subj); ?>" <?php echo $main_subject_found === $subj ? 'selected' : ''; ?>><?php echo htmlspecialchars($subj); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <select class="subtopic-select form-select" style="flex: 1; min-width: 150px;" onchange="updateProficiencyLevels(this)">
                                                    <?php if ($main_subject_found): ?>
                                                        <option value="">Select Subtopic</option>
                                                        <?php foreach (getSubtopics($main_subject_found) as $subtopic): ?>
                                                            <option value="<?php echo htmlspecialchars($subtopic); ?>" <?php echo $subject['subject_name'] === $subtopic ? 'selected' : ''; ?>><?php echo htmlspecialchars($subtopic); ?></option>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <option value="<?php echo htmlspecialchars($subject['subject_name']); ?>" selected><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                                                    <?php endif; ?>
                                                </select>
                                                <select name="subjects[]" class="proficiency-select form-select" style="flex: 1; min-width: 120px;" required>
                                                    <option value="<?php echo htmlspecialchars($main_subject_found . '|' . $subject['subject_name'] . '|' . $subject['proficiency_level']); ?>" selected>
                                                        <?php echo htmlspecialchars($subject['subject_name'] . ' - ' . ucfirst($subject['proficiency_level'])); ?>
                                                    </option>
                                                </select>
                                            </div>
                                            <button type="button" class="btn btn-danger remove-subject-btn" style="padding: 0.5rem;">Remove</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="subject-row" style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center; flex-wrap: wrap;">
                                        <div style="display: flex; gap: 0.5rem; flex: 2; flex-wrap: wrap;">
                                            <select class="main-subject-select form-select" style="flex: 1; min-width: 150px;" required>
                                                <option value="">Select Main Subject</option>
                                                <?php foreach (getMainSubjects() as $subject): ?>
                                                    <option value="<?php echo htmlspecialchars($subject); ?>"><?php echo htmlspecialchars($subject); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <select class="subtopic-select form-select" style="flex: 1; min-width: 150px;" disabled>
                                                <option value="">Select Subtopic First</option>
                                            </select>
                                            <select name="subjects[]" class="proficiency-select form-select" style="flex: 1; min-width: 120px;" disabled required>
                                                <option value="">Select Level</option>
                                            </select>
                                        </div>
                                        <button type="button" class="btn btn-danger remove-subject-btn" style="padding: 0.5rem;">Remove</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <p class="form-error-text" id="error-subjects"></p>
                            <?php if (isset($errors['subjects'])): ?>
                                <p class="form-error-text"><?php echo htmlspecialchars($errors['subjects']); ?></p>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-secondary" id="add-subject-btn">Add Another Subject</button>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($user['role'] === 'peer'): ?>
                    <div class="wizard-form-step" data-step="<?php echo $step_idx++; ?>">
                        <h3 class="mb-3">Your Subjects</h3>
                        <p class="text-secondary mb-4">Select the subjects you want to learn and the subjects you can teach. Select at least one for each.</p>
                        
                        <div class="form-group">
                            <label class="form-label">Subjects you want to learn</label>
                            <p class="text-sm text-secondary mb-3">Start by selecting a main subject (e.g., Mathematics). Once you choose, the system will automatically display related subtopics (e.g., Algebra, Calculus, Geometry) for you to refine your expertise or learning preference.</p>
                            <div class="example-hint" style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid var(--primary-color);">
                                <strong>👉 Example:</strong><br>
                                <span style="color: #64748b;">Main Subject Dropdown:</span> <strong>Mathematics</strong><br>
                                <span style="color: #64748b;">Subtopic Dropdown (auto-loaded):</span> <strong>Algebra, Calculus, Geometry</strong>
                            </div>
                            <div id="learning-subjects-container">
                                <div class="subject-row" style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center; flex-wrap: wrap;">
                                    <div style="display: flex; gap: 0.5rem; flex: 2; flex-wrap: wrap;">
                                        <select class="main-subject-select form-select" style="flex: 1; min-width: 150px;" required>
                                            <option value="">Select Main Subject</option>
                                            <?php foreach (getMainSubjects() as $subject): ?>
                                                <option value="<?php echo htmlspecialchars($subject); ?>"><?php echo htmlspecialchars($subject); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select class="subtopic-select form-select" style="flex: 1; min-width: 150px;" disabled>
                                            <option value="">Select Subtopic First</option>
                                        </select>
                                        <select name="learning_subjects[]" class="proficiency-select form-select" style="flex: 1; min-width: 120px;" disabled required>
                                            <option value="">Select Level</option>
                                        </select>
                                    </div>
                                    <button type="button" class="btn btn-danger remove-subject-btn" style="padding: 0.5rem;">Remove</button>
                                </div>
                            </div>
                            <p class="form-error-text" id="error-learning_subjects"></p>
                            <?php if (isset($errors['learning_subjects'])): ?>
                                <p class="form-error-text"><?php echo htmlspecialchars($errors['learning_subjects']); ?></p>
                            <?php endif; ?>
                            <button type="button" class="btn btn-secondary" id="add-learning-subject-btn">Add Learning Subject</button>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Subjects you can teach</label>
                            <p class="text-sm text-secondary mb-3">Start by selecting a main subject (e.g., Mathematics). Once you choose, the system will automatically display related subtopics (e.g., Algebra, Calculus, Geometry) for you to refine your expertise or learning preference.</p>
                            <div id="teaching-subjects-container">
                                <div class="subject-row" style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center; flex-wrap: wrap;">
                                    <div style="display: flex; gap: 0.5rem; flex: 2; flex-wrap: wrap;">
                                        <select class="main-subject-select form-select" style="flex: 1; min-width: 150px;" required>
                                            <option value="">Select Main Subject</option>
                                            <?php foreach (getMainSubjects() as $subject): ?>
                                                <option value="<?php echo htmlspecialchars($subject); ?>"><?php echo htmlspecialchars($subject); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select class="subtopic-select form-select" style="flex: 1; min-width: 150px;" disabled>
                                            <option value="">Select Subtopic First</option>
                                        </select>
                                        <select name="teaching_subjects[]" class="proficiency-select form-select" style="flex: 1; min-width: 120px;" disabled required>
                                            <option value="">Select Level</option>
                                        </select>
                                    </div>
                                    <button type="button" class="btn btn-danger remove-subject-btn" style="padding: 0.5rem;">Remove</button>
                                </div>
                            </div>
                            <p class="form-error-text" id="error-teaching_subjects"></p>
                             <?php if (isset($errors['teaching_subjects'])): ?>
                                <p class="form-error-text"><?php echo htmlspecialchars($errors['teaching_subjects']); ?></p>
                            <?php endif; ?>
                            <button type="button" class="btn btn-secondary" id="add-teaching-subject-btn">Add Teaching Subject</button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="wizard-form-step" data-step="<?php echo $step_idx++; ?>">
                        <h3 class="mb-3">Final Details</h3>
                        <p class="text-secondary mb-4">Finally, tell us your location for finding nearby buddies and a short bio about yourself.</p>

                        <div class="form-group">
                            <label for="location" class="form-label">Location</label>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <input type="text" id="location" name="location" class="form-input <?php echo isset($errors['location']) ? 'is-invalid' : ''; ?>" required
                                       placeholder="Start typing your location..."
                                       style="flex: 1;"
                                       value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>">
                                <button type="button" id="detect-location" class="btn btn-secondary" 
                                        style="white-space: nowrap; padding: 0.75rem 1rem;">
                                    📍 Use My Location
                                </button>
                            </div>
                            <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($user['latitude'] ?? ''); ?>">
                            <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($user['longitude'] ?? ''); ?>">
                            <input type="hidden" id="location_accuracy" name="location_accuracy" value="<?php echo htmlspecialchars($user['location_accuracy'] ?? ''); ?>">
                            <div id="location-status" class="text-sm text-secondary mt-2" style="display: none;"></div>
                            <p class="form-error-text" id="error-location"></p>
                            <?php if (isset($errors['location'])): ?>
                                <p class="form-error-text"><?php echo htmlspecialchars($errors['location']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="bio" class="form-label">Bio</label>
                            <textarea id="bio" name="bio" class="form-input <?php echo isset($errors['bio']) ? 'is-invalid' : ''; ?>" rows="4" required
                                      placeholder="Tell others about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            <p class="form-error-text" id="error-bio"></p>
                            <?php if (isset($errors['bio'])): ?>
                                <p class="form-error-text"><?php echo htmlspecialchars($errors['bio']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="wizard-nav">
                        <button type="button" class="btn btn-secondary" id="prev-btn" style="display: none;">Previous</button>
                        <button type="button" class="btn btn-primary" id="next-btn">Next</button>
                        <button type="submit" class="btn btn-primary" id="submit-btn" style="display: none; width: auto;">Save Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
    // --- [NEW] Helper functions for client-side validation ---
    function showError(element, message) {
        element.classList.add('is-invalid');
        const errorId = 'error-' + element.id;
        let errorEl = document.getElementById(errorId);
        if (errorEl) {
            errorEl.textContent = message;
        } else {
            // Fallback for elements without a dedicated error p-tag
            let newErrorEl = document.createElement('p');
            newErrorEl.className = 'form-error-text';
            newErrorEl.textContent = message;
            element.parentNode.appendChild(newErrorEl);
        }
    }

    function clearError(element) {
        element.classList.remove('is-invalid');
        const errorId = 'error-' + element.id;
        let errorEl = document.getElementById(errorId);
        if (errorEl) {
            errorEl.textContent = '';
        } else {
             // Fallback
            let errorMsg = element.parentNode.querySelector('.form-error-text');
            if (errorMsg) {
                errorMsg.textContent = '';
            }
        }
    }
    
    function validateStep(stepNumber) {
        let isValid = true;
        const stepElement = document.querySelector(`.wizard-form-step[data-step="${stepNumber}"]`);
        if (!stepElement) return false;

        // Clear all previous errors in this step
        stepElement.querySelectorAll('.form-error-text').forEach(el => el.textContent = '');
        stepElement.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        
        // 1. Check all required fields
        const requiredInputs = stepElement.querySelectorAll('[required]');
        requiredInputs.forEach(input => {
            if (input.offsetParent === null) return; // Skip hidden elements
            
            if (!input.value) {
                isValid = false;
                showError(input, 'This field is required.');
            } else {
                clearError(input);
            }
        });

        // 2. Check for specific step logic
        
        // Availability Step
        if (stepElement.querySelector('#availability-container')) {
            const availabilityRows = stepElement.querySelectorAll('.availability-row');
            let timeError = false;
            availabilityRows.forEach(row => {
                const start = row.querySelector('.availability-start');
                const end = row.querySelector('.availability-end');
                
                if (start.value && end.value && start.value >= end.value) {
                    isValid = false;
                    timeError = true;
                    showError(end, 'End time must be after start time.');
                }
            });
            if (timeError) {
                document.getElementById('error-availability').textContent = 'Please fix time errors.';
            }
        }
        
        // Subject Steps
        if (stepElement.querySelector('#subjects-container') && 
            stepElement.querySelectorAll('.subject-row').length === 0) {
            isValid = false;
            document.getElementById('error-subjects').textContent = 'Please add at least one subject.';
        }
        
        // Peer Subject Step
        if (stepElement.querySelector('#learning-subjects-container')) {
            if (stepElement.querySelectorAll('#learning-subjects-container .subject-row').length === 0) {
                isValid = false;
                document.getElementById('error-learning_subjects').textContent = 'Please add at least one learning subject.';
            }
            if (stepElement.querySelectorAll('#teaching-subjects-container .subject-row').length === 0) {
                isValid = false;
                document.getElementById('error-teaching_subjects').textContent = 'Please add at least one teaching subject.';
            }
        }

        return isValid;
    }
    
    // --- [NEW] AJAX Referral Code Check ---
    function checkReferralCode() {
        const codeInput = document.getElementById('referral_code');
        const code = codeInput.value;
        const statusDiv = document.getElementById('referral-status');
        const checkBtn = document.getElementById('check-referral-btn');
        
        if (!code) {
            statusDiv.textContent = 'Please enter a code to check.';
            statusDiv.style.color = '#dc2626';
            return;
        }

        checkBtn.disabled = true;
        checkBtn.textContent = 'Checking...';
        statusDiv.textContent = 'Checking...';
        statusDiv.style.color = '#64748b';

        const formData = new FormData();
        formData.append('action', 'check_referral');
        formData.append('code', code);

        fetch(window.location.href, { // Post to the same page
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            statusDiv.textContent = data.message;
            if (data.valid) {
                statusDiv.style.color = '#10b981'; // green-600
                codeInput.classList.remove('is-invalid');
                codeInput.classList.add('is-valid'); // You can add a .is-valid style if you want
            } else {
                statusDiv.style.color = '#dc2626'; // red-600
                codeInput.classList.add('is-invalid');
                codeInput.classList.remove('is-valid');
            }
        })
        .catch(error => {
            statusDiv.textContent = 'An error occurred. Please try again.';
            statusDiv.style.color = '#dc2626';
        })
        .finally(() => {
            checkBtn.disabled = false;
            checkBtn.textContent = 'Check';
        });
    }


    // --- [Original, but placed inside DOMContentLoaded] ---
    const subjectsHierarchy = <?php echo getSubjectsHierarchyJSON(); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        let currentStep = 1;
        const totalSteps = <?php echo $total_steps; ?>;
        const wizardSteps = document.querySelectorAll('.wizard-form-step');
        const stepIndicators = document.querySelectorAll('.wizard-step');
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');
        const submitBtn = document.getElementById('submit-btn');
        const form = document.getElementById('setup-form');
        const formContainer = document.querySelector('.form-container');

        function showStep(stepNumber) {
            wizardSteps.forEach(step => {
                if (parseInt(step.dataset.step) === stepNumber) {
                    step.classList.add('active');
                } else {
                    step.classList.remove('active');
                }
            });
            
            stepIndicators.forEach(indicator => {
                const indicatorStep = parseInt(indicator.dataset.step);
                if (indicatorStep === stepNumber) {
                    indicator.classList.add('active');
                    indicator.classList.remove('completed');
                } else if (indicatorStep < stepNumber) {
                    indicator.classList.add('completed');
                    indicator.classList.remove('active');
                } else {
                    indicator.classList.remove('active');
                    indicator.classList.remove('completed');
                }
            });

            if (stepNumber === 1) {
                prevBtn.style.display = 'none';
            } else {
                prevBtn.style.display = 'inline-block';
            }

            if (stepNumber === totalSteps) {
                nextBtn.style.display = 'none';
                submitBtn.style.display = 'inline-block';
            } else {
                nextBtn.style.display = 'inline-block';
                submitBtn.style.display = 'none';
            }
            
            if (formContainer) {
                formContainer.scrollTop = 0;
            }
            window.scrollTo(0, formContainer.offsetTop);
        }

        // --- [IMPROVED] Button Listeners ---
        nextBtn.addEventListener('click', function() {
            // [NEW] Validate before proceeding
            if (validateStep(currentStep)) {
                if (currentStep < totalSteps) {
                    currentStep++;
                    showStep(currentStep);
                }
            }
        });

        prevBtn.addEventListener('click', function() {
            if (currentStep > 1) {
                currentStep--;
                showStep(currentStep);
            }
        });

        submitBtn.addEventListener('click', function(e) {
            // [NEW] Validate final step before submitting
            if (!validateStep(currentStep)) {
                e.preventDefault();
                alert('Please fix the errors on this page before submitting.');
            }
        });
        
        // --- [NEW] Consolidated Event Listeners ---
        document.addEventListener('click', function(e) {
            // Add Availability
            if (e.target.id === 'add-availability-btn') {
                addAvailability();
            }
            // Remove Availability
            if (e.target.classList.contains('remove-availability-btn')) {
                removeAvailability(e.target);
            }
            // Add Subject (Student/Mentor)
            if (e.target.id === 'add-subject-btn') {
                addSubject('general');
            }
            // Add Learning Subject (Peer)
            if (e.target.id === 'add-learning-subject-btn') {
                addSubject('learning');
            }
            // Add Teaching Subject (Peer)
            if (e.target.id === 'add-teaching-subject-btn') {
                addSubject('teaching');
            }
            // Remove Subject
            if (e.target.classList.contains('remove-subject-btn')) {
                removeSubject(e.target);
            }
            // Check Referral Code
            if (e.target.id === 'check-referral-btn') {
                checkReferralCode();
            }
            // Detect Location
            if (e.target.id === 'detect-location') {
                detectLocation(e.target);
            }
        });

        // --- [NEW] Delegated Change Listeners ---
        document.addEventListener('change', function(e) {
            // Subject dropdowns
            if (e.target.classList.contains('main-subject-select')) {
                updateSubtopics(e.target);
            } else if (e.target.classList.contains('subtopic-select')) {
                updateProficiencyLevels(e.target);
            }
            // Availability dropdowns/inputs
            else if (e.target.classList.contains('availability-day') || 
                     e.target.classList.contains('availability-start') || 
                     e.target.classList.contains('availability-end')) {
                const row = e.target.closest('.availability-row');
                if (row) {
                    updateAvailabilityCombined(row);
                }
            }
            // Grade level dependency
            else if (e.target.id === 'grade_level') {
                handleGradeLevelChange(e.target);
            }
            // Profile pic preview
            else if (e.target.id === 'profile_picture') {
                handleProfilePicChange(e);
            }
        });

        // --- Initializers ---
        
        // Initial setup
        showStep(currentStep);
        
        // Trigger grade level handler on load
        const gradeLevelSelect = document.getElementById('grade_level');
        if (gradeLevelSelect && gradeLevelSelect.value) {
            handleGradeLevelChange(gradeLevelSelect);
        }
        
        // Attach listeners to any pre-existing availability rows
        const availabilityRows = document.querySelectorAll('.availability-row');
        availabilityRows.forEach(row => {
            // Listeners are now delegated, but we need to set initial combined value if not set
            if (!row.querySelector('.availability-combined').value) {
                updateAvailabilityCombined(row);
            }
        });
    });
    
    
    // --- [REFACTORED] Form Logic Functions ---

    function handleGradeLevelChange(selectElement) {
        const gradeLevel = selectElement.value;
        const strandField = document.getElementById('strand');
        const courseField = document.getElementById('course');
        
        const disableField = (field) => {
            field.disabled = true;
            field.value = '';
            field.style.backgroundColor = '#f1f5f9';
            field.style.cursor = 'not-allowed';
        };
        
        const enableField = (field) => {
            field.disabled = false;
            field.style.backgroundColor = '';
            field.style.cursor = '';
        };

        if (gradeLevel >= 'Grade 7' && gradeLevel <= 'Grade 10') {
            disableField(strandField);
            disableField(courseField);
        } else if (gradeLevel === 'Grade 11' || gradeLevel === 'Grade 12') {
            enableField(strandField);
            disableField(courseField);
        } else if (gradeLevel.includes('College')) {
            enableField(courseField);
            disableField(strandField);
        } else {
            enableField(strandField);
            enableField(courseField);
        }
    }

    function handleProfilePicChange(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('image-preview');
                const previewImg = document.getElementById('preview-img');
                previewImg.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    }

    function detectLocation(button) {
        const statusDiv = document.getElementById('location-status');
        
        if (!navigator.geolocation) {
            statusDiv.textContent = 'Geolocation is not supported by this browser.';
            statusDiv.style.display = 'block';
            statusDiv.style.color = '#dc2626';
            return;
        }
        
        button.disabled = true;
        button.textContent = '📍 Getting location...';
        statusDiv.style.display = 'block';
        statusDiv.textContent = 'Getting your location...';
        statusDiv.style.color = '#3b82f6';
        
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const accuracy = position.coords.accuracy;
                
                document.getElementById('latitude').value = lat;
                document.getElementById('longitude').value = lng;
                document.getElementById('location_accuracy').value = accuracy;
                
                reverseGeocode(lat, lng);
                
                button.disabled = false;
                button.textContent = '✓ Location detected';
                button.style.backgroundColor = '#10b981';
                button.style.color = 'white';
                statusDiv.textContent = `Location detected with ${Math.round(accuracy)}m accuracy`;
                statusDiv.style.color = '#10b981';
            },
            function(error) {
                let errorMessage = 'Unable to get your location. ';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        errorMessage += 'Please allow location access and try again.';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errorMessage += 'Location information is unavailable.';
                        break;
                    case error.TIMEOUT:
                        errorMessage += 'Location request timed out.';
                        break;
                    default:
                        errorMessage += 'An unknown error occurred.';
                        break;
                }
                
                button.disabled = false;
                button.textContent = '📍 Try Again';
                statusDiv.textContent = errorMessage;
                statusDiv.style.color = '#dc2626';
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }
    
    function reverseGeocode(lat, lng) {
        fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lng}&localityLanguage=en`)
            .then(response => response.json())
            .then(data => {
                let locationText = '';
                if (data.city && data.principalSubdivision) {
                    locationText = `${data.city}, ${data.principalSubdivision}`;
                } else if (data.principalSubdivision) {
                    locationText = data.principalSubdivision;
                } else {
                    locationText = `Lat: ${lat.toFixed(4)}, Lng: ${lng.toFixed(4)}`;
                }
                document.getElementById('location').value = locationText;
                clearError(document.getElementById('location')); // Clear validation error
                const statusDiv = document.getElementById('location-status');
                statusDiv.textContent = `Location set to: ${locationText}`;
            })
            .catch(error => {
                // Don't show error to user, coordinates are still captured
            });
    }

    function addSubject(type = 'general') {
        let container, selectName, isLearning;
        
        if (type === 'learning') {
            container = document.getElementById('learning-subjects-container');
            selectName = 'learning_subjects[]';
            isLearning = true;
        } else if (type === 'teaching') {
            container = document.getElementById('teaching-subjects-container');
            selectName = 'teaching_subjects[]';
            isLearning = false;
        } else {
            container = document.getElementById('subjects-container');
            selectName = 'subjects[]';
            isLearning = <?php echo $user['role'] === 'student' ? 'true' : 'false'; ?>;
        }
        
        const subjectRow = document.createElement('div');
        subjectRow.className = 'subject-row';
        subjectRow.style.cssText = 'display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center; flex-wrap: wrap;';
        
        let mainSubjectOptions = '<option value="">Select Main Subject</option>';
        Object.keys(subjectsHierarchy).forEach(subject => {
            mainSubjectOptions += `<option value="${subject}">${subject}</option>`;
        });
        
        subjectRow.innerHTML = `
            <div style="display: flex; gap: 0.5rem; flex: 2; flex-wrap: wrap;">
                <select class="main-subject-select form-select" style="flex: 1; min-width: 150px;" required>
                    ${mainSubjectOptions}
                </select>
                <select class="subtopic-select form-select" style="flex: 1; min-width: 150px;" disabled>
                    <option value="">Select Subtopic First</option>
                </select>
                <select name="${selectName}" class="proficiency-select form-select" style="flex: 1; min-width: 120px;" data-is-learning="${isLearning}" disabled required>
                    <option value="">Select Level</option>
                </select>
            </div>
            <button type="button" class="btn btn-danger remove-subject-btn" style="padding: 0.5rem;">Remove</button>
        `;
        
        container.appendChild(subjectRow);
    }
    
    function updateSubtopics(mainSubjectSelect) {
        const subjectRow = mainSubjectSelect.closest('.subject-row');
        const subtopicSelect = subjectRow.querySelector('.subtopic-select');
        const proficiencySelect = subjectRow.querySelector('.proficiency-select');
        const mainSubject = mainSubjectSelect.value;
        
        subtopicSelect.innerHTML = '<option value="">Select Subtopic</option>';
        proficiencySelect.innerHTML = '<option value="">Select Level</option>';
        subtopicSelect.disabled = !mainSubject;
        proficiencySelect.disabled = true;
        
        if (mainSubject && subjectsHierarchy[mainSubject]) {
            subjectsHierarchy[mainSubject].forEach(subtopic => {
                subtopicSelect.innerHTML += `<option value="${subtopic}">${subtopic}</option>`;
            });
            subtopicSelect.disabled = false;
        }
        clearError(mainSubjectSelect);
        clearError(subtopicSelect);
        clearError(proficiencySelect);
    }
    
    function updateProficiencyLevels(subtopicSelect) {
        const subjectRow = subtopicSelect.closest('.subject-row');
        const proficiencySelect = subjectRow.querySelector('.proficiency-select');
        const mainSubjectSelect = subjectRow.querySelector('.main-subject-select');
        const subtopic = subtopicSelect.value;
        const mainSubject = mainSubjectSelect.value;
        
        proficiencySelect.innerHTML = '<option value="">Select Level</option>';
        
        if (subtopic && mainSubject) {
            // Check if this is a learning or teaching dropdown
            const isLearning = proficiencySelect.dataset.isLearning === 'true';
            
            if (isLearning) {
                proficiencySelect.innerHTML += `<option value="${mainSubject}|${subtopic}|beginner">${subtopic} - Beginner</option>`;
                proficiencySelect.innerHTML += `<option value="${mainSubject}|${subtopic}|intermediate">${subtopic} - Intermediate</option>`;
                proficiencySelect.innerHTML += `<option value="${mainSubject}|${subtopic}|advanced">${subtopic} - Advanced</option>`;
            } else { // Mentor or Peer teaching
                proficiencySelect.innerHTML += `<option value="${mainSubject}|${subtopic}|intermediate">${subtopic} - Intermediate</option>`;
                proficiencySelect.innerHTML += `<option value="${mainSubject}|${subtopic}|advanced">${subtopic} - Advanced</option>`;
                proficiencySelect.innerHTML += `<option value="${mainSubject}|${subtopic}|expert">${subtopic} - Expert</option>`;
            }
            
            proficiencySelect.disabled = false;
        }
        clearError(subtopicSelect);
        clearError(proficiencySelect);
    }
    
    function removeSubject(button) {
        const subjectRow = button.closest('.subject-row');
        const container = subjectRow.parentElement;
        
        // Only remove if it's not the last one
        if (container.querySelectorAll('.subject-row').length > 1) {
            subjectRow.remove();
        }
    }

    function addAvailability() {
        const container = document.getElementById('availability-container');
        const availabilityRow = document.createElement('div');
        availabilityRow.className = 'availability-row';
        availabilityRow.style.cssText = 'display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center;';
        
        availabilityRow.innerHTML = `
            <select class="availability-day form-select" style="flex: 1;" required>
                <option value="">Select Day</option>
                <option value="monday">Monday</option>
                <option value="tuesday">Tuesday</option>
                <option value="wednesday">Wednesday</option>
                <option value="thursday">Thursday</option>
                <option value="friday">Friday</option>
                <option value="saturday">Saturday</option>
                <option value="sunday">Sunday</option>
            </select>
            <input type="time" class="availability-start form-input" style="flex: 1;" required>
            <input type="time" class="availability-end form-input" style="flex: 1;" required>
            <input type="hidden" name="availability[]" class="availability-combined" value="">
            <button type="button" class="btn btn-danger remove-availability-btn" style="padding: 0.5rem;">Remove</button>
        `;
        container.appendChild(availabilityRow);
    }

    function removeAvailability(button) {
        const availabilityRow = button.closest('.availability-row');
        const container = availabilityRow.parentElement;
        
        if (container.querySelectorAll('.availability-row').length > 1) {
            availabilityRow.remove();
        }
    }
    
    function updateAvailabilityCombined(row) {
        const day = row.querySelector('.availability-day').value;
        const start = row.querySelector('.availability-start').value;
        const end = row.querySelector('.availability-end').value;
        const combined = row.querySelector('.availability-combined');
        
        // Clear errors as user types
        clearError(row.querySelector('.availability-day'));
        clearError(row.querySelector('.availability-start'));
        clearError(row.querySelector('.availability-end'));
        
        if (day && start && end) {
            if (end <= start) {
                showError(row.querySelector('.availability-end'), 'End time must be after start.');
                combined.value = ''; // Invalid
            } else {
                combined.value = `${day}|${start}|${end}`;
            }
        } else {
            combined.value = '';
        }
    }

    </script>
</body>
</html>