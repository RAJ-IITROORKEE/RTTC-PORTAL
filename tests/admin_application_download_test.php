<?php
define('APP_INIT', true);

require_once __DIR__ . '/../helpers/ApplicationHelper.php';

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

assertSameValue(true, canDownloadApplicationForm([
    'current_step' => 4,
    'is_submitted' => 1,
    'razorpay_payment_id' => 'pay_123',
]), 'payment-done submitted students can download the application');

assertSameValue(false, canDownloadApplicationForm([
    'current_step' => 3,
    'is_submitted' => 1,
    'razorpay_payment_id' => 'pay_123',
]), 'students below payment step cannot download the application');

assertSameValue(false, canDownloadApplicationForm([
    'current_step' => 4,
    'is_submitted' => 0,
    'razorpay_payment_id' => 'pay_123',
]), 'unsubmitted students cannot download the application');

assertSameValue(false, canDownloadApplicationForm([
    'current_step' => 4,
    'is_submitted' => 1,
    'razorpay_payment_id' => '',
]), 'students without successful payment cannot download the application');

echo "admin_application_download_test passed\n";
