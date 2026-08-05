<?php

declare(strict_types=1);

function assertFinalMeritRelease(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$htaccess = file_get_contents(__DIR__ . '/../.htaccess');
assertFinalMeritRelease($htaccess !== false, '.htaccess is readable');
assertFinalMeritRelease(
    (bool) preg_match('/RewriteEngine On\RRewriteRule \^final_list\/ - \[F,L,NC\]/', $htaccess),
    'the final-list source folder is denied before application routes'
);

require_once __DIR__ . '/../tools/build_final_merit_list.php';

$outputPath = tempnam(sys_get_temp_dir(), 'final-merit-output-');
$temporaryPath = tempnam(sys_get_temp_dir(), 'final-merit-replacement-');
assertFinalMeritRelease($outputPath !== false && $temporaryPath !== false, 'temporary files are created');
file_put_contents($outputPath, "old source\n");
file_put_contents($temporaryPath, "new source\n");
replaceOutputFile($temporaryPath, $outputPath);
assertFinalMeritRelease(file_get_contents($outputPath) === "new source\n", 'an existing CSV is replaced by the complete temporary CSV');
assertFinalMeritRelease(!is_file($temporaryPath), 'the temporary CSV is promoted rather than copied');
unlink($outputPath);

echo "final_merit_list_release_test passed\n";
