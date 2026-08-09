<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';

SecurityHelper::requireAuth();

$fail = static function (string $message): void {
    SessionHelper::setFlash('error', $message);
    redirect(route('payment'));
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

SecurityHelper::verifyCsrf();

$db     = db();
$userId = (int) SessionHelper::get('user_id');

$progressStmt = $db->prepare("SELECT current_step FROM registration_progress WHERE user_id = ? LIMIT 1");
if (!$progressStmt) $fail('Could not verify application progress. Please try again.');
$progressStmt->bind_param('i', $userId);
$progressStmt->execute();
$progress = $progressStmt->get_result()->fetch_assoc();
$progressStmt->close();
if ((int)($progress['current_step'] ?? 0) < 3) {
    $fail('Please complete and submit your documents before making payment.');
}

// Payment gate — admin can stop new payments from Settings
if (!SiteSettingsHelper::isPaymentOpen()) {
    $fail('Online payment is currently stopped. Please contact the institute.');
}

$paymentId = trim((string)($_POST['razorpay_payment_id'] ?? ''));
$orderId   = trim((string)($_POST['razorpay_order_id'] ?? ''));
$signature = trim((string)($_POST['razorpay_signature'] ?? ''));

if (
    !preg_match('/^[A-Za-z0-9_-]{1,100}$/', $paymentId) ||
    !preg_match('/^[A-Za-z0-9_-]{1,100}$/', $orderId) ||
    !preg_match('/^[a-f0-9]{64}$/i', $signature)
) {
    $fail('Invalid payment response. Please contact support.');
}

$orderStmt = $db->prepare("SELECT id, amount, currency, status, razorpay_payment_id
    FROM payment WHERE user_id = ? AND razorpay_order_id = ? LIMIT 1");
if (!$orderStmt) $fail('Could not verify payment order. Please try again.');
$orderStmt->bind_param('is', $userId, $orderId);
$orderStmt->execute();
$payment = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (!$payment) {
    $fail('Payment order was not found. Please start the payment again.');
}

if (($payment['status'] ?? '') === 'success') {
    if (($payment['razorpay_payment_id'] ?? '') === $paymentId) {
        redirect(route('payment.confirmation'));
    }
    $fail('This payment order has already been completed.');
}

if (!PaymentHelper::verifyPaymentSignature($orderId, $paymentId, $signature, RAZORPAY_KEY_SECRET)) {
    $fail('Payment verification failed. If money was deducted, please contact us.');
}

// A valid checkout signature proves authenticity, but fulfillment requires capture.
$gatewayPayment = PaymentHelper::fetchPayment($paymentId, RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
if (
    !$gatewayPayment
    || ($gatewayPayment['id'] ?? '') !== $paymentId
    || ($gatewayPayment['order_id'] ?? '') !== $orderId
    || !PaymentHelper::matchesExpectedAmount($gatewayPayment, (int)$payment['amount'], (string)$payment['currency'])
    || ($gatewayPayment['status'] ?? '') !== 'captured'
) {
    $fail('Payment is not captured yet. If money was deducted, please contact us.');
}

// A payment ID must not be attached to a different local order.
$duplicateStmt = $db->prepare("SELECT id FROM payment
    WHERE razorpay_payment_id = ? AND razorpay_order_id <> ? LIMIT 1");
if (!$duplicateStmt) $fail('Could not verify payment details. Please try again.');
$duplicateStmt->bind_param('ss', $paymentId, $orderId);
$duplicateStmt->execute();
$duplicate = $duplicateStmt->get_result()->fetch_assoc();
$duplicateStmt->close();
if ($duplicate) {
    $fail('Payment verification failed. Please contact support.');
}

$paymentRowId = (int) $payment['id'];
$db->begin_transaction();

$applicantLock = $db->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE");
if (!$applicantLock) {
    $db->rollback();
    $fail('Could not save payment. Please try again.');
}
$applicantLock->bind_param('i', $userId);
$applicantLock->execute();
$lockedApplicant = $applicantLock->get_result()->fetch_assoc();
$applicantLock->close();
if (!$lockedApplicant) {
    $db->rollback();
    $fail('Applicant account was not found. Please contact support.');
}

$lock = $db->prepare("SELECT status, razorpay_payment_id FROM payment
    WHERE id = ? AND user_id = ? FOR UPDATE");
if (!$lock) {
    $db->rollback();
    $fail('Could not save payment. Please try again.');
}
$lock->bind_param('ii', $paymentRowId, $userId);
$lock->execute();
$lockedPayment = $lock->get_result()->fetch_assoc();
$lock->close();
if (!$lockedPayment) {
    $db->rollback();
    $fail('Payment order was not found. Please start the payment again.');
}
if (($lockedPayment['status'] ?? '') === 'success') {
    $db->rollback();
    if (($lockedPayment['razorpay_payment_id'] ?? '') === $paymentId) {
        redirect(route('payment.confirmation'));
    }
    $fail('This payment order has already been completed.');
}
if (($lockedPayment['razorpay_payment_id'] ?? '') !== '' && $lockedPayment['razorpay_payment_id'] !== $paymentId) {
    $db->rollback();
    $fail('Payment verification failed. Please contact support.');
}

$successLock = $db->prepare("SELECT id FROM payment WHERE user_id = ? AND status = 'success' LIMIT 1 FOR UPDATE");
if (!$successLock) {
    $db->rollback();
    $fail('Could not verify existing payment. Please try again.');
}
$successLock->bind_param('i', $userId);
$successLock->execute();
$existingSuccess = $successLock->get_result()->fetch_assoc();
$successLock->close();
if ($existingSuccess) {
    $duplicateUpdate = $db->prepare("UPDATE payment
        SET razorpay_payment_id = ?, razorpay_signature = ?
        WHERE id = ? AND status <> 'success'
        AND (razorpay_payment_id IS NULL OR razorpay_payment_id = ?)");
    if (!$duplicateUpdate) {
        $db->rollback();
        $fail('Could not record the additional payment. Please contact support.');
    }
    $duplicateUpdate->bind_param('ssis', $paymentId, $signature, $paymentRowId, $paymentId);
    $duplicateSaved = $duplicateUpdate->execute();
    $duplicateUpdate->close();
    if (!$duplicateSaved || !$db->commit()) {
        $db->rollback();
        $fail('Could not record the additional payment. Please contact support.');
    }
    $fail('An application payment was already completed. Extra captured payment recorded; please contact support.');
}

$update = $db->prepare("UPDATE payment
    SET razorpay_payment_id = ?, razorpay_signature = ?, status = 'success'
    WHERE id = ? AND status <> 'success'");
if (!$update) {
    $db->rollback();
    $fail('Could not save payment. Please try again.');
}
$update->bind_param('ssi', $paymentId, $signature, $paymentRowId);
$updated = $update->execute();
$update->close();
if (!$updated) {
    $db->rollback();
    $fail('Could not save payment. Please try again.');
}

$progressUpdate = $db->prepare("UPDATE registration_progress
    SET current_step = 4, is_submitted = 1 WHERE user_id = ?");
if (!$progressUpdate) {
    $db->rollback();
    $fail('Could not update application status. Please try again.');
}
$progressUpdate->bind_param('i', $userId);
$progressUpdated = $progressUpdate->execute();
$progressUpdate->close();

if (!$progressUpdated) {
    $db->rollback();
    $fail('Could not update application status. Please try again.');
}

if (!$db->commit()) {
    $db->rollback();
    $fail('Could not finalize payment. Please try again.');
}

SessionHelper::setFlash('success', 'Payment successful! Your application has been submitted.');
redirect(route('payment.confirmation'));
