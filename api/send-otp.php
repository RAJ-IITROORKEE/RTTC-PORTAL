<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false]));
}

$email = SecurityHelper::sanitize($_POST['email'] ?? '');
$type  = SecurityHelper::sanitize($_POST['type'] ?? 'signup');
$name  = SecurityHelper::sanitize($_POST['name'] ?? '');

if (empty($email) || !ValidationHelper::validateEmail($email)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email']);
    exit;
}

if ($type === 'signup') {
    $result = OTPHelper::sendSignupOTP($email, $name);
} elseif ($type === 'reset') {
    $result = OTPHelper::sendPasswordResetOTP($email);
} else {
    $result = ['success' => false, 'message' => 'Unknown OTP type'];
}

echo json_encode($result);
