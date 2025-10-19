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

$match_id = isset($_GET['match_id']) ? (int)$_GET['match_id'] : 0;
if (!$match_id) {
    redirect('index.php');
}

$db = getDB();

// Verify user is part of this match
$match_stmt = $db->prepare("
    SELECT m.*, 
           CASE 
               WHEN m.student_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
               ELSE CONCAT(u1.first_name, ' ', u1.last_name)
           END as partner_name,
           CASE 
               WHEN m.student_id = ? THEN u2.id
               ELSE u1.id
           END as partner_id,
           CASE 
               WHEN m.student_id = ? THEN u2.profile_picture
               ELSE u1.profile_picture
           END as partner_profile_picture
    FROM matches m
    JOIN users u1 ON m.student_id = u1.id
    JOIN users u2 ON m.mentor_id = u2.id
    WHERE m.id = ? AND (m.student_id = ? OR m.mentor_id = ?) AND m.status = 'accepted'
");
$match_stmt->execute([$user['id'], $user['id'], $user['id'], $match_id, $user['id'], $user['id']]);
$match = $match_stmt->fetch();

if (!$match) {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $reason = trim($_POST['reason']);
        $description = trim($_POST['description']);
        
        if (empty($reason) || empty($description)) {
            $error = 'Please provide both a reason and description for the report.';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO user_reports (reporter_id, reported_id, reason, description) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user['id'], $match['partner_id'], $reason, $description]);
                
                // Log activity
                $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'user_reported', ?, ?)");
                $log_stmt->execute([$user['id'], json_encode(['reported_user_id' => $match['partner_id'], 'reason' => $reason]), $_SERVER['REMOTE_ADDR']]);
                
                $success = 'Report submitted successfully. Our admin team will review it shortly.';
                
            } catch (Exception $e) {
                $error = 'Failed to submit report. Please try again.';
            }
        }
    }
}

// Handle new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $message = trim($_POST['message']);
        
        if (empty($message)) {
            $error = 'Please enter a message.';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO messages (match_id, sender_id, message) VALUES (?, ?, ?)");
                $stmt->execute([$match_id, $user['id'], $message]);
                
                // Log activity
                $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'message_sent', ?, ?)");
                $log_stmt->execute([$user['id'], json_encode(['match_id' => $match_id, 'partner_id' => $match['partner_id']]), $_SERVER['REMOTE_ADDR']]);
                
                // Redirect to prevent resubmission
                redirect("messages/chat.php?match_id=$match_id");
                
            } catch (Exception $e) {
                $error = 'Failed to send message. Please try again.';
            }
        }
    }
}

// Mark messages as read
$read_stmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE match_id = ? AND sender_id != ?");
$read_stmt->execute([$match_id, $user['id']]);

// Get messages
$messages_stmt = $db->prepare("
    SELECT m.*, u.first_name, u.last_name, u.profile_picture
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.match_id = ?
    ORDER BY m.created_at ASC
");
$messages_stmt->execute([$match_id]);
$messages = $messages_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?php echo htmlspecialchars($match['partner_name']); ?> - StudyConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .chat-container {
            height: 70vh;
            display: flex;
            flex-direction: column;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .chat-header {
            padding: 1rem;
            background: var(--card-background);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background: #f8fafc;
        }
        
        .message {
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
        
        .message.own {
            flex-direction: row-reverse;
        }
        
        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            flex-shrink: 0;
        }
        
        .message-content {
            max-width: 70%;
            background: white;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }
        
        .message.own .message-content {
            background: var(--primary-color);
            color: white;
        }
        
        .message-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }
        
        .message.own .message-time {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .chat-input {
            padding: 1rem;
            background: var(--card-background);
            border-top: 1px solid var(--border-color);
        }
        
        .input-group {
            display: flex;
            gap: 0.5rem;
        }
        
        .input-group input {
            flex: 1;
        }
        
        /* Added styles for file attachments */
        .attachment-preview {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: #f1f5f9;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        
        .attachment-preview button {
            background: none;
            border: none;
            color: var(--error-color);
            cursor: pointer;
            padding: 0.25rem;
        }
        
        .message-attachment {
            margin-top: 0.5rem;
            padding: 0.75rem;
            background: rgba(0,0,0,0.05);
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .message.own .message-attachment {
            background: rgba(255,255,255,0.2);
        }
        
        .message-attachment img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 4px;
        }
        
        .attachment-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border-radius: 8px;
        }
        
        .attachment-info {
            flex: 1;
        }
        
        .attachment-name {
            font-weight: 500;
            word-break: break-word;
        }
        
        .attachment-size {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .message.own .attachment-size {
            color: rgba(255,255,255,0.8);
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
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <a href="index.php" class="btn btn-outline">← Back to Messages</a>
                    <div>
                        <h1>Chat with <?php echo htmlspecialchars($match['partner_name']); ?></h1>
                        <p class="text-secondary">Subject: <?php echo htmlspecialchars($match['subject']); ?></p>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="chat-container">
                <div class="chat-header">
                    <?php if (!empty($match['partner_profile_picture']) && file_exists('../' . $match['partner_profile_picture'])): ?>
                        <img src="../<?php echo htmlspecialchars($match['partner_profile_picture']); ?>" 
                             alt="<?php echo htmlspecialchars($match['partner_name']); ?>" 
                             style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        <div style="width: 40px; height: 40px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                            <?php echo strtoupper(substr($match['partner_name'], 0, 2)); ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <div class="font-semibold"><?php echo htmlspecialchars($match['partner_name']); ?></div>
                        <div class="text-sm text-secondary">Online</div>
                    </div>
                    <div style="margin-left: auto; display: flex; gap: 0.5rem; align-items: center;">
                        <a href="../sessions/schedule.php?match_id=<?php echo $match_id; ?>" class="btn btn-secondary">Schedule Session</a>
                        <div style="position: relative;">
                            <button type="button" class="btn btn-outline" id="chatMenuBtn" 
                                    style="padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color);"
                                    onclick="toggleChatMenu()">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                    <circle cx="10" cy="4" r="1.5"/>
                                    <circle cx="10" cy="10" r="1.5"/>
                                    <circle cx="10" cy="16" r="1.5"/>
                                </svg>
                            </button>
                            <div id="chatMenu" style="display: none; position: absolute; right: 0; top: 100%; margin-top: 0.5rem; background: white; border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); min-width: 180px; z-index: 1000;">
                                <button type="button" class="btn" onclick="openReportModal()" 
                                        style="width: 100%; text-align: left; padding: 0.75rem 1rem; border: none; background: transparent; display: flex; align-items: center; gap: 0.5rem; color: var(--error-color);">
                                    <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    Report User
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="chat-messages" id="chatMessages">
                    <?php if (empty($messages)): ?>
                        <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                            <p>No messages yet. Start the conversation!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="message <?php echo $message['sender_id'] == $user['id'] ? 'own' : ''; ?>">
                                <?php if (!empty($message['profile_picture']) && file_exists('../' . $message['profile_picture'])): ?>
                                    <img src="../<?php echo htmlspecialchars($message['profile_picture']); ?>" 
                                         alt="<?php echo htmlspecialchars($message['first_name']); ?>" 
                                         class="message-avatar" 
                                         style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0;">
                                <?php else: ?>
                                    <div class="message-avatar" style="background: <?php echo $message['sender_id'] == $user['id'] ? 'var(--primary-color)' : 'var(--secondary-color)'; ?>;">
                                        <?php echo strtoupper(substr($message['first_name'], 0, 1) . substr($message['last_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="message-content">
                                    <?php if (!empty($message['message'])): ?>
                                        <div><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                    <?php endif; ?>
                                    
                                    <!-- Display file attachments -->
                                    <?php if (!empty($message['attachment_path'])): ?>
                                        <div class="message-attachment">
                                            <?php 
                                            $isImage = strpos($message['attachment_type'], 'image/') === 0;
                                            if ($isImage && file_exists('../' . $message['attachment_path'])): 
                                            ?>
                                                <a href="../api/download-attachment.php?message_id=<?php echo $message['id']; ?>" target="_blank">
                                                    <img src="../<?php echo htmlspecialchars($message['attachment_path']); ?>" 
                                                         alt="<?php echo htmlspecialchars($message['attachment_name']); ?>">
                                                </a>
                                            <?php else: ?>
                                                <div class="attachment-icon">
                                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                                                        <polyline points="13 2 13 9 20 9"></polyline>
                                                    </svg>
                                                </div>
                                                <div class="attachment-info">
                                                    <div class="attachment-name"><?php echo htmlspecialchars($message['attachment_name']); ?></div>
                                                    <div class="attachment-size"><?php echo number_format($message['attachment_size'] / 1024, 1); ?> KB</div>
                                                </div>
                                                <a href="../api/download-attachment.php?message_id=<?php echo $message['id']; ?>" 
                                                   class="btn btn-sm btn-outline" 
                                                   style="padding: 0.25rem 0.75rem;">
                                                    Download
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="message-time">
                                        <?php 
                                        $time_diff = time() - strtotime($message['created_at']);
                                        if ($time_diff < 60) {
                                            echo 'Just now';
                                        } elseif ($time_diff < 3600) {
                                            echo floor($time_diff / 60) . ' minutes ago';
                                        } elseif ($time_diff < 86400) {
                                            echo floor($time_diff / 3600) . ' hours ago';
                                        } else {
                                            echo date('M j, g:i A', strtotime($message['created_at']));
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="chat-input">
                    <!-- Added file attachment preview area -->
                    <div id="attachmentPreview" style="display: none;"></div>
                    
                    <form method="POST" action="" id="messageForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="file" id="fileInput" style="display: none;" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip">
                        <div class="input-group">
                            <!-- Added file attachment button -->
                            <button type="button" class="btn btn-outline" onclick="document.getElementById('fileInput').click()" 
                                    style="padding: 0.5rem 0.75rem;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
                                </svg>
                            </button>
                            <input type="text" name="message" id="messageInput" class="form-input" 
                                   placeholder="Type your message..." 
                                   maxlength="1000" 
                                   autocomplete="off">
                            <button type="submit" name="send_message" class="btn btn-primary">Send</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <div id="reportModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 600;">Report User</h3>
                <button type="button" onclick="closeReportModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div style="padding: 1.5rem;">
                    <div style="margin-bottom: 1rem;">
                        <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                            You are reporting <strong><?php echo htmlspecialchars($match['partner_name']); ?></strong>. 
                            Please provide details about why you're reporting this user.
                        </p>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Reason for Report</label>
                        <select name="reason" class="form-input" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 8px;">
                            <option value="">Select a reason...</option>
                            <option value="harassment">Harassment or Bullying</option>
                            <option value="inappropriate">Inappropriate Content</option>
                            <option value="spam">Spam or Scam</option>
                            <option value="fake_profile">Fake Profile</option>
                            <option value="no_show">Repeated No-Shows</option>
                            <option value="unprofessional">Unprofessional Behavior</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Description</label>
                        <textarea name="description" class="form-input" rows="4" required 
                                  placeholder="Please provide specific details about the issue..."
                                  style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 8px; resize: vertical;"></textarea>
                        <small style="color: var(--text-secondary);">Be as specific as possible. This will help our team review your report.</small>
                    </div>
                </div>
                <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <button type="button" onclick="closeReportModal()" class="btn btn-outline">Cancel</button>
                    <button type="submit" name="submit_report" class="btn btn-primary">Submit Report</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleChatMenu() {
            const menu = document.getElementById('chatMenu');
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        }
        
        function openReportModal() {
            document.getElementById('reportModal').style.display = 'flex';
            document.getElementById('chatMenu').style.display = 'none';
        }
        
        function closeReportModal() {
            document.getElementById('reportModal').style.display = 'none';
        }
        
        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('chatMenu');
            const btn = document.getElementById('chatMenuBtn');
            if (menu && btn && !menu.contains(event.target) && !btn.contains(event.target)) {
                menu.style.display = 'none';
            }
        });
        
        // Close modal when clicking outside
        document.getElementById('reportModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeReportModal();
            }
        });

        // Auto-scroll to bottom of messages
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Scroll to bottom on page load
        window.addEventListener('load', scrollToBottom);
        
        // Auto-refresh messages every 10 seconds
        setInterval(function() {
            // Simple refresh - in a real app you'd use AJAX
            if (document.hidden === false) {
                location.reload();
            }
        }, 10000);
        
        // Focus on input field
        document.querySelector('input[name="message"]').focus();
        
        // Handle Enter key to send message
        document.querySelector('input[name="message"]').addEventListener('keyup', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.closest('form').submit();
            }
        });
        
        const fileInput = document.getElementById('fileInput');
        const attachmentPreview = document.getElementById('attachmentPreview');
        const messageForm = document.getElementById('messageForm');
        const messageInput = document.getElementById('messageInput');
        let selectedFile = null;
        
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // Validate file size (10MB)
            if (file.size > 10 * 1024 * 1024) {
                alert('File size must be less than 10MB');
                fileInput.value = '';
                return;
            }
            
            selectedFile = file;
            showAttachmentPreview(file);
        });
        
        function showAttachmentPreview(file) {
            attachmentPreview.style.display = 'block';
            attachmentPreview.innerHTML = `
                <div class="attachment-preview">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
                    </svg>
                    <span>${file.name} (${(file.size / 1024).toFixed(1)} KB)</span>
                    <button type="button" onclick="clearAttachment()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            `;
        }
        
        function clearAttachment() {
            selectedFile = null;
            fileInput.value = '';
            attachmentPreview.style.display = 'none';
            attachmentPreview.innerHTML = '';
        }
        
        // Handle form submission with file upload
        messageForm.addEventListener('submit', async function(e) {
            if (selectedFile) {
                e.preventDefault();
                await uploadFileMessage();
            }
        });
        
        async function uploadFileMessage() {
            const formData = new FormData();
            formData.append('attachment', selectedFile);
            formData.append('message', messageInput.value);
            formData.append('match_id', <?php echo $match_id; ?>);
            
            try {
                const response = await fetch('../api/upload-attachment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Clear form and reload
                    messageInput.value = '';
                    clearAttachment();
                    location.reload();
                } else {
                    alert(data.error || 'Failed to upload file');
                }
            } catch (error) {
                console.error('Upload error:', error);
                alert('Failed to upload file. Please try again.');
            }
        }
    </script>
</body>
</html>
