<?php
/**
 * PHPMailer - PHP email creation and transport class.
 * 
 * This is a simplified SMTP implementation for development.
 * For production, use the full PHPMailer library:
 * composer require phpmailer/phpmailer
 */

class SimplePHPMailer {
    private $to;
    private $subject;
    private $body;
    private $from;
    private $fromName;
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $smtpEncryption;
    
    public function __construct() {
        $this->from = SMTP_FROM_EMAIL;
        $this->fromName = SMTP_FROM_NAME;
        $this->smtpHost = SMTP_HOST;
        $this->smtpPort = SMTP_PORT;
        $this->smtpUsername = SMTP_USERNAME;
        $this->smtpPassword = SMTP_PASSWORD;
        $this->smtpEncryption = SMTP_ENCRYPTION;
    }
    
    public function setTo($email) {
        $this->to = $email;
    }
    
    public function setSubject($subject) {
        $this->subject = $subject;
    }
    
    public function setBody($body) {
        $this->body = $body;
    }
    
    public function send() {
        // For development: Check if SMTP is configured
        if ($this->smtpUsername === 'your-email@gmail.com' || empty($this->smtpUsername)) {
            // SMTP not configured - log instead of sending
            error_log("[EMAIL] Would send to: {$this->to} | Subject: {$this->subject}");
            error_log("[EMAIL] Configure SMTP in config/email.php to send real emails");
            return true; // Return true for development to not block functionality
        }
        
        try {
            // Create SMTP connection
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);
            
            // Connect to SMTP server
            if ($this->smtpEncryption === 'ssl') {
                $socket = stream_socket_client(
                    "ssl://{$this->smtpHost}:{$this->smtpPort}",
                    $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context
                );
            } else {
                $socket = stream_socket_client(
                    "tcp://{$this->smtpHost}:{$this->smtpPort}",
                    $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context
                );
            }
            
            if (!$socket) {
                error_log("SMTP Connection failed: $errstr ($errno)");
                return false;
            }
            
            // Read server greeting
            $this->readResponse($socket);
            
            // Send EHLO
            fputs($socket, "EHLO {$this->smtpHost}\r\n");
            $this->readResponse($socket);
            
            // Start TLS if needed
            if ($this->smtpEncryption === 'tls') {
                fputs($socket, "STARTTLS\r\n");
                $this->readResponse($socket);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                fputs($socket, "EHLO {$this->smtpHost}\r\n");
                $this->readResponse($socket);
            }
            
            // Authenticate
            fputs($socket, "AUTH LOGIN\r\n");
            $this->readResponse($socket);
            fputs($socket, base64_encode($this->smtpUsername) . "\r\n");
            $this->readResponse($socket);
            fputs($socket, base64_encode($this->smtpPassword) . "\r\n");
            $this->readResponse($socket);
            
            // Send email
            fputs($socket, "MAIL FROM: <{$this->from}>\r\n");
            $this->readResponse($socket);
            fputs($socket, "RCPT TO: <{$this->to}>\r\n");
            $this->readResponse($socket);
            fputs($socket, "DATA\r\n");
            $this->readResponse($socket);
            
            // Email headers and body
            $message = "From: {$this->fromName} <{$this->from}>\r\n";
            $message .= "To: <{$this->to}>\r\n";
            $message .= "Subject: {$this->subject}\r\n";
            $message .= "MIME-Version: 1.0\r\n";
            $message .= "Content-type: text/html; charset=UTF-8\r\n";
            $message .= "\r\n";
            $message .= $this->body;
            $message .= "\r\n.\r\n";
            
            fputs($socket, $message);
            $this->readResponse($socket);
            
            // Close connection
            fputs($socket, "QUIT\r\n");
            $this->readResponse($socket);
            fclose($socket);
            
            return true;
        } catch (Exception $e) {
            error_log("Email send failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function readResponse($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') {
                break;
            }
        }
        return $response;
    }
}

// Helper function to send OTP email
function send_otp_email($email, $otp_code) {
    $mailer = new SimplePHPMailer();
    $mailer->setTo($email);
    $mailer->setSubject('Your StudyConnect Login Code');
    
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .otp-code { font-size: 32px; font-weight: bold; color: #667eea; text-align: center; letter-spacing: 8px; padding: 20px; background: white; border-radius: 8px; margin: 20px 0; }
            .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>StudyConnect</h1>
                <p>Your One-Time Password</p>
            </div>
            <div class='content'>
                <p>Hello,</p>
                <p>You requested to sign in to your StudyConnect account. Use the code below to complete your login:</p>
                <div class='otp-code'>{$otp_code}</div>
                <p><strong>This code will expire in " . OTP_EXPIRY_MINUTES . " minutes.</strong></p>
                <p>If you didn't request this code, please ignore this email.</p>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " StudyConnect. All rights reserved.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $mailer->setBody($body);
    return $mailer->send();
}

// Helper function to send session scheduled notification emails
/**
 * Send session scheduled notification email
 * 
 * @param string $to_email Recipient email address
 * @param string $to_name Recipient name
 * @param array $session_details Session information (subject, date, time, location, notes, partner_name)
 * @return bool Success status
 */
function send_session_notification($to_email, $to_name, $session_details) {
    $mailer = new SimplePHPMailer();
    $mailer->setTo($to_email);
    $mailer->setSubject('Session Scheduled - StudyConnect');
    
    $session_date = date('l, F j, Y', strtotime($session_details['date']));
    $start_time = date('g:i A', strtotime($session_details['start_time']));
    $end_time = date('g:i A', strtotime($session_details['end_time']));
    $location = !empty($session_details['location']) ? htmlspecialchars($session_details['location']) : 'Not specified';
    $notes = !empty($session_details['notes']) ? htmlspecialchars($session_details['notes']) : '';
    
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .session-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #7c3aed; }
            .detail-row { display: flex; padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
            .detail-row:last-child { border-bottom: none; }
            .detail-label { font-weight: bold; color: #6b7280; width: 120px; }
            .detail-value { color: #111827; }
            .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>📅 Session Scheduled</h1>
                <p>Your study session has been confirmed</p>
            </div>
            <div class='content'>
                <p>Hi " . htmlspecialchars($to_name) . ",</p>
                <p>Great news! A study session has been scheduled with <strong>" . htmlspecialchars($session_details['partner_name']) . "</strong>.</p>
                
                <div class='session-details'>
                    <h3 style='margin-top: 0; color: #7c3aed;'>Session Details</h3>
                    <div class='detail-row'>
                        <div class='detail-label'>Subject:</div>
                        <div class='detail-value'>" . htmlspecialchars($session_details['subject']) . "</div>
                    </div>
                    <div class='detail-row'>
                        <div class='detail-label'>Date:</div>
                        <div class='detail-value'>{$session_date}</div>
                    </div>
                    <div class='detail-row'>
                        <div class='detail-label'>Time:</div>
                        <div class='detail-value'>{$start_time} - {$end_time}</div>
                    </div>
                    <div class='detail-row'>
                        <div class='detail-label'>Location:</div>
                        <div class='detail-value'>{$location}</div>
                    </div>
                    " . (!empty($notes) ? "
                    <div class='detail-row'>
                        <div class='detail-label'>Notes:</div>
                        <div class='detail-value'>{$notes}</div>
                    </div>
                    " : "") . "
                </div>
                
                <p>We'll send you a reminder before the session starts. Make sure to prepare any materials you might need!</p>
                
                <div class='footer'>
                    <p>&copy; " . date('Y') . " StudyConnect. All rights reserved.</p>
                    <p>This is an automated notification. Please do not reply to this email.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $mailer->setBody($body);
    return $mailer->send();
}
?>
