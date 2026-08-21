<?php
define('APP_INIT', true);
require_once __DIR__ . '/../../config/init.php';
SecurityHelper::requireAdminAuth();
if (!IdCardHelper::canManageRole(SessionHelper::get('admin_role'))) { http_response_code(403); exit('Your role can view the ID card list but cannot access submitted personal data.'); }
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(404); exit('ID card application not found.'); }
$stmt = db()->prepare('SELECT * FROM id_card_applications WHERE id = ? LIMIT 1');
if (!$stmt) { http_response_code(500); exit('ID card data is unavailable.'); }
$stmt->bind_param('i', $id); $stmt->execute(); $application = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$application) { http_response_code(404); exit('ID card application not found.'); }
$card = IdCardHelper::templateData($application);
$esc = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$status = (string) $application['status'];
$badge = ['pending' => 'warning text-dark', 'approved' => 'primary', 'done' => 'success'][$status] ?? 'secondary';
$pageTitle = 'Review ' . $card['reference'] . ' - Admin RTTC 2026'; $activePage = 'id-cards'; $breadcrumb = [['label' => 'ID Cards', 'url' => route('admin.id-cards')], ['label' => $card['reference']]];
$extraHead = '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/id-card.css?v=' . filemtime(BASE_PATH . '/assets/css/id-card.css') . '">';
ob_start();
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><h4 class="fw-bold mb-1"><i class="bi bi-person-vcard-fill me-2 text-primary"></i><?= $esc($card['reference']) ?></h4><span class="badge bg-<?= $badge ?>" data-id-card-status data-status="<?= $esc($status) ?>"><?= $esc(IdCardHelper::statuses()[$status]) ?></span></div><a class="btn btn-outline-secondary" href="<?= route('admin.id-cards') ?>"><i class="bi bi-arrow-left me-1"></i>Back to ID Cards</a></div>
<div class="card border-0 shadow-sm mb-4"><div class="card-body"><div class="row g-3"><div class="col-md-6"><strong>Submitted details</strong><dl class="row mb-0 mt-2"><dt class="col-sm-4">Name</dt><dd class="col-sm-8"><?= $esc($application['full_name']) ?></dd><dt class="col-sm-4">C/O</dt><dd class="col-sm-8"><?= $esc($application['care_of']) ?></dd><dt class="col-sm-4">Address</dt><dd class="col-sm-8" style="white-space:pre-line"><?= $esc($application['address']) ?></dd></dl></div><div class="col-md-6"><strong>Workflow</strong><dl class="row mb-0 mt-2"><dt class="col-sm-5">Submitted</dt><dd class="col-sm-7"><?= $esc(date('d M Y, h:i A', strtotime($application['created_at']))) ?></dd><dt class="col-sm-5">Approved</dt><dd class="col-sm-7"><?= $application['approved_at'] ? $esc(date('d M Y, h:i A', strtotime($application['approved_at']))) : 'Not yet approved' ?></dd><dt class="col-sm-5">Downloads</dt><dd class="col-sm-7"><?= (int) $application['download_count'] ?></dd></dl></div></div></div></div>
<div id="id-card-export-root" data-application-id="<?= (int) $application['id'] ?>" data-reference="<?= $esc($card['reference']) ?>" data-holder-name="<?= $esc($application['full_name']) ?>" data-status="<?= $esc($status) ?>" data-action-url="<?= $esc(route('api.admin.id-card-action')) ?>" data-csrf-token="<?= $esc(SecurityHelper::generateCsrfToken()) ?>" data-csrf-field="<?= $esc(CSRF_TOKEN_NAME) ?>">
    <div class="w-100 d-flex flex-wrap justify-content-center gap-2 mb-2"><button class="btn btn-primary" data-id-card-export><i class="bi bi-download me-1"></i><?= $status === 'pending' ? 'Approve & Download' : 'Download ZIP' ?></button><?php if ($status === 'pending'): ?><button class="btn btn-outline-danger" data-id-card-delete><i class="bi bi-trash me-1"></i>Delete</button><?php endif; ?></div>
    <?php include BASE_PATH . '/views/components/id-card/template.php'; ?>
</div>
<?php $content = ob_get_clean(); $extraFoot = '<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script><script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script><script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script><script src="' . BASE_URL . '/assets/js/id-card-export.js?v=' . filemtime(BASE_PATH . '/assets/js/id-card-export.js') . '"></script>'; include BASE_PATH . '/admin/layouts/admin.php';
