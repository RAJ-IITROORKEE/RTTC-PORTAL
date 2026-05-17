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
        SUM(status = 'pending') AS pending,
        SUM(status = 'resolved') AS resolved,
        SUM(edit_access_granted = 1) AS access_granted
    FROM student_queries
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

$sql = "SELECT q.*, u.username FROM student_queries q
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
              <?php if ($q['edit_access_granted']): ?>
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
                        data-qsubject="<?= htmlspecialchars($q['issue_subject'], ENT_QUOTES) ?>"
                        data-quid="<?= (int)($q['user_id'] ?? 0) ?>"
                        data-qstatus="<?= $q['status'] ?>"
                        data-qaccess="<?= $q['edit_access_granted'] ? '1' : '0' ?>"
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
        <div class="alert alert-light border mb-3" id="replyMeta"></div>
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

<!-- Confirm Grant Access Modal -->
<div class="modal fade" id="grantAccessModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2 text-warning"></i>Grant Edit Access</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Grant edit access to <strong id="grantStudentName"></strong>? They will be able to modify their submitted registration forms.</p>
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

<?php
$content = ob_get_clean();
$urlQueryReply  = route('api.admin.query-reply');
$urlQueryAction = route('api.admin.query-action');
$extraFoot = <<<JS
<script>
let activeQueryId = null;
let activeUserId  = null;

// ---- Reply Modal ----
function openReplyModal(queryId, name, email, subject) {
  activeQueryId = queryId;
  document.getElementById('replyMeta').innerHTML =
    '<strong>To:</strong> ' + name + ' &lt;' + email + '&gt;<br><strong>Subject:</strong> ' + subject;
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
  data.append('query_id', activeQueryId);
  data.append('reply_message', msg);
  data.append('grant_access', document.getElementById('grantAccessCheck').checked ? '1' : '0');

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
function grantEditAccess(queryId, userId, name) {
  activeQueryId = queryId;
  activeUserId  = userId;
  document.getElementById('grantStudentName').textContent = name;
  document.getElementById('grantError').classList.add('d-none');
  new bootstrap.Modal(document.getElementById('grantAccessModal')).show();
}

document.getElementById('confirmGrantBtn').addEventListener('click', function () {
  setLoading(this, true);
  const data = new FormData();
  data.append('action', 'grant_access');
  data.append('query_id', activeQueryId);
  data.append('user_id', activeUserId);

  fetch('{$urlQueryAction}', { method: 'POST', body: data })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        bootstrap.Modal.getInstance(document.getElementById('grantAccessModal')).hide();
        location.reload();
      } else {
        document.getElementById('grantError').textContent = res.message || 'Error.';
        document.getElementById('grantError').classList.remove('d-none');
        setLoading(document.getElementById('confirmGrantBtn'), false);
      }
    });
});

// ---- Mark Resolved ----
function markResolved(queryId) {
  if (!confirm('Mark this query as resolved?')) return;
  const data = new FormData();
  data.append('action', 'mark_resolved');
  data.append('query_id', queryId);
  fetch('{$urlQueryAction}', { method: 'POST', body: data })
    .then(r => r.json())
    .then(res => { if (res.success) location.reload(); else alert(res.message || 'Error'); });
}

// ---- Delete ----
function deleteQuery(queryId) {
  if (!confirm('Delete this query permanently? This cannot be undone.')) return;
  const data = new FormData();
  data.append('action', 'delete');
  data.append('query_id', queryId);
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
    var qid     = d.qid;
    var name    = d.qname;
    var email   = d.qemail;
    var subject = d.qsubject;
    var uid     = parseInt(d.quid, 10);
    var status  = d.qstatus;
    var access  = d.qaccess === '1';

    // Build items
    var items = [];

    items.push({ label:'<i class="bi bi-reply me-2 text-primary"></i>Reply &amp; Resolve', action:'reply' });

    if (!access && uid > 0) {
      items.push({ label:'<i class="bi bi-pencil-square me-2 text-warning"></i>Grant Edit Access', action:'grant' });
    }
    if (status === 'pending') {
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
        if (item.action === 'reply')   openReplyModal(qid, name, email, subject);
        if (item.action === 'grant')   grantEditAccess(qid, uid, name);
        if (item.action === 'resolve') markResolved(qid);
        if (item.action === 'delete')  deleteQuery(qid);
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
