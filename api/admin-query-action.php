<?php
/**
 * RTTC 2026 - API: Admin Query Actions
 * POST /api/admin-query-action
 * Actions: mark_resolved | grant_access | revoke_access | delete
 * Returns JSON {success, message}
 */
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';
SecurityHelper::requireAdminAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

SecurityHelper::verifyCsrf();

$action  = trim($_POST['action']   ?? '');
$queryId = (int)($_POST['query_id'] ?? 0);

if (!$queryId || !in_array($action, ['mark_resolved', 'grant_access', 'revoke_access', 'delete'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$db = db();

// Verify query exists
$stmt = $db->prepare("SELECT q.id, q.user_id, q.name, q.email, q.issue_subject, u.email AS user_email
    FROM student_queries q
    LEFT JOIN users u ON u.id = q.user_id
    WHERE q.id = ? LIMIT 1");
$stmt->bind_param('i', $queryId);
$stmt->execute();
$query = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$query) {
    echo json_encode(['success' => false, 'message' => 'Query not found.']);
    exit;
}

switch ($action) {

    case 'mark_resolved':
        $upd = $db->prepare("UPDATE student_queries SET status = 'resolved', updated_at = NOW() WHERE id = ?");
        $upd->bind_param('i', $queryId);
        $ok = $upd->execute();
        $upd->close();
        echo json_encode([
            'success' => $ok,
            'message' => $ok ? 'Marked as resolved.' : 'Database error.',
        ]);
        break;

    case 'grant_access':
        if (!$query['user_id']) {
            echo json_encode(['success' => false, 'message' => 'No associated student account found.']);
            break;
        }
        // Always grant access to the student who owns the query. Do not trust
        // a client-supplied user ID from the admin page.
        $targetUserId = (int)$query['user_id'];
        $adminId      = SessionHelper::get('admin_id');
        $expiresAt    = date('Y-m-d H:i:s', strtotime('+7 days'));
        $note         = 'Granted via admin action (Query #' . $queryId . ')';

        // Deactivate any previous full-scope grants (preserve document-only grants)
        $deact = $db->prepare("UPDATE user_edit_access SET is_active = 0 WHERE user_id = ? AND scope = 'all'");
        $deact->bind_param('i', $targetUserId);
        $deact->execute();
        $deact->close();

        $ins = $db->prepare("
            INSERT INTO user_edit_access (user_id, granted_by, granted_at, expires_at, is_active, note, created_at, updated_at)
            VALUES (?, ?, NOW(), ?, 1, ?, NOW(), NOW())
        ");
        $ins->bind_param('iiss', $targetUserId, $adminId, $expiresAt, $note);
        $ok = $ins->execute();
        $ins->close();

        if ($ok) {
            // Mark query as having edit access granted
            $upd = $db->prepare("UPDATE student_queries SET edit_access_granted = 1, updated_at = NOW() WHERE id = ?");
            $upd->bind_param('i', $queryId);
            $upd->execute();
            $upd->close();
        }

        echo json_encode([
            'success' => $ok,
            'message' => $ok ? 'Edit access granted for 7 days.' : 'Failed to grant access.',
        ]);
        break;

    case 'revoke_access':
        if (!$query['user_id']) {
            echo json_encode(['success' => false, 'message' => 'No associated student account found.']);
            break;
        }

        // Revoke access for the student, including grants created from older queries.
        $targetUserId = (int)$query['user_id'];
        $revoke = $db->prepare("UPDATE user_edit_access SET is_active = 0, updated_at = NOW() WHERE user_id = ? AND is_active = 1");
        $revoke->bind_param('i', $targetUserId);
        $ok = $revoke->execute();
        $revoke->close();

        if ($ok) {
            $clearFlags = $db->prepare("UPDATE student_queries SET edit_access_granted = 0, updated_at = NOW() WHERE user_id = ?");
            $clearFlags->bind_param('i', $targetUserId);
            $clearFlags->execute();
            $clearFlags->close();

            $recipient = trim((string)($query['user_email'] ?? '')) ?: trim((string)$query['email']);
            $emailSent = sendEditAccessRevokedEmail(
                $recipient,
                (string)$query['name']
            );
            if (!$emailSent['success']) {
                error_log('Edit access revocation email failed: ' . $emailSent['message']);
            }
        } else {
            $emailSent = ['success' => false, 'message' => 'Access could not be revoked.'];
        }

        echo json_encode([
            'success' => $ok,
            'message' => $ok ? 'Edit access revoked and the student was notified by email.' : 'Failed to revoke edit access.',
            'email_sent' => $emailSent['success'],
        ]);
        break;

    case 'delete':
        $del = $db->prepare("DELETE FROM student_queries WHERE id = ?");
        $del->bind_param('i', $queryId);
        $ok = $del->execute();
        $del->close();
        echo json_encode([
            'success' => $ok,
            'message' => $ok ? 'Query deleted.' : 'Database error.',
        ]);
        break;
}

function sendEditAccessRevokedEmail(string $to, string $name): array
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Student email address is invalid.'];
    }

    require_once ROOT_PATH . '/vendor/phpmailer/phpmailer/src/Exception.php';
    require_once ROOT_PATH . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once ROOT_PATH . '/vendor/phpmailer/phpmailer/src/SMTP.php';

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = (strtolower(SMTP_ENCRYPTION) === 'ssl')
                            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to, $name);
        $mail->isHTML(true);
        $mail->Subject = 'RTTC 2026 – Edit Access Revoked';
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $mail->Body = "<p>Dear {$safeName},</p>
            <p>Your temporary edit access to the RTTC 2026 registration forms has been revoked by the admission office.</p>
            <p>Your submitted application is now locked again. If you believe this was done in error, please contact the college office.</p>
            <p>Regards,<br>RTTC 2026 Admission Team</p>";
        $mail->AltBody = "Dear {$name},\n\nYour temporary edit access to the RTTC 2026 registration forms has been revoked by the admission office. Your submitted application is now locked again. If you believe this was done in error, please contact the college office.\n\nRegards,\nRTTC 2026 Admission Team";
        $mail->send();
        return ['success' => true, 'message' => 'Email sent.'];
    } catch (\Throwable $e) {
        error_log('Edit access revocation email error: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
