<?php
/**
 * RTTC 2026 - API: Submit Student Query
 * POST /api/submit-query
 * Returns JSON {success, message}
 */
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';

$conn = db();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Rate limiting via session (max 3 queries per session)
$queryCount = SessionHelper::get('query_submit_count', 0);
if ($queryCount >= 3) {
    echo json_encode(['success' => false, 'message' => 'You have submitted too many queries. Please wait and try again later.']);
    exit;
}

// Collect & sanitize inputs
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

// Get user_id if logged in
$userId = SessionHelper::isLoggedIn() ? SessionHelper::get('user_id') : null;

// Insert into student_queries
$stmt = $conn->prepare("
    INSERT INTO student_queries (user_id, name, email, phone, issue_subject, message, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
");
$stmt->bind_param(
    'isssss',
    $userId,
    $name,
    $email,
    $phone,
    $subject,
    $message
);

if ($stmt->execute()) {
    // Update session rate limit
    SessionHelper::set('query_submit_count', $queryCount + 1);
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Query submitted successfully.']);
} else {
    $stmt->close();
    error_log('submit-query DB error: ' . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again later.']);
}
