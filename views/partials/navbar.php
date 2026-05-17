<?php
/**
 * RTTC 2026 - Navbar Partial
 */
$isLoggedIn = SessionHelper::isLoggedIn();
$username   = SessionHelper::get('username', '');
$progress   = SessionHelper::getProgress();
$baseUrl    = rtrim(APP_URL, '/');
?>
<nav class="navbar navbar-expand-lg rttc-navbar sticky-top">
  <div class="container">
    <!-- Brand -->
    <a class="navbar-brand" href="<?= route('home') ?>">
      <img src="<?= $baseUrl ?>/assets/img/RTTC_logo.jpeg" alt="RTTC Logo">
      <span>
        <span class="d-block" style="font-size:1rem;line-height:1.2;">Rangia TTC</span>
        <small class="d-block fw-normal" style="font-size:.72rem;opacity:.75;">B.Ed. Admission 2025-26</small>
      </span>
    </a>

    <!-- Toggler -->
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <i class="bi bi-list fs-4" style="color:var(--rttc-primary)"></i>
    </button>

    <!-- Nav links -->
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto align-items-center gap-1">
        <li class="nav-item">
          <a class="nav-link <?= $activeNav === 'home' ? 'active' : '' ?>" href="<?= route('home') ?>">
            <i class="bi bi-house-door me-1"></i>Home
          </a>
        </li>

        <?php if ($isLoggedIn): ?>
          <li class="nav-item">
            <a class="nav-link <?= $activeNav === 'welcome' ? 'active' : '' ?>" href="<?= route('welcome') ?>">
              <i class="bi bi-grid-1x2 me-1"></i>Dashboard
            </a>
          </li>
          <?php if ($progress < 4): ?>
          <li class="nav-item">
            <a class="nav-link <?= $activeNav === 'registration' ? 'active' : '' ?>" href="<?= route('registration') ?>">
              <i class="bi bi-person-lines-fill me-1"></i>Registration
            </a>
          </li>
          <?php endif; ?>
          <li class="nav-item">
            <a class="nav-link <?= $activeNav === 'request-query' ? 'active' : '' ?>" href="<?= route('request-query') ?>">
              <i class="bi bi-chat-left-text me-1"></i>Raise Query
            </a>
          </li>
          <!-- User dropdown -->
          <li class="nav-item dropdown ms-2">
            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 px-3 py-2 rounded-pill"
               style="background:rgba(39,39,109,.08);"
               href="#" data-bs-toggle="dropdown">
              <i class="bi bi-person-circle" style="font-size:1.2rem;"></i>
              <span style="font-size:.88rem;"><?= htmlspecialchars($username) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="min-width:200px;">
              <li><a class="dropdown-item" href="<?= route('welcome') ?>"><i class="bi bi-speedometer2 me-2"></i>My Dashboard</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="<?= route('logout') ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <a class="nav-link" href="<?= route('login') ?>"><i class="bi bi-box-arrow-in-right me-1"></i>Login</a>
          </li>
          <li class="nav-item ms-1">
            <a class="btn btn-rttc-primary btn-sm px-3" href="<?= route('signup') ?>">
              <i class="bi bi-person-plus me-1"></i>Register
            </a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
