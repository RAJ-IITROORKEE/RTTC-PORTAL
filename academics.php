<?php
define('APP_INIT', true);
require_once __DIR__ . '/config/init.php';

SecurityHelper::requireAuth();

$db     = db();
$userId = SessionHelper::get('user_id');
$errors = [];

// Step gate: must have completed step 1
$pstmt = $db->prepare("SELECT current_step FROM registration_progress WHERE user_id = ?");
$pstmt->bind_param('i', $userId);
$pstmt->execute();
$prog = $pstmt->get_result()->fetch_assoc();
$pstmt->close();
if (($prog['current_step'] ?? 0) < 1) {
    SessionHelper::setFlash('error', 'Please complete Personal Details first.');
    redirect(route('registration'));
}

// Fetch existing academic record
$stmt = $db->prepare("SELECT * FROM academic_details WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

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
    redirect(route('welcome'), [], 'error', 'Your academic details are in view-only mode. Request edit access from admin.');
}
// ──────────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SecurityHelper::verifyCsrf();

    $d = [];
    $textFields = [
        'hslc_pass_year','hslc_board','hslc_institute','hslc_division',
        'hslc_total_marks','hslc_obtained_marks','hslc_percentage','hslc_subjects',
        'hsslc_pass_year','hsslc_board','hsslc_institute','hsslc_division',
        'hsslc_total_marks','hsslc_obtained_marks','hsslc_percentage','hsslc_subjects',
        'bachelor_pass_year','bachelor_board','bachelor_institute','bachelor_division',
        'bachelor_total_marks','bachelor_obtained_marks','bachelor_percentage','bachelor_subjects',
        'masters_pass_year','masters_board','masters_institute','masters_division',
        'masters_total_marks','masters_obtained_marks','masters_percentage','masters_subjects',
        'gu_registered','gu_reg_no','gu_reg_year',
        'migrated','other_university',
        'gubedcet_rollno','gubedcet_marks','gubedcet_rank',
        'gubedcet_correct','gubedcet_wrong','gubedcet_unattempted',
        'gubedcet_name','gubedcet_category',
    ];
    foreach ($textFields as $f) {
        $d[$f] = SecurityHelper::sanitize($_POST[$f] ?? '');
    }
    $d['academic_declaration'] = isset($_POST['academic_declaration']) ? 1 : 0;

    // ── Validate required fields ─────────────────────────────────────────────
    $required = [
        'hslc_pass_year','hslc_board','hslc_institute','hslc_obtained_marks','hslc_total_marks',
        'hsslc_pass_year','hsslc_board','hsslc_institute','hsslc_obtained_marks','hsslc_total_marks',
        'bachelor_pass_year','bachelor_board','bachelor_institute','bachelor_obtained_marks','bachelor_total_marks',
        'gubedcet_rollno','gubedcet_marks','gubedcet_rank',
    ];
    foreach ($required as $r) {
        if (empty($d[$r])) $errors[$r] = 'Required.';
    }

    // GU fields required only if registered
    if ($d['gu_registered'] === 'yes') {
        if (empty($d['gu_reg_no']))   $errors['gu_reg_no']   = 'GU Registration Number is required.';
        if (empty($d['gu_reg_year'])) $errors['gu_reg_year'] = 'GU Registration Year is required.';
    }

    // Other university required only if migrated
    if ($d['migrated'] === 'yes' && empty($d['other_university'])) {
        $errors['other_university'] = 'Please enter the university name.';
    }

    if (empty($d['academic_declaration'])) {
        $errors['academic_declaration'] = 'You must confirm the entered information is correct.';
    }

    // ── Save ─────────────────────────────────────────────────────────────────
    if (empty($errors)) {
        $allFields = array_merge($textFields, ['academic_declaration']);
        $allValues = [];
        foreach ($textFields as $f) { $allValues[] = $d[$f]; }
        $allValues[] = $d['academic_declaration'];

        $types = str_repeat('s', count($textFields)) . 'i';

        if ($existing) {
            $sets = implode('=?,', $allFields) . '=?';
            $sql  = "UPDATE academic_details SET $sets WHERE user_id = ?";
            $stmt = $db->prepare($sql);
            $params = array_merge($allValues, [$userId]);
            $stmt->bind_param($types . 'i', ...$params);
        } else {
            $cols         = implode(',', $allFields);
            $placeholders = implode(',', array_fill(0, count($allFields), '?'));
            $sql          = "INSERT INTO academic_details (user_id, $cols) VALUES (?, $placeholders)";
            $stmt         = $db->prepare($sql);
            $params       = array_merge([$userId], $allValues);
            $stmt->bind_param('i' . $types, ...$params);
        }

        if ($stmt->execute()) {
            $stmt->close();
            $upd = $db->prepare("UPDATE registration_progress SET current_step = GREATEST(current_step, 2) WHERE user_id = ?");
            $upd->bind_param('i', $userId);
            $upd->execute();
            $upd->close();
            SessionHelper::setFlash('success', 'Academic details saved successfully!');
            redirect(route('documents'));
        } else {
            $errors['db'] = 'Database error. Please try again.';
            $stmt->close();
        }
    }
    $data = $d;
} else {
    $data = $existing ?: [];
}

// Helper: get stored value with fallback
function dv($data, $key, $default = '') {
    return htmlspecialchars($data[$key] ?? $default);
}

$pageTitle  = 'Academic Details - Step 2 - RTTC 2026';
// Use actual overall progress so stepper reflects true completion state
$currentStep = $prog['current_step'] ?? 2;
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
            <strong>Academic Details — View Only</strong><br>
            <span class="small">Your academic details have already been submitted and <strong>cannot be edited</strong> at this time.
            If you need to make corrections, please
            <a href="<?= route('request-query') ?>" class="alert-link fw-semibold">raise a query</a>
            and the admin may grant you temporary edit access.</span>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors) && !isset($errors['db'])): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Please fix the highlighted errors below.</div>
    <?php endif; ?>
    <?php if (!empty($errors['db'])): ?>
        <div class="alert alert-danger"><?= $errors['db'] ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= route('academics') ?>" id="academicForm" novalidate<?= $viewOnly ? ' data-viewonly="1"' : '' ?>>
        <?= SecurityHelper::csrfField() ?>

        <!-- ═══════════════════════════════════════════════════════ HSLC ══ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0"><i class="bi bi-mortarboard-fill me-2"></i>HSLC (Class X / Matriculation)</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Pass Year <span class="text-danger">*</span></label>
                        <input type="number" name="hslc_pass_year" id="hslc_pass_year"
                               class="form-control <?= isset($errors['hslc_pass_year']) ? 'is-invalid' : '' ?>"
                               value="<?= dv($data,'hslc_pass_year') ?>" min="1990" max="2027" placeholder="YYYY" required>
                        <div class="invalid-feedback" id="hslc_pass_year_err">
                            <?= $errors['hslc_pass_year'] ?? 'Enter year between 1990–2027.' ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Board/University <span class="text-danger">*</span></label>
                        <input type="text" name="hslc_board" id="hslc_board"
                               class="form-control <?= isset($errors['hslc_board']) ? 'is-invalid' : '' ?>"
                               value="<?= dv($data,'hslc_board') ?>" required>
                        <div class="invalid-feedback"><?= $errors['hslc_board'] ?? 'Required.' ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Institution/School <span class="text-danger">*</span></label>
                        <input type="text" name="hslc_institute" id="hslc_institute"
                               class="form-control <?= isset($errors['hslc_institute']) ? 'is-invalid' : '' ?>"
                               value="<?= dv($data,'hslc_institute') ?>" required>
                        <div class="invalid-feedback"><?= $errors['hslc_institute'] ?? 'Required.' ?></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Division/Grade</label>
                        <select name="hslc_division" id="hslc_division" class="form-select">
                            <option value="">-- Select --</option>
                            <?php foreach (['Distinction','1st Division','2nd Division','3rd Division','Pass','CGPA'] as $div): ?>
                                <option value="<?= $div ?>" <?= ($data['hslc_division'] ?? '') === $div ? 'selected' : '' ?>><?= $div ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Total Marks <span class="text-danger">*</span></label>
                        <input type="number" name="hslc_total_marks" id="hslc_total_marks"
                               class="form-control <?= isset($errors['hslc_total_marks']) ? 'is-invalid' : '' ?>"
                               value="<?= dv($data,'hslc_total_marks') ?>" min="1" step="0.01" required>
                        <div class="invalid-feedback"><?= $errors['hslc_total_marks'] ?? 'Required.' ?></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Obtained Marks <span class="text-danger">*</span></label>
                        <input type="number" name="hslc_obtained_marks" id="hslc_obtained_marks"
                               class="form-control <?= isset($errors['hslc_obtained_marks']) ? 'is-invalid' : '' ?>"
                               value="<?= dv($data,'hslc_obtained_marks') ?>" min="0" step="0.01" required>
                        <div class="invalid-feedback"><?= $errors['hslc_obtained_marks'] ?? 'Required.' ?></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Percentage (%)</label>
                        <input type="text" name="hslc_percentage" id="hslc_percentage"
                               class="form-control bg-light"
                               value="<?= dv($data,'hslc_percentage') ?>" readonly placeholder="Auto-calculated">
                        <div class="invalid-feedback" id="hslc_pct_err">Percentage must be between 1–100.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Main Subjects</label>
                        <input type="text" name="hslc_subjects" class="form-control"
                               value="<?= dv($data,'hslc_subjects') ?>" placeholder="e.g. English, Mathematics, Science">
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════ HSSLC ══ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0"><i class="bi bi-mortarboard-fill me-2"></i>HSSLC (Class XII / Higher Secondary)</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Pass Year <span class="text-danger">*</span></label>
                        <input type="number" name="hsslc_pass_year" id="hsslc_pass_year"
                               class="form-control <?= isset($errors['hsslc_pass_year']) ? 'is-invalid' : '' ?>"
                               value="<?= dv($data,'hsslc_pass_year') ?>" min="1990" max="2027" placeholder="YYYY" required>
                        <div class="invalid-feedback" id="hsslc_pass_year_err">
                            <?= $errors['hsslc_pass_year'] ?? 'Enter year between 1990–2027.' ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Board/University <span class="text-danger">*</span></label>
                        <input type="text" name="hsslc_board" id="hsslc_board"
                               class="form-control <?= isset($errors['hsslc_board']) ? 'is-invalid' : '' ?>"
                               value="<?= dv($data,'hsslc_board') ?>" required>
                        <div class="invalid-feedback"><?= $errors['hsslc_board'] ?? 'Required.' ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Institution/School <span class="text-danger">*</span></label>
                        <input type="text" name="hsslc_institute" id="hsslc_institute"
                               class="form-control <?= isset($errors['hsslc_institute']) ? 'is-invalid' : '' ?>"
                               value="<?= dv($data,'hsslc_institute') ?>" required>
                        <div class="invalid-feedback"><?= $errors['hsslc_institute'] ?? 'Required.' ?></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Division/Grade</label>
                        <select name="hsslc_division" id="hsslc_division" class="form-select">
                            <option value="">-- Select --</option>
                            <?php foreach (['Distinction','1st Division','2nd Division','3rd Division','Pass','CGPA'] as $div): ?>
                                <option value="<?= $div ?>" <?= ($data['hsslc_division'] ?? '') === $div ? 'selected' : '' ?>><?= $div ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Total Marks <span class="text-danger">*</span></label>
                        <input type="number" name="hsslc_total_marks" id="hsslc_total_marks"
                               class="form-control <?= isset($errors['hsslc_total_marks']) ? 'is-invalid' : '' ?>"
                               value="<?= dv($data,'hsslc_total_marks') ?>" min="1" step="0.01" required>
                        <div class="invalid-feedback"><?= $errors['hsslc_total_marks'] ?? 'Required.' ?></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Obtained Marks <span class="text-danger">*</span></label>
                        <input type="number" name="hsslc_obtained_marks" id="hsslc_obtained_marks"
                               class="form-control <?= isset($errors['hsslc_obtained_marks']) ? 'is-invalid' : '' ?>"
                               value="<?= dv($data,'hsslc_obtained_marks') ?>" min="0" step="0.01" required>
                        <div class="invalid-feedback"><?= $errors['hsslc_obtained_marks'] ?? 'Required.' ?></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Percentage (%)</label>
                        <input type="text" name="hsslc_percentage" id="hsslc_percentage"
                               class="form-control bg-light"
                               value="<?= dv($data,'hsslc_percentage') ?>" readonly placeholder="Auto-calculated">
                        <div class="invalid-feedback" id="hsslc_pct_err">Percentage must be between 1–100.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Main Subjects</label>
                        <input type="text" name="hsslc_subjects" class="form-control"
                               value="<?= dv($data,'hsslc_subjects') ?>" placeholder="e.g. Physics, Chemistry, Biology">
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════ BACHELOR'S DEGREE ══ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0"><i class="bi bi-mortarboard-fill me-2"></i>Bachelor's Degree (B.A./B.Sc./B.Com./Other)</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Pass Year <span class="text-danger">*</span></label>
                        <input type="number" name="bachelor_pass_year" id="bachelor_pass_year"
                               class="form-control <?= isset($errors['bachelor_pass_year']) ? 'is-invalid' : '' ?>"
                               value="<?= dv($data,'bachelor_pass_year') ?>" min="1990" max="2027" placeholder="YYYY" required>
                        <div class="invalid-feedback" id="bachelor_pass_year_err">
                            <?= $errors['bachelor_pass_year'] ?? 'Enter year between 1990–2027.' ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Board/University <span class="text-danger">*</span></label>
                        <input type="text" name="bachelor_board" id="bachelor_board"
                               class="form-control <?= isset($errors['bachelor_board']) ? 'is-invalid' : '' ?>"
                               value="<?= dv($data,'bachelor_board') ?>" required>
                        <div class="invalid-feedback"><?= $errors['bachelor_board'] ?? 'Required.' ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Institution/University <span class="text-danger">*</span></label>
                        <input type="text" name="bachelor_institute" id="bachelor_institute"
                               class="form-control <?= isset($errors['bachelor_institute']) ? 'is-invalid' : '' ?>"
                               value="<?= dv($data,'bachelor_institute') ?>" required>
                        <div class="invalid-feedback"><?= $errors['bachelor_institute'] ?? 'Required.' ?></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Division/Grade</label>
                        <select name="bachelor_division" id="bachelor_division" class="form-select">
                            <option value="">-- Select --</option>
                            <?php foreach (['Distinction','1st Division','2nd Division','3rd Division','Pass','CGPA'] as $div): ?>
                                <option value="<?= $div ?>" <?= ($data['bachelor_division'] ?? '') === $div ? 'selected' : '' ?>><?= $div ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Total Marks <span class="text-danger">*</span></label>
                        <input type="number" name="bachelor_total_marks" id="bachelor_total_marks"
                               class="form-control <?= isset($errors['bachelor_total_marks']) ? 'is-invalid' : '' ?>"
                               value="<?= dv($data,'bachelor_total_marks') ?>" min="1" step="0.01" required>
                        <div class="invalid-feedback"><?= $errors['bachelor_total_marks'] ?? 'Required.' ?></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Obtained Marks <span class="text-danger">*</span></label>
                        <input type="number" name="bachelor_obtained_marks" id="bachelor_obtained_marks"
                               class="form-control <?= isset($errors['bachelor_obtained_marks']) ? 'is-invalid' : '' ?>"
                               value="<?= dv($data,'bachelor_obtained_marks') ?>" min="0" step="0.01" required>
                        <div class="invalid-feedback"><?= $errors['bachelor_obtained_marks'] ?? 'Required.' ?></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Percentage (%)</label>
                        <input type="text" name="bachelor_percentage" id="bachelor_percentage"
                               class="form-control bg-light"
                               value="<?= dv($data,'bachelor_percentage') ?>" readonly placeholder="Auto-calculated">
                        <div class="invalid-feedback" id="bachelor_pct_err">Percentage must be between 1–100.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Main Subjects</label>
                        <input type="text" name="bachelor_subjects" class="form-control"
                               value="<?= dv($data,'bachelor_subjects') ?>" placeholder="e.g. History, Political Science, Education">
                    </div>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════ MASTER'S DEGREE (OPTIONAL) ══ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center" style="background-color:#e9ecef;">
                <h5 class="mb-0 text-muted">
                    <i class="bi bi-mortarboard-fill me-2"></i>Master's Degree
                    <small class="text-muted fw-normal">(If Applicable)</small>
                </h5>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleMasters">
                    <span id="mastersToggleText"><?= !empty($data['masters_pass_year']) ? 'Hide' : 'Show' ?></span>
                </button>
            </div>
            <div class="card-body p-4" id="mastersSection" style="<?= !empty($data['masters_pass_year']) ? '' : 'display:none;' ?>">
                <div class="alert alert-info small py-2 mb-3">
                    <i class="bi bi-info-circle me-1"></i>This section is optional. Leave blank if not applicable.
                </div>
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Pass Year</label>
                        <input type="number" name="masters_pass_year" id="masters_pass_year"
                               class="form-control <?= isset($errors['masters_pass_year']) ? 'is-invalid' : '' ?>"
                               value="<?= dv($data,'masters_pass_year') ?>" min="1990" max="2027" placeholder="YYYY">
                        <div class="invalid-feedback" id="masters_pass_year_err">Enter year between 1990–2027.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Board/University</label>
                        <input type="text" name="masters_board" id="masters_board" class="form-control"
                               value="<?= dv($data,'masters_board') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Institution/University</label>
                        <input type="text" name="masters_institute" id="masters_institute" class="form-control"
                               value="<?= dv($data,'masters_institute') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Division/Grade</label>
                        <select name="masters_division" id="masters_division" class="form-select">
                            <option value="">-- Select --</option>
                            <?php foreach (['Distinction','1st Division','2nd Division','3rd Division','Pass','CGPA'] as $div): ?>
                                <option value="<?= $div ?>" <?= ($data['masters_division'] ?? '') === $div ? 'selected' : '' ?>><?= $div ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Total Marks</label>
                        <input type="number" name="masters_total_marks" id="masters_total_marks"
                               class="form-control" value="<?= dv($data,'masters_total_marks') ?>" min="1" step="0.01">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Obtained Marks</label>
                        <input type="number" name="masters_obtained_marks" id="masters_obtained_marks"
                               class="form-control" value="<?= dv($data,'masters_obtained_marks') ?>" min="0" step="0.01">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Percentage (%)</label>
                        <input type="text" name="masters_percentage" id="masters_percentage"
                               class="form-control bg-light"
                               value="<?= dv($data,'masters_percentage') ?>" readonly placeholder="Auto-calculated">
                        <div class="invalid-feedback" id="masters_pct_err">Percentage must be between 1–100.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Main Subjects</label>
                        <input type="text" name="masters_subjects" class="form-control"
                               value="<?= dv($data,'masters_subjects') ?>" placeholder="e.g. Education, Psychology">
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════ GAUHATI UNIVERSITY ══ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0"><i class="bi bi-building me-2"></i>Gauhati University</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">

                    <!-- Already registered in GU? -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">Already registered in Gauhati University?</label>
                        <div class="d-flex gap-4 mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="gu_registered" id="guRegYes" value="yes"
                                       <?= ($data['gu_registered'] ?? '') === 'yes' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="guRegYes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="gu_registered" id="guRegNo" value="no"
                                       <?= ($data['gu_registered'] ?? 'no') === 'no' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="guRegNo">No</label>
                            </div>
                        </div>
                    </div>

                    <!-- GU reg fields – shown only when yes -->
                    <div id="guRegFields" style="<?= ($data['gu_registered'] ?? '') === 'yes' ? '' : 'display:none;' ?>" class="col-12">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">GU Registration Number <span class="text-danger">*</span></label>
                                <input type="text" name="gu_reg_no" id="gu_reg_no"
                                       class="form-control <?= isset($errors['gu_reg_no']) ? 'is-invalid' : '' ?>"
                                       value="<?= dv($data,'gu_reg_no') ?>">
                                <div class="invalid-feedback"><?= $errors['gu_reg_no'] ?? 'Required when registered in GU.' ?></div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">GU Registration Year <span class="text-danger">*</span></label>
                                <input type="number" name="gu_reg_year" id="gu_reg_year"
                                       class="form-control <?= isset($errors['gu_reg_year']) ? 'is-invalid' : '' ?>"
                                       value="<?= dv($data,'gu_reg_year') ?>" min="2000" max="2027" placeholder="YYYY">
                                <div class="invalid-feedback"><?= $errors['gu_reg_year'] ?? 'Required when registered in GU.' ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Migrated from GU? -->
                    <div class="col-12 mt-3">
                        <label class="form-label fw-semibold">Migrated from Gauhati University?</label>
                        <div class="d-flex gap-4 mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="migrated" id="migratedYes" value="yes"
                                       <?= ($data['migrated'] ?? '') === 'yes' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="migratedYes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="migrated" id="migratedNo" value="no"
                                       <?= ($data['migrated'] ?? 'no') === 'no' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="migratedNo">No</label>
                            </div>
                        </div>
                    </div>

                    <!-- Other university – shown only when migrated yes -->
                    <div id="otherUnivFields" style="<?= ($data['migrated'] ?? '') === 'yes' ? '' : 'display:none;' ?>" class="col-12">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Other University Name <span class="text-danger">*</span></label>
                                <input type="text" name="other_university" id="other_university"
                                       class="form-control <?= isset($errors['other_university']) ? 'is-invalid' : '' ?>"
                                       value="<?= dv($data,'other_university') ?>" placeholder="Name of the university you migrated from">
                                <div class="invalid-feedback"><?= $errors['other_university'] ?? 'Required when migrated.' ?></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════ GUBEDCET 2026 ══ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text-fill me-2"></i>GUBEDCET 2026 Details</h5>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info small py-2 mb-3">
                    <i class="bi bi-search me-1"></i>Enter your 10-digit Roll Number — marks and other details will be filled automatically.
                </div>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">GUBEDCET Roll Number <span class="text-danger">*</span></label>
                        <input type="text" name="gubedcet_rollno" id="gubedcet_rollno"
                               class="form-control <?= isset($errors['gubedcet_rollno']) ? 'is-invalid' : '' ?>"
                               value="<?= dv($data,'gubedcet_rollno') ?>" maxlength="10"
                               placeholder="10-digit roll no." required>
                        <div class="invalid-feedback" id="rollno_err">
                            <?= $errors['gubedcet_rollno'] ?? 'Enter a valid 10-digit roll number.' ?>
                        </div>
                        <div id="rollno_notfound" class="text-danger small mt-1" style="display:none;">
                            Roll number not found in GUBEDCET 2026 results.
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Candidate Name</label>
                        <input type="text" name="gubedcet_name" id="gubedcet_name"
                               class="form-control bg-light" value="<?= dv($data,'gubedcet_name') ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Total Marks <span class="text-danger">*</span></label>
                        <input type="text" name="gubedcet_marks" id="gubedcet_marks"
                               class="form-control bg-light <?= isset($errors['gubedcet_marks']) ? 'is-invalid' : '' ?>"
                               value="<?= dv($data,'gubedcet_marks') ?>" readonly required>
                        <div class="invalid-feedback"><?= $errors['gubedcet_marks'] ?? 'Required.' ?></div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Rank <span class="text-danger">*</span></label>
                        <input type="text" name="gubedcet_rank" id="gubedcet_rank"
                               class="form-control bg-light <?= isset($errors['gubedcet_rank']) ? 'is-invalid' : '' ?>"
                               value="<?= dv($data,'gubedcet_rank') ?>" readonly required>
                        <div class="invalid-feedback"><?= $errors['gubedcet_rank'] ?? 'Required.' ?></div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Category</label>
                        <input type="text" name="gubedcet_category" id="gubedcet_category"
                               class="form-control bg-light" value="<?= dv($data,'gubedcet_category') ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Correct Marks</label>
                        <input type="text" name="gubedcet_correct" id="gubedcet_correct"
                               class="form-control bg-light" value="<?= dv($data,'gubedcet_correct') ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Wrong Marks</label>
                        <input type="text" name="gubedcet_wrong" id="gubedcet_wrong"
                               class="form-control bg-light" value="<?= dv($data,'gubedcet_wrong') ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Unattempted</label>
                        <input type="text" name="gubedcet_unattempted" id="gubedcet_unattempted"
                               class="form-control bg-light" value="<?= dv($data,'gubedcet_unattempted') ?>" readonly>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════ DECLARATION ══ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="form-check">
                    <input class="form-check-input <?= isset($errors['academic_declaration']) ? 'is-invalid' : '' ?>"
                           type="checkbox" id="academicDeclaration" name="academic_declaration" value="1"
                           <?= !empty($data['academic_declaration']) ? 'checked' : '' ?> required>
                    <label class="form-check-label" for="academicDeclaration">
                        I hereby declare that all academic information entered above is authentic and matches my original mark sheets and certificates.
                    </label>
                    <?php if (isset($errors['academic_declaration'])): ?>
                        <div class="invalid-feedback d-block"><?= $errors['academic_declaration'] ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════ BUTTONS ══ -->
        <div class="d-flex justify-content-between mb-5">
            <a href="<?= route('registration') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back: Personal Details
            </a>
            <div class="d-flex gap-2">
                <button type="button" id="acadPreviewBtn" class="btn btn-outline-primary btn-lg px-4">
                    <i class="bi bi-eye me-1"></i>Preview
                </button>
                <?php if (!$viewOnly): ?>
                <button type="button" id="acadSaveBtn" class="btn btn-primary btn-lg px-5"
                        <?= empty($data['academic_declaration']) ? 'disabled' : '' ?>>
                    Save & Continue <i class="bi bi-arrow-right ms-1"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- ══════════════════════════════════ PREVIEW MODAL ══ -->
<div class="modal fade" id="acadPreviewModal" tabindex="-1" aria-labelledby="acadPreviewLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="acadPreviewLabel">
                    <i class="bi bi-eye me-2"></i>Academic Details Preview
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="acadPreviewBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════ CONFIRM MODAL ══ -->
<div class="modal fade" id="acadConfirmModal" tabindex="-1" aria-labelledby="acadConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="acadConfirmLabel">
                    <i class="bi bi-shield-check me-2"></i>Confirm & Save Academic Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="acadConfirmBody"><!-- filled by JS --></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-pencil me-1"></i>Review Again
                </button>
                <button type="button" id="acadConfirmSubmitBtn" class="btn btn-primary">
                    <i class="bi bi-check-circle me-1"></i>Yes, Save & Continue
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Constants ────────────────────────────────────────────────────────────
    const JSON_URL   = '<?= rtrim(APP_URL, '/') ?>/assets/data/gubedcet_2026.json';
    const YEAR_MIN   = 1990;
    const YEAR_MAX   = 2027;

    // ── Core refs ────────────────────────────────────────────────────────────
    const form       = document.getElementById('academicForm');
    const saveBtn    = document.getElementById('acadSaveBtn');
    const declCheck  = document.getElementById('academicDeclaration');

    const previewModal = new bootstrap.Modal(document.getElementById('acadPreviewModal'));
    const confirmModal = new bootstrap.Modal(document.getElementById('acadConfirmModal'));

    let gubedcetData = [];
    let finalSubmit  = false;

    // ── Load GUBEDCET JSON ───────────────────────────────────────────────────
    fetch(JSON_URL)
        .then(r => r.ok ? r.json() : Promise.reject('fetch failed'))
        .then(json => {
            if (json && json['Table 1'] && Array.isArray(json['Table 1'])) {
                gubedcetData = json['Table 1'].map(entry => {
                    const clean = {};
                    for (const k in entry) {
                        clean[k.replace(/\r/g,'').trim()] = typeof entry[k] === 'string' ? entry[k].trim() : entry[k];
                    }
                    return clean;
                });
            }
        })
        .catch(err => console.warn('GUBEDCET JSON load failed:', err));

    // ── Declaration checkbox → enable/disable Save btn ───────────────────────
    function toggleSaveBtn() {
        if (saveBtn) saveBtn.disabled = !declCheck.checked;
    }
    declCheck?.addEventListener('change', toggleSaveBtn);
    toggleSaveBtn();

    // ── Master's degree toggle ───────────────────────────────────────────────
    document.getElementById('toggleMasters')?.addEventListener('click', function () {
        const sec = document.getElementById('mastersSection');
        const txt = document.getElementById('mastersToggleText');
        if (sec.style.display === 'none') { sec.style.display = ''; txt.textContent = 'Hide'; }
        else { sec.style.display = 'none'; txt.textContent = 'Show'; }
    });

    // ── GU registered toggle ────────────────────────────────────────────────
    document.querySelectorAll('input[name="gu_registered"]').forEach(r => {
        r.addEventListener('change', function () {
            document.getElementById('guRegFields').style.display = this.value === 'yes' ? '' : 'none';
        });
    });

    // ── Migration toggle ─────────────────────────────────────────────────────
    document.querySelectorAll('input[name="migrated"]').forEach(r => {
        r.addEventListener('change', function () {
            document.getElementById('otherUnivFields').style.display = this.value === 'yes' ? '' : 'none';
        });
    });

    // ══════════════════════════════════════════════════════════════════════════
    // ── Auto-percentage calculation + instant validation ──────────────────────
    // ══════════════════════════════════════════════════════════════════════════

    const sections = [
        { prefix: 'hslc',      label: 'HSLC' },
        { prefix: 'hsslc',     label: 'HSSLC' },
        { prefix: 'bachelor',  label: "Bachelor's" },
        { prefix: 'masters',   label: "Master's" },
    ];

    function calcPercent(prefix) {
        const totalEl    = document.getElementById(prefix + '_total_marks');
        const obtainedEl = document.getElementById(prefix + '_obtained_marks');
        const pctEl      = document.getElementById(prefix + '_percentage');
        const pctErrEl   = document.getElementById(prefix + '_pct_err');

        if (!totalEl || !obtainedEl || !pctEl) return;

        const total    = parseFloat(totalEl.value);
        const obtained = parseFloat(obtainedEl.value);

        if (isNaN(total) || isNaN(obtained) || total <= 0) {
            pctEl.value = '';
            pctEl.classList.remove('is-invalid');
            return;
        }

        if (obtained > total) {
            pctEl.value = '';
            obtainedEl.classList.add('is-invalid');
            obtainedEl.nextElementSibling && (obtainedEl.nextElementSibling.textContent = 'Obtained marks cannot exceed total marks.');
            return;
        } else {
            obtainedEl.classList.remove('is-invalid');
        }

        const pct = (obtained / total) * 100;

        if (pct < 1 || pct > 100) {
            pctEl.value = pct.toFixed(2);
            pctEl.classList.add('is-invalid');
            if (pctErrEl) pctErrEl.style.display = 'block';
        } else {
            pctEl.value = pct.toFixed(2);
            pctEl.classList.remove('is-invalid');
            if (pctErrEl) pctErrEl.style.display = 'none';
        }
    }

    sections.forEach(({ prefix }) => {
        ['total_marks', 'obtained_marks'].forEach(field => {
            document.getElementById(prefix + '_' + field)
                ?.addEventListener('input', () => calcPercent(prefix));
        });
        // Trigger on load if values already exist (edit mode)
        calcPercent(prefix);
    });

    // ── Pass-year instant validation ─────────────────────────────────────────
    const yearFields = ['hslc_pass_year','hsslc_pass_year','bachelor_pass_year','masters_pass_year'];

    function validateYear(el) {
        const val = parseInt(el.value, 10);
        const errEl = document.getElementById(el.id + '_err');
        if (el.value === '') {
            el.classList.remove('is-invalid','is-valid');
            return;
        }
        if (isNaN(val) || val < YEAR_MIN || val > YEAR_MAX) {
            el.classList.add('is-invalid');
            el.classList.remove('is-valid');
            if (errEl) errEl.textContent = `Year must be between ${YEAR_MIN}–${YEAR_MAX}.`;
        } else {
            el.classList.remove('is-invalid');
            el.classList.add('is-valid');
        }
    }

    yearFields.forEach(id => {
        const el = document.getElementById(id);
        el?.addEventListener('input', () => validateYear(el));
        if (el?.value) validateYear(el); // validate pre-filled values
    });

    // ══════════════════════════════════════════════════════════════════════════
    // ── GUBEDCET Roll Number Autofill ─────────────────────────────────────────
    // ══════════════════════════════════════════════════════════════════════════

    function clearGubedcetFields() {
        ['gubedcet_name','gubedcet_marks','gubedcet_rank',
         'gubedcet_correct','gubedcet_wrong','gubedcet_unattempted','gubedcet_category']
            .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    }

    function fillGubedcetFields(student) {
        const map = {
            'gubedcet_name'        : 'Name',
            'gubedcet_marks'       : 'Total Marks',
            'gubedcet_rank'        : 'Rank',
            'gubedcet_correct'     : 'Correct Marks',
            'gubedcet_wrong'       : 'Wrong Marks',
            'gubedcet_category'    : 'Category',
        };
        for (const [id, key] of Object.entries(map)) {
            const el = document.getElementById(id);
            if (el) el.value = student[key] || '';
        }
        // Unattempted is not in JSON; clear it
        const ua = document.getElementById('gubedcet_unattempted');
        if (ua) ua.value = '';
    }

    const rollnoInput    = document.getElementById('gubedcet_rollno');
    const rollnoNotFound = document.getElementById('rollno_notfound');

    rollnoInput?.addEventListener('input', function () {
        const val = this.value.trim();
        rollnoNotFound.style.display = 'none';

        if (val.length === 10 && /^\d{10}$/.test(val)) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');

            if (gubedcetData.length > 0) {
                const student = gubedcetData.find(s => s['Roll No'] === val);
                if (student) {
                    fillGubedcetFields(student);
                } else {
                    clearGubedcetFields();
                    rollnoNotFound.style.display = 'block';
                }
            }
        } else {
            this.classList.remove('is-valid');
            if (val.length > 0) this.classList.add('is-invalid');
            clearGubedcetFields();
        }
    });

    // Validate pre-filled roll number on load
    if (rollnoInput?.value.trim().length === 10) {
        rollnoInput.classList.add('is-valid');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ── Preview builder ───────────────────────────────────────────────────────
    // ══════════════════════════════════════════════════════════════════════════

    function gv(id) {
        const el = document.getElementById(id) || form?.elements?.[id.replace(/_/g,'_')];
        return (el?.value || '').trim();
    }

    function getRadio(name) {
        const checked = form?.querySelector(`input[name="${name}"]:checked`);
        return checked ? checked.value : '';
    }

    function makePreviewSection(title, rows) {
        const validRows = rows.filter(([, v]) => v);
        if (!validRows.length) return '';

        let html = `<div class="mb-4">
            <h6 class="fw-bold border-bottom pb-2 mb-3 text-primary">${title}</h6>
            <table class="table table-sm table-bordered mb-0"><tbody>`;
        validRows.forEach(([label, value]) => {
            html += `<tr>
                <th class="bg-light text-muted" style="width:40%">${label}</th>
                <td>${value}</td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        return html;
    }

    function buildAcadPreview() {
        const body = document.getElementById('acadPreviewBody');
        if (!body) return;

        const guReg    = getRadio('gu_registered');
        const migrated = getRadio('migrated');

        let html = '';

        html += makePreviewSection('HSLC (Class X)', [
            ['Pass Year',       gv('hslc_pass_year')],
            ['Board/University',gv('hslc_board')],
            ['Institution',     gv('hslc_institute')],
            ['Division/Grade',  gv('hslc_division')],
            ['Total Marks',     gv('hslc_total_marks')],
            ['Obtained Marks',  gv('hslc_obtained_marks')],
            ['Percentage',      gv('hslc_percentage') ? gv('hslc_percentage') + '%' : ''],
            ['Subjects',        gv('hslc_subjects')],
        ]);

        html += makePreviewSection('HSSLC (Class XII)', [
            ['Pass Year',       gv('hsslc_pass_year')],
            ['Board/University',gv('hsslc_board')],
            ['Institution',     gv('hsslc_institute')],
            ['Division/Grade',  gv('hsslc_division')],
            ['Total Marks',     gv('hsslc_total_marks')],
            ['Obtained Marks',  gv('hsslc_obtained_marks')],
            ['Percentage',      gv('hsslc_percentage') ? gv('hsslc_percentage') + '%' : ''],
            ['Subjects',        gv('hsslc_subjects')],
        ]);

        html += makePreviewSection("Bachelor's Degree", [
            ['Pass Year',       gv('bachelor_pass_year')],
            ['Board/University',gv('bachelor_board')],
            ['Institution',     gv('bachelor_institute')],
            ['Division/Grade',  gv('bachelor_division')],
            ['Total Marks',     gv('bachelor_total_marks')],
            ['Obtained Marks',  gv('bachelor_obtained_marks')],
            ['Percentage',      gv('bachelor_percentage') ? gv('bachelor_percentage') + '%' : ''],
            ['Subjects',        gv('bachelor_subjects')],
        ]);

        if (gv('masters_pass_year') || gv('masters_board')) {
            html += makePreviewSection("Master's Degree", [
                ['Pass Year',       gv('masters_pass_year')],
                ['Board/University',gv('masters_board')],
                ['Institution',     gv('masters_institute')],
                ['Division/Grade',  gv('masters_division')],
                ['Total Marks',     gv('masters_total_marks')],
                ['Obtained Marks',  gv('masters_obtained_marks')],
                ['Percentage',      gv('masters_percentage') ? gv('masters_percentage') + '%' : ''],
                ['Subjects',        gv('masters_subjects')],
            ]);
        }

        const guRows = [['Registered in GU', guReg === 'yes' ? 'Yes' : 'No']];
        if (guReg === 'yes') {
            guRows.push(['GU Reg. Number', gv('gu_reg_no')], ['GU Reg. Year', gv('gu_reg_year')]);
        }
        guRows.push(['Migrated from GU', migrated === 'yes' ? 'Yes' : 'No']);
        if (migrated === 'yes') {
            guRows.push(['Other University', gv('other_university')]);
        }
        html += makePreviewSection('Gauhati University', guRows);

        html += makePreviewSection('GUBEDCET 2026', [
            ['Roll Number',   gv('gubedcet_rollno')],
            ['Candidate Name',gv('gubedcet_name')],
            ['Total Marks',   gv('gubedcet_marks')],
            ['Rank',          gv('gubedcet_rank')],
            ['Category',      gv('gubedcet_category')],
            ['Correct Marks', gv('gubedcet_correct')],
            ['Wrong Marks',   gv('gubedcet_wrong')],
        ]);

        body.innerHTML = html || '<p class="text-muted">No data entered yet.</p>';
    }

    // ── Confirm modal summary ────────────────────────────────────────────────
    function buildConfirmSummary() {
        const body = document.getElementById('acadConfirmBody');
        if (!body) return;
        body.innerHTML = `
            <p class="mb-3">Please verify the key details before saving:</p>
            <table class="table table-sm table-bordered mb-0">
                <tbody>
                    <tr><th class="bg-light text-muted" style="width:40%">HSLC % </th><td>${gv('hslc_percentage') || '—'}%</td></tr>
                    <tr><th class="bg-light text-muted">HSSLC %</th><td>${gv('hsslc_percentage') || '—'}%</td></tr>
                    <tr><th class="bg-light text-muted">Bachelor's %</th><td>${gv('bachelor_percentage') || '—'}%</td></tr>
                    ${gv('masters_pass_year') ? `<tr><th class="bg-light text-muted">Master's %</th><td>${gv('masters_percentage') || '—'}%</td></tr>` : ''}
                    <tr><th class="bg-light text-muted">GU Registered</th><td>${getRadio('gu_registered') === 'yes' ? 'Yes — ' + gv('gu_reg_no') : 'No'}</td></tr>
                    <tr><th class="bg-light text-muted">GUBEDCET Roll No.</th><td>${gv('gubedcet_rollno') || '—'}</td></tr>
                    <tr><th class="bg-light text-muted">GUBEDCET Marks</th><td>${gv('gubedcet_marks') || '—'}</td></tr>
                    <tr><th class="bg-light text-muted">GUBEDCET Rank</th><td>${gv('gubedcet_rank') || '—'}</td></tr>
                </tbody>
            </table>
            <div class="alert alert-warning mt-3 mb-0 small">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                Once saved, academic information <strong>cannot be updated</strong>. Ensure all entries are correct.
            </div>`;
    }

    // ── Form validity check ──────────────────────────────────────────────────
    function handleSaveAttempt() {
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        buildConfirmSummary();
        confirmModal.show();
    }

    // ── Event bindings ───────────────────────────────────────────────────────
    document.getElementById('acadPreviewBtn')?.addEventListener('click', function () {
        buildAcadPreview();
        previewModal.show();
    });

    saveBtn?.addEventListener('click', function () {
        handleSaveAttempt();
    });

    form?.addEventListener('submit', function (e) {
        if (finalSubmit) return;
        e.preventDefault();
        handleSaveAttempt();
    });

    document.getElementById('acadConfirmSubmitBtn')?.addEventListener('click', function () {
        finalSubmit = true;
        form.submit();
    });

}); // end DOMContentLoaded
</script>

<?php if ($viewOnly): ?>
<style>
#academicForm input, #academicForm select, #academicForm textarea,
#academicForm .form-check-input {
    pointer-events: none !important;
    cursor: default !important;
    user-select: text;
}
#academicForm button:not(#acadPreviewBtn) { pointer-events: none !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('academicForm');
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
