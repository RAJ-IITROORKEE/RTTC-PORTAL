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
              <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                  <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                  <li>
                    <a class="dropdown-item" href="#"
                       onclick="openReplyModal(<?= $q['id'] ?>, <?= json_encode($q['name']) ?>, <?= json_encode($q['email']) ?>, <?= json_encode($q['issue_subject']) ?>); return false;">
                      <i class="bi bi-reply me-2 text-primary"></i>Reply & Resolve
                    </a>
                  </li>
                  <?php if (!$q['edit_access_granted'] && $q['user_id']): ?>
                  <li>
                    <a class="dropdown-item" href="#"
                       onclick="grantEditAccess(<?= $q['id'] ?>, <?= (int)$q['user_id'] ?>, <?= json_encode($q['name']) ?>); return false;">
                      <i class="bi bi-pencil-square me-2 text-warning"></i>Grant Edit Access
                    </a>
                  </li>
                  <?php endif; ?>
                  <?php if ($q['status'] === 'pending'): ?>
                  <li>
                    <a class="dropdown-item" href="#"
                       onclick="markResolved(<?= $q['id'] ?>); return false;">
                      <i class="bi bi-check-circle me-2 text-success"></i>Mark Resolved
                    </a>
                  </li>
                  <?php endif; ?>
                  <li><hr class="dropdown-divider"></li>
                  <li>
                    <a class="dropdown-item text-danger" href="#"
                       onclick="deleteQuery(<?= $q['id'] ?>); return false;">
                      <i class="bi bi-trash me-2"></i>Delete
                    </a>
                  </li>
                </ul>
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
$extraFoot = <<<'JS'
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

  fetch('/api/admin-query-reply', { method: 'POST', body: data })
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

  fetch('/api/admin-query-action', { method: 'POST', body: data })
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
  fetch('/api/admin-query-action', { method: 'POST', body: data })
    .then(r => r.json())
    .then(res => { if (res.success) location.reload(); else alert(res.message || 'Error'); });
}

// ---- Delete ----
function deleteQuery(queryId) {
  if (!confirm('Delete this query permanently? This cannot be undone.')) return;
  const data = new FormData();
  data.append('action', 'delete');
  data.append('query_id', queryId);
  fetch('/api/admin-query-action', { method: 'POST', body: data })
    .then(r => r.json())
    .then(res => { if (res.success) location.reload(); else alert(res.message || 'Error'); });
}

// ---- Helper ----
function setLoading(btn, loading) {
  btn.querySelector('.btn-text').classList.toggle('d-none', loading);
  btn.querySelector('.spinner-border').classList.toggle('d-none', !loading);
  btn.disabled = loading;
}
</script>
JS;

include BASE_PATH . '/admin/layouts/admin.php';
