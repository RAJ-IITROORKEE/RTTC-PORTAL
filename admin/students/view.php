<?php
define('APP_INIT', true);
require_once __DIR__ . '/../../config/init.php';
SecurityHelper::requireAdminAuth();

$db = db();
$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('admin.students');

// Fetch everything
$stmt = $db->prepare("SELECT u.*, p.*, a.*, d.*, pay.razorpay_payment_id, pay.amount, pay.created_at as payment_date, pay.status as pay_status, rp.current_step, rp.is_submitted
    FROM users u
    LEFT JOIN personal_details p ON p.user_id = u.id
    LEFT JOIN academic_details a ON a.user_id = u.id
    LEFT JOIN documents d ON d.user_id = u.id
    LEFT JOIN payment pay ON pay.user_id = u.id
    LEFT JOIN registration_progress rp ON rp.user_id = u.id
    WHERE u.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    SessionHelper::setFlash('error', 'Student not found.');
    redirect('admin.students');
}

$pageTitle  = 'View Student - Admin RTTC 2026';
$activePage = 'students';
$breadcrumb = [['label' => 'Students', 'url' => route('admin.students')], ['label' => $data['username']]];
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-person-badge me-2 text-primary"></i>Student Details</h4>
    <a href="<?= route('admin.students') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<?php include BASE_PATH . '/views/partials/flash.php'; ?>

<!-- Student Header -->
<div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #27276d, #4a4ab0);">
    <div class="card-body text-white py-4 px-4">
        <div class="row align-items-center">
            <div class="col-md-2 text-center">
                <?php if (!empty($data['photo'])): ?>
                    <img src="<?= BASE_URL . '/' . $data['photo'] ?>" alt="Photo" class="rounded-circle border border-3 border-white" style="width:90px;height:90px;object-fit:cover;">
                <?php else: ?>
                    <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center mx-auto" style="width:90px;height:90px;font-size:2.5rem;">
                        <i class="bi bi-person"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-7">
                <h4 class="mb-1"><?= htmlspecialchars(trim(($data['firstname'] ?? '') . ' ' . ($data['middlename'] ?? '') . ' ' . ($data['lastname'] ?? $data['username']))) ?></h4>
                <p class="mb-0 opacity-75">
                    <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($data['email']) ?>
                    &nbsp;|&nbsp;
                    <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($data['phone']) ?>
                </p>
                <p class="mb-0 opacity-75 small mt-1">Registered: <?= date('d M Y', strtotime($data['created_at'])) ?></p>
            </div>
            <div class="col-md-3 text-md-end mt-3 mt-md-0">
                <?php $step = $data['current_step'] ?? 0;
                $sl = ['Not Started','Personal Done','Academic Done','Docs Done','Payment Done'];
                $sc = ['secondary','info','warning','primary','success'];
                ?>
                <span class="badge bg-<?= $sc[$step] ?? 'secondary' ?> fs-6 px-3 py-2 d-block mb-2">
                    <?= $sl[$step] ?? 'N/A' ?>
                </span>
                <?php if ($data['is_submitted']): ?>
                    <span class="badge bg-success fs-6 px-3 py-2 d-block">
                        <i class="bi bi-check-circle me-1"></i>Submitted
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Personal Details -->
<?php if (!empty($data['firstname'])): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom pt-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-person-fill me-2 text-primary"></i>Personal Details</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php $pf = [
                'Father\'s Name' => $data['fathersname'] ?? '',
                'Mother\'s Name' => $data['mothersname'] ?? '',
                'DOB' => $data['dob'] ?? '',
                'Age' => $data['age'] ?? '',
                'Gender' => $data['gender'] ?? '',
                'Blood Group' => $data['blood_group'] ?? '',
                'Religion' => $data['religion'] ?? '',
                'Category' => $data['caste'] ?? '',
                'EWS' => ($data['ews'] ?? 0) ? 'Yes' : 'No',
                'OBC-NCL' => ($data['obc_ncl'] ?? 0) ? 'Yes' : 'No',
                'PWD' => ($data['pwd'] ?? 0) ? 'Yes' : 'No',
                'Annual Income' => $data['income'] ?? '',
                'Permanent Address' => $data['permanent_address'] ?? '',
                'Emergency Contact' => $data['emergency_contact'] ?? '',
            ]; ?>
            <?php foreach ($pf as $label => $val): ?>
            <div class="col-md-3 col-6">
                <small class="text-muted"><?= $label ?></small>
                <div class="fw-semibold"><?= htmlspecialchars($val ?: '-') ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Academic Details -->
<?php if (!empty($data['hslc_board'])): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom pt-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-mortarboard-fill me-2 text-primary"></i>Academic Details</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered">
                <thead class="table-primary">
                    <tr><th>Exam</th><th>Board</th><th>Pass Year</th><th>Total</th><th>Obtained</th><th>%</th><th>Division</th></tr>
                </thead>
                <tbody>
                    <?php foreach (['HSLC' => 'hslc', 'HSSLC' => 'hsslc', "Bachelor's" => 'bachelor', "Master's" => 'masters'] as $ename => $prefix): ?>
                    <?php if (!empty($data[$prefix . '_board'])): ?>
                    <tr>
                        <td><?= $ename ?></td>
                        <td><?= htmlspecialchars($data[$prefix . '_board'] ?? '') ?></td>
                        <td><?= htmlspecialchars($data[$prefix . '_pass_year'] ?? '') ?></td>
                        <td><?= htmlspecialchars($data[$prefix . '_total_marks'] ?? '') ?></td>
                        <td><?= htmlspecialchars($data[$prefix . '_obtained_marks'] ?? '') ?></td>
                        <td><?= htmlspecialchars($data[$prefix . '_percentage'] ?? '') ?>%</td>
                        <td><?= htmlspecialchars($data[$prefix . '_division'] ?? '') ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="row g-2">
            <div class="col-md-3"><small class="text-muted">GU Reg No.</small><div class="fw-semibold"><?= htmlspecialchars($data['gu_reg_no'] ?? '-') ?></div></div>
            <div class="col-md-3"><small class="text-muted">GUBEDCET Roll</small><div class="fw-semibold"><?= htmlspecialchars($data['gubedcet_rollno'] ?? '-') ?></div></div>
            <div class="col-md-3"><small class="text-muted">GUBEDCET Marks</small><div class="fw-semibold"><?= htmlspecialchars($data['gubedcet_marks'] ?? '-') ?></div></div>
            <div class="col-md-3"><small class="text-muted">GUBEDCET Rank</small><div class="fw-semibold"><?= htmlspecialchars($data['gubedcet_rank'] ?? '-') ?></div></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Documents -->
<?php if (!empty($data['photo']) || !empty($data['hslc_marksheet'])): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom pt-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-file-earmark-check me-2 text-primary"></i>Documents</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php $docs = [
                'Photo' => 'photo', 'Signature' => 'signature',
                'HSLC Marksheet' => 'hslc_marksheet', 'HSSLC Marksheet' => 'hsslc_marksheet',
                'Degree Marksheet' => 'degree_marksheet', 'Masters Marksheet' => 'masters_marksheet',
                'Caste Cert.' => 'caste_cert', 'EWS Cert.' => 'ews_cert',
                'PWD Cert.' => 'pwd_cert', 'OBC-NCL Cert.' => 'obc_ncl_cert',
                'GUBEDCET Admit' => 'gubedcet_admit_card', 'GUBEDCET Result' => 'gubedcet_result_sheet',
            ]; ?>
            <?php foreach ($docs as $dlabel => $dcol): ?>
            <div class="col-md-2 col-4 text-center">
                <?php if (!empty($data[$dcol])): ?>
                    <a href="<?= BASE_URL . '/' . $data[$dcol] ?>" target="_blank" class="text-decoration-none">
                        <div class="badge bg-success p-2 w-100 mb-1">
                            <i class="bi bi-check-circle d-block fs-5 mb-1"></i>
                            <small><?= $dlabel ?></small>
                        </div>
                    </a>
                <?php else: ?>
                    <div class="badge bg-light text-secondary p-2 w-100 mb-1">
                        <i class="bi bi-x-circle d-block fs-5 mb-1"></i>
                        <small><?= $dlabel ?></small>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Payment -->
<?php if (!empty($data['razorpay_payment_id'])): ?>
<div class="card border-0 shadow-sm mb-4 border-success">
    <div class="card-header bg-success text-white pt-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-credit-card-fill me-2"></i>Payment Details</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4"><small class="text-muted">Payment ID</small><div class="fw-semibold"><?= htmlspecialchars($data['razorpay_payment_id']) ?></div></div>
            <div class="col-md-4"><small class="text-muted">Amount</small><div class="fw-semibold">₹<?= number_format(($data['amount'] ?? 0) / 100, 2) ?></div></div>
            <div class="col-md-4"><small class="text-muted">Payment Date</small><div class="fw-semibold"><?= date('d M Y h:i A', strtotime($data['payment_date'])) ?></div></div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include BASE_PATH . '/admin/layouts/admin.php';
