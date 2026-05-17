<?php
define('APP_INIT', true);
require_once __DIR__ . '/config/init.php';

SecurityHelper::requireAuth();

// Get user progress from DB
$db = db();
$userId = SessionHelper::get('user_id');

$stmt = $db->prepare("SELECT current_step, is_submitted FROM registration_progress WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$prog = $stmt->get_result()->fetch_assoc();
$stmt->close();

$currentStep  = $prog['current_step'] ?? 0;
$isSubmitted  = $prog['is_submitted'] ?? 0;

// Check if admin has granted active edit access to this user
$editAccess = false;
$now = date('Y-m-d H:i:s');
$stmtEA = $db->prepare(
    "SELECT id FROM user_edit_access WHERE user_id = ? AND is_active = 1 AND expires_at > ? LIMIT 1"
);
$stmtEA->bind_param('is', $userId, $now);
$stmtEA->execute();
$stmtEA->store_result();
$editAccess = $stmtEA->num_rows > 0;
$stmtEA->close();

// Get user info
$stmt2 = $db->prepare("SELECT username, email, phone, created_at FROM users WHERE id = ?");
$stmt2->bind_param('i', $userId);
$stmt2->execute();
$user = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

$pageTitle = 'My Dashboard - RTTC 2026';
ob_start();
?>

<div class="container py-4">
    <!-- Welcome header -->
    <div class="row mb-4">
        <div class="col">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #27276d, #4a4ab0);">
                <div class="card-body text-white py-4 px-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="mb-1">Welcome, <?= htmlspecialchars($user['username']) ?>!</h3>
                            <p class="mb-0 opacity-75">
                                <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($user['email']) ?>
                                &nbsp;|&nbsp;
                                <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($user['phone']) ?>
                            </p>
                            <p class="mb-0 opacity-75 small mt-1">
                                Registered on <?= date('d M Y', strtotime($user['created_at'])) ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <?php if ($isSubmitted): ?>
                                <span class="badge bg-success fs-6 px-3 py-2">
                                    <i class="bi bi-check-circle me-1"></i>Application Submitted
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark fs-6 px-3 py-2">
                                    <i class="bi bi-clock me-1"></i>Application In Progress
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stepper -->
    <div class="row mb-4">
        <div class="col">
            <?php include __DIR__ . '/views/partials/stepper.php'; ?>
        </div>
    </div>

    <?php include __DIR__ . '/views/partials/flash.php'; ?>

    <!-- Step Action Cards -->
    <div class="row g-3">
        <!-- Personal Details -->
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm step-card <?= $currentStep >= 1 ? 'step-done' : ($currentStep === 0 ? 'step-active' : '') ?>">
                <div class="card-body text-center py-4">
                    <div class="step-icon-circle mb-3 mx-auto <?= $currentStep >= 1 ? 'bg-success' : 'bg-primary' ?>">
                        <?php if ($currentStep >= 1): ?>
                            <i class="bi bi-check-lg text-white fs-4"></i>
                        <?php else: ?>
                            <span class="text-white fw-bold fs-5">1</span>
                        <?php endif; ?>
                    </div>
                    <h6 class="fw-bold">Personal Details</h6>
                    <p class="text-muted small mb-3">Name, DOB, family info, address</p>
                    <?php if ($currentStep >= 1): ?>
                        <?php if ($editAccess): ?>
                        <a href="<?= route('registration') ?>" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-pencil me-1"></i>Edit
                        </a>
                        <?php else: ?>
                        <a href="<?= route('registration') ?>?view=1" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye me-1"></i>View
                        </a>
                        <?php endif; ?>
                    <?php elseif ($currentStep === 0): ?>
                        <a href="<?= route('registration') ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-arrow-right-circle me-1"></i>Start
                        </a>
                    <?php else: ?>
                        <button class="btn btn-sm btn-secondary" disabled>Locked</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Academic Details -->
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm step-card <?= $currentStep >= 2 ? 'step-done' : ($currentStep === 1 ? 'step-active' : '') ?>">
                <div class="card-body text-center py-4">
                    <div class="step-icon-circle mb-3 mx-auto <?= $currentStep >= 2 ? 'bg-success' : ($currentStep === 1 ? 'bg-primary' : 'bg-secondary') ?>">
                        <?php if ($currentStep >= 2): ?>
                            <i class="bi bi-check-lg text-white fs-4"></i>
                        <?php else: ?>
                            <span class="text-white fw-bold fs-5">2</span>
                        <?php endif; ?>
                    </div>
                    <h6 class="fw-bold">Academic Details</h6>
                    <p class="text-muted small mb-3">HSLC, HSSLC, Degree, GUBEDCET</p>
                    <?php if ($currentStep >= 2): ?>
                        <?php if ($editAccess): ?>
                        <a href="<?= route('academics') ?>" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-pencil me-1"></i>Edit
                        </a>
                        <?php else: ?>
                        <a href="<?= route('academics') ?>?view=1" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye me-1"></i>View
                        </a>
                        <?php endif; ?>
                    <?php elseif ($currentStep === 1): ?>
                        <a href="<?= route('academics') ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-arrow-right-circle me-1"></i>Fill Now
                        </a>
                    <?php else: ?>
                        <button class="btn btn-sm btn-secondary" disabled>Locked</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Documents -->
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm step-card <?= $currentStep >= 3 ? 'step-done' : ($currentStep === 2 ? 'step-active' : '') ?>">
                <div class="card-body text-center py-4">
                    <div class="step-icon-circle mb-3 mx-auto <?= $currentStep >= 3 ? 'bg-success' : ($currentStep === 2 ? 'bg-primary' : 'bg-secondary') ?>">
                        <?php if ($currentStep >= 3): ?>
                            <i class="bi bi-check-lg text-white fs-4"></i>
                        <?php else: ?>
                            <span class="text-white fw-bold fs-5">3</span>
                        <?php endif; ?>
                    </div>
                    <h6 class="fw-bold">Upload Documents</h6>
                    <p class="text-muted small mb-3">Photo, signature, certificates</p>
                    <?php if ($currentStep >= 3): ?>
                        <?php if ($editAccess): ?>
                        <a href="<?= route('documents') ?>" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-pencil me-1"></i>Edit
                        </a>
                        <?php else: ?>
                        <a href="<?= route('documents') ?>?view=1" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye me-1"></i>View
                        </a>
                        <?php endif; ?>
                    <?php elseif ($currentStep === 2): ?>
                        <a href="<?= route('documents') ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-arrow-right-circle me-1"></i>Upload
                        </a>
                    <?php else: ?>
                        <button class="btn btn-sm btn-secondary" disabled>Locked</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Payment -->
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm step-card <?= $currentStep >= 4 ? 'step-done' : ($currentStep === 3 ? 'step-active' : '') ?>">
                <div class="card-body text-center py-4">
                    <div class="step-icon-circle mb-3 mx-auto <?= $currentStep >= 4 ? 'bg-success' : ($currentStep === 3 ? 'bg-primary' : 'bg-secondary') ?>">
                        <?php if ($currentStep >= 4): ?>
                            <i class="bi bi-check-lg text-white fs-4"></i>
                        <?php else: ?>
                            <span class="text-white fw-bold fs-5">4</span>
                        <?php endif; ?>
                    </div>
                    <h6 class="fw-bold">Payment</h6>
                    <p class="text-muted small mb-3">Application fee ₹500</p>
                    <?php if ($currentStep >= 4): ?>
                        <a href="<?= route('payment.confirmation') ?>" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-receipt me-1"></i>View Receipt
                        </a>
                    <?php elseif ($currentStep === 3): ?>
                        <a href="<?= route('payment') ?>" class="btn btn-sm btn-success">
                            <i class="bi bi-credit-card me-1"></i>Pay Now
                        </a>
                    <?php else: ?>
                        <button class="btn btn-sm btn-secondary" disabled>Locked</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($currentStep === 4 && !$isSubmitted): ?><?php endif; ?>

    <?php if ($isSubmitted): ?>
    <!-- Application submitted info -->
    <div class="row mt-4">
        <div class="col">
            <div class="card border-0 shadow-sm" style="border-left:4px solid #198754 !important;">
                <div class="card-body p-4">
                    <h5 class="text-success mb-3"><i class="bi bi-patch-check-fill me-2"></i>Application Successfully Submitted</h5>
                    <p class="text-muted mb-2">Your application for B.Ed. First Year 2025-26 admission has been successfully submitted. Keep the following information for reference:</p>
                    <ul class="list-unstyled text-muted mb-4">
                        <li><i class="bi bi-dot me-1"></i>You will receive a confirmation email at <strong><?= htmlspecialchars($user['email']) ?></strong></li>
                        <li><i class="bi bi-dot me-1"></i>Keep your application number handy for future correspondence</li>
                        <li><i class="bi bi-dot me-1"></i>Merit list will be published as per GUBEDCET 2026 guidelines</li>
                    </ul>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= route('payment.confirmation') ?>#receiptSection" class="btn btn-outline-primary">
                            <i class="bi bi-file-earmark-pdf me-1"></i>Download Payment Receipt
                        </a>
                        <a href="<?= route('payment.confirmation') ?>#applicationSection" class="btn btn-primary">
                            <i class="bi bi-file-earmark-arrow-down me-1"></i>Download Application Form
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($currentStep >= 4 && !$isSubmitted): ?>
    <!-- Payment done but not marked submitted yet — show downloads anyway -->
    <div class="row mt-4">
        <div class="col">
            <div class="card border-0 shadow-sm" style="border-left:4px solid #27276d !important;">
                <div class="card-body p-4">
                    <h5 class="mb-3" style="color:#27276d;"><i class="bi bi-download me-2"></i>Download Your Documents</h5>
                    <p class="text-muted mb-3">Your payment is complete. You can now download your payment receipt and application form.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= route('payment.confirmation') ?>#receiptSection" class="btn btn-outline-primary">
                            <i class="bi bi-file-earmark-pdf me-1"></i>Download Payment Receipt
                        </a>
                        <a href="<?= route('payment.confirmation') ?>#applicationSection" class="btn btn-primary">
                            <i class="bi bi-file-earmark-arrow-down me-1"></i>Download Application Form
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.step-icon-circle {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.step-card.step-done { border-left: 4px solid #198754 !important; }
.step-card.step-active { border-left: 4px solid #27276d !important; }
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/views/layouts/main.php';
