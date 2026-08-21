<?php
/** Public ID card form validation and completion contracts. */

$root = dirname(__DIR__);
$form = file_get_contents($root . '/id-card/form.php');
$helper = file_get_contents($root . '/helpers/IdCardHelper.php');
$script = file_get_contents($root . '/assets/js/id-card-forms.js');
$failures = [];

foreach (['data-id-card-submit-wrap hidden', '<noscript>', "route('home')", 'Return to Home', 'maximum file size 2 MB'] as $needle) {
    if (!str_contains($form, $needle)) $failures[] = 'Public form missing completion or gated-submit behavior: ' . $needle;
}
if (str_contains($form, 'data-id-card-form data-id-card-max-photo-size="<?= ID_CARD_MAX_PHOTO_SIZE ?>" novalidate')) {
    $failures[] = 'Public form prevents progressive enhancement when JavaScript is unavailable.';
}
foreach (['updateSubmitState', 'validatePhoto', 'validatePhone', 'input', 'change', 'idCardMaxPhotoSize'] as $needle) {
    if (!str_contains($script, $needle)) $failures[] = 'Live validation script missing ' . $needle;
}
foreach (['ID_CARD_MAX_PHOTO_SIZE', 'image_type_to_mime_type', 'ID_CARD_MAX_PHOTO_PIXELS'] as $needle) {
    if (!str_contains($helper, $needle)) $failures[] = 'Photo helper missing secure upload protection: ' . $needle;
}
foreach (['ID_CARD_MIN_PHOTO_WIDTH', 'ID_CARD_MIN_PHOTO_HEIGHT', 'Use a portrait-oriented photo.'] as $needle) {
    if (str_contains($helper, $needle)) $failures[] = 'Photo helper retains unwanted public requirement: ' . $needle;
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "ID card form contracts passed." . PHP_EOL;
