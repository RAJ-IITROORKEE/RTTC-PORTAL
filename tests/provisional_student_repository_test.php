<?php
define('APP_INIT', true);

require_once __DIR__ . '/../helpers/ProvisionalStudentRepository.php';

function assertRepositoryValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$repository = new ProvisionalStudentRepository(__DIR__ . '/fixtures/provisional_students.csv');

$student = $repository->findByRollNo('2652024050');
assertRepositoryValue('NARJUMONI AHMED', $student['name'] ?? null, 'exact roll lookup returns the student');
assertRepositoryValue('374', $student['total_marks'] ?? null, 'lookup maps total marks');
assertRepositoryValue(null, $repository->findByRollNo('0000000000'), 'unknown roll lookup returns null');

$result = $repository->browse('', '', '', 1, 2);
assertRepositoryValue(4, $result['total'], 'browse reports all matching records');
assertRepositoryValue(2, count($result['rows']), 'browse paginates records');
assertRepositoryValue(2, $result['total_pages'], 'browse calculates total pages');
assertRepositoryValue(4, $result['stats']['total_students'], 'stats include every source record');
assertRepositoryValue(2, $result['stats']['gender']['FEMALE'] ?? null, 'stats aggregate gender');
assertRepositoryValue(2, $result['stats']['category']['EWS'] ?? null, 'stats aggregate category');
assertRepositoryValue(330.0, $result['stats']['average_marks'], 'stats calculate average marks');
assertRepositoryValue(374.0, $result['stats']['highest_marks'], 'stats calculate highest marks');

$searchResult = $repository->browse('sarmah', '', '', 1, 10);
assertRepositoryValue(1, $searchResult['total'], 'search matches names case-insensitively');
assertRepositoryValue('2683020779', $searchResult['rows'][0]['roll_no'] ?? null, 'search returns the matching row');

$fieldSearch = $repository->browse('308', 'MALE', 'EWS', 1, 10);
assertRepositoryValue(1, $fieldSearch['total'], 'search and exact filters work together across other fields');
assertRepositoryValue('MRINMOY KOUSHIK', $fieldSearch['rows'][0]['name'] ?? null, 'combined filters return expected student');

assertRepositoryValue(['EWS', 'GENERAL / OPEN CATEGORY / UNRESERVED'], $result['filters']['categories'], 'browse returns sorted category options');
assertRepositoryValue(['FEMALE', 'MALE'], $result['filters']['genders'], 'browse returns sorted gender options');

echo "provisional_student_repository_test passed\n";
