<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';
SecurityHelper::requireAdminAuth();

$db = db();
$adminId = (int) SessionHelper::get('admin_id', 0);

$stmt = $db->prepare("SELECT id, name, email, password FROM admin_users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) {
    SessionHelper::destroyAdmin();
    redirect('admin.login');
}

$errors = [];
$registrationOpen = SiteSettingsHelper::isRegistrationOpen();
$registrationDeadline = SiteSettingsHelper::getRegistrationDeadline();
$registrationTimerActive = SiteSettingsHelper::isRegistrationTimerActive();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SecurityHelper::verifyCsrf();

    $action = $_POST['action'] ?? 'change_password';

    if ($action === 'registration_control') {
        $requestedOpen = ($_POST['registration_open'] ?? '0') === '1';
        if (SiteSettingsHelper::setRegistrationOpen($requestedOpen)) {
            SessionHelper::setFlash(
                'success',
                $requestedOpen ? 'Registration has been reopened.' : 'Registration has been closed. Existing applicants who completed documents can still pay.'
            );
            redirect('admin.settings');
        }
        $errors['registration'] = 'Unable to update registration availability. Run the latest database migration and try again.';
    } elseif ($action === 'registration_timer_start') {
        $days = filter_var($_POST['duration_days'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 365]]);
        $hours = filter_var($_POST['duration_hours'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 23]]);
        if ($days === false || $hours === false || (($days * 24) + $hours) < 1) {
            $errors['registration'] = 'Please choose at least 1 hour, with days from 0 to 365 and hours from 0 to 23.';
        } elseif (SiteSettingsHelper::startRegistrationTimer((int)$days, (int)$hours)) {
            SessionHelper::setFlash('success', 'Registration timer started. Registration will close automatically when it reaches zero.');
            redirect('admin.settings');
        } else {
            $errors['registration'] = 'Unable to start the registration timer. Run the latest database migration and try again.';
        }
    } elseif ($action === 'registration_timer_stop') {
        if (SiteSettingsHelper::setRegistrationOpen(false)) {
            SessionHelper::setFlash('success', 'The registration timer was stopped and registration is now closed. Applicants who completed documents can still pay.');
            redirect('admin.settings');
        }
        $errors['registration'] = 'Unable to stop the registration timer. Run the latest database migration and try again.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '') {
            $errors['current_password'] = 'Current password is required.';
        } elseif (!SecurityHelper::verifyPassword($currentPassword, $admin['password'])) {
            $errors['current_password'] = 'Current password is incorrect.';
        }

        if (!ValidationHelper::validatePassword($newPassword)) {
            $errors['new_password'] = 'New password must be at least 8 characters with uppercase, lowercase, number and special character.';
        }

        if ($confirmPassword !== $newPassword) {
            $errors['confirm_password'] = 'New password and confirm password do not match.';
        }

        if (empty($errors)) {
            $hash = SecurityHelper::hashPassword($newPassword);
            $up = $db->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
            $up->bind_param('si', $hash, $adminId);
            $ok = $up->execute();
            $up->close();

            if ($ok) {
                SessionHelper::setFlash('success', 'Admin password updated successfully.');
                redirect('admin.settings');
            } else {
                $errors['form'] = 'Failed to update password. Please try again.';
            }
        }
    }

    $registrationOpen = SiteSettingsHelper::isRegistrationOpen();
    $registrationDeadline = SiteSettingsHelper::getRegistrationDeadline();
    $registrationTimerActive = SiteSettingsHelper::isRegistrationTimerActive();
}

$pageTitle = 'Settings - Admin RTTC 2026';
$activePage = 'settings';
$breadcrumb = [['label' => 'Settings']];
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-gear-fill me-2 text-primary"></i>Settings</h4>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom pt-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="bi bi-door-closed me-2 text-primary"></i>Registration Control</h6>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($registrationOpen): ?>
                        <span class="badge bg-success">Open</span>
                        <?php if ($registrationTimerActive): ?><span class="badge bg-primary">Timer Active</span><?php endif; ?>
                    <?php else: ?>
                        <span class="badge bg-danger">Closed</span>
                        <?php if ($registrationDeadline && strtotime($registrationDeadline) <= time()): ?><span class="badge bg-secondary">Timer Expired</span><?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($errors['registration'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($errors['registration']) ?></div>
                <?php endif; ?>
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <h6 class="fw-semibold mb-1"><?= $registrationOpen ? 'Registration is currently open.' : 'Registration is currently closed.' ?></h6>
                        <p class="text-muted small mb-0">
                            Closing registration disables new signup and blocks personal details, academic details, and document uploads.
                             Applicants who have completed document upload can still make payment.
                        </p>
                    </div>
                    <div class="d-flex flex-wrap justify-content-end gap-2 flex-shrink-0">
                        <?php if ($registrationTimerActive && $registrationDeadline): ?>
                            <div class="registration-admin-countdown" data-registration-countdown data-deadline="<?= htmlspecialchars(date('c', strtotime($registrationDeadline))) ?>">
                                <span class="small text-muted d-block">Automatic close in</span>
                                <strong data-countdown-label>Calculating...</strong>
                            </div>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#stopRegistrationTimerModal">
                                <i class="bi bi-stop-circle me-1"></i>Stop Timer
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#registrationTimerModal">
                                <i class="bi bi-hourglass-split me-1"></i>Start Timer
                            </button>
                        <?php endif; ?>
                        <?php if ($registrationOpen): ?>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#registrationWarningModal">
                                <i class="bi bi-lock-fill me-1"></i>Close Now
                            </button>
                        <?php else: ?>
                            <form method="POST" action="<?= route('admin.settings') ?>">
                                <?= SecurityHelper::csrfField() ?>
                                <input type="hidden" name="action" value="registration_control">
                                <input type="hidden" name="registration_open" value="1">
                                <button type="submit" class="btn btn-success"><i class="bi bi-unlock-fill me-1"></i>Reopen Registration</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom pt-3">
                <h6 class="fw-bold mb-0">Admin Account Info</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <small class="text-muted d-block">Username</small>
                    <div class="fw-semibold"><?= htmlspecialchars($admin['name']) ?></div>
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block">Email</small>
                    <div class="fw-semibold"><?= htmlspecialchars($admin['email']) ?></div>
                </div>
                <div>
                    <small class="text-muted d-block">Current Password</small>
                    <div class="fw-semibold text-muted">Not visible for security reasons</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom pt-3">
                <h6 class="fw-bold mb-0">Change Password</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($errors['form'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($errors['form']) ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= route('admin.settings') ?>">
                    <?= SecurityHelper::csrfField() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-control <?= isset($errors['current_password']) ? 'is-invalid' : '' ?>" required>
                        <?php if (isset($errors['current_password'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['current_password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control <?= isset($errors['new_password']) ? 'is-invalid' : '' ?>" required>
                        <?php if (isset($errors['new_password'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['new_password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" required>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['confirm_password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-shield-lock me-1"></i>Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (!$registrationTimerActive): ?>
<div class="modal fade" id="registrationTimerModal" tabindex="-1" aria-labelledby="registrationTimerLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="registrationTimerLabel"><i class="bi bi-hourglass-split me-2"></i>Start Registration Timer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= route('admin.settings') ?>">
                <?= SecurityHelper::csrfField() ?>
                <input type="hidden" name="action" value="registration_timer_start">
                <div class="modal-body">
                    <p class="text-muted small">Registration will remain open until this server-side countdown ends. The deadline is calculated from the current server time.</p>
                    <div class="row g-3">
                        <div class="col-6">
                            <label for="durationDays" class="form-label">Days</label>
                            <input type="number" id="durationDays" name="duration_days" class="form-control" min="0" max="365" value="0" required>
                        </div>
                        <div class="col-6">
                            <label for="durationHours" class="form-label">Hours</label>
                            <input type="number" id="durationHours" name="duration_hours" class="form-control" min="0" max="23" value="1" required>
                        </div>
                    </div>
                    <div class="alert alert-warning small mt-3 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>At zero, new signup and unfinished form submissions will be closed automatically. Applicants who completed document upload can still pay.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-play-fill me-1"></i>Start Timer</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($registrationOpen && $registrationTimerActive): ?>
<div class="modal fade" id="stopRegistrationTimerModal" tabindex="-1" aria-labelledby="stopRegistrationTimerLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold" id="stopRegistrationTimerLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i>Stop Registration Timer?</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="fw-semibold mb-2">This action will stop the countdown and close registration immediately.</p>
                <p class="text-muted small mb-0">New signup and unfinished form submissions will be disabled. Applicants who completed document upload can still pay.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Timer</button>
                <form method="POST" action="<?= route('admin.settings') ?>">
                    <?= SecurityHelper::csrfField() ?>
                    <input type="hidden" name="action" value="registration_timer_stop">
                    <button type="submit" class="btn btn-danger"><i class="bi bi-stop-circle me-1"></i>Yes, Stop Timer</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($registrationOpen): ?>
<div class="modal fade" id="registrationWarningModal" tabindex="-1" aria-labelledby="registrationWarningLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold" id="registrationWarningLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Close Registration?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="fw-semibold">Closing registration will immediately:</p>
                <ul class="mb-0">
                    <li>Disable new signup and registration links.</li>
                    <li>Stop submission of personal details, academic details, and documents.</li>
                    <li>Allow only applicants who completed document upload to continue to payment.</li>
                    <li>Leave already-paid applications and admin access unchanged.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="<?= route('admin.settings') ?>">
                    <?= SecurityHelper::csrfField() ?>
                    <input type="hidden" name="action" value="registration_control">
                    <input type="hidden" name="registration_open" value="0">
                    <button type="submit" class="btn btn-danger"><i class="bi bi-lock-fill me-1"></i>Yes, Close Registration</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
$extraFoot = '<script>
(function () {
    const timer = document.querySelector("[data-registration-countdown]");
    if (!timer) return;
    const deadline = new Date(timer.dataset.deadline).getTime();
    const label = timer.querySelector("[data-countdown-label]");
    function update() {
        const remaining = Math.max(0, deadline - Date.now());
        if (remaining <= 0) {
            label.textContent = "Closing now...";
            window.setTimeout(function () { window.location.reload(); }, 1000);
            return;
        }
        const totalMinutes = Math.floor(remaining / 60000);
        const days = Math.floor(totalMinutes / 1440);
        const hours = Math.floor((totalMinutes % 1440) / 60);
        const minutes = totalMinutes % 60;
        label.textContent = days + "d " + hours + "h " + minutes + "m";
        window.setTimeout(update, 10000);
    }
    update();
})();
</script>';
include BASE_PATH . '/admin/layouts/admin.php';
