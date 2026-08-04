<?php

define('APP_INIT', true);
require_once __DIR__ . '/../helpers/PaymentHelper.php';

function assertPaymentSecurity(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$apiSecret = 'test-api-secret';
$orderId = 'order_test123';
$paymentId = 'pay_test123';
$paymentSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $apiSecret);

assertPaymentSecurity(
    PaymentHelper::verifyPaymentSignature($orderId, $paymentId, $paymentSignature, $apiSecret),
    'valid checkout signature is accepted'
);
assertPaymentSecurity(
    !PaymentHelper::verifyPaymentSignature($orderId, $paymentId, $paymentSignature . 'x', $apiSecret),
    'invalid checkout signature is rejected'
);

$payload = '{"event":"payment.captured","payload":{"payment":{"entity":{"id":"pay_test123"}}}}';
$webhookSecret = 'test-webhook-secret';
$webhookSignature = hash_hmac('sha256', $payload, $webhookSecret);

assertPaymentSecurity(
    PaymentHelper::verifyWebhookSignature($payload, $webhookSignature, $webhookSecret),
    'valid webhook signature is accepted'
);
assertPaymentSecurity(
    !PaymentHelper::verifyWebhookSignature($payload . ' ', $webhookSignature, $webhookSecret),
    'changed webhook body is rejected'
);
assertPaymentSecurity(
    PaymentHelper::decodeWebhookPayload($payload)['event'] === 'payment.captured',
    'valid webhook JSON is decoded'
);
assertPaymentSecurity(
    PaymentHelper::decodeWebhookPayload('{invalid') === null,
    'invalid webhook JSON is rejected'
);
assertPaymentSecurity(
    PaymentHelper::fetchPayment('', 'test-key', 'test-secret') === null,
    'payment fetch rejects missing payment ID'
);
assertPaymentSecurity(
    PaymentHelper::fetchOrder('', 'test-key', 'test-secret') === null,
    'order fetch rejects missing order ID'
);
assertPaymentSecurity(
    PaymentHelper::matchesExpectedAmount(['amount' => 50000, 'fee' => 1180, 'currency' => 'INR'], 50000, 'INR'),
    'merchant-borne fees preserve the exact order amount'
);
assertPaymentSecurity(
    PaymentHelper::matchesExpectedAmount(['amount' => 51180, 'fee' => 1180, 'tax' => 180, 'currency' => 'INR'], 50000, 'INR'),
    'customer-borne fees are excluded when matching the order amount'
);
assertPaymentSecurity(
    !PaymentHelper::matchesExpectedAmount(['amount' => 51180, 'fee' => 1180, 'currency' => 'USD'], 50000, 'INR'),
    'payment currency must match the order currency'
);
assertPaymentSecurity(
    !PaymentHelper::matchesExpectedAmount(['amount' => 51180, 'fee' => 1000, 'currency' => 'INR'], 50000, 'INR'),
    'unexplained payment amount differences are rejected'
);

echo "payment_security_test passed\n";
