<?php
require_once '../config/config.php';
require_once '../includes/subjects_hierarchy.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user) {
    redirect('auth/login.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
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
        $referral = null; // Initialize $referral to null
        if (($role === 'mentor' || $role === 'peer') && !empty($_POST['referral_code'])) {
            $referral_code = sanitize_input($_POST['referral_code']);
            
            // Validate referral code
            $db = getDB(); // Ensure $db is available here
            $ref_stmt = $db->prepare("SELECT id, created_by, max_uses, current_uses FROM referral_codes WHERE code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())");
            $ref_stmt->execute([$referral_code]);
            $referral = $ref_stmt->fetch();
            
            if (!$referral || $referral['current_uses'] >= $referral['max_uses']) {
                $error = 'Invalid or expired referral code.';
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
        }
        
        $learning_subjects = [];
        $teaching_subjects = [];
        
        if ($role === 'peer') {
            $learning_subjects = $_POST['learning_subjects'] ?? [];
            $teaching_subjects = $_POST['teaching_subjects'] ?? [];
            $subjects = array_merge($learning_subjects, $teaching_subjects);
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
                $error = 'Please upload a valid image file (JPG, PNG, or GIF).';
            } elseif ($file['size'] > $max_size) {
                $error = 'Image file size must be less than 5MB.';
            } else {
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'profile_' . $user['id'] . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $profile_picture_path = 'uploads/profiles/' . $filename;
                } else {
                    $error = 'Failed to upload profile picture. Please try again.';
                }
            }
        }
        
        if (empty($error)) {
            if ($role === 'student') {
                if (empty($grade_level) || empty($location) || empty($bio) || empty($learning_goals)) {
                    $error = 'Please fill in all required fields.';
                }
            } elseif ($role === 'mentor') {
                if (empty($location) || empty($bio) || empty($teaching_style)) {
                    $error = 'Please fill in all required fields.';
                }
            } elseif ($role === 'peer') {
                if (empty($grade_level) || empty($location) || empty($bio) || empty($learning_goals) || empty($teaching_style)) {
                    $error = 'Please fill in all required fields for both learning and teaching.';
                }
            }
            
            if (empty($subjects)) {
                $error = 'Please select at least one subject.';
            }
            
            if (empty($error)) {
                try {
                    $db = getDB();
                    $db->beginTransaction();
                    
                    if (($role === 'mentor' || $role === 'peer') && !empty($referral_code) && $referral) {
                        // Update referral code usage
                        $update_ref = $db->prepare("UPDATE referral_codes SET current_uses = current_uses + 1 WHERE id = ?");
                        $update_ref->execute([$referral['id']]);
                        
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
                            $stmt = $db->prepare("UPDATE users SET location = ?, latitude = ?, longitude = ?, location_accuracy = ?, bio = ?, teaching_style = ?, profile_picture = ? WHERE id = ?");
                            $stmt->execute([$location, $latitude, $longitude, $location_accuracy, $bio, $teaching_style, $profile_picture_path, $user['id']]);
                        } else {
                            $stmt = $db->prepare("UPDATE users SET location = ?, latitude = ?, longitude = ?, location_accuracy = ?, bio = ?, teaching_style = ? WHERE id = ?");
                            $stmt->execute([$location, $latitude, $longitude, $location_accuracy, $bio, $teaching_style, $user['id']]);
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
                            $stmt = $db->prepare("UPDATE users SET grade_level = ?, strand = ?, course = ?, location = ?, latitude = ?, longitude = ?, location_accuracy = ?, bio = ?, learning_goals = ?, preferred_learning_style = ?, teaching_style = ?, profile_picture = ? WHERE id = ?");
                            $stmt->execute([$grade_level, $strand, $course, $location, $latitude, $longitude, $location_accuracy, $bio, $learning_goals, $preferred_learning_style, $teaching_style, $profile_picture_path, $user['id']]);
                        } else {
                            $stmt = $db->prepare("UPDATE users SET grade_level = ?, strand = ?, course = ?, location = ?, latitude = ?, longitude = ?, location_accuracy = ?, bio = ?, learning_goals = ?, preferred_learning_style = ?, teaching_style = ? WHERE id = ?");
                            $stmt->execute([$grade_level, $strand, $course, $location, $latitude, $longitude, $location_accuracy, $bio, $learning_goals, $preferred_learning_style, $teaching_style, $user['id']]);
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
                            
                            // Removed console.log as it's not standard PHP
                            
                            $subject_stmt->execute([$user['id'], $subtopic, $proficiency_level, $main_subject, $subtopic]);
                        } elseif (count($subject_parts) === 2) {
                            $subject_name = $subject_parts[0];
                            $proficiency_level = $subject_parts[1];
                            
                            // Removed console.log as it's not standard PHP
                            
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
                    $error = 'Failed to update profile. Please try again.';
                }
            }
        }
    }
}

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

// Common subjects list - keeping for backward compatibility
$common_subjects = getMainSubjects();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Setup - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="../dashboard.php" class="logo">StudyConnect</a>
                <ul class="nav-links">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <li><a href="../auth/logout.php" class="btn btn-outline">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="container">
            <div class="form-container" style="max-width: 600px;">
                 Added peer role welcome message and hybrid setup 
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
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                     Profile picture upload section 
                    <div class="form-group">
                        <label for="profile_picture" class="form-label">Profile Picture (Optional)</label>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div id="current-picture" style="width: 80px; height: 80px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold; color: #64748b;">
                                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                            </div>
                            <div style="flex: 1;">
                                <input type="file" id="profile_picture" name="profile_picture" class="form-input" accept="image/*">
                                <p class="text-sm text-secondary mt-1">Upload a profile picture (JPG, PNG, or GIF, max 5MB)</p>
                            </div>
                        </div>
                        <div id="image-preview" style="margin-top: 1rem; display: none;">
                             Fixed placeholder.svg 404 error by removing src initially 
                            <img id="preview-img" src="/placeholder.svg" alt="Preview" style="width: 150px; height: 150px; object-fit: cover; border-radius: 8px; border: 2px solid #e2e8f0;">
                        </div>
                    </div>
                    
                     Added referral code input for both mentors and peers 
                    <?php if ($user['role'] === 'mentor' || $user['role'] === 'peer'): ?>
                        <div class="form-group">
                            <label for="referral_code" class="form-label">Referral Code (Optional)</label>
                            <input type="text" id="referral_code" name="referral_code" class="form-input" 
                                   placeholder="Enter referral code if you have one"
                                   value="<?php echo htmlspecialchars($_POST['referral_code'] ?? ''); ?>">
                            <p class="text-sm text-secondary mt-1">Using a referral code verifies your account and may unlock benefits.</p>
                        </div>
                    <?php endif; ?>
                    
                     Added peer-specific form sections 
                    <?php if ($user['role'] === 'student' || $user['role'] === 'peer'): ?>
                         Student/Learning fields 
                        <div class="form-group">
                            <label for="grade_level" class="form-label">Grade Level / Year Level</label>
                            <select id="grade_level" name="grade_level" class="form-select" required>
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
                            <textarea id="learning_goals" name="learning_goals" class="form-input" rows="3" required
                                      placeholder="What do you want to achieve? What challenges are you facing?"><?php echo htmlspecialchars($user['learning_goals'] ?? ''); ?></textarea>
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
                    <?php endif; ?>
                    
                     Updated subjects section for peer role 
                    <?php if ($user['role'] === 'peer'): ?>
                         Peer gets both learning and teaching subjects 
                        <div class="form-group">
                            <label class="form-label">Subjects you want to learn</label>
                            <p class="text-sm text-secondary mb-3">Start by selecting a main subject (e.g., Mathematics). Once you choose, the system will automatically display related subtopics (e.g., Algebra, Calculus, Geometry) for you to refine your expertise or learning preference.</p>
                            <div class="example-hint" style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid var(--primary-color);">
                                <strong>👉 Example:</strong><br>
                                <span style="color: #64748b;">Main Subject Dropdown:</span> <strong>Mathematics</strong><br>
                                <span style="color: #64748b;">Subtopic Dropdown (auto-loaded):</span> <strong>Algebra, Calculus, Geometry</strong>
                            </div>
                            <div id="learning-subjects-container">
                                <!-- Updated subject row structure for cascading dropdowns -->
                                <div class="subject-row" style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center; flex-wrap: wrap;">
                                    <div style="display: flex; gap: 0.5rem; flex: 2; flex-wrap: wrap;">
                                        <select class="main-subject-select form-select" style="flex: 1; min-width: 150px;" onchange="updateSubtopics(this)" required>
                                            <option value="">Select Main Subject</option>
                                            <?php foreach (getMainSubjects() as $subject): ?>
                                                <option value="<?php echo htmlspecialchars($subject); ?>"><?php echo htmlspecialchars($subject); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select class="subtopic-select form-select" style="flex: 1; min-width: 150px;" onchange="updateProficiencyLevels(this)" disabled>
                                            <option value="">Select Subtopic First</option>
                                        </select>
                                        <select name="learning_subjects[]" class="proficiency-select form-select" style="flex: 1; min-width: 120px;" disabled required>
                                            <option value="">Select Level</option>
                                        </select>
                                    </div>
                                    <button type="button" class="btn btn-danger" onclick="removeSubject(this, 'learning')" style="padding: 0.5rem;">Remove</button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary" onclick="addSubject('learning')">Add Learning Subject</button>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Subjects you can teach</label>
                            <p class="text-sm text-secondary mb-3">Start by selecting a main subject (e.g., Mathematics). Once you choose, the system will automatically display related subtopics (e.g., Algebra, Calculus, Geometry) for you to refine your expertise or learning preference.</p>
                            <div id="teaching-subjects-container">
                                <!-- Updated subject row structure for cascading dropdowns -->
                                <div class="subject-row" style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center; flex-wrap: wrap;">
                                    <div style="display: flex; gap: 0.5rem; flex: 2; flex-wrap: wrap;">
                                        <select class="main-subject-select form-select" style="flex: 1; min-width: 150px;" onchange="updateSubtopics(this)" required>
                                            <option value="">Select Main Subject</option>
                                            <?php foreach (getMainSubjects() as $subject): ?>
                                                <option value="<?php echo htmlspecialchars($subject); ?>"><?php echo htmlspecialchars($subject); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select class="subtopic-select form-select" style="flex: 1; min-width: 150px;" onchange="updateProficiencyLevels(this)" disabled>
                                            <option value="">Select Subtopic First</option>
                                        </select>
                                        <select name="teaching_subjects[]" class="proficiency-select form-select" style="flex: 1; min-width: 120px;" disabled required>
                                            <option value="">Select Level</option>
                                        </select>
                                    </div>
                                    <button type="button" class="btn btn-danger" onclick="removeSubject(this, 'teaching')" style="padding: 0.5rem;">Remove</button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary" onclick="addSubject('teaching')">Add Teaching Subject</button>
                        </div>
                    <?php elseif ($user['role'] === 'student'): ?>
                        <div class="form-group">
                            <label class="form-label">Subjects you want to learn</label>
                            <p class="text-sm text-secondary mb-3">Start by selecting a main subject (e.g., Mathematics). Once you choose, the system will automatically display related subtopics (e.g., Algebra, Calculus, Geometry) for you to refine your expertise or learning preference.</p>
                            <div class="example-hint" style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid var(--primary-color);">
                                <strong>👉 Example:</strong><br>
                                <span style="color: #64748b;">Main Subject Dropdown:</span> <strong>Mathematics</strong><br>
                                <span style="color: #64748b;">Subtopic Dropdown (auto-loaded):</span> <strong>Algebra, Calculus, Geometry</strong>
                            </div>
                    <?php else: // Mentor role ?>
                        <div class="form-group">
                            <label class="form-label">Subjects you can teach</label>
                            <p class="text-sm text-secondary mb-3">Start by selecting a main subject (e.g., Mathematics). Once you choose, the system will automatically display related subtopics (e.g., Algebra, Calculus, Geometry) for you to refine your expertise or learning preference.</p>
                            <div class="example-hint" style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid var(--primary-color);">
                                <strong>👉 Example:</strong><br>
                                <span style="color: #64748b;">Main Subject Dropdown:</span> <strong>Mathematics</strong><br>
                                <span style="color: #64748b;">Subtopic Dropdown (auto-loaded):</span> <strong>Algebra, Calculus, Geometry</strong>
                            </div>
                    <?php endif; ?>
                    
                    <!-- Common subjects section -->
                    <?php if ($user['role'] !== 'peer'): ?>
                        <div id="subjects-container">
                            <?php if (!empty($existing_subjects)): ?>
                                <?php foreach ($existing_subjects as $subject): ?>
                                    <!-- Fixed existing subject display for cascading dropdowns -->
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
                                            <select class="main-subject-select form-select" style="flex: 1; min-width: 150px;" onchange="updateSubtopics(this)" required>
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
                                        <button type="button" class="btn btn-danger" onclick="removeSubject(this)" style="padding: 0.5rem;">Remove</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- Fixed default subject row for cascading dropdowns -->
                                <div class="subject-row" style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center; flex-wrap: wrap;">
                                    <div style="display: flex; gap: 0.5rem; flex: 2; flex-wrap: wrap;">
                                        <select class="main-subject-select form-select" style="flex: 1; min-width: 150px;" onchange="updateSubtopics(this)" required>
                                            <option value="">Select Main Subject</option>
                                            <?php foreach (getMainSubjects() as $subject): ?>
                                                <option value="<?php echo htmlspecialchars($subject); ?>"><?php echo htmlspecialchars($subject); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select class="subtopic-select form-select" style="flex: 1; min-width: 150px;" onchange="updateProficiencyLevels(this)" disabled>
                                            <option value="">Select Subtopic First</option>
                                        </select>
                                        <select name="subjects[]" class="proficiency-select form-select" style="flex: 1; min-width: 120px;" disabled required>
                                            <option value="">Select Level</option>
                                        </select>
                                    </div>
                                    <button type="button" class="btn btn-danger" onclick="removeSubject(this)" style="padding: 0.5rem;">Remove</button>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <button type="button" class="btn btn-secondary" onclick="addSubject()">Add Another Subject</button>
                    <?php endif; ?>
                </div>
                
                 Added mentor/peer availability and teaching style sections 
                <?php if ($user['role'] === 'mentor' || $user['role'] === 'peer'): ?>
                    <div class="form-group">
                        <label class="form-label">Availability (days & times)</label>
                        <p class="text-sm text-secondary mb-3">Set your available days and time slots for tutoring sessions.</p>
                        
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
                                        <button type="button" class="btn btn-danger" onclick="removeAvailability(this)" style="padding: 0.5rem;">Remove</button>
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
                                    <button type="button" class="btn btn-danger" onclick="removeAvailability(this)" style="padding: 0.5rem;">Remove</button>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <button type="button" class="btn btn-secondary" onclick="addAvailability()">Add Time Slot</button>
                    </div>
                    
                    <div class="form-group">
                        <label for="teaching_style" class="form-label">
                            <?php echo $user['role'] === 'peer' ? 'Teaching approach / mentoring style' : 'Short bio / teaching style'; ?>
                        </label>
                        <textarea id="teaching_style" name="teaching_style" class="form-input" rows="4" required
                                  placeholder="Describe your teaching approach, experience, and what makes you a great mentor..."><?php echo htmlspecialchars($user['teaching_style'] ?? ''); ?></textarea>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="location" class="form-label">Location</label>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="text" id="location" name="location" class="form-input" required
                               placeholder="Start typing your location..."
                               style="flex: 1;"
                               value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>">
                        <button type="button" id="detect-location" class="btn btn-secondary" 
                                style="white-space: nowrap; padding: 0.75rem 1rem;">
                            📍 Use My Location
                        </button>
                    </div>
                    <!-- Added hidden fields for coordinates -->
                    <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($user['latitude'] ?? ''); ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($user['longitude'] ?? ''); ?>">
                    <input type="hidden" id="location_accuracy" name="location_accuracy" value="<?php echo htmlspecialchars($user['location_accuracy'] ?? ''); ?>">
                    <div id="location-status" class="text-sm text-secondary mt-2" style="display: none;"></div>
                </div>
                
                <div class="form-group">
                    <label for="bio" class="form-label">Bio</label>
                    <textarea id="bio" name="bio" class="form-input" rows="4" required
                              placeholder="Tell others about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Profile</button>
            </form>
        </div>
    </div>
</main>

<script>
    const subjectsHierarchy = <?php echo getSubjectsHierarchyJSON(); ?>;
    
    document.addEventListener('DOMContentLoaded', function() {
        // Grade level change handler
        const gradeLevelSelect = document.getElementById('grade_level');
        if (gradeLevelSelect) {
            gradeLevelSelect.addEventListener('change', function() {
                const gradeLevel = this.value;
                const strandField = document.getElementById('strand');
                const courseField = document.getElementById('course');
                
                // Check if Grade 7-10 (Junior High School)
                if (gradeLevel >= 'Grade 7' && gradeLevel <= 'Grade 10') {
                    // Disable both strand and course
                    strandField.disabled = true;
                    strandField.value = '';
                    strandField.style.backgroundColor = '#f1f5f9';
                    strandField.style.cursor = 'not-allowed';
                    
                    courseField.disabled = true;
                    courseField.value = '';
                    courseField.style.backgroundColor = '#f1f5f9';
                    courseField.style.cursor = 'not-allowed';
                }
                // Check if Grade 11 or 12 (Senior High School)
                else if (gradeLevel === 'Grade 11' || gradeLevel === 'Grade 12') {
                    // Enable strand, disable course
                    strandField.disabled = false;
                    strandField.style.backgroundColor = '';
                    strandField.style.cursor = '';
                    
                    courseField.disabled = true;
                    courseField.value = '';
                    courseField.style.backgroundColor = '#f1f5f9';
                    courseField.style.cursor = 'not-allowed';
                }
                // Check if College (1st-4th year)
                else if (gradeLevel.includes('College')) {
                    // Enable course, disable strand
                    courseField.disabled = false;
                    courseField.style.backgroundColor = '';
                    courseField.style.cursor = '';
                    
                    strandField.disabled = true;
                    strandField.value = '';
                    strandField.style.backgroundColor = '#f1f5f9';
                    strandField.style.cursor = 'not-allowed';
                }
                // For any other case, enable both
                else {
                    strandField.disabled = false;
                    strandField.style.backgroundColor = '';
                    strandField.style.cursor = '';
                    
                    courseField.disabled = false;
                    courseField.style.backgroundColor = '';
                    courseField.style.cursor = '';
                }
            });
            
            // Trigger on page load to set initial state
            if (gradeLevelSelect.value) {
                gradeLevelSelect.dispatchEvent(new Event('change'));
            }
        }
        
        // Profile picture preview handler
        const profilePictureInput = document.getElementById('profile_picture');
        if (profilePictureInput) {
            profilePictureInput.addEventListener('change', function(e) {
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
            });
        }
        
        const detectLocationBtn = document.getElementById('detect-location');
        if (detectLocationBtn) {
            detectLocationBtn.addEventListener('click', function() {
                const button = this;
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
                        
                        // Reverse geocode to get readable address
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
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            });
        }
        
        const availabilityRows = document.querySelectorAll('.availability-row');
        availabilityRows.forEach(row => {
            attachAvailabilityListeners(row);
        });
    });

    function addSubject(type = 'general') {
        let container, isStudent, isPeer;
        
        if (type === 'learning') {
            container = document.getElementById('learning-subjects-container');
            isStudent = true;
            isPeer = true;
        } else if (type === 'teaching') {
            container = document.getElementById('teaching-subjects-container');
            isStudent = false;
            isPeer = true;
        } else {
            container = document.getElementById('subjects-container');
            isStudent = <?php echo $user['role'] === 'student' ? 'true' : 'false'; ?>;
            isPeer = <?php echo $user['role'] === 'peer' ? 'true' : 'false'; ?>;
        }
        
        const subjectRow = document.createElement('div');
        subjectRow.className = 'subject-row';
        subjectRow.style.cssText = 'display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center; flex-wrap: wrap;';
        
        let selectName = isPeer ? (type === 'learning' ? 'learning_subjects[]' : 'teaching_subjects[]') : 'subjects[]';
        
        // Create main subject dropdown
        let mainSubjectOptions = '<option value="">Select Main Subject</option>';
        Object.keys(subjectsHierarchy).forEach(subject => {
            mainSubjectOptions += `<option value="${subject}">${subject}</option>`;
        });
        
        subjectRow.innerHTML = `
            <div style="display: flex; gap: 0.5rem; flex: 2; flex-wrap: wrap;">
                <select class="main-subject-select form-select" style="flex: 1; min-width: 150px;" onchange="updateSubtopics(this)" required>
                    ${mainSubjectOptions}
                </select>
                <select class="subtopic-select form-select" style="flex: 1; min-width: 150px;" onchange="updateProficiencyLevels(this)" disabled>
                    <option value="">Select Subtopic First</option>
                </select>
                <select name="${selectName}" class="proficiency-select form-select" style="flex: 1; min-width: 120px;" disabled required>
                    <option value="">Select Level</option>
                </select>
            </div>
            <button type="button" class="btn btn-danger" onclick="removeSubject(this, '${type}')" style="padding: 0.5rem;">Remove</button>
        `;
        
        container.appendChild(subjectRow);
    }
    
    function updateSubtopics(mainSubjectSelect) {
        const subjectRow = mainSubjectSelect.closest('.subject-row');
        const subtopicSelect = subjectRow.querySelector('.subtopic-select');
        const proficiencySelect = subjectRow.querySelector('.proficiency-select');
        const mainSubject = mainSubjectSelect.value;
        
        // Reset dependent dropdowns
        subtopicSelect.innerHTML = '<option value="">Select Subtopic</option>';
        proficiencySelect.innerHTML = '<option value="">Select Level</option>';
        subtopicSelect.disabled = !mainSubject;
        proficiencySelect.disabled = true;
        
        if (mainSubject && subjectsHierarchy[mainSubject]) {
            // Populate subtopics
            subjectsHierarchy[mainSubject].forEach(subtopic => {
                subtopicSelect.innerHTML += `<option value="${subtopic}">${subtopic}</option>`;
            });
            subtopicSelect.disabled = false;
        }
    }
    
    function updateProficiencyLevels(subtopicSelect) {
        const subjectRow = subtopicSelect.closest('.subject-row');
        const proficiencySelect = subjectRow.querySelector('.proficiency-select');
        const mainSubjectSelect = subjectRow.querySelector('.main-subject-select');
        const subtopic = subtopicSelect.value;
        const mainSubject = mainSubjectSelect.value;
        
        proficiencySelect.innerHTML = '<option value="">Select Level</option>';
        
        if (subtopic && mainSubject) {
            const isStudent = <?php echo $user['role'] === 'student' ? 'true' : 'false'; ?>;
            const isLearningSubject = subjectRow.closest('#learning-subjects-container') !== null;
            
            // Determine proficiency levels based on role and context
            if (isStudent || isLearningSubject) {
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
    }
    
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('main-subject-select')) {
            updateSubtopics(e.target);
        } else if (e.target.classList.contains('subtopic-select')) {
            updateProficiencyLevels(e.target);
        }
    });
    
    function removeSubject(button, type = 'general') {
        let container;
        
        if (type === 'learning') {
            container = document.getElementById('learning-subjects-container');
        } else if (type === 'teaching') {
            container = document.getElementById('teaching-subjects-container');
        } else {
            container = document.getElementById('subjects-container');
        }
        
        if (container && container.children.length > 1) {
            button.parentElement.remove();
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
            <button type="button" class="btn btn-danger" onclick="removeAvailability(this)" style="padding: 0.5rem;">Remove</button>
        `;
        container.appendChild(availabilityRow);
        
        attachAvailabilityListeners(availabilityRow);
    }

    function removeAvailability(button) {
        const container = document.getElementById('availability-container');
        if (container && container.children.length > 1) {
            button.parentElement.remove();
        }
    }
    
    function updateAvailabilityCombined(row) {
        const day = row.querySelector('.availability-day').value;
        const start = row.querySelector('.availability-start').value;
        const end = row.querySelector('.availability-end').value;
        const combined = row.querySelector('.availability-combined');
        
        if (day && start && end) {
            combined.value = `${day}|${start}|${end}`;
        } else {
            combined.value = '';
        }
    }
    
    function attachAvailabilityListeners(row) {
        const daySelect = row.querySelector('.availability-day');
        const startInput = row.querySelector('.availability-start');
        const endInput = row.querySelector('.availability-end');
        
        daySelect.addEventListener('change', () => updateAvailabilityCombined(row));
        startInput.addEventListener('change', () => updateAvailabilityCombined(row));
        endInput.addEventListener('change', () => updateAvailabilityCombined(row));
    }

    function reverseGeocode(lat, lng) {
        // Using a simple reverse geocoding approach
        fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lng}&localityLanguage=en`)
            .then(response => response.json())
            .then(data => {
                if (data.city && data.principalSubdivision) {
                    const locationText = `${data.city}, ${data.principalSubdivision}`;
                    document.getElementById('location').value = locationText;
                    
                    const statusDiv = document.getElementById('location-status');
                    statusDiv.textContent = `Location set to: ${locationText}`;
                } else if (data.principalSubdivision) {
                    document.getElementById('location').value = data.principalSubdivision;
                    const statusDiv = document.getElementById('location-status');
                    statusDiv.textContent = `Location set to: ${data.principalSubdivision}`;
                }
            })
            .catch(error => {
                // Don't show error to user, coordinates are still captured
            });
    }
</script>
</body>
</html>
