<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
session_start();
$user = get_logged_in_user();

if (!$user || $user['role'] !== 'admin') {
    redirect('auth/login.php');
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        $settings = [
            'platform_name',
            'platform_tagline',
            'facebook_url',
            'twitter_url',
            'instagram_url',
            'linkedin_url',
            'contact_email',
            'contact_phone',
            'show_social_links',
            'copyright_text'
        ];
        
        $stmt = $db->prepare("INSERT INTO footer_settings (setting_key, setting_value, updated_by) 
                             VALUES (?, ?, ?) 
                             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)");
        
        foreach ($settings as $key) {
            $value = $_POST[$key] ?? '';
            $stmt->execute([$key, $value, $user['id']]);
        }
        
        $db->commit();
        $success = 'Footer settings updated successfully!';
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Error updating settings: ' . $e->getMessage();
    }
}

// Fetch current settings
$stmt = $db->query("SELECT setting_key, setting_value FROM footer_settings");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Footer Settings - StudyConnect Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-purple: #7c3aed;
            --dark-purple: #5b21b6;
            --light-purple: #a78bfa;
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-purple) 0%, var(--dark-purple) 100%);
        }
        
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: var(--primary-purple);
            color: white;
            font-weight: 600;
        }
        
        .btn-primary {
            background-color: var(--primary-purple);
            border-color: var(--primary-purple);
        }
        
        .btn-primary:hover {
            background-color: var(--dark-purple);
            border-color: var(--dark-purple);
        }
        
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        
        .section-divider {
            border-top: 2px solid #e9ecef;
            margin: 2rem 0;
        }
    </style>
</head>
<body>
     Navigation 
    <nav class="navbar navbar-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
            </a>
            <h4 class="text-white mb-0">Admin Panel</h4>
        </div>
    </nav>

    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">
                    <i class="fas fa-cog text-purple"></i> Footer Settings
                </h1>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                     Platform Information 
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-info-circle me-2"></i>Platform Information
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="platform_name" class="form-label">Platform Name</label>
                                <input type="text" class="form-control" id="platform_name" name="platform_name" 
                                       value="<?php echo htmlspecialchars($settings['platform_name'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="platform_tagline" class="form-label">Platform Tagline</label>
                                <textarea class="form-control" id="platform_tagline" name="platform_tagline" 
                                          rows="2"><?php echo htmlspecialchars($settings['platform_tagline'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="copyright_text" class="form-label">Copyright Text</label>
                                <input type="text" class="form-control" id="copyright_text" name="copyright_text" 
                                       value="<?php echo htmlspecialchars($settings['copyright_text'] ?? ''); ?>" required>
                                <small class="form-text text-muted">The year will be added automatically</small>
                            </div>
                        </div>
                    </div>

                     Contact Information 
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-envelope me-2"></i>Contact Information
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="contact_email" class="form-label">Contact Email</label>
                                <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                       value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="contact_phone" class="form-label">Contact Phone</label>
                                <input type="text" class="form-control" id="contact_phone" name="contact_phone" 
                                       value="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                     Social Media Links 
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-share-alt me-2"></i>Social Media Links
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="show_social_links" 
                                           name="show_social_links" value="1" 
                                           <?php echo ($settings['show_social_links'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="show_social_links">
                                        Show Social Media Links in Footer
                                    </label>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="facebook_url" class="form-label">
                                        <i class="fab fa-facebook text-primary me-2"></i>Facebook URL
                                    </label>
                                    <input type="url" class="form-control" id="facebook_url" name="facebook_url" 
                                           value="<?php echo htmlspecialchars($settings['facebook_url'] ?? ''); ?>" 
                                           placeholder="https://facebook.com/yourpage">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="twitter_url" class="form-label">
                                        <i class="fab fa-twitter text-info me-2"></i>Twitter URL
                                    </label>
                                    <input type="url" class="form-control" id="twitter_url" name="twitter_url" 
                                           value="<?php echo htmlspecialchars($settings['twitter_url'] ?? ''); ?>" 
                                           placeholder="https://twitter.com/yourhandle">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="instagram_url" class="form-label">
                                        <i class="fab fa-instagram text-danger me-2"></i>Instagram URL
                                    </label>
                                    <input type="url" class="form-control" id="instagram_url" name="instagram_url" 
                                           value="<?php echo htmlspecialchars($settings['instagram_url'] ?? ''); ?>" 
                                           placeholder="https://instagram.com/yourprofile">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="linkedin_url" class="form-label">
                                        <i class="fab fa-linkedin text-primary me-2"></i>LinkedIn URL
                                    </label>
                                    <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" 
                                           value="<?php echo htmlspecialchars($settings['linkedin_url'] ?? ''); ?>" 
                                           placeholder="https://linkedin.com/company/yourcompany">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mb-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>Save Footer Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
