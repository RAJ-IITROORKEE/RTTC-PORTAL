<?php
/**
 * APPLICATION FORM COMPONENT
 *
 * PROFESSIONAL A4 OPTIMIZED LAYOUT
 * - Fixed Overflow
 * - Proper Spacing
 * - Better Typography
 * - Single Page Optimized
 * - Long Address Safe
 * - Manual Signature Space
 * - Proper Academic Table Fit
 * - Standard Printable Government Form UI
 */

if (!isset($data, $userId, $appNumber, $paidAmt, $paidAt, $fullName)) {
    throw new Exception('Missing required variables for application form component');
}

$paidDate = $data['payment_date'] ?? null;

$paidAtFormatted = $paidDate
    ? date('d M Y, h:i A', strtotime($paidDate))
    : 'N/A';

?>

<!-- =========================================================
     APPLICATION FORM
========================================================= -->

<div id="applicationSection"
     style="
        width:794px;
        min-height:1123px;
        margin:0 auto 32px;
        background:#fff;
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
        padding:14px 18px;
    ">

        <div style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:16px;
        ">

            <!-- LEFT -->
            <div style="
                display:flex;
                align-items:center;
                gap:14px;
                flex:1;
                min-width:0;
            ">

                <img
                    src="<?= BASE_URL ?>/assets/img/RTTC_logo.jpeg"
                    alt="Logo"
                    style="
                        width:58px;
                        height:58px;
                        border-radius:50%;
                        background:#fff;
                        padding:3px;
                        object-fit:cover;
                        flex-shrink:0;
                    "
                >

                <div>

                    <div style="
                        color:#fff;
                        font-size:1.05rem;
                        font-weight:700;
                        line-height:1.2;
                    ">
                        Rangia Teacher Training College
                    </div>

                    <div style="
                        color:rgba(255,255,255,.84);
                        font-size:.68rem;
                        line-height:1.5;
                        margin-top:3px;
                    ">
                        Ward No. 05, Mahendra Das Path,
                        Rangia, Kamrup, Assam — 781354
                    </div>

                    <div style="
                        color:rgba(255,255,255,.78);
                        font-size:.66rem;
                        margin-top:2px;
                    ">
                        B.Ed. First Year Admission Application 2025-26
                    </div>

                </div>

            </div>

            <!-- RIGHT -->
            <div style="
                text-align:right;
                color:#fff;
                flex-shrink:0;
            ">

                <div style="
                    font-size:.62rem;
                    opacity:.75;
                ">
                    Application No.
                </div>

                <div style="
                    font-size:.88rem;
                    font-weight:700;
                    margin-top:2px;
                ">
                    <?= $appNumber ?>
                </div>

                <div style="
                    font-size:.62rem;
                    opacity:.75;
                    margin-top:2px;
                ">
                    <?= date('d M Y') ?>
                </div>

            </div>

        </div>

    </div>

    <!-- =====================================================
         BODY
    ====================================================== -->

    <div style="
        padding:12px;
        background:#fff;
    ">

        <!-- =================================================
             PERSONAL DETAILS
        ================================================== -->

        <div style="
            margin-bottom:10px;
        ">

            <div style="
                background:#27276d;
                color:#fff;
                padding:6px 10px;
                font-size:.72rem;
                font-weight:700;
                letter-spacing:.6px;
                border-radius:4px 4px 0 0;
            ">
                PERSONAL DETAILS
            </div>

            <div style="
                border:1px solid #d7d9e2;
                border-top:none;
                padding:10px;
            ">

                <div style="
                    display:grid;
                    grid-template-columns:1fr 160px;
                    gap:12px;
                ">

                    <!-- DETAILS -->
                    <div>

                        <table style="
                            width:100%;
                            border-collapse:collapse;
                            table-layout:fixed;
                            font-size:.72rem;
                        ">

                            <tr>
                                <td style="width:20%;padding:5px;background:#f5f5f7;border:1px solid #d7d9e2;font-weight:600;">
                                    Full Name
                                </td>

                                <td style="padding:5px 8px;border:1px solid #d7d9e2;font-weight:600;" colspan="3">
                                    <?= htmlspecialchars($fullName) ?>
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:5px;background:#f5f5f7;border:1px solid #d7d9e2;font-weight:600;">
                                    Father's Name
                                </td>

                                <td style="padding:5px 8px;border:1px solid #d7d9e2;">
                                    <?= htmlspecialchars($data['fathersname'] ?? '-') ?>
                                </td>

                                <td style="padding:5px;background:#f5f5f7;border:1px solid #d7d9e2;font-weight:600;">
                                    Mother's Name
                                </td>

                                <td style="padding:5px 8px;border:1px solid #d7d9e2;">
                                    <?= htmlspecialchars($data['mothersname'] ?? '-') ?>
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:5px;background:#f5f5f7;border:1px solid #d7d9e2;font-weight:600;">
                                    DOB
                                </td>

                                <td style="padding:5px 8px;border:1px solid #d7d9e2;">
                                    <?= htmlspecialchars($data['dob'] ?? '-') ?>
                                </td>

                                <td style="padding:5px;background:#f5f5f7;border:1px solid #d7d9e2;font-weight:600;">
                                    Gender
                                </td>

                                <td style="padding:5px 8px;border:1px solid #d7d9e2;">
                                    <?= htmlspecialchars($data['gender'] ?? '-') ?>
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:5px;background:#f5f5f7;border:1px solid #d7d9e2;font-weight:600;">
                                    Category
                                </td>

                                <td style="padding:5px 8px;border:1px solid #d7d9e2;">
                                    <?= htmlspecialchars($data['caste'] ?? '-') ?>
                                </td>

                                <td style="padding:5px;background:#f5f5f7;border:1px solid #d7d9e2;font-weight:600;">
                                    Income
                                </td>

                                <td style="padding:5px 8px;border:1px solid #d7d9e2;">
                                    <?= htmlspecialchars($data['income'] ?? '-') ?>
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:5px;background:#f5f5f7;border:1px solid #d7d9e2;font-weight:600;">
                                    Email
                                </td>

                                <td style="
                                    padding:5px 8px;
                                    border:1px solid #d7d9e2;
                                    word-break:break-word;
                                ">
                                    <?= htmlspecialchars($data['email'] ?? '-') ?>
                                </td>

                                <td style="padding:5px;background:#f5f5f7;border:1px solid #d7d9e2;font-weight:600;">
                                    Mobile
                                </td>

                                <td style="padding:5px 8px;border:1px solid #d7d9e2;">
                                    <?= htmlspecialchars($data['phone'] ?? '-') ?>
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:5px;background:#f5f5f7;border:1px solid #d7d9e2;font-weight:600;">
                                    Permanent Address
                                </td>

                                <td colspan="3"
                                    style="
                                        padding:6px 8px;
                                        border:1px solid #d7d9e2;
                                        line-height:1.5;
                                        word-break:break-word;
                                    ">
                                    <?= htmlspecialchars($data['permanent_address'] ?? '-') ?>
                                </td>
                            </tr>

                            <tr>
                                <td style="
                                    padding:5px;
                                    background:#f5f5f7;
                                    border:1px solid #d7d9e2;
                                    font-weight:600;
                                    vertical-align:top;
                                ">
                                    Present Address
                                </td>

                                <td colspan="3"
                                    style="
                                        padding:6px 8px;
                                        border:1px solid #d7d9e2;
                                        line-height:1.5;
                                        word-break:break-word;
                                        min-height:52px;
                                    ">
                                    <?= htmlspecialchars($data['present_address'] ?? '-') ?>
                                </td>
                            </tr>

                        </table>

                    </div>

                    <!-- PHOTO -->
                 <!-- PHOTO + SIGNATURE AREA -->

<div>

    <div style="
        border:1px solid #d7d9e2;
        padding:8px;
        text-align:center;
    ">

        <!-- PASSPORT PHOTO -->

        <?php if (!empty($data['photo'])): ?>

            <img
                src="<?= BASE_URL . '/' . htmlspecialchars($data['photo']) ?>"
                alt="Photo"
                style="
                    width:88px;
                    height:108px;
                    object-fit:cover;
                    border:1px solid #999;
                "
            >

        <?php else: ?>

            <div style="
                width:88px;
                height:108px;
                border:1px solid #999;
                margin:0 auto;
            "></div>

        <?php endif; ?>

        <div style="
            font-size:.62rem;
            color:#666;
            margin-top:4px;
        ">
            Passport Photo
        </div>

        <!-- BACKEND SIGNATURE -->

        <div style="
            margin-top:18px;
            text-align:center;
        ">

            <?php if (!empty($data['signature'])): ?>

                <img
                    src="<?= BASE_URL . '/' . htmlspecialchars($data['signature']) ?>"
                    alt="Signature"
                    style="
                        width:110px;
                        height:38px;
                        object-fit:contain;
                        border-bottom:1.5px solid #333;
                        display:block;
                        margin:0 auto;
                    "
                >

            <?php else: ?>

                <div style="
                    width:110px;
                    height:38px;
                    border-bottom:1.5px solid #333;
                    margin:0 auto;
                "></div>

            <?php endif; ?>

            <div style="
                font-size:.62rem;
                color:#666;
                margin-top:4px;
            ">
                Applicant Signature
            </div>

        </div>

    </div>

</div>

                </div>

            </div>

        </div>

        <!-- =================================================
             ACADEMIC DETAILS
        ================================================== -->

        <div style="margin-bottom:10px;">

            <div style="
                background:#27276d;
                color:#fff;
                padding:6px 10px;
                font-size:.72rem;
                font-weight:700;
                border-radius:4px 4px 0 0;
            ">
                ACADEMIC DETAILS
            </div>

            <table style="
                width:100%;
                border-collapse:collapse;
                table-layout:fixed;
                font-size:.68rem;
            ">

                <thead>

                    <tr style="
                        background:#3d3d8a;
                        color:#fff;
                    ">

                        <th style="padding:5px;border:1px solid #5c5cb2;text-align:left;">Exam</th>
                        <th style="padding:5px;border:1px solid #5c5cb2;text-align:left;">Board</th>
                        <th style="padding:5px;border:1px solid #5c5cb2;text-align:left;">Institution</th>
                        <th style="padding:5px;border:1px solid #5c5cb2;">Year</th>
                        <th style="padding:5px;border:1px solid #5c5cb2;">Obt.</th>
                        <th style="padding:5px;border:1px solid #5c5cb2;">Total</th>
                        <th style="padding:5px;border:1px solid #5c5cb2;">%</th>
                        <th style="padding:5px;border:1px solid #5c5cb2;">Division</th>

                    </tr>

                </thead>

                <tbody>

                <?php

                $exams = [
                    'HSLC (X)' => 'hslc',
                    'HSSLC (XII)' => 'hsslc',
                    "Bachelor's Degree" => 'bachelor',
                    "Master's Degree" => 'masters',
                ];

                $row = 0;

                foreach ($exams as $name => $prefix):

                    if (empty($data[$prefix . '_board'])) continue;

                    $bg = $row++ % 2 === 0
                        ? '#fff'
                        : '#f8f8fc';

                ?>

                    <tr style="background:<?= $bg ?>;">

                        <td style="padding:5px;border:1px solid #d7d9e2;font-weight:600;">
                            <?= $name ?>
                        </td>

                        <td style="padding:5px;border:1px solid #d7d9e2;">
                            <?= htmlspecialchars($data[$prefix.'_board'] ?? '-') ?>
                        </td>

                        <td style="
                            padding:5px;
                            border:1px solid #d7d9e2;
                            word-break:break-word;
                        ">
                            <?= htmlspecialchars($data[$prefix.'_institute'] ?? '-') ?>
                        </td>

                        <td style="padding:5px;border:1px solid #d7d9e2;text-align:center;">
                            <?= htmlspecialchars($data[$prefix.'_pass_year'] ?? '-') ?>
                        </td>

                        <td style="padding:5px;border:1px solid #d7d9e2;text-align:center;">
                            <?= htmlspecialchars($data[$prefix.'_obtained_marks'] ?? '-') ?>
                        </td>

                        <td style="padding:5px;border:1px solid #d7d9e2;text-align:center;">
                            <?= htmlspecialchars($data[$prefix.'_total_marks'] ?? '-') ?>
                        </td>

                        <td style="
                            padding:5px;
                            border:1px solid #d7d9e2;
                            text-align:center;
                            font-weight:700;
                            color:#27276d;
                        ">
                            <?= htmlspecialchars($data[$prefix.'_percentage'] ?? '-') ?>%
                        </td>

                        <td style="padding:5px;border:1px solid #d7d9e2;text-align:center;">
                            <?= htmlspecialchars($data[$prefix.'_division'] ?? '-') ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <!-- =================================================
             GUBEDCET + PAYMENT
        ================================================== -->

        <div style="
            display:grid;
            grid-template-columns:1fr 280px;
            gap:10px;
            margin-bottom:10px;
        ">

            <!-- GUBEDCET -->

            <div>

                <div style="
                    background:#27276d;
                    color:#fff;
                    padding:6px 10px;
                    font-size:.72rem;
                    font-weight:700;
                    border-radius:4px 4px 0 0;
                ">
                    GUBEDCET DETAILS
                </div>

                <table style="
                    width:100%;
                    border-collapse:collapse;
                    table-layout:fixed;
                    font-size:.68rem;
                ">

                    <tr>
                        <td style="padding:5px;background:#f5f5f7;border:1px solid #d7d9e2;font-weight:600;width:25%;">
                            Roll No.
                        </td>

                        <td style="padding:5px;border:1px solid #d7d9e2;">
                            <?= htmlspecialchars($data['gubedcet_rollno'] ?? '-') ?>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:5px;background:#f5f5f7;border:1px solid #d7d9e2;font-weight:600;">
                            Name
                        </td>

                        <td style="padding:5px;border:1px solid #d7d9e2;">
                            <?= htmlspecialchars($data['gubedcet_name'] ?? '-') ?>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:5px;background:#f5f5f7;border:1px solid #d7d9e2;font-weight:600;">
                            Marks / Rank
                        </td>

                        <td style="padding:5px;border:1px solid #d7d9e2;">
                            <?= htmlspecialchars($data['gubedcet_marks'] ?? '-') ?>
                            /
                            <?= htmlspecialchars($data['gubedcet_rank'] ?? '-') ?>
                        </td>
                    </tr>

                </table>

            </div>

            <!-- PAYMENT -->

            <div>

                <div style="
                    background:#198754;
                    color:#fff;
                    padding:6px 10px;
                    font-size:.72rem;
                    font-weight:700;
                    border-radius:4px 4px 0 0;
                ">
                    PAYMENT SUMMARY
                </div>

                <table style="
                    width:100%;
                    border-collapse:collapse;
                    font-size:.68rem;
                ">

                    <tr>
                        <td style="padding:5px;background:#f5f5f7;border:1px solid #d7d9e2;font-weight:600;width:42%;">
                            Amount
                        </td>

                        <td style="
                            padding:5px;
                            border:1px solid #d7d9e2;
                            font-weight:700;
                            color:#198754;
                        ">
                            <?= $paidAmt ?>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:5px;background:#f5f5f7;border:1px solid #d7d9e2;font-weight:600;">
                            Date
                        </td>

                        <td style="padding:5px;border:1px solid #d7d9e2;">
                            <?= $paidAtFormatted ?>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:5px;background:#f5f5f7;border:1px solid #d7d9e2;font-weight:600;">
                            Status
                        </td>

                        <td style="
                            padding:5px;
                            border:1px solid #d7d9e2;
                            color:#198754;
                            font-weight:700;
                        ">
                            ✓ PAID
                        </td>
                    </tr>

                </table>

            </div>

        </div>

        <!-- =================================================
             DECLARATION
        ================================================== -->

        <div style="
            border:1px dashed #666;
            padding:12px;
            border-radius:4px;
            background:#fafafa;
            margin-top:14px;
            min-height:90px;
        ">

            <div style="
                font-size:.67rem;
                color:#444;
                line-height:1.7;
            ">

                I hereby declare that all information furnished in this application
                is true and correct to the best of my knowledge and belief.

                <div style="margin-top:14px;">

                    Place: _______________________

                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

                    Date: _______________________

                </div>

            </div>

        </div>

        <!-- FOOTNOTE -->

        <div style="
            text-align:center;
            margin-top:10px;
            font-size:.62rem;
            color:#777;
        ">
            This is a computer-generated application form.
            No physical signature is required during online submission.
        </div>

    </div>

    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <div style="
        background:#27276d;
        color:#fff;
        text-align:center;
        padding:8px 14px;
        font-size:.64rem;
        line-height:1.6;
    ">

        Rangia Teacher Training College
        &nbsp;|&nbsp;
        Rangia, Kamrup, Assam — 781354
        &nbsp;|&nbsp;
        admissionrttc@gmail.com

    </div>

</div>