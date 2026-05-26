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

        // Validate OTP format (must be 6 digits)
        if (!preg_match('/^\d{6}$/', $otp)) {
            $errors['otp'] = 'OTP must be 6 digits.';
        }

        if (!$signupData) {
            SessionHelper::setFlash('error', 'Session expired. Please sign up again.');
            unset($_SESSION['signup_step']);
            redirect(route('signup'));
        }

        if (!empty($errors)) {
            // Stay on OTP verification page with error
            $_SESSION['signup_step'] = 2;
        } else {

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

                    <style>
                    /* ── OTP Box UI ── */
                    .otp-label {
                        font-size: 0.85rem;
                        font-weight: 600;
                        color: #6c757d;
                        letter-spacing: 0.04em;
                        text-transform: uppercase;
                        margin-bottom: 0.75rem;
                    }
                    .otp-email-badge {
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                        background: #f0f4ff;
                        border: 1px solid #d0dbff;
                        border-radius: 20px;
                        padding: 5px 14px;
                        font-size: 0.875rem;
                        font-weight: 500;
                        color: #3b5bdb;
                        margin-bottom: 1.5rem;
                    }
                    .otp-boxes-wrapper {
                        display: flex;
                        gap: 10px;
                        justify-content: center;
                        margin-bottom: 0.5rem;
                    }
                    .otp-box {
                        width: 50px;
                        height: 58px;
                        border: 2px solid #dee2e6;
                        border-radius: 12px;
                        text-align: center;
                        font-size: 1.6rem;
                        font-weight: 700;
                        color: #212529;
                        background: #fff;
                        outline: none;
                        transition: border-color 0.18s, box-shadow 0.18s, background 0.18s, transform 0.12s;
                        caret-color: transparent;
                        -moz-appearance: textfield;
                    }
                    .otp-box::-webkit-outer-spin-button,
                    .otp-box::-webkit-inner-spin-button { -webkit-appearance: none; }
                    .otp-box:focus {
                        border-color: #4361ee;
                        box-shadow: 0 0 0 3px rgba(67,97,238,0.15);
                        background: #f5f7ff;
                        transform: translateY(-2px);
                    }
                    .otp-box.filled {
                        border-color: #4361ee;
                        background: #f5f7ff;
                    }
                    .otp-box.is-invalid-box {
                        border-color: #dc3545 !important;
                        background: #fff5f5 !important;
                        box-shadow: 0 0 0 3px rgba(220,53,69,0.12) !important;
                        animation: shake 0.35s ease;
                    }
                    @keyframes shake {
                        0%,100% { transform: translateX(0); }
                        20%      { transform: translateX(-5px); }
                        40%      { transform: translateX(5px); }
                        60%      { transform: translateX(-4px); }
                        80%      { transform: translateX(4px); }
                    }
                    .otp-error-msg {
                        color: #dc3545;
                        font-size: 0.82rem;
                        text-align: center;
                        margin-top: 6px;
                        min-height: 1.1em;
                    }
                    .otp-timer-row {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 6px;
                        font-size: 0.85rem;
                        color: #6c757d;
                        margin: 0.75rem 0 1.25rem;
                    }
                    .otp-timer-row #countdown {
                        font-weight: 700;
                        color: #4361ee;
                        font-variant-numeric: tabular-nums;
                        min-width: 2.8rem;
                        display: inline-block;
                    }
                    .otp-timer-row #countdown.expired { color: #dc3545; }
                    .otp-timer-row .timer-icon { font-size: 0.9rem; }
                    /* Paste hint */
                    .otp-paste-hint {
                        font-size: 0.78rem;
                        color: #adb5bd;
                        text-align: center;
                        margin-bottom: 0.25rem;
                    }
                    </style>

                    <div class="text-center">
                        <span class="otp-email-badge">
                            <i class="bi bi-envelope-check-fill"></i>
                            <?= htmlspecialchars($signupEmail) ?>
                        </span>
                    </div>

                    <form method="POST" action="<?= route('signup') ?>" id="otpForm">
                        <?= SecurityHelper::csrfField() ?>
                        <input type="hidden" name="action" value="verify_otp">
                        <!-- Hidden input that carries the assembled 6-digit OTP to the backend (unchanged field name) -->
                        <input type="hidden" name="otp" id="otpHidden">

                        <div class="mb-1">
                            <div class="otp-label text-center">Enter verification code</div>
                            <div class="otp-boxes-wrapper" id="otpBoxesWrapper">
                                <input class="otp-box <?= isset($errors['otp']) ? 'is-invalid-box' : '' ?>" type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" data-index="0">
                                <input class="otp-box <?= isset($errors['otp']) ? 'is-invalid-box' : '' ?>" type="text" inputmode="numeric" maxlength="1" data-index="1">
                                <input class="otp-box <?= isset($errors['otp']) ? 'is-invalid-box' : '' ?>" type="text" inputmode="numeric" maxlength="1" data-index="2">
                                <input class="otp-box <?= isset($errors['otp']) ? 'is-invalid-box' : '' ?>" type="text" inputmode="numeric" maxlength="1" data-index="3">
                                <input class="otp-box <?= isset($errors['otp']) ? 'is-invalid-box' : '' ?>" type="text" inputmode="numeric" maxlength="1" data-index="4">
                                <input class="otp-box <?= isset($errors['otp']) ? 'is-invalid-box' : '' ?>" type="text" inputmode="numeric" maxlength="1" data-index="5">
                            </div>
                            <div class="otp-error-msg" id="otpErrorMsg">
                                <?= isset($errors['otp']) ? htmlspecialchars($errors['otp']) : '' ?>
                            </div>
                            <p class="otp-paste-hint">You can paste your OTP directly</p>
                        </div>

                        <div class="otp-timer-row" id="otpTimer">
                            <i class="bi bi-clock timer-icon"></i>
                            OTP expires in <span id="countdown">10:00</span>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg" id="verifyBtn">
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

// ── OTP 6-box logic ──
<?php if ($step === 2): ?>
(function () {
    const boxes     = Array.from(document.querySelectorAll('.otp-box'));
    const hidden    = document.getElementById('otpHidden');
    const form      = document.getElementById('otpForm');
    const errorMsg  = document.getElementById('otpErrorMsg');

    // Helper: sync hidden input with box values
    function syncHidden() {
        hidden.value = boxes.map(b => b.value).join('');
    }

    // Mark filled state
    function updateFilled(box) {
        box.classList.toggle('filled', box.value !== '');
    }

    // Focus first empty box or last
    function focusNext(currentIndex) {
        const next = boxes[currentIndex + 1];
        if (next) next.focus();
    }
    function focusPrev(currentIndex) {
        const prev = boxes[currentIndex - 1];
        if (prev) prev.focus();
    }

    boxes.forEach((box, i) => {
        // Allow only digits on keydown
        box.addEventListener('keydown', function (e) {
            // Backspace: clear current or go back
            if (e.key === 'Backspace') {
                e.preventDefault();
                if (this.value) {
                    this.value = '';
                    updateFilled(this);
                    syncHidden();
                } else {
                    focusPrev(i);
                    if (boxes[i - 1]) {
                        boxes[i - 1].value = '';
                        updateFilled(boxes[i - 1]);
                        syncHidden();
                    }
                }
                return;
            }
            // Arrow keys
            if (e.key === 'ArrowLeft')  { e.preventDefault(); focusPrev(i); return; }
            if (e.key === 'ArrowRight') { e.preventDefault(); focusNext(i); return; }
            // Block non-digit keys (except tab, ctrl combos for paste)
            if (!/^\d$/.test(e.key) && !e.ctrlKey && !e.metaKey && e.key !== 'Tab') {
                e.preventDefault();
            }
        });

        box.addEventListener('input', function () {
            // Sanitise: keep only last digit in case browser sneaks two chars
            this.value = this.value.replace(/[^0-9]/g, '').slice(-1);
            updateFilled(this);
            syncHidden();
            if (this.value) focusNext(i);
        });

        // Handle paste on any box: distribute digits across all boxes
        box.addEventListener('paste', function (e) {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData)
                .getData('text')
                .replace(/[^0-9]/g, '')
                .slice(0, 6);
            if (!pasted) return;
            pasted.split('').forEach((ch, idx) => {
                if (boxes[idx]) {
                    boxes[idx].value = ch;
                    updateFilled(boxes[idx]);
                }
            });
            syncHidden();
            // Focus the box after the last pasted digit
            const nextFocus = Math.min(pasted.length, 5);
            boxes[nextFocus].focus();
        });

        // Select all text on focus so re-typing a digit is effortless
        box.addEventListener('focus', function () { this.select(); });
    });

    // Form submit: validate all 6 boxes filled
    form.addEventListener('submit', function (e) {
        syncHidden();
        const val = hidden.value;
        if (!/^\d{6}$/.test(val)) {
            e.preventDefault();
            // Shake all boxes and show error
            boxes.forEach(b => {
                b.classList.add('is-invalid-box');
                b.addEventListener('input', () => b.classList.remove('is-invalid-box'), { once: true });
            });
            errorMsg.textContent = 'Please enter all 6 digits.';
            boxes.find(b => b.value === '')?.focus();
        }
    });

    // Auto-focus first box on load
    boxes[0]?.focus();

    // OTP countdown timer
    let timeLeft = 600;
    const countdown = document.getElementById('countdown');
    const timer = setInterval(() => {
        if (timeLeft <= 0) {
            clearInterval(timer);
            countdown.textContent = 'Expired';
            countdown.classList.add('expired');
            return;
        }
        const m = Math.floor(timeLeft / 60);
        const s = timeLeft % 60;
        countdown.textContent = `${m}:${s.toString().padStart(2, '0')}`;
        timeLeft--;
    }, 1000);
})();
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/views/layouts/main.php';