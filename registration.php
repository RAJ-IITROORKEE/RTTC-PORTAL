<?php
define('APP_INIT', true);
require_once __DIR__ . '/config/init.php';

SecurityHelper::requireAuth();

$db     = db();
$userId = SessionHelper::get('user_id');
$errors = [];
$religionOptions = ['Hindu', 'Muslim', 'Christian', 'Sikh', 'Buddhist', 'Jain', 'Others'];

// Check if already have personal details
$stmt = $db->prepare("SELECT * FROM personal_details WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SecurityHelper::verifyCsrf();

    $d = [];
    $fields = [
        'firstname','middlename','lastname','fathersname','foccupation','fcontact','fqualifications',
        'mothersname','moccupation','mcontact','mqualification',
        'spousename','soccupation','scontact','squalification',
        'dob','gender','blood_group','religion','other_religion','caste','permanent_address','present_address',
        'emergency_contact','income'
    ];
    foreach ($fields as $f) {
        $d[$f] = SecurityHelper::sanitize($_POST[$f] ?? '');
    }
    $d['ews']     = isset($_POST['ews']) ? 1 : 0;
    $d['obc_ncl'] = isset($_POST['obc_ncl']) ? 1 : 0;
    $d['pwd']     = isset($_POST['pwd']) ? 1 : 0;
    $d['declaration_confirm'] = isset($_POST['declaration_confirm']) ? 1 : 0;

    if (($d['religion'] ?? '') === 'Others') {
        if (empty($d['other_religion'])) {
            $errors['other_religion'] = 'Please enter your religion.';
        } else {
            $d['religion'] = $d['other_religion'];
        }
    }

    // Compute age from DOB
    $d['age'] = '';
    if (!empty($d['dob'])) {
        $birth = new DateTime($d['dob']);
        $today = new DateTime();
        $d['age'] = $today->diff($birth)->y;
    }

    // Validate required fields
    $required = ['firstname','lastname','fathersname','mothersname','dob','gender','blood_group','religion','caste','permanent_address','present_address','emergency_contact','income'];
    foreach ($required as $r) {
        if (empty($d[$r])) {
            $errors[$r] = 'This field is required.';
        }
    }

    if (empty($d['declaration_confirm'])) {
        $errors['declaration_confirm'] = 'You must declare that the entered information is authentic.';
    }

    if (empty($errors)) {
        if ($existing) {
            // Update
            $stmt = $db->prepare("UPDATE personal_details SET
                firstname=?, middlename=?, lastname=?, fathersname=?, foccupation=?, fcontact=?, fqualifications=?,
                mothersname=?, moccupation=?, mcontact=?, mqualification=?,
                spousename=?, soccupation=?, scontact=?, squalification=?,
                dob=?, age=?, gender=?, blood_group=?, religion=?, caste=?, ews=?, obc_ncl=?, pwd=?,
                permanent_address=?, present_address=?, emergency_contact=?, income=?
                WHERE user_id=?");
            $stmt->bind_param('sssssssssssssssssssssiiissssi',
                $d['firstname'],$d['middlename'],$d['lastname'],$d['fathersname'],$d['foccupation'],$d['fcontact'],$d['fqualifications'],
                $d['mothersname'],$d['moccupation'],$d['mcontact'],$d['mqualification'],
                $d['spousename'],$d['soccupation'],$d['scontact'],$d['squalification'],
                $d['dob'],$d['age'],$d['gender'],$d['blood_group'],$d['religion'],$d['caste'],$d['ews'],$d['obc_ncl'],$d['pwd'],
                $d['permanent_address'],$d['present_address'],$d['emergency_contact'],$d['income'],
                $userId
            );
        } else {
            // Insert
            $stmt = $db->prepare("INSERT INTO personal_details
                (user_id, firstname, middlename, lastname, fathersname, foccupation, fcontact, fqualifications,
                 mothersname, moccupation, mcontact, mqualification,
                 spousename, soccupation, scontact, squalification,
                 dob, age, gender, blood_group, religion, caste, ews, obc_ncl, pwd,
                 permanent_address, present_address, emergency_contact, income)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('isssssssssssssssssssssiiissss',
                $userId,
                $d['firstname'],$d['middlename'],$d['lastname'],$d['fathersname'],$d['foccupation'],$d['fcontact'],$d['fqualifications'],
                $d['mothersname'],$d['moccupation'],$d['mcontact'],$d['mqualification'],
                $d['spousename'],$d['soccupation'],$d['scontact'],$d['squalification'],
                $d['dob'],$d['age'],$d['gender'],$d['blood_group'],$d['religion'],$d['caste'],$d['ews'],$d['obc_ncl'],$d['pwd'],
                $d['permanent_address'],$d['present_address'],$d['emergency_contact'],$d['income']
            );
        }

        if ($stmt->execute()) {
            $stmt->close();
            // Update progress to at least step 1
            $pstmt = $db->prepare("UPDATE registration_progress SET current_step = GREATEST(current_step, 1) WHERE user_id = ?");
            $pstmt->bind_param('i', $userId);
            $pstmt->execute();
            $pstmt->close();

            SessionHelper::setFlash('success', 'Personal details saved successfully!');
            redirect(route('academics'));
        } else {
            $errors['db'] = 'Database error. Please try again.';
        }
    }

    if (!empty($d['religion']) && !in_array($d['religion'], $religionOptions, true)) {
        $d['other_religion'] = $d['religion'];
        $d['religion'] = 'Others';
    }

    $data = $d;
} else {
    $data = $existing ?: [];
    if (!empty($data['religion']) && !in_array($data['religion'], $religionOptions, true)) {
        $data['other_religion'] = $data['religion'];
        $data['religion'] = 'Others';
    }
}

$pageTitle = 'Personal Details - Step 1 - RTTC 2026';
$currentStep = 1;
ob_start();
?>

<div class="container py-4">
    <div class="row mb-3">
        <div class="col">
            <?php include __DIR__ . '/views/partials/stepper.php'; ?>
        </div>
    </div>

    <?php include __DIR__ . '/views/partials/flash.php'; ?>

    <form method="POST" action="<?= route('registration') ?>" id="personalForm" novalidate>
        <?= SecurityHelper::csrfField() ?>

        <!-- Personal Info Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0"><i class="bi bi-person-fill me-2"></i>Personal Information</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="firstname" class="form-control <?= isset($errors['firstname']) ? 'is-invalid' : '' ?>"
                               value="<?= htmlspecialchars($data['firstname'] ?? '') ?>" required>
                        <?php if (isset($errors['firstname'])): ?><div class="invalid-feedback"><?= $errors['firstname'] ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Middle Name</label>
                        <input type="text" name="middlename" class="form-control"
                               value="<?= htmlspecialchars($data['middlename'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="lastname" class="form-control <?= isset($errors['lastname']) ? 'is-invalid' : '' ?>"
                               value="<?= htmlspecialchars($data['lastname'] ?? '') ?>" required>
                        <?php if (isset($errors['lastname'])): ?><div class="invalid-feedback"><?= $errors['lastname'] ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                        <input type="date" name="dob" class="form-control <?= isset($errors['dob']) ? 'is-invalid' : '' ?>"
                               value="<?= htmlspecialchars($data['dob'] ?? '') ?>" id="dobField" required>
                        <?php if (isset($errors['dob'])): ?><div class="invalid-feedback"><?= $errors['dob'] ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Age</label>
                        <input type="text" name="age_display" id="ageDisplay" class="form-control"
                               value="<?= htmlspecialchars($data['age'] ?? '') ?>" readonly placeholder="Auto-calculated">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Gender <span class="text-danger">*</span></label>
                        <select name="gender" class="form-select <?= isset($errors['gender']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Select --</option>
                            <?php foreach (['Male','Female','Transgender'] as $g): ?>
                                <option value="<?= $g ?>" <?= ($data['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['gender'])): ?><div class="invalid-feedback"><?= $errors['gender'] ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Blood Group <span class="text-danger">*</span></label>
                        <select name="blood_group" class="form-select <?= isset($errors['blood_group']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Select --</option>
                            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                                <option value="<?= $bg ?>" <?= ($data['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['blood_group'])): ?><div class="invalid-feedback"><?= $errors['blood_group'] ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Religion <span class="text-danger">*</span></label>
                        <select name="religion" class="form-select <?= isset($errors['religion']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($religionOptions as $rel): ?>
                                <option value="<?= $rel ?>" <?= ($data['religion'] ?? '') === $rel ? 'selected' : '' ?>><?= $rel ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['religion'])): ?><div class="invalid-feedback"><?= $errors['religion'] ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-4" id="otherReligionWrap" style="display: none;">
                        <label class="form-label">Please Specify Religion <span class="text-danger">*</span></label>
                        <input type="text" name="other_religion" id="otherReligionInput"
                               class="form-control <?= isset($errors['other_religion']) ? 'is-invalid' : '' ?>"
                               value="<?= htmlspecialchars($data['other_religion'] ?? '') ?>"
                               placeholder="Type your religion">
                        <?php if (isset($errors['other_religion'])): ?><div class="invalid-feedback"><?= $errors['other_religion'] ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Caste/Category <span class="text-danger">*</span></label>
                        <select name="caste" class="form-select <?= isset($errors['caste']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Select --</option>
                            <?php foreach (['General','OBC','SC','STP','STH','Others'] as $cat): ?>
                                <option value="<?= $cat ?>" <?= ($data['caste'] ?? '') === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['caste'])): ?><div class="invalid-feedback"><?= $errors['caste'] ?></div><?php endif; ?>
                    </div>

                    <!-- Special Categories -->
                    <div class="col-12">
                        <label class="form-label">Special Categories (if applicable)</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="ews" id="ewsCheck" value="1"
                                       <?= !empty($data['ews']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="ewsCheck">EWS (Economically Weaker Section)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="obc_ncl" id="obcCheck" value="1"
                                       <?= !empty($data['obc_ncl']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="obcCheck">OBC (Non-Creamy Layer)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="pwd" id="pwdCheck" value="1"
                                       <?= !empty($data['pwd']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="pwdCheck">PWD (Person with Disability)</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Father's Info -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Father's Information</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Father's Name <span class="text-danger">*</span></label>
                        <input type="text" name="fathersname" class="form-control <?= isset($errors['fathersname']) ? 'is-invalid' : '' ?>"
                               value="<?= htmlspecialchars($data['fathersname'] ?? '') ?>" required>
                        <?php if (isset($errors['fathersname'])): ?><div class="invalid-feedback"><?= $errors['fathersname'] ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Father's Occupation</label>
                        <input type="text" name="foccupation" class="form-control"
                               value="<?= htmlspecialchars($data['foccupation'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Father's Contact</label>
                        <input type="tel" name="fcontact" class="form-control" maxlength="10"
                               value="<?= htmlspecialchars($data['fcontact'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Father's Qualification</label>
                        <input type="text" name="fqualifications" class="form-control"
                               value="<?= htmlspecialchars($data['fqualifications'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Mother's Info -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Mother's Information</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Mother's Name <span class="text-danger">*</span></label>
                        <input type="text" name="mothersname" class="form-control <?= isset($errors['mothersname']) ? 'is-invalid' : '' ?>"
                               value="<?= htmlspecialchars($data['mothersname'] ?? '') ?>" required>
                        <?php if (isset($errors['mothersname'])): ?><div class="invalid-feedback"><?= $errors['mothersname'] ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mother's Occupation</label>
                        <input type="text" name="moccupation" class="form-control"
                               value="<?= htmlspecialchars($data['moccupation'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mother's Contact</label>
                        <input type="tel" name="mcontact" class="form-control" maxlength="10"
                               value="<?= htmlspecialchars($data['mcontact'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mother's Qualification</label>
                        <input type="text" name="mqualification" class="form-control"
                               value="<?= htmlspecialchars($data['mqualification'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Spouse Info (optional) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center"
                 style="background-color: #e9ecef;">
                <h5 class="mb-0 text-muted"><i class="bi bi-person-hearts me-2"></i>Spouse Information <small>(Optional)</small></h5>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleSpouse">
                    <span id="spouseToggleText">Show</span>
                </button>
            </div>
            <div class="card-body p-4" id="spouseSection" style="display:none;">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Spouse Name</label>
                        <input type="text" name="spousename" class="form-control"
                               value="<?= htmlspecialchars($data['spousename'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Spouse Occupation</label>
                        <input type="text" name="soccupation" class="form-control"
                               value="<?= htmlspecialchars($data['soccupation'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Spouse Contact</label>
                        <input type="tel" name="scontact" class="form-control" maxlength="10"
                               value="<?= htmlspecialchars($data['scontact'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Spouse Qualification</label>
                        <input type="text" name="squalification" class="form-control"
                               value="<?= htmlspecialchars($data['squalification'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Address Info -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0"><i class="bi bi-house-fill me-2"></i>Address & Contact</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Permanent Address <span class="text-danger">*</span></label>
                        <textarea name="permanent_address" class="form-control <?= isset($errors['permanent_address']) ? 'is-invalid' : '' ?>"
                                  rows="3" required><?= htmlspecialchars($data['permanent_address'] ?? '') ?></textarea>
                        <?php if (isset($errors['permanent_address'])): ?><div class="invalid-feedback"><?= $errors['permanent_address'] ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Present Address <span class="text-danger">*</span></label>
                        <textarea name="present_address" class="form-control <?= isset($errors['present_address']) ? 'is-invalid' : '' ?>"
                                  rows="3" id="presentAddr" required><?= htmlspecialchars($data['present_address'] ?? '') ?></textarea>
                        <?php if (isset($errors['present_address'])): ?><div class="invalid-feedback"><?= $errors['present_address'] ?></div><?php endif; ?>
                        <div class="form-check mt-1">
                            <input type="checkbox" class="form-check-input" id="sameAddress">
                            <label class="form-check-label small" for="sameAddress">Same as permanent address</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Emergency Contact Number <span class="text-danger">*</span></label>
                        <input type="tel" name="emergency_contact" class="form-control <?= isset($errors['emergency_contact']) ? 'is-invalid' : '' ?>"
                               value="<?= htmlspecialchars($data['emergency_contact'] ?? '') ?>" maxlength="10" required>
                        <?php if (isset($errors['emergency_contact'])): ?><div class="invalid-feedback"><?= $errors['emergency_contact'] ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Annual Family Income <span class="text-danger">*</span></label>
                        <select name="income" class="form-select <?= isset($errors['income']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Select --</option>
                            <?php foreach (['Below 1 Lakh','1-2 Lakh','2-5 Lakh','5-8 Lakh','Above 8 Lakh'] as $inc): ?>
                                <option value="<?= $inc ?>" <?= ($data['income'] ?? '') === $inc ? 'selected' : '' ?>><?= $inc ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['income'])): ?><div class="invalid-feedback"><?= $errors['income'] ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($errors['db'])): ?>
            <div class="alert alert-danger"><?= $errors['db'] ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="form-check">
                    <input class="form-check-input <?= isset($errors['declaration_confirm']) ? 'is-invalid' : '' ?>"
                           type="checkbox"
                           id="declarationConfirm"
                           name="declaration_confirm"
                           value="1"
                           <?= !empty($data['declaration_confirm']) ? 'checked' : '' ?>
                           required>
                    <label class="form-check-label" for="declarationConfirm">
                        I hereby declare that the above entered information is authentic and legal as per my original documents.
                    </label>
                    <?php if (isset($errors['declaration_confirm'])): ?>
                        <div class="invalid-feedback d-block"><?= $errors['declaration_confirm'] ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between mb-5">
            <a href="<?= route('welcome') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
            </a>
            <div class="d-flex gap-2">
                <button type="button" id="previewBtn" class="btn btn-outline-primary btn-lg px-4">
                    <i class="bi bi-eye me-1"></i>Preview
                </button>
                <button type="button" id="openConfirmBtn" class="btn btn-primary btn-lg px-5"
                        <?= empty($data['declaration_confirm']) ? 'disabled' : '' ?>>
                    Save & Continue <i class="bi bi-arrow-right ms-1"></i>
                </button>
            </div>
        </div>
    </form>
</div>

<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewModalLabel">Personal Details Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="previewModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="submitConfirmModal" tabindex="-1" aria-labelledby="submitConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="submitConfirmLabel">
                    <i class="bi bi-shield-check me-2"></i>Confirm & Save Personal Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="confirmSummaryBody">
                <!-- Dynamically populated by JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-pencil me-1"></i>Review Again
                </button>
                <button type="button" id="confirmSubmitBtn" class="btn btn-primary">
                    <i class="bi bi-check-circle me-1"></i>Yes, Save & Continue
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Age auto-calc from DOB ───────────────────────────────────────────────
    document.getElementById('dobField')?.addEventListener('change', function () {
        const dob = new Date(this.value);
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        document.getElementById('ageDisplay').value = age >= 0 ? age : '';
    });

    // ── Same address checkbox ────────────────────────────────────────────────
    document.getElementById('sameAddress')?.addEventListener('change', function () {
        const perm = document.querySelector('[name="permanent_address"]').value;
        if (this.checked) {
            document.getElementById('presentAddr').value = perm;
        } else {
            document.getElementById('presentAddr').value = '';
        }
    });

    // ── Spouse toggle ────────────────────────────────────────────────────────
    document.getElementById('toggleSpouse')?.addEventListener('click', function () {
        const sec = document.getElementById('spouseSection');
        const txt = document.getElementById('spouseToggleText');
        if (sec.style.display === 'none') { sec.style.display = 'block'; txt.textContent = 'Hide'; }
        else { sec.style.display = 'none'; txt.textContent = 'Show'; }
    });

    // Show spouse section if data exists
    <?php if (!empty($data['spousename'])): ?>
    document.getElementById('spouseSection').style.display = 'block';
    document.getElementById('spouseToggleText').textContent = 'Hide';
    <?php endif; ?>

    // ── Core references ──────────────────────────────────────────────────────
    const personalForm        = document.getElementById('personalForm');
    const religionSelect      = personalForm?.querySelector('[name="religion"]');
    const otherReligionWrap   = document.getElementById('otherReligionWrap');
    const otherReligionInput  = document.getElementById('otherReligionInput');
    const previewModalEl      = document.getElementById('previewModal');
    const submitConfirmModalEl = document.getElementById('submitConfirmModal');
    const previewModal        = new bootstrap.Modal(previewModalEl);
    const submitConfirmModal  = new bootstrap.Modal(submitConfirmModalEl);
    const previewModalBody    = document.getElementById('previewModalBody');
    const declarationCheck    = document.getElementById('declarationConfirm');
    const saveBtn             = document.getElementById('openConfirmBtn');
    let finalSubmit = false;

    // ── Declaration checkbox controls Save & Continue button ─────────────────
    function toggleSaveBtn() {
        if (saveBtn && declarationCheck) {
            saveBtn.disabled = !declarationCheck.checked;
        }
    }
    declarationCheck?.addEventListener('change', toggleSaveBtn);
    toggleSaveBtn(); // set initial state

    // ── Other-religion field toggle ──────────────────────────────────────────
    function toggleOtherReligion() {
        const isOther = religionSelect?.value === 'Others';
        if (!otherReligionWrap || !otherReligionInput) return;
        otherReligionWrap.style.display = isOther ? '' : 'none';
        otherReligionInput.required = isOther;
        if (!isOther) otherReligionInput.classList.remove('is-invalid');
    }
    religionSelect?.addEventListener('change', toggleOtherReligion);
    toggleOtherReligion();

    // ── Preview helpers ──────────────────────────────────────────────────────
    function getFieldValue(name) {
        return (personalForm?.elements?.[name]?.value || '').trim();
    }

    function getCheckboxValue(name) {
        return personalForm?.elements?.[name]?.checked ? 'Yes' : 'No';
    }

    function addPreviewSection(title, rows) {
        const section = document.createElement('div');
        section.className = 'mb-4';

        const heading = document.createElement('h6');
        heading.className = 'fw-bold border-bottom pb-2 mb-3 text-primary';
        heading.textContent = title;
        section.appendChild(heading);

        const table = document.createElement('table');
        table.className = 'table table-sm table-bordered';
        const tbody = document.createElement('tbody');

        rows.forEach(([label, value]) => {
            if (!value) return; // skip empty optional fields
            const tr = document.createElement('tr');
            const tdLabel = document.createElement('th');
            tdLabel.className = 'text-muted bg-light';
            tdLabel.style.width = '40%';
            tdLabel.textContent = label;
            const tdValue = document.createElement('td');
            tdValue.textContent = value || '—';
            tr.appendChild(tdLabel);
            tr.appendChild(tdValue);
            tbody.appendChild(tr);
        });

        table.appendChild(tbody);
        section.appendChild(table);
        previewModalBody.appendChild(section);
    }

    function buildPreview() {
        if (!previewModalBody) return;

        const religion = getFieldValue('religion') === 'Others'
            ? getFieldValue('other_religion')
            : getFieldValue('religion');

        previewModalBody.innerHTML = '';

        addPreviewSection('Personal Information', [
            ['First Name',      getFieldValue('firstname')],
            ['Middle Name',     getFieldValue('middlename')],
            ['Last Name',       getFieldValue('lastname')],
            ['Date of Birth',   getFieldValue('dob')],
            ['Age',             getFieldValue('age_display')],
            ['Gender',          getFieldValue('gender')],
            ['Blood Group',     getFieldValue('blood_group')],
            ['Religion',        religion],
            ['Caste/Category',  getFieldValue('caste')],
            ['EWS',             getCheckboxValue('ews') === 'Yes' ? 'Yes' : null],
            ['OBC-NCL',         getCheckboxValue('obc_ncl') === 'Yes' ? 'Yes' : null],
            ['PWD',             getCheckboxValue('pwd') === 'Yes' ? 'Yes' : null],
        ]);

        addPreviewSection("Father's Information", [
            ["Father's Name",          getFieldValue('fathersname')],
            ["Father's Occupation",    getFieldValue('foccupation')],
            ["Father's Contact",       getFieldValue('fcontact')],
            ["Father's Qualification", getFieldValue('fqualifications')],
        ]);

        addPreviewSection("Mother's Information", [
            ["Mother's Name",          getFieldValue('mothersname')],
            ["Mother's Occupation",    getFieldValue('moccupation')],
            ["Mother's Contact",       getFieldValue('mcontact')],
            ["Mother's Qualification", getFieldValue('mqualification')],
        ]);

        const spouseName = getFieldValue('spousename');
        if (spouseName) {
            addPreviewSection('Spouse Information', [
                ['Spouse Name',          spouseName],
                ['Spouse Occupation',    getFieldValue('soccupation')],
                ['Spouse Contact',       getFieldValue('scontact')],
                ['Spouse Qualification', getFieldValue('squalification')],
            ]);
        }

        addPreviewSection('Address & Contact', [
            ['Permanent Address',    getFieldValue('permanent_address')],
            ['Present Address',      getFieldValue('present_address')],
            ['Emergency Contact',    getFieldValue('emergency_contact')],
            ['Annual Family Income', getFieldValue('income')],
        ]);
    }

    // ── Submit attempt handler ───────────────────────────────────────────────
    function handleSubmitAttempt() {
        toggleOtherReligion();
        if (!personalForm) return;

        if (!personalForm.checkValidity()) {
            personalForm.reportValidity();
            return;
        }

        // Build a fresh preview inside the confirm modal body before showing it
        buildConfirmSummary();
        submitConfirmModal.show();
    }

    // ── Build summary inside the confirmation modal ──────────────────────────
    function buildConfirmSummary() {
        const religion = getFieldValue('religion') === 'Others'
            ? getFieldValue('other_religion')
            : getFieldValue('religion');

        const fn   = getFieldValue('firstname');
        const mn   = getFieldValue('middlename');
        const ln   = getFieldValue('lastname');
        const full = [fn, mn, ln].filter(Boolean).join(' ');

        const confirmBody = document.getElementById('confirmSummaryBody');
        if (!confirmBody) return;

        confirmBody.innerHTML = `
            <p class="mb-3">Please verify the key details below before saving:</p>
            <table class="table table-sm table-bordered mb-0">
                <tbody>
                    <tr><th class="bg-light text-muted" style="width:40%">Full Name</th><td>${full || '—'}</td></tr>
                    <tr><th class="bg-light text-muted">Date of Birth</th><td>${getFieldValue('dob') || '—'}</td></tr>
                    <tr><th class="bg-light text-muted">Gender</th><td>${getFieldValue('gender') || '—'}</td></tr>
                    <tr><th class="bg-light text-muted">Blood Group</th><td>${getFieldValue('blood_group') || '—'}</td></tr>
                    <tr><th class="bg-light text-muted">Religion</th><td>${religion || '—'}</td></tr>
                    <tr><th class="bg-light text-muted">Caste/Category</th><td>${getFieldValue('caste') || '—'}</td></tr>
                    <tr><th class="bg-light text-muted">Father's Name</th><td>${getFieldValue('fathersname') || '—'}</td></tr>
                    <tr><th class="bg-light text-muted">Mother's Name</th><td>${getFieldValue('mothersname') || '—'}</td></tr>
                    <tr><th class="bg-light text-muted">Emergency Contact</th><td>${getFieldValue('emergency_contact') || '—'}</td></tr>
                </tbody>
            </table>
            <div class="alert alert-warning mt-3 mb-0 small">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                Once saved, this information <strong>cannot be updated</strong>. Please ensure all details are correct.
            </div>`;
    }

    // ── Event bindings ───────────────────────────────────────────────────────
    document.getElementById('previewBtn')?.addEventListener('click', function () {
        buildPreview();
        previewModal.show();
    });

    document.getElementById('openConfirmBtn')?.addEventListener('click', function () {
        handleSubmitAttempt();
    });

    personalForm?.addEventListener('submit', function (event) {
        if (finalSubmit) return;
        event.preventDefault();
        handleSubmitAttempt();
    });

    document.getElementById('confirmSubmitBtn')?.addEventListener('click', function () {
        if (!personalForm) return;
        finalSubmit = true;
        personalForm.submit();
    });

}); // end DOMContentLoaded
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/views/layouts/main.php';
