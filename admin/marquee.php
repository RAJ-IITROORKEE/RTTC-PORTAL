<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';
SecurityHelper::requireAdminAuth();

$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SecurityHelper::verifyCsrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $content = trim((string)($_POST['content'] ?? ''));
        $linkUrl = trim((string)($_POST['link_url'] ?? ''));
        $linkLabel = trim((string)($_POST['link_label'] ?? ''));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($content === '') {
            SessionHelper::setFlash('error', 'Marquee text content is required.');
            redirect('admin.marquee');
        }

        if ($linkLabel === '') {
            $linkLabel = 'Click Here';
        }

        if ($id > 0) {
            $stmt = $db->prepare("UPDATE home_marquee_items
                                  SET content = ?, link_url = ?, link_label = ?, sort_order = ?, is_active = ?
                                  WHERE id = ?");
            $stmt->bind_param('sssiii', $content, $linkUrl, $linkLabel, $sortOrder, $isActive, $id);
            $ok = $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $db->prepare("INSERT INTO home_marquee_items (content, link_url, link_label, sort_order, is_active)
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('sssii', $content, $linkUrl, $linkLabel, $sortOrder, $isActive);
            $ok = $stmt->execute();
            $stmt->close();
        }

        SessionHelper::setFlash($ok ? 'success' : 'error', $ok ? 'Marquee item saved successfully.' : 'Failed to save marquee item.');
        redirect('admin.marquee');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM home_marquee_items WHERE id = ?");
            $stmt->bind_param('i', $id);
            $ok = $stmt->execute();
            $stmt->close();
            SessionHelper::setFlash($ok ? 'success' : 'error', $ok ? 'Marquee item deleted.' : 'Failed to delete marquee item.');
        }
        redirect('admin.marquee');
    }
}

$items = [];
$rs = $db->query("SELECT id, content, link_url, link_label, sort_order, is_active
                  FROM home_marquee_items
                  ORDER BY sort_order ASC, id ASC");
if ($rs) {
    while ($row = $rs->fetch_assoc()) {
        $items[] = $row;
    }
}

$pageTitle = 'Home Marquee - Admin RTTC 2026';
$activePage = 'marquee';
$breadcrumb = [['label' => 'Home Marquee']];
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-megaphone-fill me-2 text-primary"></i>Home Marquee Texts</h4>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom pt-3">
        <h6 class="fw-bold mb-0">Add New Marquee Item</h6>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= route('admin.marquee') ?>" class="row g-3">
            <?= SecurityHelper::csrfField() ?>
            <input type="hidden" name="action" value="save">

            <div class="col-md-12">
                <label class="form-label">Moving Text Content</label>
                <textarea name="content" class="form-control" rows="2" required placeholder="Enter marquee content text..."></textarea>
            </div>

            <div class="col-md-5">
                <label class="form-label">Optional Link URL</label>
                <input type="url" name="link_url" class="form-control" placeholder="https://...">
            </div>
            <div class="col-md-3">
                <label class="form-label">Link Button Label</label>
                <input type="text" name="link_label" class="form-control" value="Click Here">
            </div>
            <div class="col-md-2">
                <label class="form-label">Sort Order</label>
                <input type="number" name="sort_order" class="form-control" value="100">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="is_active" id="newMarqueeActive" checked>
                    <label class="form-check-label" for="newMarqueeActive">Active</label>
                </div>
            </div>

            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>Add Marquee Item
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom pt-3">
        <h6 class="fw-bold mb-0">Manage Existing Marquee Items</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Content</th>
                        <th>Link</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th style="min-width: 420px;">Update</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No marquee items found.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $idx => $item): ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td style="max-width: 360px; white-space: normal;"><?= htmlspecialchars($item['content']) ?></td>
                        <td>
                            <?php if (!empty($item['link_url'])): ?>
                                <a href="<?= htmlspecialchars($item['link_url']) ?>" target="_blank"><?= htmlspecialchars($item['link_label'] ?: 'Click Here') ?></a>
                            <?php else: ?>
                                <span class="text-muted">No link</span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$item['sort_order'] ?></td>
                        <td><?= (int)$item['is_active'] === 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                        <td>
                            <form method="POST" action="<?= route('admin.marquee') ?>" class="row g-2">
                                <?= SecurityHelper::csrfField() ?>
                                <input type="hidden" name="action" value="save">
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">

                                <div class="col-12">
                                    <textarea name="content" class="form-control form-control-sm" rows="2" required><?= htmlspecialchars($item['content']) ?></textarea>
                                </div>
                                <div class="col-6">
                                    <input type="url" name="link_url" class="form-control form-control-sm" value="<?= htmlspecialchars((string)($item['link_url'] ?? '')) ?>" placeholder="https://...">
                                </div>
                                <div class="col-3">
                                    <input type="text" name="link_label" class="form-control form-control-sm" value="<?= htmlspecialchars((string)($item['link_label'] ?? 'Click Here')) ?>" placeholder="Button label">
                                </div>
                                <div class="col-3">
                                    <input type="number" name="sort_order" class="form-control form-control-sm" value="<?= (int)$item['sort_order'] ?>">
                                </div>
                                <div class="col-6 d-flex align-items-center">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="activeMarquee<?= (int)$item['id'] ?>" <?= (int)$item['is_active'] === 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="activeMarquee<?= (int)$item['id'] ?>">Active</label>
                                    </div>
                                </div>
                                <div class="col-6 text-end">
                                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                </div>
                            </form>

                            <form method="POST" action="<?= route('admin.marquee') ?>" class="mt-2" onsubmit="return confirm('Delete this marquee item?');">
                                <?= SecurityHelper::csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
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
