<?php

declare(strict_types=1);

function assertConfirmationGubedcetFallback(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$confirmation = file_get_contents(__DIR__ . '/../payment/confirmation.php');
$applicationForm = file_get_contents(__DIR__ . '/../payment/components/application-form.php');
$migration = file_get_contents(__DIR__ . '/../database/fix_gubedcet_confirmation_fields.sql');

assertConfirmationGubedcetFallback($confirmation !== false, 'confirmation page is readable');
assertConfirmationGubedcetFallback($applicationForm !== false, 'application form component is readable');
assertConfirmationGubedcetFallback($migration !== false, 'GUBEDCET schema repair migration is available');
assertConfirmationGubedcetFallback(strpos($migration, '`gubedcet_gender`') !== false, 'migration adds the GUBEDCET gender column');
assertConfirmationGubedcetFallback(strpos($migration, '`gubedcet_booklet_series`') !== false, 'migration adds the GUBEDCET booklet-series column');
assertConfirmationGubedcetFallback(strpos($confirmation, 'GubedcetMeritListRepository') !== false, 'confirmation resolves GUBEDCET data from the final merit list');
assertConfirmationGubedcetFallback(strpos($confirmation, 'gubedcet_gender') !== false, 'confirmation populates GUBEDCET gender');
assertConfirmationGubedcetFallback(strpos($confirmation, 'gubedcet_booklet_series') !== false, 'confirmation populates GUBEDCET booklet series');
assertConfirmationGubedcetFallback(strpos($applicationForm, "\$data['gubedcet_gender']") !== false, 'application form displays GUBEDCET gender');
assertConfirmationGubedcetFallback(strpos($applicationForm, "\$data['gubedcet_booklet_series']") !== false, 'application form displays GUBEDCET booklet series');

echo "confirmation_gubedcet_fallback_test passed\n";
