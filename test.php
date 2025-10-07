<?php
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    require __DIR__ . '/vendor/autoload.php';
if (isset($_POST['sendEmail'])) {

    $mail = new PHPMailer(true);

    try {
        // Enable debugging (for testing only)
        $mail->SMTPDebug = 3; // Change to 0 later when working
        $mail->Debugoutput = 'html';

        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'studybuddy.mentorship@gmail.com'; // your email
        $mail->Password   = 'vkci ophi bzbl awvc'; // your 16-char app password
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('balahadianikko2015@gmail.com', 'StudyConnect Test');
        $mail->addAddress('iraaasubarashi@gmail.com', 'Test Receiver');

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'PHPMailer Test from StudyConnect';
        $mail->Body    = '<h2>Success!</h2><p>This is a test email sent using PHPMailer via Gmail SMTP.</p>';

        // Send
        $mail->send();
        echo '<h3 style="color:green;">Email has been sent successfully!</h3>';
    } catch (Exception $e) {
        echo "<h3 style='color:red;'>Message could not be sent. Mailer Error: {$mail->ErrorInfo}</h3>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Send Email Test</title>
</head>
<body>
    <form method="POST">
        <button type="submit" name="sendEmail">Send Test Email</button>
    </form>
</body>
</html>
