<?php

function assertPaymentContract(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

define('APP_INIT', true);
require_once __DIR__ . '/../config/config.php';

assertPaymentContract(defined('RAZORPAY_WEBHOOK_SECRET'), 'webhook secret is configured');

$paymentPage = file_get_contents(__DIR__ . '/../payment/index.php');
$processApi = file_get_contents(__DIR__ . '/../api/payment-process.php');
$webhookApi = file_get_contents(__DIR__ . '/../api/payment-webhook.php');
$legacyWebhook = file_get_contents(__DIR__ . '/../webhook/payment.php');
$deleteUserApi = file_get_contents(__DIR__ . '/../api/admin-delete-user.php');
$deletePaymentApi = file_get_contents(__DIR__ . '/../api/admin-delete-payment.php');
$adminPaymentsPage = file_get_contents(__DIR__ . '/../admin/payments/index.php');
$routes = file_get_contents(__DIR__ . '/../helpers/RouteHelper.php');
$htaccess = file_get_contents(__DIR__ . '/../.htaccess');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$auditMigration = file_get_contents(__DIR__ . '/../database/add_payment_deletion_log.sql');

assertPaymentContract(strpos($paymentPage, 'RAZORPAY_AMOUNT') !== false, 'checkout uses configured amount');
assertPaymentContract(strpos($paymentPage, "status = 'pending'") !== false && strpos($paymentPage, '30 MINUTE') !== false, 'checkout reuses recent pending orders');
assertPaymentContract(strpos($paymentPage, 'PaymentHelper::fetchOrder') !== false, 'checkout validates reused orders with current credentials');
assertPaymentContract(strpos($paymentPage, 'displayAmount') !== false, 'payment display uses configured amount');
assertPaymentContract(strpos($paymentPage, 'success@razorpay') !== false, 'test checkout explains how to create a successful test payment');
assertPaymentContract(strpos($paymentPage, "rzp.on('payment.failed'") !== false, 'checkout reports failed payment attempts');
assertPaymentContract(strpos($paymentPage, "CURLOPT_SSL_VERIFYPEER => false") === false, 'checkout keeps TLS verification enabled');
assertPaymentContract(strpos($paymentPage, "INSERT INTO payment") !== false, 'checkout persists a pending order');
assertPaymentContract(strpos($processApi, 'WHERE user_id = ? AND razorpay_order_id = ?') !== false, 'callback binds order to current user');
assertPaymentContract(strpos($processApi, 'PaymentHelper::verifyPaymentSignature') !== false, 'callback verifies checkout signature');
assertPaymentContract(strpos($processApi, 'PaymentHelper::fetchPayment') !== false, 'callback verifies captured gateway status');
assertPaymentContract(strpos($processApi, 'PaymentHelper::matchesExpectedAmount') !== false, 'callback supports verified customer-borne gateway fees');
assertPaymentContract(strpos($processApi, 'FROM users WHERE id = ? FOR UPDATE') !== false, 'callback serializes payment finalization per applicant');
assertPaymentContract(strpos($processApi, 'Extra captured payment recorded') !== false, 'callback records duplicate captured payments for review');
assertPaymentContract(strpos($processApi, 'FOR UPDATE') !== false, 'callback locks payment row during finalization');
assertPaymentContract(strpos($processApi, '$db->commit()') !== false, 'callback checks transaction commit');
assertPaymentContract(strpos($webhookApi, "file_get_contents('php://input')") !== false, 'webhook reads raw request body');
assertPaymentContract(strpos($webhookApi, 'RAZORPAY_WEBHOOK_SECRET') !== false, 'webhook uses dedicated webhook secret');
assertPaymentContract(strpos($webhookApi, 'PaymentHelper::matchesExpectedAmount') !== false, 'webhook supports verified customer-borne gateway fees');
assertPaymentContract(strpos($webhookApi, "payment.captured") !== false, 'webhook handles captured payments');
assertPaymentContract(strpos($webhookApi, "payment.failed") !== false, 'webhook handles failed payments');
assertPaymentContract(strpos($webhookApi, 'FOR UPDATE') !== false, 'webhook locks payment row for idempotency');
assertPaymentContract(strpos($webhookApi, "user_id = ? AND status = 'success'") !== false, 'webhook prevents multiple successful payments for one applicant');
assertPaymentContract(strpos($webhookApi, 'FROM users WHERE id = ? FOR UPDATE') !== false, 'webhook serializes payment finalization per applicant');
assertPaymentContract(strpos($webhookApi, '$db->commit()') !== false, 'webhook checks transaction commit');
assertPaymentContract(strpos($legacyWebhook, "../api/payment-webhook.php") !== false, 'configured dashboard URL reaches webhook handler');
assertPaymentContract(strpos($deleteUserApi, 'FROM users WHERE id = ? FOR UPDATE') !== false, 'user deletion follows the applicant-first payment lock order');
assertPaymentContract(strpos($deletePaymentApi, 'SecurityHelper::requireAdminAuth') !== false, 'payment deletion requires admin authentication');
assertPaymentContract(strpos($deletePaymentApi, 'SecurityHelper::verifyCsrf') !== false, 'payment deletion verifies CSRF');
assertPaymentContract(strpos($deletePaymentApi, "str_starts_with(RAZORPAY_KEY_ID, 'rzp_test_')") !== false, 'payment deletion is restricted to Razorpay Test Mode');
assertPaymentContract(strpos($deletePaymentApi, 'payment_deletion_log') !== false, 'payment deletion records an audit snapshot');
assertPaymentContract(strpos($deletePaymentApi, "UPDATE registration_progress") !== false, 'payment deletion resets registration progress when appropriate');
assertPaymentContract(strpos($adminPaymentsPage, 'admin.delete-payment') !== false, 'payments page exposes the delete action route');
assertPaymentContract(strpos($adminPaymentsPage, 'delete-payment-btn') !== false, 'payments page renders a delete button');
assertPaymentContract(strpos($adminPaymentsPage, '$isTestMode') !== false, 'payments page limits delete button to Test Mode');
assertPaymentContract(strpos($routes, "'api.admin.delete-payment'") !== false, 'payment deletion route is named');
assertPaymentContract(strpos($htaccess, 'api/admin-delete-payment') !== false, 'payment deletion route is rewritten');
assertPaymentContract(strpos($schema, 'payment_deletion_log') !== false, 'database schema includes payment deletion audit table');
assertPaymentContract(strpos($auditMigration, 'CREATE TABLE IF NOT EXISTS `payment_deletion_log`') !== false, 'payment deletion audit migration is available');

echo "payment_integration_contract_test passed\n";
