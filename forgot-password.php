<?php
define('APP_INIT', true);
require_once __DIR__ . '/config/init.php';

SecurityHelper::requireGuest();

$errors = [];
$step   = $_SESSION['reset_step'] ?? 1; // 1=email, 2=otp+new password

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SecurityHelper::verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'send_otp') {
        $email = SecurityHelper::sanitize($_POST['email'] ?? '');
        if (!ValidationHelper::validateEmail($email)) {
            $errors['email'] = 'Enter a valid email address.';
        } else {
            $db   = db();
            $stmt = $db->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$user) {
                $errors['email'] = 'No account found with this email.';
            } else {
                $result = OTPHelper::sendPasswordResetOTP($email);
                if ($result['success']) {
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_step']  = 2;
                    SessionHelper::setFlash('success', 'OTP sent to ' . $email);
                    redirect(route('forgot-password'));
                } else {
                    $errors['otp'] = 'Failed to send OTP. Try again.';
                }
            }
        }
    } elseif ($action === 'reset') {
        $email    = $_SESSION['reset_email'] ?? '';
        $otp      = trim($_POST['otp'] ?? '');
        $password = $_POST['password'] ?? '';
        $cpass    = $_POST['cpassword'] ?? '';

        if (empty($email)) {
            redirect(route('forgot-password'));
        }

        $pErrors = ValidationHelper::validatePassword($password);
        if ($password !== $cpass) $errors['cpassword'] = 'Passwords do not match.';
        if ($pErrors) $errors['password'] = $pErrors;

        if (empty($errors)) {
            $result = OTPHelper::verifyOTP($email, $otp, 'reset');
            if ($result['success']) {
                $hash = SecurityHelper::hashPassword($password);
                $db   = db();
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
                $stmt->bind_param('ss', $hash, $email);
                $stmt->execute();
                $stmt->close();
                unset($_SESSION['reset_email'], $_SESSION['reset_step']);
                SessionHelper::setFlash('success', 'Password reset successfully. Please login.');
                redirect(route('login'));
            } else {
                $errors['otp'] = $result['message'];
            }
        }
    }
}

$step = $_SESSION['reset_step'] ?? 1;
$pageTitle = 'Forgot Password - RTTC 2026';
ob_start();
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="auth-card">
                <div class="auth-card-header text-center">
                    <i class="bi bi-key-fill fs-1 mb-2 d-block"></i>
                    <h4 class="mb-0"><?= $step === 2 ? 'Reset Password' : 'Forgot Password' ?></h4>
                    <p class="text-white-50 small mb-0">
                        <?= $step === 2 ? 'Enter OTP and your new password' : 'Enter your registered email' ?>
                    </p>
                </div>
                <div class="auth-card-body">
                    <?php include __DIR__ . '/views/partials/flash.php'; ?>

                    <?php if ($step === 1): ?>
                    <form method="POST" action="<?= route('forgot-password') ?>">
                        <?= SecurityHelper::csrfField() ?>
                        <input type="hidden" name="action" value="send_otp">
                        <div class="mb-3">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                   placeholder="your@email.com" required>
                            <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= $errors['email'] ?></div><?php endif; ?>
                        </div>
                        <?php if (isset($errors['otp'])): ?><div class="alert alert-danger"><?= $errors['otp'] ?></div><?php endif; ?>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-2"></i>Send OTP
                            </button>
                        </div>
                    </form>
                    <?php else: ?>
                    <form method="POST" action="<?= route('forgot-password') ?>">
                        <?= SecurityHelper::csrfField() ?>
                        <input type="hidden" name="action" value="reset">
                        <div class="mb-3">
                            <label class="form-label">OTP <span class="text-danger">*</span></label>
                            <input type="text" name="otp" class="form-control text-center <?= isset($errors['otp']) ? 'is-invalid' : '' ?>"
                                   maxlength="6" placeholder="6-digit OTP" required
                                   style="font-size:1.3rem; letter-spacing:0.3rem;">
                            <?php if (isset($errors['otp'])): ?><div class="invalid-feedback"><?= $errors['otp'] ?></div><?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                   placeholder="Min 8 chars, upper+lower+number+symbol" required>
                            <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= $errors['password'] ?></div><?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" name="cpassword" class="form-control <?= isset($errors['cpassword']) ? 'is-invalid' : '' ?>"
                                   placeholder="Re-enter new password" required>
                            <?php if (isset($errors['cpassword'])): ?><div class="invalid-feedback"><?= $errors['cpassword'] ?></div><?php endif; ?>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-shield-check me-2"></i>Reset Password
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>

                    <hr class="my-3">
                    <p class="text-center mb-0 text-muted">
                        <a href="<?= route('login') ?>" class="text-primary">Back to Login</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/views/layouts/main.php';
