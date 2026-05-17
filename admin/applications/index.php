<?php
define('APP_INIT', true);
require_once __DIR__ . '/../../config/init.php';
SecurityHelper::requireAdminAuth();

$db      = db();
$search  = SecurityHelper::sanitize($_GET['search'] ?? '');
$submitted = (int)($_GET['submitted'] ?? -1);
$pageNum = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($pageNum - 1) * $perPage;

$where  = "WHERE 1=1";
$params = [];
$types  = '';

if (!empty($search)) {
    $where .= " AND (u.username LIKE ? OR u.email LIKE ? OR p.firstname LIKE ? OR p.lastname LIKE ?)";
    $like   = "%$search%";
    $params = [$like, $like, $like, $like];
    $types  = 'ssss';
}
if ($submitted >= 0) {
    $where .= " AND rp.is_submitted = ?";
    $params[] = $submitted;
    $types   .= 'i';
}

$cStmt = $db->prepare("SELECT COUNT(*)
    FROM users u
    LEFT JOIN personal_details p ON p.user_id = u.id
    LEFT JOIN registration_progress rp ON rp.user_id = u.id
    $where");
if ($types) $cStmt->bind_param($types, ...$params);
$cStmt->execute();
$total = $cStmt->get_result()->fetch_row()[0];
$cStmt->close();
$totalPages = ceil($total / $perPage);

$stmt = $db->prepare("SELECT u.id, u.username, u.email, u.phone, u.created_at,
    p.firstname, p.lastname, p.caste, p.ews, p.obc_ncl, p.pwd,
    a.gubedcet_rollno, a.gubedcet_marks, a.gubedcet_rank,
    rp.current_step, rp.is_submitted
    FROM users u
    LEFT JOIN personal_details p ON p.user_id = u.id
    LEFT JOIN academic_details a ON a.user_id = u.id
    LEFT JOIN registration_progress rp ON rp.user_id = u.id
    $where
    ORDER BY rp.is_submitted DESC, u.created_at DESC
    LIMIT ? OFFSET ?");
$allP = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($types . 'ii', ...$allP);
$stmt->execute();
$rows = $stmt->get_result();
$stmt->close();

$pageTitle  = 'Applications - Admin RTTC 2026';
$activePage = 'applications';
$breadcrumb = [['label' => 'Applications']];
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-person-fill me-2 text-primary"></i>Applications</h4>
    <a href="<?= route('admin.export') ?>" class="btn btn-outline-success btn-sm">
        <i class="bi bi-download me-1"></i>Export CSV
    </a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search by name or email..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="submitted" class="form-select">
                    <option value="-1">All Status</option>
                    <option value="1" <?= $submitted === 1 ? 'selected' : '' ?>>Submitted</option>
                    <option value="0" <?= $submitted === 0 ? 'selected' : '' ?>>Incomplete</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Filter</button>
            </div>
            <div class="col-md-2">
                <a href="<?= route('admin.applications') ?>" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3">
        <small class="text-muted">Total: <?= $total ?> applications</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th><th>Name</th><th>Email</th>
                        <th>Category</th><th>GUBEDCET Roll</th><th>GUBEDCET Marks</th><th>Rank</th>
                        <th>Status</th><th>Date</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = $offset + 1; while ($row = $rows->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td class="fw-semibold">
                            <?= htmlspecialchars(trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? $row['username']))) ?>
                        </td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td>
                            <span class="badge bg-secondary"><?= htmlspecialchars($row['caste'] ?? 'N/A') ?></span>
                            <?php if ($row['ews']): ?><span class="badge bg-info">EWS</span><?php endif; ?>
                            <?php if ($row['pwd']): ?><span class="badge bg-warning text-dark">PWD</span><?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['gubedcet_rollno'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['gubedcet_marks'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['gubedcet_rank'] ?? '-') ?></td>
                        <td>
                            <?php if ($row['is_submitted']): ?>
                                <span class="badge bg-success">Submitted</span>
                            <?php else:
                                $s = $row['current_step'] ?? 0;
                                $sl = ['Not Started','Personal','Academic','Docs','Payment'];
                                echo '<span class="badge bg-secondary">' . ($sl[$s] ?? 'N/A') . '</span>';
                            endif; ?>
                        </td>
                        <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                        <td>
                            <a href="<?= route('admin.students.view', ['id' => $row['id']]) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($total === 0): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">No applications found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-white">
        <nav><ul class="pagination pagination-sm mb-0 justify-content-center">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p === $pageNum ? 'active' : '' ?>">
                <a class="page-link" href="?search=<?= urlencode($search) ?>&submitted=<?= $submitted ?>&page=<?= $p ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include BASE_PATH . '/admin/layouts/admin.php';
