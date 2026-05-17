<?php
define('APP_INIT', true);
require_once __DIR__ . '/config/init.php';

SecurityHelper::requireAuth();

$db     = db();
$userId = SessionHelper::get('user_id');
$errors = [];

// Step gate
$pstmt = $db->prepare("SELECT current_step FROM registration_progress WHERE user_id = ?");
$pstmt->bind_param('i', $userId);
$pstmt->execute();
$prog = $pstmt->get_result()->fetch_assoc();
$pstmt->close();
if (($prog['current_step'] ?? 0) < 2) {
    SessionHelper::setFlash('error', 'Please complete Academic Details first.');
    redirect(route('academics'));
}

// Fetch existing documents
$stmt = $db->prepare("SELECT * FROM documents WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

// Fetch personal details for category-based required docs
$pdStmt = $db->prepare("SELECT caste, ews, obc_ncl, pwd FROM personal_details WHERE user_id = ?");
$pdStmt->bind_param('i', $userId);
$pdStmt->execute();
$personalData = $pdStmt->get_result()->fetch_assoc() ?: [];
$pdStmt->close();

$userCaste  = $personalData['caste']    ?? 'General';
$userEws    = (bool)($personalData['ews']    ?? 0);
$userObcNcl = (bool)($personalData['obc_ncl'] ?? 0);
$userPwd    = (bool)($personalData['pwd']    ?? 0);
$needsCaste = ($userCaste !== 'General');

// Document definitions: [label, required, accept, maxKB, allowedExtensions[]]
// maxKB: 200 = 200KB, 1024 = 1MB
$docDefs = [
    'photo'                => ['Passport Photo (3.5×4.5 cm)',            true,  'image/jpeg,image/jpg,image/png',                  200,  ['jpg','jpeg','png']],
    'signature'            => ['Signature (on white background, 7×3 cm)', true, 'image/jpeg,image/jpg,image/png',                  200,  ['jpg','jpeg','png']],
    'hslc_marksheet'       => ['HSLC Marksheet',                          true,  'image/jpeg,image/jpg,image/png,application/pdf', 1024, ['jpg','jpeg','png','pdf']],
    'hsslc_marksheet'      => ['HSSLC Marksheet',                         true,  'image/jpeg,image/jpg,image/png,application/pdf', 1024, ['jpg','jpeg','png','pdf']],
    'degree_marksheet'     => ['Bachelor Degree Marksheet',               true,  'image/jpeg,image/jpg,image/png,application/pdf', 1024, ['jpg','jpeg','png','pdf']],
    'masters_marksheet'    => ['Master Degree Marksheet (if applicable)', false, 'image/jpeg,image/jpg,image/png,application/pdf', 1024, ['jpg','jpeg','png','pdf']],
    'gubedcet_admit_card'  => ['GUBEDCET Admit Card',                     true,  'image/jpeg,image/jpg,image/png,application/pdf', 1024, ['jpg','jpeg','png','pdf']],
    'gubedcet_result_sheet'=> ['GUBEDCET Result Sheet',                   true,  'image/jpeg,image/jpg,image/png,application/pdf', 1024, ['jpg','jpeg','png','pdf']],
];

// Conditionally required category certificates (based on personal details)
if ($needsCaste) {
    $docDefs['caste_cert']   = [$userCaste . ' Caste Certificate',       true,  'image/jpeg,image/jpg,image/png,application/pdf', 1024, ['jpg','jpeg','png','pdf']];
}
if ($userEws) {
    $docDefs['ews_cert']     = ['EWS Certificate',                        true,  'image/jpeg,image/jpg,image/png,application/pdf', 1024, ['jpg','jpeg','png','pdf']];
}
if ($userObcNcl) {
    $docDefs['obc_ncl_cert'] = ['OBC-NCL Certificate',                   true,  'image/jpeg,image/jpg,image/png,application/pdf', 1024, ['jpg','jpeg','png','pdf']];
}
if ($userPwd) {
    $docDefs['pwd_cert']     = ['PWD Certificate',                        true,  'image/jpeg,image/jpg,image/png,application/pdf', 1024, ['jpg','jpeg','png','pdf']];
}

// ── View-only mode ─────────────────────────────────────────────────────────
$now    = date('Y-m-d H:i:s');
$stmtEA = $db->prepare("SELECT id FROM user_edit_access WHERE user_id = ? AND is_active = 1 AND expires_at > ? LIMIT 1");
$stmtEA->bind_param('is', $userId, $now);
$stmtEA->execute();
$stmtEA->store_result();
$editAccess = $stmtEA->num_rows > 0;
$stmtEA->close();

$stepDone = !empty($existing);
$viewOnly = $stepDone && !$editAccess;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $viewOnly) {
    redirect(route('welcome'), [], 'error', 'Your documents are in view-only mode. Request edit access from admin.');
}
// ──────────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SecurityHelper::verifyCsrf();

    // Declaration checkbox
    if (empty($_POST['doc_declaration'])) {
        $errors['declaration'] = 'You must confirm the declaration before submitting.';
    }

    $uploaded = [];
    foreach ($docDefs as $field => [$label, $required, $accept, $maxKB, $allowedTypes]) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $file   = $_FILES[$field];
            $ext    = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $sizeKB = $file['size'] / 1024;

            if (!in_array($ext, $allowedTypes)) {
                $allowed = implode('/', array_map('strtoupper', $allowedTypes));
                $errors[$field] = "Invalid file type. Allowed: $allowed.";
            } elseif ($sizeKB > $maxKB) {
                $limitLabel = $maxKB >= 1024 ? '1 MB' : '200 KB';
                $errors[$field] = "File too large. Maximum allowed size is $limitLabel.";
            } else {
                $result = SecurityHelper::saveUpload($field, $userId, $field);
                if ($result['success']) {
                    $uploaded[$field] = $result['path'];
                } else {
                    $errors[$field] = $result['message'];
                }
            }
        } elseif ($required && empty($existing[$field])) {
            $errors[$field] = 'This document is required.';
        } else {
            if (!empty($existing[$field])) {
                $uploaded[$field] = $existing[$field];
            }
        }
    }

    if (empty($errors)) {
        if ($existing) {
            $sets  = [];
            $types = '';
            $vals  = [];
            foreach ($uploaded as $col => $path) {
                $sets[] = "$col = ?";
                $types .= 's';
                $vals[] = $path;
            }
            $sql  = "UPDATE documents SET " . implode(', ', $sets) . " WHERE user_id = ?";
            $stmt = $db->prepare($sql);
            $types .= 'i';
            $vals[] = $userId;
            $stmt->bind_param($types, ...$vals);
        } else {
            $cols  = 'user_id, ' . implode(', ', array_keys($uploaded));
            $ph    = '?, ' . implode(', ', array_fill(0, count($uploaded), '?'));
            $sql   = "INSERT INTO documents ($cols) VALUES ($ph)";
            $stmt  = $db->prepare($sql);
            $types = 'i' . str_repeat('s', count($uploaded));
            $vals  = array_merge([$userId], array_values($uploaded));
            $stmt->bind_param($types, ...$vals);
        }

        if ($stmt->execute()) {
            $stmt->close();
            $upd = $db->prepare("UPDATE registration_progress SET current_step = GREATEST(current_step, 3) WHERE user_id = ?");
            $upd->bind_param('i', $userId);
            $upd->execute();
            $upd->close();
            SessionHelper::setFlash('success', 'Documents uploaded successfully!');
            redirect(route('payment'));
        } else {
            $errors['db'] = 'Database error. Please try again.';
        }
    }
}

// Build JS config for client-side validation
$jsDocConfig = [];
foreach ($docDefs as $field => [$label, $required, $accept, $maxKB, $allowedTypes]) {
    $jsDocConfig[] = [
        'field'    => $field,
        'label'    => $label,
        'maxKB'    => $maxKB,
        'types'    => $allowedTypes,
        'required' => $required,
    ];
}

$pageTitle   = 'Upload Documents - Step 3 - RTTC 2026';
// Use actual overall progress so stepper reflects true completion state
$currentStep = $prog['current_step'] ?? 3;
ob_start();
?>

<div class="container py-4">
    <div class="row mb-3">
        <div class="col"><?php include __DIR__ . '/views/partials/stepper.php'; ?></div>
    </div>
    <?php include __DIR__ . '/views/partials/flash.php'; ?>

    <?php if ($viewOnly): ?>
    <div class="alert alert-info border-0 shadow-sm d-flex align-items-start gap-3 mb-4" role="alert" style="border-left:4px solid #0d6efd !important;">
        <i class="bi bi-info-circle-fill fs-5 mt-1 text-primary flex-shrink-0"></i>
        <div>
            <strong>Documents — View Only</strong><br>
            <span class="small">Your uploaded documents have already been submitted and <strong>cannot be replaced</strong> at this time.
            If you need to re-upload any document, please
            <a href="<?= route('request-query') ?>" class="alert-link fw-semibold">raise a query</a>
            and the admin may grant you temporary edit access.</span>
        </div>
    </div>
    <?php endif; ?>

    <div class="alert alert-info border-0 shadow-sm mb-4">
        <i class="bi bi-info-circle-fill me-2"></i>
        <strong>Upload Guidelines:</strong>
        Photo &amp; Signature: JPG/PNG only, max <strong>200 KB</strong>.
        All other documents: JPG/PNG/PDF, max <strong>1 MB</strong> each.
        Dimensions: Photo 3.5&times;4.5 cm | Signature 7&times;3 cm.
    </div>

    <form method="POST" action="<?= route('documents') ?>" enctype="multipart/form-data" id="docsForm" novalidate<?= $viewOnly ? ' data-viewonly="1"' : '' ?>>
        <?= SecurityHelper::csrfField() ?>

        <!-- Photo & Signature -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0"><i class="bi bi-camera-fill me-2"></i>Photo &amp; Signature</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <?php foreach (['photo', 'signature'] as $f):
                        [$label, $req, $accept, $maxKB] = $docDefs[$f];
                        $hasFile = !empty($existing[$f]);
                        $hint    = 'JPG/PNG only, max 200 KB';
                    ?>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="<?= $f ?>">
                            <?= htmlspecialchars($label) ?>
                            <span class="text-danger">*</span>
                        </label>
                        <?php if ($hasFile): ?>
                        <div class="mb-2">
                            <img src="<?= BASE_URL . '/' . htmlspecialchars($existing[$f]) ?>" alt="<?= htmlspecialchars($label) ?>"
                                 class="img-thumbnail" style="max-height:90px;">
                            <span class="badge bg-success ms-2"><i class="bi bi-check-circle me-1"></i>Uploaded</span>
                        </div>
                        <?php endif; ?>
                        <input type="file" id="<?= $f ?>" name="<?= $f ?>" accept="<?= $accept ?>"
                               class="form-control <?= isset($errors[$f]) ? 'is-invalid' : '' ?>"
                               <?= ($req && !$hasFile) ? 'required' : '' ?>>
                        <div class="form-text text-muted"><?= $hint ?></div>
                        <div class="invalid-feedback" id="<?= $f ?>-feedback">
                            <?= isset($errors[$f]) ? htmlspecialchars($errors[$f]) : '' ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Academic Documents -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text-fill me-2"></i>Academic Documents</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <?php foreach (['hslc_marksheet','hsslc_marksheet','degree_marksheet','masters_marksheet'] as $f):
                        [$label, $req, $accept, $maxKB] = $docDefs[$f];
                        $hasFile = !empty($existing[$f]);
                        $hint    = 'JPG/PNG/PDF, max 1 MB';
                    ?>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="<?= $f ?>">
                            <?= htmlspecialchars($label) ?>
                            <?= $req ? '<span class="text-danger">*</span>' : '<span class="badge bg-secondary ms-1">Optional</span>' ?>
                        </label>
                        <?php if ($hasFile): ?>
                        <div class="mb-1">
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Uploaded</span>
                            <a href="<?= BASE_URL . '/' . htmlspecialchars($existing[$f]) ?>" target="_blank" class="btn btn-sm btn-link p-0 ms-2">View</a>
                        </div>
                        <?php endif; ?>
                        <input type="file" id="<?= $f ?>" name="<?= $f ?>" accept="<?= $accept ?>"
                               class="form-control <?= isset($errors[$f]) ? 'is-invalid' : '' ?>"
                               <?= ($req && !$hasFile) ? 'required' : '' ?>>
                        <div class="form-text text-muted"><?= $hint ?></div>
                        <div class="invalid-feedback" id="<?= $f ?>-feedback">
                            <?= isset($errors[$f]) ? htmlspecialchars($errors[$f]) : '' ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- GUBEDCET Documents -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0"><i class="bi bi-file-earmark-check-fill me-2"></i>GUBEDCET 2026 Documents</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <?php foreach (['gubedcet_admit_card','gubedcet_result_sheet'] as $f):
                        [$label, $req, $accept, $maxKB] = $docDefs[$f];
                        $hasFile = !empty($existing[$f]);
                        $hint    = 'JPG/PNG/PDF, max 1 MB';
                    ?>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="<?= $f ?>">
                            <?= htmlspecialchars($label) ?>
                            <span class="text-danger">*</span>
                        </label>
                        <?php if ($hasFile): ?>
                        <div class="mb-1">
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Uploaded</span>
                            <a href="<?= BASE_URL . '/' . htmlspecialchars($existing[$f]) ?>" target="_blank" class="btn btn-sm btn-link p-0 ms-2">View</a>
                        </div>
                        <?php endif; ?>
                        <input type="file" id="<?= $f ?>" name="<?= $f ?>" accept="<?= $accept ?>"
                               class="form-control <?= isset($errors[$f]) ? 'is-invalid' : '' ?>"
                               <?= ($req && !$hasFile) ? 'required' : '' ?>>
                        <div class="form-text text-muted"><?= $hint ?></div>
                        <div class="invalid-feedback" id="<?= $f ?>-feedback">
                            <?= isset($errors[$f]) ? htmlspecialchars($errors[$f]) : '' ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Category Certificates (conditional based on personal details) -->
        <?php
        $catFields = ['caste_cert','ews_cert','obc_ncl_cert','pwd_cert'];
        $activeCatFields = array_filter($catFields, fn($f) => isset($docDefs[$f]));
        ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header py-3" style="background-color:#fff3cd; border-left:4px solid #ffc107;">
                <h5 class="mb-0 text-dark"><i class="bi bi-person-badge me-2"></i>Category Certificates</h5>
            </div>
            <div class="card-body p-4">
                <?php if (empty($activeCatFields)): ?>
                    <div class="alert alert-success mb-0">
                        <i class="bi bi-check-circle me-2"></i>
                        No additional category certificates required based on your personal details (Category: <strong>General</strong>).
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Based on your personal details, the following certificates are <strong>mandatory</strong>.
                    </div>
                    <div class="row g-3">
                        <?php foreach ($activeCatFields as $f):
                            [$label, $req, $accept, $maxKB] = $docDefs[$f];
                            $hasFile = !empty($existing[$f]);
                            $hint    = 'JPG/PNG/PDF, max 1 MB';
                        ?>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="<?= $f ?>">
                                <?= htmlspecialchars($label) ?>
                                <span class="badge bg-danger ms-1">Required</span>
                            </label>
                            <?php if ($hasFile): ?>
                            <div class="mb-1">
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Uploaded</span>
                                <a href="<?= BASE_URL . '/' . htmlspecialchars($existing[$f]) ?>" target="_blank" class="btn btn-sm btn-link p-0 ms-2">View</a>
                            </div>
                            <?php endif; ?>
                            <input type="file" id="<?= $f ?>" name="<?= $f ?>" accept="<?= $accept ?>"
                                   class="form-control <?= isset($errors[$f]) ? 'is-invalid' : '' ?>"
                                   <?= ($req && !$hasFile) ? 'required' : '' ?>>
                            <div class="form-text text-muted"><?= $hint ?></div>
                            <div class="invalid-feedback" id="<?= $f ?>-feedback">
                                <?= isset($errors[$f]) ? htmlspecialchars($errors[$f]) : '' ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($errors['db'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errors['db']) ?></div>
        <?php endif; ?>

        <!-- Declaration -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="form-check">
                    <input class="form-check-input <?= isset($errors['declaration']) ? 'is-invalid' : '' ?>"
                           type="checkbox" name="doc_declaration" id="docDeclaration" value="1"
                           <?= !empty($_POST['doc_declaration']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="docDeclaration">
                        I hereby confirm that all documents uploaded above are genuine, authentic and belong to me.
                        I understand that any false document may lead to cancellation of my application.
                    </label>
                    <div class="invalid-feedback">
                        <?= isset($errors['declaration']) ? htmlspecialchars($errors['declaration']) : 'You must confirm this declaration.' ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-5">
            <a href="<?= route('academics') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back: Academic Details
            </a>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary btn-lg px-4" id="docPreviewBtn">
                    <i class="bi bi-eye me-1"></i>Preview Documents
                </button>
                <?php if (!$viewOnly): ?>
                <button type="submit" class="btn btn-primary btn-lg px-5" id="docSaveBtn" disabled>
                    Save &amp; Continue <i class="bi bi-arrow-right ms-1"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="docPreviewModal" tabindex="-1" aria-labelledby="docPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="docPreviewModalLabel">
                    <i class="bi bi-eye me-2"></i>Preview Uploaded Documents
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:25%">Document</th>
                                <th style="width:30%">Preview</th>
                                <th>File Name</th>
                                <th>Size</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="docPreviewTableBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                    <i class="bi bi-check-circle me-1"></i>Looks Good
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Submission Modal -->
<div class="modal fade" id="docConfirmModal" tabindex="-1" aria-labelledby="docConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="docConfirmModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Document Submission
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="docConfirmBody"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-pencil me-1"></i>Review Again
                </button>
                <button type="button" class="btn btn-primary" id="docConfirmSubmitBtn">
                    <i class="bi bi-check-circle me-1"></i>Yes, Save &amp; Continue
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // Per-field validation config from PHP
    const docConfig = <?= json_encode(array_values($jsDocConfig)) ?>;

    // Existing uploaded files from DB (paths), used in view-only preview
    const existingDocs = <?= json_encode($existing ?: new stdClass()) ?>;
    const baseUrl      = <?= json_encode(rtrim(BASE_URL, '/')) ?>;
    const isViewOnly   = <?= $viewOnly ? 'true' : 'false' ?>;

    // Build lookup map: field -> config
    const configMap = {};
    docConfig.forEach(c => { configMap[c.field] = c; });

    let finalSubmit = false;
    const form       = document.getElementById('docsForm');
    const saveBtn    = document.getElementById('docSaveBtn');
    const declaration = document.getElementById('docDeclaration');

    // ─── Declaration checkbox toggle ───────────────────────────────
    function toggleSaveBtn() {
        if (saveBtn) saveBtn.disabled = !declaration?.checked;
    }
    declaration?.addEventListener('change', toggleSaveBtn);
    toggleSaveBtn(); // initial state

    // ─── Per-field instant file validation ─────────────────────────
    function validateFileInput(input) {
        const cfg     = configMap[input.id];
        if (!cfg) return true;
        const feedback = document.getElementById(input.id + '-feedback');
        if (!input.files || input.files.length === 0) {
            input.classList.remove('is-invalid', 'is-valid');
            if (feedback) feedback.textContent = '';
            return true; // no file chosen yet — not invalid
        }
        const file   = input.files[0];
        const ext    = file.name.split('.').pop().toLowerCase();
        const sizeKB = file.size / 1024;
        const maxKB  = cfg.maxKB;
        const types  = cfg.types;

        if (!types.includes(ext)) {
            const allowed = types.map(t => t.toUpperCase()).join('/');
            const msg = `Invalid type. Allowed: ${allowed}.`;
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            if (feedback) feedback.textContent = msg;
            return false;
        }
        if (sizeKB > maxKB) {
            const limitLabel = maxKB >= 1024 ? '1 MB' : '200 KB';
            const msg = `File too large. Max allowed: ${limitLabel}.`;
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            if (feedback) feedback.textContent = msg;
            return false;
        }
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
        if (feedback) feedback.textContent = '';
        return true;
    }

    // Attach change listeners to all file inputs
    document.querySelectorAll('input[type="file"]').forEach(function (input) {
        input.addEventListener('change', function () {
            validateFileInput(this);
        });
    });

    // ─── Form submit interceptor ───────────────────────────────────
    form.addEventListener('submit', function (e) {
        if (finalSubmit) return; // allowed through by confirm modal
        e.preventDefault();

        // Validate all file inputs
        let hasErrors = false;
        document.querySelectorAll('input[type="file"]').forEach(function (input) {
            if (!validateFileInput(input)) hasErrors = true;
        });

        if (!declaration.checked) {
            declaration.classList.add('is-invalid');
            hasErrors = true;
        } else {
            declaration.classList.remove('is-invalid');
        }

        if (hasErrors) {
            // Scroll to first error
            const firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        // Build confirm summary
        buildConfirmSummary();
        const confirmModal = new bootstrap.Modal(document.getElementById('docConfirmModal'));
        confirmModal.show();
    });

    // ─── Confirm submit button ─────────────────────────────────────
    document.getElementById('docConfirmSubmitBtn')?.addEventListener('click', function () {
        finalSubmit = true;
        form.submit();
    });

    // ─── Preview button ────────────────────────────────────────────
    document.getElementById('docPreviewBtn').addEventListener('click', function () {
        buildDocPreview();
        const modal = new bootstrap.Modal(document.getElementById('docPreviewModal'));
        modal.show();
    });

    function buildDocPreview() {
        const tbody = document.getElementById('docPreviewTableBody');
        tbody.innerHTML = '';

        let anyFile = false;

        docConfig.forEach(function (cfg) {
            const input = document.getElementById(cfg.field);
            if (!input) return;

            let row = '';

            if (input.files && input.files.length > 0) {
                // New file selected by user (edit mode)
                anyFile = true;
                const file    = input.files[0];
                const fileURL = URL.createObjectURL(file);
                const sizeKB  = (file.size / 1024).toFixed(1);
                const cfg2    = configMap[cfg.field];
                const maxKB   = cfg2 ? cfg2.maxKB : 1024;
                const ext     = file.name.split('.').pop().toLowerCase();
                const types   = cfg2 ? cfg2.types : [];
                const typeOk  = types.includes(ext);
                const sizeOk  = (file.size / 1024) <= maxKB;
                const statusBadge = (typeOk && sizeOk)
                    ? '<span class="badge bg-success">Valid</span>'
                    : '<span class="badge bg-danger">Error</span>';

                let previewCell = '';
                if (file.type.startsWith('image/')) {
                    previewCell = `<img src="${fileURL}" class="img-thumbnail" style="max-height:80px;max-width:120px;" alt="${file.name}">`;
                } else if (file.type === 'application/pdf') {
                    previewCell = `<iframe src="${fileURL}" style="width:120px;height:80px;border:1px solid #dee2e6;" title="PDF Preview"></iframe>`;
                } else {
                    previewCell = '<span class="text-muted">No preview</span>';
                }

                row = `<tr>
                    <td><strong>${cfg.label}</strong>${cfg.required ? ' <span class="text-danger">*</span>' : ''}</td>
                    <td>${previewCell}</td>
                    <td class="small">${file.name}</td>
                    <td class="small">${sizeKB} KB</td>
                    <td class="small">${file.type || ext.toUpperCase()}</td>
                    <td>${statusBadge}</td>
                </tr>`;

            } else if (existingDocs[cfg.field]) {
                // Already uploaded file from DB
                anyFile = true;
                const path = existingDocs[cfg.field];
                const ext  = path.split('.').pop().toLowerCase();
                const name = path.split('/').pop();
                const url  = baseUrl + '/' + path;

                let previewCell = '';
                if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
                    previewCell = `<img src="${url}" class="img-thumbnail" style="max-height:80px;max-width:120px;" alt="${cfg.label}">`;
                } else if (ext === 'pdf') {
                    previewCell = `<a href="${url}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i>View PDF</a>`;
                } else {
                    previewCell = `<a href="${url}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>View</a>`;
                }

                row = `<tr>
                    <td><strong>${cfg.label}</strong>${cfg.required ? ' <span class="text-danger">*</span>' : ''}</td>
                    <td>${previewCell}</td>
                    <td class="small">${name}</td>
                    <td class="small">—</td>
                    <td class="small">${ext.toUpperCase()}</td>
                    <td><span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Uploaded</span></td>
                </tr>`;

            } else {
                // No file at all
                row = `<tr class="table-light">
                    <td><strong>${cfg.label}</strong>${cfg.required ? ' <span class="text-danger">*</span>' : ''}</td>
                    <td colspan="4"><em class="text-muted">Not uploaded</em></td>
                    <td>${cfg.required ? '<span class="badge bg-warning text-dark">Missing</span>' : '<span class="badge bg-light text-muted border">Optional</span>'}</td>
                </tr>`;
            }
            tbody.insertAdjacentHTML('beforeend', row);
        });

        if (!anyFile) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No files uploaded yet.</td></tr>';
        }
    }

    function buildConfirmSummary() {
        const body = document.getElementById('docConfirmBody');
        let newFiles = 0;
        docConfig.forEach(cfg => {
            const input = document.getElementById(cfg.field);
            if (input && input.files && input.files.length > 0) newFiles++;
        });

        body.innerHTML = `
            <p>You are about to save your document uploads. Please confirm:</p>
            <ul>
                <li><strong>${newFiles}</strong> new file(s) will be uploaded.</li>
                <li>Any previously uploaded documents will be replaced if you selected a new file.</li>
            </ul>
            <div class="alert alert-warning mb-0">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Important:</strong> Ensure all uploaded documents are genuine.
                Submitting false documents may result in cancellation of your application.
            </div>`;
    }

});
</script>

<?php if ($viewOnly): ?>
<style>
#docsForm input, #docsForm select, #docsForm textarea,
#docsForm .form-check-input, #docsForm input[type="file"] {
    pointer-events: none !important;
    cursor: default !important;
    user-select: text;
}
#docsForm button:not(#docPreviewBtn) { pointer-events: none !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('docsForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            return false;
        }, true);
    }
});
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/views/layouts/main.php';
