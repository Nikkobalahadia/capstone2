<?php
require_once '../config/config.php';
require_once '../includes/subjects_hierarchy.php';

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
            $main_subject = trim($_POST['main_subject']);
            $subtopic = !empty($_POST['subtopic']) ? trim($_POST['subtopic']) : null;
            $proficiency_level = $_POST['proficiency_level'];
            
            $valid_levels = ['beginner', 'intermediate', 'advanced', 'expert'];
            if ($user['role'] === 'mentor') {
                // Mentors can only add advanced/expert (teaching) subjects
                $valid_levels = ['advanced', 'expert'];
            }
            
            // Create full subject name
            $subject_name = !empty($subtopic) ? $main_subject . ' - ' . $subtopic : $main_subject;
            
            if (!empty($main_subject) && in_array($proficiency_level, $valid_levels)) {
                // Check if subject already exists for user
                $check_stmt = $db->prepare("SELECT id FROM user_subjects WHERE user_id = ? AND subject_name = ?");
                $check_stmt->execute([$user['id'], $subject_name]);
                
                if ($check_stmt->fetch()) {
                    $error = "You already have this subject in your list.";
                } else {
                    $insert_stmt = $db->prepare("INSERT INTO user_subjects (user_id, subject_name, proficiency_level, main_subject, subtopic) VALUES (?, ?, ?, ?, ?)");
                    if ($insert_stmt->execute([$user['id'], $subject_name, $proficiency_level, $main_subject, $subtopic])) {
                        $message = "Subject added successfully!";
                    } else {
                        $error = "Failed to add subject. Please try again.";
                    }
                }
            } else {
                $error = "Please provide a valid subject and proficiency level.";
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
            
            $valid_levels = ['beginner', 'intermediate', 'advanced', 'expert'];
            if ($user['role'] === 'mentor') {
                $valid_levels = ['advanced', 'expert'];
            }
            
            if (in_array($proficiency_level, $valid_levels)) {
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

if ($user['role'] === 'peer') {
    // Learning subjects (beginner/intermediate)
    $learning_stmt = $db->prepare("SELECT * FROM user_subjects WHERE user_id = ? AND proficiency_level IN ('beginner', 'intermediate') ORDER BY subject_name");
    $learning_stmt->execute([$user['id']]);
    $learning_subjects = $learning_stmt->fetchAll();
    
    // Teaching subjects (advanced/expert)
    $teaching_stmt = $db->prepare("SELECT * FROM user_subjects WHERE user_id = ? AND proficiency_level IN ('advanced', 'expert') ORDER BY subject_name");
    $teaching_stmt->execute([$user['id']]);
    $teaching_subjects = $teaching_stmt->fetchAll();
} elseif ($user['role'] === 'mentor') {
    // Mentors only have teaching subjects (advanced/expert)
    $teaching_stmt = $db->prepare("SELECT * FROM user_subjects WHERE user_id = ? ORDER BY subject_name");
    $teaching_stmt->execute([$user['id']]);
    $teaching_subjects = $teaching_stmt->fetchAll();
} else {
    // Students only have learning subjects
    $subjects_stmt = $db->prepare("SELECT * FROM user_subjects WHERE user_id = ? ORDER BY subject_name");
    $subjects_stmt->execute([$user['id']]);
    $user_subjects = $subjects_stmt->fetchAll();
}

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

    <main class="py-8">
        <div class="container">
            <div class="mb-6">
                <h1 class="text-3xl font-bold mb-2">Manage Your Subjects</h1>
                <!-- Updated description based on role -->
                <p class="text-secondary">
                    <?php if ($user['role'] === 'student'): ?>
                        Add subjects you want to learn and set your proficiency level.
                    <?php elseif ($user['role'] === 'mentor'): ?>
                        Add subjects you can teach at Advanced or Expert level.
                    <?php else: ?>
                        Add subjects you want to learn (Beginner/Intermediate) or teach (Advanced/Expert).
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="grid grid-cols-2 gap-8">
                <div class="card">
                    <div class="card-header">
                        <!-- Updated title based on role -->
                        <h3 class="card-title">
                            <?php if ($user['role'] === 'student'): ?>
                                Add New Subject to Learn
                            <?php elseif ($user['role'] === 'mentor'): ?>
                                Add New Subject to Teach
                            <?php else: ?>
                                Add New Subject
                            <?php endif; ?>
                        </h3>
                        <!-- Updated instructions based on role -->
                        <p class="text-sm text-secondary mt-2">
                            <strong>Example:</strong> Select "Mathematics" → Choose "Algebra", "Calculus", or "Geometry"
                            <?php if ($user['role'] === 'student'): ?>
                                <br><em>Add subjects you want to learn and get help with.</em>
                            <?php elseif ($user['role'] === 'mentor'): ?>
                                <br><em>Add subjects you can teach others. Only Advanced and Expert levels available.</em>
                            <?php else: ?>
                                <br><em><strong>Tip:</strong> Choose Beginner/Intermediate for subjects you want to learn, Advanced/Expert for subjects you can teach.</em>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="subjectForm">
                            <input type="hidden" name="action" value="add_subject">
                            
                            <div class="form-group">
                                <label for="main_subject" class="form-label">Main Subject</label>
                                <select id="main_subject" name="main_subject" class="form-control" required>
                                    <option value="">Select main subject</option>
                                    <?php foreach (getSubjectsHierarchy() as $main => $subtopics): ?>
                                        <option value="<?php echo htmlspecialchars($main); ?>">
                                            <?php echo htmlspecialchars($main); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="subtopic" class="form-label">Subtopic <span class="text-secondary">(Optional)</span></label>
                                <select id="subtopic" name="subtopic" class="form-control" disabled>
                                    <option value="">First select a main subject</option>
                                </select>
                                <small class="text-secondary mt-1 block">Choose a specific area within your main subject</small>
                            </div>

                            <div class="form-group">
                                <!-- Updated proficiency level options based on role -->
                                <label for="proficiency_level" class="form-label">
                                    Proficiency Level
                                    <?php if ($user['role'] === 'peer'): ?>
                                        <span class="text-xs text-secondary">(Determines if you're learning or teaching)</span>
                                    <?php endif; ?>
                                </label>
                                <select id="proficiency_level" name="proficiency_level" class="form-control" required>
                                    <option value="">Select level</option>
                                    <?php if ($user['role'] === 'student'): ?>
                                        <option value="beginner">Beginner - Just starting out</option>
                                        <option value="intermediate">Intermediate - Some experience</option>
                                        <option value="advanced">Advanced - Strong knowledge</option>
                                        <option value="expert">Expert - Mastered the subject</option>
                                    <?php elseif ($user['role'] === 'mentor'): ?>
                                        <option value="advanced">Advanced - Strong knowledge, can teach</option>
                                        <option value="expert">Expert - Mastered, can mentor others</option>
                                    <?php else: ?>
                                        <optgroup label="📚 Learning (Want to Learn)">
                                            <option value="beginner">Beginner - Just starting out</option>
                                            <option value="intermediate">Intermediate - Some experience</option>
                                        </optgroup>
                                        <optgroup label="👨‍🏫 Teaching (Can Teach Others)">
                                            <option value="advanced">Advanced - Strong knowledge</option>
                                            <option value="expert">Expert - Can teach others</option>
                                        </optgroup>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary w-full">Add Subject</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <!-- Updated title based on role -->
                        <h3 class="card-title">
                            <?php if ($user['role'] === 'student'): ?>
                                Subjects You Want to Learn (<?php echo count($user_subjects); ?>)
                            <?php elseif ($user['role'] === 'mentor'): ?>
                                Subjects You Can Teach (<?php echo count($teaching_subjects); ?>)
                            <?php else: ?>
                                Your Subjects
                            <?php endif; ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <!-- Conditional display based on role -->
                        <?php if ($user['role'] === 'peer'): ?>
                             Separate display for peers: learning and teaching subjects 
                            
                             Learning Subjects Section 
                            <div style="margin-bottom: 2rem;">
                                <h4 class="font-semibold mb-3" style="color: #3b82f6; display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="font-size: 1.1rem;">📚</span>
                                    Subjects I Want to Learn (<?php echo count($learning_subjects); ?>)
                                </h4>
                                <?php if (empty($learning_subjects)): ?>
                                    <p class="text-secondary" style="padding: 1rem; background: #f8fafc; border-radius: 6px; font-style: italic; font-size: 0.9rem;">
                                        No learning subjects yet. Add subjects at Beginner or Intermediate level to find mentors and peers who can help you learn.
                                    </p>
                                <?php else: ?>
                                    <div class="flex flex-col gap-3">
                                        <?php foreach ($learning_subjects as $subject): ?>
                                            <div class="flex justify-between items-center p-4 rounded-lg border hover:bg-gray-100 transition-colors" style="background: #eff6ff; border-color: #bfdbfe;">
                                                <div class="flex-1">
                                                    <div class="font-medium" style="color: #1e40af;"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                                    <div class="text-sm text-secondary mt-1">
                                                        Level: <span class="proficiency-<?php echo $subject['proficiency_level']; ?> font-medium"><?php echo ucfirst($subject['proficiency_level']); ?></span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <form method="POST" class="inline-block">
                                                        <input type="hidden" name="action" value="update_subject">
                                                        <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                        <select name="proficiency_level" class="form-control text-sm" style="width: auto; min-width: 140px;" onchange="this.form.submit()">
                                                            <optgroup label="📚 Learning">
                                                                <option value="beginner" <?php echo $subject['proficiency_level'] === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                                                <option value="intermediate" <?php echo $subject['proficiency_level'] === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                                            </optgroup>
                                                            <optgroup label="👨‍🏫 Teaching">
                                                                <option value="advanced" <?php echo $subject['proficiency_level'] === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                                                <option value="expert" <?php echo $subject['proficiency_level'] === 'expert' ? 'selected' : ''; ?>>Expert</option>
                                                            </optgroup>
                                                        </select>
                                                    </form>
                                                    <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to remove this subject?')">
                                                        <input type="hidden" name="action" value="remove_subject">
                                                        <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline text-error border-error hover:bg-red-50">Remove</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                             Teaching Subjects Section 
                            <div>
                                <h4 class="font-semibold mb-3" style="color: #22c55e; display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="font-size: 1.1rem;">👨‍🏫</span>
                                    Subjects I Can Teach (<?php echo count($teaching_subjects); ?>)
                                </h4>
                                <?php if (empty($teaching_subjects)): ?>
                                    <p class="text-secondary" style="padding: 1rem; background: #f8fafc; border-radius: 6px; font-style: italic; font-size: 0.9rem;">
                                        No teaching subjects yet. Add subjects at Advanced or Expert level to help other students and peers learn.
                                    </p>
                                <?php else: ?>
                                    <div class="flex flex-col gap-3">
                                        <?php foreach ($teaching_subjects as $subject): ?>
                                            <div class="flex justify-between items-center p-4 rounded-lg border hover:bg-gray-100 transition-colors" style="background: #f0fdf4; border-color: #bbf7d0;">
                                                <div class="flex-1">
                                                    <div class="font-medium" style="color: #15803d;"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                                    <div class="text-sm text-secondary mt-1">
                                                        Level: <span class="proficiency-<?php echo $subject['proficiency_level']; ?> font-medium"><?php echo ucfirst($subject['proficiency_level']); ?></span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <form method="POST" class="inline-block">
                                                        <input type="hidden" name="action" value="update_subject">
                                                        <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                        <select name="proficiency_level" class="form-control text-sm" style="width: auto; min-width: 140px;" onchange="this.form.submit()">
                                                            <optgroup label="📚 Learning">
                                                                <option value="beginner" <?php echo $subject['proficiency_level'] === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                                                <option value="intermediate" <?php echo $subject['proficiency_level'] === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                                            </optgroup>
                                                            <optgroup label="👨‍🏫 Teaching">
                                                                <option value="advanced" <?php echo $subject['proficiency_level'] === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                                                <option value="expert" <?php echo $subject['proficiency_level'] === 'expert' ? 'selected' : ''; ?>>Expert</option>
                                                            </optgroup>
                                                        </select>
                                                    </form>
                                                    <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to remove this subject?')">
                                                        <input type="hidden" name="action" value="remove_subject">
                                                        <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline text-error border-error hover:bg-red-50">Remove</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                        <?php elseif ($user['role'] === 'mentor'): ?>
                             Display for mentors: only teaching subjects 
                            <?php if (empty($teaching_subjects)): ?>
                                <p class="text-secondary text-center py-8">No teaching subjects added yet. Add subjects you can teach at Advanced or Expert level!</p>
                            <?php else: ?>
                                <div class="flex flex-col gap-3">
                                    <?php foreach ($teaching_subjects as $subject): ?>
                                        <div class="flex justify-between items-center p-4 rounded-lg border hover:bg-gray-100 transition-colors" style="background: #f0fdf4; border-color: #bbf7d0;">
                                            <div class="flex-1">
                                                <div class="font-medium" style="color: #15803d;"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                                <div class="text-sm text-secondary mt-1">
                                                    Level: <span class="proficiency-<?php echo $subject['proficiency_level']; ?> font-medium"><?php echo ucfirst($subject['proficiency_level']); ?></span>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <form method="POST" class="inline-block">
                                                    <input type="hidden" name="action" value="update_subject">
                                                    <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                    <select name="proficiency_level" class="form-control text-sm" style="width: auto; min-width: 120px;" onchange="this.form.submit()">
                                                        <option value="advanced" <?php echo $subject['proficiency_level'] === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                                        <option value="expert" <?php echo $subject['proficiency_level'] === 'expert' ? 'selected' : ''; ?>>Expert</option>
                                                    </select>
                                                </form>
                                                <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to remove this subject?')">
                                                    <input type="hidden" name="action" value="remove_subject">
                                                    <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline text-error border-error hover:bg-red-50">Remove</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                             Display for students: only learning subjects 
                            <?php if (empty($user_subjects)): ?>
                                <p class="text-secondary text-center py-8">No subjects added yet. Add your first subject to get started!</p>
                            <?php else: ?>
                                <div class="flex flex-col gap-3">
                                    <?php foreach ($user_subjects as $subject): ?>
                                        <div class="flex justify-between items-center p-4 rounded-lg border hover:bg-gray-100 transition-colors" style="background: #eff6ff; border-color: #bfdbfe;">
                                            <div class="flex-1">
                                                <div class="font-medium" style="color: #1e40af;"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                                <div class="text-sm text-secondary mt-1">
                                                    Level: <span class="proficiency-<?php echo $subject['proficiency_level']; ?> font-medium"><?php echo ucfirst($subject['proficiency_level']); ?></span>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <form method="POST" class="inline-block">
                                                    <input type="hidden" name="action" value="update_subject">
                                                    <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                    <select name="proficiency_level" class="form-control text-sm" style="width: auto; min-width: 120px;" onchange="this.form.submit()">
                                                        <option value="beginner" <?php echo $subject['proficiency_level'] === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                                        <option value="intermediate" <?php echo $subject['proficiency_level'] === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                                        <option value="advanced" <?php echo $subject['proficiency_level'] === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                                        <option value="expert" <?php echo $subject['proficiency_level'] === 'expert' ? 'selected' : ''; ?>>Expert</option>
                                                    </select>
                                                </form>
                                                <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to remove this subject?')">
                                                    <input type="hidden" name="action" value="remove_subject">
                                                    <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline text-error border-error hover:bg-red-50">Remove</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="flex gap-4 mt-8">
                <a href="../dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                <a href="index.php" class="btn btn-outline">View Profile</a>
            </div>
        </div>
    </main>

    <!-- Added JavaScript for cascading dropdown functionality -->
    <script>
        // Subjects hierarchy data
        const subjectsHierarchy = <?php echo json_encode(getSubjectsHierarchy()); ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            const mainSubjectSelect = document.getElementById('main_subject');
            const subtopicSelect = document.getElementById('subtopic');
            
            mainSubjectSelect.addEventListener('change', function() {
                const selectedSubject = this.value;
                
                // Clear and disable subtopic dropdown
                subtopicSelect.innerHTML = '<option value="">Select subtopic</option>';
                subtopicSelect.disabled = true;
                
                if (selectedSubject && subjectsHierarchy[selectedSubject]) {
                    // Enable subtopic dropdown
                    subtopicSelect.disabled = false;
                    
                    // Add subtopic options
                    subjectsHierarchy[selectedSubject].forEach(function(subtopic) {
                        const option = document.createElement('option');
                        option.value = subtopic;
                        option.textContent = subtopic;
                        subtopicSelect.appendChild(option);
                    });
                    
                    // Update placeholder
                    subtopicSelect.firstElementChild.textContent = 'Choose a specific area (optional)';
                } else {
                    subtopicSelect.firstElementChild.textContent = 'First select a main subject';
                }
            });
        });
    </script>

    <style>
        /* Enhanced proficiency level colors and styling */
        .proficiency-beginner { color: #dc2626; }
        .proficiency-intermediate { color: #ea580c; }
        .proficiency-advanced { color: #16a34a; }
        .proficiency-expert { color: #2563eb; }
        
        .bg-gray-50 { background-color: #f9fafb; }
        .bg-gray-100 { background-color: #f3f4f6; }
        .bg-green-100 { background-color: #dcfce7; }
        .bg-yellow-100 { background-color: #fef3c7; }
        .bg-red-50 { background-color: #fef2f2; }
        
        .text-green-800 { color: #166534; }
        .text-yellow-800 { color: #92400e; }
        .text-gray-800 { color: #1f2937; }
        .text-error { color: #dc2626; }
        .border-error { border-color: #dc2626; }
        
        .hover\:bg-red-50:hover { background-color: #fef2f2; }
        .hover\:bg-gray-100:hover { background-color: #f3f4f6; }
        
        .transition-colors { transition: background-color 0.2s ease, border-color 0.2s ease; }
        
        /* Enhanced form styling */
        select:disabled {
            background-color: #f1f5f9;
            color: #64748b;
            cursor: not-allowed;
        }
    </style>
</body>
</html>
