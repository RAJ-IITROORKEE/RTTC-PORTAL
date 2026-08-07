<?php
/**
 * Mail helper for unpaid application reminders.
 */
if (!defined('APP_INIT')) die('Direct access not permitted');

require_once ROOT_PATH . '/vendor/phpmailer/phpmailer/src/Exception.php';
require_once ROOT_PATH . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once ROOT_PATH . '/vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

class UnpaidEmailHelper
{
    public static function defaultSubject(): string
    {
        return 'Important: Complete Your RTTC Application and Payment';
    }

    public static function defaultTemplate(): string
    {
        return "Dear {name},\n\n"
            . "Important Instruction for Applicants\n\n"
            . "All applicants are hereby informed that applications without successful submission and payment will be treated as incomplete and will be rejected. Candidates must complete both the application submission and the payment process before the deadline.\n\n"
            . "Last Date & Time for Submission and Payment:\n"
            . "09/08/2026 (Sunday), 05:59:59 PM\n\n"
            . "Complete Your Payment & Application:\n"
            . "https://rttcadmission.in/login\n\n"
            . "For the latest updates and notifications, visit:\n"
            . "https://rangiattcollege.in/\n\n"
            . "Admission Helpline (During Office Hours Only):\n"
            . "03621-359330\n\n"
            . "If you face any problem with the online application, please raise a query through the admission portal so that we can identify and resolve the issue promptly.\n\n"
            . "Regards,\n"
            . "RTTC Admissions Team\n"
            . "Rangia Teacher Training College";
    }

    /**
     * Send one reminder using the same SMTP configuration as OTP mail.
     *
     * @return array{success: bool, message: string}
     */
    public static function send(string $to, string $name, string $subject, string $template): array
    {
        $name = trim($name) !== '' ? trim($name) : 'Applicant';
        $message = str_replace('{name}', $name, trim($template));

        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || $message === '') {
            return ['success' => false, 'message' => 'Invalid recipient or email content.'];
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = (strtolower(SMTP_ENCRYPTION) === 'ssl')
                                ? PHPMailer::ENCRYPTION_SMTPS
                                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) SMTP_PORT;
            $mail->Timeout    = 15;
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to, $name);
            $mail->isHTML(true);
            $mail->Subject = trim($subject) !== '' ? trim($subject) : self::defaultSubject();
            $mail->Body    = self::htmlBody($message);
            $mail->AltBody = $message;
            $mail->send();

            return ['success' => true, 'message' => 'Email sent successfully.'];
        } catch (Throwable $exception) {
            error_log('Unpaid reminder email error: ' . $exception->getMessage());
            return ['success' => false, 'message' => 'Unable to send this email right now.'];
        }
    }

    private static function htmlBody(string $message): string
    {
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $safeMessage = preg_replace(
            '~(https?://[^\s<]+)~i',
            '<a href="$1" style="color:#27276d;font-weight:600;">$1</a>',
            $safeMessage
        );

        return '<div style="background:#f4f6fb;padding:28px 12px;font-family:Arial,sans-serif;color:#212529;">'
            . '<div style="max-width:620px;margin:0 auto;background:#fff;border:1px solid #e3e6ef;border-radius:12px;overflow:hidden;">'
            . '<div style="background:#27276d;color:#fff;padding:24px 28px;text-align:center;">'
            . '<div style="font-size:21px;font-weight:700;">Rangia Teacher Training College</div>'
            . '<div style="font-size:13px;opacity:.85;margin-top:5px;">B.Ed Admission Portal 2026-27</div>'
            . '</div>'
            . '<div style="padding:28px;line-height:1.65;font-size:15px;">'
            . $safeMessage
            . '</div>'
            . '<div style="padding:16px 28px;background:#f8f9fc;border-top:1px solid #edf0f5;color:#68707d;font-size:12px;text-align:center;">'
            . 'This is an official admission notification from RTTC Admissions.'
            . '</div></div></div>';
    }
}
