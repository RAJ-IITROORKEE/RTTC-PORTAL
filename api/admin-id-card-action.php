<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json; charset=UTF-8');

$respond = static function (int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body);
    exit;
};
if ($_SERVER['REQUEST_METHOD'] !== 'POST') $respond(405, ['success' => false, 'message' => 'Method not allowed.']);
if (!SessionHelper::isAdminLoggedIn()) $respond(401, ['success' => false, 'message' => 'Admin authentication is required.']);
if (!IdCardHelper::canManageRole(SessionHelper::get('admin_role'))) $respond(403, ['success' => false, 'message' => 'Your role cannot manage ID cards.']);
if (!SecurityHelper::validateCsrfToken((string) ($_POST[CSRF_TOKEN_NAME] ?? ''))) $respond(403, ['success' => false, 'message' => 'Security token is invalid or expired.']);

$action = (string) ($_POST['action'] ?? '');
$applicationId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
$adminId = (int) SessionHelper::get('admin_id', 0);
if (!in_array($action, ['approve', 'mark_done', 'delete'], true) || !$applicationId || $applicationId < 1 || $adminId < 1) {
    $respond(400, ['success' => false, 'message' => 'Invalid ID card action.']);
}

$db = db();
$photoToDelete = '';
try {
    $db->begin_transaction();
    $stmt = $db->prepare('SELECT * FROM id_card_applications WHERE id = ? FOR UPDATE');
    if (!$stmt) throw new RuntimeException('ID card lookup could not be prepared.');
    $stmt->bind_param('i', $applicationId);
    $stmt->execute();
    $application = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$application) {
        $db->rollback();
        $respond(404, ['success' => false, 'message' => 'ID card application was not found.']);
    }
    $status = (string) $application['status'];
    $reference = IdCardHelper::formatReference((string) $application['application_type'], (int) $application['id']);
    $now = date('Y-m-d H:i:s');
    if ($action === 'approve') {
        if (!IdCardHelper::canTransition($status, IdCardHelper::STATUS_APPROVED)) {
            $db->rollback();
            $respond(409, ['success' => false, 'message' => 'Only pending ID cards can be approved.']);
        }
        $update = $db->prepare("UPDATE id_card_applications SET status = 'approved', approved_at = ?, approved_by = ?, updated_at = ? WHERE id = ? AND status = 'pending'");
        if (!$update) throw new RuntimeException('Approval could not be prepared.');
        $update->bind_param('sisi', $now, $adminId, $now, $applicationId);
        if (!$update->execute() || $update->affected_rows !== 1) throw new RuntimeException('ID card approval failed.');
        $update->close();
        $logAction = 'approved';
        $message = 'ID card approved. Downloading will mark it done.';
        $responseData = IdCardHelper::approvalDates($now);
    } elseif ($action === 'mark_done') {
        if (!IdCardHelper::canTransition($status, IdCardHelper::STATUS_DONE)) {
            $db->rollback();
            $respond(409, ['success' => false, 'message' => 'Only approved or completed ID cards can be recorded as downloaded.']);
        }
        $update = $db->prepare("UPDATE id_card_applications SET status = 'done', first_downloaded_at = COALESCE(first_downloaded_at, ?), last_downloaded_at = ?, download_count = download_count + 1, updated_at = ? WHERE id = ? AND status IN ('approved', 'done')");
        if (!$update) throw new RuntimeException('Download completion could not be prepared.');
        $update->bind_param('sssi', $now, $now, $now, $applicationId);
        if (!$update->execute() || $update->affected_rows !== 1) throw new RuntimeException('ID card completion failed.');
        $update->close();
        $logAction = 'downloaded';
        $message = 'ID card marked done.';
        $responseData = IdCardHelper::approvalDates((string) $application['approved_at']);
    } else {
        if ($status !== IdCardHelper::STATUS_PENDING) {
            $db->rollback();
            $respond(409, ['success' => false, 'message' => 'Only pending ID card applications can be deleted.']);
        }
        $photoToDelete = (string) $application['photo_path'];
        $delete = $db->prepare("DELETE FROM id_card_applications WHERE id = ? AND status = 'pending'");
        if (!$delete) throw new RuntimeException('Deletion could not be prepared.');
        $delete->bind_param('i', $applicationId);
        if (!$delete->execute() || $delete->affected_rows !== 1) throw new RuntimeException('ID card deletion failed.');
        $delete->close();
        $logAction = 'deleted';
        $message = 'Pending ID card application deleted.';
        $responseData = [];
    }
    $log = $db->prepare('INSERT INTO id_card_action_log (application_id, application_reference, action, admin_user_id, created_at) VALUES (?, ?, ?, ?, ?)');
    if (!$log) throw new RuntimeException('ID card audit logging could not be prepared.');
    $logApplicationId = $action === 'delete' ? null : $applicationId;
    $log->bind_param('issis', $logApplicationId, $reference, $logAction, $adminId, $now);
    if (!$log->execute()) throw new RuntimeException('ID card audit logging failed.');
    $log->close();
    if (!$db->commit()) throw new RuntimeException('ID card action could not be committed.');
    if ($photoToDelete !== '' && !IdCardHelper::deleteStoredPhoto($photoToDelete)) error_log('ID card photo cleanup failed for ' . $reference);
    $respond(200, array_merge(['success' => true, 'message' => $message], $responseData));
} catch (Throwable $e) {
    $db->rollback();
    error_log('admin-id-card-action error: ' . $e->getMessage());
    $respond(500, ['success' => false, 'message' => 'The ID card action could not be completed. Please try again.']);
}
