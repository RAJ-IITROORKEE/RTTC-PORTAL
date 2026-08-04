<?php
/**
 * RTTC 2026 – API: Admin Delete User
 * POST /api/admin-delete-user
 * Body: user_id (int)
 * Returns JSON {success, message}
 */
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';
SecurityHelper::requireAdminAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$userId = (int)($_POST['user_id'] ?? 0);
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
    exit;
}

$db = db();
$db->begin_transaction();
try {
    // Payment finalization uses this same applicant-first lock order.
    $userLock = $db->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE");
    if (!$userLock) throw new RuntimeException('Could not prepare user lock.');
    $userLock->bind_param('i', $userId);
    $userLock->execute();
    $user = $userLock->get_result()->fetch_assoc();
    $userLock->close();
    if (!$user) {
        $db->rollback();
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    // Delete in dependency order (child tables first, then users).
    $tables = [
        'user_edit_access'      => 'user_id',
        'student_queries'       => 'user_id',
        'documents'             => 'user_id',
        'payment'               => 'user_id',
        'registration_progress' => 'user_id',
        'academic_details'      => 'user_id',
        'personal_details'      => 'user_id',
    ];

    foreach ($tables as $table => $col) {
        $stmt = $db->prepare("DELETE FROM `{$table}` WHERE `{$col}` = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }

    // Finally delete from users
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();

    if (!$db->commit()) throw new RuntimeException('Could not commit user deletion.');
    echo json_encode(['success' => true, 'message' => 'Student record permanently deleted.']);
} catch (Throwable $e) {
    $db->rollback();
    error_log('admin-delete-user error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Could not delete user.']);
}
