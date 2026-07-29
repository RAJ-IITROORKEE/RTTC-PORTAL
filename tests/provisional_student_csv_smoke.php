<?php
define('APP_INIT', true);
require_once __DIR__ . '/../helpers/ProvisionalStudentRepository.php';

$result = (new ProvisionalStudentRepository(__DIR__ . '/../PROVISIONAL LIST.csv'))->browse();

if ($result['stats']['total_students'] < 1 || $result['total'] < 1) {
    fwrite(STDERR, "CSV smoke test failed\n");
    exit(1);
}

echo 'provisional_student_csv_smoke passed: ' . $result['stats']['total_students'] . " records\n";
