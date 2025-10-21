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
        $first_name = sanitize_input($_POST['first_name']);
        $last_name = sanitize_input($_POST['last_name']);
        $grade_level = sanitize_input($_POST['grade_level']);
        $strand = isset($_POST['strand']) ? sanitize_input($_POST['strand']) : '';
        $course = sanitize_input($_POST['course']);
        $location = sanitize_input($_POST['location']);
        $bio = sanitize_input($_POST['bio']);
        
        $hourly_rate = null;
        if ($user['role'] === 'mentor' && isset($_POST['hourly_rate'])) {
            $hourly_rate = floatval($_POST['hourly_rate']);
            if ($hourly_rate < 0) {
                $hourly_rate = 0;
            }
        }
        
        $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
        $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
        $location_accuracy = isset($_POST['location_accuracy']) ? floatval($_POST['location_accuracy']) : null;
        
        $profile_picture = $user['profile_picture'];
        
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_info = pathinfo($_FILES['profile_picture']['name']);
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            $file_extension = strtolower($file_info['extension']);
            
            if (in_array($file_extension, $allowed_types)) {
                $max_size = 5 * 1024 * 1024;
                if ($_FILES['profile_picture']['size'] <= $max_size) {
                    $new_filename = 'profile_' . $user['id'] . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                        if ($user['profile_picture'] && file_exists('../' . $user['profile_picture'])) {
                            unlink('../' . $user['profile_picture']);
                        }
                        $profile_picture = 'uploads/profiles/' . $new_filename;
                    } else {
                        $error = 'Failed to upload profile picture.';
                    }
                } else {
                    $error = 'Profile picture must be less than 5MB.';
                }
            } else {
                $error = 'Profile picture must be a JPG, PNG, or GIF file.';
            }
        }
        
        if (empty($first_name) || empty($last_name) || empty($grade_level) || empty($location) || empty($bio)) {
            $error = 'Please fill in all required fields.';
        } else if (empty($error)) {
            try {
                $db = getDB();
                $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, grade_level = ?, strand = ?, course = ?, location = ?, bio = ?, profile_picture = ?, latitude = ?, longitude = ?, location_accuracy = ?, hourly_rate = ? WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $grade_level, $strand, $course, $location, $bio, $profile_picture, $latitude, $longitude, $location_accuracy, $hourly_rate, $user['id']]);
                
                $success = 'Profile updated successfully!';
                $_SESSION['full_name'] = $first_name . ' ' . $last_name;
                $user = get_logged_in_user();
                
            } catch (Exception $e) {
                $error = 'Failed to update profile. Please try again.';
            }
        }
    }
}

$user_subjects = [];
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT main_subject, subtopic, proficiency_level FROM user_subjects WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $user_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .profile-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem 0;
        }
        
        .profile-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .profile-header p {
            color: #6b7280;
            font-size: 0.95rem;
        }
        
        .profile-picture-section {
            position: relative;
            width: 140px;
            height: 140px;
            margin: 0 auto 2rem;
        }
        
        .profile-picture-preview, .profile-picture-placeholder {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 4px solid white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .profile-picture-preview {
            object-fit: cover;
        }
        
        .profile-picture-placeholder {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: 600;
        }
        
        .upload-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 44px;
            height: 44px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.4);
            transition: all 0.2s;
            border: 3px solid white;
        }
        
        .upload-overlay:hover {
            background: #2563eb;
            transform: scale(1.05);
        }
        
        .upload-overlay svg {
            width: 20px;
            height: 20px;
            color: white;
        }
        
        .section-card {
            background: white;
            border-radius: 12px;
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #e5e7eb;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .section-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-label svg {
            width: 16px;
            height: 16px;
            color: #9ca3af;
        }
        
        .form-input, .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .location-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: #f3f4f6;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-top: 1rem;
        }
        
        .location-status.success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .location-status svg {
            width: 18px;
            height: 18px;
        }
        
        .subjects-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .subject-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #9ca3af;
        }
        
        .empty-state svg {
            width: 48px;
            height: 48px;
            margin: 0 auto 1rem;
            opacity: 0.5;
        }
        
        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            flex: 1;
            padding: 0.875rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn svg {
            width: 18px;
            height: 18px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .btn-group {
                flex-direction: column;
            }
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
                    <li><a href="index.php">Profile</a></li>
                    <li><a href="../auth/logout.php" class="btn btn-outline">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="container">
            <div class="form-container" style="max-width: 700px;">
                <div class="profile-header">
                    <h2>✨ Edit Your Profile</h2>
                    <p>Keep your information up to date to get better matches</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($user['latitude'] ? (string)$user['latitude'] : ''); ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($user['longitude'] ? (string)$user['longitude'] : ''); ?>">
                    <input type="hidden" id="location_accuracy" name="location_accuracy" value="<?php echo htmlspecialchars($user['location_accuracy'] ? (string)$user['location_accuracy'] : ''); ?>">
                    
                    <!-- Profile Picture -->
                    <div class="section-card">
                        <div class="profile-picture-section">
                            <?php if ($user['profile_picture'] && file_exists('../' . $user['profile_picture'])): ?>
                                <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                                     alt="Profile Picture" class="profile-picture-preview" id="profilePreview">
                            <?php else: ?>
                                <div class="profile-picture-placeholder" id="profilePlaceholder">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <label for="profile_picture" class="upload-overlay">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                    <circle cx="12" cy="13" r="4"></circle>
                                </svg>
                                <input type="file" id="profile_picture" name="profile_picture" 
                                       style="display: none;" accept="image/*" onchange="previewImage(this)">
                            </label>
                        </div>
                        <p style="text-align: center; font-size: 0.875rem; color: #6b7280;">
                            JPG, PNG, or GIF • Max 5MB
                        </p>
                    </div>
                    
                    <!-- Basic Information -->
                    <div class="section-card">
                        <div class="section-header">
                            <div class="section-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </div>
                            <h3 class="section-title">Basic Information</h3>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name" class="form-label">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                    First Name
                                </label>
                                <input type="text" id="first_name" name="first_name" class="form-input" required 
                                       value="<?php echo htmlspecialchars($user['first_name']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name" class="form-label">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                    Last Name
                                </label>
                                <input type="text" id="last_name" name="last_name" class="form-input" required 
                                       value="<?php echo htmlspecialchars($user['last_name']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Academic Information -->
                    <div class="section-card">
                        <div class="section-header">
                            <div class="section-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                                    <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                                </svg>
                            </div>
                            <h3 class="section-title">Academic Information</h3>
                        </div>
                        
                        <div class="form-group">
                            <label for="grade_level" class="form-label">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                                </svg>
                                Grade Level / Year Level
                            </label>
                            <select id="grade_level" name="grade_level" class="form-select" required onchange="updateFormFields()">
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
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="strand" class="form-label">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                    </svg>
                                    Strand (if SHS)
                                </label>
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
                                <label for="course" class="form-label">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                                    </svg>
                                    Course (if College)
                                </label>
                                <input type="text" id="course" name="course" class="form-input" 
                                       placeholder="e.g., BS Computer Science"
                                       value="<?php echo htmlspecialchars($user['course'] ? $user['course'] : ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Location -->
                    <div class="section-card">
                        <div class="section-header">
                            <div class="section-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                            </div>
                            <h3 class="section-title">Location Information</h3>
                        </div>
                        
                        <div class="form-group">
                            <label for="location" class="form-label">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="10" r="3"></circle>
                                    <path d="M12 21.7C17.3 17 20 13 20 10a8 8 0 1 0-16 0c0 3 2.7 6.9 8 11.7z"></path>
                                </svg>
                                Location
                            </label>
                            <input type="text" id="location" name="location" class="form-input" required
                                   placeholder="e.g., Quezon City, Metro Manila"
                                   value="<?php echo htmlspecialchars($user['location'] ? $user['location'] : ''); ?>">
                        </div>
                        
                        <div class="location-status <?php echo ($user['latitude'] && $user['longitude']) ? 'success' : ''; ?>" id="locationStatus">
                            <?php if ($user['latitude'] && $user['longitude']): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                GPS coordinates saved (Accuracy: <?php echo round($user['location_accuracy'] ?? 0); ?>m)
                            <?php else: ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                                Click "Update GPS Location" to enable location-based matching
                            <?php endif; ?>
                        </div>
                        
                        <button type="button" id="updateLocationBtn" class="btn btn-secondary" style="margin-top: 1rem; width: 100%;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            Update GPS Location
                        </button>
                    </div>
                    
                    <!-- Subjects -->
                    <div class="section-card">
                        <div class="section-header">
                            <div class="section-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                                </svg>
                            </div>
                            <h3 class="section-title">Your Subjects</h3>
                        </div>
                        
                        <?php if (!empty($user_subjects)): ?>
                            <div class="subjects-grid">
                                <?php foreach ($user_subjects as $subject): ?>
                                    <span class="subject-badge">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                        <?php echo htmlspecialchars($subject['main_subject']); ?>
                                        <?php if ($subject['subtopic']): ?>
                                            - <?php echo htmlspecialchars($subject['subtopic']); ?>
                                        <?php endif; ?>
                                        (<?php echo htmlspecialchars($subject['proficiency_level']); ?>)
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                                </svg>
                                <p>No subjects added yet</p>
                            </div>
                        <?php endif; ?>
                        
                        <a href="subjects.php" class="btn btn-secondary" style="margin-top: 1rem; width: 100%;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Manage Subjects
                        </a>
                    </div>
                    
                    <!-- About -->
                    <div class="section-card">
                        <div class="section-header">
                            <div class="section-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                </svg>
                            </div>
                            <h3 class="section-title">About You</h3>
                        </div>
                        
                        <div class="form-group">
                            <label for="bio" class="form-label">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                                Bio
                            </label>
                            <textarea id="bio" name="bio" class="form-input" rows="5" required
                                      placeholder="Tell others about yourself, your learning goals, and what you can help with..."><?php echo htmlspecialchars($user['bio'] ? $user['bio'] : ''); ?></textarea>
                        </div>
                    </div>
                    
                    <?php if ($user['role'] === 'mentor'): ?>
                    <!-- Hourly Rate -->
                    <div class="section-card">
                        <div class="section-header">
                            <div class="section-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="1" x2="12" y2="23"></line>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                </svg>
                            </div>
                            <h3 class="section-title">Hourly Rate</h3>
                        </div>
                        
                        <div class="form-group">
                            <label for="hourly_rate" class="form-label">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="1" x2="12" y2="23"></line>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                </svg>
                                Rate per Hour (₱)
                            </label>
                            <input type="number" id="hourly_rate" name="hourly_rate" class="form-input" 
                                   min="0" step="0.01" 
                                   placeholder="e.g., 200.00"
                                   value="<?php echo htmlspecialchars($user['hourly_rate'] ? number_format($user['hourly_rate'], 2, '.', '') : ''); ?>">
                            <p style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem; display: flex; align-items: start; gap: 0.5rem;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px; flex-shrink: 0; margin-top: 2px;">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                                Set to 0 or leave empty for free tutoring. This rate will be shown to students.
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Save Changes
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('profilePreview');
                    const placeholder = document.getElementById('profilePlaceholder');
                    
                    if (preview) {
                        preview.src = e.target.result;
                    } else if (placeholder) {
                        placeholder.outerHTML = `<img src="${e.target.result}" alt="Profile Picture" class="profile-picture-preview" id="profilePreview">`;
                    }
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function updateFormFields() {
            const gradeLevel = document.getElementById('grade_level').value;
            const strandField = document.getElementById('strand');
            const courseField = document.getElementById('course');
            
            if (!gradeLevel || gradeLevel === '') {
                strandField.disabled = true;
                courseField.disabled = true;
                strandField.value = '';
                courseField.value = '';
                return;
            }
            
            if (gradeLevel === 'Grade 7' || gradeLevel === 'Grade 8' || 
                gradeLevel === 'Grade 9' || gradeLevel === 'Grade 10') {
                strandField.disabled = true;
                courseField.disabled = true;
                strandField.value = '';
                courseField.value = '';
            }
            else if (gradeLevel === 'Grade 11' || gradeLevel === 'Grade 12') {
                strandField.disabled = false;
                courseField.disabled = true;
                courseField.value = '';
            }
            else if (gradeLevel.includes('College')) {
                strandField.disabled = true;
                courseField.disabled = false;
                strandField.value = '';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            updateFormFields();
            document.getElementById('grade_level').addEventListener('change', updateFormFields);
        });
        
        document.getElementById('updateLocationBtn').addEventListener('click', function() {
            if (navigator.geolocation) {
                this.innerHTML = `
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 6v6l4 2"></path>
                    </svg>
                    Getting location...
                `;
                this.disabled = true;
                
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        document.getElementById('latitude').value = position.coords.latitude;
                        document.getElementById('longitude').value = position.coords.longitude;
                        document.getElementById('location_accuracy').value = position.coords.accuracy;
                        
                        const statusDiv = document.getElementById('locationStatus');
                        statusDiv.className = 'location-status success';
                        statusDiv.innerHTML = `
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            GPS coordinates updated (Accuracy: ${Math.round(position.coords.accuracy)}m)
                        `;
                        
                        const btn = document.getElementById('updateLocationBtn');
                        btn.innerHTML = `
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Location Updated!
                        `;
                        btn.disabled = false;
                        
                        fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${position.coords.latitude}&longitude=${position.coords.longitude}&localityLanguage=en`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.city && data.countryName) {
                                    const address = `${data.city}, ${data.principalSubdivision}, ${data.countryName}`;
                                    document.getElementById('location').value = address;
                                }
                            })
                            .catch(error => console.log('Geocoding failed:', error));
                    },
                    function(error) {
                        const statusDiv = document.getElementById('locationStatus');
                        statusDiv.className = 'location-status';
                        statusDiv.innerHTML = `
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="15" y1="9" x2="9" y2="15"></line>
                                <line x1="9" y1="9" x2="15" y2="15"></line>
                            </svg>
                            Location access denied or unavailable
                        `;
                        
                        const btn = document.getElementById('updateLocationBtn');
                        btn.innerHTML = `
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            Try Again
                        `;
                        btn.disabled = false;
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 }
                );
            } else {
                alert('Geolocation is not supported by this browser.');
            }
        });
    </script>
    <style>
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</body>
</html>