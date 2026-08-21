<?php
define('APP_INIT', true);
require_once __DIR__ . '/../../config/init.php';
SecurityHelper::requireAdminAuth();

$db = db();
$search = IdCardHelper::normalizeText($_GET['search'] ?? '');
$type = (string) ($_GET['type'] ?? '');
$status = (string) ($_GET['status'] ?? '');
$pageNum = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$where = 'WHERE 1=1';
$params = [];
$types = '';
if ($search !== '') {
    $like = '%' . $search . '%';
    $referenceId = 0;
    $referenceType = '';
    if (preg_match('/^IDC-([SF])-(\d{1,})$/i', $search, $match)) {
        $referenceId = (int) $match[2];
        $referenceType = strtoupper($match[1]) === 'S' ? IdCardHelper::TYPE_STUDENT : IdCardHelper::TYPE_FACULTY_STAFF;
    }
    $where .= ' AND (full_name LIKE ? OR contact_number LIKE ? OR roll_number LIKE ? OR department LIKE ? OR designation LIKE ? OR id = ? OR (id = ? AND application_type = ?))';
    $params = [$like, $like, $like, $like, $like, ctype_digit($search) ? (int) $search : 0, $referenceId, $referenceType];
    $types = 'sssssiis';
}
if (IdCardHelper::isValidType($type)) {
    $where .= ' AND application_type = ?';
    $params[] = $type;
    $types .= 's';
}
if (array_key_exists($status, IdCardHelper::statuses())) {
    $where .= ' AND status = ?';
    $params[] = $status;
    $types .= 's';
}
$count = $db->prepare("SELECT COUNT(*) FROM id_card_applications $where");
if ($types !== '') $count->bind_param($types, ...$params);
$count->execute();
$total = (int) $count->get_result()->fetch_row()[0];
$count->close();
$totalPages = max(1, (int) ceil($total / $perPage));
$pageNum = min($pageNum, $totalPages);
$offset = ($pageNum - 1) * $perPage;
$list = $db->prepare("SELECT id, application_type, full_name, course, designation, roll_number, contact_number, status, created_at, approved_at FROM id_card_applications $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
$listParams = array_merge($params, [$perPage, $offset]);
$list->bind_param($types . 'ii', ...$listParams);
$list->execute();
$rows = $list->get_result();
$list->close();
$role = SessionHelper::get('admin_role');
$canManage = IdCardHelper::canManageRole($role);
$esc = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pageTitle = 'ID Cards - Admin RTTC 2026';
$activePage = 'id-cards';
$breadcrumb = [['label' => 'ID Cards']];
ob_start();
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><h4 class="fw-bold mb-0"><i class="bi bi-person-vcard-fill me-2 text-primary"></i>ID Cards</h4><small class="text-muted"><?= $canManage ? 'Review and issue submitted cards.' : 'Viewer access: list only.' ?></small></div>
<div class="card border-0 shadow-sm mb-4"><div class="card-body py-3"><div class="row g-2 align-items-end"><form method="get" class="col-lg row g-2"><div class="col-md-5"><label class="visually-hidden" for="id-card-search">Search</label><input id="id-card-search" name="search" class="form-control" value="<?= $esc($search) ?>" placeholder="Search name, roll no., contact, ID"></div><div class="col-md-3"><select name="type" class="form-select"><option value="">All types</option><?php foreach (IdCardHelper::applicationTypes() as $value => $label): ?><option value="<?= $esc($value) ?>" <?= $type === $value ? 'selected' : '' ?>><?= $esc($label) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><select name="status" class="form-select"><option value="">All statuses</option><?php foreach (IdCardHelper::statuses() as $value => $label): ?><option value="<?= $esc($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= $esc($label) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Search</button></div></form><div class="col-lg-auto d-flex flex-wrap gap-2"><button type="button" class="btn btn-outline-secondary btn-sm" data-copy-url="<?= $esc(route('id-card.student')) ?>">Copy Student URL</button><a class="btn btn-outline-primary btn-sm" href="<?= route('id-card.student') ?>" target="_blank" rel="noopener">Open Student</a><button type="button" class="btn btn-outline-secondary btn-sm" data-copy-url="<?= $esc(route('id-card.faculty-staff')) ?>">Copy Faculty URL</button><a class="btn btn-outline-primary btn-sm" href="<?= route('id-card.faculty-staff') ?>" target="_blank" rel="noopener">Open Faculty</a></div></div></div></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white border-0 pt-3"><small class="text-muted">Showing <?= $total ? $offset + 1 : 0 ?>-<?= min($total, $offset + $perPage) ?> of <?= $total ?> applications</small></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0 admin-data-table"><thead class="table-light"><tr><th>Reference</th><th>Holder</th><th>Type</th><th>Course / Designation</th><th>Contact</th><th>Status</th><th>Submitted</th><th>Action</th></tr></thead><tbody><?php while ($row = $rows->fetch_assoc()): $reference = IdCardHelper::formatReference($row['application_type'], (int) $row['id']); $badge = ['pending' => 'warning text-dark', 'approved' => 'primary', 'done' => 'success'][$row['status']] ?? 'secondary'; ?><tr><td class="font-monospace small fw-semibold"><?= $esc($reference) ?></td><td><?= $esc($row['full_name']) ?><?php if (!empty($row['roll_number'])): ?><small class="d-block text-muted">Roll: <?= $esc($row['roll_number']) ?></small><?php endif; ?></td><td><?= $esc(IdCardHelper::applicationTypes()[$row['application_type']] ?? '') ?></td><td><?= $esc($row['course'] ?: $row['designation']) ?></td><td><?= $esc($row['contact_number']) ?></td><td><span class="badge bg-<?= $badge ?>"><?= $esc(IdCardHelper::statuses()[$row['status']] ?? $row['status']) ?></span></td><td><?= $esc(date('d M Y', strtotime($row['created_at']))) ?></td><td><?php if ($canManage): ?><a href="<?= route('admin.id-cards.review', ['id' => $row['id']]) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i><?= $row['status'] === 'pending' ? 'Approve' : 'Review' ?></a><?php else: ?><span class="text-muted small">List only</span><?php endif; ?></td></tr><?php endwhile; ?><?php if (!$total): ?><tr><td colspan="8" class="text-center text-muted py-4">No ID card applications found.</td></tr><?php endif; ?></tbody></table></div></div><?php if ($totalPages > 1): ?><div class="card-footer bg-white"><nav><ul class="pagination pagination-sm justify-content-center mb-0"><li class="page-item <?= $pageNum <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?<?= http_build_query(['search' => $search, 'type' => $type, 'status' => $status, 'page' => max(1, $pageNum - 1)]) ?>">&laquo;</a></li><?php for ($i = max(1, $pageNum - 2); $i <= min($totalPages, $pageNum + 2); $i++): ?><li class="page-item <?= $i === $pageNum ? 'active' : '' ?>"><a class="page-link" href="?<?= http_build_query(['search' => $search, 'type' => $type, 'status' => $status, 'page' => $i]) ?>"><?= $i ?></a></li><?php endfor; ?><li class="page-item <?= $pageNum >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="?<?= http_build_query(['search' => $search, 'type' => $type, 'status' => $status, 'page' => min($totalPages, $pageNum + 1)]) ?>">&raquo;</a></li></ul></nav></div><?php endif; ?></div>
<?php $content = ob_get_clean(); $extraFoot = '<script>document.querySelectorAll("[data-copy-url]").forEach(function(button){button.addEventListener("click",function(){navigator.clipboard.writeText(button.dataset.copyUrl).then(function(){var original=button.textContent;button.textContent="Copied";setTimeout(function(){button.textContent=original;},1200);});});});</script>'; include BASE_PATH . '/admin/layouts/admin.php';
