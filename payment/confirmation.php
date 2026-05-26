<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';

SecurityHelper::requireAuth();

$db     = db();
$userId = SessionHelper::get('user_id');

// Fetch all data for the confirmation page
$stmt = $db->prepare(
    "SELECT u.username, u.email, u.phone,
            p.firstname, p.middlename, p.lastname, p.fathersname, p.mothersname,
            p.dob, p.age, p.gender, p.blood_group, p.religion, p.caste,
            p.ews, p.obc_ncl, p.pwd, p.permanent_address, p.present_address,
            p.emergency_contact, p.income,
            a.hslc_pass_year, a.hslc_board, a.hslc_institute, a.hslc_obtained_marks,
            a.hslc_total_marks, a.hslc_percentage, a.hslc_division,
            a.hsslc_pass_year, a.hsslc_board, a.hsslc_institute, a.hsslc_obtained_marks,
            a.hsslc_total_marks, a.hsslc_percentage, a.hsslc_division,
            a.bachelor_pass_year, a.bachelor_board, a.bachelor_institute, a.bachelor_obtained_marks,
            a.bachelor_total_marks, a.bachelor_percentage, a.bachelor_division,
            a.masters_pass_year, a.masters_board, a.masters_institute, a.masters_obtained_marks,
            a.masters_total_marks, a.masters_percentage, a.masters_division,
            a.gubedcet_rollno, a.gubedcet_marks, a.gubedcet_rank, a.gubedcet_name,
            a.gubedcet_category, a.gu_registered, a.gu_reg_no, a.gu_reg_year,
            a.migrated, a.other_university,
            d.photo, d.signature,
            d.hslc_marksheet, d.hsslc_marksheet, d.degree_marksheet, d.masters_marksheet,
            d.gubedcet_admit_card, d.gubedcet_result_sheet,
            d.caste_cert, d.ews_cert, d.pwd_cert, d.obc_ncl_cert,
            pay.razorpay_payment_id, pay.razorpay_order_id, pay.amount, pay.currency,
            pay.created_at AS payment_date,
            rp.current_step, rp.is_submitted
     FROM users u
     LEFT JOIN personal_details p  ON p.user_id  = u.id
     LEFT JOIN academic_details a  ON a.user_id  = u.id
     LEFT JOIN documents d         ON d.user_id  = u.id
     LEFT JOIN payment pay         ON pay.user_id = u.id AND pay.status = 'success'
     LEFT JOIN registration_progress rp ON rp.user_id = u.id
     WHERE u.id = ?"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data || ($data['current_step'] ?? 0) < 4) {
    SessionHelper::setFlash('error', 'Please complete payment first.');
    redirect(route('payment'));
}

// Helpers
$fullName  = trim(($data['firstname'] ?? '') . ' ' . ($data['middlename'] ?? '') . ' ' . ($data['lastname'] ?? ''));
$appNumber = 'RTTC2026-' . str_pad($userId, 5, '0', STR_PAD_LEFT);
$paidAmt   = '₹' . number_format(($data['amount'] ?? 50000) / 100, 2);
$paidAt    = $data['payment_date'] ? date('d M Y, h:i A', strtotime($data['payment_date'])) : 'N/A';

$pageTitle   = 'Application Confirmation - RTTC 2026';
$currentStep = 4;
$extraHead   = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
';
ob_start();
?>

<div class="container py-4">
    <div class="row mb-3">
        <div class="col"><?php include __DIR__ . '/../views/partials/stepper.php'; ?></div>
    </div>
    <?php include __DIR__ . '/../views/partials/flash.php'; ?>

    <!-- ── Success Banner ──────────────────────────────────────────── -->
    <div class="d-flex align-items-center gap-3 bg-success text-white rounded-3 p-4 mb-4 shadow-sm">
        <i class="bi bi-patch-check-fill" style="font-size:3rem;flex-shrink:0;"></i>
        <div>
            <h4 class="mb-1 fw-bold">Application Successfully Submitted!</h4>
            <p class="mb-0 opacity-90 small">
                Your B.Ed. Admission application for 2025-26 has been submitted.
                Application No: <strong><?= $appNumber ?></strong>
            </p>
        </div>
    </div>

    <!-- ── Download Action Bar ─────────────────────────────────────── -->
    <div class="d-flex flex-wrap gap-2 justify-content-end mb-4">
        <a href="<?= route('welcome') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-house me-1"></i>My Dashboard
        </a>
        <button type="button" class="btn btn-outline-primary" id="btnDownloadReceipt" onclick="downloadReceipt()">
            <i class="bi bi-file-earmark-pdf me-1"></i>Download Payment Receipt
        </button>
        <button type="button" class="btn btn-primary" id="btnDownloadApplication" onclick="downloadApplication()">
            <i class="bi bi-file-earmark-arrow-down me-1"></i>Download Application Form
        </button>
    </div>

    <!-- ════════════════════════════════════════════════════════════════
         SECTION 1 — PAYMENT RECEIPT
         Using component from /payment/components/payment-receipt.php
    ════════════════════════════════════════════════════════════════ -->
    <?php include __DIR__ . '/components/payment-receipt.php'; ?>

    <!-- ════════════════════════════════════════════════════════════════
         SECTION 2 — APPLICATION FORM
         Using component from /payment/components/application-form.php
    ════════════════════════════════════════════════════════════════ -->
    <?php include __DIR__ . '/components/application-form.php'; ?>

    <!-- Repeat download buttons at bottom -->
    <div class="d-flex flex-wrap gap-2 justify-content-end mb-4">
        <a href="<?= route('welcome') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-house me-1"></i>My Dashboard
        </a>
        <button type="button" class="btn btn-outline-primary" id="btnDownloadReceipt2" onclick="downloadReceipt()">
            <i class="bi bi-file-earmark-pdf me-1"></i>Download Payment Receipt
        </button>
        <button type="button" class="btn btn-primary" id="btnDownloadApplication2" onclick="downloadApplication()">
            <i class="bi bi-file-earmark-arrow-down me-1"></i>Download Application Form
        </button>
    </div>
</div>

<style>
@media print {
    nav, .btn, .marquee-container, footer, .stepper-wrapper { display: none !important; }
}
</style>

<script>
(function () {

    function setLoading(ids, text) {
        ids.forEach(function (id) {
            var b = document.getElementById(id);
            if (!b) return;
            b.disabled = true;
            b.dataset.orig = b.innerHTML;
            b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + text;
        });
    }
    function resetBtns(ids) {
        ids.forEach(function (id) {
            var b = document.getElementById(id);
            if (!b) return;
            b.disabled = false;
            b.innerHTML = b.dataset.orig;
        });
    }

    /*
     * Both sections are capped at max-width:794px and use flex/table
     * layouts with no Bootstrap responsive breakpoints. Whatever you
     * see in the preview is exactly what html2canvas captures — no
     * viewport tricks needed.
     */
    async function captureAndSave(elementId, filename, marginMm) {
        var el = document.getElementById(elementId);
        if (!el) throw new Error('Element not found: ' + elementId);

        var canvas = await html2canvas(el, {
            scale:           2,
            useCORS:         true,
            allowTaint:      true,
            logging:         false,
            backgroundColor: '#ffffff'
        });

        var imgData  = canvas.toDataURL('image/jpeg', 0.95);
        var m        = marginMm || 6;
        var pdfW     = 210;
        var contentW = pdfW - 2 * m;
        var contentH = (canvas.height / canvas.width) * contentW;
        var pdfH     = contentH + 2 * m;

        var { jsPDF } = window.jspdf;
        var pdf = new jsPDF({ unit: 'mm', format: [pdfW, pdfH], orientation: 'portrait' });
        pdf.addImage(imgData, 'JPEG', m, m, contentW, contentH);
        pdf.save(filename);
    }

    var receiptBtns     = ['btnDownloadReceipt',     'btnDownloadReceipt2'];
    var applicationBtns = ['btnDownloadApplication', 'btnDownloadApplication2'];

    window.downloadReceipt = async function () {
        if (!window.html2canvas || !window.jspdf) {
            alert('PDF library is still loading — please wait a moment and try again.');
            return;
        }
        setLoading(receiptBtns, 'Generating…');
        try {
            await captureAndSave('receiptSection', 'RTTC2026_Payment_Receipt_<?= $appNumber ?>.pdf', 8);
        } catch (err) {
            console.error('PDF error:', err);
            alert('Failed to generate PDF. Please try again.');
        } finally {
            resetBtns(receiptBtns);
        }
    };

    window.downloadApplication = async function () {
        if (!window.html2canvas || !window.jspdf) {
            alert('PDF library is still loading — please wait a moment and try again.');
            return;
        }
        setLoading(applicationBtns, 'Generating…');
        try {
            await captureAndSave('applicationSection', 'RTTC2026_Application_Form_<?= $appNumber ?>.pdf', 5);
        } catch (err) {
            console.error('PDF error:', err);
            alert('Failed to generate PDF. Please try again.');
        } finally {
            resetBtns(applicationBtns);
        }
    };

})();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/main.php';
