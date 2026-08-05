<?php

declare(strict_types=1);

function assertFinalMeritList(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rttc-final-merit-list-' . bin2hex(random_bytes(8)) . '.csv';
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../tools/build_final_merit_list.php') . ' ' . escapeshellarg($outputPath) . ' 2>&1';

try {
    exec($command, $commandOutput, $exitCode);
    assertFinalMeritList($exitCode === 0, 'final workbook conversion succeeds: ' . implode("\n", $commandOutput));
    assertFinalMeritList(is_file($outputPath), 'conversion writes the requested CSV');

    $handle = fopen($outputPath, 'rb');
    assertFinalMeritList($handle !== false, 'generated CSV is readable');

    $expectedHeader = ['Sl. No.', 'RollNo', 'Name', 'Gender', 'Category', 'QBookletSeries', 'Correct Marks', 'Wrong Marks', 'Total Marks', 'Rank'];
    assertFinalMeritList(fgetcsv($handle) === $expectedHeader, 'generated CSV preserves the application column contract');

    $count = 0;
    $rejectedCount = 0;
    $expectedSerial = 1;
    $rollNumbers = [];
    while (($row = fgetcsv($handle)) !== false) {
        assertFinalMeritList(count($row) === 10, "row {$expectedSerial} has every required column");
        assertFinalMeritList($row[0] === (string) $expectedSerial, "row {$expectedSerial} has a continuous serial number");
        assertFinalMeritList((bool) preg_match('/^\d{10}$/', $row[1]), "row {$expectedSerial} has a ten-digit roll number");
        assertFinalMeritList(!isset($rollNumbers[$row[1]]), "row {$expectedSerial} does not duplicate a roll number");
        $hasBlankResult = in_array('', array_slice($row, 6), true);
        if ($hasBlankResult) {
            assertFinalMeritList($row[5] === 'REJECTED DUE TO NON COMPLIANCE WITH RULES', "row {$expectedSerial} has a recognised rejected-result status");
            assertFinalMeritList(array_slice($row, 6) === ['', '', '', ''], "row {$expectedSerial} only omits marks and rank for a rejected result");
            $rejectedCount++;
        } else {
            assertFinalMeritList(!in_array('', $row, true), "row {$expectedSerial} has no blank fields");
        }
        $rollNumbers[$row[1]] = true;
        $count++;
        $expectedSerial++;
    }
    fclose($handle);

    assertFinalMeritList($count === 22184, 'generated CSV contains all final-merit records');
    assertFinalMeritList($rejectedCount === 2, 'generated CSV preserves both official rejected-result records');
    assertFinalMeritList(isset($rollNumbers['2652024050']), 'generated CSV contains the top-ranked final-merit record');
    echo "final_merit_list_build_test passed\n";
} finally {
    if (is_file($outputPath)) {
        unlink($outputPath);
    }
}
