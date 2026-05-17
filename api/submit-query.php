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

// Collect & sanitize inputs FIRST (need email for rate-limit check)
$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$phone   = trim($_POST['phone']   ?? '');
$subject = trim($_POST['issue_subject'] ?? '');
$message = trim($_POST['message'] ?? '');

// Validation
$errors = [];
if (strlen($name) < 2)    $errors[] = 'Full name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
if (strlen($subject) < 1) $errors[] = 'Please select a subject.';
if (strlen($message) < 20) $errors[] = 'Message must be at least 20 characters.';
if (strlen($message) > 2000) $errors[] = 'Message is too long.';
if ($phone !== '' && !preg_match('/^[0-9]{10}$/', $phone)) $errors[] = 'Phone must be a 10-digit number.';

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
