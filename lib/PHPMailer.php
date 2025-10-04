<?php
/**
 * PHPMailer - PHP email creation and transport class.
 * 
 * This is a simplified version. For production, download the full PHPMailer library:
 * https://github.com/PHPMailer/PHPMailer
 * 
 * Installation via Composer (recommended):
 * composer require phpmailer/phpmailer
 * 
 * Manual installation:
 * 1. Download from https://github.com/PHPMailer/PHPMailer/releases
 * 2. Extract to lib/PHPMailer/
 * 3. Include: require 'lib/PHPMailer/src/PHPMailer.php';
 */

// For now, we'll use PHP's mail() function as a fallback
// In production, replace this with actual PHPMailer

class SimplePHPMailer {
    private $to;
    private $subject;
    private $body;
    private $from;
    private $fromName;
    
    public function __construct() {
        $this->from = SMTP_FROM_EMAIL;
        $this->fromName = SMTP_FROM_NAME;
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
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: {$this->fromName} <{$this->from}>" . "\r\n";
        
        return mail($this->to, $this->subject, $this->body, $headers);
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
?>
