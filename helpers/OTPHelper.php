<?php
/**
 * RTTC 2026 - OTP Helper (DB-backed, not session-based)
 *
 * All methods use db() internally — no $conn parameter needed.
 * All methods return ['success' => bool, 'message' => string].
 */
if (!defined('APP_INIT')) die('Direct access not permitted');

// Load only PHPMailer classes to avoid Composer autoloading app helpers twice.
require_once ROOT_PATH . '/vendor/phpmailer/phpmailer/src/Exception.php';
require_once ROOT_PATH . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once ROOT_PATH . '/vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class OTPHelper
{
    /**
     * Generate, store, and email a signup OTP.
     *
     * @param  string $email  Recipient email
     * @param  string $name   Recipient name (for personalisation)
     * @return array  ['success' => bool, 'message' => string]
     */
    public static function sendSignupOTP(string $email, string $name = ''): array
    {
        return self::generate($email, 'signup');
    }

    /**
     * Generate, store, and email a password-reset OTP.
     *
     * @param  string $email  Recipient email
     * @return array  ['success' => bool, 'message' => string]
     */
    public static function sendPasswordResetOTP(string $email): array
    {
        return self::generate($email, 'reset');
    }

    /**
     * Verify an OTP from the DB.
     *
     * @param  string $email
     * @param  string $otp
     * @param  string $purpose  'signup' or 'reset'
     * @return array  ['success' => bool, 'message' => string]
     */
    public static function verifyOTP(string $email, string $otp, string $purpose = 'signup'): array
    {
        $conn = db();
        $now  = date('Y-m-d H:i:s');
        $stmt = $conn->prepare(
            "SELECT id FROM otp_tokens
             WHERE email = ? AND otp_code = ? AND purpose = ? AND is_used = 0 AND expires_at > ?
             ORDER BY id DESC LIMIT 1"
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error.'];
        }
        $stmt->bind_param('ssss', $email, $otp, $purpose, $now);
        $stmt->execute();
        $stmt->bind_result($id);
        $found = $stmt->fetch();
        $stmt->close();

        if ($found && $id) {
            $upd = $conn->prepare("UPDATE otp_tokens SET is_used = 1 WHERE id = ?");
            $upd->bind_param('i', $id);
            $upd->execute();
            $upd->close();
            return ['success' => true, 'message' => 'OTP verified successfully.'];
        }

        return ['success' => false, 'message' => 'Invalid or expired OTP. Please try again.'];
    }

    // ── Private helpers ──────────────────────────────────────

    private static function generate(string $email, string $purpose): array
    {
        $conn = db();

        // Invalidate old unused OTPs for this email + purpose
        $del = $conn->prepare("UPDATE otp_tokens SET is_used = 1 WHERE email = ? AND purpose = ? AND is_used = 0");
        if ($del) {
            $del->bind_param('ss', $email, $purpose);
            $del->execute();
            $del->close();
        }

        $otp     = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + OTP_EXPIRY);

        $ins = $conn->prepare("INSERT INTO otp_tokens (email, otp_code, purpose, expires_at) VALUES (?, ?, ?, ?)");
        if (!$ins) {
            return ['success' => false, 'message' => 'Failed to store OTP.'];
        }
        $ins->bind_param('ssss', $email, $otp, $purpose, $expires);
        if (!$ins->execute()) {
            $ins->close();
            return ['success' => false, 'message' => 'Failed to store OTP.'];
        }
        $ins->close();

        $sent = self::mail($email, $otp, $purpose);
        if ($sent !== true) {
            return ['success' => false, 'message' => $sent];
        }
        return ['success' => true, 'message' => 'OTP sent successfully.'];
    }

    private static function mail(string $to, string $otp, string $purpose): true|string
    {
        return self::sendViaPHPMailer($to, $otp, $purpose);
    }

     private static function sendViaPHPMailer(string $to, string $otp, string $purpose): true|string
     {
         $maxRetries = 3;
         $retryDelay = 1; // seconds
         $lastError = '';

         for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
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
                 $mail->Timeout    = 10; // Connection timeout
                 $mail->CharSet    = 'UTF-8';
                 $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                 $mail->addAddress($to);
                 $mail->isHTML(true);
                 $mail->Subject = self::subject($purpose);
                 $mail->Body    = self::body($otp, $purpose);
                 $mail->AltBody = "Your OTP is: $otp (valid for 10 minutes)";
                 $mail->send();
                 return true;

             } catch (\Exception $e) {
                 $lastError = $e->getMessage();
                 error_log("OTP mail error (attempt $attempt/$maxRetries): " . $lastError);

                 // Retry on temporary failures (don't retry on permanent errors like invalid email)
                 $isTempError = (
                     strpos($lastError, 'Temporary lookup failure') !== false ||
                     strpos($lastError, 'Connection refused') !== false ||
                     strpos($lastError, 'Connection timed out') !== false ||
                     strpos($lastError, 'DNS') !== false ||
                     strpos($lastError, 'SMTP') !== false && strpos($lastError, '550') === false
                 );

                 if ($attempt < $maxRetries && $isTempError) {
                     sleep($retryDelay);
                     $retryDelay *= 2; // Exponential backoff
                     continue;
                 }

                 // Permanent error or last attempt
                 break;
             }
         }

         // Don't expose SMTP details to user
         return 'Failed to send OTP email. Please try again later.';
     }

    private static function subject(string $purpose): string
    {
        return $purpose === 'signup'
            ? 'RTTC 2026 – Email Verification OTP'
            : 'RTTC 2026 – Password Reset OTP';
    }

    private static function body(string $otp, string $purpose): string
    {
        $action = $purpose === 'signup' ? 'verify your email address' : 'reset your password';
        return <<<HTML
        <div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;padding:32px;border:1px solid #e0e0e0;border-radius:10px;">
          <div style="text-align:center;margin-bottom:20px;">
            <h2 style="color:#27276d;margin-bottom:4px;">Rangia Teacher Training College</h2>
            <p style="color:#666;font-size:0.9em;margin:0;">B.Ed. First Year Admission 2025-26</p>
          </div>
          <p>Dear Applicant,</p>
          <p>Use the OTP below to <strong>{$action}</strong>. It is valid for <strong>10 minutes</strong>.</p>
          <div style="text-align:center;margin:28px 0;">
            <span style="font-size:2.5em;font-weight:bold;letter-spacing:0.5em;color:#27276d;background:#f0f4ff;padding:16px 32px;border-radius:8px;display:inline-block;">{$otp}</span>
          </div>
          <p style="color:#dc3545;font-size:0.9em;"><strong>Do not share this OTP with anyone.</strong></p>
          <hr style="border:none;border-top:1px solid #eee;margin:20px 0;">
          <p style="font-size:0.85em;color:#888;">For support: <a href="mailto:admissionrttc@gmail.com">admissionrttc@gmail.com</a> | +91 03621-359330</p>
        </div>
        HTML;
    }
}
