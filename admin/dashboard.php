<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';
SecurityHelper::requireAdminAuth();

$db = db();

// Stats
$stats = [];
$queries = [
    'total_users'       => "SELECT COUNT(*) FROM users",
    'verified_users'    => "SELECT COUNT(*) FROM users WHERE is_verified = 1",
    'personal_done'     => "SELECT COUNT(*) FROM registration_progress WHERE current_step >= 1",
    'academic_done'     => "SELECT COUNT(*) FROM registration_progress WHERE current_step >= 2",
    'docs_done'         => "SELECT COUNT(*) FROM registration_progress WHERE current_step >= 3",
    'payments_done'     => "SELECT COUNT(*) FROM registration_progress WHERE current_step >= 4",
    'submitted'         => "SELECT COUNT(*) FROM registration_progress WHERE is_submitted = 1",
    'total_payment_amt' => "SELECT COALESCE(SUM(amount), 0) FROM payment WHERE status = 'success'",
];
foreach ($queries as $key => $q) {
    $r = $db->query($q);
    $stats[$key] = $r ? $r->fetch_row()[0] : 0;
}

// Recent registrations
$recent = $db->query("SELECT u.id, u.username, u.email, u.phone, u.created_at, rp.current_step, rp.is_submitted
    FROM users u
    LEFT JOIN registration_progress rp ON rp.user_id = u.id
    ORDER BY u.created_at DESC LIMIT 10");

$pageTitle  = 'Dashboard - Admin RTTC 2026';
$activePage = 'dashboard';
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 fw-bold"><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h4>
    <span class="text-muted small">Last updated: <?= date('d M Y h:i A') ?></span>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <?php $cards = [
        ['icon' => 'people-fill', 'color' => 'primary', 'label' => 'Total Registrations', 'value' => $stats['total_users']],
        ['icon' => 'patch-check-fill', 'color' => 'success', 'label' => 'Applications Submitted', 'value' => $stats['submitted']],
        ['icon' => 'credit-card-fill', 'color' => 'info', 'label' => 'Payments Received', 'value' => $stats['payments_done']],
        ['icon' => 'currency-rupee', 'color' => 'warning', 'label' => 'Total Revenue', 'value' => '₹' . number_format($stats['total_payment_amt'] / 100, 0)],
    ]; ?>
    <?php foreach ($cards as $card): ?>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm stat-card">
            <div class="card-body d-flex align-items-center gap-3 p-4">
                <div class="stat-icon bg-<?= $card['color'] ?> bg-opacity-10 text-<?= $card['color'] ?>">
                    <i class="bi bi-<?= $card['icon'] ?> fs-4"></i>
                </div>
                <div>
                    <div class="stat-value fw-bold fs-3"><?= $card['value'] ?></div>
                    <div class="stat-label text-muted small"><?= $card['label'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Progress Funnel -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 pt-3">
        <h6 class="fw-bold mb-0">Application Progress Funnel</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php $funnel = [
                ['label' => 'Signed Up', 'value' => $stats['total_users'], 'color' => 'primary'],
                ['label' => 'Personal Done', 'value' => $stats['personal_done'], 'color' => 'info'],
                ['label' => 'Academic Done', 'value' => $stats['academic_done'], 'color' => 'warning'],
                ['label' => 'Docs Uploaded', 'value' => $stats['docs_done'], 'color' => 'orange'],
                ['label' => 'Payment Done', 'value' => $stats['payments_done'], 'color' => 'success'],
            ];
            $max = max(1, $stats['total_users']);
            ?>
            <?php foreach ($funnel as $f): ?>
            <div class="col-md">
                <div class="text-center mb-1">
                    <strong><?= $f['value'] ?></strong>
                    <div class="text-muted small"><?= $f['label'] ?></div>
                </div>
                <div class="progress" style="height:10px;">
                    <div class="progress-bar bg-<?= $f['color'] ?>" style="width:<?= round($f['value'] / $max * 100) ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Recent Registrations -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0">Recent Registrations</h6>
        <a href="<?= route('admin.students') ?>" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Step</th><th>Submitted</th><th>Date</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; while ($row = $recent->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['username']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['phone']) ?></td>
                        <td>
                            <?php $step = $row['current_step'] ?? 0;
                            $steps = ['Not Started','Personal','Academic','Docs','Payment'];
                            $colors = ['secondary','info','warning','orange','success'];
                            ?>
                            <span class="badge bg-<?= $colors[$step] ?? 'secondary' ?>"><?= $steps[$step] ?? 'N/A' ?></span>
                        </td>
                        <td>
                            <?php if ($row['is_submitted']): ?>
                                <span class="badge bg-success">Yes</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">No</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                        <td>
                            <a href="<?= route('admin.students.view', ['id' => $row['id']]) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include BASE_PATH . '/admin/layouts/admin.php';
