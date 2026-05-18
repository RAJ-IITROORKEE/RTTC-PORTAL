<?php
define('APP_INIT', true);
require_once __DIR__ . '/../../config/init.php';
SecurityHelper::requireAdminAuth();

$db = db();
$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('admin.students');

// Explicit column aliases — prevents u.created_at being silently overridden
// by d.created_at / p.created_at when those rows are NULL (LEFT JOIN).
$stmt = $db->prepare("
    SELECT
        u.id AS uid, u.username, u.email, u.phone, u.is_verified, u.is_active,
        u.created_at AS reg_date,
        p.firstname, p.middlename, p.lastname,
        p.fathersname, p.foccupation, p.fcontact, p.fqualifications,
        p.mothersname, p.moccupation, p.mcontact, p.mqualification,
        p.spousename, p.soccupation, p.scontact, p.squalification,
        p.dob, p.age, p.gender, p.blood_group, p.religion, p.caste,
        p.ews, p.obc_ncl, p.pwd, p.income,
        p.permanent_address, p.present_address, p.emergency_contact,
        a.hslc_board, a.hslc_pass_year, a.hslc_total_marks, a.hslc_obtained_marks, a.hslc_percentage, a.hslc_division, a.hslc_institute,
        a.hsslc_board, a.hsslc_pass_year, a.hsslc_total_marks, a.hsslc_obtained_marks, a.hsslc_percentage, a.hsslc_division, a.hsslc_institute,
        a.bachelor_board, a.bachelor_pass_year, a.bachelor_total_marks, a.bachelor_obtained_marks, a.bachelor_percentage, a.bachelor_division, a.bachelor_institute,
        a.masters_board, a.masters_pass_year, a.masters_total_marks, a.masters_obtained_marks, a.masters_percentage, a.masters_division, a.masters_institute,
        a.gu_reg_no, a.gu_reg_year, a.gubedcet_rollno, a.gubedcet_marks, a.gubedcet_rank,
        a.gubedcet_correct, a.gubedcet_wrong, a.gubedcet_unattempted, a.gubedcet_name, a.gubedcet_category,
        a.gu_registered, a.migrated, a.other_university,
        d.photo, d.signature,
        d.hslc_marksheet, d.hsslc_marksheet, d.degree_marksheet, d.masters_marksheet,
        d.caste_cert, d.ews_cert, d.pwd_cert, d.obc_ncl_cert,
        d.gubedcet_admit_card, d.gubedcet_result_sheet,
        d.status AS doc_status,
        pay.razorpay_payment_id, pay.amount, pay.created_at AS payment_date, pay.status AS pay_status,
        rp.current_step, rp.is_submitted
    FROM users u
    LEFT JOIN personal_details p   ON p.user_id  = u.id
    LEFT JOIN academic_details a   ON a.user_id  = u.id
    LEFT JOIN documents d          ON d.user_id  = u.id
    LEFT JOIN payment pay          ON pay.user_id = u.id AND pay.status = 'success'
    LEFT JOIN registration_progress rp ON rp.user_id = u.id
    WHERE u.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    SessionHelper::setFlash('error', 'Student not found.');
    redirect('admin.students');
}

$step        = (int)($data['current_step'] ?? 0);
$stepLabels  = ['Not Started', 'Personal Done', 'Academic Done', 'Docs Uploaded', 'Payment Done'];
$stepIcons   = ['dash-circle-fill', 'person-check-fill', 'mortarboard-fill', 'file-earmark-check-fill', 'credit-card-fill'];
$stepColors  = ['secondary', 'info', 'primary', 'warning', 'success'];

$fullName = trim(($data['firstname'] ?? '') . ' ' . ($data['middlename'] ?? '') . ' ' . ($data['lastname'] ?? ''));
if (!$fullName) $fullName = $data['username'];
$appId = 'RTTC-' . str_pad($data['uid'], 5, '0', STR_PAD_LEFT);

$pageTitle  = 'Student: ' . $fullName . ' — Admin RTTC 2026';
$activePage = 'students';
$breadcrumb = [
    ['label' => 'Students', 'url' => route('admin.students')],
    ['label' => $fullName],
];
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-person-badge me-2 text-primary"></i>Student Details</h4>
    <a href="<?= route('admin.students') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<?php include BASE_PATH . '/views/partials/flash.php'; ?>

<!-- ===== Profile Header Card ===== -->
<div class="card border-0 shadow mb-4 overflow-hidden"
     style="background:linear-gradient(135deg,#27276d 0%,#4a4ab0 100%);">
    <div class="card-body text-white px-4 py-4">
        <div class="row align-items-center g-3">

            <!-- Avatar / Photo -->
            <div class="col-auto">
                <?php if (!empty($data['photo'])): ?>
                    <img src="<?= BASE_URL . '/' . htmlspecialchars($data['photo']) ?>"
                         class="rounded-circle border border-3 border-white shadow"
                         style="width:100px;height:100px;object-fit:cover;" alt="Profile Photo">
                <?php else: ?>
                    <div class="rounded-circle border border-3 border-white d-flex align-items-center justify-content-center"
                         style="width:100px;height:100px;background:rgba(255,255,255,.15);">
                        <i class="bi bi-person-fill text-white" style="font-size:3rem;opacity:.8;"></i>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Name / contact info -->
            <div class="col">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h4 class="mb-0 fw-bold"><?= htmlspecialchars($fullName) ?></h4>
                    <span class="badge bg-white text-dark fw-normal small"><?= $appId ?></span>
                    <?php if ($data['is_verified']): ?>
                        <span class="badge bg-success"><i class="bi bi-patch-check me-1"></i>Verified</span>
                    <?php endif; ?>
                </div>
                <p class="mb-1 opacity-75 small">
                    <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($data['email']) ?>
                    &nbsp;·&nbsp;
                    <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($data['phone']) ?>
                </p>
                <?php $rd = $data['reg_date'] ?? null; ?>
                <p class="mb-0 small" style="opacity:.65;">
                    <i class="bi bi-calendar3 me-1"></i>Registered:
                    <?= $rd ? date('d M Y, h:i A', strtotime($rd)) : 'N/A' ?>
                </p>
            </div>

            <!-- Progress badge -->
            <div class="col-md-auto text-md-end">
                <div class="d-flex flex-column gap-2 align-items-md-end">
                    <span class="badge fs-6 px-3 py-2 bg-<?= $stepColors[$step] ?? 'secondary' ?> shadow-sm">
                        <i class="bi bi-<?= $stepIcons[$step] ?? 'dash-circle-fill' ?> me-1"></i>
                        <?= $stepLabels[$step] ?? 'N/A' ?>
                    </span>
                    <?php if ($data['is_submitted']): ?>
                    <span class="badge fs-6 px-3 py-2 bg-success shadow-sm">
                        <i class="bi bi-check-circle-fill me-1"></i>Application Submitted
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($data['razorpay_payment_id'])): ?>
                    <span class="badge fs-6 px-3 py-2 shadow-sm" style="background:#ffd700;color:#333;">
                        <i class="bi bi-credit-card-fill me-1"></i>Payment Done
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Step Progress Bar -->
        <div class="mt-4 pt-3" style="border-top:1px solid rgba(255,255,255,.25);">
            <div class="row g-0 text-center">
                <?php
                $allSteps = [
                    ['label' => 'Registered', 'icon' => 'person-plus-fill',          'done' => true],
                    ['label' => 'Personal',   'icon' => 'person-lines-fill',          'done' => $step >= 1],
                    ['label' => 'Academic',   'icon' => 'book-fill',                  'done' => $step >= 2],
                    ['label' => 'Documents',  'icon' => 'file-earmark-check-fill',    'done' => $step >= 3],
                    ['label' => 'Payment',    'icon' => 'credit-card-fill',           'done' => $step >= 4],
                ];
                foreach ($allSteps as $ps): ?>
                <div class="col">
                    <div class="d-flex flex-column align-items-center gap-1">
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width:38px;height:38px;
                                    background:<?= $ps['done'] ? 'rgba(255,255,255,.9)' : 'rgba(255,255,255,.18)' ?>;">
                            <i class="bi bi-<?= $ps['icon'] ?>"
                               style="font-size:1.1rem;color:<?= $ps['done'] ? '#27276d' : 'rgba(255,255,255,.4)' ?>"></i>
                        </div>
                        <span style="font-size:.68rem;opacity:<?= $ps['done'] ? '1' : '.45' ?>">
                            <?= $ps['label'] ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ===== Personal Details ===== -->
<?php if (!empty($data['firstname'])): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom py-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-person-fill me-2 text-primary"></i>Personal Details</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php $pFields = [
                'First Name'        => htmlspecialchars($data['firstname'] ?? '-'),
                'Middle Name'       => htmlspecialchars($data['middlename'] ?: '-'),
                'Last Name'         => htmlspecialchars($data['lastname'] ?? '-'),
                'Date of Birth'     => ($data['dob'] && $data['dob'] !== '0000-00-00') ? date('d M Y', strtotime($data['dob'])) : '-',
                'Age'               => $data['age'] ? htmlspecialchars($data['age']) . ' yrs' : '-',
                'Gender'            => htmlspecialchars($data['gender'] ?: '-'),
                'Blood Group'       => htmlspecialchars($data['blood_group'] ?: '-'),
                'Religion'          => htmlspecialchars($data['religion'] ?: '-'),
                'Category / Caste'  => htmlspecialchars($data['caste'] ?: '-'),
                'EWS'               => $data['ews']     ? '<span class="badge bg-info">Yes</span>'               : '<span class="text-muted">No</span>',
                'OBC-NCL'           => $data['obc_ncl'] ? '<span class="badge bg-info">Yes</span>'               : '<span class="text-muted">No</span>',
                'PWD'               => $data['pwd']     ? '<span class="badge bg-warning text-dark">Yes</span>'  : '<span class="text-muted">No</span>',
                'Annual Income'     => htmlspecialchars($data['income'] ?: '-'),
                'Emergency Contact' => htmlspecialchars($data['emergency_contact'] ?: '-'),
            ]; ?>
            <?php foreach ($pFields as $lbl => $val): ?>
            <div class="col-md-3 col-6">
                <div class="text-muted small"><?= $lbl ?></div>
                <div class="fw-semibold"><?= $val ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($data['permanent_address'])): ?>
        <hr class="my-3">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="text-muted small">Permanent Address</div>
                <div class="fw-semibold"><?= nl2br(htmlspecialchars($data['permanent_address'])) ?></div>
            </div>
            <?php if (!empty($data['present_address'])): ?>
            <div class="col-md-6">
                <div class="text-muted small">Present Address</div>
                <div class="fw-semibold"><?= nl2br(htmlspecialchars($data['present_address'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($data['fathersname'])): ?>
        <hr class="my-3">
        <p class="fw-semibold text-muted small mb-2 text-uppercase" style="letter-spacing:.05em;">Guardian / Family Info</p>
        <div class="row g-3">
            <?php $guardians = [
                "Father's Name"           => $data['fathersname'],
                "Father's Occupation"     => $data['foccupation'],
                "Father's Contact"        => $data['fcontact'],
                "Father's Qualification"  => $data['fqualifications'],
                "Mother's Name"           => $data['mothersname'],
                "Mother's Occupation"     => $data['moccupation'],
                "Mother's Contact"        => $data['mcontact'],
                "Mother's Qualification"  => $data['mqualification'],
            ];
            if (!empty($data['spousename'])) {
                $guardians['Spouse Name']         = $data['spousename'];
                $guardians['Spouse Occupation']   = $data['soccupation'];
                $guardians['Spouse Contact']      = $data['scontact'];
            }
            foreach ($guardians as $gl => $gv): if (!$gv) continue; ?>
            <div class="col-md-3 col-6">
                <div class="text-muted small"><?= $gl ?></div>
                <div class="fw-semibold"><?= htmlspecialchars($gv) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ===== Academic Details ===== -->
<?php if (!empty($data['hslc_board'])): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom py-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-mortarboard-fill me-2 text-primary"></i>Academic Details</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-primary">
                    <tr>
                        <th>Exam</th><th>Board / University</th><th>Institute</th>
                        <th>Pass Year</th><th>Total</th><th>Obtained</th><th>%</th><th>Division</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ([
                        'HSLC'       => 'hslc',
                        'HSSLC'      => 'hsslc',
                        "Bachelor's" => 'bachelor',
                        "Master's"   => 'masters',
                    ] as $ename => $pfx): ?>
                    <?php if (!empty($data[$pfx . '_board'])): ?>
                    <tr>
                        <td class="fw-semibold"><?= $ename ?></td>
                        <td><?= htmlspecialchars($data[$pfx . '_board']           ?? '-') ?></td>
                        <td><?= htmlspecialchars($data[$pfx . '_institute']       ?? '-') ?></td>
                        <td><?= htmlspecialchars($data[$pfx . '_pass_year']       ?? '-') ?></td>
                        <td><?= htmlspecialchars($data[$pfx . '_total_marks']     ?? '-') ?></td>
                        <td><?= htmlspecialchars($data[$pfx . '_obtained_marks']  ?? '-') ?></td>
                        <td><?= htmlspecialchars($data[$pfx . '_percentage']      ?? '-') ?>%</td>
                        <td><?= htmlspecialchars($data[$pfx . '_division']        ?? '-') ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="row g-3">
            <?php $gFields = [
                'GU Reg. No.'        => $data['gu_reg_no']             ?? '',
                'GU Reg. Year'       => $data['gu_reg_year']           ?? '',
                'GU Registered'      => (!empty($data['gu_registered']) && $data['gu_registered'] !== 'no')  ? '<span class="badge bg-success">Yes</span>'             : '<span class="text-muted">No</span>',
                'Migrated'           => (!empty($data['migrated'])      && $data['migrated'] !== 'no')        ? '<span class="badge bg-warning text-dark">Yes</span>'   : '<span class="text-muted">No</span>',
                'Other University'   => htmlspecialchars($data['other_university']  ?? ''),
                'GUBEDCET Roll No.'  => htmlspecialchars($data['gubedcet_rollno']   ?? ''),
                'GUBEDCET Name'      => htmlspecialchars($data['gubedcet_name']     ?? ''),
                'GUBEDCET Category'  => htmlspecialchars($data['gubedcet_category'] ?? ''),
                'GUBEDCET Marks'     => htmlspecialchars($data['gubedcet_marks']    ?? ''),
                'GUBEDCET Rank'      => htmlspecialchars($data['gubedcet_rank']     ?? ''),
                'Correct'            => htmlspecialchars($data['gubedcet_correct']  ?? ''),
                'Wrong'              => htmlspecialchars($data['gubedcet_wrong']    ?? ''),
                'Unattempted'        => htmlspecialchars($data['gubedcet_unattempted'] ?? ''),
            ];
            foreach ($gFields as $gl => $gv): if ($gv === '' || $gv === null) continue; ?>
            <div class="col-md-3 col-6">
                <div class="text-muted small"><?= $gl ?></div>
                <div class="fw-semibold"><?= $gv ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===== Documents ===== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
        <h6 class="fw-bold mb-0"><i class="bi bi-file-earmark-check-fill me-2 text-primary"></i>Documents</h6>
        <?php if (!empty($data['doc_status'])):
            $ds = $data['doc_status'];
            $dsBadge = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$ds] ?? 'secondary';
        ?>
        <span class="badge bg-<?= $dsBadge ?> px-3 py-2">
            <i class="bi bi-shield-check me-1"></i>Status: <?= ucfirst($ds) ?>
        </span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php $docList = [
            'photo'                => ['Photo',               'bi-person-square'],
            'signature'            => ['Signature',           'bi-pen-fill'],
            'hslc_marksheet'       => ['HSLC Marksheet',      'bi-file-earmark-text-fill'],
            'hsslc_marksheet'      => ['HSSLC Marksheet',     'bi-file-earmark-text-fill'],
            'degree_marksheet'     => ['Degree Marksheet',    'bi-file-earmark-text-fill'],
            'masters_marksheet'    => ['Masters Marksheet',   'bi-file-earmark-text-fill'],
            'caste_cert'           => ['Caste Certificate',   'bi-file-earmark-medical-fill'],
            'ews_cert'             => ['EWS Certificate',     'bi-file-earmark-medical-fill'],
            'pwd_cert'             => ['PWD Certificate',     'bi-file-earmark-medical-fill'],
            'obc_ncl_cert'         => ['OBC-NCL Cert.',       'bi-file-earmark-medical-fill'],
            'gubedcet_admit_card'  => ['GUBEDCET Admit Card', 'bi-card-heading'],
            'gubedcet_result_sheet'=> ['GUBEDCET Result',     'bi-file-earmark-bar-graph-fill'],
        ]; ?>
        <div class="row g-3">
            <?php foreach ($docList as $dcol => [$dlabel, $dicon]): ?>
            <div class="col-lg-2 col-md-3 col-4">
                <?php if (!empty($data[$dcol])): ?>
                    <a href="<?= BASE_URL . '/' . htmlspecialchars($data[$dcol]) ?>" target="_blank"
                       class="text-decoration-none d-block text-center p-3 rounded-3 h-100"
                       style="background:#e8f5e9;border:1.5px solid #a5d6a7;transition:.2s;"
                       onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
                        <i class="bi <?= $dicon ?> text-success d-block fs-3 mb-1"></i>
                        <small class="fw-semibold text-success d-block lh-sm" style="font-size:.7rem"><?= $dlabel ?></small>
                        <small class="text-muted mt-1 d-block" style="font-size:.65rem;"><i class="bi bi-box-arrow-up-right"></i> View</small>
                    </a>
                <?php else: ?>
                    <div class="text-center p-3 rounded-3 h-100"
                         style="background:#fafafa;border:1.5px dashed #dee2e6;">
                        <i class="bi <?= $dicon ?> text-muted d-block fs-3 mb-1"></i>
                        <small class="text-muted d-block fw-semibold lh-sm" style="font-size:.7rem"><?= $dlabel ?></small>
                        <small class="text-danger mt-1 d-block" style="font-size:.65rem;">Not uploaded</small>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ===== Payment ===== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom py-3">
        <h6 class="fw-bold mb-0">
            <i class="bi bi-credit-card-fill me-2 text-<?= !empty($data['razorpay_payment_id']) ? 'success' : 'secondary' ?>"></i>
            Payment
        </h6>
    </div>
    <div class="card-body">
        <?php if (!empty($data['razorpay_payment_id'])): ?>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-muted small">Payment ID</div>
                <div class="fw-semibold font-monospace small"><?= htmlspecialchars($data['razorpay_payment_id']) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Amount Paid</div>
                <div class="fw-bold text-success fs-5">₹<?= number_format(($data['amount'] ?? 0) / 100, 2) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Payment Date</div>
                <div class="fw-semibold">
                    <?= $data['payment_date'] ? date('d M Y, h:i A', strtotime($data['payment_date'])) : '-' ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="d-flex align-items-center gap-2 py-2 text-muted">
            <i class="bi bi-x-circle fs-4 text-danger"></i>
            <span>No successful payment record found for this student.</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include BASE_PATH . '/admin/layouts/admin.php';
