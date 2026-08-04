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

assertPaymentContract(strpos($paymentPage, 'RAZORPAY_AMOUNT') !== false, 'checkout uses configured amount');
assertPaymentContract(strpos($paymentPage, "status = 'pending'") !== false && strpos($paymentPage, '30 MINUTE') !== false, 'checkout reuses recent pending orders');
assertPaymentContract(strpos($paymentPage, 'PaymentHelper::fetchOrder') !== false, 'checkout validates reused orders with current credentials');
assertPaymentContract(strpos($paymentPage, 'displayAmount') !== false, 'payment display uses configured amount');
assertPaymentContract(strpos($paymentPage, "CURLOPT_SSL_VERIFYPEER => false") === false, 'checkout keeps TLS verification enabled');
assertPaymentContract(strpos($paymentPage, "INSERT INTO payment") !== false, 'checkout persists a pending order');
assertPaymentContract(strpos($processApi, 'WHERE user_id = ? AND razorpay_order_id = ?') !== false, 'callback binds order to current user');
assertPaymentContract(strpos($processApi, 'PaymentHelper::verifyPaymentSignature') !== false, 'callback verifies checkout signature');
assertPaymentContract(strpos($processApi, 'PaymentHelper::fetchPayment') !== false, 'callback verifies captured gateway status');
assertPaymentContract(strpos($processApi, 'FOR UPDATE') !== false, 'callback locks payment row during finalization');
assertPaymentContract(strpos($processApi, '$db->commit()') !== false, 'callback checks transaction commit');
assertPaymentContract(strpos($webhookApi, "file_get_contents('php://input')") !== false, 'webhook reads raw request body');
assertPaymentContract(strpos($webhookApi, 'RAZORPAY_WEBHOOK_SECRET') !== false, 'webhook uses dedicated webhook secret');
assertPaymentContract(strpos($webhookApi, "payment.captured") !== false, 'webhook handles captured payments');
assertPaymentContract(strpos($webhookApi, "payment.failed") !== false, 'webhook handles failed payments');
assertPaymentContract(strpos($webhookApi, 'FOR UPDATE') !== false, 'webhook locks payment row for idempotency');
assertPaymentContract(strpos($webhookApi, '$db->commit()') !== false, 'webhook checks transaction commit');
assertPaymentContract(strpos($legacyWebhook, "../api/payment-webhook.php") !== false, 'configured dashboard URL reaches webhook handler');

echo "payment_integration_contract_test passed\n";
