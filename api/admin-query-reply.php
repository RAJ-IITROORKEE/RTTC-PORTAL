<?php
/**
 * RTTC 2026 - API: Admin Reply to Query
 * POST /api/admin-query-reply
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

$queryId      = (int)($_POST['query_id']      ?? 0);
$replyMessage = trim($_POST['reply_message']  ?? '');
$grantAccess  = ($_POST['grant_access']       ?? '0') === '1';

if (!$queryId || strlen($replyMessage) < 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

$db = db();

// Fetch the query
$stmt = $db->prepare("SELECT * FROM student_queries WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $queryId);
$stmt->execute();
$query = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$query) {
    echo json_encode(['success' => false, 'message' => 'Query not found.']);
    exit;
}

// Determine if we're granting edit access
$editAccessGranted = $grantAccess && !empty($query['user_id']);

// Update query record
$upd = $db->prepare("
    UPDATE student_queries
    SET reply_message = ?, status = 'resolved', replied_at = NOW(),
        edit_access_granted = ?, updated_at = NOW()
    WHERE id = ?
");
$ega = $editAccessGranted ? 1 : 0;
$upd->bind_param('sii', $replyMessage, $ega, $queryId);
if (!$upd->execute()) {
    $upd->close();
    echo json_encode(['success' => false, 'message' => 'Database error updating query.']);
    exit;
}
$upd->close();

// Grant edit access in user_edit_access table if requested
if ($editAccessGranted) {
    $adminId  = SessionHelper::get('admin_id');
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
    $note = 'Granted via query reply (Query #' . $queryId . ')';

    // Deactivate previous grants for same user
    $deact = $db->prepare("UPDATE user_edit_access SET is_active = 0 WHERE user_id = ?");
    $deact->bind_param('i', $query['user_id']);
    $deact->execute();
    $deact->close();

    $ins = $db->prepare("
        INSERT INTO user_edit_access (user_id, granted_by, granted_at, expires_at, is_active, note, created_at, updated_at)
        VALUES (?, ?, NOW(), ?, 1, ?, NOW(), NOW())
    ");
    $ins->bind_param('iiss', $query['user_id'], $adminId, $expiresAt, $note);
    $ins->execute();
    $ins->close();
}

// Send email to student
$emailSent = sendQueryReplyEmail(
    $query['email'],
    $query['name'],
    $query['issue_subject'],
    $replyMessage,
    $editAccessGranted
);

if (!$emailSent['success']) {
    // Log but don't fail — DB is already updated
    error_log('Query reply email failed: ' . $emailSent['message']);
}

echo json_encode([
    'success' => true,
    'message' => 'Reply sent and query marked as resolved.',
    'email_sent' => $emailSent['success'],
]);

// ---- Email helper ----
function sendQueryReplyEmail(string $to, string $name, string $subject, string $reply, bool $accessGranted): array
{
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
        $mail->Port       = (int) SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to, $name);
        $mail->isHTML(true);
        $mail->Subject = 'RTTC 2026 – Response to Your Query: ' . $subject;

        // Capture template
        $studentName      = $name;
        $subjectLabel     = $subject;
        $replyMessage     = $reply;
        $editAccessGranted = $accessGranted;
        ob_start();
        include ROOT_PATH . '/views/email/query-reply-template.php';
        $mail->Body = ob_get_clean();
        $mail->AltBody = "Dear $name,\n\nThank you for reaching out. Here is our response:\n\n$reply\n\n-- RTTC 2026 Admission Team";

        $mail->send();
        return ['success' => true, 'message' => 'Email sent.'];
    } catch (\Exception $e) {
        error_log('Query reply email error: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
