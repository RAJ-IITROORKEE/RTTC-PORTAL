<?php

declare(strict_types=1);

const FINAL_LIST_HEADERS = [
    'Sl. No.',
    'RollNo',
    'Name',
    'Gender',
    'Category',
    'QBookletSeries',
    'Correct Marks',
    'Wrong Marks',
    'Total Marks',
    'Rank',
];

const XLSX_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
const REJECTED_RESULT_STATUS = 'REJECTED DUE TO NON COMPLIANCE WITH RULES';

function failFinalListBuild(string $message): void
{
    throw new RuntimeException($message);
}

function readSharedStrings(ZipArchive $archive, string $sourceFile): array
{
    $xml = $archive->getFromName('xl/sharedStrings.xml');
    if ($xml === false) {
        return [];
    }

    $document = new DOMDocument();
    if (!$document->loadXML($xml, LIBXML_NONET)) {
        failFinalListBuild("Could not parse shared strings in {$sourceFile}");
    }

    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('xlsx', XLSX_NAMESPACE);
    $sharedStrings = [];
    foreach ($xpath->query('//xlsx:si') as $item) {
        $sharedStrings[] = $item->textContent;
    }

    return $sharedStrings;
}

function readCellValue(DOMXPath $xpath, DOMElement $cell, array $sharedStrings, string $sourceFile): string
{
    $type = $cell->getAttribute('t');
    if ($type === 'inlineStr') {
        return $cell->textContent;
    }

    $value = $xpath->query('./xlsx:v', $cell)->item(0);
    $value = $value === null ? '' : $value->textContent;
    if ($type !== 's') {
        return $value;
    }

    if (!ctype_digit($value) || !array_key_exists((int) $value, $sharedStrings)) {
        failFinalListBuild("Invalid shared-string reference in {$sourceFile}");
    }

    return $sharedStrings[(int) $value];
}

function columnNumber(string $cellReference, string $sourceFile): int
{
    if (!preg_match('/^([A-Z]+)[1-9][0-9]*$/', $cellReference, $matches)) {
        failFinalListBuild("Invalid cell reference {$cellReference} in {$sourceFile}");
    }

    $number = 0;
    foreach (str_split($matches[1]) as $letter) {
        $number = ($number * 26) + (ord($letter) - ord('A') + 1);
    }

    return $number - 1;
}

function readWorkbookRows(string $sourceFile): array
{
    $archive = new ZipArchive();
    if ($archive->open($sourceFile) !== true) {
        failFinalListBuild("Could not open workbook {$sourceFile}");
    }

    try {
        $worksheetNames = [];
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $name = $archive->getNameIndex($index);
            if (preg_match('#^xl/worksheets/sheet[0-9]+\.xml$#', $name)) {
                $worksheetNames[] = $name;
            }
        }
        if (count($worksheetNames) !== 1) {
            failFinalListBuild("Workbook must contain exactly one worksheet: {$sourceFile}");
        }

        $sheetXml = $archive->getFromName($worksheetNames[0]);
        if ($sheetXml === false) {
            failFinalListBuild("Could not read worksheet in {$sourceFile}");
        }
        $sharedStrings = readSharedStrings($archive, $sourceFile);
    } finally {
        $archive->close();
    }

    $document = new DOMDocument();
    if (!$document->loadXML($sheetXml, LIBXML_NONET)) {
        failFinalListBuild("Could not parse worksheet in {$sourceFile}");
    }

    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('xlsx', XLSX_NAMESPACE);
    $rows = [];
    foreach ($xpath->query('//xlsx:sheetData/xlsx:row') as $sheetRow) {
        $row = array_fill(0, count(FINAL_LIST_HEADERS), '');
        foreach ($xpath->query('./xlsx:c', $sheetRow) as $cell) {
            $column = columnNumber($cell->getAttribute('r'), $sourceFile);
            if ($column < count($row)) {
                $row[$column] = trim(readCellValue($xpath, $cell, $sharedStrings, $sourceFile));
            }
        }
        $rows[] = $row;
    }

    if ($rows === [] || $rows[0] !== FINAL_LIST_HEADERS) {
        failFinalListBuild("Workbook headers do not match the GUBEDCET source contract: {$sourceFile}");
    }

    return array_slice($rows, 1);
}

function validateFinalListRow(array $row, int $expectedSerial, array &$rollNumbers, string $sourceFile): void
{
    if (count($row) !== count(FINAL_LIST_HEADERS) || in_array('', array_slice($row, 0, 6), true)) {
        failFinalListBuild("Row {$expectedSerial} has missing identity values in {$sourceFile}");
    }
    if ($row[0] !== (string) $expectedSerial) {
        failFinalListBuild("Expected serial {$expectedSerial}, found {$row[0]} in {$sourceFile}");
    }
    if (!preg_match('/^\d{10}$/', $row[1])) {
        failFinalListBuild("Invalid roll number {$row[1]} at serial {$expectedSerial}");
    }
    if (isset($rollNumbers[$row[1]])) {
        failFinalListBuild("Duplicate roll number {$row[1]} at serial {$expectedSerial}");
    }
    if ($row[5] === REJECTED_RESULT_STATUS) {
        if (array_slice($row, 6) !== ['', '', '', '']) {
            failFinalListBuild("Rejected result at serial {$expectedSerial} has incomplete result fields");
        }
        $rollNumbers[$row[1]] = true;
        return;
    }
    foreach ([6, 7, 8, 9] as $numericColumn) {
        if (!is_numeric($row[$numericColumn])) {
            failFinalListBuild("Non-numeric {$row[$numericColumn]} in " . FINAL_LIST_HEADERS[$numericColumn] . " at serial {$expectedSerial}");
        }
    }

    $rollNumbers[$row[1]] = true;
}

function replaceOutputFile(string $temporaryPath, string $outputPath): void
{
    if (!rename($temporaryPath, $outputPath)) {
        failFinalListBuild("Could not write {$outputPath}");
    }
}

function buildFinalMeritList(string $outputPath): array
{
    $sourceFiles = glob(__DIR__ . '/../final_list/gubedcet-2026-final-merit-list-1_*.xlsx') ?: [];
    natsort($sourceFiles);
    $sourceFiles = array_values($sourceFiles);
    if (count($sourceFiles) !== 2) {
        failFinalListBuild('Expected exactly two final-merit workbooks in final_list');
    }

    $outputDirectory = dirname($outputPath);
    if (!is_dir($outputDirectory) || !is_writable($outputDirectory)) {
        failFinalListBuild("Output directory is not writable: {$outputDirectory}");
    }

    $temporaryPath = tempnam($outputDirectory, 'rttc-final-merit-');
    if ($temporaryPath === false) {
        failFinalListBuild("Could not create a temporary file in {$outputDirectory}");
    }

    try {
        $output = fopen($temporaryPath, 'wb');
        if ($output === false) {
            failFinalListBuild("Could not write temporary file {$temporaryPath}");
        }

        try {
            fputcsv($output, FINAL_LIST_HEADERS);
            $serial = 1;
            $rollNumbers = [];
            foreach ($sourceFiles as $sourceFile) {
                foreach (readWorkbookRows($sourceFile) as $row) {
                    validateFinalListRow($row, $serial, $rollNumbers, basename($sourceFile));
                    fputcsv($output, $row);
                    $serial++;
                }
            }
        } finally {
            fclose($output);
        }

        replaceOutputFile($temporaryPath, $outputPath);
        return ['records' => $serial - 1, 'sources' => $sourceFiles];
    } finally {
        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
}

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return;
}

$outputPath = $argv[1] ?? __DIR__ . '/../final_list/GUBEDCET 2026 FINAL LIST.csv';

try {
    $result = buildFinalMeritList($outputPath);
    printf("Built %d final-merit records from %d workbooks: %s\n", $result['records'], count($result['sources']), $outputPath);
} catch (Throwable $exception) {
    fwrite(STDERR, "Final-merit list build failed: {$exception->getMessage()}\n");
    exit(1);
}
