<?php
require_once '../config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$user = get_logged_in_user();
if (!$user || $user['role'] !== 'peer') {
    redirect('dashboard.php');
}

$error = '';
$success = '';
$db = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        try {
            $db->beginTransaction();
            
            // Clear existing preferences
            $clear_stmt = $db->prepare("DELETE FROM peer_subject_preferences WHERE user_id = ?");
            $clear_stmt->execute([$user['id']]);
            
            // Process each subject preference
            if (isset($_POST['subjects']) && is_array($_POST['subjects'])) {
                $insert_stmt = $db->prepare("
                    INSERT INTO peer_subject_preferences 
                    (user_id, subject_name, can_teach, can_learn, teaching_proficiency, learning_level) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($_POST['subjects'] as $subject_data) {
                    $subject_name = sanitize_input($subject_data['name']);
                    $can_teach = isset($subject_data['can_teach']) ? 1 : 0;
                    $can_learn = isset($subject_data['can_learn']) ? 1 : 0;
                    $teaching_proficiency = $can_teach ? sanitize_input($subject_data['teaching_level']) : null;
                    $learning_level = $can_learn ? sanitize_input($subject_data['learning_level']) : null;
                    
                    if ($can_teach || $can_learn) {
                        $insert_stmt->execute([
                            $user['id'], 
                            $subject_name, 
                            $can_teach, 
                            $can_learn, 
                            $teaching_proficiency, 
                            $learning_level
                        ]);
                    }
                }
            }
            
            $db->commit();
            $success = 'Peer preferences updated successfully!';
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Failed to update preferences. Please try again.';
        }
    }
}

// Get current preferences
$prefs_stmt = $db->prepare("SELECT * FROM peer_subject_preferences WHERE user_id = ?");
$prefs_stmt->execute([$user['id']]);
$current_preferences = $prefs_stmt->fetchAll();

// Get all available subjects from user_subjects table
$subjects_stmt = $db->prepare("SELECT DISTINCT subject_name FROM user_subjects ORDER BY subject_name");
$subjects_stmt->execute();
$available_subjects = $subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

// Convert current preferences to associative array for easier access
$prefs_by_subject = [];
foreach ($current_preferences as $pref) {
    $prefs_by_subject[$pref['subject_name']] = $pref;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peer Learning Preferences - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main style="padding: 2rem 0;">
        <div class="container">
            <div class="mb-4">
                <h1>Peer Learning Preferences</h1>
                <p class="text-secondary">Configure which subjects you can teach and which you'd like to learn. This helps us match you with the right study partners.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div id="subjects-container">
                            <?php if (empty($available_subjects)): ?>
                                <p class="text-secondary">No subjects available. Please add some subjects to your profile first.</p>
                                <a href="subjects.php" class="btn btn-primary">Add Subjects</a>
                            <?php else: ?>
                                <?php foreach ($available_subjects as $subject): ?>
                                    <?php $pref = $prefs_by_subject[$subject] ?? null; ?>
                                    <div class="subject-preference-row" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem;">
                                        <h4 style="margin-bottom: 1rem;"><?php echo htmlspecialchars($subject); ?></h4>
                                        
                                        <input type="hidden" name="subjects[<?php echo htmlspecialchars($subject); ?>][name]" value="<?php echo htmlspecialchars($subject); ?>">
                                        
                                        <div class="grid grid-cols-2" style="gap: 2rem;">
                                            <!-- Teaching Section -->
                                            <div>
                                                <label style="display: flex; align-items: center; margin-bottom: 1rem;">
                                                    <input type="checkbox" name="subjects[<?php echo htmlspecialchars($subject); ?>][can_teach]" 
                                                           <?php echo ($pref && $pref['can_teach']) ? 'checked' : ''; ?>
                                                           onchange="toggleTeachingLevel('<?php echo htmlspecialchars($subject); ?>', this.checked)"
                                                           style="margin-right: 0.5rem;">
                                                    <strong>I can teach this subject</strong>
                                                </label>
                                                
                                                <div id="teaching-level-<?php echo htmlspecialchars($subject); ?>" 
                                                     style="<?php echo (!$pref || !$pref['can_teach']) ? 'display: none;' : ''; ?>">
                                                    <label class="form-label">My teaching level:</label>
                                                    <select name="subjects[<?php echo htmlspecialchars($subject); ?>][teaching_level]" class="form-select">
                                                        <option value="beginner" <?php echo ($pref && $pref['teaching_proficiency'] === 'beginner') ? 'selected' : ''; ?>>Beginner</option>
                                                        <option value="intermediate" <?php echo ($pref && $pref['teaching_proficiency'] === 'intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                                                        <option value="advanced" <?php echo ($pref && $pref['teaching_proficiency'] === 'advanced') ? 'selected' : ''; ?>>Advanced</option>
                                                        <option value="expert" <?php echo ($pref && $pref['teaching_proficiency'] === 'expert') ? 'selected' : ''; ?>>Expert</option>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <!-- Learning Section -->
                                            <div>
                                                <label style="display: flex; align-items: center; margin-bottom: 1rem;">
                                                    <input type="checkbox" name="subjects[<?php echo htmlspecialchars($subject); ?>][can_learn]" 
                                                           <?php echo ($pref && $pref['can_learn']) ? 'checked' : ''; ?>
                                                           onchange="toggleLearningLevel('<?php echo htmlspecialchars($subject); ?>', this.checked)"
                                                           style="margin-right: 0.5rem;">
                                                    <strong>I want to learn this subject</strong>
                                                </label>
                                                
                                                <div id="learning-level-<?php echo htmlspecialchars($subject); ?>" 
                                                     style="<?php echo (!$pref || !$pref['can_learn']) ? 'display: none;' : ''; ?>">
                                                    <label class="form-label">My current level:</label>
                                                    <select name="subjects[<?php echo htmlspecialchars($subject); ?>][learning_level]" class="form-select">
                                                        <option value="beginner" <?php echo ($pref && $pref['learning_level'] === 'beginner') ? 'selected' : ''; ?>>Beginner</option>
                                                        <option value="intermediate" <?php echo ($pref && $pref['learning_level'] === 'intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                                                        <option value="advanced" <?php echo ($pref && $pref['learning_level'] === 'advanced') ? 'selected' : ''; ?>>Advanced</option>
                                                        <option value="expert" <?php echo ($pref && $pref['learning_level'] === 'expert') ? 'selected' : ''; ?>>Expert</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <div style="margin-top: 2rem;">
                                    <button type="submit" class="btn btn-primary">Save Preferences</button>
                                    <a href="index.php" class="btn btn-secondary">Back to Profile</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        function toggleTeachingLevel(subject, checked) {
            const element = document.getElementById('teaching-level-' + subject);
            element.style.display = checked ? 'block' : 'none';
        }
        
        function toggleLearningLevel(subject, checked) {
            const element = document.getElementById('learning-level-' + subject);
            element.style.display = checked ? 'block' : 'none';
        }
    </script>
</body>
</html>
