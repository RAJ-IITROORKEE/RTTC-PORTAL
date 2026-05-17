<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';

SecurityHelper::requireAuth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

SecurityHelper::verifyCsrf();

$db     = db();
$userId = SessionHelper::get('user_id');

$paymentId = SecurityHelper::sanitize($_POST['razorpay_payment_id'] ?? '');
$orderId   = SecurityHelper::sanitize($_POST['razorpay_order_id'] ?? '');
$signature = SecurityHelper::sanitize($_POST['razorpay_signature'] ?? '');

if (empty($paymentId) || empty($orderId) || empty($signature)) {
    SessionHelper::setFlash('error', 'Invalid payment response. Please contact support.');
    redirect(route('payment'));
}

// Verify signature
$expectedSig = hash_hmac('sha256', $orderId . '|' . $paymentId, RAZORPAY_KEY_SECRET);
if (!hash_equals($expectedSig, $signature)) {
    SessionHelper::setFlash('error', 'Payment verification failed. If money was deducted, please contact us.');
    redirect(route('payment'));
}

// Store payment record
$stmt = $db->prepare("INSERT INTO payment (user_id, razorpay_order_id, razorpay_payment_id, razorpay_signature, amount, currency, status)
    VALUES (?, ?, ?, ?, 50000, 'INR', 'success')
    ON DUPLICATE KEY UPDATE razorpay_payment_id=VALUES(razorpay_payment_id), razorpay_signature=VALUES(razorpay_signature), status='success'");
$stmt->bind_param('isss', $userId, $orderId, $paymentId, $signature);
$stmt->execute();
$stmt->close();

// Update progress to step 4
$upd = $db->prepare("UPDATE registration_progress SET current_step = 4, is_submitted = 1 WHERE user_id = ?");
$upd->bind_param('i', $userId);
$upd->execute();
$upd->close();

SessionHelper::setFlash('success', 'Payment successful! Your application has been submitted.');
redirect(route('payment.confirmation'));
