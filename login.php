<?php
require_once __DIR__ . '/config/init.php';

// Already logged in → redirect
SecurityHelper::requireGuest();

$errors   = [];
$formData = [];

// ── CAPTCHA generation ───────────────────────────────────
if (isset($_GET['captcha'])) {
    // Session is already started by init.php
    $chars  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code   = '';
    for ($i = 0; $i < 6; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
    $_SESSION['captcha_code'] = $code;

    $width = 130; $height = 44;
    $img   = imagecreatetruecolor($width, $height);
    $bg    = imagecolorallocate($img, 248, 249, 250);
    $fc    = imagecolorallocate($img, 39, 39, 109);
    imagefill($img, 0, 0, $bg);
    for ($i = 0; $i < 40; $i++) {
        $dc = imagecolorallocate($img, random_int(180, 230), random_int(180, 230), random_int(180, 230));
        imagesetpixel($img, random_int(0, $width), random_int(0, $height), $dc);
    }
    $font = __DIR__ . '/assets/font/monofont.ttf';
    if (file_exists($font)) imagettftext($img, 22, 0, 8, 34, $fc, $font, $code);
    else imagestring($img, 5, 10, 12, $code, $fc);
    header('Content-Type: image/png');
    imagepng($img);
    imagedestroy($img);
    exit;
}

// ── POST handling ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SecurityHelper::validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Security token mismatch. Please reload and try again.';
    } else {
        $email    = SecurityHelper::sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $captcha  = strtoupper(trim($_POST['captcha'] ?? ''));

        if (empty($email))    $errors[] = 'Email is required.';
        if (empty($password)) $errors[] = 'Password is required.';
        if (empty($captcha))  $errors[] = 'Please enter the CAPTCHA.';
        elseif ($captcha !== ($_SESSION['captcha_code'] ?? '')) $errors[] = 'CAPTCHA verification failed.';

        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT id, username, email, phone, password, created_at FROM users WHERE email=? AND is_active=1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row && SecurityHelper::verifyPassword($password, $row['password'])) {
                SessionHelper::setUserSession((int)$row['id'], $row['email'], $row['phone'], $row['username']);
                SessionHelper::set('registration_time', $row['created_at']);
                SessionHelper::syncProgress($conn, (int)$row['id']);
                SessionHelper::regenerate();
                redirect('welcome', [], 'success', 'Welcome back, ' . $row['username'] . '!');
            } else {
                $errors[] = 'Invalid email or password. Please try again.';
            }
        }
        $formData['email'] = $email;
    }
}

$csrfToken = SecurityHelper::generateCsrfToken();
$pageTitle = 'Login – RTTC 2026';
ob_start();
?>

<section class="py-5 mt-2">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-sm-10 col-md-8 col-lg-6 col-xl-5">

        <div class="card auth-card border-0">
          <!-- Card Header -->
          <div class="auth-header">
            <img src="<?= rtrim(APP_URL,'/') ?>/assets/img/RTTC_logo.jpeg" alt="RTTC Logo">
            <h4>Student Login</h4>
            <small>Rangia Teacher Training College – B.Ed. 2026-2027</small>
          </div>

          <div class="card-body">
            <!-- Errors -->
            <?php if (!empty($errors)): ?>
              <div class="alert alert-danger d-flex gap-2" role="alert">
                <i class="bi bi-x-circle-fill mt-1 flex-shrink-0"></i>
                <ul class="mb-0 ps-3">
                  <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <form method="POST" action="<?= route('login') ?>" autocomplete="off" novalidate>
              <?= SecurityHelper::csrfField() ?>

              <!-- Email -->
              <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                  <input type="email" id="email" name="email" class="form-control"
                         placeholder="your@email.com"
                         value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
                         required autocomplete="username">
                </div>
              </div>

              <!-- Password -->
              <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-lock"></i></span>
                  <input type="password" id="password" name="password" class="form-control"
                         placeholder="Enter password" required autocomplete="current-password">
                  <button class="btn btn-outline-secondary" type="button" id="togglePwd">
                    <i class="bi bi-eye-slash" id="eyeIcon"></i>
                  </button>
                </div>
              </div>

              <!-- CAPTCHA -->
              <div class="mb-3 captcha-box">
                <label class="form-label fw-semibold">Verify CAPTCHA</label>
                <div class="d-flex align-items-center gap-3 mb-2">
                  <img id="captchaImg" src="<?= route('login') ?>?captcha=1" alt="CAPTCHA"
                       class="rounded" style="height:44px;">
                  <span class="captcha-refresh" onclick="refreshCaptcha()" title="Refresh">
                    <i class="bi bi-arrow-clockwise"></i>
                  </span>
                </div>
                <input type="text" name="captcha" class="form-control text-uppercase"
                       placeholder="Enter 6-character code" maxlength="6"
                       autocomplete="off" required style="letter-spacing:.2em;">
              </div>

              <!-- Remember + Forgot -->
              <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-check mb-0">
                  <input class="form-check-input" type="checkbox" id="remember">
                  <label class="form-check-label" for="remember" style="font-size:.88rem;">Remember me</label>
                </div>
                <a href="<?= route('forgot-password') ?>" style="font-size:.88rem;color:var(--rttc-primary);">
                  Forgot password?
                </a>
              </div>

              <!-- Submit -->
              <button type="submit" class="btn btn-rttc-primary w-100 py-2 fw-semibold">
                <i class="bi bi-box-arrow-in-right me-2"></i>Login
              </button>
            </form>

            <hr>
            <p class="text-center mb-0" style="font-size:.9rem;">
              Don't have an account?
              <a href="<?= route('signup') ?>" class="fw-semibold" style="color:var(--rttc-primary);">Register here</a>
            </p>
          </div>
        </div>

      </div>
    </div>
  </div>
</section>

<?php
$content  = ob_get_clean();
$loginRouteJson = json_encode(route('login'));
$extraFoot = <<<JS
<script>
const loginCaptchaUrl = {$loginRouteJson};
function refreshCaptcha() {
  document.getElementById('captchaImg').src = loginCaptchaUrl + '?captcha=1&t=' + Date.now();
}
document.getElementById('togglePwd')?.addEventListener('click', function() {
  const pwd = document.getElementById('password');
  const ico = document.getElementById('eyeIcon');
  if (pwd.type === 'password') { pwd.type = 'text'; ico.className = 'bi bi-eye'; }
  else { pwd.type = 'password'; ico.className = 'bi bi-eye-slash'; }
});
</script>
JS;
include __DIR__ . '/views/layouts/main.php';
