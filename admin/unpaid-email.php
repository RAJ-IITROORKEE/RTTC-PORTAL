<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';
SecurityHelper::requireAdminAuth();

$db = db();
$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = ($_GET['status'] ?? 'not_sent') === 'sent' ? 'sent' : 'not_sent';
$pageNum = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$effectiveStepSql = "CASE WHEN EXISTS (
    SELECT 1 FROM payment payment_status
    WHERE payment_status.user_id = u.id AND payment_status.status = 'success'
) THEN 4 ELSE LEAST(COALESCE(rp.current_step, 0), 3) END";

$baseWhere = "WHERE u.is_active = 1
              AND NOT EXISTS (
                  SELECT 1 FROM payment unpaid_payment
                  WHERE unpaid_payment.user_id = u.id AND unpaid_payment.status = 'success'
              )";
$params = [];
$types = '';

if ($search !== '') {
    $baseWhere .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ?
                        OR pd.firstname LIKE ? OR pd.lastname LIKE ?)";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like, $like];
    $types = 'sssss';
}

$statusWhere = $statusFilter === 'sent'
    ? " AND email_log.status = 'sent'"
    : " AND (email_log.id IS NULL OR email_log.status <> 'sent')";

$stats = ['total' => 0, 'sent' => 0, 'not_sent' => 0];
$statsStmt = $db->query(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN email_log.status = 'sent' THEN 1 ELSE 0 END), 0) AS sent
     FROM users u
     LEFT JOIN unpaid_email_log email_log ON email_log.user_id = u.id
     WHERE u.is_active = 1
       AND NOT EXISTS (
           SELECT 1 FROM payment unpaid_payment
           WHERE unpaid_payment.user_id = u.id AND unpaid_payment.status = 'success'
       )"
);
if ($statsStmt) {
    $statsRow = $statsStmt->fetch_assoc();
    $stats['total'] = (int)($statsRow['total'] ?? 0);
    $stats['sent'] = (int)($statsRow['sent'] ?? 0);
    $stats['not_sent'] = max(0, $stats['total'] - $stats['sent']);
}

$countSql = "SELECT COUNT(*)
             FROM users u
             LEFT JOIN personal_details pd ON pd.user_id = u.id
             LEFT JOIN unpaid_email_log email_log ON email_log.user_id = u.id
             $baseWhere $statusWhere";
$countStmt = $db->prepare($countSql);
$total = 0;
if ($countStmt) {
    if ($types !== '') $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_row()[0];
    $countStmt->close();
}

$totalPages = max(1, (int)ceil($total / $perPage));
if ($total > 0) $pageNum = min($pageNum, $totalPages);
$offset = ($pageNum - 1) * $perPage;

$rows = null;
$sql = "SELECT u.id, u.username, u.email, u.phone,
               COALESCE(NULLIF(TRIM(CONCAT_WS(' ', pd.firstname, NULLIF(pd.middlename, ''), pd.lastname)), ''), u.username) AS name,
               COALESCE(pd.gender, '') AS gender,
               {$effectiveStepSql} AS current_step,
               COALESCE(email_log.status, 'not_sent') AS email_status,
               email_log.sent_at
        FROM users u
        LEFT JOIN personal_details pd ON pd.user_id = u.id
        LEFT JOIN registration_progress rp ON rp.user_id = u.id
        LEFT JOIN unpaid_email_log email_log ON email_log.user_id = u.id
        $baseWhere $statusWhere
        ORDER BY u.created_at DESC, u.id DESC
        LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
if ($stmt) {
    $allParams = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($types . 'ii', ...$allParams);
    $stmt->execute();
        $rows = $stmt->get_result();
    $stmt->close();
}

$stepLabels = ['Not Started', 'Personal', 'Academic', 'Docs'];
$stepColors = ['secondary', 'info', 'warning', 'primary'];
$queryParams = 'search=' . urlencode($search) . '&status=' . urlencode($statusFilter);
$pageTitle = 'Email Unpaid - Admin RTTC 2026';
$activePage = 'unpaid-email';
$breadcrumb = [['label' => 'Email Unpaid']];
ob_start();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-envelope-exclamation-fill me-2 text-primary"></i>Email Unpaid</h4>
        <p class="text-muted small mb-0">Reach active applicants who do not yet have a successful payment.</p>
    </div>
    <a href="<?= route('admin.unpaid-email', ['search' => $search, 'status' => $statusFilter, 'page' => $pageNum]) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger-subtle text-danger"><i class="bi bi-person-exclamation fs-4"></i></div>
                <div><div class="text-muted small">Total Unpaid Candidates</div><div class="stat-value fs-3 fw-bold"><?= $stats['total'] ?></div></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-envelope fs-4"></i></div>
                <div><div class="text-muted small">Unpaid / Not Sent</div><div class="stat-value fs-3 fw-bold"><?= $stats['not_sent'] ?></div></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-envelope-check fs-4"></i></div>
                <div><div class="text-muted small">Unpaid / Sent</div><div class="stat-value fs-3 fw-bold"><?= $stats['sent'] ?></div></div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-6 col-lg-5">
                <label class="form-label" for="unpaidSearch">Search applicants</label>
                <input type="search" id="unpaidSearch" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Search by RTTC ID, name, email or phone...">
            </div>
            <div class="col-md-4 col-lg-4">
                <label class="form-label" for="emailStatus">Sort by email status</label>
                <select id="emailStatus" name="status" class="form-select">
                    <option value="not_sent" <?= $statusFilter === 'not_sent' ? 'selected' : '' ?>>Unpaid / Not Sent</option>
                    <option value="sent" <?= $statusFilter === 'sent' ? 'selected' : '' ?>>Unpaid / Sent</option>
                </select>
            </div>
            <div class="col-md-2 col-lg-1">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i><span class="d-md-none ms-1">Filter</span></button>
            </div>
            <div class="col-md-2 col-lg-2">
                <a href="<?= route('admin.unpaid-email') ?>" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#unpaidEmailModal">
            <i class="bi bi-send-fill me-1"></i>Send Email to Unpaid
        </button>
        <small class="text-muted">
            <?php if ($total > 0): ?>Showing <?= (($pageNum - 1) * $perPage) + 1 ?>–<?= min($total, $pageNum * $perPage) ?> of <?= $total ?> applicants<?php else: ?>No applicants in this view<?php endif; ?>
        </small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive admin-table-wrap">
            <table class="table table-hover align-middle mb-0 admin-data-table">
                <thead class="table-light">
                    <tr>
                        <th>RTTC ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Gender</th><th>Step</th><th>Emailed</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($rows): while ($row = $rows->fetch_assoc()):
                    $appId = 'RTTC-' . str_pad((string)$row['id'], 5, '0', STR_PAD_LEFT);
                    $displayName = trim((string)($row['name'] ?? $row['username'] ?? '')) ?: 'Applicant';
                    $step = min(3, max(0, (int)($row['current_step'] ?? 0)));
                    $isSent = ($row['email_status'] ?? '') === 'sent';
                ?>
                    <tr>
                        <td class="font-monospace small fw-semibold"><?= $appId ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['phone']) ?></td>
                        <td><?= htmlspecialchars($row['gender'] ?: 'Not provided') ?></td>
                        <td><span class="badge bg-<?= $stepColors[$step] ?>"><?= $stepLabels[$step] ?></span></td>
                        <td>
                            <?php if ($isSent): ?>
                                <span class="badge bg-success"><i class="bi bi-check2 me-1"></i>Sent</span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark border"><i class="bi bi-dash-circle me-1"></i>Not Sent</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
                <?php if ($total === 0): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No unpaid applicants found for this filter.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-white">
        <nav aria-label="Unpaid applicants pagination">
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

<div class="modal fade" id="unpaidEmailModal" tabindex="-1" aria-labelledby="unpaidEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <div>
                    <h5 class="modal-title fw-bold" id="unpaidEmailModalLabel"><i class="bi bi-envelope-paper-fill me-2"></i>Send Unpaid Reminder</h5>
                    <small class="text-white-50">The message is sent only to active applicants without a successful payment.</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>Use <code>{name}</code> wherever the applicant's name should appear. The current unpaid/not-sent list is collected when you click send.</div>
                <div class="mb-3">
                    <label for="unpaidEmailSubject" class="form-label fw-semibold">Subject</label>
                    <input type="text" id="unpaidEmailSubject" class="form-control" maxlength="180" value="<?= htmlspecialchars(UnpaidEmailHelper::defaultSubject()) ?>">
                </div>
                <div class="mb-3">
                    <label for="unpaidEmailTemplate" class="form-label fw-semibold">Email message</label>
                    <textarea id="unpaidEmailTemplate" class="form-control" rows="16" maxlength="12000"><?= htmlspecialchars(UnpaidEmailHelper::defaultTemplate()) ?></textarea>
                </div>
                <div id="unpaidEmailProgress" class="d-none">
                    <div class="d-flex justify-content-between small text-muted mb-1"><span id="unpaidEmailProgressLabel">Preparing...</span><span id="unpaidEmailProgressCount">0 / 0</span></div>
                    <div class="progress" role="progressbar" aria-label="Email progress"><div id="unpaidEmailProgressBar" class="progress-bar" style="width:0%"></div></div>
                    <div id="unpaidEmailResult" class="small mt-2"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="sendUnpaidEmailButton"><i class="bi bi-send me-1"></i>Send to All Unpaid / Not Sent</button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$extraFoot = '<script>window.rttcUnpaidEmailConfig = ' . json_encode([
    'apiUrl' => route('api.admin.send-unpaid-email'),
    'csrfToken' => SecurityHelper::generateCsrfToken(),
]) . ';</script>';
$extraFoot .= <<<'HTML'
<script>
(function () {
    const config = window.rttcUnpaidEmailConfig || {};
    const sendButton = document.getElementById('sendUnpaidEmailButton');
    const progress = document.getElementById('unpaidEmailProgress');
    const progressLabel = document.getElementById('unpaidEmailProgressLabel');
    const progressCount = document.getElementById('unpaidEmailProgressCount');
    const progressBar = document.getElementById('unpaidEmailProgressBar');
    const result = document.getElementById('unpaidEmailResult');

    if (!sendButton) return;

    async function post(data) {
        data.csrf_token = config.csrfToken;
        const response = await fetch(config.apiUrl, {
            method: 'POST',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
            body: new URLSearchParams(data)
        });
        const payload = await response.json().catch(function () { return {success: false, message: 'Invalid server response.'}; });
        if (!response.ok && payload.success !== true) throw new Error(payload.message || 'Request failed.');
        return payload;
    }

    sendButton.addEventListener('click', async function () {
        if (sendButton.disabled) return;
        const subject = document.getElementById('unpaidEmailSubject').value.trim();
        const template = document.getElementById('unpaidEmailTemplate').value.trim();
        if (!subject || !template) {
            result.className = 'small mt-2 text-danger';
            result.textContent = 'Subject and message are required.';
            progress.classList.remove('d-none');
            return;
        }
        if (!window.confirm('Send this email to every current unpaid applicant marked Not Sent?')) return;

        sendButton.disabled = true;
        progress.classList.remove('d-none');
        result.textContent = '';
        progressLabel.textContent = 'Preparing recipient list...';
        progressCount.textContent = '0 / 0';
        progressBar.style.width = '0%';

        let prepared;
        try {
            prepared = await post({action: 'prepare'});
        } catch (error) {
            result.className = 'small mt-2 text-danger';
            result.textContent = error.message;
            sendButton.disabled = false;
            return;
        }

        const recipients = prepared.recipients || [];
        if (!recipients.length) {
            progressLabel.textContent = 'Nothing to send.';
            result.className = 'small mt-2 text-success';
            result.textContent = 'All unpaid applicants have already been emailed, or no unpaid applicants exist.';
            sendButton.disabled = false;
            return;
        }

        let completed = 0;
        let sent = 0;
        let failed = 0;
        let nextIndex = 0;
        progressLabel.textContent = 'Sending emails...';
        progressCount.textContent = '0 / ' + recipients.length;

        async function worker() {
            while (nextIndex < recipients.length) {
                const recipient = recipients[nextIndex++];
                try {
                    const payload = await post({
                        action: 'send',
                        user_id: String(recipient.id),
                        subject: subject,
                        template: template
                    });
                    if (payload.success && !payload.skipped) sent++;
                } catch (error) {
                    failed++;
                }
                completed++;
                progressCount.textContent = completed + ' / ' + recipients.length;
                progressBar.style.width = Math.round((completed / recipients.length) * 100) + '%';
            }
        }

        await Promise.all([worker(), worker(), worker()]);
        progressLabel.textContent = 'Email dispatch complete.';
        result.className = 'small mt-2 ' + (failed ? 'text-warning' : 'text-success');
        result.textContent = sent + ' sent successfully' + (failed ? '; ' + failed + ' failed and remain Not Sent for retry.' : '.') + ' Refreshing status...';
        setTimeout(function () { window.location.reload(); }, 1800);
    });
})();
</script>
HTML;
include BASE_PATH . '/admin/layouts/admin.php';
