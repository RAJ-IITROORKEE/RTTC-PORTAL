<?php
/**
 * Admin API for preparing and sending unpaid applicant reminders.
 * POST /api/admin-send-unpaid-email
 */
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';
SecurityHelper::requireAdminAuth();

header('Content-Type: application/json');

function unpaidEmailJson(array $payload, int $status = 200)
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    unpaidEmailJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

SecurityHelper::verifyCsrf();

$db = db();
$action = $_POST['action'] ?? '';

if ($action === 'prepare') {
    $stmt = $db->prepare(
        "SELECT u.id, u.email,
                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', pd.firstname, NULLIF(pd.middlename, ''), pd.lastname)), ''), u.username) AS name
         FROM users u
         LEFT JOIN personal_details pd ON pd.user_id = u.id
         LEFT JOIN unpaid_email_log log ON log.user_id = u.id
         WHERE u.is_active = 1
           AND NOT EXISTS (
               SELECT 1 FROM payment paid
               WHERE paid.user_id = u.id AND paid.status = 'success'
           )
           AND (log.id IS NULL OR log.status <> 'sent')
         ORDER BY u.id ASC"
    );

    if (!$stmt) {
        unpaidEmailJson(['success' => false, 'message' => 'Email tracking is not ready. Apply the database migration first.'], 500);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $recipients = [];
    while ($row = $result->fetch_assoc()) {
        $recipients[] = [
            'id' => (int)$row['id'],
            'email' => (string)$row['email'],
            'name' => trim((string)$row['name']) ?: 'Applicant',
        ];
    }
    $stmt->close();

    unpaidEmailJson([
        'success' => true,
        'message' => count($recipients) . ' unpaid applicant(s) are ready for email.',
        'recipients' => $recipients,
    ]);
}

// ── prepare_resend: ALL unpaid users regardless of prior email status ──────
if ($action === 'prepare_resend') {
    $stmt = $db->prepare(
        "SELECT u.id, u.email,
                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', pd.firstname, NULLIF(pd.middlename, ''), pd.lastname)), ''), u.username) AS name
         FROM users u
         LEFT JOIN personal_details pd ON pd.user_id = u.id
         WHERE u.is_active = 1
           AND NOT EXISTS (
               SELECT 1 FROM payment paid
               WHERE paid.user_id = u.id AND paid.status = 'success'
           )
         ORDER BY u.id ASC"
    );

    if (!$stmt) {
        unpaidEmailJson(['success' => false, 'message' => 'Database query failed.'], 500);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $recipients = [];
    while ($row = $result->fetch_assoc()) {
        $recipients[] = [
            'id'    => (int)$row['id'],
            'email' => (string)$row['email'],
            'name'  => trim((string)$row['name']) ?: 'Applicant',
        ];
    }
    $stmt->close();

    unpaidEmailJson([
        'success'    => true,
        'message'    => count($recipients) . ' unpaid applicant(s) ready for re-send.',
        'recipients' => $recipients,
    ]);
}

if ($action !== 'send') {
    unpaidEmailJson(['success' => false, 'message' => 'Invalid action.'], 400);
}

// force=1 means re-send even if already emailed (used by Re-send All button)
$force = ($_POST['force'] ?? '0') === '1';

$userId = (int)($_POST['user_id'] ?? 0);
$subject = trim((string)($_POST['subject'] ?? ''));
$template = trim((string)($_POST['template'] ?? ''));

if ($userId < 1 || $subject === '' || $template === '') {
    unpaidEmailJson(['success' => false, 'message' => 'Recipient, subject, and message are required.'], 400);
}
if (strlen($subject) > 180 || strlen($template) > 12000) {
    unpaidEmailJson(['success' => false, 'message' => 'The subject or message is too long.'], 400);
}

// Re-check payment state immediately before sending. A candidate may pay while
// the admin page is preparing or dispatching a batch.
$stmt = $db->prepare(
    "SELECT u.email,
            COALESCE(NULLIF(TRIM(CONCAT_WS(' ', pd.firstname, NULLIF(pd.middlename, ''), pd.lastname)), ''), u.username) AS name,
            log.status AS email_status
     FROM users u
     LEFT JOIN personal_details pd ON pd.user_id = u.id
     LEFT JOIN unpaid_email_log log ON log.user_id = u.id
     WHERE u.id = ? AND u.is_active = 1
       AND NOT EXISTS (
           SELECT 1 FROM payment paid
           WHERE paid.user_id = u.id AND paid.status = 'success'
       )
     LIMIT 1"
);
if (!$stmt) {
    unpaidEmailJson(['success' => false, 'message' => 'Email tracking is not ready. Apply the database migration first.'], 500);
}
$stmt->bind_param('i', $userId);
$stmt->execute();
$recipient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$recipient) {
    unpaidEmailJson(['success' => false, 'message' => 'Applicant is no longer unpaid or could not be found.'], 409);
}
if (!$force && ($recipient['email_status'] ?? '') === 'sent') {
    unpaidEmailJson(['success' => true, 'skipped' => true, 'message' => 'Email was already sent to this applicant.']);
}

// Record an in-progress attempt so the admin can distinguish a retryable
// failure from a successful delivery. Only "sent" is shown as Sent in UI.
if ($force) {
    // Force re-send: reset any existing 'sent' record back to 'sending'
    $claim = $db->prepare(
        "INSERT INTO unpaid_email_log (user_id, email, status, attempts, last_error, sent_at)
         VALUES (?, ?, 'sending', 1, NULL, NULL)
         ON DUPLICATE KEY UPDATE
             email = VALUES(email),
             status = 'sending',
             attempts = attempts + 1,
             last_error = NULL,
             sent_at = NULL"
    );
} else {
    $claim = $db->prepare(
        "INSERT INTO unpaid_email_log (user_id, email, status, attempts, last_error, sent_at)
         VALUES (?, ?, 'sending', 1, NULL, NULL)
         ON DUPLICATE KEY UPDATE
             email = VALUES(email),
             status = IF(status = 'sent', 'sent', 'sending'),
             attempts = IF(status = 'sent', attempts, attempts + 1),
             last_error = IF(status = 'sent', last_error, NULL)"
    );
}
if (!$claim) {
    unpaidEmailJson(['success' => false, 'message' => 'Unable to start the email attempt.'], 500);
}
$claim->bind_param('is', $userId, $recipient['email']);
$claim->execute();
$claim->close();

if (!$force && ($recipient['email_status'] ?? '') === 'sent') {
    unpaidEmailJson(['success' => true, 'skipped' => true, 'message' => 'Email was already sent to this applicant.']);
}

$mailResult = UnpaidEmailHelper::send(
    (string)$recipient['email'],
    (string)$recipient['name'],
    $subject,
    $template
);

if ($mailResult['success']) {
    $update = $db->prepare(
        "UPDATE unpaid_email_log
         SET status = 'sent', sent_at = NOW(), last_error = NULL
         WHERE user_id = ?"
    );
    if ($update) {
        $update->bind_param('i', $userId);
        $update->execute();
        $update->close();
    }
    unpaidEmailJson(['success' => true, 'message' => 'Email sent successfully.']);
}

$update = $db->prepare(
    "UPDATE unpaid_email_log
     SET status = 'failed', last_error = ?
     WHERE user_id = ?"
);
if ($update) {
    $update->bind_param('si', $error, $userId);
    $update->execute();
    $update->close();
}

unpaidEmailJson(['success' => false, 'message' => $error], 502);
