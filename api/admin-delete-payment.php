<?php
/**
 * Delete one successful payment record for controlled test retries.
 * POST /api/admin-delete-payment
 */
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';

SecurityHelper::requireAdminAuth();
header('Content-Type: application/json; charset=UTF-8');

$respond = static function (int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respond(405, ['success' => false, 'message' => 'Method not allowed.']);
}

if (!str_starts_with(RAZORPAY_KEY_ID, 'rzp_test_')) {
    $respond(403, ['success' => false, 'message' => 'Payment deletion is available only in Razorpay Test Mode.']);
}

SecurityHelper::verifyCsrf();

$paymentId = filter_var($_POST['payment_id'] ?? null, FILTER_VALIDATE_INT);
$adminId = (int)SessionHelper::get('admin_id', 0);
if (!$paymentId || $paymentId < 1 || $adminId < 1) {
    $respond(400, ['success' => false, 'message' => 'Invalid payment or administrator.']);
}

$db = db();
$db->begin_transaction();

try {
    // Read the owner first, then lock applicant before payment like payment finalization.
    $ownerStmt = $db->prepare("SELECT user_id FROM payment
        WHERE id = ? AND status = 'success' LIMIT 1");
    if (!$ownerStmt) throw new RuntimeException('Could not prepare payment owner lookup.');
    $ownerStmt->bind_param('i', $paymentId);
    $ownerStmt->execute();
    $owner = $ownerStmt->get_result()->fetch_assoc();
    $ownerStmt->close();
    if (!$owner) {
        $db->rollback();
        $respond(404, ['success' => false, 'message' => 'Successful payment record was not found.']);
    }

    $userLock = $db->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE");
    if (!$userLock) throw new RuntimeException('Could not prepare applicant lock.');
    $userLock->bind_param('i', $owner['user_id']);
    $userLock->execute();
    $lockedUser = $userLock->get_result()->fetch_assoc();
    $userLock->close();
    if (!$lockedUser) {
        $db->rollback();
        $respond(404, ['success' => false, 'message' => 'Applicant account was not found.']);
    }

    $paymentStmt = $db->prepare("SELECT pay.id, pay.user_id, pay.razorpay_order_id,
            pay.razorpay_payment_id, pay.amount, pay.currency, pay.status
        FROM payment pay
        WHERE pay.id = ? AND pay.status = 'success'
        FOR UPDATE");
    if (!$paymentStmt) throw new RuntimeException('Could not prepare payment lookup.');
    $paymentStmt->bind_param('i', $paymentId);
    $paymentStmt->execute();
    $payment = $paymentStmt->get_result()->fetch_assoc();
    $paymentStmt->close();
    if (!$payment) {
        $db->rollback();
        $respond(404, ['success' => false, 'message' => 'Successful payment record was not found.']);
    }

    $auditPaymentId = (int)$payment['id'];
    $auditUserId = (int)$payment['user_id'];
    $auditOrderId = (string)$payment['razorpay_order_id'];
    $auditRazorpayPaymentId = $payment['razorpay_payment_id'] !== null
        ? (string)$payment['razorpay_payment_id'] : null;
    $auditAmount = (int)$payment['amount'];
    $auditCurrency = (string)$payment['currency'];
    $auditStatus = (string)$payment['status'];
    $auditStmt = $db->prepare("INSERT INTO payment_deletion_log
        (payment_id, user_id, razorpay_order_id, razorpay_payment_id, amount, currency, status, deleted_by_admin_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$auditStmt) throw new RuntimeException('Payment deletion audit table is unavailable.');
    $auditStmt->bind_param(
        'iississi',
        $auditPaymentId,
        $auditUserId,
        $auditOrderId,
        $auditRazorpayPaymentId,
        $auditAmount,
        $auditCurrency,
        $auditStatus,
        $adminId
    );
    if (!$auditStmt->execute()) {
        $auditStmt->close();
        throw new RuntimeException('Could not record payment deletion.');
    }
    $auditStmt->close();

    $deleteStmt = $db->prepare('DELETE FROM payment WHERE id = ? AND status = \'success\'');
    if (!$deleteStmt) throw new RuntimeException('Could not prepare payment deletion.');
    $deleteStmt->bind_param('i', $paymentId);
    if (!$deleteStmt->execute() || $deleteStmt->affected_rows !== 1) {
        $deleteStmt->close();
        throw new RuntimeException('Payment record was not deleted.');
    }
    $deleteStmt->close();

    // Allow the applicant to run the payment test again only when no successful payment remains.
    $remainingStmt = $db->prepare("SELECT COUNT(*) AS total FROM payment
        WHERE user_id = ? AND status = 'success'");
    if (!$remainingStmt) throw new RuntimeException('Could not verify remaining payments.');
    $remainingStmt->bind_param('i', $payment['user_id']);
    $remainingStmt->execute();
    $remaining = (int)($remainingStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $remainingStmt->close();

    if ($remaining === 0) {
        $progressStmt = $db->prepare("UPDATE registration_progress
            SET current_step = 3, is_submitted = 0 WHERE user_id = ?");
        if (!$progressStmt) throw new RuntimeException('Could not reset registration progress.');
        $progressStmt->bind_param('i', $payment['user_id']);
        if (!$progressStmt->execute()) {
            $progressStmt->close();
            throw new RuntimeException('Could not reset registration progress.');
        }
        $progressStmt->close();
    }

    if (!$db->commit()) throw new RuntimeException('Could not commit payment deletion.');
    $respond(200, ['success' => true, 'message' => 'Payment record deleted and deletion was recorded.']);
} catch (Throwable $e) {
    $db->rollback();
    error_log('admin-delete-payment error: ' . $e->getMessage());
    $respond(500, ['success' => false, 'message' => 'Could not delete payment record. Apply the audit-table migration and try again.']);
}
