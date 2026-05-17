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
         SECTION 1 — PAYMENT RECEIPT  (fixed 794px = A4 width at 96dpi)
         No Bootstrap responsive grid inside — layout never reflows.
    ════════════════════════════════════════════════════════════════ -->
    <div id="receiptSection" style="max-width:794px;min-width:680px;margin:0 auto 2.5rem;font-family:'Segoe UI',Arial,sans-serif;border-radius:8px;box-shadow:0 2px 14px rgba(0,0,0,.13);">

        <!-- Header -->
        <div style="background:linear-gradient(135deg,#27276d,#4a4ab0);padding:14px 16px;border-radius:8px 8px 0 0;">
            <div style="display:flex;flex-wrap:nowrap;align-items:center;gap:14px;">
                <img src="<?= BASE_URL ?>/assets/img/RTTC_logo.jpeg" alt="RTTC Logo"
                     style="height:58px;width:58px;object-fit:cover;border-radius:50%;border:3px solid rgba(255,255,255,.4);flex-shrink:0;">
                <div style="flex:1;color:#fff;min-width:0;">
                    <div style="font-size:1.05rem;font-weight:700;letter-spacing:.2px;">Rangia Teacher Training College</div>
                    <div style="font-size:0.72rem;opacity:.82;margin-top:2px;">Ward No. 05, Mahendra Das Path, Rangia, Kamrup, Assam — 781354</div>
                </div>
                <div style="background:#ffc107;color:#212529;padding:6px 16px;border-radius:4px;font-weight:700;font-size:0.85rem;white-space:nowrap;flex-shrink:0;">
                    PAYMENT RECEIPT
                </div>
            </div>
        </div>

        <!-- Amount + Status -->
        <div style="background:#fff;padding:16px 16px;text-align:center;border-bottom:2px dashed #dee2e6;">
            <div style="font-size:2.4rem;color:#198754;line-height:1;">&#10003;</div>
            <div style="font-size:2rem;font-weight:700;color:#198754;margin:4px 0;"><?= $paidAmt ?></div>
            <div style="font-size:1.05rem;font-weight:600;color:#198754;margin-bottom:4px;">Payment Successful</div>
            <div style="font-size:0.82rem;color:#6c757d;">Thank you for completing your application fee payment.</div>
        </div>

        <!-- 4-column Payment Info Grid — table guarantees no overflow -->
        <div style="background:#fff;padding:14px 16px 12px;">
            <table width="100%" style="table-layout:fixed;border-collapse:separate;border-spacing:8px 0;margin-bottom:12px;">
                <tr>
                    <td style="background:#f8f9fa;border-radius:6px;padding:10px 8px;text-align:center;vertical-align:top;">
                        <div style="color:#6c757d;font-size:0.72rem;margin-bottom:4px;">Payment Date &amp; Time</div>
                        <div style="font-weight:600;font-size:0.82rem;"><?= $paidAt ?></div>
                    </td>
                    <td style="background:#f8f9fa;border-radius:6px;padding:10px 8px;text-align:center;vertical-align:top;">
                        <div style="color:#6c757d;font-size:0.72rem;margin-bottom:4px;">Razorpay Order ID</div>
                        <div style="font-weight:600;font-size:0.74rem;word-break:break-all;"><?= htmlspecialchars($data['razorpay_order_id'] ?? 'N/A') ?></div>
                    </td>
                    <td style="background:#f8f9fa;border-radius:6px;padding:10px 8px;text-align:center;vertical-align:top;">
                        <div style="color:#6c757d;font-size:0.72rem;margin-bottom:4px;">Payment ID</div>
                        <div style="font-weight:600;font-size:0.74rem;word-break:break-all;"><?= htmlspecialchars($data['razorpay_payment_id'] ?? 'N/A') ?></div>
                    </td>
                    <td style="background:#f8f9fa;border-radius:6px;padding:10px 8px;text-align:center;vertical-align:top;">
                        <div style="color:#6c757d;font-size:0.72rem;margin-bottom:4px;">Application No.</div>
                        <div style="font-weight:600;font-size:0.82rem;"><?= $appNumber ?></div>
                    </td>
                </tr>
            </table>

            <!-- Applicant Details — table-based 3-col grid, no overflow -->
            <div style="background:#f0f0fa;border-left:4px solid #27276d;border-radius:5px;padding:12px 14px;">
                <div style="font-weight:700;color:#27276d;font-size:0.86rem;margin-bottom:8px;">Applicant Details</div>
                <table width="100%" style="table-layout:fixed;border-collapse:collapse;margin-bottom:6px;">
                    <tr>
                        <td style="width:33.33%;padding:0 8px 6px 0;vertical-align:top;">
                            <div style="color:#6c757d;font-size:0.72rem;">Full Name</div>
                            <div style="font-weight:600;font-size:0.86rem;"><?= htmlspecialchars($fullName) ?></div>
                        </td>
                        <td style="width:33.33%;padding:0 8px 6px;vertical-align:top;">
                            <div style="color:#6c757d;font-size:0.72rem;">Email Address</div>
                            <div style="font-weight:600;font-size:0.86rem;word-break:break-all;"><?= htmlspecialchars($data['email'] ?? '') ?></div>
                        </td>
                        <td style="width:33.33%;padding:0 0 6px 8px;vertical-align:top;">
                            <div style="color:#6c757d;font-size:0.72rem;">Mobile Number</div>
                            <div style="font-weight:600;font-size:0.86rem;"><?= htmlspecialchars($data['phone'] ?? '') ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 8px 0 0;vertical-align:top;">
                            <div style="color:#6c757d;font-size:0.72rem;">Application For</div>
                            <div style="font-weight:600;font-size:0.86rem;">B.Ed. First Year Admission 2025-26</div>
                        </td>
                        <td style="padding:0 8px;vertical-align:top;">
                            <div style="color:#6c757d;font-size:0.72rem;">Amount Paid</div>
                            <div style="font-weight:600;font-size:0.86rem;"><?= $paidAmt ?> (<?= htmlspecialchars($data['currency'] ?? 'INR') ?>)</div>
                        </td>
                        <td style="padding:0 0 0 8px;vertical-align:top;">
                            <div style="color:#6c757d;font-size:0.72rem;">Payment Status</div>
                            <span style="display:inline-block;background:#198754;color:#fff;padding:2px 10px;border-radius:4px;font-size:0.76rem;font-weight:600;margin-top:2px;">PAID</span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div style="background:#f8f8fc;border-top:1px solid #e9ecef;padding:8px 16px;display:flex;flex-wrap:nowrap;justify-content:space-between;align-items:center;gap:8px;border-radius:0 0 8px 8px;">
            <div style="font-size:0.77rem;color:#6c757d;">
                Secure payment powered by <strong>Razorpay</strong> &nbsp;|&nbsp; RTTC, Rangia, Assam — 781354
            </div>
            <div style="font-size:0.77rem;color:#6c757d;white-space:nowrap;">
                Computer-generated receipt &nbsp;|&nbsp; <?= date('d M Y') ?>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════
         SECTION 2 — APPLICATION FORM  (fixed 794px, compact one-page)
         All inner layouts use explicit flex/table — zero Bootstrap grid.
    ════════════════════════════════════════════════════════════════ -->
    <div id="applicationSection" style="max-width:794px;min-width:680px;margin:0 auto 2.5rem;font-family:'Segoe UI',Arial,sans-serif;font-size:0.8rem;border-radius:8px;box-shadow:0 2px 14px rgba(0,0,0,.13);">

        <!-- Header -->
        <div style="background:linear-gradient(135deg,#27276d,#4a4ab0);padding:10px 14px;border-radius:8px 8px 0 0;">
            <div style="display:flex;flex-wrap:nowrap;align-items:center;gap:10px;">
                <img src="<?= BASE_URL ?>/assets/img/RTTC_logo.jpeg" alt="RTTC Logo"
                     style="height:52px;width:52px;object-fit:cover;border-radius:50%;border:2px solid rgba(255,255,255,.4);flex-shrink:0;">
                <div style="flex:1;color:#fff;min-width:0;">
                    <div style="font-size:1rem;font-weight:700;letter-spacing:.3px;">Rangia Teacher Training College</div>
                    <div style="font-size:0.7rem;opacity:.82;">Ward No. 05, Mahendra Das Path, Rangia, Kamrup, Assam — 781354</div>
                    <div style="font-size:0.68rem;opacity:.76;margin-top:1px;">B.Ed. First Year Admission Application 2025-26</div>
                </div>
                <div style="color:#fff;text-align:right;flex-shrink:0;">
                    <div style="font-size:0.62rem;opacity:.76;">Application No.</div>
                    <div style="font-size:0.84rem;font-weight:700;"><?= $appNumber ?></div>
                    <div style="font-size:0.62rem;opacity:.76;">Date: <?= date('d M Y') ?></div>
                </div>
            </div>
        </div>

        <div style="background:#fff;padding:10px 12px;">

            <!-- ROW 1: Personal (46%) | Category+Contact (40%) | Photo+Sig (14%) -->
            <div style="display:flex;flex-wrap:nowrap;gap:8px;margin-bottom:8px;">

                <!-- Personal Details -->
                <div style="width:46%;flex-shrink:0;background:#f0f0fa;border-left:3px solid #27276d;border-radius:4px;padding:8px;">
                    <div style="font-weight:700;color:#27276d;font-size:0.68rem;letter-spacing:.5px;margin-bottom:5px;">PERSONAL DETAILS</div>
                    <table style="width:100%;border-collapse:collapse;font-size:0.74rem;">
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;width:42%;white-space:nowrap;">Full Name</td><td style="font-weight:600;padding:2px 0;"><?= htmlspecialchars($fullName) ?></td></tr>
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;">Father's Name</td><td style="font-weight:600;padding:2px 0;"><?= htmlspecialchars($data['fathersname'] ?? '-') ?></td></tr>
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;">Mother's Name</td><td style="font-weight:600;padding:2px 0;"><?= htmlspecialchars($data['mothersname'] ?? '-') ?></td></tr>
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;">Date of Birth</td><td style="font-weight:600;padding:2px 0;"><?= htmlspecialchars($data['dob'] ?? '-') ?><?= $data['age'] ? ' (Age: '.$data['age'].')' : '' ?></td></tr>
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;">Gender</td><td style="font-weight:600;padding:2px 0;"><?= htmlspecialchars($data['gender'] ?? '-') ?></td></tr>
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;">Blood Group</td><td style="font-weight:600;padding:2px 0;"><?= htmlspecialchars($data['blood_group'] ?? '-') ?></td></tr>
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;">Religion</td><td style="font-weight:600;padding:2px 0;"><?= htmlspecialchars($data['religion'] ?? '-') ?></td></tr>
                    </table>
                </div>

                <!-- Category & Contact -->
                <div style="width:40%;flex-shrink:0;background:#f8f8fc;border-left:3px solid #6c757d;border-radius:4px;padding:8px;">
                    <div style="font-weight:700;color:#555;font-size:0.68rem;letter-spacing:.5px;margin-bottom:5px;">CATEGORY &amp; CONTACT</div>
                    <table style="width:100%;border-collapse:collapse;font-size:0.74rem;">
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;width:42%;white-space:nowrap;">Caste/Category</td><td style="font-weight:600;padding:2px 0;"><?= htmlspecialchars($data['caste'] ?? '-') ?><?= ($data['ews'] ?? 0) ? ' <span style="color:#0d6efd;font-weight:600;">EWS</span>' : '' ?><?= ($data['obc_ncl'] ?? 0) ? ' <span style="color:#0d6efd;font-weight:600;">OBC-NCL</span>' : '' ?><?= ($data['pwd'] ?? 0) ? ' <span style="color:#dc3545;font-weight:600;">PWD</span>' : '' ?></td></tr>
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;">Income</td><td style="font-weight:600;padding:2px 0;"><?= htmlspecialchars($data['income'] ?? '-') ?></td></tr>
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;">Email</td><td style="font-weight:600;padding:2px 0;word-break:break-all;"><?= htmlspecialchars($data['email'] ?? '-') ?></td></tr>
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;">Mobile</td><td style="font-weight:600;padding:2px 0;"><?= htmlspecialchars($data['phone'] ?? '-') ?></td></tr>
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;">Emergency</td><td style="font-weight:600;padding:2px 0;"><?= htmlspecialchars($data['emergency_contact'] ?? '-') ?></td></tr>
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;">Address</td><td style="font-weight:600;padding:2px 0;line-height:1.3;"><?= htmlspecialchars($data['permanent_address'] ?? '-') ?></td></tr>
                    </table>
                </div>

                <!-- Photo + Signature -->
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;">
                    <?php if (!empty($data['photo'])): ?>
                    <div style="text-align:center;">
                        <img src="<?= BASE_URL . '/' . htmlspecialchars($data['photo']) ?>" alt="Photo"
                             style="width:76px;height:96px;object-fit:cover;border:2px solid #27276d;border-radius:3px;display:block;">
                        <div style="font-size:0.6rem;color:#888;margin-top:2px;">Passport Photo</div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($data['signature'])): ?>
                    <div style="text-align:center;">
                        <img src="<?= BASE_URL . '/' . htmlspecialchars($data['signature']) ?>" alt="Signature"
                             style="width:86px;height:34px;object-fit:contain;border-bottom:1px solid #333;display:block;">
                        <div style="font-size:0.6rem;color:#888;margin-top:2px;">Signature</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ROW 2: Academic Details (full-width table, fixed layout) -->
            <div style="margin-bottom:8px;">
                <div style="background:#27276d;color:#fff;padding:5px 10px;font-size:0.72rem;font-weight:700;letter-spacing:.6px;border-radius:3px 3px 0 0;">ACADEMIC DETAILS</div>
                <table style="width:100%;border-collapse:collapse;font-size:0.72rem;table-layout:fixed;">
                    <colgroup>
                        <col style="width:18%"><col style="width:18%"><col style="width:24%">
                        <col style="width:8%"><col style="width:8%"><col style="width:8%">
                        <col style="width:8%"><col style="width:8%">
                    </colgroup>
                    <thead>
                        <tr style="background:#3d3d8a;color:#fff;">
                            <th style="padding:4px 7px;border:1px solid #5555a0;text-align:left;">Examination</th>
                            <th style="padding:4px 7px;border:1px solid #5555a0;text-align:left;">Board / University</th>
                            <th style="padding:4px 7px;border:1px solid #5555a0;text-align:left;">Institution</th>
                            <th style="padding:4px 5px;border:1px solid #5555a0;text-align:center;">Year</th>
                            <th style="padding:4px 5px;border:1px solid #5555a0;text-align:center;">Obtained</th>
                            <th style="padding:4px 5px;border:1px solid #5555a0;text-align:center;">Total</th>
                            <th style="padding:4px 5px;border:1px solid #5555a0;text-align:center;">%</th>
                            <th style="padding:4px 5px;border:1px solid #5555a0;text-align:center;">Division</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $exams = [
                            'HSLC (Class X)'    => 'hslc',
                            'HSSLC (Class XII)' => 'hsslc',
                            "Bachelor's Degree" => 'bachelor',
                            "Master's Degree"   => 'masters',
                        ];
                        $rowIdx = 0;
                        foreach ($exams as $ename => $prefix):
                            if (empty($data[$prefix . '_board'])) continue;
                            $rowBg = ($rowIdx++ % 2 === 0) ? '#fff' : '#f5f5fb';
                        ?>
                        <tr style="background:<?= $rowBg ?>;">
                            <td style="padding:3px 7px;border:1px solid #c5c5d8;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= $ename ?></td>
                            <td style="padding:3px 7px;border:1px solid #c5c5d8;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($data[$prefix.'_board'] ?? '-') ?></td>
                            <td style="padding:3px 7px;border:1px solid #c5c5d8;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($data[$prefix.'_institute'] ?? '-') ?></td>
                            <td style="padding:3px 5px;border:1px solid #c5c5d8;text-align:center;"><?= htmlspecialchars($data[$prefix.'_pass_year'] ?? '-') ?></td>
                            <td style="padding:3px 5px;border:1px solid #c5c5d8;text-align:center;"><?= htmlspecialchars($data[$prefix.'_obtained_marks'] ?? '-') ?></td>
                            <td style="padding:3px 5px;border:1px solid #c5c5d8;text-align:center;"><?= htmlspecialchars($data[$prefix.'_total_marks'] ?? '-') ?></td>
                            <td style="padding:3px 5px;border:1px solid #c5c5d8;text-align:center;font-weight:700;color:#27276d;"><?= htmlspecialchars($data[$prefix.'_percentage'] ?? '-') ?>%</td>
                            <td style="padding:3px 5px;border:1px solid #c5c5d8;text-align:center;"><?= htmlspecialchars($data[$prefix.'_division'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ROW 3: GUBEDCET Details (full-width table, fixed layout) -->
            <div style="margin-bottom:8px;">
                <div style="background:#27276d;color:#fff;padding:5px 10px;font-size:0.72rem;font-weight:700;letter-spacing:.6px;border-radius:3px 3px 0 0;">GUBEDCET 2026 DETAILS</div>
                <table style="width:100%;border-collapse:collapse;font-size:0.72rem;table-layout:fixed;">
                    <colgroup>
                        <col style="width:20%"><col style="width:40%">
                        <col style="width:15%"><col style="width:12%"><col style="width:13%">
                    </colgroup>
                    <thead>
                        <tr style="background:#3d3d8a;color:#fff;">
                            <th style="padding:4px 7px;border:1px solid #5555a0;text-align:left;">Roll No.</th>
                            <th style="padding:4px 7px;border:1px solid #5555a0;text-align:left;">Name (as per GUBEDCET)</th>
                            <th style="padding:4px 5px;border:1px solid #5555a0;text-align:center;">Marks Obtained</th>
                            <th style="padding:4px 5px;border:1px solid #5555a0;text-align:center;">Rank</th>
                            <th style="padding:4px 5px;border:1px solid #5555a0;text-align:center;">Category</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="background:#fff;">
                            <td style="padding:3px 7px;border:1px solid #c5c5d8;font-weight:600;"><?= htmlspecialchars($data['gubedcet_rollno'] ?? '-') ?></td>
                            <td style="padding:3px 7px;border:1px solid #c5c5d8;font-weight:600;"><?= htmlspecialchars($data['gubedcet_name'] ?? '-') ?></td>
                            <td style="padding:3px 5px;border:1px solid #c5c5d8;text-align:center;font-weight:700;color:#27276d;"><?= htmlspecialchars($data['gubedcet_marks'] ?? '-') ?></td>
                            <td style="padding:3px 5px;border:1px solid #c5c5d8;text-align:center;font-weight:700;color:#27276d;"><?= htmlspecialchars($data['gubedcet_rank'] ?? '-') ?></td>
                            <td style="padding:3px 5px;border:1px solid #c5c5d8;text-align:center;"><?= htmlspecialchars($data['gubedcet_category'] ?? '-') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ROW 4: GU Details (50%) + Payment Summary (50%) — no-wrap flex -->
            <div style="display:flex;flex-wrap:nowrap;gap:8px;margin-bottom:8px;">
                <div style="flex:1;background:#f0fff4;border-left:3px solid #198754;border-radius:4px;padding:8px;">
                    <div style="font-weight:700;color:#198754;font-size:0.68rem;letter-spacing:.5px;margin-bottom:5px;">GAUHATI UNIVERSITY DETAILS</div>
                    <table style="width:100%;border-collapse:collapse;font-size:0.73rem;">
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;width:45%;white-space:nowrap;">GU Registered?</td><td style="font-weight:600;padding:2px 0;"><?= ucfirst($data['gu_registered'] ?? 'No') ?></td></tr>
                        <?php if (!empty($data['gu_reg_no'])): ?>
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;">GU Reg. No.</td><td style="font-weight:600;padding:2px 0;"><?= htmlspecialchars($data['gu_reg_no']) ?></td></tr>
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;">GU Reg. Year</td><td style="font-weight:600;padding:2px 0;"><?= htmlspecialchars($data['gu_reg_year'] ?? '-') ?></td></tr>
                        <?php endif; ?>
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;">Migrated?</td><td style="font-weight:600;padding:2px 0;"><?= ucfirst($data['migrated'] ?? 'No') ?><?= !empty($data['other_university']) ? ' ('.htmlspecialchars($data['other_university']).')' : '' ?></td></tr>
                    </table>
                </div>
                <div style="flex:1;background:#fff8e1;border-left:3px solid #ffc107;border-radius:4px;padding:8px;">
                    <div style="font-weight:700;color:#856404;font-size:0.68rem;letter-spacing:.5px;margin-bottom:5px;">PAYMENT SUMMARY</div>
                    <table style="width:100%;border-collapse:collapse;font-size:0.73rem;">
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;width:40%;white-space:nowrap;">Amount Paid</td><td style="font-weight:700;color:#198754;padding:2px 0;"><?= $paidAmt ?></td></tr>
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;">Payment Date</td><td style="font-weight:600;padding:2px 0;"><?= $paidAt ?></td></tr>
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;">Payment ID</td><td style="font-weight:600;padding:2px 0;word-break:break-all;"><?= htmlspecialchars($data['razorpay_payment_id'] ?? 'N/A') ?></td></tr>
                        <tr><td style="color:#6c757d;padding:2px 6px 2px 0;">Status</td><td style="padding:2px 0;"><span style="color:#198754;font-weight:700;">&#10003; PAID</span></td></tr>
                    </table>
                </div>
            </div>

            <!-- ROW 5: Declaration -->
            <div style="display:flex;flex-wrap:nowrap;align-items:flex-end;gap:12px;padding:10px 12px;border:1px dashed #27276d;border-radius:4px;background:#f8f8fc;">
                <div style="font-size:0.68rem;color:#555;flex:1;">
                    I hereby declare that all the information furnished above is true, correct and complete to the best of my knowledge and belief.
                    I understand that if any information is found to be false or incorrect, my application will be liable to be cancelled.
                    <div style="margin-top:6px;">Place: _______________________&nbsp;&nbsp;&nbsp; Date: _______________________</div>
                </div>
                <div style="text-align:center;flex-shrink:0;">
                    <?php if (!empty($data['signature'])): ?>
                    <img src="<?= BASE_URL . '/' . htmlspecialchars($data['signature']) ?>"
                         style="height:40px;max-width:140px;object-fit:contain;border-bottom:1px solid #333;display:block;">
                    <?php else: ?>
                    <div style="width:140px;border-bottom:1px solid #333;height:40px;"></div>
                    <?php endif; ?>
                    <div style="font-size:0.65rem;color:#888;margin-top:2px;">Applicant's Signature</div>
                </div>
            </div>

        </div><!-- /body -->

        <!-- Footer -->
        <div style="background:#27276d;color:#fff;padding:7px 14px;text-align:center;font-size:0.67rem;border-radius:0 0 8px 8px;">
            <span style="opacity:.88;">Rangia Teacher Training College &nbsp;|&nbsp; Rangia, Kamrup, Assam — 781354 &nbsp;|&nbsp; Tel: +91 03621-359330 &nbsp;|&nbsp; admissionrttc@gmail.com</span>
        </div>
    </div>

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
