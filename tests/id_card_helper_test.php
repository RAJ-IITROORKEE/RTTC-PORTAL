<?php
define('APP_INIT', true);

require_once __DIR__ . '/../helpers/IdCardHelper.php';

function assertIdCardSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function assertIdCardTrue(bool $actual, string $message): void
{
    assertIdCardSame(true, $actual, $message);
}

assertIdCardSame('IDC-S-000042', IdCardHelper::formatReference(IdCardHelper::TYPE_STUDENT, 42), 'student references use the approved prefix and padding');
assertIdCardSame('IDC-F-000042', IdCardHelper::formatReference(IdCardHelper::TYPE_FACULTY_STAFF, 42), 'faculty references use the approved prefix and padding');

$student = IdCardHelper::validateApplication(IdCardHelper::TYPE_STUDENT, [
    'full_name' => '  Ananya   Das  ',
    'care_of' => 'C/O  Prabin Das',
    'course' => 'B.Ed.',
    'academic_session' => '2026-27',
    'roll_number' => 'R-101',
    'date_of_birth' => '2002-04-12',
    'blood_group' => 'O+',
    'contact_number' => '+91 98765 43210',
    'address' => "Ward 5,\nRangia, Assam",
    'declaration' => '1',
    'department' => 'Injected value',
]);
assertIdCardSame([], $student['errors'], 'a complete student application passes validation');
assertIdCardSame('Ananya Das', $student['data']['full_name'], 'names are normalized before storage');
assertIdCardSame('9876543210', $student['data']['contact_number'], 'Indian contact numbers are normalized');
assertIdCardSame(null, $student['data']['department'], 'student submissions discard faculty-only fields');

$faculty = IdCardHelper::validateApplication(IdCardHelper::TYPE_FACULTY_STAFF, [
    'full_name' => 'Rina Bora',
    'care_of' => 'C/O RTTC',
    'department' => 'Education',
    'designation' => 'Assistant Professor',
    'blood_group' => 'AB-',
    'contact_number' => '9123456789',
    'address' => 'Mahendra Das Path, Rangia',
    'declaration' => '1',
    'course' => 'Injected course',
]);
assertIdCardSame([], $faculty['errors'], 'a complete faculty/staff application passes validation');
assertIdCardSame(null, $faculty['data']['course'], 'faculty/staff submissions discard student-only fields');

$invalid = IdCardHelper::validateApplication(IdCardHelper::TYPE_STUDENT, [
    'full_name' => 'A',
    'care_of' => '',
    'course' => 'B.Ed.',
    'academic_session' => '2026/27',
    'roll_number' => '',
    'date_of_birth' => date('Y-m-d'),
    'blood_group' => 'Unknown',
    'contact_number' => '12345',
    'address' => 'x',
]);
assertIdCardTrue(isset($invalid['errors']['full_name']), 'too-short full names are rejected');
assertIdCardTrue(isset($invalid['errors']['academic_session']), 'invalid sessions are rejected');
assertIdCardTrue(isset($invalid['errors']['date_of_birth']), 'today is not a valid date of birth');
assertIdCardTrue(isset($invalid['errors']['declaration']), 'the declaration is mandatory');

assertIdCardTrue(IdCardHelper::canTransition('pending', 'approved'), 'pending applications can be approved');
assertIdCardTrue(IdCardHelper::canTransition('approved', 'done'), 'approved applications can be marked done');
assertIdCardTrue(!IdCardHelper::canTransition('done', 'approved'), 'done applications cannot be re-approved');
assertIdCardTrue(IdCardHelper::canManageRole('admin'), 'admin role can manage card applications');
assertIdCardTrue(!IdCardHelper::canManageRole('viewer'), 'viewer role cannot manage card applications');

$dates = IdCardHelper::approvalDates('2026-08-21 14:30:00');
assertIdCardSame('21 Aug 2026', $dates['issue_display'], 'issue date is formatted from approval time');
assertIdCardSame('21 Aug 2027', $dates['valid_until_display'], 'validity is exactly one year after approval');

echo "id_card_helper_test passed\n";
