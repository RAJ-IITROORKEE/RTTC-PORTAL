<?php
define('APP_INIT', true);

require_once __DIR__ . '/../helpers/RegistrationHelper.php';

function assertRegistrationValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

assertRegistrationValue(
    ['ews' => 1, 'pwd' => 1],
    RegistrationHelper::normalizeSpecialCategories('General', true, true),
    'General applicants can select EWS and PWD'
);
assertRegistrationValue(
    ['ews' => 0, 'pwd' => 1],
    RegistrationHelper::normalizeSpecialCategories('SC', true, true),
    'non-General applicants cannot save EWS but can save PWD'
);
assertRegistrationValue(
    ['ews' => 0, 'pwd' => 0],
    RegistrationHelper::normalizeSpecialCategories('General', false, false),
    'unselected special categories remain unset'
);

echo "registration_helper_test passed\n";
