<?php

declare(strict_types=1);

function assertUnattemptedHidden(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$academics = file_get_contents(__DIR__ . '/../academics.php');
assertUnattemptedHidden($academics !== false, 'academics page is readable');
$gubedcetStart = strpos($academics, '<h5 class="mb-0"><i class="bi bi-file-earmark-text-fill me-2"></i>GUBEDCET 2026 Details</h5>');
$gubedcetEnd = strpos($academics, 'id="academicDeclaration"');
assertUnattemptedHidden($gubedcetStart !== false && $gubedcetEnd !== false, 'academics GUBEDCET section exists');
$gubedcetSection = substr($academics, $gubedcetStart, $gubedcetEnd - $gubedcetStart);
assertUnattemptedHidden(strpos($gubedcetSection, 'gubedcet_unattempted') === false, 'academics form does not render Unattempted');

$confirmation = file_get_contents(__DIR__ . '/../payment/confirmation.php');
assertUnattemptedHidden($confirmation !== false, 'confirmation page is readable');
assertUnattemptedHidden(strpos($confirmation, 'gubedcet_unattempted') === false, 'confirmation page does not load Unattempted');

echo "gubedcet_unattempted_presentation_test passed\n";
