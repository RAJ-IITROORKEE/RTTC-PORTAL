<?php
define('APP_INIT', true);
require_once __DIR__ . '/../../config/init.php';
SecurityHelper::requireAdminAuth();

$db = db();

// ── Document category stats (for pie chart) ──────────────────
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
        COUNT(*)                                                             AS total_entries
    FROM documents
")->fetch_assoc();

$totalEntries = (int)($statRow['total_entries'] ?? 0);

// ── All users with their document paths ──────────────────────
$rows = $db->query("
    SELECT
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
    LEFT JOIN documents        d ON d.user_id = u.id
    ORDER BY u.id ASC
");

$pageTitle  = 'Documents — Admin RTTC 2026';
$activePage = 'documents';
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">
        <i class="bi bi-file-earmark-check-fill me-2 text-primary"></i>Documents
    </h4>
    <span class="text-muted small">Total document entries: <strong><?= number_format($totalEntries) ?></strong></span>
</div>

<!-- ===== Stats row ===== -->
<div class="row g-3 mb-4">
    <?php
    $docCategories = [
        ['key' => 'photo',     'label' => 'Photos',            'icon' => 'person-square',              'color' => 'primary'],
        ['key' => 'signature', 'label' => 'Signatures',        'icon' => 'pen-fill',                   'color' => 'secondary'],
        ['key' => 'hslc',      'label' => 'HSLC Marksheets',   'icon' => 'file-earmark-text-fill',     'color' => 'info'],
        ['key' => 'hsslc',     'label' => 'HSSLC Marksheets',  'icon' => 'file-earmark-text-fill',     'color' => 'info'],
        ['key' => 'degree',    'label' => 'Degree Marksheets', 'icon' => 'mortarboard-fill',            'color' => 'warning'],
        ['key' => 'masters',   'label' => "Master's Marksheets",'icon'=> 'mortarboard-fill',            'color' => 'warning'],
        ['key' => 'caste',     'label' => 'Caste Certificates','icon' => 'file-earmark-medical-fill',  'color' => 'success'],
        ['key' => 'ews',       'label' => 'EWS Certificates',  'icon' => 'file-earmark-medical-fill',  'color' => 'success'],
        ['key' => 'pwd',       'label' => 'PWD Certificates',  'icon' => 'file-earmark-medical-fill',  'color' => 'danger'],
        ['key' => 'obc_ncl',   'label' => 'OBC-NCL Certs.',    'icon' => 'file-earmark-medical-fill',  'color' => 'danger'],
        ['key' => 'admit',     'label' => 'GUBEDCET Admits',   'icon' => 'card-heading',               'color' => 'purple'],
        ['key' => 'result',    'label' => 'GUBEDCET Results',  'icon' => 'file-earmark-bar-graph-fill','color' => 'purple'],
    ];
    foreach ($docCategories as $dc): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100 text-center py-3 px-2">
            <i class="bi bi-<?= $dc['icon'] ?> fs-2 mb-1 text-<?= $dc['color'] ?>"></i>
            <div class="fw-bold fs-5"><?= (int)($statRow[$dc['key']] ?? 0) ?></div>
            <div class="text-muted" style="font-size:.72rem;"><?= $dc['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ===== Pie Chart + DataTable ===== -->
<div class="row g-4 mb-4">
    <!-- Pie chart -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-3">
                <h6 class="fw-bold mb-0">
                    <i class="bi bi-pie-chart-fill me-2 text-primary"></i>Category Distribution
                </h6>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center py-2">
                <div id="pieChart" style="width:100%;height:300px;"></div>
            </div>
        </div>
    </div>
    <!-- Quick totals -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-3">
                <h6 class="fw-bold mb-0">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Upload Summary
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($docCategories as $dc): $cnt = (int)($statRow[$dc['key']] ?? 0); ?>
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-<?= $dc['icon'] ?> text-<?= $dc['color'] ?> fs-5 flex-shrink-0"></i>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted"><?= $dc['label'] ?></small>
                                    <small class="fw-bold"><?= $cnt ?> / <?= $totalEntries ?></small>
                                </div>
                                <div class="progress" style="height:5px;">
                                    <div class="progress-bar bg-<?= $dc['color'] ?>"
                                         style="width:<?= $totalEntries ? round($cnt / $totalEntries * 100) : 0 ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== Documents DataTable ===== -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0">
            <i class="bi bi-table me-2 text-primary"></i>All Student Documents
        </h6>
        <button id="csvExportBtn" class="btn btn-sm btn-outline-success">
            <i class="bi bi-filetype-csv me-1"></i>Export CSV
        </button>
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
                    <?php while ($row = $rows->fetch_assoc()):
                        $appId = 'RTTC-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT);
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
                        <td class="text-nowrap"><?= htmlspecialchars($row['full_name']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
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
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Build pie data in PHP
$pieJson = json_encode(array_map(fn($dc) => [
    'name'  => $dc['label'],
    'value' => (int)($statRow[$dc['key']] ?? 0),
], $docCategories));

$baseUrl = rtrim(BASE_URL, '/');

$extraFoot = <<<JS
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<!-- ECharts -->
<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
<script>
// ===== Pie Chart =====
(function () {
    var chart = echarts.init(document.getElementById('pieChart'));
    var data  = {$pieJson};
    chart.setOption({
        tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
        legend: { bottom: 0, type: 'scroll', textStyle: { fontSize: 11 } },
        series: [{
            type: 'pie',
            radius: ['38%', '70%'],
            center: ['50%', '44%'],
            avoidLabelOverlap: true,
            itemStyle: { borderRadius: 6, borderWidth: 2, borderColor: '#fff' },
            label: { show: false },
            emphasis: {
                label: { show: true, fontSize: 13, fontWeight: 'bold' }
            },
            data: data
        }]
    });
    window.addEventListener('resize', function () { chart.resize(); });
})();

// ===== DataTable =====
var docsTable;
$(document).ready(function () {
    docsTable = $('#docsTable').DataTable({
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, -1], ['5', '10', '25', '50', 'All']],
        scrollX: true,
        language: {
            search:     '<i class="bi bi-search me-1"></i>Search:',
            lengthMenu: 'Show _MENU_ entries',
            info:       'Showing _START_ to _END_ of _TOTAL_ students'
        }
    });
});

// ===== CSV Export with full document URLs =====
document.getElementById('csvExportBtn').addEventListener('click', function () {
    var rows = [];
    var headers = [
        'App ID','Name','Email',
        'Photo','Signature','HSLC','HSSLC','Degree','Masters',
        'Caste Cert','EWS Cert','PWD Cert','OBC-NCL Cert',
        'GUBEDCET Admit','GUBEDCET Result','Doc Status'
    ];
    rows.push(headers);

    document.querySelectorAll('#docsTable tbody tr').forEach(function (tr) {
        var cells = tr.querySelectorAll('td');
        var row   = [];
        cells.forEach(function (td, idx) {
            // For doc link columns (index 3–14): use data-export-url
            if (idx >= 3 && idx <= 14) {
                row.push(td.dataset.exportUrl || '');
            } else {
                row.push(td.innerText.trim());
            }
        });
        rows.push(row);
    });

    var csv = rows.map(function (r) {
        return r.map(function (cell) {
            var s = (cell + '').replace(/"/g, '""');
            return '"' + s + '"';
        }).join(',');
    }).join('\\r\\n');

    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    var url  = URL.createObjectURL(blob);
    var a    = document.createElement('a');
    a.href     = url;
    a.download = 'rttc_documents_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
});
</script>
JS;

include BASE_PATH . '/admin/layouts/admin.php';
