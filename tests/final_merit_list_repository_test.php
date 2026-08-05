<?php
define('APP_INIT', true);

require_once __DIR__ . '/../helpers/GubedcetMeritListRepository.php';

function assertFinalMeritRepositoryValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$repository = new GubedcetMeritListRepository(__DIR__ . '/fixtures/final_merit_list.csv');

$student = $repository->findByRollNo('2652024050');
assertFinalMeritRepositoryValue('NARJUMONI AHMED', $student['name'] ?? null, 'exact roll lookup returns the student');
assertFinalMeritRepositoryValue('374', $student['total_marks'] ?? null, 'lookup maps total marks');
assertFinalMeritRepositoryValue(null, $repository->findByRollNo('0000000000'), 'unknown roll lookup returns null');

$result = $repository->browse('', '', '', 1, 2);
assertFinalMeritRepositoryValue(5, $result['total'], 'browse reports all matching records');
assertFinalMeritRepositoryValue(2, count($result['rows']), 'browse paginates records');
assertFinalMeritRepositoryValue(3, $result['total_pages'], 'browse calculates total pages');
assertFinalMeritRepositoryValue(5, $result['stats']['total_students'], 'stats include every source record');
assertFinalMeritRepositoryValue(2, $result['stats']['gender']['FEMALE'] ?? null, 'stats aggregate gender');
assertFinalMeritRepositoryValue(3, $result['stats']['gender']['MALE'] ?? null, 'stats include rejected-result gender');
assertFinalMeritRepositoryValue(2, $result['stats']['category']['EWS'] ?? null, 'stats aggregate category');
assertFinalMeritRepositoryValue(330.0, $result['stats']['average_marks'], 'stats exclude blank rejected-result marks');
assertFinalMeritRepositoryValue(374.0, $result['stats']['highest_marks'], 'stats calculate highest marks');

$searchResult = $repository->browse('sarmah', '', '', 1, 10);
assertFinalMeritRepositoryValue(1, $searchResult['total'], 'search matches names case-insensitively');
assertFinalMeritRepositoryValue('2683020779', $searchResult['rows'][0]['roll_no'] ?? null, 'search returns the matching row');

$fieldSearch = $repository->browse('308', 'MALE', 'EWS', 1, 10);
assertFinalMeritRepositoryValue(1, $fieldSearch['total'], 'search and exact filters work together across other fields');
assertFinalMeritRepositoryValue('MRINMOY KOUSHIK', $fieldSearch['rows'][0]['name'] ?? null, 'combined filters return expected student');

assertFinalMeritRepositoryValue(['EWS', 'GENERAL / OPEN CATEGORY / UNRESERVED'], $result['filters']['categories'], 'browse returns sorted category options');
assertFinalMeritRepositoryValue(['FEMALE', 'MALE'], $result['filters']['genders'], 'browse returns sorted gender options');

$sortedResult = $repository->browse('', '', '', 1, 10, 'total_marks', 'desc');
assertFinalMeritRepositoryValue('374', $sortedResult['rows'][0]['total_marks'] ?? null, 'browse sorts numeric fields descending');

$rejectedStudent = $repository->findByRollNo('2560020997');
assertFinalMeritRepositoryValue('REJECTED DUE TO NON COMPLIANCE WITH RULES', $rejectedStudent['booklet_series'] ?? null, 'lookup preserves official rejected-result status');
assertFinalMeritRepositoryValue('', $rejectedStudent['total_marks'] ?? null, 'lookup preserves blank marks for a rejected result');
assertFinalMeritRepositoryValue('', $rejectedStudent['rank'] ?? null, 'lookup preserves blank rank for a rejected result');

echo "final_merit_list_repository_test passed\n";
