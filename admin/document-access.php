<?php
/**
 * RTTC 2026 - Admin: Document Edit Access
 * Grant/revoke document-only edit access to students (works even when portal is closed).
 */
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';
SecurityHelper::requireAdminAuth();

$db      = db();
$adminId = (int) SessionHelper::get('admin_id', 0);
$errors  = [];
$success = '';

// ── Handle POST actions ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SecurityHelper::verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'grant_access') {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $days         = max(1, min(90, (int) ($_POST['duration_days'] ?? 7)));
        $note         = trim($_POST['note'] ?? '');

        if ($targetUserId < 1) {
            $errors[] = 'Invalid student selected.';
        } else {
            // Verify user exists
            $chk = $db->prepare("SELECT id, email FROM users WHERE id = ? LIMIT 1");
            $chk->bind_param('i', $targetUserId);
            $chk->execute();
            $user = $chk->get_result()->fetch_assoc();
            $chk->close();

            if (!$user) {
                $errors[] = 'Student not found.';
            } else {
                // Deactivate any existing document-scope grants for this user
                $deact = $db->prepare("UPDATE user_edit_access SET is_active = 0, updated_at = NOW() WHERE user_id = ? AND scope = 'documents' AND is_active = 1");
                $deact->bind_param('i', $targetUserId);
                $deact->execute();
                $deact->close();

                // Insert new grant
                $expiresAt = date('Y-m-d H:i:s', strtotime("+{$days} days"));
                $ins = $db->prepare("INSERT INTO user_edit_access (user_id, granted_by, granted_at, expires_at, is_active, scope, note) VALUES (?, ?, NOW(), ?, 1, 'documents', ?)");
                $ins->bind_param('iiss', $targetUserId, $adminId, $expiresAt, $note);

                if ($ins->execute()) {
                    $rttcId = 'RTTC-' . str_pad((string)$targetUserId, 5, '0', STR_PAD_LEFT);
                    SessionHelper::setFlash('success', "Document edit access granted to {$rttcId} ({$user['email']}) for {$days} days.");
                    redirect(route('admin.document-access'));
                } else {
                    $errors[] = 'Database error. Please try again.';
                }
                $ins->close();
            }
        }
    } elseif ($action === 'revoke_access') {
        $grantId = (int) ($_POST['grant_id'] ?? 0);
        if ($grantId > 0) {
            // Revoke any grant (both 'all' and 'documents' scope)
            $rev = $db->prepare("UPDATE user_edit_access SET is_active = 0, updated_at = NOW() WHERE id = ?");
            $rev->bind_param('i', $grantId);
            $rev->execute();
            $rev->close();
            SessionHelper::setFlash('success', 'Edit access revoked successfully.');
            redirect(route('admin.document-access'));
        }
    }
}

// ── Search handling ────────────────────────────────────────────────────────
$searchQuery  = trim($_GET['q'] ?? '');
$searchResult = null;

if ($searchQuery !== '') {
    // Try to parse RTTC ID (e.g., RTTC-00007 → user_id 7)
    if (preg_match('/^RTTC-?(\d+)$/i', $searchQuery, $m)) {
        $searchUserId = (int) $m[1];
        $stmt = $db->prepare("SELECT u.id, u.username, u.email, u.phone,
            (SELECT current_step FROM registration_progress WHERE user_id = u.id) AS current_step
            FROM users u WHERE u.id = ? LIMIT 1");
        $stmt->bind_param('i', $searchUserId);
    } else {
        // Search by email
        $stmt = $db->prepare("SELECT u.id, u.username, u.email, u.phone,
            (SELECT current_step FROM registration_progress WHERE user_id = u.id) AS current_step
            FROM users u WHERE u.email = ? LIMIT 1");
        $stmt->bind_param('s', $searchQuery);
    }
    $stmt->execute();
    $searchResult = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Check if student already has active edit access (any scope)
    if ($searchResult) {
        $now = date('Y-m-d H:i:s');
        $ea = $db->prepare("SELECT id, scope, expires_at, note, granted_at FROM user_edit_access WHERE user_id = ? AND is_active = 1 AND expires_at > ? ORDER BY granted_at DESC LIMIT 1");
        $ea->bind_param('is', $searchResult['id'], $now);
        $ea->execute();
        $searchResult['active_grant'] = $ea->get_result()->fetch_assoc();
        $ea->close();
    }
}

// ── Active grants list ─────────────────────────────────────────────────────
$now = date('Y-m-d H:i:s');
$activeGrants = $db->prepare("
    SELECT ea.id, ea.user_id, ea.scope, ea.granted_at, ea.expires_at, ea.note, u.email, u.username
    FROM user_edit_access ea
    JOIN users u ON u.id = ea.user_id
    WHERE ea.is_active = 1 AND ea.expires_at > ?
    ORDER BY ea.granted_at DESC
");
$activeGrants->bind_param('s', $now);
$activeGrants->execute();
$grants = $activeGrants->get_result()->fetch_all(MYSQLI_ASSOC);
$activeGrants->close();

// ── Page setup ─────────────────────────────────────────────────────────────
$pageTitle  = 'Document Edit Access - Admin RTTC 2026';
$activePage = 'document-access';
$breadcrumb = [
    ['label' => 'Settings', 'url' => route('admin.settings')],
    ['label' => 'Document Edit Access']
];
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-lock2-fill me-2 text-primary"></i>Document Edit Access</h4>
    <a href="<?= route('admin.settings') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Settings
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $err): ?>
            <div><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Search Section -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom pt-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-search me-2 text-primary"></i>Search Student</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="<?= route('admin.document-access') ?>" class="row g-3 align-items-end">
            <div class="col-md-8">
                <label for="searchInput" class="form-label">RTTC Unique ID or Email</label>
                <input type="text" id="searchInput" name="q" class="form-control"
                       placeholder="e.g. RTTC-00007 or student@email.com"
                       value="<?= htmlspecialchars($searchQuery) ?>" required>
                <div class="form-text">Enter the student's RTTC ID (e.g., RTTC-00007) or their registered email address.</div>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-1"></i>Search
                </button>
            </div>
        </form>

        <?php if ($searchQuery !== '' && !$searchResult): ?>
            <div class="alert alert-warning mt-3 mb-0">
                <i class="bi bi-exclamation-triangle me-1"></i>No student found for "<strong><?= htmlspecialchars($searchQuery) ?></strong>". Check the RTTC ID or email and try again.
            </div>
        <?php endif; ?>

        <?php if ($searchResult): ?>
            <div class="mt-4 p-3 bg-light rounded border">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <h6 class="fw-bold mb-2">
                            <i class="bi bi-person-fill me-1"></i>
                            <?= htmlspecialchars($searchResult['username']) ?>
                        </h6>
                        <table class="table table-sm table-borderless mb-0" style="font-size:0.9rem;">
                            <tr>
                                <td class="text-muted fw-medium" style="width:120px;">RTTC ID</td>
                                <td><span class="badge bg-primary">RTTC-<?= str_pad((string)$searchResult['id'], 5, '0', STR_PAD_LEFT) ?></span></td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-medium">Email</td>
                                <td><?= htmlspecialchars($searchResult['email']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-medium">Phone</td>
                                <td><?= htmlspecialchars($searchResult['phone'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-medium">Step</td>
                                <td>
                                    <?php
                                    $step = (int)($searchResult['current_step'] ?? 0);
                                    $stepLabels = [0 => 'Not started', 1 => 'Personal Details', 2 => 'Academic Details', 3 => 'Documents Done'];
                                    echo $stepLabels[$step] ?? "Step $step";
                                    ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-5 mt-3 mt-md-0">
                        <?php if ($searchResult['active_grant']): ?>
                            <div class="alert alert-info small mb-2">
                                <i class="bi bi-check-circle-fill me-1"></i>
                                <strong>Already has edit access</strong>
                                <span class="badge bg-<?= $searchResult['active_grant']['scope'] === 'all' ? 'warning text-dark' : 'info' ?> ms-1"><?= $searchResult['active_grant']['scope'] === 'all' ? 'Full Access' : 'Documents Only' ?></span><br>
                                Expires: <?= date('d M Y, h:i A', strtotime($searchResult['active_grant']['expires_at'])) ?>
                                <?php if ($searchResult['active_grant']['note']): ?>
                                    <br>Note: <?= htmlspecialchars($searchResult['active_grant']['note']) ?>
                                <?php endif; ?>
                            </div>
                            <form method="POST" action="<?= route('admin.document-access') ?>" class="d-inline">
                                <?= SecurityHelper::csrfField() ?>
                                <input type="hidden" name="action" value="revoke_access">
                                <input type="hidden" name="grant_id" value="<?= $searchResult['active_grant']['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Revoke edit access for this student?')">
                                    <i class="bi bi-x-circle me-1"></i>Revoke Access
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="<?= route('admin.document-access') ?>">
                                <?= SecurityHelper::csrfField() ?>
                                <input type="hidden" name="action" value="grant_access">
                                <input type="hidden" name="user_id" value="<?= $searchResult['id'] ?>">
                                <div class="mb-2">
                                    <label class="form-label small fw-medium mb-1">Duration (days)</label>
                                    <select name="duration_days" class="form-select form-select-sm">
                                        <option value="3">3 days</option>
                                        <option value="7" selected>7 days</option>
                                        <option value="14">14 days</option>
                                        <option value="30">30 days</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-medium mb-1">Note (optional)</label>
                                    <input type="text" name="note" class="form-control form-control-sm" placeholder="e.g. Re-upload photo" maxlength="500">
                                </div>
                                <button type="submit" class="btn btn-success btn-sm w-100">
                                    <i class="bi bi-unlock-fill me-1"></i>Grant Document Edit Access
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Active Grants Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom pt-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0"><i class="bi bi-shield-check me-2 text-success"></i>Active Edit Grants</h6>
        <span class="badge bg-success"><?= count($grants) ?> active</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($grants)): ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                No active edit grants.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>RTTC ID</th>
                            <th>Student</th>
                            <th>Scope</th>
                            <th>Granted</th>
                            <th>Expires</th>
                            <th>Note</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grants as $g): ?>
                        <tr>
                            <td><span class="badge bg-primary">RTTC-<?= str_pad((string)$g['user_id'], 5, '0', STR_PAD_LEFT) ?></span></td>
                            <td>
                                <div class="fw-medium"><?= htmlspecialchars($g['username']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($g['email']) ?></small>
                            </td>
                            <td><span class="badge bg-<?= $g['scope'] === 'all' ? 'warning text-dark' : 'info' ?>"><?= $g['scope'] === 'all' ? 'Full' : 'Docs Only' ?></span></td>
                            <td><small><?= date('d M Y', strtotime($g['granted_at'])) ?></small></td>
                            <td>
                                <?php
                                $expiry = strtotime($g['expires_at']);
                                $remaining = $expiry - time();
                                $daysLeft = max(0, ceil($remaining / 86400));
                                ?>
                                <small><?= date('d M Y', $expiry) ?></small>
                                <br><span class="badge bg-<?= $daysLeft <= 1 ? 'warning' : 'secondary' ?> bg-opacity-75" style="font-size:0.7rem;"><?= $daysLeft ?> day<?= $daysLeft !== 1 ? 's' : '' ?> left</span>
                            </td>
                            <td><small class="text-muted"><?= htmlspecialchars($g['note'] ?? '-') ?></small></td>
                            <td class="text-end">
                                <form method="POST" action="<?= route('admin.document-access') ?>" class="d-inline">
                                    <?= SecurityHelper::csrfField() ?>
                                    <input type="hidden" name="action" value="revoke_access">
                                    <input type="hidden" name="grant_id" value="<?= $g['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Revoke edit access for RTTC-<?= str_pad((string)$g['user_id'], 5, '0', STR_PAD_LEFT) ?>?')">
                                        <i class="bi bi-x-circle me-1"></i>Revoke
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="alert alert-info small mt-4">
    <i class="bi bi-info-circle me-1"></i>
    <strong>How it works:</strong> Document edit access allows a student to re-upload their documents even when the registration portal is closed.
    This does <strong>not</strong> unlock personal details or academic details editing. Access expires automatically after the specified duration.
</div>

<?php
$content = ob_get_clean();
include BASE_PATH . '/admin/layouts/admin.php';
