<?php
require_once '../config/config.php';
require_once '../config/notification_helper.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user) {
    redirect('auth/login.php');
}

// Check if user is a student
if ($user['role'] !== 'student') {
    redirect('../dashboard.php');
}

$unread_notifications = get_unread_count($user['id']);

$error = '';
$success = '';

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $document_type = sanitize_input($_POST['document_type']);
        $description = sanitize_input($_POST['description']);
        
        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            $file_type = $_FILES['document']['type'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (in_array($file_type, $allowed_types) && $_FILES['document']['size'] <= $max_size) {
                $upload_dir = '../uploads/verification/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
                $filename = 'verification_' . $user['id'] . '_' . $document_type . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['document']['tmp_name'], $upload_path)) {
                    // Store document info in database
                    $db = getDB();
                    $stmt = $db->prepare("INSERT INTO user_verification_documents (user_id, document_type, filename, original_filename, description, uploaded_by, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                    $stmt->execute([$user['id'], $document_type, $filename, $_FILES['document']['name'], $description, $user['id']]);
                    
                    $success = 'Verification document uploaded successfully. It will be reviewed by our admin team.';
                } else {
                    $error = 'Failed to upload document. Please try again.';
                }
            } else {
                $error = 'Invalid file type or size. Please upload JPG, PNG, GIF, or PDF files under 5MB.';
            }
        } else {
            $error = 'Please select a file to upload.';
        }
    }
}

// Get user's verification documents
$db = getDB();
$docs_stmt = $db->prepare("SELECT * FROM user_verification_documents WHERE user_id = ? ORDER BY created_at DESC");
$docs_stmt->execute([$user['id']]);
$verification_docs = $docs_stmt->fetchAll();

// Check if user has any approved documents
$approved_docs = array_filter($verification_docs, function($doc) {
    return $doc['status'] === 'approved';
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Student Verification - Study Buddy</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --success-color: #16a34a;
            --warning-color: #f59e0b;
            --text-primary: #1a1a1a;
            --text-secondary: #666;
            --border-color: #e5e5e5;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.1);
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
            color: #1a1a1a;
        }

        /* ===== HEADER & NAVIGATION ===== */
        .header {
            background: white;
            border-bottom: 1px solid var(--border-color);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
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
            background: #f0f0f0;
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
            border: 2px solid white;
        }

        .notification-dropdown {
            display: none;
            position: absolute;
            right: -10px;
            top: 100%;
            margin-top: 0.75rem;
            width: 380px;
            max-height: 450px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            overflow: hidden;
            flex-direction: column;
        }

        .notification-dropdown.show {
            display: flex;
        }

        .notification-header {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
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
            border-bottom: 1px solid #f5f5f5;
            cursor: pointer;
            transition: background 0.15s;
            display: flex;
            gap: 0.75rem;
        }

        .notification-item-dropdown:hover {
            background: #fafafa;
        }

        .notification-item-dropdown.unread {
            background: #f0f7ff;
        }

        .notification-footer {
            padding: 0.75rem;
            text-align: center;
            border-top: 1px solid #f0f0f0;
        }

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
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
        }

        .profile-dropdown.show {
            display: block;
        }

        .profile-dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
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
            color: #999;
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
            background: #f5f5f5;
            color: var(--primary-color);
        }

        .profile-dropdown-item.logout {
            color: #dc2626;
        }

        .profile-dropdown-item.logout:hover {
            background: #fee2e2;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        main {
            padding: 2rem 0;
            margin-top: 60px;
        }

        .card {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .card-body {
            padding: 1.25rem;
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
            background: #f0f0f0;
            color: #1a1a1a;
        }

        .btn-secondary:hover {
            background: #e5e5e5;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        .grid {
            display: grid;
        }

        .grid-cols-3 {
            grid-template-columns: repeat(3, 1fr);
        }

        .text-secondary {
            color: var(--text-secondary);
        }

        .text-primary {
            color: var(--primary-color);
        }

        .text-success {
            color: var(--success-color);
        }

        .font-semibold {
            font-weight: 600;
        }

        .mb-2 {
            margin-bottom: 0.5rem;
        }

        .mb-3 {
            margin-bottom: 0.75rem;
        }

        .mb-4 {
            margin-bottom: 1rem;
        }

        .mt-4 {
            margin-top: 1rem;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid var(--border-color);
        }

        td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        .info-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .info-box-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: #0369a1;
            margin-bottom: 0.5rem;
        }

        .info-box-content {
            font-size: 0.9rem;
            color: #075985;
            line-height: 1.6;
        }
        
        /* Modal Styles - Custom for this file's non-Bootstrap CSS */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal.show {
            display: block;
        }

        .modal-dialog {
            margin: 0;
            width: 100%;
            height: 100%;
        }

        .modal-content {
            position: relative;
            background-color: #fefefe;
            border: 1px solid rgba(0,0,0,0.2);
            border-radius: 0.3rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5);
            width: 100%;
            height: 100%;
            pointer-events: auto;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 500;
        }

        .modal-body {
            position: relative;
            flex: 1 1 auto;
            padding: 0;
        }

        .modal-footer {
            padding: 0.75rem;
            border-top: 1px solid #e9ecef;
            text-align: right;
        }

        /* ===== MOBILE RESPONSIVE ===== */
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

            .container {
                padding: 0 0.75rem;
            }

            .grid-cols-3 {
                grid-template-columns: 1fr;
            }

            .card-body {
                padding: 1rem;
            }

            input, select, textarea, button {
                font-size: 16px !important;
            }

            .notification-dropdown {
                width: calc(100vw - 2rem);
                right: 0;
                left: 1rem;
            }

            .table-responsive {
                margin: 0 -1rem;
                padding: 0 1rem;
            }

            table {
                font-size: 0.875rem;
            }

            th, td {
                padding: 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .logo {
                font-size: 1rem;
            }

            .btn {
                padding: 0.625rem 1rem;
                font-size: 0.85rem;
            }

            th, td {
                padding: 0.375rem;
                font-size: 0.8rem;
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
                <li><a href="../matches/index.php"><i class="fas fa-handshake"></i> Matches</a></li>
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
                            <h4><i class="fas fa-bell"></i> Notifications</h4>
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
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </button>
                    <div class="profile-dropdown" id="profileDropdown">
                        <div class="profile-dropdown-header">
                            <p class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                            <p class="user-role"><?php echo ucfirst($user['role']); ?></p>
                        </div>
                        <div class="profile-dropdown-menu">
                            <a href="index.php" class="profile-dropdown-item">
                                <i class="fas fa-user-circle"></i>
                                <span>View Profile</span>
                            </a>
                            <a href="settings.php" class="profile-dropdown-item">
                                <i class="fas fa-sliders-h"></i>
                                <span>Settings</span>
                            </a>
                            <hr style="margin: 0.5rem 0; border: none; border-top: 1px solid #f0f0f0;">
                            <a href="../auth/logout.php" class="profile-dropdown-item logout">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="mb-4">
                <a href="index.php" class="text-primary" style="text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Back to Profile
                </a>
                <h1 style="margin: 0.5rem 0; font-size: 1.875rem; font-weight: 700;">Student Verification</h1>
                <p class="text-secondary">Verify your student status to access exclusive features and connect with verified mentors.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <?php endif; ?>

            <div class="grid grid-cols-3" style="gap: 2rem;">
                <div style="grid-column: span 2;">
                    <?php if ($user['is_verified']): ?>
                        <div class="card mb-4" style="border: 2px solid var(--success-color); background: #f0fdf4;">
                            <div class="card-body text-center" style="text-align: center;">
                                <div style="color: var(--success-color); font-size: 3rem; margin-bottom: 1rem;">✓</div>
                                <h3 style="color: var(--success-color); margin-bottom: 0.5rem; font-size: 1.25rem; font-weight: 700;">Verified Student</h3>
                                <p class="text-secondary">Your student status has been verified!</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="info-box">
                        <div class="info-box-title">
                            <i class="fas fa-info-circle"></i>
                            Why verify your student status?
                        </div>
                        <div class="info-box-content">
                            Get a verified badge on your profile, access to verified mentors only, priority in matching queue, exclusive student discounts, and enhanced credibility with mentors.
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">Upload Verification Document</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="upload_document" value="1">
                                
                                <div class="form-group">
                                    <label for="document_type" class="form-label">Document Type</label>
                                    <select id="document_type" name="document_type" class="form-select" required>
                                        <option value="">Select Document Type</option>
                                        <option value="student_id">Student ID Card</option>
                                        <option value="enrollment_cert">Enrollment Certificate</option>
                                        <option value="school_id">School/University ID</option>
                                        <option value="tuition_receipt">Tuition Receipt</option>
                                        <option value="class_schedule">Class Schedule</option>
                                        <option value="transcript">Academic Transcript</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description" class="form-label">Description (Optional)</label>
                                    <textarea id="description" name="description" class="form-input" rows="3" 
                                              placeholder="Provide additional details about your document..."></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="document" class="form-label">Upload File</label>
                                    <input type="file" id="document" name="document" class="form-input" 
                                           accept=".jpg,.jpeg,.png,.gif,.pdf" required>
                                    <small class="text-secondary">Accepted formats: JPG, PNG, GIF, PDF (Maximum size: 5MB)</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Upload Document
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Verification Benefits</h3>
                        </div>
                        <div class="card-body">
                            <ul style="margin-left: 1.5rem; line-height: 1.8;">
                                <li><strong>Verified Badge:</strong> Display your verified student status on your profile</li>
                                <li><strong>Priority Matching:</strong> Get matched with mentors faster</li>
                                <li><strong>Access to Verified Mentors:</strong> Connect with verified academic experts</li>
                                <li><strong>Exclusive Features:</strong> Unlock premium student-only features</li>
                                <li><strong>Increased Trust:</strong> Build credibility with the Study Buddy community</li>
                                <li><strong>Special Offers:</strong> Access student discounts and promotions</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">Verification Status</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Account Status:</strong><br>
                                <?php if ($user['is_verified']): ?>
                                    <span style="color: var(--success-color); display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                                        <i class="fas fa-check-circle"></i> Verified Student
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--warning-color); display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                                        <i class="fas fa-exclamation-triangle"></i> Unverified
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Documents Uploaded:</strong><br>
                                <span class="text-primary" style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                                    <i class="fas fa-file-alt"></i> <?php echo count($verification_docs); ?> document<?php echo count($verification_docs) !== 1 ? 's' : ''; ?>
                                </span>
                            </div>
                            
                            <div>
                                <strong>Approved Documents:</strong><br>
                                <span class="text-success" style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                                    <i class="fas fa-check"></i> <?php echo count($approved_docs); ?> approved
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Acceptable Documents</h3>
                        </div>
                        <div class="card-body">
                            <ul style="font-size: 0.875rem; line-height: 1.8; color: var(--text-secondary);">
                                <li>Current Student ID Card</li>
                                <li>Enrollment Certificate</li>
                                <li>School/University ID</li>
                                <li>Recent Tuition Receipt</li>
                                <li>Current Class Schedule</li>
                                <li>Academic Transcript</li>
                            </ul>
                            <div style="margin-top: 1rem; padding: 0.75rem; background: #fef3c7; border-radius: 6px; font-size: 0.85rem;">
                                <strong>Note:</strong> Documents must be current and clearly show your name and institution.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($verification_docs): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h3 class="card-title">Your Submitted Documents</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Document Type</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Submitted Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($verification_docs as $doc): ?>
                                        <tr>
                                            <td>
                                                <?php 
                                                $type_labels = [
                                                    'student_id' => 'Student ID',
                                                    'enrollment_cert' => 'Enrollment Cert',
                                                    'school_id' => 'School ID',
                                                    'tuition_receipt' => 'Tuition Receipt',
                                                    'class_schedule' => 'Class Schedule',
                                                    'transcript' => 'Transcript',
                                                    'other' => 'Other'
                                                ];
                                                $doc_title = $type_labels[$doc['document_type']] ?? ucfirst($doc['document_type']);
                                                echo $doc_title;
                                                ?>
                                            </td>
                                            <td>
                                                <?php echo $doc['description'] ? htmlspecialchars(substr($doc['description'], 0, 40)) . (strlen($doc['description']) > 40 ? '...' : '') : '-'; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status_colors = [
                                                    'approved' => ['bg' => '#dcfce7', 'text' => '#166534'],
                                                    'rejected' => ['bg' => '#fee2e2', 'text' => '#dc2626'],
                                                    'pending' => ['bg' => '#fef3c7', 'text' => '#d97706']
                                                ];
                                                $colors = $status_colors[$doc['status']] ?? ['bg' => '#f3f4f6', 'text' => '#6b7280'];
                                                ?>
                                                <span style="background: <?php echo $colors['bg']; ?>; color: <?php echo $colors['text']; ?>; padding: 0.25rem 0.75rem; border-radius: 0.5rem; font-size: 0.8rem; font-weight: 500; display: inline-flex; align-items: center; gap: 0.375rem;">
                                                    <?php if ($doc['status'] === 'approved'): ?>
                                                        <i class="fas fa-check-circle"></i>
                                                    <?php elseif ($doc['status'] === 'rejected'): ?>
                                                        <i class="fas fa-times-circle"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-clock"></i>
                                                    <?php endif; ?>
                                                    <?php echo ucfirst($doc['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($doc['created_at'])); ?></td>
                                            <td>
                                                <button type="button" 
                                                   data-path="../uploads/verification/<?php echo $doc['filename']; ?>"
                                                   data-title="<?php echo htmlspecialchars($doc_title); ?>"
                                                   onclick="openDocumentPreview(this)" 
                                                   class="btn btn-secondary" style="padding: 0.375rem 0.75rem; font-size: 0.8rem; text-decoration: none; min-height: auto;">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                        <?php if ($doc['status'] === 'rejected' && !empty($doc['admin_notes'])): ?>
                                            <tr>
                                                <td colspan="5" style="background: #fef2f2; padding: 0.75rem;">
                                                    <strong style="color: #dc2626;"><i class="fas fa-info-circle"></i> Rejection Reason:</strong>
                                                    <p style="margin: 0.25rem 0 0 0; color: #991b1b;"><?php echo htmlspecialchars($doc['admin_notes']); ?></p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="card-title">Frequently Asked Questions</h3>
                </div>
                <div class="card-body">
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <div>
                            <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--text-primary);">
                                <i class="fas fa-question-circle" style="color: var(--primary-color);"></i> How long does verification take?
                            </h4>
                            <p style="color: var(--text-secondary); margin: 0; line-height: 1.6;">
                                Our admin team reviews verification documents within 24-48 hours. You'll receive a notification once your document has been reviewed.
                            </p>
                        </div>

                        <div>
                            <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--text-primary);">
                                <i class="fas fa-question-circle" style="color: var(--primary-color);"></i> What if my document is rejected?
                            </h4>
                            <p style="color: var(--text-secondary); margin: 0; line-height: 1.6;">
                                If your document is rejected, you'll receive feedback explaining why. You can then upload a new document that meets the requirements.
                            </p>
                        </div>

                        <div>
                            <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--text-primary);">
                                <i class="fas fa-question-circle" style="color: var(--primary-color);"></i> Is my information secure?
                            </h4>
                            <p style="color: var(--text-secondary); margin: 0; line-height: 1.6;">
                                Yes! All verification documents are securely stored and only accessible by authorized admin personnel. We take your privacy seriously.
                            </p>
                        </div>

                        <div>
                            <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--text-primary);">
                                <i class="fas fa-question-circle" style="color: var(--primary-color);"></i> Can I upload multiple documents?
                            </h4>
                            <p style="color: var(--text-secondary); margin: 0; line-height: 1.6;">
                                Yes! You can upload multiple documents to increase your chances of verification. Having multiple forms of proof strengthens your application.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="documentPreviewModal" tabindex="-1" aria-labelledby="documentPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="documentPreviewModalLabel">Document Preview: <span id="previewModalTitle"></span></h5>
                    <button type="button" class="btn-close" onclick="closeModal('documentPreviewModal')" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="documentIframe" src="" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('documentPreviewModal')">Close Viewer</button>
                </div>
            </div>
        </div>
    </div>


    <script>
        let notificationDropdownOpen = false;
        let profileDropdownOpen = false;

        // NEW FUNCTION: Handles opening the document preview modal
        function openDocumentPreview(buttonElement) {
            const filePath = buttonElement.getAttribute('data-path');
            const title = buttonElement.getAttribute('data-title');
            
            if (!filePath) {
                console.error('Document file path is missing.');
                alert('Error: Document file path is missing.');
                return;
            }
            
            const modal = document.getElementById('documentPreviewModal');
            const iframe = document.getElementById('documentIframe');
            
            // Set the title
            document.getElementById('previewModalTitle').textContent = title;

            // Set the iframe source to display the document
            iframe.src = filePath;

            // Show the modal
            modal.classList.add('show');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent scrolling background
        }

        // NEW FUNCTION: Handles closing the document preview modal
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('show');
            modal.style.display = 'none';
            document.body.style.overflow = ''; // Restore scrolling
            // Clear iframe source to stop video/audio/memory use
            if (modalId === 'documentPreviewModal') {
                document.getElementById('documentIframe').src = '';
            }
        }
        
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
                
                // Close modal if clicking outside it
                document.getElementById('documentPreviewModal').addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal('documentPreviewModal');
                    }
                });
            }
        });

        function toggleNotifications(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('notificationDropdown');
            notificationDropdownOpen = !notificationDropdownOpen;
            
            if (notificationDropdownOpen) {
                dropdown.classList.add('show');
                document.getElementById('profileDropdown').classList.remove('show');
                profileDropdownOpen = false;
                loadNotifications();
            } else {
                dropdown.classList.remove('show');
            }
        }

        function toggleProfileMenu(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('profileDropdown');
            profileDropdownOpen = !profileDropdownOpen;
            
            if (profileDropdownOpen) {
                dropdown.classList.add('show');
                document.getElementById('notificationDropdown').classList.remove('show');
                notificationDropdownOpen = false;
            } else {
                dropdown.classList.remove('show');
            }
        }

        function loadNotifications() {
            fetch('../api/notifications.php')
                .then(response => response.json())
                .then(data => {
                    const list = document.getElementById('notificationList');
                    
                    if (!data.notifications || data.notifications.length === 0) {
                        list.innerHTML = '<div style="text-align: center; padding: 1.5rem; color: #999;"><i class="fas fa-bell-slash"></i><p style="margin-top: 0.5rem;">No notifications</p></div>';
                        return;
                    }
                    
                    list.innerHTML = data.notifications.slice(0, 6).map(notif => `
                        <div class="notification-item-dropdown ${!notif.is_read ? 'unread' : ''}" 
                             onclick="handleNotificationClick(${notif.id}, '${notif.link || ''}')">
                            <i class="fas ${getNotificationIcon(notif.type)}" style="color: ${getNotificationColor(notif.type)};"></i>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.25rem;">${escapeHtml(notif.title)}</div>
                                <div style="font-size: 0.8rem; color: #666;">${escapeHtml(notif.message)}</div>
                                <div style="font-size: 0.75rem; color: #999; margin-top: 0.25rem;">${timeAgo(notif.created_at)}</div>
                            </div>
                        </div>
                    `).join('');
                });
        }

        function handleNotificationClick(notificationId, link) {
            fetch('../api/notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'mark_read', notification_id: notificationId})
            }).then(() => {
                if (link) window.location.href = link;
                else loadNotifications();
            });
        }

        function getNotificationIcon(type) {
            const icons = {
                'session_scheduled': 'fa-calendar-check',
                'session_accepted': 'fa-check-circle',
                'session_rejected': 'fa-times-circle',
                'match_request': 'fa-handshake',
                'match_accepted': 'fa-user-check',
                'announcement': 'fa-megaphone',
                'verification_approved': 'fa-check-circle',
                'verification_rejected': 'fa-times-circle'
            };
            return icons[type] || 'fa-bell';
        }

        function getNotificationColor(type) {
            const colors = {
                'session_accepted': '#16a34a',
                'session_rejected': '#dc2626',
                'match_accepted': '#16a34a',
                'announcement': '#2563eb',
                'session_scheduled': '#2563eb',
                'match_request': '#2563eb',
                'verification_approved': '#16a34a',
                'verification_rejected': '#dc2626'
            };
            return colors[type] || '#666';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function timeAgo(dateString) {
            const date = new Date(dateString);
            const seconds = Math.floor((new Date() - date) / 1000);
            if (seconds < 60) return 'Just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
            if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
            return Math.floor(seconds / 604800) + 'w ago';
        }

        document.addEventListener('click', function(e) {
            if (notificationDropdownOpen && e.target.closest('.notification-bell') === null && e.target.closest('.notification-dropdown') === null) {
                document.getElementById('notificationDropdown').classList.remove('show');
                notificationDropdownOpen = false;
            }
            if (profileDropdownOpen && e.target.closest('.profile-menu') === null && e.target.closest('.profile-dropdown') === null) {
                document.getElementById('profileDropdown').classList.remove('show');
                profileDropdownOpen = false;
            }
        });

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
                                bell.innerHTML += `<span class="notification-badge">${data.unread_count}</span>`;
                            }
                        } else if (badge) {
                            badge.remove();
                        }
                    });
            }
        }, 30000);
    </script>
</body>
</html>