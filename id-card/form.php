<?php
if (!isset($applicationType, $formRoute) || !IdCardHelper::isValidType($applicationType)) {
    http_response_code(404);
    exit('Page not found.');
}

header('X-Robots-Tag: noindex, nofollow', true);

$db = db();
$errors = [];
$data = [
    'course' => $applicationType === IdCardHelper::TYPE_STUDENT ? 'B.Ed.' : '',
    'academic_session' => $applicationType === IdCardHelper::TYPE_STUDENT ? YEAR_LABEL : '',
];
$success = null;

if (($_GET['submitted'] ?? '') === '1') {
    $storedSuccess = SessionHelper::get('id_card_submission_success');
    if (is_array($storedSuccess) && ($storedSuccess['type'] ?? '') === $applicationType) {
        $success = $storedSuccess;
        SessionHelper::remove('id_card_submission_success');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SecurityHelper::verifyCsrf();
    $data = $_POST;
    $submittedToken = (string) ($_POST['submission_token'] ?? '');
    $sessionToken = IdCardHelper::submissionToken($applicationType);

    if ($submittedToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        $errors['form'] = 'Your form session has expired. Please refresh the page and try again.';
    } else {
        try {
            $attempt = IdCardHelper::recordSubmissionAttempt($db);
            if (!$attempt['allowed']) {
                $errors['form'] = 'Too many attempts were received. Please wait a few minutes and try again.';
            } elseif (!empty($_POST['website'] ?? '')) {
                $errors['form'] = 'Your submission could not be accepted. Please try again.';
            } elseif (time() - IdCardHelper::submissionStartedAt($applicationType) < ID_CARD_PUBLIC_FORM_MIN_SECONDS) {
                $errors['form'] = 'Please take a moment to review the form before submitting.';
            } else {
                $validation = IdCardHelper::validateApplication($applicationType, $_POST);
                $data = array_merge($data, $validation['data']);
                $errors = $validation['errors'];

                if (empty($errors)) {
                    $photo = IdCardHelper::validateAndStorePhoto('photo', $applicationType);
                    if (!$photo['success']) {
                        $errors['photo'] = $photo['message'];
                    } else {
                        $now = date('Y-m-d H:i:s');
                        $tokenHash = hash('sha256', $submittedToken);
                        $photoPath = $photo['path'];
                        $saved = false;

                        $db->begin_transaction();
                        try {
                            $stmt = $db->prepare("INSERT INTO id_card_applications
                                (application_type, full_name, care_of, course, academic_session, roll_number, date_of_birth,
                                 department, designation, blood_group, contact_number, address, photo_path,
                                 declaration_accepted_at, status, submission_token_hash, submitted_ip_key, created_at, updated_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)");
                            if (!$stmt) throw new RuntimeException('Could not prepare ID card application insert.');
                            $values = [
                                $applicationType,
                                $validation['data']['full_name'],
                                $validation['data']['care_of'],
                                $validation['data']['course'],
                                $validation['data']['academic_session'],
                                $validation['data']['roll_number'],
                                $validation['data']['date_of_birth'],
                                $validation['data']['department'],
                                $validation['data']['designation'],
                                $validation['data']['blood_group'],
                                $validation['data']['contact_number'],
                                $validation['data']['address'],
                                $photoPath,
                                $now,
                                $tokenHash,
                                $attempt['ip_key'],
                                $now,
                                $now,
                            ];
                            $stmt->bind_param(str_repeat('s', count($values)), ...$values);
                            if (!$stmt->execute()) {
                                $errorNumber = $stmt->errno;
                                $stmt->close();
                                if ($errorNumber === 1062) {
                                    throw new RuntimeException('This submission has already been received.');
                                }
                                throw new RuntimeException('Could not save ID card application.');
                            }
                            $applicationId = (int) $db->insert_id;
                            $stmt->close();

                            $reference = IdCardHelper::formatReference($applicationType, $applicationId);
                            $audit = $db->prepare("INSERT INTO id_card_action_log
                                (application_id, application_reference, action, admin_user_id, notes, created_at)
                                VALUES (?, ?, 'submitted', NULL, NULL, ?)");
                            if (!$audit) throw new RuntimeException('Could not prepare ID card submission audit.');
                            $audit->bind_param('iss', $applicationId, $reference, $now);
                            if (!$audit->execute()) {
                                $audit->close();
                                throw new RuntimeException('Could not record ID card submission audit.');
                            }
                            $audit->close();

                            if (!$db->commit()) throw new RuntimeException('Could not commit ID card submission.');
                            $saved = true;
                            IdCardHelper::clearSubmissionToken($applicationType);
                            SessionHelper::set('id_card_submission_success', [
                                'type' => $applicationType,
                                'reference' => $reference,
                            ]);
                            redirect($formRoute, ['submitted' => '1']);
                        } catch (Throwable $e) {
                            $db->rollback();
                            error_log('ID card public submission failed: ' . $e->getMessage());
                            if (!$saved) IdCardHelper::deleteStoredPhoto($photoPath);
                            $errors['form'] = 'Your application could not be saved. Please try again.';
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('ID card public submission protection failed: ' . $e->getMessage());
            $errors['form'] = 'The submission service is temporarily unavailable. Please try again later.';
        }
    }
}

$token = IdCardHelper::submissionToken($applicationType);
$isStudent = $applicationType === IdCardHelper::TYPE_STUDENT;
$typeLabel = $isStudent ? 'Student' : 'Faculty/Staff';
$pageTitle = $typeLabel . ' ID Card Application';
$extraHead = '<meta name="robots" content="noindex, nofollow">' . PHP_EOL
    . '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/id-card.css?v=' . filemtime(BASE_PATH . '/assets/css/id-card.css') . '">';
$extraFoot = '<script src="' . BASE_URL . '/assets/js/id-card-forms.js?v=' . filemtime(BASE_PATH . '/assets/js/id-card-forms.js') . '"></script>';

$fieldClass = static function (string $name) use ($errors): string {
    return isset($errors[$name]) ? ' is-invalid' : '';
};

ob_start();
?>
<div class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">
            <div class="card border-0 shadow-sm overflow-hidden">
                <div class="card-header bg-primary text-white p-4">
                    <div class="d-flex align-items-center gap-3">
                        <img src="<?= BASE_URL ?>/assets/img/RTTC_logo.jpeg" alt="RTTC logo" width="58" height="58" class="rounded-circle bg-white p-1">
                        <div>
                            <h1 class="h4 mb-1"><?= $typeLabel ?> ID Card Application</h1>
                            <p class="mb-0 small opacity-75">Rangia Teacher Training College</p>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4 p-md-5">
                    <?php if ($success): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem;"></i>
                        <h2 class="h4 fw-bold mt-3">Application received</h2>
                        <p class="text-muted mb-2">Your <?= strtolower($typeLabel) ?> ID card application has been submitted successfully and is now pending review by the college authority.</p>
                        <p>Request reference: <strong class="font-monospace"><?= htmlspecialchars($success['reference']) ?></strong></p>
                        <p class="small text-muted mb-4">This application is now closed. The college authority will process the submitted details and photo.</p>
                        <a href="<?= route('home') ?>" class="btn btn-primary"><i class="bi bi-house-door me-2"></i>Return to Home</a>
                    </div>
                    <?php else: ?>
                    <p class="text-muted mb-4">Complete every field carefully. The information submitted here will be reviewed before your ID card is issued.</p>

                    <?php if (isset($errors['form'])): ?>
                    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($errors['form']) ?></div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data" data-id-card-form data-id-card-max-photo-size="<?= ID_CARD_MAX_PHOTO_SIZE ?>">
                        <?= SecurityHelper::csrfField() ?>
                        <input type="hidden" name="submission_token" value="<?= htmlspecialchars($token) ?>">
                        <div class="position-absolute start-0" style="left:-10000px !important;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
                            <label for="id-card-website">Website</label>
                            <input type="text" id="id-card-website" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="id-card-full-name">Name <span class="text-danger">*</span></label>
                                <input id="id-card-full-name" name="full_name" type="text" minlength="2" maxlength="80" class="form-control<?= $fieldClass('full_name') ?>" value="<?= htmlspecialchars($data['full_name'] ?? '') ?>" required>
                                <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['full_name']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="id-card-care-of">C/O <span class="text-danger">*</span></label>
                                <input id="id-card-care-of" name="care_of" type="text" minlength="2" maxlength="80" class="form-control<?= $fieldClass('care_of') ?>" value="<?= htmlspecialchars($data['care_of'] ?? '') ?>" required>
                                <?php if (isset($errors['care_of'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['care_of']) ?></div><?php endif; ?>
                            </div>

                            <?php if ($isStudent): ?>
                            <div class="col-md-4">
                                <label class="form-label" for="id-card-course">Course <span class="text-danger">*</span></label>
                                <input id="id-card-course" name="course" type="text" minlength="2" maxlength="40" class="form-control<?= $fieldClass('course') ?>" value="<?= htmlspecialchars($data['course'] ?? 'B.Ed.') ?>" required>
                                <?php if (isset($errors['course'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['course']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="id-card-session">Session <span class="text-danger">*</span></label>
                                <input id="id-card-session" name="academic_session" type="text" maxlength="9" pattern="[0-9]{4}-[0-9]{2}" class="form-control<?= $fieldClass('academic_session') ?>" value="<?= htmlspecialchars($data['academic_session'] ?? YEAR_LABEL) ?>" required>
                                <?php if (isset($errors['academic_session'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['academic_session']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="id-card-roll">Roll No. <span class="text-danger">*</span></label>
                                <input id="id-card-roll" name="roll_number" type="text" maxlength="30" class="form-control<?= $fieldClass('roll_number') ?>" value="<?= htmlspecialchars($data['roll_number'] ?? '') ?>" required>
                                <?php if (isset($errors['roll_number'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['roll_number']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="id-card-dob">Date of Birth <span class="text-danger">*</span></label>
                                <input id="id-card-dob" name="date_of_birth" type="date" max="<?= date('Y-m-d', strtotime('-1 day')) ?>" class="form-control<?= $fieldClass('date_of_birth') ?>" value="<?= htmlspecialchars($data['date_of_birth'] ?? '') ?>" required>
                                <?php if (isset($errors['date_of_birth'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['date_of_birth']) ?></div><?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="col-md-6">
                                <label class="form-label" for="id-card-department">Department <span class="text-danger">*</span></label>
                                <input id="id-card-department" name="department" type="text" minlength="2" maxlength="70" class="form-control<?= $fieldClass('department') ?>" value="<?= htmlspecialchars($data['department'] ?? '') ?>" required>
                                <?php if (isset($errors['department'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['department']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="id-card-designation">Designation <span class="text-danger">*</span></label>
                                <input id="id-card-designation" name="designation" type="text" minlength="2" maxlength="70" class="form-control<?= $fieldClass('designation') ?>" value="<?= htmlspecialchars($data['designation'] ?? '') ?>" required>
                                <?php if (isset($errors['designation'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['designation']) ?></div><?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <div class="col-md-6">
                                <label class="form-label" for="id-card-blood-group">Blood Group <span class="text-danger">*</span></label>
                                <select id="id-card-blood-group" name="blood_group" class="form-select<?= $fieldClass('blood_group') ?>" required>
                                    <option value="">Select blood group</option>
                                    <?php foreach (IdCardHelper::bloodGroups() as $bloodGroup): ?>
                                    <option value="<?= $bloodGroup ?>" <?= ($data['blood_group'] ?? '') === $bloodGroup ? 'selected' : '' ?>><?= $bloodGroup ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['blood_group'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['blood_group']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="id-card-contact">Contact No. <span class="text-danger">*</span></label>
                                <input id="id-card-contact" name="contact_number" type="tel" inputmode="numeric" maxlength="16" class="form-control<?= $fieldClass('contact_number') ?>" value="<?= htmlspecialchars($data['contact_number'] ?? '') ?>" required>
                                <div class="form-text">Enter a 10-digit Indian mobile number.</div>
                                <?php if (isset($errors['contact_number'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['contact_number']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="id-card-address">Address <span class="text-danger">*</span></label>
                                <textarea id="id-card-address" name="address" rows="3" minlength="5" maxlength="220" class="form-control<?= $fieldClass('address') ?>" required><?= htmlspecialchars($data['address'] ?? '') ?></textarea>
                                <div class="form-text">Maximum 220 characters to keep the printed card readable.</div>
                                <?php if (isset($errors['address'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['address']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="id-card-photo">Photo <span class="text-danger">*</span></label>
                                <input id="id-card-photo" name="photo" type="file" accept="image/jpeg,image/png" class="form-control<?= $fieldClass('photo') ?>" required>
                                <div class="form-text">JPEG or PNG only, maximum file size 2 MB.</div>
                                <?php if (isset($errors['photo'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($errors['photo']) ?></div><?php endif; ?>
                                <img id="id-card-photo-preview" alt="Selected photo preview" aria-hidden="true">
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input id="id-card-declaration" name="declaration" value="1" type="checkbox" class="form-check-input<?= $fieldClass('declaration') ?>" <?= !empty($data['declaration']) ? 'checked' : '' ?> required>
                                    <label class="form-check-label" for="id-card-declaration">I confirm that the information and photo submitted for this ID card are correct.</label>
                                    <?php if (isset($errors['declaration'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['declaration']) ?></div><?php endif; ?>
                                </div>
                            </div>
                            <div class="col-12" data-id-card-validation-status role="status" aria-live="polite" hidden></div>
                            <div class="col-12 d-flex justify-content-end pt-2" data-id-card-submit-wrap hidden>
                                <button type="submit" class="btn btn-primary px-4" data-id-card-confirm><i class="bi bi-send-check me-2"></i>Review and Submit</button>
                            </div>
                            <noscript>
                                <div class="col-12 d-flex justify-content-end pt-2">
                                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-send-check me-2"></i>Submit Application</button>
                                </div>
                            </noscript>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!$success): ?>
<div class="modal fade" id="idCardConfirmModal" tabindex="-1" aria-labelledby="idCardConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0"><h2 class="modal-title fs-5 fw-bold" id="idCardConfirmTitle">Submit ID card application?</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">Please confirm that all details are correct. They will be used to prepare the ID card after approval.</div>
            <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Back</button><button type="button" class="btn btn-primary" id="idCardConfirmSubmit" data-id-card-confirm-submit>Submit Application</button></div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
include BASE_PATH . '/views/layouts/main.php';
