<?php
define('APP_INIT', true);
require_once __DIR__ . '/../../config/init.php';
SecurityHelper::requireAdminAuth();

$db = db();

// ── All document counts + status summary ──────────────────────
$statRow = $db->query("
    SELECT
        SUM(photo              IS NOT NULL AND photo              != '') AS photo,
        SUM(signature          IS NOT NULL AND signature          != '') AS signature,
        SUM(hslc_marksheet     IS NOT NULL AND hslc_marksheet     != '') AS hslc,
        SUM(hsslc_marksheet    IS NOT NULL AND hsslc_marksheet    != '') AS hsslc,
        SUM(degree_marksheet   IS NOT NULL AND degree_marksheet   != '') AS degree,
        SUM(masters_marksheet  IS NOT NULL AND masters_marksheet  != '') AS masters,
        SUM(caste_cert         IS NOT NULL AND caste_cert         != '') AS caste,
        SUM(ews_cert           IS NOT NULL AND ews_cert           != '') AS ews,
        SUM(pwd_cert           IS NOT NULL AND pwd_cert           != '') AS pwd,
        SUM(obc_ncl_cert       IS NOT NULL AND obc_ncl_cert       != '') AS obc_ncl,
        SUM(gubedcet_admit_card   IS NOT NULL AND gubedcet_admit_card   != '') AS admit,
        SUM(gubedcet_result_sheet IS NOT NULL AND gubedcet_result_sheet != '') AS result,
        COUNT(*)                                                              AS total_entries,
        SUM(status = 'approved')                                              AS approved_count,
        SUM(status = 'pending')                                               AS pending_count,
        SUM(status = 'rejected')                                              AS rejected_count
    FROM documents
")->fetch_assoc();

$totalEntries  = (int)($statRow['total_entries']   ?? 0);
$totalFiles    = (int)($statRow['photo']  ?? 0) + (int)($statRow['signature'] ?? 0)
               + (int)($statRow['hslc']   ?? 0) + (int)($statRow['hsslc']    ?? 0)
               + (int)($statRow['degree'] ?? 0) + (int)($statRow['masters']  ?? 0)
               + (int)($statRow['caste']  ?? 0) + (int)($statRow['ews']      ?? 0)
               + (int)($statRow['pwd']    ?? 0) + (int)($statRow['obc_ncl']  ?? 0)
               + (int)($statRow['admit']  ?? 0) + (int)($statRow['result']   ?? 0);
$approved      = (int)($statRow['approved_count']  ?? 0);
$pending       = (int)($statRow['pending_count']   ?? 0);
$rejected      = (int)($statRow['rejected_count']  ?? 0);

// ── Paginated document rows ──────────────────────────────────
$search = trim((string)($_GET['search'] ?? ''));
$pageNum = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$documentWhere = '';
$documentParams = [];
$documentTypes = '';
if ($search !== '') {
    $documentWhere = "WHERE u.username LIKE ?
                         OR u.email LIKE ?
                         OR CAST(u.id AS CHAR) LIKE ?
                         OR p.firstname LIKE ?
                         OR p.lastname LIKE ?";
    $searchLike = '%' . $search . '%';
    $documentParams = [$searchLike, $searchLike, $searchLike, $searchLike, $searchLike];
    $documentTypes = 'sssss';
}

$documentSelect = "SELECT
        u.id,
        u.username,
        u.email,
        COALESCE(NULLIF(TRIM(CONCAT_WS(' ',
            p.firstname, p.middlename, p.lastname
        )), ''), u.username) AS full_name,
        d.photo, d.signature,
        d.hslc_marksheet, d.hsslc_marksheet, d.degree_marksheet, d.masters_marksheet,
        d.caste_cert, d.ews_cert, d.pwd_cert, d.obc_ncl_cert,
        d.gubedcet_admit_card, d.gubedcet_result_sheet,
        d.status AS doc_status
    FROM users u
    LEFT JOIN personal_details p ON p.user_id = u.id
    LEFT JOIN documents        d ON d.user_id = u.id";

$countStmt = $db->prepare("SELECT COUNT(*) FROM users u
    LEFT JOIN personal_details p ON p.user_id = u.id
    LEFT JOIN documents d ON d.user_id = u.id
    $documentWhere");
$documentTotal = 0;
if ($countStmt) {
    if ($documentTypes !== '') $countStmt->bind_param($documentTypes, ...$documentParams);
    $countStmt->execute();
    $documentTotal = (int)$countStmt->get_result()->fetch_row()[0];
    $countStmt->close();
}
$totalPages = max(1, (int)ceil($documentTotal / $perPage));
if ($documentTotal > 0) $pageNum = min($pageNum, $totalPages);
$offset = ($pageNum - 1) * $perPage;

$rowsStmt = $db->prepare($documentSelect . " $documentWhere ORDER BY u.id ASC LIMIT ? OFFSET ?");
$rows = false;
if ($rowsStmt) {
    $rowsParams = array_merge($documentParams, [$perPage, $offset]);
    $rowsStmt->bind_param($documentTypes . 'ii', ...$rowsParams);
    $rowsStmt->execute();
    $rows = $rowsStmt->get_result();
    $rowsStmt->close();
}

// Export always reads every row, independently of the current 20-row page.
if (($_GET['export'] ?? '') === 'csv') {
    $exportResult = $db->query($documentSelect . ' ORDER BY u.id ASC');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="RTTC_Documents_All_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');

    $csvColumns = [
        'App ID', 'Name', 'Email', 'Photo', 'Signature', 'HSLC', 'HSSLC', 'Degree', 'Masters',
        'Caste Cert', 'EWS Cert', 'PWD Cert', 'OBC-NCL Cert', 'GUBEDCET Admit', 'GUBEDCET Result', 'Doc Status'
    ];
    $docCols = [
        'photo', 'signature', 'hslc_marksheet', 'hsslc_marksheet', 'degree_marksheet', 'masters_marksheet',
        'caste_cert', 'ews_cert', 'pwd_cert', 'obc_ncl_cert', 'gubedcet_admit_card', 'gubedcet_result_sheet'
    ];
    $fh = fopen('php://output', 'w');
    fputcsv($fh, $csvColumns);
    if ($exportResult) {
        while ($exportRow = $exportResult->fetch_assoc()) {
            $csvRow = [
                'RTTC-' . str_pad((string)$exportRow['id'], 5, '0', STR_PAD_LEFT),
                trim((string)($exportRow['full_name'] ?? $exportRow['username'] ?? '')) ?: 'Applicant',
                (string)($exportRow['email'] ?? ''),
            ];
            foreach ($docCols as $docCol) {
                $csvRow[] = !empty($exportRow[$docCol]) ? BASE_URL . '/' . $exportRow[$docCol] : '';
            }
            $csvRow[] = ucfirst($exportRow['doc_status'] ?? 'pending');
            fputcsv($fh, $csvRow);
        }
    }
    fclose($fh);
    exit;
}

$pageTitle  = 'Documents — Admin RTTC 2026';
$activePage = 'documents';
$queryParams = 'search=' . urlencode($search);
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">
        <i class="bi bi-file-earmark-check-fill me-2 text-primary"></i>Documents
    </h4>
    <span class="text-muted small">Total document entries: <strong><?= number_format($totalEntries) ?></strong></span>
</div>

<!-- ===== 5 Grouped Stats ===== -->
<div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4">

    <!-- Total Entries -->
    <div class="col">
        <div class="card border-0 shadow-sm h-100" style="min-height:100px;">
            <div class="card-body d-flex align-items-center gap-3 p-3">
                <div class="flex-shrink-0 rounded-3 d-flex align-items-center justify-content-center bg-primary bg-opacity-10"
                     style="width:48px;height:48px;min-width:48px;">
                    <i class="bi bi-people-fill fs-4 text-primary"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="fw-bold fs-4 lh-1 mb-1"><?= number_format($totalEntries) ?></div>
                    <div class="small fw-semibold text-truncate">Students w/ Docs</div>
                    <div class="text-muted" style="font-size:.7rem;">Document rows in DB</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Files Uploaded -->
    <div class="col">
        <div class="card border-0 shadow-sm h-100" style="min-height:100px;">
            <div class="card-body d-flex align-items-center gap-3 p-3">
                <div class="flex-shrink-0 rounded-3 d-flex align-items-center justify-content-center bg-info bg-opacity-10"
                     style="width:48px;height:48px;min-width:48px;">
                    <i class="bi bi-files fs-4 text-info"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="fw-bold fs-4 lh-1 mb-1"><?= number_format($totalFiles) ?></div>
                    <div class="small fw-semibold text-truncate">Total Files</div>
                    <div class="text-muted" style="font-size:.7rem;">All uploads combined</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approved -->
    <div class="col">
        <div class="card border-0 shadow-sm h-100" style="min-height:100px;">
            <div class="card-body d-flex align-items-center gap-3 p-3">
                <div class="flex-shrink-0 rounded-3 d-flex align-items-center justify-content-center bg-success bg-opacity-10"
                     style="width:48px;height:48px;min-width:48px;">
                    <i class="bi bi-patch-check-fill fs-4 text-success"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="fw-bold fs-4 lh-1 mb-1"><?= number_format($approved) ?></div>
                    <div class="small fw-semibold text-truncate">Approved</div>
                    <div class="text-muted" style="font-size:.7rem;">Documents verified</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending -->
    <div class="col">
        <div class="card border-0 shadow-sm h-100" style="min-height:100px;">
            <div class="card-body d-flex align-items-center gap-3 p-3">
                <div class="flex-shrink-0 rounded-3 d-flex align-items-center justify-content-center bg-warning bg-opacity-10"
                     style="width:48px;height:48px;min-width:48px;">
                    <i class="bi bi-hourglass-split fs-4 text-warning"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="fw-bold fs-4 lh-1 mb-1"><?= number_format($pending) ?></div>
                    <div class="small fw-semibold text-truncate">Pending Review</div>
                    <div class="text-muted" style="font-size:.7rem;">Awaiting verification</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rejected -->
    <div class="col">
        <div class="card border-0 shadow-sm h-100" style="min-height:100px;">
            <div class="card-body d-flex align-items-center gap-3 p-3">
                <div class="flex-shrink-0 rounded-3 d-flex align-items-center justify-content-center bg-danger bg-opacity-10"
                     style="width:48px;height:48px;min-width:48px;">
                    <i class="bi bi-x-circle-fill fs-4 text-danger"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="fw-bold fs-4 lh-1 mb-1"><?= number_format($rejected) ?></div>
                    <div class="small fw-semibold text-truncate">Rejected</div>
                    <div class="text-muted" style="font-size:.7rem;">Docs rejected</div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ===== Category Certificates Distribution Pie ===== -->
<div class="row g-4 mb-4 justify-content-center">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-3">
                <h5 class="card-title fw-bold mb-0">
                    <i class="bi bi-pie-chart-fill me-2 text-primary"></i>Category Certificate Distribution
                </h5>
                <p class="text-muted small mb-0 mt-1">Caste, EWS, PWD &amp; OBC-NCL certificates submitted</p>
            </div>
            <div class="card-body py-2">
                <div id="certPieChart" style="width:100%;height:320px;"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-3">
                <h5 class="card-title fw-bold mb-0">
                    <i class="bi bi-bar-chart-fill me-2 text-primary"></i>Document Status Overview
                </h5>
                <p class="text-muted small mb-0 mt-1">Approval status breakdown across all submitted documents</p>
            </div>
            <div class="card-body py-2">
                <div id="statusDonutChart" style="width:100%;height:320px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- ===== Documents DataTable ===== -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h6 class="fw-bold mb-0">
            <i class="bi bi-table me-2 text-primary"></i>All Student Documents
        </h6>
        <a href="<?= route('admin.documents', ['export' => 'csv']) ?>" class="btn btn-sm btn-outline-success">
            <i class="bi bi-filetype-csv me-1"></i>Export All CSV
        </a>
    </div>
    <div class="card-body border-top py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-8 col-lg-6">
                <label for="documentSearch" class="form-label small fw-semibold mb-1">Search documents</label>
                <input type="search" id="documentSearch" name="search" class="form-control" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search ID, name or email...">
            </div>
            <div class="col-md-2 col-lg-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Search</button>
            </div>
            <div class="col-md-2 col-lg-2">
                <a href="<?= route('admin.documents') ?>" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
            <div class="col-lg-2 text-lg-end">
                <span class="small text-muted d-block mt-2 mt-lg-0">Showing <?= $documentTotal ? (($pageNum - 1) * $perPage) + 1 : 0 ?>–<?= min($documentTotal, $pageNum * $perPage) ?> of <?= number_format($documentTotal) ?></span>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="overflow-x:auto;">
            <table id="docsTable" class="table table-hover table-sm align-middle mb-0 nowrap" style="font-size:.8rem;min-width:1400px;">
                <thead class="table-light">
                    <tr>
                        <th>App ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Photo</th>
                        <th>Signature</th>
                        <th>HSLC</th>
                        <th>HSSLC</th>
                        <th>Degree</th>
                        <th>Masters</th>
                        <th>Caste</th>
                        <th>EWS</th>
                        <th>PWD</th>
                        <th>OBC-NCL</th>
                        <th>Admit Card</th>
                        <th>GUBEDCET Result</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows): while ($row = $rows->fetch_assoc()):
                        $appId = 'RTTC-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT);
                        $displayName = trim((string)($row['full_name'] ?? $row['username'] ?? '')) ?: 'Applicant';
                        $displayEmail = (string)($row['email'] ?? '');
                        $docCols = [
                            'photo', 'signature',
                            'hslc_marksheet', 'hsslc_marksheet', 'degree_marksheet', 'masters_marksheet',
                            'caste_cert', 'ews_cert', 'pwd_cert', 'obc_ncl_cert',
                            'gubedcet_admit_card', 'gubedcet_result_sheet',
                        ];
                        $ds = $row['doc_status'] ?? 'pending';
                        $dsBadge = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$ds] ?? 'secondary';
                    ?>
                    <tr>
                        <td class="font-monospace fw-semibold"><?= $appId ?></td>
                        <td class="text-nowrap">
                            <span class="admin-document-user">
                            <?php if (!empty($row['photo'])):
                                $profileUrl = BASE_URL . '/' . $row['photo'];
                            ?>
                                <a href="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="View profile photo">
                                    <img src="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Profile photo of <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>" class="admin-document-profile-photo" width="28" height="28">
                                </a>
                            <?php else: ?>
                                <span class="admin-document-profile-placeholder" title="Profile photo not uploaded"><i class="bi bi-person"></i></span>
                            <?php endif; ?>
                                <span><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></span>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($displayEmail, ENT_QUOTES, 'UTF-8') ?></td>
                        <?php foreach ($docCols as $dcol): $url = !empty($row[$dcol]) ? BASE_URL . '/' . $row[$dcol] : ''; ?>
                        <td class="text-center"
                            data-export-url="<?= htmlspecialchars($url) ?>">
                            <?php if ($url): ?>
                                <a href="<?= htmlspecialchars($url) ?>" target="_blank"
                                   class="btn btn-xs btn-success py-0 px-1" style="font-size:.7rem;"
                                   title="View document">
                                    <i class="bi bi-eye"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-muted" title="Not uploaded">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <td>
                            <span class="badge bg-<?= $dsBadge ?>" style="font-size:.68rem;"><?= ucfirst($ds) ?></span>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                    <?php if ($documentTotal === 0): ?>
                        <tr><td colspan="16" class="text-center text-muted py-5"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No document records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-white">
        <nav aria-label="Documents pagination">
            <ul class="pagination pagination-sm flex-wrap justify-content-center mb-0">
                <li class="page-item <?= $pageNum <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= $queryParams ?>&page=<?= max(1, $pageNum - 1) ?>" aria-label="Previous">&laquo;</a>
                </li>
                <?php
                $startPage = max(1, $pageNum - 2);
                $endPage = min($totalPages, $pageNum + 2);
                if ($startPage > 1):
                ?>
                    <li class="page-item"><a class="page-link" href="?<?= $queryParams ?>&page=1">1</a></li>
                    <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                <?php endif; ?>
                <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                    <li class="page-item <?= $p === $pageNum ? 'active' : '' ?>"><a class="page-link" href="?<?= $queryParams ?>&page=<?= $p ?>"><?= $p ?></a></li>
                <?php endfor; ?>
                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                    <li class="page-item"><a class="page-link" href="?<?= $queryParams ?>&page=<?= $totalPages ?>"><?= $totalPages ?></a></li>
                <?php endif; ?>
                <li class="page-item <?= $pageNum >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= $queryParams ?>&page=<?= min($totalPages, $pageNum + 1) ?>" aria-label="Next">&raquo;</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();

// Category certs pie data
$certPieJson = json_encode([
    ['name' => 'Caste Certificate', 'value' => (int)($statRow['caste']   ?? 0)],
    ['name' => 'EWS Certificate',   'value' => (int)($statRow['ews']     ?? 0)],
    ['name' => 'PWD Certificate',   'value' => (int)($statRow['pwd']     ?? 0)],
    ['name' => 'OBC-NCL Cert.',     'value' => (int)($statRow['obc_ncl'] ?? 0)],
]);

// Status donut data
$statusDonutJson = json_encode([
    ['name' => 'Approved', 'value' => $approved],
    ['name' => 'Pending',  'value' => $pending],
    ['name' => 'Rejected', 'value' => $rejected],
]);

$extraFoot = <<<JS
<!-- ECharts -->
<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
<script>
// ===== Category Certificates Pie Chart =====
echarts.init(document.getElementById('certPieChart')).setOption({
    tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
    legend: { orient: 'vertical', left: 'left', textStyle: { fontSize: 12 } },
    color: ['#4361ee', '#06d6a0', '#f77f00', '#e63946'],
    series: [{
        name: 'Category Certificates',
        type: 'pie',
        radius: '55%',
        center: ['62%', '50%'],
        data: {$certPieJson},
        emphasis: {
            itemStyle: { shadowBlur: 10, shadowOffsetX: 0, shadowColor: 'rgba(0,0,0,0.4)' }
        },
        itemStyle: { borderRadius: 5, borderWidth: 2, borderColor: '#fff' },
        label: { show: true, formatter: '{b}\\n{c}', fontSize: 11 }
    }]
});

// ===== Document Status Donut =====
echarts.init(document.getElementById('statusDonutChart')).setOption({
    tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
    legend: { top: '5%', left: 'center', textStyle: { fontSize: 12 } },
    color: ['#06d6a0', '#f4a261', '#e63946'],
    series: [{
        name: 'Document Status',
        type: 'pie',
        radius: ['40%', '70%'],
        center: ['50%', '58%'],
        avoidLabelOverlap: false,
        label: { show: false, position: 'center' },
        emphasis: { label: { show: true, fontSize: 18, fontWeight: 'bold' } },
        labelLine: { show: false },
        data: {$statusDonutJson}
    }]
});

</script>
JS;

include BASE_PATH . '/admin/layouts/admin.php';
