<?php
define('APP_INIT', true);
require_once __DIR__ . '/config/init.php';

SecurityHelper::requireGuest();

$step = $_SESSION['signup_step'] ?? 1; // 1=form, 2=otp-verify
$errors = [];
$old = [];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SecurityHelper::verifyCsrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'signup') {
        $old = [
            'username'  => SecurityHelper::sanitize($_POST['username'] ?? ''),
            'email'     => SecurityHelper::sanitize($_POST['email'] ?? ''),
            'phone'     => SecurityHelper::sanitize($_POST['phone'] ?? ''),
        ];
        $password  = $_POST['password'] ?? '';
        $cpassword = $_POST['cpassword'] ?? '';

        // Validate
        $errors = ValidationHelper::validateSignup($old['username'], $old['email'], $old['phone'], $password, $cpassword);

        if (empty($errors)) {
            $db = db();
            // Check duplicate email
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param('s', $old['email']);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors['email'] = 'This email is already registered.';
            }
            $stmt->close();
        }

        if (empty($errors)) {
            $db = db();
            // Check duplicate phone
            $stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->bind_param('s', $old['phone']);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors['phone'] = 'This phone number is already registered.';
            }
            $stmt->close();
        }

        if (empty($errors)) {
            // Store signup data in session, send OTP
            $_SESSION['signup_data'] = [
                'username' => $old['username'],
                'email'    => $old['email'],
                'phone'    => $old['phone'],
                'password' => SecurityHelper::hashPassword($password),
            ];
            $result = OTPHelper::sendSignupOTP($old['email'], $old['username']);
            if ($result['success']) {
                $_SESSION['signup_step'] = 2;
                SessionHelper::setFlash('success', 'OTP sent to ' . $old['email'] . '. Please verify.');
                redirect(route('signup'));
            } else {
                $errors['otp'] = 'Failed to send OTP. Please try again.';
            }
        }
    } elseif ($action === 'verify_otp') {
        $otp = trim($_POST['otp'] ?? '');
        $signupData = $_SESSION['signup_data'] ?? null;

        if (!$signupData) {
            SessionHelper::setFlash('error', 'Session expired. Please sign up again.');
            unset($_SESSION['signup_step']);
            redirect(route('signup'));
        }

        $result = OTPHelper::verifyOTP($signupData['email'], $otp, 'signup');
        if ($result['success']) {
            // Insert user
            $db = db();
            $stmt = $db->prepare("INSERT INTO users (username, email, phone, password, is_verified) VALUES (?, ?, ?, ?, 1)");
            $stmt->bind_param('ssss', $signupData['username'], $signupData['email'], $signupData['phone'], $signupData['password']);
            if ($stmt->execute()) {
                $userId = $db->insert_id;
                $stmt->close();

                // Insert default registration_progress
                $stmt2 = $db->prepare("INSERT INTO registration_progress (user_id, current_step) VALUES (?, 0)");
                $stmt2->bind_param('i', $userId);
                $stmt2->execute();
                $stmt2->close();

                // Clean up session
                unset($_SESSION['signup_data'], $_SESSION['signup_step']);

                SessionHelper::setFlash('success', 'Registration successful! Please login.');
                redirect(route('login'));
            } else {
                $errors['db'] = 'Database error. Please try again.';
            }
        } else {
            $errors['otp'] = $result['message'];
        }
    } elseif ($action === 'resend_otp') {
        $signupData = $_SESSION['signup_data'] ?? null;
        if ($signupData) {
            $result = OTPHelper::sendSignupOTP($signupData['email'], $signupData['username']);
            if ($result['success']) {
                SessionHelper::setFlash('success', 'OTP resent successfully.');
            } else {
                SessionHelper::setFlash('error', 'Failed to resend OTP.');
            }
        }
        redirect(route('signup'));
    }
}

$step = $_SESSION['signup_step'] ?? 1;
$pageTitle = 'Register - RTTC 2026';
ob_start();
?>

<div class="auth-page-wrapper">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
            <div class="auth-card">
                <div class="auth-card-header text-center">
                    <img src="<?= BASE_URL ?>/assets/img/RTTC_logo.jpeg" alt="RTTC Logo" height="70" class="mb-3">
                    <h4 class="mb-0"><?= $step === 2 ? 'Verify Email OTP' : 'Create Account' ?></h4>
                    <p class="text-white-50 small mb-0">
                        <?= $step === 2 ? 'Enter the 6-digit OTP sent to your email' : 'Register for B.Ed. Admission 2026' ?>
                    </p>
                </div>
                <div class="auth-card-body">

                    <?php include __DIR__ . '/views/partials/flash.php'; ?>

                    <?php if ($step === 1): ?>
                    <!-- SIGNUP FORM -->
                    <form method="POST" action="<?= route('signup') ?>" id="signupForm" novalidate>
                        <?= SecurityHelper::csrfField() ?>
                        <input type="hidden" name="action" value="signup">

                        <div class="mb-3">
                            <label for="username" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                                   id="username" name="username"
                                   value="<?= htmlspecialchars($old['username'] ?? '') ?>"
                                   placeholder="Enter your full name" required>
                            <?php if (isset($errors['username'])): ?>
                                <div class="invalid-feedback"><?= $errors['username'] ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                       id="email" name="email"
                                       value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                                       placeholder="your@email.com" required>
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback"><?= $errors['email'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                                   id="phone" name="phone"
                                   value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
                                   placeholder="10-digit mobile number" maxlength="10" required>
                            <?php if (isset($errors['phone'])): ?>
                                <div class="invalid-feedback"><?= $errors['phone'] ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                       id="password" name="password"
                                       placeholder="Min 8 chars, upper+lower+number+symbol" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePass">
                                    <i class="bi bi-eye" id="passIcon"></i>
                                </button>
                                <?php if (isset($errors['password'])): ?>
                                    <div class="invalid-feedback"><?= $errors['password'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="password-strength mt-1" id="passwordStrength"></div>
                        </div>

                        <div class="mb-3">
                            <label for="cpassword" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control <?= isset($errors['cpassword']) ? 'is-invalid' : '' ?>"
                                       id="cpassword" name="cpassword"
                                       placeholder="Re-enter password" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleCPass">
                                    <i class="bi bi-eye" id="cpassIcon"></i>
                                </button>
                                <?php if (isset($errors['cpassword'])): ?>
                                    <div class="invalid-feedback"><?= $errors['cpassword'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (isset($errors['otp'])): ?>
                            <div class="alert alert-danger"><?= $errors['otp'] ?></div>
                        <?php endif; ?>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-person-plus me-2"></i>Send OTP & Register
                            </button>
                        </div>
                    </form>

                    <?php else: ?>
                    <!-- OTP VERIFICATION FORM -->
                    <?php $signupEmail = $_SESSION['signup_data']['email'] ?? ''; ?>
                    <p class="text-center text-muted mb-4">
                        OTP sent to <strong><?= htmlspecialchars($signupEmail) ?></strong>
                    </p>

                    <form method="POST" action="<?= route('signup') ?>" id="otpForm">
                        <?= SecurityHelper::csrfField() ?>
                        <input type="hidden" name="action" value="verify_otp">

                        <div class="mb-3">
                            <label for="otp" class="form-label">Enter OTP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-center otp-input <?= isset($errors['otp']) ? 'is-invalid' : '' ?>"
                                   id="otp" name="otp"
                                   placeholder="6-digit OTP" maxlength="6" required
                                   style="font-size:1.5rem; letter-spacing:0.4rem;">
                            <?php if (isset($errors['otp'])): ?>
                                <div class="invalid-feedback"><?= $errors['otp'] ?></div>
                            <?php endif; ?>
                        </div>

                        <div id="otpTimer" class="text-center text-muted small mb-3">
                            OTP expires in <span id="countdown">10:00</span>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-circle me-2"></i>Verify OTP
                            </button>
                        </div>
                    </form>

                    <form method="POST" action="<?= route('signup') ?>" class="text-center">
                        <?= SecurityHelper::csrfField() ?>
                        <input type="hidden" name="action" value="resend_otp">
                        <button type="submit" class="btn btn-link text-decoration-none">
                            <i class="bi bi-arrow-clockwise me-1"></i>Resend OTP
                        </button>
                    </form>
                    <?php endif; ?>

                    <hr class="my-3">
                    <p class="text-center mb-0 text-muted">
                        Already have an account?
                        <a href="<?= route('login') ?>" class="text-primary fw-semibold">Login here</a>
                    </p>
                </div>
            </div>
    </div>
    </div>
</div>
</div><!-- /.auth-page-wrapper -->

<script>
// Password toggle
document.getElementById('togglePass')?.addEventListener('click', function() {
    const pass = document.getElementById('password');
    const icon = document.getElementById('passIcon');
    if (pass.type === 'password') { pass.type = 'text'; icon.className = 'bi bi-eye-slash'; }
    else { pass.type = 'password'; icon.className = 'bi bi-eye'; }
});
document.getElementById('toggleCPass')?.addEventListener('click', function() {
    const pass = document.getElementById('cpassword');
    const icon = document.getElementById('cpassIcon');
    if (pass.type === 'password') { pass.type = 'text'; icon.className = 'bi bi-eye-slash'; }
    else { pass.type = 'password'; icon.className = 'bi bi-eye'; }
});

// Password strength indicator
document.getElementById('password')?.addEventListener('input', function() {
    const val = this.value;
    const el = document.getElementById('passwordStrength');
    let strength = 0;
    if (val.length >= 8) strength++;
    if (/[A-Z]/.test(val)) strength++;
    if (/[a-z]/.test(val)) strength++;
    if (/[0-9]/.test(val)) strength++;
    if (/[^A-Za-z0-9]/.test(val)) strength++;
    const labels = ['', 'Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
    const colors = ['', '#dc3545', '#fd7e14', '#ffc107', '#198754', '#20c997'];
    el.innerHTML = val ? `<small style="color:${colors[strength]}">${labels[strength]}</small>` : '';
});

// OTP countdown timer
<?php if ($step === 2): ?>
let timeLeft = 600;
const countdown = document.getElementById('countdown');
const timer = setInterval(() => {
    if (timeLeft <= 0) { clearInterval(timer); countdown.textContent = 'Expired'; return; }
    const m = Math.floor(timeLeft / 60);
    const s = timeLeft % 60;
    countdown.textContent = `${m}:${s.toString().padStart(2, '0')}`;
    timeLeft--;
}, 1000);
<?php endif; ?>

// Only allow numbers in OTP
document.getElementById('otp')?.addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/views/layouts/main.php';
