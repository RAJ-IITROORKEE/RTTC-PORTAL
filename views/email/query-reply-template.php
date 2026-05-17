<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Query Reply - RTTC 2026</title>
  <style>
    body { margin:0; padding:0; background:#f4f6fb; font-family:'Segoe UI',Arial,sans-serif; color:#333; }
    .wrapper { max-width:620px; margin:32px auto; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.08); }
    .header { background:linear-gradient(135deg,#27276d 0%,#3a3a9e 100%); color:#fff; padding:32px 40px 24px; text-align:center; }
    .header img { height:52px; border-radius:8px; margin-bottom:14px; }
    .header h2 { margin:0; font-size:1.4rem; font-weight:700; }
    .header p  { margin:6px 0 0; font-size:.9rem; opacity:.8; }
    .body { padding:36px 40px; }
    .greeting { font-size:1.05rem; font-weight:600; margin-bottom:8px; }
    .intro { color:#555; line-height:1.7; margin-bottom:24px; }
    .subject-badge { display:inline-block; background:#e8f4fd; color:#1565c0; border-radius:20px; padding:4px 16px; font-size:.85rem; font-weight:600; margin-bottom:20px; }
    .reply-box { background:#f8f9fa; border-left:4px solid #27276d; border-radius:8px; padding:20px 24px; line-height:1.8; color:#333; margin-bottom:24px; }
    .access-notice { background:#e8f5e9; border:1px solid #a5d6a7; border-radius:8px; padding:16px 20px; color:#2e7d32; margin-bottom:24px; font-size:.92rem; }
    .access-notice strong { display:block; margin-bottom:4px; }
    .cta-btn { display:inline-block; background:#27276d; color:#fff!important; text-decoration:none; padding:13px 32px; border-radius:8px; font-weight:600; font-size:.95rem; }
    .divider { border:none; border-top:1px solid #eee; margin:28px 0; }
    .footer { background:#f8f9fa; padding:24px 40px; text-align:center; color:#888; font-size:.82rem; line-height:1.7; }
    .footer a { color:#27276d; text-decoration:none; }
  </style>
</head>
<body>
<div class="wrapper">
  <!-- Header -->
  <div class="header">
    <img src="<?= rtrim(APP_URL, '/') ?>/assets/img/RTTC_logo.jpeg" alt="RTTC Logo">
    <h2>Rangia Teacher Training College</h2>
    <p>B.Ed. Admission 2025–26 &nbsp;|&nbsp; Query Response</p>
  </div>

  <!-- Body -->
  <div class="body">
    <p class="greeting">Dear <?= htmlspecialchars($studentName) ?>,</p>
    <p class="intro">
      Thank you for reaching out to us. Our admission team has reviewed your query and we are pleased to provide a response below.
    </p>

    <div class="subject-badge"><i>Subject:</i> <?= htmlspecialchars($subjectLabel) ?></div>

    <div class="reply-box">
      <?= nl2br(htmlspecialchars($replyMessage)) ?>
    </div>

    <?php if (!empty($editAccessGranted)): ?>
    <div class="access-notice">
      <strong><span style="font-size:1.1em;">&#9989;</span> Edit Access Granted</strong>
      You have been granted temporary edit access to your registration forms. Please log in to your account and update the required details. This access may expire, so please act promptly.
    </div>
    <?php endif; ?>

    <p style="margin-bottom:24px;color:#555;">
      If you have further questions, please log in to your account and submit another query, or contact us directly.
    </p>

    <a href="<?= route('welcome') ?>" class="cta-btn">Go to My Dashboard</a>

    <hr class="divider">
    <p style="color:#888;font-size:.88rem;margin:0;">
      If you did not submit a query, please ignore this email or contact us immediately.
    </p>
  </div>

  <!-- Footer -->
  <div class="footer">
    <strong style="color:#27276d;">Rangia Teacher Training College</strong><br>
    B.Ed. Admission 2025–26 &nbsp;&bull;&nbsp;
    <a href="mailto:admissionrttc@gmail.com">admissionrttc@gmail.com</a><br>
    Phone: +91 03621-359330<br><br>
    <small>This is an automated message. Please do not reply directly to this email.</small>
  </div>
</div>
</body>
</html>
