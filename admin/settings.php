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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SecurityHelper::verifyCsrf();

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

$pageTitle = 'Settings - Admin RTTC 2026';
$activePage = 'settings';
$breadcrumb = [['label' => 'Settings']];
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-gear-fill me-2 text-primary"></i>Settings</h4>
</div>

<div class="row g-4">
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

<?php
$content = ob_get_clean();
include BASE_PATH . '/admin/layouts/admin.php';
