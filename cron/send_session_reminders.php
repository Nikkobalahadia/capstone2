<?php
// Run this script every 5 minutes via cron: */5 * * * * php /path/to/send_session_reminders.php
// Or manually trigger via: http://localhost/study-mentorship-platform/cron/send_session_reminders.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/PHPMailer.php';

$db = getDB();

error_log("[CRON] Session reminders cron job started at " . date('Y-m-d H:i:s'));

// Get pending reminders that need to be sent
$reminders_query = "
    SELECT sr.*, s.session_date, s.start_time, s.end_time, s.location, s.notes,
           m.subject, m.student_id, m.mentor_id,
           u.email, u.first_name, u.last_name,
           CASE 
               WHEN m.student_id = sr.user_id THEN CONCAT(u2.first_name, ' ', u2.last_name)
               ELSE CONCAT(u1.first_name, ' ', u1.last_name)
           END as partner_name
    FROM session_reminders sr
    JOIN sessions s ON sr.session_id = s.id
    JOIN matches m ON s.match_id = m.id
    JOIN users u ON sr.user_id = u.id
    LEFT JOIN users u1 ON m.student_id = u1.id
    LEFT JOIN users u2 ON m.mentor_id = u2.id
    WHERE sr.is_sent = FALSE 
    AND sr.reminder_time <= NOW()
    AND s.status = 'scheduled'
    ORDER BY sr.reminder_time ASC
    LIMIT 50
";

$stmt = $db->query($reminders_query);
$reminders = $stmt->fetchAll();

error_log("[CRON] Found " . count($reminders) . " pending reminders to send");

$sent_count = 0;
$failed_count = 0;

foreach ($reminders as $reminder) {
    try {
        // Format reminder type
        $reminder_label = [
            '24_hours' => '24 hours',
            '1_hour' => '1 hour',
            '30_minutes' => '30 minutes'
        ][$reminder['reminder_type']] ?? $reminder['reminder_type'];
        
        // Format session date and time
        $session_datetime = date('l, F j, Y \a\t g:i A', strtotime($reminder['session_date'] . ' ' . $reminder['start_time']));
        
        $mailer = new SimplePHPMailer();
        $mailer->setTo($reminder['email']);
        $mailer->setSubject("Reminder: Study Session in {$reminder_label}");
        
        $message = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #7c3aed;'>Study Session Reminder</h2>
                <p>Hi " . htmlspecialchars($reminder['first_name']) . ",</p>
                <p>This is a reminder that you have a study session scheduled in <strong>{$reminder_label}</strong>.</p>
                
                <div style='background: #f8fafc; padding: 1.5rem; border-radius: 8px; margin: 1.5rem 0; border-left: 4px solid #7c3aed;'>
                    <h3 style='margin-top: 0; color: #111827;'>Session Details</h3>
                    <p style='margin: 0.5rem 0;'><strong>Subject:</strong> " . htmlspecialchars($reminder['subject']) . "</p>
                    <p style='margin: 0.5rem 0;'><strong>Partner:</strong> " . htmlspecialchars($reminder['partner_name']) . "</p>
                    <p style='margin: 0.5rem 0;'><strong>Date & Time:</strong> {$session_datetime}</p>
                    <p style='margin: 0.5rem 0;'><strong>Duration:</strong> " . date('g:i A', strtotime($reminder['start_time'])) . " - " . date('g:i A', strtotime($reminder['end_time'])) . "</p>
                    " . ($reminder['location'] ? "<p style='margin: 0.5rem 0;'><strong>Location:</strong> " . htmlspecialchars($reminder['location']) . "</p>" : "") . "
                    " . ($reminder['notes'] ? "<p style='margin: 0.5rem 0;'><strong>Notes:</strong> " . htmlspecialchars($reminder['notes']) . "</p>" : "") . "
                </div>
                
                <p>Don't forget to prepare any materials you might need!</p>
                <p style='margin: 1.5rem 0;'>
                    <a href='" . BASE_URL . "/sessions/history.php' style='display: inline-block; padding: 12px 24px; background: #7c3aed; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;'>View Session Details</a>
                </p>
                
                <p style='color: #6b7280; font-size: 0.875rem; margin-top: 2rem; border-top: 1px solid #e5e7eb; padding-top: 1rem;'>
                    You're receiving this reminder because you scheduled a study session. You can manage your reminder preferences in your account settings.
                </p>
            </div>
        ";
        
        $mailer->setBody($message);
        
        if ($mailer->send()) {
            // Mark reminder as sent
            $update_stmt = $db->prepare("UPDATE session_reminders SET is_sent = TRUE, sent_at = NOW() WHERE id = ?");
            $update_stmt->execute([$reminder['id']]);
            $sent_count++;
            
            error_log("[CRON] Successfully sent reminder #{$reminder['id']} to {$reminder['email']}");
            
            // Log activity
            $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'reminder_sent', ?, 'cron')");
            $log_stmt->execute([$reminder['user_id'], json_encode(['session_id' => $reminder['session_id'], 'type' => $reminder['reminder_type']])]);
        } else {
            $failed_count++;
            error_log("[CRON] Failed to send reminder #{$reminder['id']} to {$reminder['email']}");
        }
        
    } catch (Exception $e) {
        $failed_count++;
        error_log("[CRON] Exception sending reminder #{$reminder['id']}: " . $e->getMessage());
    }
}

// Log cron execution summary
$summary = "Session reminders cron completed: Sent {$sent_count}, Failed {$failed_count}";
error_log("[CRON] " . $summary);

if (php_sapi_name() !== 'cli') {
    // Running via web browser
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Session Reminders Cron Job</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .success { background: #d1fae5; color: #065f46; padding: 15px; border-radius: 8px; margin: 10px 0; }
            .error { background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 8px; margin: 10px 0; }
            .info { background: #dbeafe; color: #1e40af; padding: 15px; border-radius: 8px; margin: 10px 0; }
            h1 { color: #7c3aed; }
            .stats { background: #f3f4f6; padding: 15px; border-radius: 8px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <h1>Session Reminders Cron Job</h1>
        <div class='info'>
            <strong>Execution Time:</strong> " . date('Y-m-d H:i:s') . "
        </div>
        <div class='stats'>
            <h3>Results</h3>
            <p><strong>Reminders Found:</strong> " . count($reminders) . "</p>
            <p><strong>Successfully Sent:</strong> {$sent_count}</p>
            <p><strong>Failed:</strong> {$failed_count}</p>
        </div>";
    
    if ($sent_count > 0) {
        echo "<div class='success'>✓ Successfully sent {$sent_count} reminder(s)</div>";
    }
    
    if ($failed_count > 0) {
        echo "<div class='error'>✗ Failed to send {$failed_count} reminder(s). Check error logs for details.</div>";
    }
    
    if (count($reminders) === 0) {
        echo "<div class='info'>No pending reminders to send at this time.</div>";
    }
    
    // Check SMTP configuration
    if (SMTP_USERNAME === 'your-email@gmail.com' || empty(SMTP_USERNAME)) {
        echo "<div class='error'>
            <strong>⚠ SMTP Not Configured</strong><br>
            Emails are being logged but not actually sent. To send real emails, configure SMTP credentials in <code>config/email.php</code>
        </div>";
    }
    
    echo "
        <div class='info'>
            <h3>Setup Instructions</h3>
            <p>To automate this cron job on Windows (XAMPP):</p>
            <ol>
                <li>Open Task Scheduler</li>
                <li>Create a new Basic Task</li>
                <li>Set trigger to run every 5 minutes</li>
                <li>Action: Start a program</li>
                <li>Program: <code>C:\\xampp\\php\\php.exe</code></li>
                <li>Arguments: <code>C:\\xampp\\htdocs\\study-mentorship-platform\\cron\\send_session_reminders.php</code></li>
            </ol>
            <p>Or manually trigger by visiting this page.</p>
        </div>
    </body>
    </html>";
}
?>
