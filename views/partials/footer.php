<?php $baseUrl = rtrim(APP_URL, '/'); ?>
<footer class="rttc-footer">
  <div class="container">
    <div class="row gy-3">
      <!-- College Info -->
      <div class="col-md-4">
        <div class="d-flex align-items-center gap-2 mb-2">
          <img src="<?= $baseUrl ?>/assets/img/RTTC_logo.jpeg" alt="Logo"
               style="height:42px;width:42px;border-radius:50%;border:2px solid rgba(255,255,255,.5);">
          <strong style="font-size:1rem;">Rangia Teacher Training College</strong>
        </div>
        <small><?= COLLEGE_ADDRESS ?></small><br>
        <small><i class="bi bi-telephone me-1"></i><?= CONTACT_PHONE ?></small><br>
        <small><i class="bi bi-envelope me-1"></i><?= CONTACT_EMAIL ?></small>
      </div>
      <!-- Quick Links -->
      <div class="col-md-4">
        <h6 class="fw-bold mb-2 text-white">Quick Links</h6>
        <ul class="list-unstyled mb-0" style="font-size:.87rem;">
          <li><a href="<?= route('home') ?>"><i class="bi bi-chevron-right me-1"></i>Home</a></li>
          <li><a href="<?= route('login') ?>"><i class="bi bi-chevron-right me-1"></i>Applicant Login</a></li>
          <li><a href="<?= route('signup') ?>"><i class="bi bi-chevron-right me-1"></i>New Registration</a></li>
          <li><a href="http://www.rangiattcollege.in" target="_blank"><i class="bi bi-chevron-right me-1"></i>College Website</a></li>
        </ul>
      </div>
      <!-- Important Info -->
      <div class="col-md-4">
        <h6 class="fw-bold mb-2 text-white">Important</h6>
        <ul class="list-unstyled mb-0" style="font-size:.87rem;">
          <li><i class="bi bi-dot me-1"></i>B.Ed. First Year Admission 2026-2027</li>
          <li><i class="bi bi-dot me-1"></i>GUBEDCET 2026 Based Admission</li>
          <li><i class="bi bi-dot me-1"></i>Registration fee: ₹500 (non-refundable)</li>
          <li><i class="bi bi-dot me-1"></i>Pay via registered phone number only</li>
        </ul>
      </div>
    </div>
    <hr>
    <div class="text-center" style="font-size:.8rem;opacity:.7;">
      &copy; <?= date('Y') ?> Rangia Teacher Training College. All rights reserved.
      &nbsp;|&nbsp; Powered by RTTC Portal v<?= APP_VERSION ?>
    </div>
  </div>
</footer>
