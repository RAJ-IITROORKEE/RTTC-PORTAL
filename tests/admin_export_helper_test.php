<?php
define('APP_INIT', true);
require_once __DIR__ . '/../helpers/AdminExportHelper.php';

function assertExportValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$row = adminExportRow([
    'user_id' => 7,
    'username' => 'portal-user',
    'successful_payment_id' => 'pay_success',
    'successful_order_id' => 'order_success',
    'successful_payment_amount' => 50000,
    'successful_payment_date' => '2026-07-29 10:00:00',
    'gubedcet_name' => 'Candidate',
    'foccupation' => 'Teacher',
    'academic_declaration' => 1,
    'is_submitted' => 1,
    'current_step' => 4,
]);

assertExportValue('RTTC-00007', $row['portal_unique_id'], 'export creates the portal ID');
assertExportValue('SUCCESS', $row['payment_status'], 'successful payment is marked SUCCESS');
assertExportValue('500.00', $row['payment_amount'], 'payment amount is converted from paise');
assertExportValue('Teacher', $row['foccupation'], 'family fields are included');
assertExportValue('Yes', $row['academic_declaration'], 'academic declaration is included');
assertExportValue(true, adminExportHasSuccessfulPayment(['successful_payment_id' => 'pay_success']), 'successful payment is exportable');
assertExportValue(false, adminExportHasSuccessfulPayment(['successful_payment_id' => '']), 'missing successful payment is not exportable');
assertExportValue("'=SUM(A1:A2)", adminExportCsvValue('=SUM(A1:A2)'), 'CSV formula values are neutralized');
assertExportValue(false, array_key_exists('photo', $row), 'document fields are not included');
assertExportValue('Portal Unique ID', adminExportColumns()['portal_unique_id'], 'export includes portal ID header');

echo "admin_export_helper_test passed\n";
