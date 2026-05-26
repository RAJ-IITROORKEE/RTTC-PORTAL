<?php
/**
 * Payment Receipt Component
 *
 * Optimized Professional A4 Printable Layout
 * Fixed Overflow + Perfect PDF Fit
 */

if (!isset($data, $userId, $appNumber, $paidAmt, $paidAt)) {
    throw new Exception('Missing required variables for payment receipt component');
}

$paidDate = $data['payment_date'] ?? null;
$paidAtFormatted = $paidDate
    ? date('d M Y, h:i A', strtotime($paidDate))
    : 'N/A';

$fullName = trim(
    ($data['firstname'] ?? '') . ' ' .
    ($data['middlename'] ?? '') . ' ' .
    ($data['lastname'] ?? '')
);
?>

<!-- =========================================================
     PAYMENT RECEIPT COMPONENT
     PERFECT A4 PDF SAFE LAYOUT
========================================================= -->

<div id="receiptSection"
     style="
        width:794px;
        min-height:560px;
        margin:0 auto 32px;
        background:#ffffff;
        border-radius:8px;
        overflow:hidden;
        box-shadow:0 2px 14px rgba(0,0,0,.12);
        font-family:'Segoe UI',Arial,sans-serif;
        box-sizing:border-box;
     ">

    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div style="
        background:linear-gradient(135deg,#1f1f72 0%, #4040b2 100%);
        padding:18px 22px;
    ">

        <div style="
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:18px;
        ">

            <!-- Left -->
            <div style="
                display:flex;
                align-items:center;
                gap:14px;
                min-width:0;
                flex:1;
            ">

                <img
                    src="<?= BASE_URL ?>/assets/img/RTTC_logo.jpeg"
                    alt="RTTC Logo"
                    style="
                        width:62px;
                        height:62px;
                        border-radius:50%;
                        object-fit:cover;
                        background:#fff;
                        padding:3px;
                        border:2px solid rgba(255,255,255,.35);
                        flex-shrink:0;
                    "
                >

                <div style="min-width:0;">

                    <div style="
                        color:#ffffff;
                        font-size:1.18rem;
                        font-weight:700;
                        line-height:1.2;
                    ">
                        Rangia Teacher Training College
                    </div>

                    <div style="
                        color:rgba(255,255,255,.82);
                        font-size:0.76rem;
                        margin-top:4px;
                        line-height:1.45;
                    ">
                        Ward No. 05, Mahendra Das Path,
                        Rangia, Kamrup, Assam — 781354
                    </div>

                </div>

            </div>

            <!-- Badge -->
            <div style="
                background:#ffc107;
                color:#111;
                padding:7px 16px;
                border-radius:5px;
                font-size:0.78rem;
                font-weight:700;
                letter-spacing:.3px;
                white-space:nowrap;
                flex-shrink:0;
            ">
                PAYMENT RECEIPT
            </div>

        </div>

    </div>

    <!-- =====================================================
         SUCCESS AREA
    ====================================================== -->

    <div style="
        text-align:center;
        padding:28px 24px 22px;
        border-bottom:2px dashed #dddddd;
    ">

        <div style="
            font-size:3rem;
            color:#198754;
            line-height:1;
            margin-bottom:6px;
        ">
            ✓
        </div>

        <div style="
            font-size:2.7rem;
            font-weight:800;
            color:#198754;
            line-height:1.1;
        ">
            <?= $paidAmt ?>
        </div>

        <div style="
            margin-top:8px;
            color:#198754;
            font-size:1.05rem;
            font-weight:700;
        ">
            Payment Successful
        </div>

        <div style="
            margin-top:6px;
            color:#6c757d;
            font-size:0.84rem;
            line-height:1.5;
        ">
            Your application fee payment has been received successfully.
            Please download and keep this receipt for future reference.
        </div>

    </div>

    <!-- =====================================================
         PAYMENT INFO GRID
    ====================================================== -->

    <div style="padding:18px;">

        <div style="
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:12px;
            margin-bottom:18px;
        ">

            <!-- Date -->
            <div style="
                background:#f5f7fb;
                border:1px solid #e7ebf3;
                border-radius:8px;
                padding:14px 12px;
                text-align:center;
            ">
                <div style="
                    color:#777;
                    font-size:0.72rem;
                    margin-bottom:5px;
                ">
                    Payment Date & Time
                </div>

                <div style="
                    font-weight:700;
                    font-size:0.84rem;
                    color:#222;
                ">
                    <?= $paidAtFormatted ?>
                </div>
            </div>

            <!-- App No -->
            <div style="
                background:#f5f7fb;
                border:1px solid #e7ebf3;
                border-radius:8px;
                padding:14px 12px;
                text-align:center;
            ">
                <div style="
                    color:#777;
                    font-size:0.72rem;
                    margin-bottom:5px;
                ">
                    Application Number
                </div>

                <div style="
                    font-weight:700;
                    font-size:0.84rem;
                    color:#222;
                ">
                    <?= $appNumber ?>
                </div>
            </div>

            <!-- Order ID -->
            <div style="
                background:#f5f7fb;
                border:1px solid #e7ebf3;
                border-radius:8px;
                padding:14px 12px;
                text-align:center;
            ">
                <div style="
                    color:#777;
                    font-size:0.72rem;
                    margin-bottom:5px;
                ">
                    Razorpay Order ID
                </div>

                <div style="
                    font-weight:700;
                    font-size:0.74rem;
                    color:#222;
                    word-break:break-word;
                    line-height:1.4;
                ">
                    <?= htmlspecialchars($data['razorpay_order_id'] ?? 'N/A') ?>
                </div>
            </div>

            <!-- Payment ID -->
            <div style="
                background:#f5f7fb;
                border:1px solid #e7ebf3;
                border-radius:8px;
                padding:14px 12px;
                text-align:center;
            ">
                <div style="
                    color:#777;
                    font-size:0.72rem;
                    margin-bottom:5px;
                ">
                    Payment ID
                </div>

                <div style="
                    font-weight:700;
                    font-size:0.74rem;
                    color:#222;
                    word-break:break-word;
                    line-height:1.4;
                ">
                    <?= htmlspecialchars($data['razorpay_payment_id'] ?? 'N/A') ?>
                </div>
            </div>

        </div>

        <!-- =================================================
             APPLICANT DETAILS
        ================================================== -->

        <div style="
            background:#f9fbff;
            border:1px solid #dde4f0;
            border-left:4px solid #27276d;
            border-radius:8px;
            padding:16px;
        ">

            <div style="
                color:#27276d;
                font-size:0.92rem;
                font-weight:700;
                margin-bottom:14px;
            ">
                Applicant Details
            </div>

            <div style="
                display:grid;
                grid-template-columns:1fr 1fr;
                gap:14px 20px;
            ">

                <!-- Full Name -->
                <div>
                    <div style="
                        color:#6c757d;
                        font-size:0.72rem;
                        margin-bottom:4px;
                    ">
                        Full Name
                    </div>

                    <div style="
                        font-size:0.86rem;
                        font-weight:600;
                        color:#222;
                        line-height:1.45;
                        word-break:break-word;
                    ">
                        <?= htmlspecialchars($fullName) ?>
                    </div>
                </div>

                <!-- Email -->
                <div>
                    <div style="
                        color:#6c757d;
                        font-size:0.72rem;
                        margin-bottom:4px;
                    ">
                        Email Address
                    </div>

                    <div style="
                        font-size:0.84rem;
                        font-weight:600;
                        color:#222;
                        line-height:1.45;
                        word-break:break-word;
                    ">
                        <?= htmlspecialchars($data['email'] ?? '') ?>
                    </div>
                </div>

                <!-- Mobile -->
                <div>
                    <div style="
                        color:#6c757d;
                        font-size:0.72rem;
                        margin-bottom:4px;
                    ">
                        Mobile Number
                    </div>

                    <div style="
                        font-size:0.86rem;
                        font-weight:600;
                        color:#222;
                    ">
                        <?= htmlspecialchars($data['phone'] ?? '') ?>
                    </div>
                </div>

                <!-- Course -->
                <div>
                    <div style="
                        color:#6c757d;
                        font-size:0.72rem;
                        margin-bottom:4px;
                    ">
                        Application For
                    </div>

                    <div style="
                        font-size:0.86rem;
                        font-weight:600;
                        color:#222;
                    ">
                        B.Ed. First Year Admission 2025-26
                    </div>
                </div>

                <!-- Amount -->
                <div>
                    <div style="
                        color:#6c757d;
                        font-size:0.72rem;
                        margin-bottom:4px;
                    ">
                        Amount Paid
                    </div>

                    <div style="
                        font-size:0.86rem;
                        font-weight:700;
                        color:#222;
                    ">
                        <?= $paidAmt ?>
                        (<?= htmlspecialchars($data['currency'] ?? 'INR') ?>)
                    </div>
                </div>

                <!-- Status -->
                <div>
                    <div style="
                        color:#6c757d;
                        font-size:0.72rem;
                        margin-bottom:4px;
                    ">
                        Payment Status
                    </div>

                    <span style="
                        display:inline-block;
                        background:#198754;
                        color:#fff;
                        padding:4px 12px;
                        border-radius:4px;
                        font-size:0.76rem;
                        font-weight:700;
                    ">
                        ✓ PAID
                    </span>
                </div>

            </div>

        </div>

    </div>

    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <div style="
        border-top:1px solid #e5e7eb;
        background:#f8f9fc;
        padding:10px 18px;
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:12px;
        flex-wrap:wrap;
    ">

        <div style="
            color:#6c757d;
            font-size:0.76rem;
            line-height:1.5;
        ">
            Secure payment powered by <strong>Razorpay</strong>
            &nbsp;|&nbsp;
            RTTC, Rangia, Assam — 781354
        </div>

        <div style="
            color:#6c757d;
            font-size:0.76rem;
            white-space:nowrap;
        ">
            Computer-generated receipt
            &nbsp;|&nbsp;
            <?= date('d M Y') ?>
        </div>

    </div>

</div>