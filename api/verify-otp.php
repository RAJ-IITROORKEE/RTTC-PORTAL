<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false]));
}

$email = SecurityHelper::sanitize($_POST['email'] ?? '');
$otp   = trim($_POST['otp'] ?? '');
$type  = SecurityHelper::sanitize($_POST['type'] ?? 'signup');

if (empty($email) || empty($otp)) {
    echo json_encode(['success' => false, 'message' => 'Email and OTP are required']);
    exit;
}

$result = OTPHelper::verifyOTP($email, $otp, $type);
echo json_encode($result);
