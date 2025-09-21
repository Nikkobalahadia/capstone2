<?php
require_once '../config/config.php';

// Check if user is logged in and is admin
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$admin_user = get_logged_in_user();
if (!$admin_user || $admin_user['role'] !== 'admin') {
    redirect('dashboard.php');
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$user_id) {
    redirect('admin/users.php');
}

$db = getDB();
$error = '';
$success = '';

// Handle verification document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $document_type = sanitize_input($_POST['document_type']);
        
        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            $file_type = $_FILES['document']['type'];
            
            if (in_array($file_type, $allowed_types) && $_FILES['document']['size'] <= MAX_FILE_SIZE) {
                $upload_dir = '../uploads/verification/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
                $filename = 'verification_' . $user_id . '_' . $document_type . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['document']['tmp_name'], $upload_path)) {
                    // Store document info in database
                    $stmt = $db->prepare("INSERT INTO user_verification_documents (user_id, document_type, filename, uploaded_by, status) VALUES (?, ?, ?, ?, 'pending')");
                    $stmt->execute([$user_id, $document_type, $filename, $admin_user['id']]);
                    
                    $success = 'Verification document uploaded successfully.';
                } else {
                    $error = 'Failed to upload document.';
                }
            } else {
                $error = 'Invalid file type or size. Please upload JPG, PNG, GIF, or PDF files under 5MB.';
            }
        } else {
            $error = 'Please select a file to upload.';
        }
    }
}

// Get user details with extended information
$user_query = "
    SELECT u.*,
           COUNT(DISTINCT m1.id) as matches_as_student,
           COUNT(DISTINCT m2.id) as matches_as_mentor,
           COUNT(DISTINCT s.id) as completed_sessions,
           AVG(sr.rating) as avg_rating,
           COUNT(DISTINCT sr.id) as rating_count,
           COUNT(DISTINCT msg.id) as messages_sent
    FROM users u
    LEFT JOIN matches m1 ON u.id = m1.student_id AND m1.status = 'accepted'
    LEFT JOIN matches m2 ON u.id = m2.mentor_id AND m2.status = 'accepted'
    LEFT JOIN matches m ON (u.id = m.student_id OR u.id = m.mentor_id) AND m.status = 'accepted'
    LEFT JOIN sessions s ON m.id = s.match_id AND s.status = 'completed'
    LEFT JOIN session_ratings sr ON u.id = sr.rated_id
    LEFT JOIN messages msg ON u.id = msg.sender_id
    WHERE u.id = ?
    GROUP BY u.id
";

$stmt = $db->prepare($user_query);
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    redirect('admin/users.php');
}

// Get user subjects
$subjects_stmt = $db->prepare("SELECT * FROM user_subjects WHERE user_id = ? ORDER BY subject_name");
$subjects_stmt->execute([$user_id]);
$subjects = $subjects_stmt->fetchAll();

// Get user availability
$availability_stmt = $db->prepare("SELECT * FROM user_availability WHERE user_id = ? AND is_active = 1 ORDER BY FIELD(day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')");
$availability_stmt->execute([$user_id]);
$availability = $availability_stmt->fetchAll();

// Get verification documents
$docs_stmt = $db->prepare("SELECT * FROM user_verification_documents WHERE user_id = ? ORDER BY created_at DESC");
$docs_stmt->execute([$user_id]);
$verification_docs = $docs_stmt->fetchAll();

// Get recent activity
$activity_stmt = $db->prepare("SELECT * FROM user_activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$activity_stmt->execute([$user_id]);
$recent_activity = $activity_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?> - StudyConnect Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="dashboard.php" class="logo">StudyConnect Admin</a>
                <ul class="nav-links">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="users.php">Users</a></li>
                    <li><a href="matches.php">Matches</a></li>
                    <li><a href="sessions.php">Sessions</a></li>
                    <li><a href="reports.php">Reports</a></li>
                    <li><a href="../auth/logout.php" class="btn btn-outline">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="container">
            <div class="mb-4">
                <a href="users.php" class="text-primary" style="text-decoration: none;">← Back to Users</a>
                <h1 style="margin: 0.5rem 0;">User Profile Management</h1>
                <p class="text-secondary">Detailed view and verification tools for user account.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="grid grid-cols-3" style="gap: 2rem;">
                <!-- User Information -->
                <div style="grid-column: span 2;">
                    <!-- Basic Info Card -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">User Information</h3>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-2" style="gap: 2rem;">
                                <div>
                                    <div class="mb-3">
                                        <strong>Full Name:</strong><br>
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Username:</strong><br>
                                        @<?php echo htmlspecialchars($user['username']); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Email:</strong><br>
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Role:</strong><br>
                                        <span style="background: <?php echo $user['role'] === 'student' ? '#dbeafe' : '#dcfce7'; ?>; color: <?php echo $user['role'] === 'student' ? '#1e40af' : '#166534'; ?>; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.875rem;">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <div class="mb-3">
                                        <strong>Grade Level:</strong><br>
                                        <?php echo $user['grade_level'] ? htmlspecialchars($user['grade_level']) : 'Not specified'; ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Strand/Course:</strong><br>
                                        <?php echo $user['strand'] ? htmlspecialchars($user['strand']) : 'Not specified'; ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Location:</strong><br>
                                        <?php echo $user['location'] ? htmlspecialchars($user['location']) : 'Not specified'; ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Member Since:</strong><br>
                                        <?php echo date('F j, Y', strtotime($user['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($user['bio']): ?>
                                <div class="mt-4">
                                    <strong>Bio:</strong><br>
                                    <p style="margin-top: 0.5rem; padding: 1rem; background: #f8fafc; border-radius: 0.5rem;">
                                        <?php echo nl2br(htmlspecialchars($user['bio'])); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Subjects -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">Subjects & Expertise</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($subjects): ?>
                                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                    <?php foreach ($subjects as $subject): ?>
                                        <span style="background: var(--primary-color); color: white; padding: 0.5rem 1rem; border-radius: 1rem; font-size: 0.875rem;">
                                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                                            <small style="opacity: 0.8;">(<?php echo ucfirst($subject['proficiency_level']); ?>)</small>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-secondary">No subjects specified.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Availability -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">Availability Schedule</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($availability): ?>
                                <div class="grid grid-cols-2" style="gap: 1rem;">
                                    <?php foreach ($availability as $slot): ?>
                                        <div style="padding: 0.75rem; background: #f8fafc; border-radius: 0.5rem;">
                                            <strong><?php echo ucfirst($slot['day_of_week']); ?></strong><br>
                                            <span class="text-secondary">
                                                <?php echo date('g:i A', strtotime($slot['start_time'])); ?> - 
                                                <?php echo date('g:i A', strtotime($slot['end_time'])); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-secondary">No availability schedule set.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Verification Documents -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Verification Documents</h3>
                        </div>
                        <div class="card-body">
                            <!-- Upload Form -->
                            <form method="POST" enctype="multipart/form-data" style="margin-bottom: 2rem; padding: 1rem; background: #f8fafc; border-radius: 0.5rem;">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="upload_document" value="1">
                                
                                <div class="grid grid-cols-3" style="gap: 1rem; align-items: end;">
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label for="document_type" class="form-label">Document Type</label>
                                        <select id="document_type" name="document_type" class="form-select" required>
                                            <option value="id">Government ID</option>
                                            <option value="student_id">Student ID</option>
                                            <option value="diploma">Diploma/Certificate</option>
                                            <option value="transcript">Transcript</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label for="document" class="form-label">Upload File</label>
                                        <input type="file" id="document" name="document" class="form-input" accept=".jpg,.jpeg,.png,.gif,.pdf" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Upload</button>
                                </div>
                                
                                <small class="text-secondary">Accepted formats: JPG, PNG, GIF, PDF (max 5MB)</small>
                            </form>

                            <!-- Existing Documents -->
                            <?php if ($verification_docs): ?>
                                <div style="overflow-x: auto;">
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <thead>
                                            <tr style="border-bottom: 1px solid var(--border-color);">
                                                <th style="padding: 0.75rem; text-align: left;">Document Type</th>
                                                <th style="padding: 0.75rem; text-align: left;">Status</th>
                                                <th style="padding: 0.75rem; text-align: left;">Uploaded</th>
                                                <th style="padding: 0.75rem; text-align: left;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($verification_docs as $doc): ?>
                                                <tr style="border-bottom: 1px solid var(--border-color);">
                                                    <td style="padding: 0.75rem;"><?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?></td>
                                                    <td style="padding: 0.75rem;">
                                                        <span style="background: <?php echo $doc['status'] === 'approved' ? '#dcfce7' : ($doc['status'] === 'rejected' ? '#fecaca' : '#fef3c7'); ?>; color: <?php echo $doc['status'] === 'approved' ? '#166534' : ($doc['status'] === 'rejected' ? '#dc2626' : '#d97706'); ?>; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.875rem;">
                                                            <?php echo ucfirst($doc['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td style="padding: 0.75rem;"><?php echo date('M j, Y', strtotime($doc['created_at'])); ?></td>
                                                    <td style="padding: 0.75rem;">
                                                        <a href="../uploads/verification/<?php echo $doc['filename']; ?>" target="_blank" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.875rem; text-decoration: none;">View</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-secondary">No verification documents uploaded.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div>
                    <!-- Status & Actions -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">Account Status</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Verification Status:</strong><br>
                                <?php if ($user['is_verified']): ?>
                                    <span style="color: var(--success-color);">✓ Verified</span>
                                <?php else: ?>
                                    <span style="color: var(--warning-color);">⚠ Unverified</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-4">
                                <strong>Account Status:</strong><br>
                                <?php if ($user['is_active']): ?>
                                    <span style="color: var(--success-color);">● Active</span>
                                <?php else: ?>
                                    <span style="color: var(--error-color);">● Inactive</span>
                                <?php endif; ?>
                            </div>

                            <!-- Quick Actions -->
                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                <?php if (!$user['is_verified']): ?>
                                    <form method="POST" action="users.php" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="verify">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-success" style="width: 100%;">Verify User</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="users.php" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="unverify">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-warning" style="width: 100%;">Remove Verification</button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if ($user['is_active']): ?>
                                    <form method="POST" action="users.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to deactivate this user?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="deactivate">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-danger" style="width: 100%;">Deactivate Account</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="users.php" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="activate">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-success" style="width: 100%;">Activate Account</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Statistics -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">Activity Statistics</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Total Matches:</strong><br>
                                <?php echo ($user['matches_as_student'] + $user['matches_as_mentor']); ?>
                            </div>
                            <div class="mb-3">
                                <strong>Completed Sessions:</strong><br>
                                <?php echo $user['completed_sessions']; ?>
                            </div>
                            <div class="mb-3">
                                <strong>Messages Sent:</strong><br>
                                <?php echo $user['messages_sent']; ?>
                            </div>
                            <div class="mb-3">
                                <strong>Average Rating:</strong><br>
                                <?php if ($user['avg_rating']): ?>
                                    <?php echo number_format($user['avg_rating'], 1); ?>/5
                                    <small class="text-secondary">(<?php echo $user['rating_count']; ?> reviews)</small>
                                <?php else: ?>
                                    <span class="text-secondary">No ratings yet</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Activity</h3>
                        </div>
                        <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                            <?php if ($recent_activity): ?>
                                <?php foreach (array_slice($recent_activity, 0, 10) as $activity): ?>
                                    <div style="padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
                                        <div class="text-sm">
                                            <?php echo ucfirst(str_replace('_', ' ', $activity['action'])); ?>
                                        </div>
                                        <div class="text-sm text-secondary">
                                            <?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-secondary">No recent activity.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
