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

// Handle announcement actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'];
        
        try {
            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO announcements (title, message, type, target_audience, is_active, created_by) VALUES (?, ?, ?, ?, 1, ?)");
                $stmt->execute([
                    sanitize_input($_POST['title']),
                    sanitize_input($_POST['message']),
                    $_POST['type'],
                    $_POST['target_audience'],
                    $user['id']
                ]);
                $success = 'Announcement created successfully.';
            } elseif ($action === 'delete') {
                $stmt = $db->prepare("DELETE FROM announcements WHERE id = ?");
                $stmt->execute([(int)$_POST['announcement_id']]);
                $success = 'Announcement deleted successfully.';
            } elseif ($action === 'toggle') {
                $stmt = $db->prepare("UPDATE announcements SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([(int)$_POST['announcement_id']]);
                $success = 'Announcement status updated.';
            }
        } catch (Exception $e) {
            $error = 'Failed to perform action. Please try again.';
        }
    }
}

// Get all announcements
$announcements = $db->query("
    SELECT a.*, u.first_name, u.last_name
    FROM announcements a
    JOIN users u ON a.created_by = u.id
    ORDER BY a.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Admin</title>
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

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Announcements</h1>
                    <p class="text-muted">Create and manage platform-wide announcements.</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                    <i class="fas fa-plus me-2"></i> New Announcement
                </button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="row">
                <?php foreach ($announcements as $announcement): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-<?php echo $announcement['type'] === 'info' ? 'primary' : ($announcement['type'] === 'warning' ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst($announcement['type']); ?>
                                    </span>
                                    <span class="badge bg-secondary"><?php echo ucfirst($announcement['target_audience']); ?></span>
                                    <?php if ($announcement['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                        <button type="submit" class="btn btn-outline-secondary">
                                            <i class="fas fa-<?php echo $announcement['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this announcement?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($announcement['message'])); ?></p>
                                <div class="small text-muted">
                                    Created by <?php echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']); ?>
                                    on <?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($announcements)): ?>
                    <div class="col-12">
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-bullhorn fa-3x mb-3"></i>
                            <p>No announcements yet. Create one to get started.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

     Create Announcement Modal 
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Create Announcement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-control" rows="4" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-select" required>
                                <option value="info">Info</option>
                                <option value="warning">Warning</option>
                                <option value="alert">Alert</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Target Audience</label>
                            <select name="target_audience" class="form-select" required>
                                <option value="all">All Users</option>
                                <option value="students">Students Only</option>
                                <option value="mentors">Mentors Only</option>
                                <option value="peers">Peers Only</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
