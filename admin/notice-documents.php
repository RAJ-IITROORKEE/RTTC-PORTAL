<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';
SecurityHelper::requireAdminAuth();

$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SecurityHelper::verifyCsrf();

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'save') {
        $docKey = trim((string)($_POST['doc_key'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $buttonLabel = trim((string)($_POST['button_label'] ?? ''));
        $linkUrl = trim((string)($_POST['link_url'] ?? ''));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $removeFile = isset($_POST['remove_file']) ? 1 : 0;

        if ($docKey === '' || $title === '' || $buttonLabel === '') {
            SessionHelper::setFlash('error', 'Document key, title, and button label are required.');
            redirect('admin.notice-documents');
        }

        $existingPath = '';
        if ($id > 0) {
            $s = $db->prepare("SELECT file_path FROM notice_documents WHERE id = ?");
            $s->bind_param('i', $id);
            $s->execute();
            $old = $s->get_result()->fetch_assoc();
            $s->close();
            $existingPath = (string)($old['file_path'] ?? '');
        }

        $filePath = $existingPath;

        if ($removeFile === 1) {
            if ($existingPath !== '' && str_starts_with($existingPath, 'storage/uploads/notices/')) {
                $full = BASE_PATH . '/' . $existingPath;
                if (is_file($full)) {
                    @unlink($full);
                }
            }
            $filePath = '';
        }

        if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
                SessionHelper::setFlash('error', 'PDF upload failed.');
                redirect('admin.notice-documents');
            }

            $ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                SessionHelper::setFlash('error', 'Only PDF files are allowed.');
                redirect('admin.notice-documents');
            }

            if ($_FILES['pdf_file']['size'] > MAX_FILE_SIZE) {
                SessionHelper::setFlash('error', 'PDF size exceeds upload limit.');
                redirect('admin.notice-documents');
            }

            if (!is_dir(NOTICE_UPLOAD_DIR)) {
                mkdir(NOTICE_UPLOAD_DIR, 0755, true);
            }

            $safeKey = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($docKey));
            $filename = 'notice_' . $safeKey . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
            $dest = rtrim(NOTICE_UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $filename;

            if (!move_uploaded_file($_FILES['pdf_file']['tmp_name'], $dest)) {
                SessionHelper::setFlash('error', 'Failed to save uploaded PDF file.');
                redirect('admin.notice-documents');
            }

            if ($existingPath !== '' && str_starts_with($existingPath, 'storage/uploads/notices/')) {
                $oldFull = BASE_PATH . '/' . $existingPath;
                if (is_file($oldFull)) {
                    @unlink($oldFull);
                }
            }

            $filePath = 'storage/uploads/notices/' . $filename;
        }

        if ($id > 0) {
            $up = $db->prepare("UPDATE notice_documents
                                SET doc_key = ?, title = ?, button_label = ?, file_path = ?, link_url = ?, is_active = ?, sort_order = ?
                                WHERE id = ?");
            $up->bind_param('sssssiii', $docKey, $title, $buttonLabel, $filePath, $linkUrl, $isActive, $sortOrder, $id);
            $ok = $up->execute();
            $up->close();
        } else {
            $ins = $db->prepare("INSERT INTO notice_documents
                                 (doc_key, title, button_label, file_path, link_url, is_active, sort_order)
                                 VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param('sssssii', $docKey, $title, $buttonLabel, $filePath, $linkUrl, $isActive, $sortOrder);
            $ok = $ins->execute();
            $ins->close();
        }

        SessionHelper::setFlash($ok ? 'success' : 'error', $ok ? 'Notice document saved successfully.' : 'Failed to save notice document.');
        redirect('admin.notice-documents');
    }

    if ($action === 'delete' && $id > 0) {
        $s = $db->prepare("SELECT file_path FROM notice_documents WHERE id = ?");
        $s->bind_param('i', $id);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();

        $del = $db->prepare("DELETE FROM notice_documents WHERE id = ?");
        $del->bind_param('i', $id);
        $ok = $del->execute();
        $del->close();

        if ($ok && !empty($row['file_path']) && str_starts_with($row['file_path'], 'storage/uploads/notices/')) {
            $full = BASE_PATH . '/' . $row['file_path'];
            if (is_file($full)) {
                @unlink($full);
            }
        }

        SessionHelper::setFlash($ok ? 'success' : 'error', $ok ? 'Notice document removed successfully.' : 'Failed to remove notice document.');
        redirect('admin.notice-documents');
    }
}

$docs = SiteSettingsHelper::getNoticeDocuments(false);

$pageTitle = 'Notice Documents - Admin RTTC 2026';
$activePage = 'notice-documents';
$breadcrumb = [['label' => 'Notice Documents']];
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-pdf-fill me-2 text-primary"></i>Notice Documents</h4>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom pt-3">
        <h6 class="fw-bold mb-0">Add New Document</h6>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= route('admin.notice-documents') ?>" enctype="multipart/form-data" class="row g-3">
            <?= SecurityHelper::csrfField() ?>
            <input type="hidden" name="action" value="save">

            <div class="col-md-3">
                <label class="form-label">Document Key</label>
                <input type="text" name="doc_key" class="form-control" placeholder="e.g. brochure_2026" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" placeholder="Internal title" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Button Label</label>
                <input type="text" name="button_label" class="form-control" value="View PDF" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Sort Order</label>
                <input type="number" name="sort_order" class="form-control" value="100">
            </div>

            <div class="col-md-6">
                <label class="form-label">PDF File (optional if link used)</label>
                <input type="file" name="pdf_file" class="form-control" accept="application/pdf">
            </div>
            <div class="col-md-6">
                <label class="form-label">External Link (optional)</label>
                <input type="url" name="link_url" class="form-control" placeholder="https://...">
            </div>

            <div class="col-md-3">
                <div class="form-check mt-4 pt-2">
                    <input class="form-check-input" type="checkbox" name="is_active" id="newDocActive" checked>
                    <label class="form-check-label" for="newDocActive">Active</label>
                </div>
            </div>
            <div class="col-md-9 text-md-end">
                <button type="submit" class="btn btn-primary mt-3 mt-md-4">
                    <i class="bi bi-plus-circle me-1"></i>Add Document
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom pt-3">
        <h6 class="fw-bold mb-0">Manage Existing Documents</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Key</th>
                        <th>Title</th>
                        <th>Button</th>
                        <th>Source</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th style="min-width: 420px;">Update</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($docs)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No documents configured yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($docs as $doc): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($doc['doc_key']) ?></code></td>
                        <td><?= htmlspecialchars($doc['title']) ?></td>
                        <td><?= htmlspecialchars($doc['button_label']) ?></td>
                        <td>
                            <?php if (!empty($doc['link_url'])): ?>
                                <a href="<?= htmlspecialchars($doc['link_url']) ?>" target="_blank">External Link</a>
                            <?php elseif (!empty($doc['file_path'])): ?>
                                <a href="<?= BASE_URL . '/' . ltrim($doc['file_path'], '/') ?>" target="_blank">Uploaded PDF</a>
                            <?php else: ?>
                                <span class="text-muted">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$doc['sort_order'] ?></td>
                        <td><?= (int)$doc['is_active'] === 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                        <td>
                            <form method="POST" action="<?= route('admin.notice-documents') ?>" enctype="multipart/form-data" class="row g-2">
                                <?= SecurityHelper::csrfField() ?>
                                <input type="hidden" name="action" value="save">
                                <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">

                                <div class="col-6"><input type="text" name="doc_key" class="form-control form-control-sm" value="<?= htmlspecialchars($doc['doc_key']) ?>" required></div>
                                <div class="col-6"><input type="text" name="title" class="form-control form-control-sm" value="<?= htmlspecialchars($doc['title']) ?>" required></div>
                                <div class="col-6"><input type="text" name="button_label" class="form-control form-control-sm" value="<?= htmlspecialchars($doc['button_label']) ?>" required></div>
                                <div class="col-6"><input type="number" name="sort_order" class="form-control form-control-sm" value="<?= (int)$doc['sort_order'] ?>"></div>
                                <div class="col-6"><input type="file" name="pdf_file" class="form-control form-control-sm" accept="application/pdf"></div>
                                <div class="col-6"><input type="url" name="link_url" class="form-control form-control-sm" value="<?= htmlspecialchars((string)($doc['link_url'] ?? '')) ?>" placeholder="https://..."></div>

                                <div class="col-6 d-flex align-items-center gap-3">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="active<?= (int)$doc['id'] ?>" <?= (int)$doc['is_active'] === 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="active<?= (int)$doc['id'] ?>">Active</label>
                                    </div>
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" name="remove_file" id="remove<?= (int)$doc['id'] ?>">
                                        <label class="form-check-label small" for="remove<?= (int)$doc['id'] ?>">Remove file</label>
                                    </div>
                                </div>
                                <div class="col-6 text-end">
                                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                </div>
                            </form>

                            <form method="POST" action="<?= route('admin.notice-documents') ?>" class="mt-2" onsubmit="return confirm('Delete this notice document?');">
                                <?= SecurityHelper::csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include BASE_PATH . '/admin/layouts/admin.php';
