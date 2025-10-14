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
        $strand = sanitize_input($_POST['strand']);
        $course = sanitize_input($_POST['course']);
        $location = sanitize_input($_POST['location']);
        $bio = sanitize_input($_POST['bio']);
        
        $hourly_rate = null;
        if (($user['role'] === 'mentor' || $user['role'] === 'peer') && isset($_POST['hourly_rate'])) {
            $hourly_rate = floatval($_POST['hourly_rate']);
            if ($hourly_rate < 0) {
                $hourly_rate = 0;
            }
        }
        
        $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
        $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
        $location_accuracy = isset($_POST['location_accuracy']) ? floatval($_POST['location_accuracy']) : null;
        
        $profile_picture = $user['profile_picture']; // Keep existing if no new upload
        
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_info = pathinfo($_FILES['profile_picture']['name']);
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            $file_extension = strtolower($file_info['extension']);
            
            if (in_array($file_extension, $allowed_types)) {
                $max_size = 5 * 1024 * 1024; // 5MB
                if ($_FILES['profile_picture']['size'] <= $max_size) {
                    $new_filename = 'profile_' . $user['id'] . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                        // Delete old profile picture if exists
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
                
                // Update session data
                $_SESSION['full_name'] = $first_name . ' ' . $last_name;
                
                // Refresh user data
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
    // Handle error silently
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
        .profile-picture-section {
            text-align: center;
            margin-bottom: 2rem;
        }
        .profile-picture-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #e5e7eb;
            margin-bottom: 1rem;
        }
        .profile-picture-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            border: 4px solid #e5e7eb;
            color: #6b7280;
            font-size: 2rem;
        }
        .file-input-wrapper {
            position: relative;
            display: inline-block;
        }
        .file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        .file-input-label {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #3b82f6;
            color: white;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.875rem;
        }
        .file-input-label:hover {
            background: #2563eb;
        }
        /* Added styles for location and subjects sections */
        .location-section, .subjects-section {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .subjects-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .subject-tag {
            background: #3b82f6;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
        }
        .location-status {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.5rem;
        }
        .location-status.success {
            color: #059669;
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
            <div class="form-container" style="max-width: 600px;">
                <h2 class="text-center mb-4">Edit Profile</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Added profile picture upload section -->
                <div class="profile-picture-section">
                    <?php if ($user['profile_picture'] && file_exists('../' . $user['profile_picture'])): ?>
                        <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                             alt="Profile Picture" class="profile-picture-preview" id="profilePreview">
                    <?php else: ?>
                        <div class="profile-picture-placeholder" id="profilePlaceholder">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <!-- Added profile picture upload field -->
                    <div class="form-group" style="text-align: center; margin-bottom: 2rem;">
                        <label class="form-label">Profile Picture</label>
                        <div class="file-input-wrapper">
                            <input type="file" id="profile_picture" name="profile_picture" 
                                   class="file-input" accept="image/*" onchange="previewImage(this)">
                            <label for="profile_picture" class="file-input-label">
                                Choose Photo
                            </label>
                        </div>
                        <p style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">
                            JPG, PNG, or GIF. Max 5MB.
                        </p>
                    </div>
                    
                    <!-- Added hidden fields for location coordinates -->
                    <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($user['latitude'] ? (string)$user['latitude'] : ''); ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($user['longitude'] ? (string)$user['longitude'] : ''); ?>">
                    <input type="hidden" id="location_accuracy" name="location_accuracy" value="<?php echo htmlspecialchars($user['location_accuracy'] ? (string)$user['location_accuracy'] : ''); ?>">
                    
                    <div class="grid grid-cols-2" style="gap: 1rem;">
                        <div class="form-group">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-input" required 
                                   value="<?php echo htmlspecialchars($user['first_name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-input" required 
                                   value="<?php echo htmlspecialchars($user['last_name']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="grade_level" class="form-label">Grade Level / Year Level</label>
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
                                   value="<?php echo htmlspecialchars($user['course'] ? $user['course'] : ''); ?>">
                        </div>
                    </div>
                    
                    <!-- Enhanced location section with GPS coordinates -->
                    <div class="location-section">
                        <h3 style="margin-bottom: 1rem; color: #374151;">📍 Location Information</h3>
                        <div class="form-group">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" id="location" name="location" class="form-input" required
                                   placeholder="e.g., Quezon City, Metro Manila"
                                   value="<?php echo htmlspecialchars($user['location'] ? $user['location'] : ''); ?>">
                        </div>
                        
                        <div class="location-status" id="locationStatus">
                            <?php if ($user['latitude'] && $user['longitude']): ?>
                                <span class="success">✓ GPS coordinates saved (Accuracy: <?php echo round($user['location_accuracy'] ?? 0); ?>m)</span>
                            <?php else: ?>
                                <span>📍 Click "Update GPS Location" to enable location-based matching</span>
                            <?php endif; ?>
                        </div>
                        
                        <button type="button" id="updateLocationBtn" class="btn btn-secondary" style="margin-top: 1rem;">
                            Update GPS Location
                        </button>
                    </div>
                    
                    <!-- Added current subjects display section -->
                    <div class="subjects-section">
                        <h3 style="margin-bottom: 1rem; color: #374151;">📚 Your Subjects</h3>
                        <?php if (!empty($user_subjects)): ?>
                            <div class="subjects-list">
                                <?php foreach ($user_subjects as $subject): ?>
                                    <span class="subject-tag">
                                        <?php echo htmlspecialchars($subject['main_subject']); ?>
                                        <?php if ($subject['subtopic']): ?>
                                            - <?php echo htmlspecialchars($subject['subtopic']); ?>
                                        <?php endif; ?>
                                        (<?php echo htmlspecialchars($subject['proficiency_level']); ?>)
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="color: #6b7280;">No subjects added yet.</p>
                        <?php endif; ?>
                        <a href="subjects.php" class="btn btn-secondary" style="margin-top: 1rem; display: inline-block;">
                            Manage Subjects
                        </a>
                    </div>
                    
                    <div class="form-group">
                        <label for="bio" class="form-label">Bio</label>
                        <textarea id="bio" name="bio" class="form-input" rows="4" required
                                  placeholder="Tell others about yourself, your learning goals, and what you can help with..."><?php echo htmlspecialchars($user['bio'] ? $user['bio'] : ''); ?></textarea>
                    </div>
                    
                    <?php // Added hourly rate input for mentors and peers ?>
                    <?php if ($user['role'] === 'mentor' || $user['role'] === 'peer'): ?>
                        <div class="form-group">
                            <label for="hourly_rate" class="form-label">
                                Hourly Rate (₱)
                                <?php if ($user['role'] === 'peer'): ?>
                                    <span class="text-sm text-secondary">(for teaching sessions)</span>
                                <?php endif; ?>
                            </label>
                            <input type="number" id="hourly_rate" name="hourly_rate" class="form-input" 
                                   min="0" step="0.01" 
                                   placeholder="e.g., 200.00"
                                   value="<?php echo htmlspecialchars($user['hourly_rate'] ? number_format($user['hourly_rate'], 2, '.', '') : ''); ?>">
                            <p style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">
                                Set to 0 or leave empty for free tutoring. This rate will be shown to students and used for commission calculations.
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">Save Changes</button>
                        <a href="index.php" class="btn btn-secondary" style="flex: 1; text-align: center;">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Added JavaScript for image preview -->
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
            
            // Default: disable both if no grade level selected
            if (!gradeLevel || gradeLevel === '') {
                strandField.disabled = true;
                courseField.disabled = true;
                strandField.value = '';
                courseField.value = '';
                return;
            }
            
            // Grade 7-10: disable both strand and course
            if (gradeLevel === 'Grade 7' || gradeLevel === 'Grade 8' || 
                gradeLevel === 'Grade 9' || gradeLevel === 'Grade 10') {
                strandField.disabled = true;
                courseField.disabled = true;
                strandField.value = '';
                courseField.value = '';
            }
            // Grade 11-12: enable strand, disable course
            else if (gradeLevel === 'Grade 11' || gradeLevel === 'Grade 12') {
                strandField.disabled = false;
                courseField.disabled = true;
                courseField.value = '';
            }
            // College: enable course, disable strand
            else if (gradeLevel.includes('College')) {
                strandField.disabled = true;
                courseField.disabled = false;
                strandField.value = '';
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateFormFields();
            
            // Add event listener for grade level changes
            document.getElementById('grade_level').addEventListener('change', updateFormFields);
        });
        
        document.getElementById('updateLocationBtn').addEventListener('click', function() {
            if (navigator.geolocation) {
                this.textContent = 'Getting location...';
                this.disabled = true;
                
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        document.getElementById('latitude').value = position.coords.latitude;
                        document.getElementById('longitude').value = position.coords.longitude;
                        document.getElementById('location_accuracy').value = position.coords.accuracy;
                        
                        document.getElementById('locationStatus').innerHTML = 
                            '<span class="success">✓ GPS coordinates updated (Accuracy: ' + 
                            Math.round(position.coords.accuracy) + 'm)</span>';
                        
                        document.getElementById('updateLocationBtn').textContent = 'Location Updated!';
                        document.getElementById('updateLocationBtn').disabled = false;
                        
                        // Reverse geocode to get readable address
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
                        document.getElementById('locationStatus').innerHTML = 
                            '<span style="color: #dc2626;">❌ Location access denied or unavailable</span>';
                        document.getElementById('updateLocationBtn').textContent = 'Try Again';
                        document.getElementById('updateLocationBtn').disabled = false;
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 }
                );
            } else {
                alert('Geolocation is not supported by this browser.');
            }
        });
    </script>
</body>
</html>
