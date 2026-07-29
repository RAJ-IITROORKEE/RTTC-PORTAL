<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';

SecurityHelper::requireAuth();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $student = (new ProvisionalStudentRepository(ROOT_PATH . '/PROVISIONAL LIST.csv'))
        ->findByRollNo((string) ($_GET['roll_no'] ?? ''));
} catch (Throwable $exception) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Student data is temporarily unavailable']);
    exit;
}

if ($student === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Roll number not found']);
    exit;
}

echo json_encode(['success' => true, 'student' => $student], JSON_UNESCAPED_UNICODE);
