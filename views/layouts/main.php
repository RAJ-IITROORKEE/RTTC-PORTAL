<?php
/**
 * RTTC 2026 - Main Layout
 * Usage: $pageTitle, $activeNav, $extraHead, $extraFoot must be set before including.
 */
$pageTitle = $pageTitle ?? 'RTTC 2026 – Rangia Teacher Training College';
$activeNav = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?= htmlspecialchars($pageTitle) ?> | RTTC 2026</title>
  <!-- Favicon -->
  <link rel="icon" href="<?= rtrim(APP_URL,'/') ?>/assets/img/RTTC_logo.jpeg" type="image/jpeg">
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- Bootstrap 5.3 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <!-- App CSS -->
  <link href="<?= rtrim(APP_URL,'/') ?>/assets/css/app.css" rel="stylesheet">
  <?= $extraHead ?? '' ?>
</head>
<body>

<!-- Page Loader -->
<div id="page-loader"><div class="spinner-grow" role="status"><span class="visually-hidden">Loading...</span></div></div>

<!-- Navbar -->
<?php include __DIR__ . '/../partials/navbar.php'; ?>

<!-- Flash Messages -->
<?php include __DIR__ . '/../partials/flash.php'; ?>

<!-- Main Content -->
<main>
  <?= $content ?? '' ?>
</main>

<!-- Footer -->
<?php include __DIR__ . '/../partials/footer.php'; ?>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- App JS -->
<script src="<?= rtrim(APP_URL,'/') ?>/assets/js/app.js"></script>
<?= $extraFoot ?? '' ?>
</body>
</html>
