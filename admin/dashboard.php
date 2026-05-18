<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';
SecurityHelper::requireAdminAuth();

$db = db();

// Stats
$queries_map = [
    'total_users'       => "SELECT COUNT(*) FROM users",
    'total_payment_amt' => "SELECT COALESCE(SUM(amount), 0) FROM payment WHERE status = 'success'",
    'apps_no_pay'       => "SELECT COUNT(*) FROM registration_progress rp
                            WHERE rp.is_submitted = 1
                            AND NOT EXISTS (SELECT 1 FROM payment p WHERE p.user_id = rp.user_id AND p.status = 'success')",
    'apps_with_pay'     => "SELECT COUNT(*) FROM payment WHERE status = 'success'",
    'personal_done'     => "SELECT COUNT(*) FROM registration_progress WHERE current_step >= 1",
    'academic_done'     => "SELECT COUNT(*) FROM registration_progress WHERE current_step >= 2",
    'docs_done'         => "SELECT COUNT(*) FROM registration_progress WHERE current_step >= 3",
    'payments_done'     => "SELECT COUNT(*) FROM registration_progress WHERE current_step >= 4",
];
$stats = [];
foreach ($queries_map as $key => $q) {
    $r = $db->query($q);
    $stats[$key] = $r ? (int)$r->fetch_row()[0] : 0;
}

// All registrations for DataTable (no LIMIT — client-side pagination)
$recent = $db->query("
    SELECT u.id, u.username, u.email, u.phone, u.created_at,
           rp.current_step, rp.is_submitted
    FROM users u
    LEFT JOIN registration_progress rp ON rp.user_id = u.id
    ORDER BY u.created_at DESC
");

$pageTitle  = 'Dashboard - Admin RTTC 2026';
$activePage = 'dashboard';
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 fw-bold"><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h4>
    <span class="text-muted small">Last updated: <?= date('d M Y, h:i A') ?></span>
</div>

<!-- ===== Stats Cards (equal dimension) ===== -->
<div class="row g-3 mb-4">
<?php
$cards = [
    ['icon' => 'people-fill',         'color' => 'primary', 'label' => 'Total Users',                 'value' => number_format($stats['total_users']),   'sub' => 'Registered accounts'],
    ['icon' => 'currency-rupee',      'color' => 'success', 'label' => 'Total Amount Collected',       'value' => '₹' . number_format($stats['total_payment_amt'] / 100, 0), 'sub' => 'From successful payments'],
    ['icon' => 'file-earmark-text-fill','color'=>'warning',  'label' => 'Applications (No Payment)',   'value' => number_format($stats['apps_no_pay']),   'sub' => 'Submitted, awaiting payment'],
    ['icon' => 'patch-check-fill',    'color' => 'info',    'label' => 'Applications (With Payment)',  'value' => number_format($stats['apps_with_pay']), 'sub' => 'Fully completed'],
];
foreach ($cards as $card): ?>
<div class="col-sm-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100" style="min-height:110px;">
        <div class="card-body d-flex align-items-center gap-3 p-4">
            <div class="flex-shrink-0 rounded-3 d-flex align-items-center justify-content-center
                        bg-<?= $card['color'] ?> bg-opacity-10"
                 style="width:56px;height:56px;min-width:56px;">
                <i class="bi bi-<?= $card['icon'] ?> fs-3 text-<?= $card['color'] ?>"></i>
            </div>
            <div class="flex-grow-1 overflow-hidden">
                <div class="fw-bold fs-3 lh-1 mb-1"><?= $card['value'] ?></div>
                <div class="fw-semibold small text-dark text-truncate"><?= $card['label'] ?></div>
                <div class="text-muted" style="font-size:.72rem;"><?= $card['sub'] ?></div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- ===== Charts Row: Progress Pie + Payment Donut ===== -->
<div class="row g-4 mb-4">

    <!-- Application Progress Distribution -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-3">
                <h5 class="card-title fw-bold mb-0">
                    <i class="bi bi-pie-chart-fill me-2 text-primary"></i>Application Progress
                </h5>
                <p class="text-muted small mb-0 mt-1">Distribution of applicants across registration steps</p>
            </div>
            <div class="card-body py-2">
                <div id="progressPieChart" style="height:350px;"></div>
            </div>
        </div>
    </div>

    <!-- Payment Status Donut -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-3">
                <h5 class="card-title fw-bold mb-0">
                    <i class="bi bi-credit-card-2-front-fill me-2 text-success"></i>Payment Status
                </h5>
                <p class="text-muted small mb-0 mt-1">Breakdown of payment completion across all applicants</p>
            </div>
            <div class="card-body py-2">
                <div id="paymentDonutChart" style="height:350px;"></div>
            </div>
        </div>
    </div>

</div>

<!-- ===== Registrations DataTable ===== -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0">
            <i class="bi bi-list-ul me-2 text-primary"></i>All Registrations
        </h6>
        <a href="<?= route('admin.students') ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-people me-1"></i>Students Panel
        </a>
    </div>
    <div class="card-body p-3">
        <div class="table-responsive">
            <table id="recentTable" class="table table-hover align-middle mb-0 w-100" style="font-size:.875rem;">
                <thead class="table-light">
                    <tr>
                        <th>App ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Progress</th>
                        <th>Submitted</th>
                        <th>Registered</th>
                        <th class="text-end no-sort">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $recent->fetch_assoc()):
                        $s     = (int)($row['current_step'] ?? 0);
                        $bgs   = ['secondary','info','primary','warning','success'];
                        $icons = ['dash-circle','person-check','book','file-earmark-check','credit-card'];
                        $labs  = ['Not Started','Personal','Academic','Docs','Payment'];
                        $appId = 'RTTC-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT);
                    ?>
                    <tr>
                        <td class="font-monospace small fw-semibold"><?= $appId ?></td>
                        <td><?= htmlspecialchars($row['username']) ?></td>
                        <td class="small"><?= htmlspecialchars($row['email']) ?></td>
                        <td class="small"><?= htmlspecialchars($row['phone']) ?></td>
                        <td>
                            <span class="badge bg-<?= $bgs[$s] ?? 'secondary' ?> fw-semibold px-2 py-1"
                                  style="font-size:.72rem;letter-spacing:.02em;">
                                <i class="bi bi-<?= $icons[$s] ?? 'dash-circle' ?> me-1"></i><?= $labs[$s] ?? 'N/A' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($row['is_submitted']): ?>
                                <span class="badge bg-success px-2 py-1" style="font-size:.72rem;">
                                    <i class="bi bi-check-lg me-1"></i>Yes
                                </span>
                            <?php else: ?>
                                <span class="badge border text-secondary bg-white px-2 py-1" style="font-size:.72rem;">No</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-nowrap">
                            <?= date('d M Y', strtotime($row['created_at'])) ?>
                            <div class="text-muted" style="font-size:.7rem;"><?= date('H:i', strtotime($row['created_at'])) ?></div>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="<?= route('admin.students.view', ['id' => $row['id']]) ?>"
                               class="btn btn-sm btn-outline-primary py-1 px-2 me-1" title="View Student">
                                <i class="bi bi-eye"></i>
                            </a>
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger py-1 px-2 delete-user-btn"
                                    data-uid="<?= $row['id'] ?>"
                                    data-name="<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>"
                                    data-appid="<?= $appId ?>"
                                    title="Delete Student">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0" style="background:#dc3545;">
                <h5 class="modal-title text-white fw-bold">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Student Record
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-4">
                <p class="mb-1 text-muted">You are about to permanently delete:</p>
                <p class="fw-bold fs-5 mb-3" id="deleteModalName"></p>
                <div class="alert alert-danger border-0 rounded-3 mb-0">
                    <i class="bi bi-exclamation-octagon-fill me-2"></i>
                    <strong>This cannot be undone.</strong> All registration data, documents, academic records,
                    payment history, and queries for this student will be permanently wiped.
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <span class="btn-label"><i class="bi bi-trash me-1"></i>Yes, Delete Permanently</span>
                    <span class="spinner-border spinner-border-sm d-none"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// PHP data for JS (heredoc allows interpolation)
$progressPieJson = json_encode([
    ['name' => 'Not Started',   'value' => max(0, $stats['total_users']    - $stats['personal_done'])],
    ['name' => 'Personal Done', 'value' => max(0, $stats['personal_done']  - $stats['academic_done'])],
    ['name' => 'Academic Done', 'value' => max(0, $stats['academic_done']  - $stats['docs_done'])],
    ['name' => 'Docs Uploaded', 'value' => max(0, $stats['docs_done']      - $stats['payments_done'])],
    ['name' => 'Payment Done',  'value' => $stats['payments_done']],
]);
$paymentDonutJson = json_encode([
    ['name' => 'Payment Done', 'value' => $stats['apps_with_pay']],
    ['name' => 'No Payment',   'value' => max(0, $stats['total_users'] - $stats['apps_with_pay'])],
]);
$deleteUrl = route('api.admin.delete-user');

$extraFoot = <<<JS
<!-- DataTables CSS (in foot to avoid layout) -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<!-- ECharts -->
<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
<script>
$(document).ready(function () {

    // ===== Application Progress Pie =====
    echarts.init(document.getElementById('progressPieChart')).setOption({
        tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
        legend: { orient: 'vertical', left: 'left', textStyle: { fontSize: 12 } },
        color: ['#adb5bd', '#4cc9f0', '#4361ee', '#f77f00', '#06d6a0'],
        series: [{
            name: 'Application Progress',
            type: 'pie',
            radius: '55%',
            center: ['62%', '50%'],
            data: {$progressPieJson},
            emphasis: {
                itemStyle: {
                    shadowBlur: 10,
                    shadowOffsetX: 0,
                    shadowColor: 'rgba(0,0,0,0.4)'
                }
            },
            itemStyle: { borderRadius: 5, borderWidth: 2, borderColor: '#fff' },
            label: { show: true, formatter: '{b}\\n{c}', fontSize: 11 }
        }]
    });

    // ===== Payment Status Donut =====
    echarts.init(document.getElementById('paymentDonutChart')).setOption({
        tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
        legend: { top: '5%', left: 'center', textStyle: { fontSize: 12 } },
        color: ['#06d6a0', '#e63946'],
        series: [{
            name: 'Payment Status',
            type: 'pie',
            radius: ['40%', '70%'],
            center: ['50%', '58%'],
            avoidLabelOverlap: false,
            label: { show: false, position: 'center' },
            emphasis: {
                label: { show: true, fontSize: 18, fontWeight: 'bold' }
            },
            labelLine: { show: false },
            data: {$paymentDonutJson}
        }]
    });

    // ===== DataTable =====
    $('#recentTable').DataTable({
        order: [[6, 'desc']],
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, -1], ['5', '10', '25', '50', 'All']],
        columnDefs: [
            { targets: 'no-sort', orderable: false }
        ],
        language: {
            search:         '<i class="bi bi-search me-1"></i>Search:',
            lengthMenu:     'Show _MENU_ entries',
            info:           'Showing _START_ to _END_ of _TOTAL_ students',
            infoEmpty:      'No students found',
            paginate: {
                previous: '&laquo;',
                next:     '&raquo;'
            }
        }
    });
});

// ===== Delete User =====
var deleteUserId = null;
var deleteModal  = new bootstrap.Modal(document.getElementById('deleteModal'));

document.querySelectorAll('.delete-user-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        deleteUserId = btn.dataset.uid;
        document.getElementById('deleteModalName').textContent =
            btn.dataset.name + '  (' + btn.dataset.appid + ')';
        deleteModal.show();
    });
});

document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
    if (!deleteUserId) return;
    var btn     = this;
    var label   = btn.querySelector('.btn-label');
    var spinner = btn.querySelector('.spinner-border');
    label.classList.add('d-none');
    spinner.classList.remove('d-none');
    btn.disabled = true;

    var fd = new FormData();
    fd.append('user_id', deleteUserId);

    fetch('{$deleteUrl}', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                deleteModal.hide();
                location.reload();
            } else {
                alert(res.message || 'Failed to delete user.');
                label.classList.remove('d-none');
                spinner.classList.add('d-none');
                btn.disabled = false;
            }
        })
        .catch(function () {
            alert('Network error. Please try again.');
            label.classList.remove('d-none');
            spinner.classList.add('d-none');
            btn.disabled = false;
        });
});
</script>
JS;

include BASE_PATH . '/admin/layouts/admin.php';
