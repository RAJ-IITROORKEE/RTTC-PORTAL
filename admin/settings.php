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
        $registrationOpen = SiteSettingsHelper::isRegistrationOpen();
    }

    if ($action !== 'registration_control') {
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
                <?php if ($registrationOpen): ?>
                    <span class="badge bg-success">Open</span>
                <?php else: ?>
                    <span class="badge bg-danger">Closed</span>
                <?php endif; ?>
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
                    <?php if ($registrationOpen): ?>
                        <button type="button" class="btn btn-danger flex-shrink-0" data-bs-toggle="modal" data-bs-target="#registrationWarningModal">
                            <i class="bi bi-lock-fill me-1"></i>Close Registration
                        </button>
                    <?php else: ?>
                        <form method="POST" action="<?= route('admin.settings') ?>" class="flex-shrink-0">
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
include BASE_PATH . '/admin/layouts/admin.php';
