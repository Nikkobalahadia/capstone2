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

$session_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$session_id) {
    redirect('index.php');
}

$db = getDB();

// Get session details and verify user access
$session_stmt = $db->prepare("
    SELECT s.*, m.subject,
           CASE 
               WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
               ELSE CONCAT(u1.first_name, ' ', u1.last_name)
           END as partner_name,
           CASE 
               WHEN m.student_id = ? THEN u2.id
               ELSE u1.id
           END as partner_id
    FROM sessions s
    JOIN matches m ON s.match_id = m.id
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE s.id = ? AND (m.student_id = ? OR m.mentor_id = ?)
");
$session_stmt->execute([$user['id'], $user['id'], $session_id, $user['id'], $user['id']]);
$session = $session_stmt->fetch();

if (!$session) {
    redirect('index.php');
}

// Check if user has already rated this session
$existing_rating_stmt = $db->prepare("SELECT id FROM session_ratings WHERE session_id = ? AND rater_id = ?");
$existing_rating_stmt->execute([$session_id, $user['id']]);
if ($existing_rating_stmt->fetch()) {
    redirect('index.php');
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $rating = (int)$_POST['rating'];
        $feedback = sanitize_input($_POST['feedback']);
        
        if ($rating < 1 || $rating > 5) {
            $error = 'Please select a valid rating (1-5 stars).';
        } else {
            try {
                $db->beginTransaction();
                
                // Insert rating
                $stmt = $db->prepare("
                    INSERT INTO session_ratings (session_id, rater_id, rated_id, rating, feedback) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$session_id, $user['id'], $session['partner_id'], $rating, $feedback]);
                
                // Log activity
                $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'session_rated', ?, ?)");
                $log_stmt->execute([$user['id'], json_encode(['session_id' => $session_id, 'rating' => $rating]), $_SERVER['REMOTE_ADDR']]);
                
                $db->commit();
                
                $success = 'Thank you for rating this session!';
                
                // Redirect after 2 seconds
                header("refresh:2;url=index.php");
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Failed to submit rating. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Session - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .star-rating {
            display: flex;
            gap: 0.25rem;
            margin: 1rem 0;
        }
        
        .star {
            font-size: 2rem;
            color: #e5e7eb;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .star:hover,
        .star.active {
            color: #fbbf24;
        }
        
        .star.active {
            color: #f59e0b;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="../dashboard.php" class="logo">StudyConnect</a>
                <ul class="nav-links">
                    <li><a href="../dashboard.php">Dashboard</a></li>
                    <li><a href="../profile/index.php">Profile</a></li>
                    <li><a href="../matches/index.php">Matches</a></li>
                    <li><a href="index.php">Sessions</a></li>
                    <li><a href="../messages/index.php">Messages</a></li>
                    <li><a href="../auth/logout.php" class="btn btn-outline">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="container">
            <div class="form-container" style="max-width: 500px;">
                <h2 class="text-center mb-4">Rate Your Session</h2>
                
                <!-- Session Details -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                            <div style="width: 50px; height: 50px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                <?php echo strtoupper(substr($session['partner_name'], 0, 2)); ?>
                            </div>
                            <div>
                                <h4 class="font-semibold"><?php echo htmlspecialchars($session['partner_name']); ?></h4>
                                <div class="text-sm text-secondary">
                                    <?php echo htmlspecialchars($session['subject']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-sm text-secondary">
                            <strong>Date:</strong> <?php echo date('l, M j, Y', strtotime($session['session_date'])); ?><br>
                            <strong>Time:</strong> <?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?>
                            <?php if ($session['location']): ?>
                                <br><strong>Location:</strong> <?php echo htmlspecialchars($session['location']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="rating" id="rating_value" value="">
                    
                    <div class="form-group">
                        <label class="form-label">How was your session?</label>
                        <div class="star-rating" id="star_rating">
                            <span class="star" data-rating="1">★</span>
                            <span class="star" data-rating="2">★</span>
                            <span class="star" data-rating="3">★</span>
                            <span class="star" data-rating="4">★</span>
                            <span class="star" data-rating="5">★</span>
                        </div>
                        <div id="rating_text" class="text-sm text-secondary"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="feedback" class="form-label">Feedback (Optional)</label>
                        <textarea id="feedback" name="feedback" class="form-input" rows="4" 
                                  placeholder="Share your experience with this session..."><?php echo isset($_POST['feedback']) ? htmlspecialchars($_POST['feedback']) : ''; ?></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;" id="submit_btn" disabled>Submit Rating</button>
                        <a href="index.php" class="btn btn-secondary" style="flex: 1; text-align: center;">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        const stars = document.querySelectorAll('.star');
        const ratingValue = document.getElementById('rating_value');
        const ratingText = document.getElementById('rating_text');
        const submitBtn = document.getElementById('submit_btn');
        
        const ratingTexts = {
            1: 'Poor - The session didn\'t meet expectations',
            2: 'Fair - Some issues but had value',
            3: 'Good - Met expectations',
            4: 'Very Good - Exceeded expectations',
            5: 'Excellent - Outstanding session!'
        };
        
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.dataset.rating);
                setRating(rating);
            });
            
            star.addEventListener('mouseover', function() {
                const rating = parseInt(this.dataset.rating);
                highlightStars(rating);
            });
        });
        
        document.getElementById('star_rating').addEventListener('mouseleave', function() {
            const currentRating = parseInt(ratingValue.value) || 0;
            highlightStars(currentRating);
        });
        
        function setRating(rating) {
            ratingValue.value = rating;
            ratingText.textContent = ratingTexts[rating];
            highlightStars(rating);
            submitBtn.disabled = false;
        }
        
        function highlightStars(rating) {
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }
            });
        }
    </script>
</body>
</html>
