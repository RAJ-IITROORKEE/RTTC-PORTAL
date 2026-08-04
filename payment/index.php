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
$amount      = RAZORPAY_AMOUNT;
$currency    = 'INR';
$displayAmount = number_format($amount / 100, 2, '.', '');
$isTestMode = str_starts_with($razorpayKey, 'rzp_test_');

// Reuse a recent pending order so refreshing the page cannot create duplicate charges.
$pendingStmt = $db->prepare("SELECT id, razorpay_order_id, amount, currency
    FROM payment
    WHERE user_id = ? AND status = 'pending' AND amount = ? AND currency = ?
      AND created_at >= (NOW() - INTERVAL 30 MINUTE)
    ORDER BY id DESC LIMIT 1");
if ($pendingStmt) {
    $pendingStmt->bind_param('iis', $userId, $amount, $currency);
    $pendingStmt->execute();
    $pending = $pendingStmt->get_result()->fetch_assoc();
    $pendingStmt->close();
} else {
    $pending = null;
}

$orderData = null;
if ($pending && $razorpayKey !== '' && RAZORPAY_KEY_SECRET !== '') {
    // A pending row may belong to an old Razorpay account/key after credentials change.
    $remoteOrder = PaymentHelper::fetchOrder(
        (string) $pending['razorpay_order_id'],
        $razorpayKey,
        RAZORPAY_KEY_SECRET
    );
    if (
        $remoteOrder
        && ($remoteOrder['id'] ?? '') === $pending['razorpay_order_id']
        && (int)($remoteOrder['amount'] ?? 0) === $amount
        && ($remoteOrder['currency'] ?? '') === $currency
        && in_array(($remoteOrder['status'] ?? ''), ['created', 'attempted'], true)
    ) {
        $orderData = [
            'id' => $pending['razorpay_order_id'],
            'amount' => (int) $pending['amount'],
            'currency' => $pending['currency'],
        ];
    } else {
        // Do not send an order that the current credentials cannot access to Checkout.
        $staleStmt = $db->prepare("UPDATE payment SET status = 'failed'
            WHERE id = ? AND status = 'pending'");
        if ($staleStmt) {
            $pendingId = (int) $pending['id'];
            $staleStmt->bind_param('i', $pendingId);
            $staleStmt->execute();
            $staleStmt->close();
        }
        error_log('Discarded stale or inaccessible Razorpay pending order: ' . $pending['razorpay_order_id']);
    }
}

// Create Razorpay order via API
$orderError = null;
if ($orderData === null && ($razorpayKey === '' || RAZORPAY_KEY_SECRET === '' || $amount <= 0)) {
    $orderError = 'Payment is temporarily unavailable. Please contact support.';
} elseif ($orderData === null) {
    $receiptId = 'RTTC2026_' . $userId . '_' . bin2hex(random_bytes(6));
    $requestBody = json_encode([
        'amount'   => $amount,
        'currency' => $currency,
        'receipt'  => $receiptId,
        'notes'    => ['user_id' => $userId, 'email' => $user['email']],
    ]);

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    if ($ch === false || $requestBody === false) {
        $orderError = 'Payment gateway error. Please try again.';
    } else {
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => $razorpayKey . ':' . RAZORPAY_KEY_SECRET,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $orderData = is_string($response) ? json_decode($response, true) : null;
        if ($curlError !== '' || $httpCode < 200 || $httpCode >= 300 || !is_array($orderData) || empty($orderData['id'])) {
            error_log('Razorpay order creation failed. HTTP status: ' . $httpCode . ($curlError !== '' ? ', cURL: ' . $curlError : ''));
            $orderData = null;
            $orderError = 'Could not create payment order. Please try again.';
        } elseif ((int)($orderData['amount'] ?? 0) !== $amount || ($orderData['currency'] ?? '') !== $currency) {
            error_log('Razorpay order amount or currency did not match the requested payment.');
            $orderData = null;
            $orderError = 'Payment order validation failed. Please try again.';
        } else {
            $createdOrderId = (string) $orderData['id'];
            $stmt = $db->prepare("INSERT INTO payment (user_id, razorpay_order_id, amount, currency, status)
                VALUES (?, ?, ?, ?, 'pending')");
            if (!$stmt) {
                $orderData = null;
                $orderError = 'Could not prepare payment order. Please try again.';
            } else {
                $stmt->bind_param('isis', $userId, $createdOrderId, $amount, $currency);
                if (!$stmt->execute()) {
                    error_log('Could not persist Razorpay order: ' . $stmt->error);
                    $orderData = null;
                    $orderError = 'Could not save payment order. Please try again.';
                }
                $stmt->close();
            }
        }
    }
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
                            <td class="text-muted">Application For</td>
                            <td>B.Ed admission 2026-27</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Amount</td>
                            <td><span class="badge bg-primary fs-6">₹<?= $displayAmount ?></span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Order ID</td>
                            <td class="small text-muted"><?= htmlspecialchars((string)($orderData['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    </table>

                    <hr>

                    <?php if ($isTestMode): ?>
                    <div class="alert alert-info border-0" role="alert">
                        <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-2"></i>Razorpay Test Mode</div>
                        <div class="small">
                            No real money is transferred. Do not scan the QR with a real UPI app.
                            Select UPI and enter <code>success@razorpay</code> for success or
                            <code>failure@razorpay</code> for failure.
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="alert alert-warning border-0">
                        <i class="bi bi-shield-check me-2"></i>
                        Secure payment powered by <strong>Razorpay</strong>. Your payment info is encrypted and secure.
                    </div>

                    <div class="d-grid">
                        <button id="payBtn" class="btn btn-success btn-lg">
                            <i class="bi bi-lock-fill me-2"></i>Pay ₹<?= $displayAmount ?> Securely
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
    key: <?= json_encode(RAZORPAY_KEY_ID, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    amount: <?= $amount ?>,
    currency: <?= json_encode($currency) ?>,
    name: 'RTTC Admission 2026',
    description: 'B.Ed admission 2026-27 application fee',
    image: <?= json_encode(BASE_URL . '/assets/img/RTTC_logo.jpeg', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    order_id: <?= json_encode((string)$orderData['id'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    handler: function(response) {
        // Send to verification endpoint
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = <?= json_encode(BASE_URL . '/api/payment-process.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const fields = {
            razorpay_payment_id: response.razorpay_payment_id,
            razorpay_order_id:   response.razorpay_order_id,
            razorpay_signature:  response.razorpay_signature,
            csrf_token:          <?= json_encode(SecurityHelper::generateCsrf(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        };
        for (const [k, v] of Object.entries(fields)) {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = k; inp.value = v;
            form.appendChild(inp);
        }
        document.body.appendChild(form);
        form.submit();
    },
    prefill: <?= json_encode([
        'name' => (string)($user['username'] ?? ''),
        'email' => (string)($user['email'] ?? ''),
        'contact' => (string)($user['phone'] ?? ''),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    theme: { color: '#27276d' },
    modal: {
        ondismiss: function() {
            document.getElementById('payBtn').disabled = false;
            document.getElementById('payBtn').innerHTML = '<i class="bi bi-lock-fill me-2"></i>Pay ₹<?= $displayAmount ?> Securely';
        }
    }
};
const rzp = new Razorpay(options);
rzp.on('payment.failed', function(response) {
    const error = response.error || {};
    const description = error.description || 'The payment attempt failed. Please try again.';
    const button = document.getElementById('payBtn');
    button.disabled = false;
    button.innerHTML = '<i class="bi bi-lock-fill me-2"></i>Pay ₹<?= $displayAmount ?> Securely';
    window.alert(description);
});
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
