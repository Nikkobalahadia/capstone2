<?php
/**
 * Email Configuration for OTP Authentication
 * 
 * SETUP INSTRUCTIONS:
 * 
 * For Gmail SMTP:
 * 1. Enable 2-Factor Authentication on your Gmail account
 * 2. Generate an App Password: https://myaccount.google.com/apppasswords
 * 3. Update the credentials below
 * 
 * For SendGrid:
 * 1. Sign up at https://sendgrid.com
 * 2. Create an API key
 * 3. Change SMTP_HOST to 'smtp.sendgrid.net'
 * 4. Use 'apikey' as SMTP_USERNAME
 * 5. Use your API key as SMTP_PASSWORD
 */

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'studybuddy.mentorship@gmail.com'); // Change this
define('SMTP_PASSWORD', 'vkci ophi bzbl awvc'); // Change this (use App Password, not regular password)
define('SMTP_FROM_EMAIL', 'studybuddy.mentorship@gmail.com'); // Change this
define('SMTP_FROM_NAME', 'StudyConnect');
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'

// OTP Configuration
define('OTP_LENGTH', 6);
define('OTP_EXPIRY_MINUTES', 10);
define('OTP_MAX_ATTEMPTS', 3);
?>
