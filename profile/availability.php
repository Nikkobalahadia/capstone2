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
        $availability_data = $_POST['availability'] ?? [];
        
        try {
            $db = getDB();
            $db->beginTransaction();
            
            // Clear existing availability
            $clear_stmt = $db->prepare("DELETE FROM user_availability WHERE user_id = ?");
            $clear_stmt->execute([$user['id']]);
            
            // Add new availability
            $insert_stmt = $db->prepare("INSERT INTO user_availability (user_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)");
            
            foreach ($availability_data as $day => $times) {
                if (!empty($times['start']) && !empty($times['end'])) {
                    $insert_stmt->execute([$user['id'], $day, $times['start'], $times['end']]);
                }
            }
            
            $db->commit();
            $success = 'Availability updated successfully!';
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Failed to update availability. Please try again.';
        }
    }
}

// Get existing availability
$db = getDB();
$availability_stmt = $db->prepare("SELECT day_of_week, start_time, end_time FROM user_availability WHERE user_id = ? AND is_active = 1");
$availability_stmt->execute([$user['id']]);
$existing_availability = [];
while ($row = $availability_stmt->fetch()) {
    $existing_availability[$row['day_of_week']] = [
        'start' => $row['start_time'],
        'end' => $row['end_time']
    ];
}

$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Availability Schedule - StudyConnect</title>
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
                    <li><a href="../auth/logout.php" class="btn btn-outline">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="container">
            <div class="form-container" style="max-width: 600px;">
                <h2 class="text-center mb-4">Set Your Availability</h2>
                <p class="text-center text-secondary mb-4">Let others know when you're available for study sessions.</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <?php foreach ($days as $day): ?>
                        <div class="form-group">
                            <label class="form-label font-medium"><?php echo ucfirst($day); ?></label>
                            <div class="grid grid-cols-2" style="gap: 1rem;">
                                <div>
                                    <label for="<?php echo $day; ?>_start" class="form-label text-sm">Start Time</label>
                                    <input type="time" id="<?php echo $day; ?>_start" name="availability[<?php echo $day; ?>][start]" 
                                           class="form-input" 
                                           value="<?php echo isset($existing_availability[$day]) ? $existing_availability[$day]['start'] : ''; ?>">
                                </div>
                                <div>
                                    <label for="<?php echo $day; ?>_end" class="form-label text-sm">End Time</label>
                                    <input type="time" id="<?php echo $day; ?>_end" name="availability[<?php echo $day; ?>][end]" 
                                           class="form-input"
                                           value="<?php echo isset($existing_availability[$day]) ? $existing_availability[$day]['end'] : ''; ?>">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="alert" style="background-color: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe;">
                        <strong>Tip:</strong> Leave time fields empty for days when you're not available. You can always update this later.
                    </div>
                    
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">Save Availability</button>
                        <a href="index.php" class="btn btn-secondary" style="flex: 1; text-align: center;">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
