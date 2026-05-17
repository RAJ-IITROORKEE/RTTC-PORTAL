<?php
/**
 * Razorpay Webhook Handler
 * URL: /api/payment-webhook.php
 * Must be set in Razorpay dashboard
 */
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';

// No session needed for webhook

$payload   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

// Verify webhook signature
$expectedSig = hash_hmac('sha256', $payload, RAZORPAY_WEBHOOK_SECRET);
if (!hash_equals($expectedSig, $signature)) {
    http_response_code(403);
    exit('Invalid signature');
}

$event = json_decode($payload, true);
if (empty($event)) {
    http_response_code(400);
    exit('Invalid payload');
}

$db = db();

if ($event['event'] === 'payment.captured') {
    $payment   = $event['payload']['payment']['entity'];
    $orderId   = $payment['order_id'];
    $paymentId = $payment['id'];

    // Find payment record by order_id
    $stmt = $db->prepare("UPDATE payment SET status = 'success', razorpay_payment_id = ? WHERE razorpay_order_id = ?");
    $stmt->bind_param('ss', $paymentId, $orderId);
    $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if ($affectedRows > 0) {
        // Get user_id from payment
        $s = $db->prepare("SELECT user_id FROM payment WHERE razorpay_order_id = ?");
        $s->bind_param('s', $orderId);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();
        if ($row) {
            $uid = $row['user_id'];
            $upd = $db->prepare("UPDATE registration_progress SET current_step = 4, is_submitted = 1 WHERE user_id = ?");
            $upd->bind_param('i', $uid);
            $upd->execute();
            $upd->close();
        }
    }
}

if ($event['event'] === 'payment.failed') {
    $payment = $event['payload']['payment']['entity'];
    $orderId = $payment['order_id'];
    $stmt = $db->prepare("UPDATE payment SET status = 'failed' WHERE razorpay_order_id = ?");
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $stmt->close();
}

http_response_code(200);
echo 'OK';
