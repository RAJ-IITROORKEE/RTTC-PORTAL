<?php

declare(strict_types=1);

function assertAdminQueryModal(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$page = file_get_contents(__DIR__ . '/../admin/queries/index.php');
$actionApi = file_get_contents(__DIR__ . '/../api/admin-query-action.php');
$replyApi = file_get_contents(__DIR__ . '/../api/admin-query-reply.php');

assertAdminQueryModal($page !== false, 'admin query page is readable');
assertAdminQueryModal(strpos($page, 'data-qmessage=') !== false, 'query action retains the full student message');
assertAdminQueryModal(strpos($page, 'data-qphone=') !== false, 'query action retains the student phone number');
assertAdminQueryModal(strpos($page, 'id="viewQueryModal"') !== false, 'query view modal is rendered');
assertAdminQueryModal(strpos($page, 'openViewModal') !== false, 'query view action opens its modal');
assertAdminQueryModal(strpos($page, 'id="resolveQueryModal"') !== false, 'query resolve modal is rendered');
assertAdminQueryModal(strpos($page, "confirm('Mark this query as resolved?')") === false, 'resolving a query does not use a context-free browser prompt');
assertAdminQueryModal(strpos($page, 'renderQueryDetails') !== false, 'action modals render complete query details');
assertAdminQueryModal(strpos($actionApi, 'SecurityHelper::verifyCsrf') !== false, 'query action API verifies CSRF');
assertAdminQueryModal(strpos($replyApi, 'SecurityHelper::verifyCsrf') !== false, 'query reply API verifies CSRF');

echo "admin_query_modal_test passed\n";
