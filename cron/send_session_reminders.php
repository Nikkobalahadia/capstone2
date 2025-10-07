<?php
// Run this script every 5 minutes via cron: */5 * * * * php /path/to/send_session_reminders.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/email.php';

$db = getDB();

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

$sent_count = 0;
$failed_count = 0;

foreach ($reminders as $reminder) {
    try {
        // Format reminder type
        $reminder_label = [
            '24_hours' => '24 hours',
            '1_hour' => '1 hour',
            '30_minutes' => '30 minutes'
        ][$reminder['reminder_type']];
        
        // Format session date and time
        $session_datetime = date('l, F j, Y \a\t g:i A', strtotime($reminder['session_date'] . ' ' . $reminder['start_time']));
        
        // Prepare email content
        $subject = "Reminder: Study Session in {$reminder_label}";
        $message = "
            <h2>Study Session Reminder</h2>
            <p>Hi {$reminder['first_name']},</p>
            <p>This is a reminder that you have a study session scheduled in <strong>{$reminder_label}</strong>.</p>
            
            <div style='background: #f8fafc; padding: 1.5rem; border-radius: 8px; margin: 1.5rem 0;'>
                <h3 style='margin-top: 0;'>Session Details</h3>
                <p><strong>Subject:</strong> {$reminder['subject']}</p>
                <p><strong>Partner:</strong> {$reminder['partner_name']}</p>
                <p><strong>Date & Time:</strong> {$session_datetime}</p>
                <p><strong>Duration:</strong> " . date('g:i A', strtotime($reminder['start_time'])) . " - " . date('g:i A', strtotime($reminder['end_time'])) . "</p>
                " . ($reminder['location'] ? "<p><strong>Location:</strong> {$reminder['location']}</p>" : "") . "
                " . ($reminder['notes'] ? "<p><strong>Notes:</strong> {$reminder['notes']}</p>" : "") . "
            </div>
            
            <p>Don't forget to prepare any materials you might need!</p>
            <p><a href='" . BASE_URL . "/sessions/index.php' style='display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;'>View Session Details</a></p>
            
            <p style='color: #6b7280; font-size: 0.875rem; margin-top: 2rem;'>
                You can manage your reminder preferences in your <a href='" . BASE_URL . "/profile/settings.php'>account settings</a>.
            </p>
        ";
        
        // Send email
        if (send_email($reminder['email'], $subject, $message)) {
            // Mark reminder as sent
            $update_stmt = $db->prepare("UPDATE session_reminders SET is_sent = TRUE, sent_at = NOW() WHERE id = ?");
            $update_stmt->execute([$reminder['id']]);
            $sent_count++;
            
            // Log activity
            $log_stmt = $db->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'reminder_sent', ?, 'cron')");
            $log_stmt->execute([$reminder['user_id'], json_encode(['session_id' => $reminder['session_id'], 'type' => $reminder['reminder_type']])]);
        } else {
            $failed_count++;
        }
        
    } catch (Exception $e) {
        $failed_count++;
        error_log("Failed to send reminder {$reminder['id']}: " . $e->getMessage());
    }
}

// Log cron execution
if ($sent_count > 0 || $failed_count > 0) {
    error_log("Session reminders cron: Sent {$sent_count}, Failed {$failed_count}");
}
?>
