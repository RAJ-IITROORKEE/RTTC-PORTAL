<?php
define('APP_INIT', true);
require_once __DIR__ . '/../../config/init.php';
SecurityHelper::requireAdminAuth();

$db = db();

// Filters
$search  = SecurityHelper::sanitize($_GET['search'] ?? '');
$stepF   = (int)($_GET['step'] ?? -1);
$pageNum = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($pageNum - 1) * $perPage;

$where  = "WHERE 1=1";
$params = [];
$types  = '';

if (!empty($search)) {
    $where   .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $like     = "%$search%";
    $params   = array_merge($params, [$like, $like, $like]);
    $types   .= 'sss';
}
if ($stepF >= 0) {
    $where  .= " AND rp.current_step = ?";
    $params[] = $stepF;
    $types   .= 'i';
}

// Count total
$cStmt = $db->prepare("SELECT COUNT(*) FROM users u LEFT JOIN registration_progress rp ON rp.user_id = u.id $where");
if ($types) $cStmt->bind_param($types, ...$params);
$cStmt->execute();
$total = $cStmt->get_result()->fetch_row()[0];
$cStmt->close();
$totalPages = ceil($total / $perPage);

// Fetch rows
$sql = "SELECT u.id, u.username, u.email, u.phone, u.is_verified, u.created_at,
               rp.current_step, rp.is_submitted
        FROM users u
        LEFT JOIN registration_progress rp ON rp.user_id = u.id
        $where
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
$allParams = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($types . 'ii', ...$allParams);
$stmt->execute();
$rows = $stmt->get_result();
$stmt->close();

$pageTitle  = 'All Students - Admin RTTC 2026';
$activePage = 'students';
$breadcrumb = [['label' => 'Students']];
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-people-fill me-2 text-primary"></i>All Students</h4>
    <a href="<?= route('admin.export') ?>" class="btn btn-outline-success btn-sm">
        <i class="bi bi-download me-1"></i>Export CSV
    </a>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search by name, email, phone..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="step" class="form-select">
                    <option value="-1">All Steps</option>
                    <option value="0" <?= $stepF === 0 ? 'selected' : '' ?>>Not Started</option>
                    <option value="1" <?= $stepF === 1 ? 'selected' : '' ?>>Personal Done</option>
                    <option value="2" <?= $stepF === 2 ? 'selected' : '' ?>>Academic Done</option>
                    <option value="3" <?= $stepF === 3 ? 'selected' : '' ?>>Docs Uploaded</option>
                    <option value="4" <?= $stepF === 4 ? 'selected' : '' ?>>Payment Done</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
            </div>
            <div class="col-md-2">
                <a href="<?= route('admin.students') ?>" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3">
        <small class="text-muted">Showing <?= min($total, ($pageNum - 1) * $perPage + 1) ?>–<?= min($total, $pageNum * $perPage) ?> of <?= $total ?> students</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>App ID</th><th>Name</th><th>Email</th><th>Phone</th>
                        <th>Verified</th><th>Step</th><th>Submitted</th><th>Registered</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = $offset + 1; while ($row = $rows->fetch_assoc()):
                        $appId = 'RTTC-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT);
                    ?>
                    <tr>
                        <td class="font-monospace small fw-semibold"><?= $appId ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($row['username']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['phone']) ?></td>
                        <td><?= $row['is_verified'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
                        <td>
                            <?php $s = $row['current_step'] ?? 0;
                            $sc = ['secondary','info','warning','primary','success'];
                            $sl = ['Not Started','Personal','Academic','Docs','Payment'];
                            echo '<span class="badge bg-' . ($sc[$s] ?? 'secondary') . '">' . ($sl[$s] ?? 'N/A') . '</span>';
                            ?>
                        </td>
                        <td><?= $row['is_submitted'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-light text-dark">No</span>' ?></td>
                        <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                        <td>
                            <a href="<?= route('admin.students.view', ['id' => $row['id']]) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i>View
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($total === 0): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No students found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-white">
        <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= $p === $pageNum ? 'active' : '' ?>">
                    <a class="page-link" href="?search=<?= urlencode($search) ?>&step=<?= $stepF ?>&page=<?= $p ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include BASE_PATH . '/admin/layouts/admin.php';
