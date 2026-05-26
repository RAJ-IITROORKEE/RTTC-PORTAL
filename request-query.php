<?php
/**
 * RTTC 2026 - Raise a Query / Request Edit Access
 */
require_once __DIR__ . '/config/init.php';

$isLoggedIn = SessionHelper::isLoggedIn();
$prefillName  = '';
$prefillEmail = '';
$prefillPhone = '';

if ($isLoggedIn) {
    $userId = SessionHelper::get('user_id');

    // Get email, username, and phone from users table
    $stmt = $conn->prepare("SELECT username, email, phone FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $uRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($uRow) {
        $prefillEmail = $uRow['email'];
        $prefillName  = $uRow['username'];
        $prefillPhone = $uRow['phone'] ?? '';
    }

    // Override name/phone with personal_details if filled
    $stmt = $conn->prepare("SELECT firstname, middlename, lastname, emergency_contact FROM personal_details WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $pRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($pRow) {
        $prefillName  = trim($pRow['firstname'] . ' ' . $pRow['middlename'] . ' ' . $pRow['lastname']);
        $prefillPhone = $pRow['emergency_contact'] ?? $prefillPhone;
    }
}

// Release session lock so AJAX requests on this page don't block waiting for it
session_write_close();

$activeNav = '';
$pageTitle  = 'Raise a Query';

ob_start();
?>

<section class="py-5" style="min-height:80vh; background:#f8f9fa;">
  <div class="container" style="max-width:680px;">

    <!-- Header -->
    <div class="text-center mb-4">
      <h2 class="fw-bold mb-1" style="color:var(--rttc-primary, #27276d);">
        <i class="bi bi-chat-dots me-2"></i>Raise a Query
      </h2>
      <p class="text-muted mb-0">Have an issue with your registration? Submit your query below and we will get back to you.</p>
    </div>

    <!-- Info Notice -->
    <div class="alert alert-info border-0 d-flex gap-3 align-items-start mb-4" style="border-radius:12px; background:#e8f4fd;">
      <i class="bi bi-info-circle-fill fs-5 mt-1 text-info flex-shrink-0"></i>
      <div>
        <strong>How it works:</strong> Once you submit a query, our admin team will review it. If edit access to your registration is needed, it will be granted after review. You will receive a reply on your email.
      </div>
    </div>

    <!-- Query Form Card -->
    <div class="card border-0 shadow-sm" style="border-radius:16px;">
      <div class="card-body p-4 p-md-5">

        <!-- ✅ Success Panel (hidden until submission) -->
        <div id="successPanel" class="text-center py-3" style="display:none !important;">
          <div class="mb-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10"
                  style="width:72px;height:72px;">
              <i class="bi bi-check-circle-fill fs-2 text-success"></i>
            </span>
          </div>
          <h5 class="fw-bold mb-2" style="color:#1a7a4a;">Query Submitted Successfully!</h5>
          <p class="text-muted mb-4">
            Our admin team will review your query and get back to you on your email shortly.
          </p>
          <button type="button" id="resubmitBtn" class="btn btn-outline-primary px-4">
            <i class="bi bi-plus-circle me-2"></i>Submit Another Query
          </button>
        </div>

        <!-- 📝 Form (shown by default) -->
        <div id="formWrap">
          <form id="queryForm" method="POST" novalidate>

            <div class="mb-3">
              <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="name" id="qName"
                     value="<?= htmlspecialchars($prefillName) ?>"
                     placeholder="Enter your full name" required maxlength="120">
              <div class="invalid-feedback">Please enter your full name.</div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
              <input type="email" class="form-control" name="email" id="qEmail"
                     value="<?= htmlspecialchars($prefillEmail) ?>"
                     placeholder="Enter your email" required maxlength="180">
              <div class="invalid-feedback">Please enter a valid email address.</div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Phone Number</label>
              <input type="tel" class="form-control" name="phone" id="qPhone"
                     value="<?= htmlspecialchars($prefillPhone) ?>"
                     placeholder="10-digit mobile number" maxlength="15"
                     pattern="[0-9]{10}">
              <div class="invalid-feedback">Enter a valid 10-digit phone number.</div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Subject / Issue <span class="text-danger">*</span></label>
              <select class="form-select" name="issue_subject" id="qSubject" required>
                <option value="" disabled selected>Select the type of issue</option>
                <option value="Edit Personal Details">Edit Personal Details</option>
                <option value="Edit Academic Details">Edit Academic Details</option>
                <option value="Edit Documents">Edit / Re-upload Documents</option>
                <option value="Payment Issue">Payment Issue</option>
                <option value="Login / Account Issue">Login / Account Issue</option>
                <option value="Other">Other</option>
              </select>
              <div class="invalid-feedback">Please select a subject.</div>
            </div>

            <div class="mb-4">
              <label class="form-label fw-semibold">Message <span class="text-danger">*</span></label>
              <textarea class="form-control" name="message" id="qMessage" rows="5"
                        placeholder="Describe your issue in detail..." required minlength="20" maxlength="2000"></textarea>
              <div class="form-text text-end"><span id="msgCount">0</span>/2000</div>
              <div class="invalid-feedback">Please describe your issue (at least 20 characters).</div>
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-primary btn-lg fw-semibold" id="submitBtn" disabled>
                <span class="btn-text"><i class="bi bi-send me-2"></i>Submit Query</span>
                <span class="spinner-border spinner-border-sm d-none" role="status"></span>
              </button>
              <small class="text-muted mt-2" id="validationMsg">Fill all required fields correctly to enable submission</small>
            </div>

          </form>
        </div><!-- /formWrap -->

      </div>
    </div>

    <!-- Back link -->
    <div class="text-center mt-4">
      <?php if ($isLoggedIn): ?>
        <a href="<?= route('welcome') ?>" class="text-muted text-decoration-none">
          <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
        </a>
      <?php else: ?>
        <a href="<?= route('home') ?>" class="text-muted text-decoration-none">
          <i class="bi bi-arrow-left me-1"></i>Back to Home
        </a>
      <?php endif; ?>
    </div>

  </div>
</section>

<!-- Error Toast (kept for network/validation errors) -->
<div class="position-fixed top-0 end-0 p-3" style="z-index:9999;">
  <div id="errorToast" class="toast align-items-center text-bg-danger border-0 shadow" role="alert">
    <div class="d-flex">
      <div class="toast-body fw-semibold" id="errorToastMsg">
        Something went wrong. Please try again.
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<?php
$content  = ob_get_clean();
$submitQueryUrl = route('api.submit-query');
$extraFoot = <<<JS
<script>
(function () {
  const formWrap     = document.getElementById('formWrap');
  const successPanel = document.getElementById('successPanel');
  const form         = document.getElementById('queryForm');
  const submitBtn    = document.getElementById('submitBtn');
  const btnText      = submitBtn.querySelector('.btn-text');
  const spinner      = submitBtn.querySelector('.spinner-border');
  const msgArea      = document.getElementById('qMessage');
  const msgCount     = document.getElementById('msgCount');
  const resubmitBtn  = document.getElementById('resubmitBtn');
  const validationMsg = document.getElementById('validationMsg');

  let isSubmitting = false;

  const requiredFields = [
    { id: 'qName', type: 'text', minLen: 1 },
    { id: 'qEmail', type: 'email', minLen: 1 },
    { id: 'qSubject', type: 'select', minLen: 1 },
    { id: 'qMessage', type: 'textarea', minLen: 20 }
  ];

  // Character counter
  msgArea.addEventListener('input', function () {
    msgCount.textContent = msgArea.value.length;
    validateForm();
  });

  // Real-time validation on all required fields
  requiredFields.forEach(function (field) {
    const el = document.getElementById(field.id);
    if (el) {
      el.addEventListener('input', validateForm);
      el.addEventListener('change', validateForm);
      el.addEventListener('blur', validateForm);
    }
  });

  function validateField(field) {
    const el = document.getElementById(field.id);
    if (!el) return false;

    let value = el.value.trim();

    if (field.type === 'email') {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(value)) {
        el.classList.add('is-invalid');
        el.classList.remove('is-valid');
        return false;
      }
    } else if (field.type === 'select') {
      if (value === '' || value === null) {
        el.classList.add('is-invalid');
        el.classList.remove('is-valid');
        return false;
      }
    } else if (field.minLen > 0) {
      if (value.length < field.minLen) {
        el.classList.add('is-invalid');
        el.classList.remove('is-valid');
        return false;
      }
    }

    el.classList.remove('is-invalid');
    el.classList.add('is-valid');
    return true;
  }

  function validateForm() {
    let allValid = true;

    requiredFields.forEach(function (field) {
      const isValid = validateField(field);
      if (!isValid) allValid = false;
    });

    // Optional: validate phone if filled
    const phoneEl = document.getElementById('qPhone');
    if (phoneEl && phoneEl.value.trim() !== '') {
      const phoneRegex = /^[0-9]{10}$/;
      if (!phoneRegex.test(phoneEl.value.trim())) {
        phoneEl.classList.add('is-invalid');
        phoneEl.classList.remove('is-valid');
        allValid = false;
      } else {
        phoneEl.classList.remove('is-invalid');
        phoneEl.classList.add('is-valid');
      }
    } else if (phoneEl) {
      phoneEl.classList.remove('is-invalid', 'is-valid');
    }

    if (allValid) {
      submitBtn.disabled = false;
      validationMsg.textContent = '✓ Form ready to submit';
      validationMsg.classList.add('text-success');
      validationMsg.classList.remove('text-muted');
    } else {
      submitBtn.disabled = true;
      validationMsg.textContent = 'Fill all required fields correctly to enable submission';
      validationMsg.classList.remove('text-success');
      validationMsg.classList.add('text-muted');
    }

    return allValid;
  }

  // "Submit Another Query" link
  resubmitBtn.addEventListener('click', function () {
    successPanel.style.setProperty('display', 'none', 'important');
    formWrap.style.display = '';
    form.reset();
    msgCount.textContent = '0';
    requiredFields.forEach(function (field) {
      const el = document.getElementById(field.id);
      if (el) {
        el.classList.remove('is-valid', 'is-invalid');
      }
    });
    validateForm();
  });

  function unlockBtn() {
    isSubmitting = false;
    btnText.classList.remove('d-none');
    spinner.classList.add('d-none');
    submitBtn.disabled = false;
  }

  function showError(msg) {
    document.getElementById('errorToastMsg').textContent = msg;
    new bootstrap.Toast(document.getElementById('errorToast'), {delay: 6000}).show();
  }

  // Form submit
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (isSubmitting) return;

    if (!validateForm()) {
      return;
    }

    isSubmitting = true;
    btnText.classList.add('d-none');
    spinner.classList.remove('d-none');
    submitBtn.disabled = true;
    validationMsg.textContent = 'Submitting...';

    var controller = new AbortController();
    var timeoutId  = setTimeout(function () { controller.abort(); }, 30000);

    fetch('{$submitQueryUrl}', {
      method: 'POST',
      body: new FormData(form),
      signal: controller.signal
    })
      .then(function (r) {
        clearTimeout(timeoutId);
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (res) {
        if (res.success) {
          formWrap.style.display = 'none';
          successPanel.style.removeProperty('display');
        } else {
          showError(res.message || 'Something went wrong. Please try again.');
          unlockBtn();
          validateForm();
        }
      })
      .catch(function (err) {
        clearTimeout(timeoutId);
        if (err.name === 'AbortError') {
          showError('Request timed out. Please check your connection and try again.');
        } else {
          showError('Network error. Please check your connection and try again.');
        }
        unlockBtn();
        validateForm();
      });
  });

  // Initial validation on page load (for pre-filled fields)
  document.addEventListener('DOMContentLoaded', validateForm);
})();
</script>
JS;

include __DIR__ . '/views/layouts/main.php';
