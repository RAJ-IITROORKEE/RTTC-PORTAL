<?php

declare(strict_types=1);

function assertQueryFeature(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$adminPage = file_get_contents(__DIR__ . '/../admin/queries/index.php');
$adminAction = file_get_contents(__DIR__ . '/../api/admin-query-action.php');
$submitApi = file_get_contents(__DIR__ . '/../api/submit-query.php');
$queryPage = file_get_contents(__DIR__ . '/../request-query.php');
$homePage = file_get_contents(__DIR__ . '/../index.php');

assertQueryFeature($adminPage !== false, 'admin query page is readable');
assertQueryFeature(strpos($adminPage, 'Revoke Edit Access') !== false, 'admin menu offers revoke edit access');
assertQueryFeature(strpos($adminAction, "revoke_access") !== false, 'admin action accepts revoke access');
assertQueryFeature(strpos($adminAction, 'UPDATE user_edit_access SET is_active = 0') !== false, 'revoke action deactivates user access');
assertQueryFeature(strpos($adminAction, 'sendEditAccessRevokedEmail') !== false, 'revoke action sends a notification email');
assertQueryFeature(strpos($submitApi, "status = 'success'") !== false, 'query submission checks successful payment');
assertQueryFeature(strpos($submitApi, 'cannot raise a query') !== false, 'paid query submission is rejected');
assertQueryFeature(strpos($queryPage, '$hasSuccessfulPayment') !== false, 'query page checks successful payment');
assertQueryFeature(strpos($queryPage, 'cannot raise a query') !== false, 'paid users do not receive the query form');
assertQueryFeature(strpos($homePage, 'Document Submission Notice') !== false, 'home page renders document submission notice');
assertQueryFeature(strpos($queryPage, 'Any discrepancy in the data') !== false, 'query page renders post-approval warning');

echo "query_access_and_notice_test passed\n";
