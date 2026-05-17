<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false]));
}

$email = SecurityHelper::sanitize($_POST['email'] ?? '');
if (empty($email) || !ValidationHelper::validateEmail($email)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email']);
    exit;
}

$db   = db();
$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
$exists = $stmt->num_rows > 0;
$stmt->close();

echo json_encode(['success' => true, 'exists' => $exists]);
