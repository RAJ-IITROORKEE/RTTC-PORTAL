<?php
require_once __DIR__ . '/config/init.php';

$isLoggedIn = SessionHelper::isLoggedIn();
$progress   = SessionHelper::getProgress();

// Sync progress from DB if logged in
if ($isLoggedIn) {
    SessionHelper::syncProgress($conn, (int) SessionHelper::get('user_id'));
    $progress = SessionHelper::getProgress();
}

$pageTitle = 'Home – RTTC 2026 Registration Portal';
$activeNav = 'home';

$marqueeItems = SiteSettingsHelper::getMarqueeItems();

$noticeDocs = SiteSettingsHelper::getNoticeDocuments(true);
$noticeDocMap = [];
$noticeButtons = [];

foreach ($noticeDocs as $doc) {
    $docKey = trim((string)($doc['doc_key'] ?? ''));
    if ($docKey !== '') {
        $noticeDocMap[$docKey] = $doc;
    }

    $url = SiteSettingsHelper::getDocumentUrl($doc);
    if ($url !== '') {
        $noticeButtons[] = [
            'key' => $docKey,
            'label' => trim((string)($doc['button_label'] ?? 'View PDF')) ?: 'View PDF',
            'url' => $url,
        ];
    }
}

$termsDoc = $noticeDocMap['terms_conditions'] ?? null;
$termsUrl = SiteSettingsHelper::getDocumentUrl($termsDoc);

ob_start();
?>

<!-- Marquee Banner -->
<div class="marquee-container">
  <span class="marquee">
    <i class="bi bi-info-circle me-2"></i>
    <?php foreach ($marqueeItems as $idx => $item): ?>
      <?= htmlspecialchars((string)($item['content'] ?? '')) ?>
      <?php if (!empty($item['link_url'])): ?>
        <a href="<?= htmlspecialchars($item['link_url']) ?>" target="_blank" rel="noopener" class="marquee-cta ms-2">
          <?= htmlspecialchars((string)($item['link_label'] ?? 'Click Here')) ?>
        </a>
      <?php endif; ?>
      <?php if ($idx < count($marqueeItems) - 1): ?>
        &nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
      <?php endif; ?>
    <?php endforeach; ?>
  </span>
</div>

<!-- Note Alert -->
<div class="container mt-3">
  <div class="alert alert-warning d-flex gap-2 align-items-start" role="alert">
    <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
    <div>
      <strong>Important Note:</strong> To complete your application process, payment of the registration fee is required
      after form submission. Applications without payment will <strong>not</strong> be processed.
      While making the payment, please use <strong>only the registered phone number</strong>.
      <?php if ($termsUrl !== ''): ?>
      <a href="<?= htmlspecialchars($termsUrl) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary ms-2">
        <i class="bi bi-file-earmark-text me-1"></i><?= htmlspecialchars((string)($termsDoc['button_label'] ?? 'Terms & Conditions')) ?>
      </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Hero Row: College Info + Notice -->
<div class="container mt-3 mb-4">
  <div class="row g-3">
    <!-- College Info -->
    <div class="col-md-6">
      <div class="rttc-card h-100 p-4">
        <div class="d-flex align-items-center gap-3 mb-3">
          <img src="<?= rtrim(APP_URL,'/') ?>/assets/img/RTTC_logo.jpeg" alt="RTTC Logo"
               class="rounded-circle" style="height:64px;width:64px;object-fit:cover;border:3px solid var(--rttc-primary);">
          <div>
            <h5 class="mb-0 fw-bold" style="color:var(--rttc-primary);">Rangia Teacher Training College</h5>
            <small class="text-muted">Established & Recognised by NCTE</small>
          </div>
        </div>
        <hr>
        <p class="mb-1"><i class="bi bi-geo-alt-fill me-2 text-primary"></i><?= COLLEGE_ADDRESS ?></p>
        <p class="mb-1"><i class="bi bi-telephone-fill me-2 text-primary"></i><?= CONTACT_PHONE ?></p>
        <p class="mb-0"><i class="bi bi-envelope-fill me-2 text-primary"></i><?= CONTACT_EMAIL ?></p>
      </div>
    </div>
    <!-- Notice -->
    <div class="col-md-6">
      <div class="rttc-card h-100 p-4" style="border-left:4px solid var(--rttc-primary);">
        <h6 class="fw-bold" style="color:var(--rttc-primary);"><i class="bi bi-megaphone-fill me-2"></i>NOTICE</h6>
        <ul class="mb-3 ps-3" style="font-size:.9rem;">
          <li>Please read the application instructions carefully before proceeding.</li>
          <li>Carry all required physical documents during in-person admission process.</li>
          <li>Only GUBEDCET 2026 qualified candidates are eligible to apply.</li>
          <li>Minimum marks criteria must be fulfilled as per category.</li>
        </ul>
        <div class="d-flex flex-wrap gap-2">
          <?php if (!empty($noticeButtons)): ?>
            <?php foreach ($noticeButtons as $btn): ?>
              <?php
                $btnClass = in_array($btn['key'], ['instructions'], true) ? 'btn-rttc-primary' : 'btn-outline-primary';
                $icon = in_array($btn['key'], ['required_documents'], true) ? 'bi-list-check' : 'bi-file-earmark-pdf';
              ?>
              <a href="<?= htmlspecialchars($btn['url']) ?>" target="_blank" rel="noopener" class="btn btn-sm <?= $btnClass ?>">
                <i class="bi <?= $icon ?> me-1"></i><?= htmlspecialchars($btn['label']) ?>
              </a>
            <?php endforeach; ?>
          <?php else: ?>
            <span class="text-muted small">No notice documents configured.</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Action Cards -->
<div class="container mb-5">
  <div class="row g-4 justify-content-center">
    <!-- Registration Card -->
    <div class="col-md-5">
      <div class="rttc-card text-center p-4 h-100 d-flex flex-column justify-content-between">
        <div>
          <div class="mb-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle"
                  style="width:80px;height:80px;background:rgba(39,39,109,.08);">
              <i class="bi bi-pencil-square" style="font-size:2.5rem;color:var(--rttc-primary);"></i>
            </span>
          </div>
          <h4 class="fw-bold" style="color:var(--rttc-primary);">New Registration</h4>
          <p class="text-muted" style="font-size:.9rem;">
            Apply for B.Ed. First Year admission (2025-26). Complete your registration in 4 simple steps:
            Personal Details → Academic Details → Document Upload → Payment.
          </p>
        </div>
        <?php if ($isLoggedIn): ?>
          <a href="<?= route('welcome') ?>" class="btn btn-rttc-primary mt-3">
            <i class="bi bi-arrow-right-circle me-2"></i>Continue Registration
          </a>
        <?php else: ?>
          <a href="<?= route('signup') ?>" class="btn btn-rttc-primary mt-3">
            <i class="bi bi-person-plus me-2"></i>Sign Up to Apply
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Status / Download Card -->
    <div class="col-md-5">
      <div class="rttc-card text-center p-4 h-100 d-flex flex-column justify-content-between"
           style="border-top:4px solid var(--rttc-success);">
        <div>
          <div class="mb-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle"
                  style="width:80px;height:80px;background:rgba(25,135,84,.08);">
              <?php if ($isLoggedIn && $progress === 4): ?>
                <i class="bi bi-check-circle-fill" style="font-size:2.5rem;color:var(--rttc-success);"></i>
              <?php else: ?>
                <i class="bi bi-people-fill" style="font-size:2.5rem;color:var(--rttc-success);"></i>
              <?php endif; ?>
            </span>
          </div>
        <?php if ($isLoggedIn && $progress === 4): ?>
            <h4 class="fw-bold text-success">Registration Complete!</h4>
            <p class="text-muted" style="font-size:.9rem;">
              Your application has been submitted and payment received. Download your
              payment receipt or full application form below.
            </p>
          </div>
          <div class="d-flex flex-column gap-2 mt-3">
            <a href="<?= route('payment.confirmation') ?>#receiptSection" class="btn btn-outline-primary w-100">
              <i class="bi bi-file-earmark-pdf me-2"></i>Download Payment Receipt
            </a>
            <a href="<?= route('payment.confirmation') ?>#applicationSection" class="btn btn-rttc-success w-100">
              <i class="bi bi-file-earmark-arrow-down me-2"></i>Download Application Form
            </a>
          </div>
        <?php elseif ($isLoggedIn): ?>
          <a href="<?= route('welcome') ?>" class="btn btn-outline-success mt-3">
            <i class="bi bi-arrow-right-circle me-2"></i>Go to My Dashboard
          </a>
        <?php else: ?>
          <a href="<?= route('login') ?>" class="btn btn-outline-success mt-3">
            <i class="bi bi-box-arrow-in-right me-2"></i>Login to Continue
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Eligibility Info -->
<div class="container mb-5">
  <div class="section-title"><i class="bi bi-info-circle me-2"></i>Eligibility Criteria</div>
  <div class="row g-3">
    <div class="col-sm-6 col-lg-3">
      <div class="rttc-card p-3 text-center h-100">
        <i class="bi bi-people-fill fs-3 text-primary mb-2"></i>
        <h6 class="fw-bold">General Category</h6>
        <p class="text-muted mb-0" style="font-size:.85rem;">Minimum <strong>80 marks</strong> in GUBEDCET 2026</p>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="rttc-card p-3 text-center h-100">
        <i class="bi bi-person-badge-fill fs-3 text-primary mb-2"></i>
        <h6 class="fw-bold">OBC/MOBC/SC/STP/STH</h6>
        <p class="text-muted mb-0" style="font-size:.85rem;">Minimum <strong>60 marks</strong> in GUBEDCET 2026</p>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="rttc-card p-3 text-center h-100">
        <i class="bi bi-heart-pulse-fill fs-3 text-primary mb-2"></i>
        <h6 class="fw-bold">PWD Category</h6>
        <p class="text-muted mb-0" style="font-size:.85rem;">Minimum <strong>60 marks</strong> + valid PWD certificate</p>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="rttc-card p-3 text-center h-100">
        <i class="bi bi-award-fill fs-3 text-primary mb-2"></i>
        <h6 class="fw-bold">EWS Category</h6>
        <p class="text-muted mb-0" style="font-size:.85rem;">Minimum <strong>60 marks</strong> + valid EWS certificate</p>
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/views/layouts/main.php';
