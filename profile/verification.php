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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Documents - StudyConnect</title>
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
                    <li><a href="index.php">Profile</a></li>
                    <li><a href="../matches/index.php">Matches</a></li>
                    <li><a href="../sessions/index.php">Sessions</a></li>
                    <li><a href="../messages/index.php">Messages</a></li>
                    <li><a href="../auth/logout.php" class="btn btn-outline">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="container">
            <div class="mb-4">
                <a href="index.php" class="text-primary" style="text-decoration: none;">← Back to Profile</a>
                <h1 style="margin: 0.5rem 0;">Verification Documents</h1>
                <p class="text-secondary">Upload documents to verify your identity and expertise as a mentor.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="grid grid-cols-3" style="gap: 2rem;">
                <!-- Upload Form -->
                <div style="grid-column: span 2;">
                    <!-- Added verification status banner -->
                    <?php if ($user['is_verified']): ?>
                        <div class="card mb-4" style="border: 2px solid var(--success-color); background: #f0fdf4;">
                            <div class="card-body text-center">
                                <div style="color: var(--success-color); font-size: 3rem; margin-bottom: 1rem;">✓</div>
                                <h3 style="color: var(--success-color); margin-bottom: 0.5rem;">Verified Mentor</h3>
                                <p class="text-secondary">Your account has been verified. You can continue uploading additional documents if needed.</p>
                            </div>
                        </div>
                    <?php elseif (count($approved_docs) > 0): ?>
                        <div class="card mb-4" style="border: 2px solid var(--warning-color); background: #fffbeb;">
                            <div class="card-body text-center">
                                <div style="color: var(--warning-color); font-size: 3rem; margin-bottom: 1rem;">⏳</div>
                                <h3 style="color: var(--warning-color); margin-bottom: 0.5rem;">Verification In Progress</h3>
                                <p class="text-secondary">Some of your documents have been approved. Full verification is pending admin review.</p>
                            </div>
                        </div>
                    <?php endif; ?>

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
                                        <option value="id">Government ID (Driver's License, Passport, etc.)</option>
                                        <option value="student_id">Student ID</option>
                                        <option value="diploma">Diploma/Certificate</option>
                                        <option value="transcript">Academic Transcript</option>
                                        <option value="professional_cert">Professional Certification</option>
                                        <option value="expertise_proof">Proof of Expertise (Portfolio, Awards, etc.)</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description" class="form-label">Description (Optional)</label>
                                    <textarea id="description" name="description" class="form-input" rows="3" 
                                              placeholder="Provide additional context about this document..."></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="document" class="form-label">Upload File</label>
                                    <input type="file" id="document" name="document" class="form-input" 
                                           accept=".jpg,.jpeg,.png,.gif,.pdf" required>
                                    <small class="text-secondary">Accepted formats: JPG, PNG, GIF, PDF (max 5MB)</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Upload Document</button>
                            </form>
                        </div>
                    </div>

                    <!-- Document Guidelines -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Verification Guidelines</h3>
                        </div>
                        <div class="card-body">
                            <h4 class="font-semibold mb-2">Required Documents for Mentors:</h4>
                            <ul style="margin-left: 1.5rem; margin-bottom: 1.5rem;">
                                <li><strong>Government ID:</strong> Valid photo identification (Driver's License, Passport, National ID)</li>
                                <li><strong>Academic Credentials:</strong> Diploma, transcript, or certificate relevant to your expertise</li>
                                <li><strong>Proof of Expertise:</strong> Professional certifications, portfolio, awards, or work samples</li>
                            </ul>
                            
                            <h4 class="font-semibold mb-2">Document Requirements:</h4>
                            <ul style="margin-left: 1.5rem;">
                                <li>Clear, readable images or PDFs</li>
                                <li>All text and details must be visible</li>
                                <li>No edited or altered documents</li>
                                <li>File size must be under 5MB</li>
                                <li>Documents must be current and valid</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div>
                    <!-- Verification Status -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">Verification Status</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Account Status:</strong><br>
                                <?php if ($user['is_verified']): ?>
                                    <span style="color: var(--success-color);">✓ Verified Mentor</span>
                                <?php else: ?>
                                    <span style="color: var(--warning-color);">⚠ Unverified</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Documents Uploaded:</strong><br>
                                <span class="text-primary"><?php echo count($verification_docs); ?></span>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Approved Documents:</strong><br>
                                <span class="text-success"><?php echo count($approved_docs); ?></span>
                            </div>
                            
                            <?php if (!$user['is_verified'] && count($verification_docs) === 0): ?>
                                <div class="alert alert-info" style="font-size: 0.875rem;">
                                    Upload at least 2-3 verification documents to begin the verification process.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Benefits of Verification -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Benefits of Verification</h3>
                        </div>
                        <div class="card-body">
                            <ul style="margin-left: 1rem; font-size: 0.875rem;">
                                <li>Verified badge on your profile</li>
                                <li>Higher visibility in search results</li>
                                <li>Increased trust from students</li>
                                <li>Access to premium mentor features</li>
                                <li>Priority matching with students</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Uploaded Documents -->
            <?php if ($verification_docs): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h3 class="card-title">Your Uploaded Documents</h3>
                    </div>
                    <div class="card-body">
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <th style="padding: 0.75rem; text-align: left;">Document Type</th>
                                        <th style="padding: 0.75rem; text-align: left;">Description</th>
                                        <th style="padding: 0.75rem; text-align: left;">Status</th>
                                        <th style="padding: 0.75rem; text-align: left;">Uploaded</th>
                                        <th style="padding: 0.75rem; text-align: left;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($verification_docs as $doc): ?>
                                        <tr style="border-bottom: 1px solid var(--border-color);">
                                            <td style="padding: 0.75rem;">
                                                <?php 
                                                $type_labels = [
                                                    'id' => 'Government ID',
                                                    'student_id' => 'Student ID',
                                                    'diploma' => 'Diploma/Certificate',
                                                    'transcript' => 'Academic Transcript',
                                                    'professional_cert' => 'Professional Certification',
                                                    'expertise_proof' => 'Proof of Expertise',
                                                    'other' => 'Other'
                                                ];
                                                echo $type_labels[$doc['document_type']] ?? ucfirst(str_replace('_', ' ', $doc['document_type']));
                                                ?>
                                            </td>
                                            <td style="padding: 0.75rem;">
                                                <?php echo $doc['description'] ? htmlspecialchars($doc['description']) : '-'; ?>
                                            </td>
                                            <td style="padding: 0.75rem;">
                                                <span style="background: <?php echo $doc['status'] === 'approved' ? '#dcfce7' : ($doc['status'] === 'rejected' ? '#fecaca' : '#fef3c7'); ?>; color: <?php echo $doc['status'] === 'approved' ? '#166534' : ($doc['status'] === 'rejected' ? '#dc2626' : '#d97706'); ?>; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.875rem;">
                                                    <?php echo ucfirst($doc['status']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 0.75rem;"><?php echo date('M j, Y', strtotime($doc['created_at'])); ?></td>
                                            <td style="padding: 0.75rem;">
                                                <a href="../uploads/verification/<?php echo $doc['filename']; ?>" target="_blank" 
                                                   class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.875rem; text-decoration: none;">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
