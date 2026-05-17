<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';

SecurityHelper::requireAuth();

$db     = db();
$userId = SessionHelper::get('user_id');

// Step gate
$pstmt = $db->prepare("SELECT current_step FROM registration_progress WHERE user_id = ?");
$pstmt->bind_param('i', $userId);
$pstmt->execute();
$prog = $pstmt->get_result()->fetch_assoc();
$pstmt->close();
if (($prog['current_step'] ?? 0) < 3) {
    SessionHelper::setFlash('error', 'Please upload documents first.');
    redirect(route('documents'));
}

// Check if already paid
$stmt = $db->prepare("SELECT * FROM payment WHERE user_id = ? AND status = 'success'");
$stmt->bind_param('i', $userId);
$stmt->execute();
$paid = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($paid) {
    redirect(route('payment.confirmation'));
}

// Get user info
$stmt2 = $db->prepare("SELECT u.username, u.email, u.phone, p.firstname, p.lastname FROM users u LEFT JOIN personal_details p ON p.user_id=u.id WHERE u.id=?");
$stmt2->bind_param('i', $userId);
$stmt2->execute();
$user = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

$razorpayKey = RAZORPAY_KEY_ID;
$amount      = 50000; // in paise = Rs 500
$currency    = 'INR';
$receiptId   = 'RTTC2026_' . $userId . '_' . time();

// Create Razorpay order via API
$orderData = null;
$orderError = null;
try {
    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_USERPWD => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'amount'   => $amount,
            'currency' => $currency,
            'receipt'  => $receiptId,
            'notes'    => ['user_id' => $userId, 'email' => $user['email']],
        ]),
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $orderData = json_decode($response, true);
    if (empty($orderData['id'])) {
        $orderError = 'Could not create payment order. Please try again.';
    }
} catch (Exception $e) {
    $orderError = 'Payment gateway error. Please try again.';
}

$pageTitle = 'Payment - Step 4 - RTTC 2026';
$currentStep = 4;
ob_start();
?>

<div class="container py-4">
    <div class="row mb-3">
        <div class="col"><?php include __DIR__ . '/../views/partials/stepper.php'; ?></div>
    </div>
    <?php include __DIR__ . '/../views/partials/flash.php'; ?>

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white py-3 text-center">
                    <h4 class="mb-0"><i class="bi bi-credit-card-fill me-2"></i>Application Fee Payment</h4>
                </div>
                <div class="card-body p-4">
                    <?php if ($orderError): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i><?= $orderError ?>
                        </div>
                        <a href="<?= route('payment') ?>" class="btn btn-outline-primary">Try Again</a>
                    <?php else: ?>

                    <!-- Fee Details -->
                    <table class="table table-borderless">
                        <tr>
                            <td class="text-muted">Applicant</td>
                            <td class="fw-semibold"><?= htmlspecialchars($user['firstname'] . ' ' . ($user['lastname'] ?? $user['username'])) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Email</td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Application For</td>
                            <td>B.Ed. First Year 2025-26</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Amount</td>
                            <td><span class="badge bg-primary fs-6">₹500.00</span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Order ID</td>
                            <td class="small text-muted"><?= htmlspecialchars($orderData['id'] ?? '') ?></td>
                        </tr>
                    </table>

                    <hr>

                    <div class="alert alert-warning border-0">
                        <i class="bi bi-shield-check me-2"></i>
                        Secure payment powered by <strong>Razorpay</strong>. Your payment info is encrypted and secure.
                    </div>

                    <div class="d-grid">
                        <button id="payBtn" class="btn btn-success btn-lg">
                            <i class="bi bi-lock-fill me-2"></i>Pay ₹500 Securely
                        </button>
                    </div>

                    <p class="text-center text-muted small mt-3 mb-0">
                        By proceeding, you agree to RTTC's terms and conditions.
                    </p>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!$orderError && $orderData): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
const options = {
    key: '<?= RAZORPAY_KEY_ID ?>',
    amount: <?= $amount ?>,
    currency: '<?= $currency ?>',
    name: 'RTTC Admission 2026',
    description: 'B.Ed. First Year Application Fee',
    image: '<?= BASE_URL ?>/assets/img/RTTC_logo.jpeg',
    order_id: '<?= $orderData['id'] ?>',
    handler: function(response) {
        // Send to verification endpoint
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= BASE_URL ?>/api/payment-process.php';
        const fields = {
            razorpay_payment_id: response.razorpay_payment_id,
            razorpay_order_id:   response.razorpay_order_id,
            razorpay_signature:  response.razorpay_signature,
            csrf_token:          '<?= SecurityHelper::generateCsrf() ?>',
        };
        for (const [k, v] of Object.entries(fields)) {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = k; inp.value = v;
            form.appendChild(inp);
        }
        document.body.appendChild(form);
        form.submit();
    },
    prefill: {
        name:  '<?= htmlspecialchars($user['username']) ?>',
        email: '<?= htmlspecialchars($user['email']) ?>',
        contact: '<?= htmlspecialchars($user['phone']) ?>',
    },
    theme: { color: '#27276d' },
    modal: {
        ondismiss: function() {
            document.getElementById('payBtn').disabled = false;
            document.getElementById('payBtn').innerHTML = '<i class="bi bi-lock-fill me-2"></i>Pay ₹500 Securely';
        }
    }
};
const rzp = new Razorpay(options);
document.getElementById('payBtn').addEventListener('click', function() {
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Opening payment gateway...';
    rzp.open();
});
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/main.php';
