<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';

http_response_code(404);

$pageTitle = '404 - Page Not Found';
$activeNav = '';
ob_start();
?>

<section class="py-5 mt-3">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-9 col-lg-7">
        <div class="rttc-card text-center p-5">
          <div class="mb-3" style="font-size:4rem;line-height:1;color:var(--rttc-primary);">404</div>
          <h3 class="fw-bold mb-2" style="color:var(--rttc-primary);">Page Not Found</h3>
          <p class="text-muted mb-4">
            The page you are looking for does not exist or the URL is incorrect.
          </p>
          <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="<?= route('home') ?>" class="btn btn-rttc-primary">
              <i class="bi bi-house-door me-1"></i>Go to Home
            </a>
            <button type="button" class="btn btn-outline-primary" onclick="window.history.back()">
              <i class="bi bi-arrow-left me-1"></i>Go Back
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php
$content = ob_get_clean();
include BASE_PATH . '/views/layouts/main.php';
