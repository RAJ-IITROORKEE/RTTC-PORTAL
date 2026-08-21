<?php

function assertIdCardSchema(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$sqlPath = __DIR__ . '/../database/ID_CARD.SQL';
assertIdCardSchema(is_file($sqlPath), 'ID_CARD.SQL exists');
$sql = (string) file_get_contents($sqlPath);

assertIdCardSchema((bool) preg_match('/CREATE TABLE IF NOT EXISTS\s+`?id_card_applications`?/i', $sql), 'applications table is additive');
assertIdCardSchema((bool) preg_match('/CREATE TABLE IF NOT EXISTS\s+`?id_card_action_log`?/i', $sql), 'action log table is additive');
assertIdCardSchema((bool) preg_match('/CREATE TABLE IF NOT EXISTS\s+`?id_card_submission_attempts`?/i', $sql), 'throttle table is additive');
assertIdCardSchema(stripos($sql, 'submission_token_hash') !== false, 'schema has submission idempotency protection');
assertIdCardSchema(stripos($sql, 'FOREIGN KEY (`application_id`)') !== false, 'action log keeps an application relationship');
assertIdCardSchema(stripos($sql, 'ON DELETE SET NULL') !== false, 'issued audit history survives application deletion');
assertIdCardSchema(!preg_match('/\bDROP\s+(DATABASE|TABLE)\b/i', $sql), 'schema contains no destructive drop statement');
assertIdCardSchema(!preg_match('/\bCREATE\s+DATABASE\b|\bUSE\s+`?/i', $sql), 'schema does not select or create a database');

echo "id_card_schema_test passed\n";
