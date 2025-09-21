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
        $grade_level = sanitize_input($_POST['grade_level']);
        $strand = sanitize_input($_POST['strand']);
        $course = sanitize_input($_POST['course']);
        $location = sanitize_input($_POST['location']);
        $bio = sanitize_input($_POST['bio']);
        $subjects = $_POST['subjects'] ?? [];
        
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
            if (empty($grade_level) || empty($location) || empty($bio)) {
                $error = 'Please fill in all required fields.';
            } elseif (empty($subjects)) {
                $error = 'Please select at least one subject.';
            } else {
                try {
                    $db = getDB();
                    $db->beginTransaction();
                    
                    if ($profile_picture_path) {
                        $stmt = $db->prepare("UPDATE users SET grade_level = ?, strand = ?, course = ?, location = ?, bio = ?, profile_picture = ? WHERE id = ?");
                        $stmt->execute([$grade_level, $strand, $course, $location, $bio, $profile_picture_path, $user['id']]);
                    } else {
                        $stmt = $db->prepare("UPDATE users SET grade_level = ?, strand = ?, course = ?, location = ?, bio = ? WHERE id = ?");
                        $stmt->execute([$grade_level, $strand, $course, $location, $bio, $user['id']]);
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
                <h2 class="text-center mb-4">Complete Your Profile</h2>
                <p class="text-center text-secondary mb-4">Help us match you with the right study partners by completing your profile.</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Added enctype for file upload support -->
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <!-- Added profile picture upload section -->
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
                        <label for="location" class="form-label">Location</label>
                        <input type="text" id="location" name="location" class="form-input" required
                               placeholder="e.g., Quezon City, Metro Manila"
                               value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="bio" class="form-label">Bio</label>
                        <textarea id="bio" name="bio" class="form-input" rows="4" required
                                  placeholder="Tell others about yourself, your learning goals, and what you can help with..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Subject Expertise</label>
                        <p class="text-sm text-secondary mb-3">Select subjects you're good at or want to learn. Choose your proficiency level for each.</p>
                        
                        <div id="subjects-container">
                            <?php if (!empty($existing_subjects)): ?>
                                <?php foreach ($existing_subjects as $subject): ?>
                                    <div class="subject-row" style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center;">
                                        <select name="subjects[]" class="form-select" style="flex: 2;" required>
                                            <option value="">Select Subject</option>
                                            <?php foreach ($common_subjects as $subj): ?>
                                                <option value="<?php echo $subj; ?>|beginner" <?php echo $subject['subject_name'] === $subj && $subject['proficiency_level'] === 'beginner' ? 'selected' : ''; ?>><?php echo $subj; ?> - Beginner</option>
                                                <option value="<?php echo $subj; ?>|intermediate" <?php echo $subject['subject_name'] === $subj && $subject['proficiency_level'] === 'intermediate' ? 'selected' : ''; ?>><?php echo $subj; ?> - Intermediate</option>
                                                <option value="<?php echo $subj; ?>|advanced" <?php echo $subject['subject_name'] === $subj && $subject['proficiency_level'] === 'advanced' ? 'selected' : ''; ?>><?php echo $subj; ?> - Advanced</option>
                                                <option value="<?php echo $subj; ?>|expert" <?php echo $subject['subject_name'] === $subj && $subject['proficiency_level'] === 'expert' ? 'selected' : ''; ?>><?php echo $subj; ?> - Expert</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="btn btn-danger" onclick="removeSubject(this)" style="padding: 0.5rem;">Remove</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="subject-row" style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center;">
                                    <select name="subjects[]" class="form-select" style="flex: 2;" required>
                                        <option value="">Select Subject</option>
                                        <?php foreach ($common_subjects as $subject): ?>
                                            <option value="<?php echo $subject; ?>|beginner"><?php echo $subject; ?> - Beginner</option>
                                            <option value="<?php echo $subject; ?>|intermediate"><?php echo $subject; ?> - Intermediate</option>
                                            <option value="<?php echo $subject; ?>|advanced"><?php echo $subject; ?> - Advanced</option>
                                            <option value="<?php echo $subject; ?>|expert"><?php echo $subject; ?> - Expert</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-danger" onclick="removeSubject(this)" style="padding: 0.5rem;">Remove</button>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <button type="button" class="btn btn-secondary" onclick="addSubject()">Add Another Subject</button>
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

        function addSubject() {
            const container = document.getElementById('subjects-container');
            const subjectRow = document.createElement('div');
            subjectRow.className = 'subject-row';
            subjectRow.style.cssText = 'display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center;';
            
            subjectRow.innerHTML = `
                <select name="subjects[]" class="form-select" style="flex: 2;" required>
                    <option value="">Select Subject</option>
                    <?php foreach ($common_subjects as $subject): ?>
                        <option value="<?php echo $subject; ?>|beginner"><?php echo $subject; ?> - Beginner</option>
                        <option value="<?php echo $subject; ?>|intermediate"><?php echo $subject; ?> - Intermediate</option>
                        <option value="<?php echo $subject; ?>|advanced"><?php echo $subject; ?> - Advanced</option>
                        <option value="<?php echo $subject; ?>|expert"><?php echo $subject; ?> - Expert</option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-danger" onclick="removeSubject(this)" style="padding: 0.5rem;">Remove</button>
            `;
            
            container.appendChild(subjectRow);
        }
        
        function removeSubject(button) {
            const container = document.getElementById('subjects-container');
            if (container.children.length > 1) {
                button.parentElement.remove();
            }
        }
    </script>
</body>
</html>
