<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';

SecurityHelper::requireGuest(); // if not admin

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SecurityHelper::verifyCsrf();

    $email    = SecurityHelper::sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errors['form'] = 'Email and password are required.';
    } else {
        $db   = db();
        $stmt = $db->prepare("SELECT id, name, email, password FROM admin_users WHERE email = ? AND is_active = 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($admin && SecurityHelper::verifyPassword($password, $admin['password'])) {
            SessionHelper::setAdminSession($admin['id'], $admin['email'], $admin['name'], 'admin');
            redirect('admin.dashboard');
        } else {
            $errors['form'] = 'Invalid email or password.';
        }
    }
}

$pageTitle = 'Admin Login - RTTC 2026';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/RTTC_logo.jpeg" type="image/jpeg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
</head>
<body>
<section class="auth-page-wrapper admin-auth-shell py-4">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
                <div class="card auth-card border-0">
                    <div class="auth-header">
                        <img src="<?= BASE_URL ?>/assets/img/RTTC_logo.jpeg" alt="RTTC Logo">
                        <h4 class="mb-1">Admin Login</h4>
                        <small>RTTC Admission Portal 2026</small>
                    </div>

                    <div class="card-body">
                        <?php if (!empty($errors['form'])): ?>
                            <div class="alert alert-danger d-flex gap-2" role="alert">
                                <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                                <div><?= htmlspecialchars($errors['form']) ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= route('admin.login') ?>" autocomplete="off">
                            <?= SecurityHelper::csrfField() ?>

                            <div class="mb-3">
                                <label for="adminEmail" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" id="adminEmail" name="email" class="form-control" required autofocus>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="adminPassword" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" id="adminPassword" name="password" class="form-control" required>
                                    <button type="button" class="btn btn-outline-secondary" id="toggleAdminPwd">
                                        <i class="bi bi-eye-slash" id="adminEyeIcon"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-rttc-primary w-100 py-2 fw-semibold">
                                <i class="bi bi-shield-lock me-2"></i>Login to Admin Panel
                            </button>
                        </form>

                        <hr class="my-3">
                        <p class="text-center mb-0 small text-muted">
                            <a href="<?= route('home') ?>" class="text-primary">&larr; Back to main site</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('toggleAdminPwd')?.addEventListener('click', function() {
    const input = document.getElementById('adminPassword');
    const icon = document.getElementById('adminEyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye-slash';
    }
});
</script>
</body>
</html>
