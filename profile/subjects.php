<?php
require_once '../config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('../auth/login.php');
}

$user = get_logged_in_user();
if (!$user) {
    redirect('../auth/login.php');
}

$db = getDB();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_subject') {
            $subject_name = trim($_POST['subject_name']);
            $proficiency_level = $_POST['proficiency_level'];
            
            if (!empty($subject_name) && in_array($proficiency_level, ['beginner', 'intermediate', 'advanced', 'expert'])) {
                // Check if subject already exists for user
                $check_stmt = $db->prepare("SELECT id FROM user_subjects WHERE user_id = ? AND subject_name = ?");
                $check_stmt->execute([$user['id'], $subject_name]);
                
                if ($check_stmt->fetch()) {
                    $error = "You already have this subject in your list.";
                } else {
                    $insert_stmt = $db->prepare("INSERT INTO user_subjects (user_id, subject_name, proficiency_level) VALUES (?, ?, ?)");
                    if ($insert_stmt->execute([$user['id'], $subject_name, $proficiency_level])) {
                        $message = "Subject added successfully!";
                    } else {
                        $error = "Failed to add subject. Please try again.";
                    }
                }
            } else {
                $error = "Please provide a valid subject name and proficiency level.";
            }
        } elseif ($_POST['action'] === 'remove_subject') {
            $subject_id = (int)$_POST['subject_id'];
            $delete_stmt = $db->prepare("DELETE FROM user_subjects WHERE id = ? AND user_id = ?");
            if ($delete_stmt->execute([$subject_id, $user['id']])) {
                $message = "Subject removed successfully!";
            } else {
                $error = "Failed to remove subject. Please try again.";
            }
        } elseif ($_POST['action'] === 'update_subject') {
            $subject_id = (int)$_POST['subject_id'];
            $proficiency_level = $_POST['proficiency_level'];
            
            if (in_array($proficiency_level, ['beginner', 'intermediate', 'advanced', 'expert'])) {
                $update_stmt = $db->prepare("UPDATE user_subjects SET proficiency_level = ? WHERE id = ? AND user_id = ?");
                if ($update_stmt->execute([$proficiency_level, $subject_id, $user['id']])) {
                    $message = "Subject updated successfully!";
                } else {
                    $error = "Failed to update subject. Please try again.";
                }
            } else {
                $error = "Please select a valid proficiency level.";
            }
        }
    }
}

// Get user's subjects
$subjects_stmt = $db->prepare("SELECT * FROM user_subjects WHERE user_id = ? ORDER BY subject_name");
$subjects_stmt->execute([$user['id']]);
$user_subjects = $subjects_stmt->fetchAll();

// Common subjects list
$common_subjects = [
    'Mathematics', 'Physics', 'Chemistry', 'Biology', 'Computer Science',
    'English Literature', 'History', 'Geography', 'Economics', 'Psychology',
    'Philosophy', 'Art', 'Music', 'Foreign Languages', 'Statistics',
    'Engineering', 'Business Studies', 'Accounting', 'Marketing', 'Law'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects - StudyConnect</title>
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
                    <li><a href="index.php" class="active">Profile</a></li>
                    <li><a href="../matches/index.php">Matches</a></li>
                    <li><a href="../sessions/index.php">Sessions</a></li>
                    <li><a href="../messages/index.php">Messages</a></li>
                    <li>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span class="text-secondary">Hi, <?php echo htmlspecialchars($user['first_name']); ?>!</span>
                            <a href="../auth/logout.php" class="btn btn-outline" style="padding: 0.5rem 1rem;">Logout</a>
                        </div>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="container">
            <div class="mb-4">
                <h1>Manage Your Subjects</h1>
                <p class="text-secondary">Add subjects you want to learn or teach, and set your proficiency level.</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success mb-4"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error mb-4"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="grid grid-cols-2" style="gap: 2rem;">
                <!-- Add New Subject -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Add New Subject</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_subject">
                            
                            <div class="form-group">
                                <label for="subject_name">Subject Name</label>
                                <input type="text" id="subject_name" name="subject_name" class="form-control" 
                                       placeholder="Enter subject name" list="common_subjects" required>
                                <datalist id="common_subjects">
                                    <?php foreach ($common_subjects as $subject): ?>
                                        <option value="<?php echo htmlspecialchars($subject); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <div class="form-group">
                                <label for="proficiency_level">Proficiency Level</label>
                                <select id="proficiency_level" name="proficiency_level" class="form-control" required>
                                    <option value="">Select level</option>
                                    <option value="beginner">Beginner - Just starting out</option>
                                    <option value="intermediate">Intermediate - Some experience</option>
                                    <option value="advanced">Advanced - Strong knowledge</option>
                                    <option value="expert">Expert - Can teach others</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">Add Subject</button>
                        </form>
                    </div>
                </div>

                <!-- Current Subjects -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Your Subjects (<?php echo count($user_subjects); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($user_subjects)): ?>
                            <p class="text-secondary text-center">No subjects added yet. Add your first subject to get started!</p>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                <?php foreach ($user_subjects as $subject): ?>
                                    <div class="subject-item" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0;">
                                        <div>
                                            <div class="font-medium"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                            <div class="text-sm text-secondary">
                                                Level: <span class="proficiency-<?php echo $subject['proficiency_level']; ?>"><?php echo ucfirst($subject['proficiency_level']); ?></span>
                                            </div>
                                        </div>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_subject">
                                                <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                <select name="proficiency_level" class="form-control" style="width: auto; font-size: 0.875rem;" onchange="this.form.submit()">
                                                    <option value="beginner" <?php echo $subject['proficiency_level'] === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                                    <option value="intermediate" <?php echo $subject['proficiency_level'] === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                                    <option value="advanced" <?php echo $subject['proficiency_level'] === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                                    <option value="expert" <?php echo $subject['proficiency_level'] === 'expert' ? 'selected' : ''; ?>>Expert</option>
                                                </select>
                                            </form>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this subject?')">
                                                <input type="hidden" name="action" value="remove_subject">
                                                <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                <button type="submit" class="btn btn-outline btn-sm" style="color: #dc2626;">Remove</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <a href="../dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                <a href="index.php" class="btn btn-outline">View Profile</a>
            </div>
        </div>
    </main>

    <style>
        .proficiency-beginner { color: #dc2626; }
        .proficiency-intermediate { color: #ea580c; }
        .proficiency-advanced { color: #16a34a; }
        .proficiency-expert { color: #2563eb; }
        
        .subject-item:hover {
            background: #f1f5f9 !important;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
    </style>
</body>
</html>
