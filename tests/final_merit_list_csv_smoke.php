<?php
define('APP_INIT', true);
require_once __DIR__ . '/../helpers/GubedcetMeritListRepository.php';

$repository = new GubedcetMeritListRepository(__DIR__ . '/../final_list/GUBEDCET 2026 FINAL LIST.csv');
$result = $repository->browse();

if ($result['stats']['total_students'] !== 22184 || $result['total'] !== 22184) {
    fwrite(STDERR, "CSV smoke test failed\n");
    exit(1);
}

$topRanked = $repository->findByRollNo('2652024050');
if (($topRanked['total_marks'] ?? null) !== '374' || ($topRanked['rank'] ?? null) !== '1') {
    fwrite(STDERR, "Top-ranked final-merit record is incorrect\n");
    exit(1);
}

$rejected = $repository->findByRollNo('2560020997');
if (($rejected['booklet_series'] ?? null) !== 'REJECTED DUE TO NON COMPLIANCE WITH RULES' || ($rejected['rank'] ?? null) !== '') {
    fwrite(STDERR, "Rejected final-merit record is incorrect\n");
    exit(1);
}

echo 'final_merit_list_csv_smoke passed: ' . $result['stats']['total_students'] . " records\n";
