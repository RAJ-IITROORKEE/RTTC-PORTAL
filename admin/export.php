<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';
require_once ROOT_PATH . '/helpers/AdminExportHelper.php';

SecurityHelper::requireAdminAuth();
header('Cache-Control: no-store, private');

$db = db();
$columns = adminExportColumns();
$download = ($_GET['download'] ?? '') === 'csv';

$exportSql = "
    SELECT
        u.id AS user_id, u.username, u.email, u.phone, u.is_verified, u.created_at AS registered_at,
        p.firstname, p.middlename, p.lastname, p.dob, p.age, p.gender AS personal_gender,
        p.blood_group, p.religion, p.caste AS personal_category, p.ews, p.obc_ncl, p.pwd,
        p.fathersname, p.foccupation, p.fcontact, p.fqualifications,
        p.mothersname, p.moccupation, p.mcontact, p.mqualification,
        p.spousename, p.soccupation, p.scontact, p.squalification,
        p.permanent_address, p.present_address,
        p.emergency_contact, p.income,
        a.hslc_pass_year, a.hslc_board, a.hslc_institute, a.hslc_total_marks,
        a.hslc_obtained_marks, a.hslc_percentage, a.hslc_division, a.hslc_subjects,
        a.hsslc_pass_year, a.hsslc_board, a.hsslc_institute, a.hsslc_total_marks,
        a.hsslc_obtained_marks, a.hsslc_percentage, a.hsslc_division, a.hsslc_subjects,
        a.bachelor_pass_year, a.bachelor_board, a.bachelor_institute, a.bachelor_total_marks,
        a.bachelor_obtained_marks, a.bachelor_percentage, a.bachelor_division, a.bachelor_subjects,
        a.masters_pass_year, a.masters_board, a.masters_institute, a.masters_total_marks,
        a.masters_obtained_marks, a.masters_percentage, a.masters_division, a.masters_subjects,
        a.gu_registered, a.gu_reg_no, a.gu_reg_year, a.migrated, a.other_university,
        a.gubedcet_rollno, a.gubedcet_name, a.gubedcet_gender, a.gubedcet_category,
        a.gubedcet_booklet_series, a.gubedcet_marks, a.gubedcet_rank,
        a.gubedcet_correct, a.gubedcet_wrong, a.gubedcet_unattempted,
        a.academic_declaration,
        rp.current_step, rp.is_submitted,
        success_pay.razorpay_payment_id AS successful_payment_id,
        success_pay.razorpay_order_id AS successful_order_id,
        success_pay.amount AS successful_payment_amount,
        success_pay.created_at AS successful_payment_date,
        latest_pay.status AS latest_payment_status,
        latest_pay.razorpay_payment_id AS latest_payment_id,
        latest_pay.razorpay_order_id AS latest_order_id,
        latest_pay.amount AS latest_payment_amount,
        latest_pay.created_at AS latest_payment_date
    FROM users u
    LEFT JOIN personal_details p ON p.user_id = u.id
    LEFT JOIN academic_details a ON a.user_id = u.id
    LEFT JOIN registration_progress rp ON rp.user_id = u.id
    LEFT JOIN (
        SELECT paid.*
        FROM payment paid
        INNER JOIN (
            SELECT user_id, MAX(id) AS payment_id
            FROM payment
            WHERE status = 'success' AND razorpay_payment_id IS NOT NULL AND razorpay_payment_id <> ''
            GROUP BY user_id
        ) latest_success ON latest_success.payment_id = paid.id
    ) success_pay ON success_pay.user_id = u.id
    LEFT JOIN (
        SELECT latest.*
        FROM payment latest
        INNER JOIN (
            SELECT user_id, MAX(id) AS payment_id
            FROM payment
            GROUP BY user_id
        ) latest_record ON latest_record.payment_id = latest.id
    ) latest_pay ON latest_pay.user_id = u.id
";

if ($download) {
    $downloadResult = $db->query($exportSql . ' WHERE success_pay.id IS NOT NULL ORDER BY u.created_at ASC');

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="RTTC_2026_Successful_Payments_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');

    $fh = fopen('php://output', 'w');
    fputcsv($fh, array_values($columns));
    while ($row = $downloadResult->fetch_assoc()) {
        $exportRow = adminExportRow($row);
        fputcsv($fh, array_map('adminExportCsvValue', array_values($exportRow)));
    }
    fclose($fh);
    exit;
}

$result = $db->query($exportSql . ' ORDER BY u.created_at ASC');
$previewRows = [];
$stats = [
    'total' => 0,
    'successful' => 0,
    'not_successful' => 0,
    'submitted' => 0,
    'academic' => 0,
    'amount' => 0,
];

while ($row = $result->fetch_assoc()) {
    $exportRow = adminExportRow($row);
    $previewRows[] = $exportRow;
    $stats['total']++;
    if (adminExportHasSuccessfulPayment($row)) {
        $stats['successful']++;
        $stats['amount'] += (int) ($row['successful_payment_amount'] ?? 0);
    } else {
        $stats['not_successful']++;
    }
    if (!empty($row['is_submitted'])) $stats['submitted']++;
    if ((int) ($row['current_step'] ?? 0) >= 2) $stats['academic']++;
}

$pageTitle = 'Export Data - Admin RTTC 2026';
$activePage = 'export';
$breadcrumb = [['label' => 'Export Data']];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

ob_start();
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-download me-2 text-primary"></i>Export Data</h4>
        <p class="text-muted small mb-0">Review the complete portal data first. CSV download includes only students with successful payment.</p>
    </div>
    <a href="<?= route('admin.export', ['download' => 'csv']) ?>" class="btn btn-success">
        <i class="bi bi-filetype-csv me-1"></i>Download Successful Payments CSV
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Total Portal Users</div><div class="fs-3 fw-bold text-primary"><?= number_format($stats['total']) ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Successful Payments</div><div class="fs-3 fw-bold text-success"><?= number_format($stats['successful']) ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Submitted Applications</div><div class="fs-3 fw-bold text-info"><?= number_format($stats['submitted']) ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Amount Collected</div><div class="fs-3 fw-bold text-warning">₹<?= number_format($stats['amount'] / 100, 2) ?></div></div></div></div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="row g-2 align-items-center">
            <div class="col-lg-6">
                <label for="exportSearch" class="form-label small fw-semibold mb-1">Search preview</label>
                <input type="search" id="exportSearch" class="form-control" placeholder="Search ID, name, email, roll, payment status...">
            </div>
            <div class="col-sm-6 col-lg-3">
                <label for="exportSort" class="form-label small fw-semibold mb-1">Sort preview</label>
                <select id="exportSort" class="form-select">
                    <option value="portal_unique_id">Portal ID</option>
                    <option value="firstname">Name</option>
                    <option value="payment_status">Payment Status</option>
                    <option value="gubedcet_marks">GUBEDCET Marks</option>
                    <option value="registered_at">Registration Date</option>
                </select>
            </div>
            <div class="col-sm-6 col-lg-3">
                <label for="exportSortDirection" class="form-label small fw-semibold mb-1">Order</label>
                <select id="exportSortDirection" class="form-select">
                    <option value="asc">Ascending</option>
                    <option value="desc">Descending</option>
                </select>
            </div>
        </div>
        <div class="small text-muted mt-2">Preview rows: <span id="exportVisibleCount"><?= number_format($stats['total']) ?></span> of <?= number_format($stats['total']) ?></div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Complete Student Data Preview</span>
        <span class="small text-muted">Document files and links are excluded</span>
    </div>
    <div class="table-responsive" style="max-height: 620px; overflow: auto;">
        <table class="table table-sm table-hover align-middle mb-0" id="exportTable">
            <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                <tr>
                    <?php foreach ($columns as $key => $label): ?><th data-column="<?= $escape($key) ?>" class="text-nowrap"><?= $escape($label) ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($previewRows as $row): ?>
                <tr>
                    <?php foreach ($columns as $key => $label): ?>
                        <td class="text-nowrap <?= $key === 'payment_status' && $row[$key] === 'SUCCESS' ? 'text-success fw-semibold' : '' ?>"><?= nl2br($escape($row[$key] ?? '')) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$previewRows): ?><tr><td colspan="<?= count($columns) ?>" class="text-center text-muted py-4">No student data found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean();
$extraFoot = '<script>
(function () {
    const table = document.getElementById("exportTable");
    const body = table?.querySelector("tbody");
    const search = document.getElementById("exportSearch");
    const sort = document.getElementById("exportSort");
    const direction = document.getElementById("exportSortDirection");
    const count = document.getElementById("exportVisibleCount");
    if (!body || !search || !sort || !direction) return;

    const rows = Array.from(body.querySelectorAll("tr"));
    const columnIndex = {};
    table.querySelectorAll("thead th[data-column]").forEach((heading, index) => { columnIndex[heading.dataset.column] = index; });
    function render() {
        const term = search.value.trim().toLowerCase();
        const field = sort.value;
        const multiplier = direction.value === "desc" ? -1 : 1;
        const visible = rows.filter(row => !term || row.textContent.toLowerCase().includes(term));
        visible.sort((a, b) => (a.cells[columnIndex[field]]?.textContent || "").localeCompare(b.cells[columnIndex[field]]?.textContent || "", undefined, {numeric: true, sensitivity: "base"}) * multiplier);
        visible.forEach(row => { row.hidden = false; body.appendChild(row); });
        rows.forEach(row => { if (!visible.includes(row)) row.hidden = true; });
        if (count) count.textContent = visible.length.toLocaleString();
    }
    search.addEventListener("input", render);
    sort.addEventListener("change", render);
    direction.addEventListener("change", render);
    render();
})();
</script>';
include BASE_PATH . '/admin/layouts/admin.php';
