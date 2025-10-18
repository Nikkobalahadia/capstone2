<?php
require_once '../config/config.php';

// Check if user is logged in and is admin
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user || $user['role'] !== 'admin') {
    redirect('dashboard.php');
}

$db = getDB();
$error = '';
$success = '';

try {
    $db->exec("CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    error_log("Failed to create system_settings table: " . $e->getMessage());
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        try {
            $settings = [
                'platform_name' => sanitize_input($_POST['platform_name']),
                'platform_tagline' => sanitize_input($_POST['platform_tagline']),
                'support_email' => sanitize_input($_POST['support_email']),
                'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
                'allow_registrations' => isset($_POST['allow_registrations']) ? '1' : '0',
                'require_email_verification' => isset($_POST['require_email_verification']) ? '1' : '0',
                'require_mentor_verification' => isset($_POST['require_mentor_verification']) ? '1' : '0',
                'max_matches_per_user' => (int)$_POST['max_matches_per_user'],
                'session_duration_default' => (int)$_POST['session_duration_default'],
                'enable_messaging' => isset($_POST['enable_messaging']) ? '1' : '0',
                'enable_video_sessions' => isset($_POST['enable_video_sessions']) ? '1' : '0',
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $value, $value]);
            }
            
            $success = 'Settings updated successfully.';
        } catch (Exception $e) {
            $error = 'Failed to update settings. Please try again.';
        }
    }
}

// Get current settings
$settings = [];
try {
    $settings_query = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $settings_query->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Table might not exist yet, use defaults
    $settings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; position: fixed; width: 250px; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; border-radius: 8px; margin: 4px 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } .sidebar { display: none; } }
    </style>
</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?>
    <?php include '../includes/admin-header.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">System Settings</h1>
                    <p class="text-muted">Configure platform settings and features.</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">General Settings</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Platform Name</label>
                                <input type="text" name="platform_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['platform_name'] ?? 'Study Mentorship Platform'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Platform Tagline</label>
                                <input type="text" name="platform_tagline" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['platform_tagline'] ?? 'Connect, Learn, Succeed Together'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Support Email</label>
                                <input type="email" name="support_email" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['support_email'] ?? 'support@studyplatform.com'); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Platform Features</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintenance_mode"
                                   <?php echo ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="maintenance_mode">
                                <strong>Maintenance Mode</strong>
                                <div class="small text-muted">Disable platform access for non-admin users</div>
                            </label>
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="allow_registrations" id="allow_registrations"
                                   <?php echo ($settings['allow_registrations'] ?? '1') === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="allow_registrations">
                                <strong>Allow New Registrations</strong>
                                <div class="small text-muted">Enable new users to register</div>
                            </label>
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="require_email_verification" id="require_email_verification"
                                   <?php echo ($settings['require_email_verification'] ?? '1') === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="require_email_verification">
                                <strong>Require Email Verification</strong>
                                <div class="small text-muted">Users must verify email before accessing platform</div>
                            </label>
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="require_mentor_verification" id="require_mentor_verification"
                                   <?php echo ($settings['require_mentor_verification'] ?? '1') === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="require_mentor_verification">
                                <strong>Require Mentor Verification</strong>
                                <div class="small text-muted">Mentors must be verified by admin before matching</div>
                            </label>
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="enable_messaging" id="enable_messaging"
                                   <?php echo ($settings['enable_messaging'] ?? '1') === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="enable_messaging">
                                <strong>Enable Messaging</strong>
                                <div class="small text-muted">Allow users to send messages to each other</div>
                            </label>
                        </div>
                        
                        
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Platform Limits</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Max Matches Per User</label>
                                <input type="number" name="max_matches_per_user" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['max_matches_per_user'] ?? '10'); ?>" min="1" max="100">
                                <div class="small text-muted">Maximum number of active matches a user can have</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Default Session Duration (minutes)</label>
                                <input type="number" name="session_duration_default" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['session_duration_default'] ?? '60'); ?>" min="15" max="240" step="15">
                                <div class="small text-muted">Default duration for new sessions</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
