<?php
/**
 * RTTC 2026 - API: Submit Student Query
 * POST /api/submit-query
 * Returns JSON {success, message}
 */
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';

// Read everything we need from session, then release the lock immediately.
// PHP session files are locked for the duration of the script; releasing early
// prevents this AJAX request from blocking (or being blocked by) the page load.
$userId = SessionHelper::isLoggedIn() ? SessionHelper::get('user_id') : null;
session_write_close();

$conn = db();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to submit a query.']);
    exit;
}

$paidStmt = $conn->prepare("SELECT EXISTS (
    SELECT 1 FROM payment WHERE user_id = ? AND status = 'success'
) AS has_successful_payment");
$paidStmt->bind_param('i', $userId);
$paidStmt->execute();
$hasSuccessfulPayment = !empty($paidStmt->get_result()->fetch_assoc()['has_successful_payment']);
$paidStmt->close();

if ($hasSuccessfulPayment) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'You cannot raise a query after your application has been submitted with successful payment.',
    ]);
    exit;
}

$contactStmt = $conn->prepare("SELECT email, phone FROM users WHERE id = ? LIMIT 1");
$contactStmt->bind_param('i', $userId);
$contactStmt->execute();
$contact = $contactStmt->get_result()->fetch_assoc() ?: [];
$contactStmt->close();
$email = trim($contact['email'] ?? '');
$phone = trim($contact['phone'] ?? '');

// Collect & sanitize inputs. Contact details come from the authenticated account.
$name    = trim($_POST['name']    ?? '');
$subject = trim($_POST['issue_subject'] ?? '');
$message = trim($_POST['message'] ?? '');

// Validation
$errors = [];
if (strlen($name) < 2)    $errors[] = 'Full name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
if (strlen($subject) < 1) $errors[] = 'Please select a subject.';
if (strlen($message) < 20) $errors[] = 'Message must be at least 20 characters.';
if (strlen($message) > 2000) $errors[] = 'Message is too long.';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// Per-email rate limit: max 3 queries per calendar day
try {
    $rateStmt = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM student_queries
        WHERE email = ? AND DATE(created_at) = CURDATE()
    ");
    if (!$rateStmt) throw new RuntimeException('Rate-limit prepare failed: ' . $conn->error);
    $rateStmt->bind_param('s', $email);
    $rateStmt->execute();
    $rateRow = $rateStmt->get_result()->fetch_assoc();
    $rateStmt->close();

    if ((int)$rateRow['cnt'] >= 3) {
        echo json_encode([
            'success' => false,
            'message' => 'You have already submitted 3 queries today. Please try again tomorrow.'
        ]);
        exit;
    }
} catch (Throwable $e) {
    error_log('submit-query rate-limit error: ' . $e->getMessage());
    // Non-fatal: let the insert proceed
}

// Insert into student_queries
try {
    $stmt = $conn->prepare("
        INSERT INTO student_queries (user_id, name, email, phone, issue_subject, message, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
    ");
    if (!$stmt) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('isssss', $userId, $name, $email, $phone, $subject, $message);
    if (!$stmt->execute()) {
        throw new RuntimeException('Execute failed: ' . $stmt->error);
    }
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Query submitted successfully.']);
} catch (Throwable $e) {
    error_log('submit-query error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again later.']);
}
