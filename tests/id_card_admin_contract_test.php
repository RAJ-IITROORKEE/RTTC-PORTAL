<?php
/** ID card protected workflow source contracts. */

$root = dirname(__DIR__);
$files = [
    'admin/id-cards/index.php',
    'admin/id-cards/review.php',
    'api/admin-id-card-action.php',
    'api/admin-id-card-photo.php',
    'views/components/id-card/template.php',
];

$failures = [];
foreach ($files as $file) {
    if (!is_file($root . DIRECTORY_SEPARATOR . $file)) {
        $failures[] = $file . ' must exist.';
    }
}

if (!$failures) {
    $queue = file_get_contents($root . '/admin/id-cards/index.php');
    $action = file_get_contents($root . '/api/admin-id-card-action.php');
    $photo = file_get_contents($root . '/api/admin-id-card-photo.php');
    $review = file_get_contents($root . '/admin/id-cards/review.php');
    $export = file_get_contents($root . '/assets/js/id-card-export.js');

    foreach ([
        'id_card_applications', 'LIMIT ? OFFSET ?', "route('id-card.student')", "route('id-card.faculty-staff')",
    ] as $needle) {
        if (!str_contains($queue, $needle)) $failures[] = 'Queue missing ' . $needle;
    }
    foreach ([
        "SessionHelper::isAdminLoggedIn()", 'validateCsrfToken', 'FOR UPDATE',
        "'approve'", "'mark_done'", "'delete'", 'canManageRole', "status IN ('approved', 'done')",
    ] as $needle) {
        if (!str_contains($action, $needle)) $failures[] = 'Action API missing ' . $needle;
    }
    foreach (["status !== IdCardHelper::STATUS_PENDING", 'deleteStoredPhoto', 'id_card_action_log'] as $needle) {
        if (!str_contains($action, $needle)) $failures[] = 'Action API must enforce pending-only deletion: ' . $needle;
    }
    foreach (["SessionHelper::isAdminLoggedIn()", 'canManageRole', 'resolvePhotoPath', 'X-Content-Type-Options'] as $needle) {
        if (!str_contains($photo, $needle)) $failures[] = 'Private photo API missing ' . $needle;
    }
    foreach (['id-card-export-root', 'data-holder-name', "views/components/id-card/template.php", 'id-card-export.js'] as $needle) {
        if (!str_contains($review, $needle)) $failures[] = 'Review page missing export integration: ' . $needle;
    }
    foreach (['waitForAssets', 'image.complete', 'naturalWidth > 0', "postAction(root, 'mark_done')"] as $needle) {
        if (!str_contains($export, $needle)) $failures[] = 'Export script missing asset/download guard: ' . $needle;
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "ID card admin contracts passed." . PHP_EOL;
