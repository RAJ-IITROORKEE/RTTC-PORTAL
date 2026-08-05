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
    $student = (new GubedcetMeritListRepository(ROOT_PATH . '/final_list/GUBEDCET 2026 FINAL LIST.csv'))
        ->findByRollNo((string) ($_GET['roll_no'] ?? ''));
} catch (Throwable $exception) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'GUBEDCET final merit data is temporarily unavailable. Please try again.']);
    exit;
}

if ($student === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Roll number not found']);
    exit;
}

if ($student['total_marks'] === '' || $student['rank'] === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'This result is marked as rejected in the GUBEDCET final merit list and cannot be used for admission.']);
    exit;
}

echo json_encode(['success' => true, 'student' => $student], JSON_UNESCAPED_UNICODE);
