<?php
define('APP_INIT', true);
require_once __DIR__ . '/../../config/init.php';
SecurityHelper::requireAdminAuth();

$db      = db();
$search  = SecurityHelper::sanitize($_GET['search'] ?? '');
$pageNum = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($pageNum - 1) * $perPage;

$where  = "WHERE pay.status = 'success'";
$params = [];
$types  = '';

if (!empty($search)) {
    $where .= " AND (u.username LIKE ? OR u.email LIKE ? OR pay.razorpay_payment_id LIKE ?)";
    $like   = "%$search%";
    $params = [$like, $like, $like];
    $types  = 'sss';
}

$cStmt = $db->prepare("SELECT COUNT(*) FROM payment pay JOIN users u ON u.id = pay.user_id $where");
if ($types) $cStmt->bind_param($types, ...$params);
$cStmt->execute();
$total = $cStmt->get_result()->fetch_row()[0];
$cStmt->close();
$totalPages = ceil($total / $perPage);

$stmt = $db->prepare("SELECT pay.*, u.username, u.email, u.phone
    FROM payment pay
    JOIN users u ON u.id = pay.user_id
    $where
    ORDER BY pay.created_at DESC
    LIMIT ? OFFSET ?");
$allP = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($types . 'ii', ...$allP);
$stmt->execute();
$rows = $stmt->get_result();
$stmt->close();

// Sum
$sumStmt = $db->query("SELECT SUM(amount) FROM payment WHERE status = 'success'");
$totalAmt = $sumStmt->fetch_row()[0] ?? 0;

$pageTitle  = 'Payments - Admin RTTC 2026';
$activePage = 'payments';
$breadcrumb = [['label' => 'Payments']];
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-credit-card-fill me-2 text-primary"></i>Payments</h4>
    <div class="badge bg-success fs-6">Total Collected: ₹<?= number_format($totalAmt / 100, 2) ?></div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Search by name, email or payment ID..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Search</button>
            </div>
            <div class="col-md-2">
                <a href="<?= route('admin.payments') ?>" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>Student</th><th>Email</th><th>Payment ID</th><th>Amount</th><th>Date</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php $i = $offset + 1; while ($row = $rows->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($row['username']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><code><?= htmlspecialchars($row['razorpay_payment_id']) ?></code></td>
                        <td><span class="badge bg-success">₹<?= number_format($row['amount'] / 100, 2) ?></span></td>
                        <td><?= date('d M Y h:i A', strtotime($row['created_at'])) ?></td>
                        <td>
                            <a href="<?= route('admin.students.view', ['id' => $row['user_id']]) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($total === 0): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No payments found.</td></tr>
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
                <a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $p ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include BASE_PATH . '/admin/layouts/admin.php';
