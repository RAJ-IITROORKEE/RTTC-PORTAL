<?php
define('APP_INIT', true);
require_once __DIR__ . '/../../config/init.php';

SecurityHelper::requireAdminAuth();
header('Cache-Control: no-store, private');

$search = trim((string) ($_GET['search'] ?? ''));
$gender = trim((string) ($_GET['gender'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$page = max(1, min(100000, (int) ($_GET['page'] ?? 1)));
$sort = trim((string) ($_GET['sort'] ?? 'serial_no'));
$direction = trim((string) ($_GET['direction'] ?? 'asc'));

try {
    $result = (new GubedcetMeritListRepository(ROOT_PATH . '/final_list/GUBEDCET 2026 FINAL LIST.csv'))
        ->browse($search, $gender, $category, $page, 10, $sort, $direction);
    $loadError = '';
} catch (Throwable $exception) {
    $result = [
        'rows' => [], 'total' => 0, 'page' => 1, 'per_page' => 10,
        'total_pages' => 1,
        'stats' => ['total_students' => 0, 'average_marks' => null, 'highest_marks' => null, 'lowest_marks' => null, 'gender' => [], 'category' => []],
        'filters' => ['genders' => [], 'categories' => []],
    ];
    $loadError = 'The final merit list is temporarily unavailable.';
}

$stats = $result['stats'];
$pageTitle = 'Final Merit List - Admin RTTC 2026';
$activePage = 'final-merit-list';
$breadcrumb = [['label' => 'Final Merit List']];

$query = function (int $pageNumber) use ($search, $gender, $category, $sort, $direction): string {
    return route('admin.final-merit-list', [
        'search' => $search,
        'gender' => $gender,
        'category' => $category,
        'sort' => $sort,
        'direction' => $direction,
        'page' => $pageNumber,
    ]);
};

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$from = $result['total'] > 0 ? (($result['page'] - 1) * $result['per_page']) + 1 : 0;
$to = min($result['total'], $result['page'] * $result['per_page']);

ob_start();
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-database-fill-check me-2 text-primary"></i>GUBEDCET Final Merit List</h4>
        <p class="text-muted small mb-0">Read-only official GUBEDCET 2026 final-merit information. Search is available across every CSV field.</p>
    </div>
    <span class="badge text-bg-light border"><i class="bi bi-shield-lock me-1"></i>Admin only</span>
</div>

<?php if ($loadError): ?>
    <div class="alert alert-danger"><?= $escape($loadError) ?></div>
<?php else: ?>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Total Students</div><div class="fs-3 fw-bold text-primary"><?= number_format($stats['total_students']) ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Average Marks</div><div class="fs-3 fw-bold text-success"><?= $stats['average_marks'] === null ? 'N/A' : $escape(number_format($stats['average_marks'], 2)) ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Highest Marks</div><div class="fs-3 fw-bold text-info"><?= $stats['highest_marks'] === null ? 'N/A' : $escape(number_format($stats['highest_marks'], 2)) ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Categories</div><div class="fs-3 fw-bold text-warning"><?= count($stats['category']) ?></div></div></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-5"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white fw-semibold">Gender Distribution</div><div class="card-body"><div id="genderChart" style="height:280px"></div></div></div></div>
    <div class="col-lg-7"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white fw-semibold">Category Distribution</div><div class="card-body"><div id="categoryChart" style="height:280px"></div></div></div></div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-lg-4">
                <label class="form-label small fw-semibold">Search all fields</label>
                <input type="search" name="search" class="form-control" value="<?= $escape($search) ?>" placeholder="Name, roll, marks, rank, booklet, category...">
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small fw-semibold">Gender</label>
                <select name="gender" class="form-select">
                    <option value="">All genders</option>
                    <?php foreach ($result['filters']['genders'] as $option): ?>
                        <option value="<?= $escape($option) ?>" <?= strcasecmp($gender, $option) === 0 ? 'selected' : '' ?>><?= $escape($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small fw-semibold">Category</label>
                <select name="category" class="form-select">
                    <option value="">All categories</option>
                    <?php foreach ($result['filters']['categories'] as $option): ?>
                        <option value="<?= $escape($option) ?>" <?= strcasecmp($category, $option) === 0 ? 'selected' : '' ?>><?= $escape($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small fw-semibold">Sort by</label>
                <select name="sort" class="form-select">
                    <?php foreach (['serial_no' => 'Serial No.', 'roll_no' => 'Roll No.', 'name' => 'Name', 'total_marks' => 'Total Marks', 'rank' => 'Rank'] as $sortKey => $sortLabel): ?>
                        <option value="<?= $sortKey ?>" <?= $sort === $sortKey ? 'selected' : '' ?>><?= $sortLabel ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-1">
                <label class="form-label small fw-semibold">Order</label>
                <select name="direction" class="form-select">
                    <option value="asc" <?= strtolower($direction) === 'asc' ? 'selected' : '' ?>>A-Z / Low</option>
                    <option value="desc" <?= strtolower($direction) === 'desc' ? 'selected' : '' ?>>Z-A / High</option>
                </select>
            </div>
            <div class="col-sm-6 col-lg-1"><button class="btn btn-primary w-100" type="submit" title="Search and sort"><i class="bi bi-search"></i></button></div>
            <div class="col-sm-6 col-lg-1"><a class="btn btn-outline-secondary w-100" href="<?= route('admin.final-merit-list') ?>">Reset</a></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="small text-muted">Showing <?= $from ?>–<?= $to ?> of <?= number_format($result['total']) ?> matching records</span>
        <span class="small text-muted">10 entries per page</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Roll No.</th><th>Name</th><th>Gender</th><th>Category</th><th>Booklet / Status</th><th>Correct</th><th>Wrong</th><th>Total</th><th>Rank</th></tr></thead>
            <tbody>
            <?php foreach ($result['rows'] as $student): ?>
                <tr>
                    <td class="font-monospace fw-semibold"><?= $escape($student['roll_no']) ?></td>
                    <td><?= $escape($student['name']) ?></td>
                    <td><?= $escape($student['gender']) ?></td>
                    <td><span class="badge text-bg-light border"><?= $escape($student['category']) ?></span></td>
                    <td><?= $escape($student['booklet_series']) ?></td>
                    <td><?= $escape($student['correct_marks']) ?></td>
                    <td><?= $escape($student['wrong_marks']) ?></td>
                    <td class="fw-semibold"><?= $escape($student['total_marks']) ?></td>
                    <td><?= $escape($student['rank']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$result['rows']): ?><tr><td colspan="9" class="text-center text-muted py-4">No matching records found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($result['total_pages'] > 1): ?>
    <div class="card-footer bg-white"><nav aria-label="Final merit list pages"><ul class="pagination pagination-sm mb-0 justify-content-center flex-wrap">
        <?php if ($result['page'] > 1): ?><li class="page-item"><a class="page-link" href="<?= $query($result['page'] - 1) ?>">Previous</a></li><?php endif; ?>
        <?php for ($number = max(1, $result['page'] - 2); $number <= min($result['total_pages'], $result['page'] + 2); $number++): ?><li class="page-item <?= $number === $result['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= $query($number) ?>"><?= $number ?></a></li><?php endfor; ?>
        <?php if ($result['page'] < $result['total_pages']): ?><li class="page-item"><a class="page-link" href="<?= $query($result['page'] + 1) ?>">Next</a></li><?php endif; ?>
    </ul></nav></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$genderChart = [];
foreach ($stats['gender'] as $name => $value) $genderChart[] = ['name' => $name, 'value' => $value];
$categoryChart = [];
foreach ($stats['category'] as $name => $value) $categoryChart[] = ['name' => $name, 'value' => $value];
$extraFoot = '<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script><script>
const chartData = ' . json_encode(['gender' => $genderChart, 'category' => $categoryChart], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';
function renderPie(id, data) { const el = document.getElementById(id); if (!el || !window.echarts) return; const chart = echarts.init(el); chart.setOption({tooltip:{trigger:"item"},legend:{type:"scroll",bottom:0},series:[{type:"pie",radius:["35%","68%"],data:data,label:{formatter:"{b}: {d}%"}}]}); window.addEventListener("resize", () => chart.resize()); }
renderPie("genderChart", chartData.gender); renderPie("categoryChart", chartData.category);
</script>';
$content = ob_get_clean();
include BASE_PATH . '/admin/layouts/admin.php';
