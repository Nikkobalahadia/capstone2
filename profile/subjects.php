<?php
require_once '../config/config.php';
require_once '../includes/subjects_hierarchy.php';
require_once '../config/notification_helper.php';

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
                $valid_levels = ['advanced', 'expert'];
            }
            
            $subject_name = !empty($subtopic) ? $main_subject . ' - ' . $subtopic : $main_subject;
            
            if (!empty($main_subject) && in_array($proficiency_level, $valid_levels)) {
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
            
            $verify_stmt = $db->prepare("SELECT user_id FROM user_subjects WHERE id = ?");
            $verify_stmt->execute([$subject_id]);
            $subject = $verify_stmt->fetch();
            
            if ($subject && $subject['user_id'] == $user['id']) {
                $delete_stmt = $db->prepare("DELETE FROM user_subjects WHERE id = ? AND user_id = ?");
                if ($delete_stmt->execute([$subject_id, $user['id']])) {
                    $message = "Subject removed successfully!";
                } else {
                    $error = "Failed to remove subject. Please try again.";
                }
            } else {
                $error = "You don't have permission to remove this subject.";
            }
        }
    }
}

// Fetch user subjects based on role
if ($user['role'] === 'peer') {
    $learning_stmt = $db->prepare("SELECT * FROM user_subjects WHERE user_id = ? AND proficiency_level IN ('beginner', 'intermediate') ORDER BY subject_name");
    $learning_stmt->execute([$user['id']]);
    $learning_subjects = $learning_stmt->fetchAll();
    
    $teaching_stmt = $db->prepare("SELECT * FROM user_subjects WHERE user_id = ? AND proficiency_level IN ('advanced', 'expert') ORDER BY subject_name");
    $teaching_stmt->execute([$user['id']]);
    $teaching_subjects = $teaching_stmt->fetchAll();
} elseif ($user['role'] === 'mentor') {
    $teaching_stmt = $db->prepare("SELECT * FROM user_subjects WHERE user_id = ? ORDER BY subject_name");
    $teaching_stmt->execute([$user['id']]);
    $teaching_subjects = $teaching_stmt->fetchAll();
} else {
    $subjects_stmt = $db->prepare("SELECT * FROM user_subjects WHERE user_id = ? ORDER BY subject_name");
    $subjects_stmt->execute([$user['id']]);
    $user_subjects = $subjects_stmt->fetchAll();
}

$all_subjects = getSubjectsHierarchy();

$proficiency_options = [
    'beginner' => 'Beginner (I want to learn)',
    'intermediate' => 'Intermediate (I have some knowledge)',
    'advanced' => 'Advanced (I can teach this)',
    'expert' => 'Expert (I have mastery in this)'
];

if ($user['role'] === 'mentor') {
    $proficiency_options = [
        'advanced' => 'Advanced (I can teach this)',
        'expert' => 'Expert (I have mastery in this)'
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Manage Subjects - Study Buddy</title>
    
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-color: #2563eb;
            --text-primary: #1a1a1a;
            --text-secondary: #666;
            --border-color: #e5e5e5;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.1);
            --bg-color: #fafafa;
            --card-bg: white;
        }

        [data-theme="dark"] {
            --primary-color: #3b82f6;
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --border-color: #374151;
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.3);
            --bg-color: #111827;
            --card-bg: #1f2937;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            overflow-x: hidden;
            width: 100%;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-color);
            color: var(--text-primary);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            padding: 0.5rem 0;
            margin-bottom: 1.5rem;
            transition: color 0.2s;
        }
        .back-button:hover {
            color: var(--primary-color);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        main {
            padding: 2rem 0;
            min-height: 100vh;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-primary);
        }
        .page-header h1 i {
            color: var(--primary-color);
        }
        .page-subtitle {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success {
            background: #dcfce7;
            border: 1px solid #86efac;
            color: #166534;
        }
        .alert-error {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }

        /* Grid */
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        /* Card */
        .card {
            background: var(--card-bg);
            border-radius: 10px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: box-shadow 0.2s, background-color 0.3s ease, border-color 0.3s ease;
        }
        
        .card-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        [data-theme="dark"] .card-header {
            background: rgba(0, 0, 0, 0.2);
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }
        .card-subtitle {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
            line-height: 1.5;
        }
        .card-body {
            padding: 1.25rem;
        }

        /* Form */
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            background: var(--card-bg);
            color: var(--text-primary);
            transition: border-color 0.2s, box-shadow 0.2s, background-color 0.3s ease, color 0.3s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .form-control:disabled {
            background: var(--bg-color);
            color: var(--text-secondary);
            cursor: not-allowed;
        }

        /* Button */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            min-height: 44px;
        }
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .btn-secondary {
            background: var(--border-color);
            color: var(--text-primary);
        }
        .btn-secondary:hover {
            background: rgba(0,0,0,0.1);
        }
        [data-theme="dark"] .btn-secondary:hover {
            background: rgba(255,255,255,0.1);
        }
        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }
        .btn-danger:hover {
            background: #fecaca;
        }
        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            min-height: 36px;
        }

        /* Subject List */
        .subject-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .subject-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        .subject-item:last-child {
            border-bottom: none;
        }
        .subject-info {
            flex: 1;
            margin-right: 1rem;
        }
        .subject-name {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        .subject-level {
            font-size: 0.85rem;
            color: var(--text-secondary);
            text-transform: capitalize;
        }
        .subject-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        .empty-state i {
            font-size: 2.5rem;
            opacity: 0.5;
            margin-bottom: 1rem;
            display: block;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            main {
                padding: 1rem 0;
            }
            .container {
                padding: 0 0.75rem;
            }
            .grid {
                grid-template-columns: 1fr;
            }
            .page-header h1 {
                font-size: 1.5rem;
            }
            input, select, textarea, button {
                font-size: 16px !important;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>

<script>
    (function() {
        const theme = localStorage.getItem('theme');
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.body.classList.add('dark-mode');
        } else {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    })();
</script>

<main>
    <div class="container">
        <a href="index.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Profile
        </a>
        
        <div class="page-header">
            <h1><i class="fas fa-book"></i> Manage Your Subjects</h1>
            <?php if ($user['role'] === 'peer'): ?>
                <p class="page-subtitle">Add subjects you want to learn and subjects you can teach.</p>
            <?php elseif ($user['role'] === 'mentor'): ?>
                <p class="page-subtitle">Add the subjects you are an expert in and can mentor.</p>
            <?php else: ?>
                <p class="page-subtitle">Manage your subjects and learning interests.</p>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $message; ?></div>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h2 class="card-title">Add New Subject</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_subject">
                    <div class="grid">
                        <div class="form-group">
                            <label for="main_subject" class="form-label">Subject Category</label>
                            <select id="main_subject" name="main_subject" class="form-control" required>
                                <option value="">Select a subject category...</option>
                                <?php foreach ($all_subjects as $subject => $subtopics): ?>
                                    <option value="<?php echo htmlspecialchars($subject); ?>"><?php echo htmlspecialchars($subject); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="subtopic" class="form-label">Subtopic (Optional)</label>
                            <select id="subtopic" name="subtopic" class="form-control" disabled>
                                <option value="">Select subtopic (optional)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="proficiency_level" class="form-label">Your Proficiency</label>
                        <select id="proficiency_level" name="proficiency_level" class="form-control" required>
                            <option value="">Select your level...</option>
                            <?php foreach ($proficiency_options as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Subject</button>
                </form>
            </div>
        </div>
        
        <?php if ($user['role'] === 'peer'): ?>
            <div class="grid">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Subjects I'm Learning</h2>
                        <p class="card-subtitle">Topics you are a beginner or intermediate in.</p>
                    </div>
                    <div class="card-body">
                        <?php if (empty($learning_subjects)): ?>
                            <div class="empty-state">
                                <i class="fas fa-search"></i>
                                You haven't added any subjects to learn yet.
                            </div>
                        <?php else: ?>
                            <ul class="subject-list">
                                <?php foreach ($learning_subjects as $subject): ?>
                                    <li class="subject-item">
                                        <div class="subject-info">
                                            <div class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                            <div class="subject-level"><?php echo htmlspecialchars($subject['proficiency_level']); ?></div>
                                        </div>
                                        <div class="subject-actions">
                                            <form method="POST" action="" onsubmit="confirmDelete(event)" class="delete-form" style="display: inline;">
                                                <input type="hidden" name="action" value="remove_subject">
                                                <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Remove subject"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Subjects I'm Teaching</h2>
                        <p class="card-subtitle">Topics you are advanced or expert in.</p>
                    </div>
                    <div class="card-body">
                        <?php if (empty($teaching_subjects)): ?>
                            <div class="empty-state">
                                <i class="fas fa-chalkboard-teacher"></i>
                                You haven't added any subjects to teach yet.
                            </div>
                        <?php else: ?>
                            <ul class="subject-list">
                                <?php foreach ($teaching_subjects as $subject): ?>
                                    <li class="subject-item">
                                        <div class="subject-info">
                                            <div class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                            <div class="subject-level"><?php echo htmlspecialchars($subject['proficiency_level']); ?></div>
                                        </div>
                                        <div class="subject-actions">
                                            <form method="POST" action="" onsubmit="confirmDelete(event)" class="delete-form" style="display: inline;">
                                                <input type="hidden" name="action" value="remove_subject">
                                                <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Remove subject"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        <?php elseif ($user['role'] === 'mentor'): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Subjects I'm Mentoring</h2>
                    <p class="card-subtitle">Topics you are advanced or expert in.</p>
                </div>
                <div class="card-body">
                    <?php if (empty($teaching_subjects)): ?>
                        <div class="empty-state">
                            <i class="fas fa-chalkboard-teacher"></i>
                            You haven't added any subjects to mentor yet.
                        </div>
                    <?php else: ?>
                        <ul class="subject-list">
                            <?php foreach ($teaching_subjects as $subject): ?>
                                <li class="subject-item">
                                    <div class="subject-info">
                                        <div class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                        <div class="subject-level"><?php echo htmlspecialchars($subject['proficiency_level']); ?></div>
                                    </div>
                                    <div class="subject-actions">
                                        <form method="POST" action="" onsubmit="confirmDelete(event)" class="delete-form" style="display: inline;">
                                            <input type="hidden" name="action" value="remove_subject">
                                            <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Remove subject"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">My Subjects</h2>
                    <p class="card-subtitle">All your subjects and learning interests.</p>
                </div>
                <div class="card-body">
                    <?php if (empty($user_subjects)): ?>
                        <div class="empty-state">
                            <i class="fas fa-book"></i>
                            You haven't added any subjects yet.
                        </div>
                    <?php else: ?>
                        <ul class="subject-list">
                            <?php foreach ($user_subjects as $subject): ?>
                                <li class="subject-item">
                                    <div class="subject-info">
                                        <div class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                        <div class="subject-level"><?php echo htmlspecialchars($subject['proficiency_level']); ?></div>
                                    </div>
                                    <div class="subject-actions">
                                        <form method="POST" action="" onsubmit="confirmDelete(event)" class="delete-form" style="display: inline;">
                                            <input type="hidden" name="action" value="remove_subject">
                                            <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Remove subject"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</main>

<script>
    function confirmDelete(event) {
        event.preventDefault();
        const form = event.currentTarget;
        
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this! The subject will be removed from your list.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, remove it!'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const subjectsHierarchy = <?php echo json_encode(getSubjectsHierarchy()); ?>;
        const mainSubjectSelect = document.getElementById('main_subject');
        const subtopicSelect = document.getElementById('subtopic');
        
        if (mainSubjectSelect) {
            mainSubjectSelect.addEventListener('change', function() {
                const selectedSubject = this.value;
                
                subtopicSelect.innerHTML = '<option value="">Select subtopic (optional)</option>';
                subtopicSelect.disabled = true;
                
                if (selectedSubject && subjectsHierarchy[selectedSubject]) {
                    subtopicSelect.disabled = false;
                    
                    subjectsHierarchy[selectedSubject].forEach(function(subtopic) {
                        const option = document.createElement('option');
                        option.value = subtopic;
                        option.textContent = subtopic;
                        subtopicSelect.appendChild(option);
                    });
                }
            });
        }
    });
</script>

</body>
</html>