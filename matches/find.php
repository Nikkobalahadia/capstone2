<?php
require_once '../config/config.php';
require_once '../includes/matchmaking.php';

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
$matches = [];

// Initialize matchmaking engine
$db = getDB();
$matchmaker = new MatchmakingEngine($db);

// Handle match request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_match'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $mentor_id = (int)$_POST['mentor_id'];
        $subject = sanitize_input($_POST['subject']);
        $message = sanitize_input($_POST['message']);
        
        try {
            $match_id = $matchmaker->createMatch($user['id'], $mentor_id, $subject, $message);
            $success = 'Match request sent successfully!';
        } catch (Exception $e) {
            $error = 'Failed to send match request. Please try again.';
        }
    }
}

// Get search parameters
$subject_filter = isset($_GET['subject']) ? sanitize_input($_GET['subject']) : '';
$search_performed = !empty($subject_filter) || isset($_GET['search']);

if ($search_performed) {
    $matches = $matchmaker->findMatches($user['id'], $subject_filter, 20);
}

// Get user's subjects for filter dropdown
$subjects_stmt = $db->prepare("SELECT DISTINCT subject_name FROM user_subjects WHERE user_id = ?");
$subjects_stmt->execute([$user['id']]);
$user_subjects = $subjects_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Study Partners - StudyConnect</title>
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
                    <li><a href="../profile/index.php">Profile</a></li>
                    <li><a href="index.php">Matches</a></li>
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
                <h1>Find Study Partners</h1>
                <p class="text-secondary">Discover mentors and study partners that match your learning needs.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Search Form -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" style="display: flex; gap: 1rem; align-items: end;">
                        <div class="form-group" style="flex: 1; margin-bottom: 0;">
                            <label for="subject" class="form-label">Subject (Optional)</label>
                            <select id="subject" name="subject" class="form-select">
                                <option value="">All Subjects</option>
                                <?php foreach ($user_subjects as $subject): ?>
                                    <option value="<?php echo htmlspecialchars($subject); ?>" 
                                            <?php echo $subject_filter === $subject ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subject); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="search" class="btn btn-primary">Search Partners</button>
                    </form>
                </div>
            </div>

            <?php if ($search_performed): ?>
                <?php if (empty($matches)): ?>
                    <div class="card">
                        <div class="card-body text-center">
                            <h3>No matches found</h3>
                            <p class="text-secondary">Try adjusting your search criteria or check back later for new members.</p>
                            <a href="../profile/subjects.php" class="btn btn-secondary">Update Your Subjects</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1" style="gap: 1.5rem;">
                        <?php foreach ($matches as $match): ?>
                            <div class="card">
                                <div class="card-body">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div style="flex: 1;">
                                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                                <div style="width: 60px; height: 60px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 1.25rem;">
                                                    <?php echo strtoupper(substr($match['first_name'], 0, 1) . substr($match['last_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <h4 class="font-semibold"><?php echo htmlspecialchars($match['first_name'] . ' ' . $match['last_name']); ?></h4>
                                                    <div class="text-sm text-secondary">
                                                        <?php echo ucfirst($match['role']); ?> • <?php echo htmlspecialchars($match['grade_level'] ?? 'Grade not set'); ?>
                                                        <?php if ($match['location']): ?>
                                                            • <?php echo htmlspecialchars($match['location']); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-sm" style="color: var(--success-color);">
                                                        Match Score: <?php echo $match['match_score']; ?>%
                                                        <?php if ($match['avg_rating']): ?>
                                                            • Rating: <?php echo number_format($match['avg_rating'], 1); ?>/5 (<?php echo $match['rating_count']; ?> reviews)
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($match['bio']): ?>
                                                <p class="text-secondary mb-3"><?php echo nl2br(htmlspecialchars(substr($match['bio'], 0, 200))); ?><?php echo strlen($match['bio']) > 200 ? '...' : ''; ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if ($match['subjects']): ?>
                                                <div class="mb-3">
                                                    <strong>Subjects:</strong>
                                                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem;">
                                                        <?php 
                                                        $subjects = explode(',', $match['subjects']);
                                                        $proficiency_levels = explode(',', $match['proficiency_levels']);
                                                        for ($i = 0; $i < count($subjects) && $i < 5; $i++): 
                                                        ?>
                                                            <span style="background: #f1f5f9; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.875rem;">
                                                                <?php echo htmlspecialchars($subjects[$i]); ?>
                                                                <span class="text-secondary">(<?php echo ucfirst($proficiency_levels[$i] ?? 'Unknown'); ?>)</span>
                                                            </span>
                                                        <?php endfor; ?>
                                                        <?php if (count($subjects) > 5): ?>
                                                            <span class="text-secondary">+<?php echo count($subjects) - 5; ?> more</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($match['availability_slots'] > 0): ?>
                                                <div class="text-sm text-secondary">
                                                    Available <?php echo $match['availability_slots']; ?> time slot<?php echo $match['availability_slots'] > 1 ? 's' : ''; ?> per week
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div style="margin-left: 2rem;">
                                            <button type="button" class="btn btn-primary" onclick="openMatchModal(<?php echo $match['id']; ?>, '<?php echo htmlspecialchars($match['first_name'] . ' ' . $match['last_name']); ?>')">
                                                Send Request
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center">
                        <h3>Ready to find your study partner?</h3>
                        <p class="text-secondary">Use the search form above to discover mentors and study partners that match your learning needs.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Match Request Modal -->
    <div id="matchModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 8px; width: 90%; max-width: 500px;">
            <h3 class="mb-4">Send Match Request</h3>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="mentor_id" id="modal_mentor_id">
                
                <div class="form-group">
                    <label class="form-label">Requesting match with: <span id="modal_mentor_name" class="font-semibold"></span></label>
                </div>
                
                <div class="form-group">
                    <label for="modal_subject" class="form-label">Subject</label>
                    <select id="modal_subject" name="subject" class="form-select" required>
                        <option value="">Select Subject</option>
                        <?php foreach ($user_subjects as $subject): ?>
                            <option value="<?php echo htmlspecialchars($subject); ?>"><?php echo htmlspecialchars($subject); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="modal_message" class="form-label">Message (Optional)</label>
                    <textarea id="modal_message" name="message" class="form-input" rows="3" 
                              placeholder="Introduce yourself and explain what you'd like help with..."></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" name="request_match" class="btn btn-primary" style="flex: 1;">Send Request</button>
                    <button type="button" class="btn btn-secondary" onclick="closeMatchModal()" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openMatchModal(mentorId, mentorName) {
            document.getElementById('modal_mentor_id').value = mentorId;
            document.getElementById('modal_mentor_name').textContent = mentorName;
            document.getElementById('matchModal').style.display = 'block';
        }
        
        function closeMatchModal() {
            document.getElementById('matchModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('matchModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeMatchModal();
            }
        });
    </script>
</body>
</html>
