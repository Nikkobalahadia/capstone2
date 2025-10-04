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

// Get user's active matches with latest message
$db = getDB();
$conversations_query = "
    SELECT m.id as match_id,
           m.subject,
           CASE 
               WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
               ELSE CONCAT(u1.first_name, ' ', u1.last_name)
           END as partner_name,
           CASE 
               WHEN m.student_id = ? THEN u2.id
               ELSE u1.id
           END as partner_id,
           CASE 
               WHEN m.student_id = ? THEN u2.role
               ELSE u1.role
           END as partner_role,
           CASE 
               WHEN m.student_id = ? THEN u2.profile_picture
               ELSE u1.profile_picture
           END as partner_profile_picture,
           (SELECT msg.message FROM messages msg WHERE msg.match_id = m.id ORDER BY msg.created_at DESC LIMIT 1) as last_message,
           (SELECT msg.created_at FROM messages msg WHERE msg.match_id = m.id ORDER BY msg.created_at DESC LIMIT 1) as last_message_time,
           (SELECT msg.sender_id FROM messages msg WHERE msg.match_id = m.id ORDER BY msg.created_at DESC LIMIT 1) as last_sender_id,
           (SELECT COUNT(*) FROM messages msg WHERE msg.match_id = m.id AND msg.sender_id != ? AND msg.is_read = 0) as unread_count
    FROM matches m
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE (m.student_id = ? OR m.mentor_id = ?) 
    AND m.status = 'accepted'
    ORDER BY 
        CASE WHEN last_message_time IS NULL THEN 1 ELSE 0 END,
        last_message_time DESC, 
        m.created_at DESC
";

$stmt = $db->prepare($conversations_query);
$stmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
$conversations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - StudyConnect</title>
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
                    <li><a href="../matches/index.php">Matches</a></li>
                    <li><a href="../sessions/index.php">Sessions</a></li>
                    <li><a href="index.php">Messages</a></li>
                    <li><a href="../auth/logout.php" class="btn btn-outline">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="container">
            <div class="mb-4">
                <h1>Messages</h1>
                <p class="text-secondary">Chat with your study partners and mentors.</p>
            </div>

            <?php if (empty($conversations)): ?>
                <div class="card">
                    <div class="card-body text-center">
                        <h3>No conversations yet</h3>
                        <p class="text-secondary mb-4">Start messaging your study partners once you have active matches.</p>
                        <a href="../matches/find.php" class="btn btn-primary">Find Study Partners</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Conversations</h3>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div style="display: flex; flex-direction: column;">
                            <?php foreach ($conversations as $index => $conversation): ?>
                                <a href="chat.php?match_id=<?php echo $conversation['match_id']; ?>" 
                                   style="display: block; padding: 1.5rem; text-decoration: none; color: inherit; border-bottom: 1px solid var(--border-color); transition: background-color 0.2s;"
                                   onmouseover="this.style.backgroundColor='#f8fafc'" 
                                   onmouseout="this.style.backgroundColor='transparent'">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                                            <?php if (!empty($conversation['partner_profile_picture']) && file_exists('../' . $conversation['partner_profile_picture'])): ?>
                                                <div style="width: 50px; height: 50px; position: relative;">
                                                    <img src="../<?php echo htmlspecialchars($conversation['partner_profile_picture']); ?>" 
                                                         alt="<?php echo htmlspecialchars($conversation['partner_name']); ?>" 
                                                         style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                                                    <?php if ($conversation['unread_count'] > 0): ?>
                                                        <div style="position: absolute; top: -5px; right: -5px; background: var(--error-color); color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem;">
                                                            <?php echo min($conversation['unread_count'], 9); ?><?php echo $conversation['unread_count'] > 9 ? '+' : ''; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div style="width: 50px; height: 50px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; position: relative;">
                                                    <?php echo strtoupper(substr($conversation['partner_name'], 0, 2)); ?>
                                                    <?php if ($conversation['unread_count'] > 0): ?>
                                                        <div style="position: absolute; top: -5px; right: -5px; background: var(--error-color); color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem;">
                                                            <?php echo min($conversation['unread_count'], 9); ?><?php echo $conversation['unread_count'] > 9 ? '+' : ''; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div style="flex: 1; min-width: 0;">
                                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                                    <h4 class="font-semibold" style="margin: 0;"><?php echo htmlspecialchars($conversation['partner_name']); ?></h4>
                                                    <span class="text-sm text-secondary">•</span>
                                                    <span class="text-sm text-secondary"><?php echo htmlspecialchars($conversation['subject']); ?></span>
                                                </div>
                                                <?php if ($conversation['last_message']): ?>
                                                    <div class="text-sm text-secondary" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                        <?php if ($conversation['last_sender_id'] == $user['id']): ?>
                                                            <span>You: </span>
                                                        <?php endif; ?>
                                                        <?php echo htmlspecialchars(substr($conversation['last_message'], 0, 60)); ?>
                                                        <?php echo strlen($conversation['last_message']) > 60 ? '...' : ''; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-sm text-secondary">No messages yet</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div style="text-align: right; margin-left: 1rem;">
                                            <?php if ($conversation['last_message_time']): ?>
                                                <div class="text-sm text-secondary">
                                                    <?php 
                                                    $time_diff = time() - strtotime($conversation['last_message_time']);
                                                    if ($time_diff < 60) {
                                                        echo 'Just now';
                                                    } elseif ($time_diff < 3600) {
                                                        echo floor($time_diff / 60) . 'm ago';
                                                    } elseif ($time_diff < 86400) {
                                                        echo floor($time_diff / 3600) . 'h ago';
                                                    } elseif ($time_diff < 604800) {
                                                        echo floor($time_diff / 86400) . 'd ago';
                                                    } else {
                                                        echo date('M j', strtotime($conversation['last_message_time']));
                                                    }
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($conversation['unread_count'] > 0): ?>
                                                <div style="margin-top: 0.25rem;">
                                                    <span style="background: var(--error-color); color: white; padding: 0.125rem 0.5rem; border-radius: 1rem; font-size: 0.75rem;">
                                                        <?php echo $conversation['unread_count']; ?> new
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
