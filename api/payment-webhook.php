<?php
/**
 * Razorpay Webhook Handler
 * Supported endpoint URLs:
 * - /api/payment-webhook.php
 * - /webhook/payment.php (Razorpay dashboard compatibility URL)
 */
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';

$respond = static function (int $status, string $body): void {
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    exit($body);
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respond(405, 'Method not allowed');
}

$payload   = file_get_contents('php://input');
$signature = trim((string)($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? ''));

if (!PaymentHelper::verifyWebhookSignature($payload, $signature, RAZORPAY_WEBHOOK_SECRET)) {
    $respond(403, 'Invalid signature');
}

$event = PaymentHelper::decodeWebhookPayload($payload);
if (!$event || empty($event['event'])) {
    $respond(400, 'Invalid payload');
}

// The configured webhook may include payment.authorized; fulfillment waits for captured.
if (!in_array($event['event'], ['payment.captured', 'payment.failed'], true)) {
    $respond(200, 'OK');
}

$payment = $event['payload']['payment']['entity'] ?? null;
if (!is_array($payment)) {
    $respond(400, 'Invalid payment payload');
}

$orderId   = trim((string)($payment['order_id'] ?? ''));
$paymentId = trim((string)($payment['id'] ?? ''));
if ($orderId === '' || $paymentId === '') {
    $respond(400, 'Missing payment identifiers');
}

$db = db();
$stmt = $db->prepare("SELECT id, user_id, amount, currency, status, razorpay_payment_id
    FROM payment WHERE razorpay_order_id = ? LIMIT 1");
if (!$stmt) {
    error_log('Could not prepare Razorpay webhook lookup: ' . $db->error);
    $respond(500, 'Temporary error');
}
$stmt->bind_param('s', $orderId);
$stmt->execute();
$localPayment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$localPayment) {
    // Do not attach a signed Razorpay event to an unknown applicant/order.
    error_log('Razorpay webhook received for unknown order: ' . $orderId);
    $respond(200, 'OK');
}

if ($event['event'] === 'payment.captured') {
    $amount = (int)($payment['amount'] ?? 0);
    $currency = (string)($payment['currency'] ?? '');
    if ($amount !== (int)$localPayment['amount'] || $currency !== (string)$localPayment['currency']) {
        error_log('Razorpay webhook amount/currency mismatch for order: ' . $orderId);
        $respond(400, 'Payment details mismatch');
    }

    $db->begin_transaction();

    $lock = $db->prepare("SELECT status, razorpay_payment_id FROM payment WHERE id = ? FOR UPDATE");
    if (!$lock) {
        $db->rollback();
        error_log('Could not lock Razorpay webhook payment: ' . $db->error);
        $respond(500, 'Temporary error');
    }
    $paymentRowId = (int)$localPayment['id'];
    $lock->bind_param('i', $paymentRowId);
    $lock->execute();
    $lockedPayment = $lock->get_result()->fetch_assoc();
    $lock->close();
    if (!$lockedPayment) {
        $db->rollback();
        $respond(500, 'Temporary error');
    }

    // Never overwrite a successful payment with a different payment ID.
    if (($lockedPayment['status'] ?? '') === 'success') {
        if (($lockedPayment['razorpay_payment_id'] ?? '') !== $paymentId) {
            error_log('Razorpay webhook attempted to replace a successful payment for order: ' . $orderId);
        }
        $db->rollback();
        $respond(200, 'OK');
    }

    $update = $db->prepare("UPDATE payment
        SET status = 'success', razorpay_payment_id = ?
        WHERE id = ? AND status <> 'success'");
    if (!$update) {
        $db->rollback();
        error_log('Could not prepare Razorpay captured update: ' . $db->error);
        $respond(500, 'Temporary error');
    }
    $update->bind_param('si', $paymentId, $paymentRowId);
    if (!$update->execute()) {
        $update->close();
        $db->rollback();
        $respond(500, 'Temporary error');
    }
    $update->close();

    $progress = $db->prepare("UPDATE registration_progress
        SET current_step = 4, is_submitted = 1 WHERE user_id = ?");
    if (!$progress) {
        $db->rollback();
        error_log('Could not prepare registration progress update: ' . $db->error);
        $respond(500, 'Temporary error');
    }
    $userId = (int)$localPayment['user_id'];
    $progress->bind_param('i', $userId);
    $progressUpdated = $progress->execute();
    $progress->close();
    if (!$progressUpdated) {
        $db->rollback();
        $respond(500, 'Temporary error');
    }

    if (!$db->commit()) {
        $db->rollback();
        error_log('Could not commit Razorpay captured update.');
        $respond(500, 'Temporary error');
    }
}

if ($event['event'] === 'payment.failed') {
    $db->begin_transaction();
    $lock = $db->prepare("SELECT status FROM payment WHERE id = ? FOR UPDATE");
    if (!$lock) {
        $db->rollback();
        error_log('Could not lock Razorpay failed payment: ' . $db->error);
        $respond(500, 'Temporary error');
    }
    $paymentRowId = (int)$localPayment['id'];
    $lock->bind_param('i', $paymentRowId);
    $lock->execute();
    $lockedPayment = $lock->get_result()->fetch_assoc();
    $lock->close();
    if (!$lockedPayment) {
        $db->rollback();
        $respond(500, 'Temporary error');
    }
    if (($lockedPayment['status'] ?? '') === 'success') {
        $db->rollback();
        $respond(200, 'OK');
    }

    $update = $db->prepare("UPDATE payment SET status = 'failed'
        WHERE id = ? AND status <> 'success'");
    if (!$update) {
        $db->rollback();
        error_log('Could not prepare Razorpay failed update: ' . $db->error);
        $respond(500, 'Temporary error');
    }
    $update->bind_param('i', $paymentRowId);
    if (!$update->execute()) {
        $update->close();
        $db->rollback();
        $respond(500, 'Temporary error');
    }
    $update->close();
    if (!$db->commit()) {
        $db->rollback();
        error_log('Could not commit Razorpay failed update.');
        $respond(500, 'Temporary error');
    }
}

$respond(200, 'OK');
