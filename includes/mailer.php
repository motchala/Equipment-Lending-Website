<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

// ── TODO: Change these to your real credentials before deployment ──────────
define('MAIL_USERNAME', 'wcwcwcwcwcec@gmail.com');  // TODO: Replace with PUPSync Gmail address
define('MAIL_PASSWORD', 'jffk guoj tnxn veut');   // TODO: Replace with your Gmail App Password
define('MAIL_FROM_NAME', 'PUPSync Notifications');
// ──────────────────────────────────────────────────────────────────────────

function sendPupSyncEmail(string $to_email, string $to_name, string $subject, string $html_body): bool
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody = strip_tags($html_body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Silent fail — email error must never break the borrow flow
        error_log('[PUPSync Mailer] ' . $mail->ErrorInfo);
        return false;
    }
}