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
if (!is_file($root . '/assets/img/RTTC_logo_blue.png')) {
    $failures[] = 'Capture-safe blue RTTC logo asset must exist.';
}

if (!$failures) {
    $queue = file_get_contents($root . '/admin/id-cards/index.php');
    $action = file_get_contents($root . '/api/admin-id-card-action.php');
    $photo = file_get_contents($root . '/api/admin-id-card-photo.php');
    $review = file_get_contents($root . '/admin/id-cards/review.php');
    $export = file_get_contents($root . '/assets/js/id-card-export.js');
    $template = file_get_contents($root . '/views/components/id-card/template.php');
    $styles = file_get_contents($root . '/assets/css/id-card.css');
    $cssBlock = static function (string $selector) use ($styles): string {
        $pattern = '/(?:^|\n)\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/m';
        return preg_match($pattern, $styles, $matches) === 1 ? $matches[1] : '';
    };
    $cssHeight = static function (string $selector) use ($cssBlock): ?int {
        $block = $cssBlock($selector);
        return preg_match('/\bheight:\s*(\d+)px\s*;/', $block, $matches) === 1 ? (int) $matches[1] : null;
    };

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
    foreach (['waitForAssets', 'image.complete', 'naturalWidth > 0', "postAction(root, 'mark_done')", "'image/png'", "'.png'", "querySelector('#id-card-sheet')", 'CAPTURE_SCALE = 2'] as $needle) {
        if (!str_contains($export, $needle)) $failures[] = 'Export script missing asset/download guard: ' . $needle;
    }
    foreach (['id-card-sheet', 'id-card-information', 'id-card-divider', 'id-card-instructions', 'data-id-card-issue', 'data-id-card-valid-until', 'RTTC_logo_blue.png'] as $needle) {
        if (!str_contains($template, $needle)) $failures[] = 'Single-sheet template missing ' . $needle;
    }
    $frontPosition = strpos($template, '<section class="id-card-information"');
    $letterheadPosition = strpos($template, '<header class="id-card-letterhead">');
    $frontContentPosition = strpos($template, '<div class="id-card-information-content">');
    $instructionsPosition = strpos($template, '<section class="id-card-instructions"');
    if ($frontPosition === false || $letterheadPosition === false || $frontContentPosition === false || $instructionsPosition === false
        || !($frontPosition < $letterheadPosition && $letterheadPosition < $frontContentPosition && $frontContentPosition < $instructionsPosition)) {
        $failures[] = 'College letterhead must be confined to the left front panel before its holder details.';
    }
    if (str_contains($template, 'id-card-sheet-body')) $failures[] = 'Card must not retain a shared body below a full-width letterhead.';
    $styleContracts = [
        '#id-card-export-root' => ['--id-card-ink: #20205f'],
        '.id-card-sheet' => ['display: grid', 'grid-template-columns: 1fr 2px 1fr', 'width: 1600px', 'height: 1067px', 'font-family: "Nirmala UI", Arial, Helvetica, sans-serif'],
        '.id-card-letterhead' => ['justify-content: flex-start', 'height: 174px'],
        '.id-card-letterhead-logo-wrap' => ['width: 96px', 'height: 96px'],
        '.id-card-information-content' => ['height: 881px'],
        '.id-card-instruction-watermark' => ['opacity: .11'],
    ];
    foreach ($styleContracts as $selector => $needles) {
        $block = $cssBlock($selector);
        if ($block === '') $failures[] = 'Card styling missing selector ' . $selector;
        foreach ($needles as $needle) {
            if (!str_contains($block, $needle)) $failures[] = $selector . ' missing ' . $needle;
        }
    }
    $fixedHeight = $cssHeight('.id-card-letterhead') + $cssHeight('.id-card-accent-line') + $cssHeight('.id-card-information-content');
    if ($fixedHeight !== 1067) $failures[] = 'Front letterhead, accent, and holder details must total the 1067px capture canvas.';
    foreach (['Georgia', 'Times New Roman'] as $needle) {
        if (str_contains($styles, $needle)) $failures[] = 'Card styling retains mixed font family: ' . $needle;
    }
    if (str_contains($styles, 'mix-blend-mode')) $failures[] = 'Preview uses a blend mode unsupported by PNG capture.';
    foreach (['jsPDF', 'JSZip', 'makePdf', 'makeZip', '#id-card-front', '#id-card-back', '.zip', '.pdf'] as $needle) {
        if (str_contains($export, $needle)) $failures[] = 'PNG-only export retains obsolete output behavior: ' . $needle;
    }
    foreach (['jspdf', 'jszip'] as $needle) {
        if (stripos($review, $needle) !== false) $failures[] = 'Review page loads obsolete export library: ' . $needle;
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "ID card admin contracts passed." . PHP_EOL;
