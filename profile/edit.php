<?php
require_once '../config/config.php';
require_once '../includes/subjects_hierarchy.php';
require_once '../config/notification_helper.php';

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
        
        $strand = '';
        $course = '';
        
        if (in_array($grade_level, ['Grade 11', 'Grade 12']) && isset($_POST['strand'])) {
            $strand = sanitize_input($_POST['strand']);
        }
        
        if (in_array($grade_level, ['1st Year College', '2nd Year College', '3rd Year College', '4th Year College', 'Graduate']) && isset($_POST['course'])) {
            $course = sanitize_input($_POST['course']);
        }

        $location = sanitize_input($_POST['location']);
        $bio = sanitize_input($_POST['bio']);
        
        $hourly_rate = null;
        if ($user['role'] === 'mentor' || $user['role'] === 'peer' && isset($_POST['hourly_rate'])) {
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
                
                $_SESSION['success_message'] = 'Profile updated successfully!';
                redirect('profile/index.php');
                
            } catch (Exception $e) {
                $error = 'Failed to update profile. Please try again.';
            }
        }
    }
}

$is_shs = in_array($user['grade_level'], ['Grade 11', 'Grade 12']);
$is_college_or_grad = in_array($user['grade_level'], ['1st Year College', '2nd Year College', '3rd Year College', '4th Year College', 'Graduate']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <title>Edit Profile - Study Buddy</title>
    
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

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            padding: 0.5rem 0;
            margin-bottom: 1.5rem;
            transition: color 0.2s;
        }
        .back-button:hover {
            color: var(--primary-color);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        main {
            padding: 2rem 0;
        }
        
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
        .btn-secondary {
            background: var(--border-color);
            color: var(--text-primary);
        }
        .btn-secondary:hover {
            background: #d1d5db;
        }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        .btn-outline:hover {
            background: var(--border-color);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1rem 0;
        }
        
        .profile-header h2 {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .profile-header p {
            color: var(--text-secondary);
            font-size: 0.9rem;
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
            border: 4px solid var(--card-bg);
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
            border: 3px solid var(--card-bg);
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
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
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
            color: var(--text-primary);
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
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .form-label svg {
            width: 16px;
            height: 16px;
            color: var(--text-secondary);
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s;
            background: var(--card-bg);
            color: var(--text-primary);
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .location-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: var(--border-color);
            border-radius: 8px;
            font-size: 0.875rem;
            margin-top: 1rem;
        }
        
        .location-status.success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        @media (max-width: 768px) {
            main {
                padding: 1rem 0;
            }
            .container {
                padding: 0 0.75rem;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .btn-group {
                flex-direction: column;
            }
            input, select, textarea, button {
                font-size: 16px !important;
            }
        }
    </style>
</head>
<body>

<script>
    (function() {
        const theme = localStorage.getItem('theme');
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.body.classList.add('dark-mode');
        } else {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    })();
</script>

<main>
    <div class="container" style="max-width: 900px;">
        <a href="index.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Profile
        </a>
        
        <div class="profile-header">
            <h2>Edit Your Profile</h2>
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

            <div class="section-card">
                <div class="profile-picture-section">
                    <?php if ($user['profile_picture'] && file_exists('../' . $user['profile_picture'])): ?>
                        <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture" class="profile-picture-preview" id="profilePreview">
                        <div class="profile-picture-placeholder" id="profilePlaceholder" style="display: none;"></div>
                    <?php else: ?>
                        <img src="" alt="Profile Picture" class="profile-picture-preview" id="profilePreview" style="display: none;">
                        <div class="profile-picture-placeholder" id="profilePlaceholder">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <label for="profile_picture" class="upload-overlay">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                            <circle cx="12" cy="13" r="4"></circle>
                        </svg>
                        <input type="file" id="profile_picture" name="profile_picture" style="display: none;" accept="image/*" onchange="previewImage(this)">
                    </label>
                </div>
                <p style="text-align: center; font-size: 0.875rem; color: var(--text-secondary);">JPG, PNG, or GIF • Max 5MB</p>
            </div>

            <div class="section-card">
                <div class="section-header">
                    <div class="section-icon"><i class="fas fa-user"></i></div>
                    <h3 class="section-title">Basic Information</h3>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name" class="form-label"><i class="fas fa-user"></i> First Name</label>
                        <input type="text" id="first_name" name="first_name" class="form-input" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name" class="form-label"><i class="fas fa-user"></i> Last Name</label>
                        <input type="text" id="last_name" name="last_name" class="form-input" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="section-card">
                <div class="section-header">
                    <div class="section-icon"><i class="fas fa-graduation-cap"></i></div>
                    <h3 class="section-title">Academic Information</h3>
                </div>
                <div class="form-group">
                    <label for="grade_level" class="form-label"><i class="fas fa-layer-group"></i> Grade Level / Year</label>
                    <select id="grade_level" name="grade_level" class="form-select" required>
                        <option value="">Select level...</option>
                        <option value="Grade 11" <?php echo $user['grade_level'] === 'Grade 11' ? 'selected' : ''; ?>>Grade 11</option>
                        <option value="Grade 12" <?php echo $user['grade_level'] === 'Grade 12' ? 'selected' : ''; ?>>Grade 12</option>
                        <option value="1st Year College" <?php echo $user['grade_level'] === '1st Year College' ? 'selected' : ''; ?>>1st Year College</option>
                        <option value="2nd Year College" <?php echo $user['grade_level'] === '2nd Year College' ? 'selected' : ''; ?>>2nd Year College</option>
                        <option value="3rd Year College" <?php echo $user['grade_level'] === '3rd Year College' ? 'selected' : ''; ?>>3rd Year College</option>
                        <option value="4th Year College" <?php echo $user['grade_level'] === '4th Year College' ? 'selected' : ''; ?>>4th Year College</option>
                        <option value="Graduate" <?php echo $user['grade_level'] === 'Graduate' ? 'selected' : ''; ?>>Graduate</option>
                    </select>
                </div>

                <div class="form-group" id="strand-group" style="<?php echo $is_shs ? '' : 'display: none;'; ?>">
                    <label for="strand" class="form-label"><i class="fas fa-flask"></i> Strand (SHS)</label>
                    <input type="text" id="strand" name="strand" class="form-input" value="<?php echo htmlspecialchars($user['strand'] ?? ''); ?>" placeholder="e.g., STEM, HUMSS">
                </div>
                
                <div class="form-group" id="course-group" style="<?php echo $is_college_or_grad ? '' : 'display: none;'; ?>">
                    <label for="course" class="form-label"><i class="fas fa-book"></i> Course (College/Graduate)</label>
                    <input type="text" id="course" name="course" class="form-input" value="<?php echo htmlspecialchars($user['course'] ?? ''); ?>" placeholder="e.g., BS in Computer Science">
                </div>
            </div>

            <div class="section-card">
                <div class="section-header">
                    <div class="section-icon"><i class="fas fa-info-circle"></i></div>
                    <h3 class="section-title">Profile Details</h3>
                </div>
                <div class="form-group">
                    <label for="bio" class="form-label"><i class="fas fa-pen-nib"></i> Bio</label>
                    <textarea id="bio" name="bio" class="form-textarea" rows="5" placeholder="Tell everyone a bit about yourself..." required><?php echo htmlspecialchars($user['bio']); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="location" class="form-label"><i class="fas fa-map-marker-alt"></i> Location</label>
                    <input type="text" id="location" name="location" class="form-input" value="<?php echo htmlspecialchars($user['location']); ?>" placeholder="e.g., Manila, Philippines" required>
                    <button type="button" class="btn btn-outline" id="updateLocationBtn" style="width: 100%; margin-top: 0.75rem;">
                        <i class="fas fa-location-arrow"></i> Get Current Location
                    </button>
                    <div id="location-status" class="location-status" style="display: none;"></div>
                </div>
                <?php if ($user['role'] === 'mentor' || $user['role'] === 'peer'): ?>
                <div class="form-group">
                    <label for="hourly_rate" class="form-label"><i class="fas fa-dollar-sign"></i> Hourly Rate (PHP)</label>
                    <input type="number" id="hourly_rate" name="hourly_rate" class="form-input" min="0" step="0.01" value="<?php echo htmlspecialchars($user['hourly_rate'] ?? '0'); ?>" placeholder="e.g., 150.00">
                </div>
                <?php endif; ?>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="index.php" class="btn btn-secondary" style="text-align: center;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</main>

<script>
    document.getElementById('grade_level').addEventListener('change', function() {
        var gradeLevel = this.value;
        var strandGroup = document.getElementById('strand-group');
        var courseGroup = document.getElementById('course-group');
        
        var isSHS = gradeLevel === 'Grade 11' || gradeLevel === 'Grade 12';
        var isCollege = gradeLevel.includes('College') || gradeLevel === 'Graduate';

        strandGroup.style.display = isSHS ? 'block' : 'none';
        courseGroup.style.display = isCollege ? 'block' : 'none';
    });

    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var preview = document.getElementById('profilePreview');
                var placeholder = document.getElementById('profilePlaceholder');
                preview.src = e.target.result;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    document.getElementById('updateLocationBtn').addEventListener('click', function() {
        var btn = this;
        var statusEl = document.getElementById('location-status');
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fetching location...';
        statusEl.style.display = 'flex';
        statusEl.className = 'location-status';
        statusEl.innerHTML = '<i class="fas fa-info-circle"></i> Please approve the location request.';

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    var lat = position.coords.latitude;
                    var lon = position.coords.longitude;
                    var accuracy = position.coords.accuracy;
                    
                    document.getElementById('latitude').value = lat;
                    document.getElementById('longitude').value = lon;
                    document.getElementById('location_accuracy').value = accuracy;

                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`)
                        .then(response => response.json())
                        .then(data => {
                            var city = data.address.city || data.address.town || data.address.village || 'Unknown';
                            var country = data.address.country || 'Unknown';
                            document.getElementById('location').value = `${city}, ${country}`;
                            
                            statusEl.className = 'location-status success';
                            statusEl.innerHTML = '<i class="fas fa-check-circle"></i> Location updated successfully!';
                            btn.innerHTML = '<i class="fas fa-location-arrow"></i> Get Current Location';
                            btn.disabled = false;
                        })
                        .catch(err => {
                            document.getElementById('location').value = `Lat: ${lat.toFixed(4)}, Lon: ${lon.toFixed(4)}`;
                            statusEl.className = 'location-status success';
                            statusEl.innerHTML = '<i class="fas fa-check-circle"></i> Coordinates saved. Could not fetch city name.';
                            btn.innerHTML = '<i class="fas fa-location-arrow"></i> Get Current Location';
                            btn.disabled = false;
                        });
                },
                function(error) {
                    console.error("Geolocation error: ", error);
                    statusEl.className = 'location-status alert-error';
                    statusEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Location access denied or unavailable.';
                    btn.innerHTML = '<i class="fas fa-location-arrow"></i> Try Again';
                    btn.disabled = false;
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 }
            );
        } else {
            statusEl.className = 'location-status alert-error';
            statusEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Geolocation is not supported by this browser.';
            btn.innerHTML = '<i class="fas fa-location-arrow"></i> Try Again';
            btn.disabled = false;
        }
    });
</script>

</body>
</html>