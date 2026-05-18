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

// Verify user exists
$chk = $db->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
$chk->bind_param('i', $userId);
$chk->execute();
if (!$chk->get_result()->fetch_assoc()) {
    $chk->close();
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit;
}
$chk->close();

// Delete in dependency order (child tables first, then users)
$db->begin_transaction();
try {
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

    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Student record permanently deleted.']);
} catch (Throwable $e) {
    $db->rollback();
    error_log('admin-delete-user error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Could not delete user.']);
}
