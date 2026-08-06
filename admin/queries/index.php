<?php
/**
 * RTTC 2026 - Admin: Student Queries
 */
define('APP_INIT', true);
require_once __DIR__ . '/../../config/init.php';
SecurityHelper::requireAdminAuth();

$db = db();

// Stats
$stats = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(q.status = 'pending') AS pending,
        SUM(q.status = 'resolved') AS resolved,
        SUM(CASE WHEN q.user_id IS NOT NULL AND EXISTS (
            SELECT 1 FROM user_edit_access ea
            WHERE ea.user_id = q.user_id AND ea.is_active = 1 AND ea.expires_at > NOW()
        ) THEN 1 ELSE 0 END) AS access_granted
    FROM student_queries q
")->fetch_assoc();

// Filters
$statusF = $_GET['status'] ?? '';
$search  = trim($_GET['search'] ?? '');

$where  = "WHERE 1=1";
$params = [];
$types  = '';

if ($statusF !== '') {
    $where   .= " AND q.status = ?";
    $params[] = $statusF;
    $types   .= 's';
}
if ($search !== '') {
    $where  .= " AND (q.name LIKE ? OR q.email LIKE ? OR q.issue_subject LIKE ?)";
    $like    = "%$search%";
    $params  = array_merge($params, [$like, $like, $like]);
    $types  .= 'sss';
}

$sql = "SELECT q.*, u.username,
        CASE WHEN q.user_id IS NOT NULL AND EXISTS (
            SELECT 1 FROM user_edit_access ea
            WHERE ea.user_id = q.user_id AND ea.is_active = 1 AND ea.expires_at > NOW()
        ) THEN 1 ELSE 0 END AS has_active_edit_access
        FROM student_queries q
        LEFT JOIN users u ON u.id = q.user_id
        $where
        ORDER BY q.created_at DESC";

$stmt = $db->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$queries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$activePage = 'queries';
$pageTitle  = 'Student Queries';
$breadcrumb = [['label' => 'Student Queries']];

ob_start();
?>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:48px;height:48px;background:#e8f4fd;">
          <i class="bi bi-chat-left-dots-fill text-primary fs-5"></i>
        </div>
        <div>
          <div class="fw-bold fs-4 lh-1"><?= $stats['total'] ?></div>
          <div class="text-muted small">Total Queries</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:48px;height:48px;background:#fff3cd;">
          <i class="bi bi-hourglass-split text-warning fs-5"></i>
        </div>
        <div>
          <div class="fw-bold fs-4 lh-1"><?= $stats['pending'] ?></div>
          <div class="text-muted small">Pending</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:48px;height:48px;background:#d1e7dd;">
          <i class="bi bi-check-circle-fill text-success fs-5"></i>
        </div>
        <div>
          <div class="fw-bold fs-4 lh-1"><?= $stats['resolved'] ?></div>
          <div class="text-muted small">Resolved</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:48px;height:48px;background:#ede7f6;">
          <i class="bi bi-pencil-square text-purple fs-5" style="color:#7c3aed!important;"></i>
        </div>
        <div>
          <div class="fw-bold fs-4 lh-1"><?= $stats['access_granted'] ?></div>
          <div class="text-muted small">Edit Access Given</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Filters + Table Card -->
<div class="card border-0 shadow-sm" style="border-radius:14px;">
  <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex flex-wrap gap-3 align-items-center justify-content-between">
    <h5 class="fw-bold mb-0"><i class="bi bi-chat-left-dots me-2 text-primary"></i>Student Queries</h5>
    <div class="d-flex gap-2 flex-wrap">
      <form method="get" class="d-flex gap-2 flex-wrap" id="filterForm">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name / email..." value="<?= htmlspecialchars($search) ?>" style="min-width:200px;">
        <select name="status" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
          <option value="">All Status</option>
          <option value="pending"  <?= $statusF === 'pending'  ? 'selected' : '' ?>>Pending</option>
          <option value="resolved" <?= $statusF === 'resolved' ? 'selected' : '' ?>>Resolved</option>
        </select>
        <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i></button>
        <?php if ($search || $statusF): ?>
          <a href="<?= route('admin.queries') ?>" class="btn btn-outline-secondary btn-sm">Clear</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <div class="card-body px-4 pb-4">
    <div class="table-responsive">
      <table class="table table-hover align-middle" id="queriesTable">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Name / Email</th>
            <th>Subject</th>
            <th>Message</th>
            <th>Status</th>
            <th>Submitted</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($queries)): ?>
          <tr><td colspan="7" class="text-center text-muted py-5">No queries found.</td></tr>
          <?php else: foreach ($queries as $i => $q): ?>
          <tr>
            <td class="text-muted small"><?= $i + 1 ?></td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($q['name']) ?></div>
              <div class="text-muted small"><?= htmlspecialchars($q['email']) ?></div>
              <?php if ($q['phone']): ?>
                <div class="text-muted small"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($q['phone']) ?></div>
              <?php endif; ?>
              <?php if ($q['has_active_edit_access']): ?>
                <span class="badge bg-purple-subtle text-purple mt-1" style="background:#ede7f6;color:#7c3aed;">Edit Access Given</span>
              <?php endif; ?>
            </td>
            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($q['issue_subject']) ?></span></td>
            <td style="max-width:260px;">
              <div class="text-truncate" style="max-width:240px;" title="<?= htmlspecialchars($q['message']) ?>">
                <?= htmlspecialchars($q['message']) ?>
              </div>
              <?php if ($q['reply_message']): ?>
                <div class="mt-1 small text-success"><i class="bi bi-reply-fill me-1"></i><?= htmlspecialchars(mb_strimwidth($q['reply_message'], 0, 60, '...')) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($q['status'] === 'resolved'): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle">Resolved</span>
              <?php else: ?>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Pending</span>
              <?php endif; ?>
            </td>
            <td class="small text-muted"><?= date('d M Y', strtotime($q['created_at'])) ?><br><?= date('H:i', strtotime($q['created_at'])) ?></td>
            <td class="text-end">
              <div class="tbl-action-wrap">
                <button class="btn btn-sm btn-light border tbl-action-btn p-1 px-2"
                        type="button"
                        data-qid="<?= $q['id'] ?>"
                        data-qname="<?= htmlspecialchars($q['name'], ENT_QUOTES) ?>"
                        data-qemail="<?= htmlspecialchars($q['email'], ENT_QUOTES) ?>"
                        data-qphone="<?= htmlspecialchars($q['phone'] ?? '', ENT_QUOTES) ?>"
                        data-qsubject="<?= htmlspecialchars($q['issue_subject'], ENT_QUOTES) ?>"
                        data-qmessage="<?= htmlspecialchars($q['message'], ENT_QUOTES) ?>"
                        data-qcreated="<?= htmlspecialchars($q['created_at'], ENT_QUOTES) ?>"
                        data-qreply="<?= htmlspecialchars($q['reply_message'] ?? '', ENT_QUOTES) ?>"
                        data-qrepliedat="<?= htmlspecialchars($q['replied_at'] ?? '', ENT_QUOTES) ?>"
                        data-quid="<?= (int)($q['user_id'] ?? 0) ?>"
                        data-qstatus="<?= $q['status'] ?>"
                        data-qaccess="<?= $q['has_active_edit_access'] ? '1' : '0' ?>"
                        aria-label="Actions">
                  <i class="bi bi-three-dots-vertical"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Reply Modal -->
<div class="modal fade" id="replyModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-reply me-2 text-primary"></i>Reply to Query</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="replyQueryDetails" class="mb-3"></div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Reply Message <span class="text-danger">*</span></label>
          <textarea class="form-control" id="replyMessage" rows="6" placeholder="Type your reply to the student..."></textarea>
        </div>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" id="grantAccessCheck">
          <label class="form-check-label" for="grantAccessCheck">
            Grant edit access to this student (allows editing submitted forms)
          </label>
        </div>
        <div id="replyError" class="text-danger small d-none"></div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary fw-semibold" id="sendReplyBtn">
          <span class="btn-text"><i class="bi bi-send me-1"></i>Send Reply</span>
          <span class="spinner-border spinner-border-sm d-none"></span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- View Query Modal -->
<div class="modal fade" id="viewQueryModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-eye me-2 text-primary"></i>Student Query</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><div id="viewQueryDetails"></div></div>
      <div class="modal-footer border-0 pt-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Confirm Grant Access Modal -->
<div class="modal fade" id="grantAccessModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2 text-warning"></i>Grant Edit Access</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="grantQueryDetails" class="mb-3"></div>
        <p class="mb-0">Grant edit access to this student? They will be able to modify their submitted registration forms for 7 days.</p>
        <div id="grantError" class="text-danger small d-none"></div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-warning fw-semibold" id="confirmGrantBtn">
          <span class="btn-text"><i class="bi bi-check-lg me-1"></i>Confirm Grant</span>
          <span class="spinner-border spinner-border-sm d-none"></span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Confirm Revoke Access Modal -->
<div class="modal fade" id="revokeAccessModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-slash-circle me-2 text-danger"></i>Revoke Edit Access</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="revokeQueryDetails" class="mb-3"></div>
        <p class="mb-0">Remove this student's active edit access? The student will no longer be able to modify submitted forms.</p>
        <div id="revokeError" class="text-danger small d-none"></div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger fw-semibold" id="confirmRevokeBtn">
          <span class="btn-text"><i class="bi bi-slash-circle me-1"></i>Revoke Access</span>
          <span class="spinner-border spinner-border-sm d-none"></span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Confirm Resolve Modal -->
<div class="modal fade" id="resolveQueryModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-check-circle me-2 text-success"></i>Mark Query as Resolved</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="resolveQueryDetails" class="mb-3"></div>
        <p class="mb-0">Mark this query as resolved without sending a reply?</p>
        <div id="resolveError" class="text-danger small d-none"></div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success fw-semibold" id="confirmResolveBtn">
          <span class="btn-text"><i class="bi bi-check-lg me-1"></i>Mark Resolved</span>
          <span class="spinner-border spinner-border-sm d-none"></span>
        </button>
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
$urlQueryReply  = route('api.admin.query-reply');
$urlQueryAction = route('api.admin.query-action');
$csrfFieldName = json_encode(CSRF_TOKEN_NAME, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$csrfToken = json_encode(SecurityHelper::generateCsrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$extraFoot = <<<JS
<script>
let activeQuery = null;
const csrfFieldName = {$csrfFieldName};
const csrfToken = {$csrfToken};

function appendCsrf(data) {
  data.append(csrfFieldName, csrfToken);
}

function formatDate(value) {
  if (!value) return 'Not available';
  const date = new Date(value.replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

function renderQueryDetails(containerId, query) {
  const container = document.getElementById(containerId);
  if (!container) return;

  container.replaceChildren();
  const table = document.createElement('table');
  table.className = 'table table-sm table-bordered mb-3';
  const details = [
    ['Student', query.name],
    ['Email', query.email],
    ['Phone', query.phone || 'Not provided'],
    ['Subject', query.subject],
    ['Submitted', formatDate(query.createdAt)],
    ['Status', query.status === 'resolved' ? 'Resolved' : 'Pending'],
    ['Edit access', query.hasAccess ? 'Granted' : 'Not granted']
  ];
  details.forEach(function (detail) {
    const row = table.insertRow();
    const label = document.createElement('th');
    label.className = 'bg-light text-muted fw-semibold';
    label.style.width = '30%';
    label.textContent = detail[0];
    const value = document.createElement('td');
    value.textContent = detail[1];
    row.append(label, value);
  });
  container.appendChild(table);

  const messageLabel = document.createElement('div');
  messageLabel.className = 'fw-semibold small mb-1';
  messageLabel.textContent = 'Student message';
  const message = document.createElement('div');
  message.className = 'border rounded bg-light p-3 small';
  message.style.whiteSpace = 'pre-wrap';
  message.textContent = query.message;
  container.append(messageLabel, message);

  if (query.replyMessage) {
    const replyLabel = document.createElement('div');
    replyLabel.className = 'fw-semibold small mb-1 mt-3 text-success';
    replyLabel.textContent = 'Previous admin reply' + (query.repliedAt ? ' (' + formatDate(query.repliedAt) + ')' : '');
    const reply = document.createElement('div');
    reply.className = 'border border-success-subtle rounded bg-success-subtle p-3 small';
    reply.style.whiteSpace = 'pre-wrap';
    reply.textContent = query.replyMessage;
    container.append(replyLabel, reply);
  }
}

function openViewModal(query) {
  renderQueryDetails('viewQueryDetails', query);
  new bootstrap.Modal(document.getElementById('viewQueryModal')).show();
}

// ---- Reply Modal ----
function openReplyModal(query) {
  activeQuery = query;
  renderQueryDetails('replyQueryDetails', query);
  document.getElementById('replyMessage').value = '';
  document.getElementById('grantAccessCheck').checked = false;
  document.getElementById('replyError').classList.add('d-none');
  new bootstrap.Modal(document.getElementById('replyModal')).show();
}

document.getElementById('sendReplyBtn').addEventListener('click', function () {
  const msg = document.getElementById('replyMessage').value.trim();
  if (msg.length < 5) {
    document.getElementById('replyError').textContent = 'Please enter a reply message.';
    document.getElementById('replyError').classList.remove('d-none');
    return;
  }
  setLoading(this, true);
  document.getElementById('replyError').classList.add('d-none');

  const data = new FormData();
  data.append('query_id', activeQuery.id);
  data.append('reply_message', msg);
  data.append('grant_access', document.getElementById('grantAccessCheck').checked ? '1' : '0');
  appendCsrf(data);

  fetch('{$urlQueryReply}', { method: 'POST', body: data })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        bootstrap.Modal.getInstance(document.getElementById('replyModal')).hide();
        location.reload();
      } else {
        document.getElementById('replyError').textContent = res.message || 'Error sending reply.';
        document.getElementById('replyError').classList.remove('d-none');
        setLoading(document.getElementById('sendReplyBtn'), false);
      }
    })
    .catch(() => {
      document.getElementById('replyError').textContent = 'Network error. Please try again.';
      document.getElementById('replyError').classList.remove('d-none');
      setLoading(document.getElementById('sendReplyBtn'), false);
    });
});

// ---- Grant Access ----
function grantEditAccess(query) {
  activeQuery = query;
  renderQueryDetails('grantQueryDetails', query);
  document.getElementById('grantError').classList.add('d-none');
  new bootstrap.Modal(document.getElementById('grantAccessModal')).show();
}

function revokeEditAccess(query) {
  activeQuery = query;
  renderQueryDetails('revokeQueryDetails', query);
  document.getElementById('revokeError').classList.add('d-none');
  new bootstrap.Modal(document.getElementById('revokeAccessModal')).show();
}

function submitQueryAction(action, button, errorId, modalId) {
  const error = document.getElementById(errorId);
  error.classList.add('d-none');
  setLoading(button, true);
  const data = new FormData();
  data.append('action', action);
  data.append('query_id', activeQuery.id);
  appendCsrf(data);

  fetch('{$urlQueryAction}', { method: 'POST', body: data })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        bootstrap.Modal.getInstance(document.getElementById(modalId)).hide();
        location.reload();
      } else {
        error.textContent = res.message || 'Error.';
        error.classList.remove('d-none');
        setLoading(button, false);
      }
    })
    .catch(() => {
      error.textContent = 'Network error. Please try again.';
      error.classList.remove('d-none');
      setLoading(button, false);
    });
}

document.getElementById('confirmGrantBtn').addEventListener('click', function () {
  submitQueryAction('grant_access', this, 'grantError', 'grantAccessModal');
});

document.getElementById('confirmRevokeBtn').addEventListener('click', function () {
  submitQueryAction('revoke_access', this, 'revokeError', 'revokeAccessModal');
});

// ---- Mark Resolved ----
function markResolved(query) {
  activeQuery = query;
  renderQueryDetails('resolveQueryDetails', query);
  document.getElementById('resolveError').classList.add('d-none');
  new bootstrap.Modal(document.getElementById('resolveQueryModal')).show();
}

document.getElementById('confirmResolveBtn').addEventListener('click', function () {
  submitQueryAction('mark_resolved', this, 'resolveError', 'resolveQueryModal');
});

// ---- Delete ----
function deleteQuery(queryId) {
  if (!confirm('Delete this query permanently? This cannot be undone.')) return;
  const data = new FormData();
  data.append('action', 'delete');
  data.append('query_id', queryId);
  appendCsrf(data);
  fetch('{$urlQueryAction}', { method: 'POST', body: data })
    .then(r => r.json())
    .then(res => { if (res.success) location.reload(); else alert(res.message || 'Error'); });
}

// ---- Helper ----
function setLoading(btn, loading) {
  btn.querySelector('.btn-text').classList.toggle('d-none', loading);
  btn.querySelector('.spinner-border').classList.toggle('d-none', !loading);
  btn.disabled = loading;
}

// ---- Table action dropdown ----
(function () {
  // Inject styles
  var style = document.createElement('style');
  style.textContent =
    '.tbl-action-wrap { display:inline-block; }' +
    '.tbl-action-btn { line-height:1; }' +
    '.tbl-action-btn:focus { box-shadow:none !important; }' +
    '.tbl-action-btn.is-open { background:#e9ecef; }' +
    '#tblFloatMenu {' +
    '  position:fixed; z-index:99999; background:#fff; list-style:none; margin:0;' +
    '  padding:4px 0; min-width:195px; border-radius:10px;' +
    '  box-shadow:0 4px 20px rgba(0,0,0,.15); font-size:.875rem;' +
    '  animation:tblIn .1s ease;' +
    '}' +
    '@keyframes tblIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:none} }' +
    '#tblFloatMenu li a {' +
    '  display:flex; align-items:center; padding:9px 16px;' +
    '  color:#212529; text-decoration:none; white-space:nowrap;' +
    '}' +
    '#tblFloatMenu li a:hover { background:#f5f5f5; }' +
    '#tblFloatMenu li a.danger { color:#dc3545; }' +
    '#tblFloatMenu li a.danger:hover { background:#fff0f0; }' +
    '#tblFloatMenu .sep { border-top:1px solid #eee; margin:3px 0; }';
  document.head.appendChild(style);

  // Build single reusable floating menu element
  var menu = document.createElement('ul');
  menu.id = 'tblFloatMenu';
  menu.style.display = 'none';
  document.body.appendChild(menu);

  var openBtn = null;

  function hideMenu() {
    menu.style.display = 'none';
    if (openBtn) { openBtn.classList.remove('is-open'); openBtn = null; }
  }

  function showMenu(btn) {
    if (openBtn === btn) { hideMenu(); return; }
    hideMenu();

    var d = btn.dataset;
    var query = {
      id: parseInt(d.qid, 10),
      name: d.qname,
      email: d.qemail,
      phone: d.qphone,
      subject: d.qsubject,
      message: d.qmessage,
      createdAt: d.qcreated,
      replyMessage: d.qreply,
      repliedAt: d.qrepliedat,
      userId: parseInt(d.quid, 10),
      status: d.qstatus,
      hasAccess: d.qaccess === '1'
    };

    // Build items
    var items = [];

    items.push({ label:'<i class="bi bi-eye me-2 text-primary"></i>View Full Query', action:'view' });
    items.push({ label:'<i class="bi bi-reply me-2 text-primary"></i>Reply &amp; Resolve', action:'reply' });

    if (query.hasAccess && query.userId > 0) {
      items.push({ label:'<i class="bi bi-slash-circle me-2 text-danger"></i>Revoke Edit Access', action:'revoke' });
    } else if (!query.hasAccess && query.userId > 0) {
      items.push({ label:'<i class="bi bi-pencil-square me-2 text-warning"></i>Grant Edit Access', action:'grant' });
    }
    if (query.status === 'pending') {
      items.push({ label:'<i class="bi bi-check-circle me-2 text-success"></i>Mark Resolved', action:'resolve' });
    }
    items.push({ sep: true });
    items.push({ label:'<i class="bi bi-trash me-2"></i>Delete', action:'delete', cls:'danger' });

    menu.innerHTML = '';
    items.forEach(function(item) {
      var li = document.createElement('li');
      if (item.sep) { li.className = 'sep'; menu.appendChild(li); return; }
      var a = document.createElement('a');
      a.href = '#';
      a.innerHTML = item.label;
      if (item.cls) a.className = item.cls;
      a.addEventListener('click', function(e) {
        e.preventDefault();
        hideMenu();
        if (item.action === 'view')    openViewModal(query);
        if (item.action === 'reply')   openReplyModal(query);
        if (item.action === 'grant')   grantEditAccess(query);
        if (item.action === 'revoke')  revokeEditAccess(query);
        if (item.action === 'resolve') markResolved(query);
        if (item.action === 'delete')  deleteQuery(query.id);
      });
      li.appendChild(a);
      menu.appendChild(li);
    });

    // Position
    menu.style.display = 'block';
    var r      = btn.getBoundingClientRect();
    var mh     = menu.offsetHeight;
    var right  = window.innerWidth - r.right;
    menu.style.right = right + 'px';
    menu.style.left  = 'auto';

    var spaceBelow = window.innerHeight - r.bottom - 8;
    if (spaceBelow < mh) {
      menu.style.top    = 'auto';
      menu.style.bottom = (window.innerHeight - r.top + 4) + 'px';
    } else {
      menu.style.bottom = 'auto';
      menu.style.top    = (r.bottom + 4) + 'px';
    }

    openBtn = btn;
    btn.classList.add('is-open');
  }

  // Direct listeners on each button (avoids event-delegation issues with admin layout)
  document.querySelectorAll('.tbl-action-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      showMenu(btn);
    });
  });

  // Close on outside click
  document.addEventListener('click', hideMenu);
  // Close on scroll/resize
  window.addEventListener('scroll', hideMenu, true);
  window.addEventListener('resize', hideMenu);
})();
</script>
JS;

include BASE_PATH . '/admin/layouts/admin.php';
