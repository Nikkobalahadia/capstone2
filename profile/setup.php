<?php
require_once '../config/config.php';

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
        
        // Role-specific fields
        if ($role === 'student' || $role === 'peer') {
            $grade_level = sanitize_input($_POST['grade_level']);
            $strand = sanitize_input($_POST['strand']);
            $course = sanitize_input($_POST['course']);
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
                    
                    if ($role === 'student') {
                        if ($profile_picture_path) {
                            $stmt = $db->prepare("UPDATE users SET grade_level = ?, strand = ?, course = ?, location = ?, bio = ?, learning_goals = ?, preferred_learning_style = ?, profile_picture = ? WHERE id = ?");
                            $stmt->execute([$grade_level, $strand, $course, $location, $bio, $learning_goals, $preferred_learning_style, $profile_picture_path, $user['id']]);
                        } else {
                            $stmt = $db->prepare("UPDATE users SET grade_level = ?, strand = ?, course = ?, location = ?, bio = ?, learning_goals = ?, preferred_learning_style = ? WHERE id = ?");
                            $stmt->execute([$grade_level, $strand, $course, $location, $bio, $learning_goals, $preferred_learning_style, $user['id']]);
                        }
                    } elseif ($role === 'mentor') {
                        if ($profile_picture_path) {
                            $stmt = $db->prepare("UPDATE users SET location = ?, bio = ?, teaching_style = ?, profile_picture = ? WHERE id = ?");
                            $stmt->execute([$location, $bio, $teaching_style, $profile_picture_path, $user['id']]);
                        } else {
                            $stmt = $db->prepare("UPDATE users SET location = ?, bio = ?, teaching_style = ? WHERE id = ?");
                            $stmt->execute([$location, $bio, $teaching_style, $user['id']]);
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
                            $stmt = $db->prepare("UPDATE users SET grade_level = ?, strand = ?, course = ?, location = ?, bio = ?, learning_goals = ?, preferred_learning_style = ?, teaching_style = ?, profile_picture = ? WHERE id = ?");
                            $stmt->execute([$grade_level, $strand, $course, $location, $bio, $learning_goals, $preferred_learning_style, $teaching_style, $profile_picture_path, $user['id']]);
                        } else {
                            $stmt = $db->prepare("UPDATE users SET grade_level = ?, strand = ?, course = ?, location = ?, bio = ?, learning_goals = ?, preferred_learning_style = ?, teaching_style = ? WHERE id = ?");
                            $stmt->execute([$grade_level, $strand, $course, $location, $bio, $learning_goals, $preferred_learning_style, $teaching_style, $user['id']]);
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
                    $subject_stmt = $db->prepare("INSERT INTO user_subjects (user_id, subject_name, proficiency_level) VALUES (?, ?, ?)");
                    foreach ($subjects as $subject_data) {
                        $subject_parts = explode('|', $subject_data);
                        if (count($subject_parts) === 2) {
                            $subject_stmt->execute([$user['id'], $subject_parts[0], $subject_parts[1]]);
                        }
                    }
                    
                    $db->commit();
                    $success = 'Profile updated successfully!';
                    
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

// Common subjects list
$common_subjects = [
    'Mathematics', 'Science', 'English', 'Filipino', 'History', 'Geography',
    'Physics', 'Chemistry', 'Biology', 'Computer Science', 'Programming',
    'Accounting', 'Economics', 'Psychology', 'Sociology', 'Philosophy',
    'Art', 'Music', 'Physical Education', 'Research', 'Statistics'
];
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
                            <img id="preview-img" src="/placeholder.svg" alt="Preview" style="width: 150px; height: 150px; object-fit: cover; border-radius: 8px; border: 2px solid #e2e8f0;">
                        </div>
                    </div>
                    
                     Added peer-specific form sections 
                    <?php if ($user['role'] === 'student' || $user['role'] === 'peer'): ?>
                         Student/Learning fields 
                        <div class="form-group">
                            <label for="grade_level" class="form-label">Grade Level / Year Level</label>
                            <select id="grade_level" name="grade_level" class="form-select" required>
                                <option value="">Select Grade Level</option>
                                <option value="Grade 7" <?php echo $user['grade_level'] === 'Grade 7' ? 'selected' : ''; ?>>Grade 7</option>
                                <option value="Grade 8" <?php echo $user['grade_level'] === 'Grade 8' ? 'selected' : ''; ?>>Grade 8</option>
                                <option value="Grade 9" <?php echo $user['grade_level'] === 'Grade 9' ? 'selected' : ''; ?>>Grade 9</option>
                                <option value="Grade 10" <?php echo $user['grade_level'] === 'Grade 10' ? 'selected' : ''; ?>>Grade 10</option>
                                <option value="Grade 11" <?php echo $user['grade_level'] === 'Grade 11' ? 'selected' : ''; ?>>Grade 11</option>
                                <option value="Grade 12" <?php echo $user['grade_level'] === 'Grade 12' ? 'selected' : ''; ?>>Grade 12</option>
                                <option value="1st Year College" <?php echo $user['grade_level'] === '1st Year College' ? 'selected' : ''; ?>>1st Year College</option>
                                <option value="2nd Year College" <?php echo $user['grade_level'] === '2nd Year College' ? 'selected' : ''; ?>>2nd Year College</option>
                                <option value="3rd Year College" <?php echo $user['grade_level'] === '3rd Year College' ? 'selected' : ''; ?>>3rd Year College</option>
                                <option value="4th Year College" <?php echo $user['grade_level'] === '4th Year College' ? 'selected' : ''; ?>>4th Year College</option>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-2" style="gap: 1rem;">
                            <div class="form-group">
                                <label for="strand" class="form-label">Strand (if SHS)</label>
                                <select id="strand" name="strand" class="form-select">
                                    <option value="">Select Strand</option>
                                    <option value="STEM" <?php echo $user['strand'] === 'STEM' ? 'selected' : ''; ?>>STEM</option>
                                    <option value="ABM" <?php echo $user['strand'] === 'ABM' ? 'selected' : ''; ?>>ABM</option>
                                    <option value="HUMSS" <?php echo $user['strand'] === 'HUMSS' ? 'selected' : ''; ?>>HUMSS</option>
                                    <option value="GAS" <?php echo $user['strand'] === 'GAS' ? 'selected' : ''; ?>>GAS</option>
                                    <option value="TVL" <?php echo $user['strand'] === 'TVL' ? 'selected' : ''; ?>>TVL</option>
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
                                <option value="Visual" <?php echo $user['preferred_learning_style'] === 'Visual' ? 'selected' : ''; ?>>Visual (diagrams, charts, images)</option>
                                <option value="Auditory" <?php echo $user['preferred_learning_style'] === 'Auditory' ? 'selected' : ''; ?>>Auditory (listening, discussion)</option>
                                <option value="Kinesthetic" <?php echo $user['preferred_learning_style'] === 'Kinesthetic' ? 'selected' : ''; ?>>Kinesthetic (hands-on, practice)</option>
                                <option value="Reading/Writing" <?php echo $user['preferred_learning_style'] === 'Reading/Writing' ? 'selected' : ''; ?>>Reading/Writing (notes, text)</option>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                     Updated subjects section for peer role 
                    <?php if ($user['role'] === 'peer'): ?>
                         Peer gets both learning and teaching subjects 
                        <div class="form-group">
                            <label class="form-label">Subjects you want to learn</label>
                            <p class="text-sm text-secondary mb-3">Select subjects you want to learn and your current skill level.</p>
                            <div id="learning-subjects-container">
                                <div class="subject-row" style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center;">
                                    <select name="learning_subjects[]" class="form-select" style="flex: 2;" required>
                                        <option value="">Select Subject</option>
                                        <?php foreach ($common_subjects as $subject): ?>
                                            <option value="<?php echo $subject; ?>|beginner"><?php echo $subject; ?> - Beginner</option>
                                            <option value="<?php echo $subject; ?>|intermediate"><?php echo $subject; ?> - Intermediate</option>
                                            <option value="<?php echo $subject; ?>|advanced"><?php echo $subject; ?> - Advanced</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-danger" onclick="removeSubject(this, 'learning')" style="padding: 0.5rem;">Remove</button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary" onclick="addSubject('learning')">Add Learning Subject</button>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Subjects you can teach</label>
                            <p class="text-sm text-secondary mb-3">Select subjects you can teach and your proficiency level.</p>
                            <div id="teaching-subjects-container">
                                <div class="subject-row" style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center;">
                                    <select name="teaching_subjects[]" class="form-select" style="flex: 2;" required>
                                        <option value="">Select Subject</option>
                                        <?php foreach ($common_subjects as $subject): ?>
                                            <option value="<?php echo $subject; ?>|intermediate"><?php echo $subject; ?> - Intermediate</option>
                                            <option value="<?php echo $subject; ?>|advanced"><?php echo $subject; ?> - Advanced</option>
                                            <option value="<?php echo $subject; ?>|expert"><?php echo $subject; ?> - Expert</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-danger" onclick="removeSubject(this, 'teaching')" style="padding: 0.5rem;">Remove</button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary" onclick="addSubject('teaching')">Add Teaching Subject</button>
                        </div>
                    <?php elseif ($user['role'] === 'student'): ?>
                        <div class="form-group">
                            <label class="form-label">Subjects you want to learn</label>
                            <p class="text-sm text-secondary mb-3">Select subjects you want to learn and your current skill level.</p>
                    <?php else: ?>
                        <div class="form-group">
                            <label class="form-label">Subjects you can teach</label>
                            <p class="text-sm text-secondary mb-3">Select subjects you can teach and your proficiency level.</p>
                    <?php endif; ?>
                    
                     Common subjects section 
                    <div id="subjects-container">
                        <?php if (!empty($existing_subjects) && $user['role'] !== 'peer'): ?>
                            <?php foreach ($existing_subjects as $subject): ?>
                                <div class="subject-row" style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center;">
                                    <select name="subjects[]" class="form-select" style="flex: 2;" required>
                                        <option value="">Select Subject</option>
                                        <?php foreach ($common_subjects as $subj): ?>
                                            <?php if ($user['role'] === 'student'): ?>
                                                <option value="<?php echo $subj; ?>|beginner" <?php echo $subject['subject_name'] === $subj && $subject['proficiency_level'] === 'beginner' ? 'selected' : ''; ?>><?php echo $subj; ?> - Beginner</option>
                                                <option value="<?php echo $subj; ?>|intermediate" <?php echo $subject['subject_name'] === $subj && $subject['proficiency_level'] === 'intermediate' ? 'selected' : ''; ?>><?php echo $subj; ?> - Intermediate</option>
                                                <option value="<?php echo $subj; ?>|advanced" <?php echo $subject['subject_name'] === $subj && $subject['proficiency_level'] === 'advanced' ? 'selected' : ''; ?>><?php echo $subj; ?> - Advanced</option>
                                            <?php else: ?>
                                                <option value="<?php echo $subj; ?>|beginner" <?php echo $subject['subject_name'] === $subj && $subject['proficiency_level'] === 'beginner' ? 'selected' : ''; ?>><?php echo $subj; ?> - Beginner</option>
                                                <option value="<?php echo $subj; ?>|intermediate" <?php echo $subject['subject_name'] === $subj && $subject['proficiency_level'] === 'intermediate' ? 'selected' : ''; ?>><?php echo $subj; ?> - Intermediate</option>
                                                <option value="<?php echo $subj; ?>|advanced" <?php echo $subject['subject_name'] === $subj && $subject['proficiency_level'] === 'advanced' ? 'selected' : ''; ?>><?php echo $subj; ?> - Advanced</option>
                                                <option value="<?php echo $subj; ?>|expert" <?php echo $subject['subject_name'] === $subj && $subject['proficiency_level'] === 'expert' ? 'selected' : ''; ?>><?php echo $subj; ?> - Expert</option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-danger" onclick="removeSubject(this)" style="padding: 0.5rem;">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif ($user['role'] !== 'peer'): ?>
                            <div class="subject-row" style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center;">
                                <select name="subjects[]" class="form-select" style="flex: 2;" required>
                                    <option value="">Select Subject</option>
                                    <?php foreach ($common_subjects as $subject): ?>
                                        <?php if ($user['role'] === 'student'): ?>
                                            <option value="<?php echo $subject; ?>|beginner"><?php echo $subject; ?> - Beginner</option>
                                            <option value="<?php echo $subject; ?>|intermediate"><?php echo $subject; ?> - Intermediate</option>
                                            <option value="<?php echo $subject; ?>|advanced"><?php echo $subject; ?> - Advanced</option>
                                        <?php else: ?>
                                            <option value="<?php echo $subject; ?>|beginner"><?php echo $subject; ?> - Beginner</option>
                                            <option value="<?php echo $subject; ?>|intermediate"><?php echo $subject; ?> - Intermediate</option>
                                            <option value="<?php echo $subject; ?>|advanced"><?php echo $subject; ?> - Advanced</option>
                                            <option value="<?php echo $subject; ?>|expert"><?php echo $subject; ?> - Expert</option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-danger" onclick="removeSubject(this)" style="padding: 0.5rem;">Remove</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($user['role'] !== 'peer'): ?>
                        <button type="button" class="btn btn-secondary" onclick="addSubject()">Add Another Subject</button>
                    <?php endif; ?>
                </div>
                
                <?php if ($user['role'] === 'mentor' || $user['role'] === 'peer'): ?>
                    <div class="form-group">
                        <label class="form-label">Availability (days & times)</label>
                        <p class="text-sm text-secondary mb-3">Set your available days and time slots for tutoring sessions.</p>
                        
                        <div id="availability-container">
                            <?php if (!empty($existing_availability)): ?>
                                <?php foreach ($existing_availability as $avail): ?>
                                    <div class="availability-row" style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center;">
                                        <select name="availability[]" class="form-select" style="flex: 1;" required>
                                            <option value="">Select Day</option>
                                            <option value="monday|<?php echo $avail['start_time']; ?>|<?php echo $avail['end_time']; ?>" <?php echo $avail['day_of_week'] === 'monday' ? 'selected' : ''; ?>>Monday</option>
                                            <option value="tuesday|<?php echo $avail['start_time']; ?>|<?php echo $avail['end_time']; ?>" <?php echo $avail['day_of_week'] === 'tuesday' ? 'selected' : ''; ?>>Tuesday</option>
                                            <option value="wednesday|<?php echo $avail['start_time']; ?>|<?php echo $avail['end_time']; ?>" <?php echo $avail['day_of_week'] === 'wednesday' ? 'selected' : ''; ?>>Wednesday</option>
                                            <option value="thursday|<?php echo $avail['start_time']; ?>|<?php echo $avail['end_time']; ?>" <?php echo $avail['day_of_week'] === 'thursday' ? 'selected' : ''; ?>>Thursday</option>
                                            <option value="friday|<?php echo $avail['start_time']; ?>|<?php echo $avail['end_time']; ?>" <?php echo $avail['day_of_week'] === 'friday' ? 'selected' : ''; ?>>Friday</option>
                                            <option value="saturday|<?php echo $avail['start_time']; ?>|<?php echo $avail['end_time']; ?>" <?php echo $avail['day_of_week'] === 'saturday' ? 'selected' : ''; ?>>Saturday</option>
                                            <option value="sunday|<?php echo $avail['start_time']; ?>|<?php echo $avail['end_time']; ?>" <?php echo $avail['day_of_week'] === 'sunday' ? 'selected' : ''; ?>>Sunday</option>
                                        </select>
                                        <input type="time" name="start_time[]" class="form-input" style="flex: 1;" value="<?php echo $avail['start_time']; ?>" required>
                                        <input type="time" name="end_time[]" class="form-input" style="flex: 1;" value="<?php echo $avail['end_time']; ?>" required>
                                        <button type="button" class="btn btn-danger" onclick="removeAvailability(this)" style="padding: 0.5rem;">Remove</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="availability-row" style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center;">
                                    <select name="availability_day[]" class="form-select" style="flex: 1;" required>
                                        <option value="">Select Day</option>
                                        <option value="monday">Monday</option>
                                        <option value="tuesday">Tuesday</option>
                                        <option value="wednesday">Wednesday</option>
                                        <option value="thursday">Thursday</option>
                                        <option value="friday">Friday</option>
                                        <option value="saturday">Saturday</option>
                                        <option value="sunday">Sunday</option>
                                    </select>
                                    <input type="time" name="start_time[]" class="form-input" style="flex: 1;" required>
                                    <input type="time" name="end_time[]" class="form-input" style="flex: 1;" required>
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
                    <input type="text" id="location" name="location" class="form-input" required
                           placeholder="e.g., Quezon City, Metro Manila"
                           value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>">
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
    document.getElementById('profile_picture').addEventListener('change', function(e) {
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
        subjectRow.style.cssText = 'display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center;';
        
        let options = '';
        let selectName = isPeer ? (type === 'learning' ? 'learning_subjects[]' : 'teaching_subjects[]') : 'subjects[]';
        
        <?php foreach ($common_subjects as $subject): ?>
            if (isStudent || (isPeer && type === 'learning')) {
                options += '<option value="<?php echo $subject; ?>|beginner"><?php echo $subject; ?> - Beginner</option>';
                options += '<option value="<?php echo $subject; ?>|intermediate"><?php echo $subject; ?> - Intermediate</option>';
                options += '<option value="<?php echo $subject; ?>|advanced"><?php echo $subject; ?> - Advanced</option>';
            } else {
                options += '<option value="<?php echo $subject; ?>|beginner"><?php echo $subject; ?> - Beginner</option>';
                options += '<option value="<?php echo $subject; ?>|intermediate"><?php echo $subject; ?> - Intermediate</option>';
                options += '<option value="<?php echo $subject; ?>|advanced"><?php echo $subject; ?> - Advanced</option>';
                options += '<option value="<?php echo $subject; ?>|expert"><?php echo $subject; ?> - Expert</option>';
            }
        <?php endforeach; ?>
        
        subjectRow.innerHTML = `
            <select name="${selectName}" class="form-select" style="flex: 2;" required>
                <option value="">Select Subject</option>
                ${options}
            </select>
            <button type="button" class="btn btn-danger" onclick="removeSubject(this, '${type}')" style="padding: 0.5rem;">Remove</button>
        `;
        
        container.appendChild(subjectRow);
    }
    
    function removeSubject(button, type = 'general') {
        let container;
        
        if (type === 'learning') {
            container = document.getElementById('learning-subjects-container');
        } else if (type === 'teaching') {
            container = document.getElementById('teaching-subjects-container');
        } else {
            container = document.getElementById('subjects-container');
        }
        
        if (container.children.length > 1) {
            button.parentElement.remove();
        }
    }
    
    function addAvailability() {
        const container = document.getElementById('availability-container');
        const availRow = document.createElement('div');
        availRow.className = 'availability-row';
        availRow.style.cssText = 'display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center;';
        
        availRow.innerHTML = `
            <select name="availability_day[]" class="form-select" style="flex: 1;" required>
                <option value="">Select Day</option>
                <option value="monday">Monday</option>
                <option value="tuesday">Tuesday</option>
                <option value="wednesday">Wednesday</option>
                <option value="thursday">Thursday</option>
                <option value="friday">Friday</option>
                <option value="saturday">Saturday</option>
                <option value="sunday">Sunday</option>
            </select>
            <input type="time" name="start_time[]" class="form-input" style="flex: 1;" required>
            <input type="time" name="end_time[]" class="form-input" style="flex: 1;" required>
            <button type="button" class="btn btn-danger" onclick="removeAvailability(this)" style="padding: 0.5rem;">Remove</button>
        `;
        
        container.appendChild(availRow);
    }
    
    function removeAvailability(button) {
        const container = document.getElementById('availability-container');
        if (container.children.length > 1) {
            button.parentElement.remove();
        }
    }
</script>
</body>
</html>
