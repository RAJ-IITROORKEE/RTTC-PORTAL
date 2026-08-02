<?php $baseUrl = rtrim(APP_URL, '/'); ?>
<footer class="rttc-footer">
  <div class="container">
    <div class="row gy-3">
      <!-- College Info -->
      <div class="col-md-4">
        <div class="rttc-footer-brand">
          <img src="<?= $baseUrl ?>/assets/img/RTTC_logo.jpeg" alt="Logo"
               class="rttc-footer-brand-logo">
          <div class="rttc-footer-brand-copy">
            <div class="rttc-footer-brand-title">Rangia Teacher Training College</div>
            <div class="rttc-footer-brand-affiliation">
              <div>Recognized by NCTE, affiliated to Gauhati University and</div>
              <div>conveyed concurrence by Govt. of Assam</div>
            </div>
            <div class="rttc-footer-brand-portal">B.Ed Admission Portal 2026-27</div>
          </div>
        </div>
        <small class="rttc-footer-address"><?= COLLEGE_ADDRESS ?></small>
      </div>
      <!-- Quick Links -->
      <div class="col-md-4">
        <h6 class="fw-bold mb-2 text-white">Quick Links</h6>
        <ul class="list-unstyled mb-0" style="font-size:.87rem;">
          <li><a href="<?= route('home') ?>"><i class="bi bi-chevron-right me-1"></i>Home</a></li>
          <li><a href="<?= route('login') ?>"><i class="bi bi-chevron-right me-1"></i>Applicant Login</a></li>
           <?php if (SiteSettingsHelper::isRegistrationOpen()): ?>
           <li><a href="<?= route('signup') ?>"><i class="bi bi-chevron-right me-1"></i>New Registration</a></li>
           <?php endif; ?>
          <li><a href="http://www.rangiattcollege.in" target="_blank"><i class="bi bi-chevron-right me-1"></i>College Website</a></li>
        </ul>
      </div>
      <!-- Important Info -->
      <div class="col-md-4">
        <h6 class="fw-bold mb-2 text-white">Important</h6>
        <ul class="list-unstyled mb-0" style="font-size:.87rem;">
           <li><i class="bi bi-dot me-1"></i>B.Ed admission 2026-27</li>
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
