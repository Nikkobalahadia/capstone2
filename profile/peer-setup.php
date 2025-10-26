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
$db = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        try {
            $db->beginTransaction();
            
            // Update user profile with peer tutoring info
            $learning_goals = sanitize_input($_POST['learning_goals']);
            $preferred_learning_style = sanitize_input($_POST['preferred_learning_style']);
            $teaching_style = sanitize_input($_POST['teaching_style']);
            
            $update_stmt = $db->prepare("
                UPDATE users 
                SET learning_goals = ?, preferred_learning_style = ?, teaching_style = ?
                WHERE id = ?
            ");
            $update_stmt->execute([$learning_goals, $preferred_learning_style, $teaching_style, $user['id']]);
            
            // Clear existing help intentions
            $clear_stmt = $db->prepare("DELETE FROM user_help_intentions WHERE user_id = ?");
            $clear_stmt->execute([$user['id']]);
            
            // Process subjects where user can offer help
            if (!empty($_POST['offer_help_subjects'])) {
                foreach ($_POST['offer_help_subjects'] as $subject) {
                    $proficiency = $_POST['offer_proficiency'][$subject] ?? 'intermediate';
                    $description = $_POST['offer_description'][$subject] ?? '';
                    
                    $offer_stmt = $db->prepare("
                        INSERT INTO user_help_intentions 
                        (user_id, subject_name, intention_type, proficiency_level, description) 
                        VALUES (?, ?, 'offer_help', ?, ?)
                    ");
                    $offer_stmt->execute([$user['id'], $subject, $proficiency, $description]);
                }
            }
            
            // Process subjects where user needs help
            if (!empty($_POST['need_help_subjects'])) {
                foreach ($_POST['need_help_subjects'] as $subject) {
                    $current_level = $_POST['need_level'][$subject] ?? 'beginner';
                    $description = $_POST['need_description'][$subject] ?? '';
                    
                    $need_stmt = $db->prepare("
                        INSERT INTO user_help_intentions 
                        (user_id, subject_name, intention_type, proficiency_level, description) 
                        VALUES (?, ?, 'need_help', ?, ?)
                    ");
                    $need_stmt->execute([$user['id'], $subject, $current_level, $description]);
                }
            }
            
            $db->commit();
            $success = 'Your peer tutoring profile has been set up successfully!';
            
            // Redirect to dashboard after successful setup
            header('Location: ../dashboard.php');
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Peer setup error: " . $e->getMessage());
            $error = 'Failed to save your profile. Please try again.';
        }
    }
}

// Get available subjects for dropdowns
$subjects = [
    'Mathematics', 'Physics', 'Chemistry', 'Biology', 'English', 'History', 
    'Geography', 'Computer Science', 'Economics', 'Psychology', 'Art', 
    'Music', 'Physical Education', 'Foreign Languages', 'Literature'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peer Tutoring Setup - Study Buddy</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .setup-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        .intention-section {
            background: #f8fafc;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary-color);
        }
        .subject-item {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .emoji-button {
            font-size: 2rem;
            padding: 1rem 2rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
            margin: 0.5rem;
        }
        .emoji-button:hover {
            border-color: var(--primary-color);
            background: #f0f9ff;
        }
        .emoji-button.selected {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main style="padding: 2rem 0;">
        <div class="container" style="max-width: 800px;">
            <div class="setup-card">
                <h1>🎓 Peer Tutoring Setup</h1>
                <p style="font-size: 1.1rem; margin-top: 1rem; opacity: 0.9;">
                    Connect with classmates for learning support. You can share your knowledge or ask for help in subjects you find challenging.
                </p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                <!-- Learning Goals Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">📚 Your Learning Journey</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="learning_goals" class="form-label">What are your learning goals?</label>
                            <textarea id="learning_goals" name="learning_goals" class="form-input" rows="3" 
                                      placeholder="Describe what you want to achieve through peer tutoring..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="preferred_learning_style" class="form-label">How do you learn best?</label>
                            <select id="preferred_learning_style" name="preferred_learning_style" class="form-select">
                                <option value="">Select your preferred style</option>
                                <option value="visual">Visual (diagrams, charts, images)</option>
                                <option value="auditory">Auditory (discussions, explanations)</option>
                                <option value="kinesthetic">Hands-on (practice, activities)</option>
                                <option value="reading">Reading/Writing (notes, texts)</option>
                                <option value="mixed">Mixed approach</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Offer Help Section -->
                <div class="intention-section">
                    <h3 style="color: var(--success-color); margin-bottom: 1rem;">
                        ✅ Offer Help - Teach or guide your peers
                    </h3>
                    <p class="text-secondary mb-3">Select subjects you're confident in and can help others with:</p>
                    
                    <div class="form-group">
                        <label for="teaching_style" class="form-label">Your teaching/helping style:</label>
                        <textarea id="teaching_style" name="teaching_style" class="form-input" rows="2" 
                                  placeholder="Describe how you like to help others learn..."></textarea>
                    </div>
                    
                    <div id="offer-help-subjects">
                        <button type="button" class="btn btn-outline" onclick="addOfferSubject()">+ Add Subject You Can Help With</button>
                    </div>
                </div>

                <!-- Need Help Section -->
                <div class="intention-section">
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem;">
                        🙋 Need Help - Ask for tutoring from classmates
                    </h3>
                    <p class="text-secondary mb-3">Select subjects where you'd like support from your peers:</p>
                    
                    <div id="need-help-subjects">
                        <button type="button" class="btn btn-outline" onclick="addNeedSubject()">+ Add Subject You Need Help With</button>
                    </div>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary" style="padding: 1rem 2rem; font-size: 1.1rem;">
                        Complete Setup & Start Connecting
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        const subjects = <?php echo json_encode($subjects); ?>;
        let offerCount = 0;
        let needCount = 0;

        function addOfferSubject() {
            offerCount++;
            const container = document.getElementById('offer-help-subjects');
            const div = document.createElement('div');
            div.className = 'subject-item';
            div.innerHTML = `
                <div class="grid grid-cols-3" style="gap: 1rem; align-items: end;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Subject</label>
                        <select name="offer_help_subjects[]" class="form-select" required>
                            <option value="">Select subject</option>
                            ${subjects.map(s => `<option value="${s}">${s}</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Your Level</label>
                        <select name="offer_proficiency[${offerCount}]" class="form-select" required>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                            <option value="expert">Expert</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">Remove</button>
                </div>
                <div class="form-group mt-2">
                    <label class="form-label">How can you help? (Optional)</label>
                    <input type="text" name="offer_description[${offerCount}]" class="form-input" 
                           placeholder="e.g., I can explain concepts clearly and help with homework">
                </div>
            `;
            container.insertBefore(div, container.lastElementChild);
        }

        function addNeedSubject() {
            needCount++;
            const container = document.getElementById('need-help-subjects');
            const div = document.createElement('div');
            div.className = 'subject-item';
            div.innerHTML = `
                <div class="grid grid-cols-3" style="gap: 1rem; align-items: end;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Subject</label>
                        <select name="need_help_subjects[]" class="form-select" required>
                            <option value="">Select subject</option>
                            ${subjects.map(s => `<option value="${s}">${s}</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Current Level</label>
                        <select name="need_level[${needCount}]" class="form-select" required>
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">Remove</button>
                </div>
                <div class="form-group mt-2">
                    <label class="form-label">What specific help do you need? (Optional)</label>
                    <input type="text" name="need_description[${needCount}]" class="form-input" 
                           placeholder="e.g., I struggle with solving equations and need practice">
                </div>
            `;
            container.insertBefore(div, container.lastElementChild);
        }

        // Add at least one of each by default
        addOfferSubject();
        addNeedSubject();
    </script>
</body>
</html>
