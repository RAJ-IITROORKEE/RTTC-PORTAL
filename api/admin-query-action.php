<?php
/**
 * RTTC 2026 - API: Admin Query Actions
 * POST /api/admin-query-action
 * Actions: mark_resolved | grant_access | delete
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

$action  = trim($_POST['action']   ?? '');
$queryId = (int)($_POST['query_id'] ?? 0);
$userId  = (int)($_POST['user_id'] ?? 0);

if (!$queryId || !in_array($action, ['mark_resolved', 'grant_access', 'delete'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$db = db();

// Verify query exists
$stmt = $db->prepare("SELECT id, user_id FROM student_queries WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $queryId);
$stmt->execute();
$query = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$query) {
    echo json_encode(['success' => false, 'message' => 'Query not found.']);
    exit;
}

switch ($action) {

    case 'mark_resolved':
        $upd = $db->prepare("UPDATE student_queries SET status = 'resolved', updated_at = NOW() WHERE id = ?");
        $upd->bind_param('i', $queryId);
        $ok = $upd->execute();
        $upd->close();
        echo json_encode([
            'success' => $ok,
            'message' => $ok ? 'Marked as resolved.' : 'Database error.',
        ]);
        break;

    case 'grant_access':
        if (!$userId && !$query['user_id']) {
            echo json_encode(['success' => false, 'message' => 'No associated student account found.']);
            break;
        }
        $targetUserId = $userId ?: $query['user_id'];
        $adminId      = SessionHelper::get('admin_id');
        $expiresAt    = date('Y-m-d H:i:s', strtotime('+7 days'));
        $note         = 'Granted via admin action (Query #' . $queryId . ')';

        // Deactivate any previous grants
        $deact = $db->prepare("UPDATE user_edit_access SET is_active = 0 WHERE user_id = ?");
        $deact->bind_param('i', $targetUserId);
        $deact->execute();
        $deact->close();

        $ins = $db->prepare("
            INSERT INTO user_edit_access (user_id, granted_by, granted_at, expires_at, is_active, note, created_at, updated_at)
            VALUES (?, ?, NOW(), ?, 1, ?, NOW(), NOW())
        ");
        $ins->bind_param('iiss', $targetUserId, $adminId, $expiresAt, $note);
        $ok = $ins->execute();
        $ins->close();

        if ($ok) {
            // Mark query as having edit access granted
            $upd = $db->prepare("UPDATE student_queries SET edit_access_granted = 1, updated_at = NOW() WHERE id = ?");
            $upd->bind_param('i', $queryId);
            $upd->execute();
            $upd->close();
        }

        echo json_encode([
            'success' => $ok,
            'message' => $ok ? 'Edit access granted for 7 days.' : 'Failed to grant access.',
        ]);
        break;

    case 'delete':
        $del = $db->prepare("DELETE FROM student_queries WHERE id = ?");
        $del->bind_param('i', $queryId);
        $ok = $del->execute();
        $del->close();
        echo json_encode([
            'success' => $ok,
            'message' => $ok ? 'Query deleted.' : 'Database error.',
        ]);
        break;
}
