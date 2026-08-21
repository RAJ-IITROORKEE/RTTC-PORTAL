<?php
if (!defined('APP_INIT') || !isset($card) || !is_array($card)) {
    http_response_code(404);
    exit;
}

$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$isStudent = ($card['application_type'] ?? '') === IdCardHelper::TYPE_STUDENT;
$programme = $isStudent ? ($card['course'] ?: 'Student') : ($card['designation'] ?: 'Faculty/Staff');
$logo = BASE_URL . '/assets/img/RTTC_logo.jpeg';
?>
<section class="id-card-canvas id-card-front" id="id-card-front" aria-label="ID card front">
    <header class="id-card-header">
        <div class="id-card-brand">
            <img class="id-card-brand-logo" src="<?= $esc($logo) ?>" alt="RTTC logo">
            <div>
                <span class="id-card-brand-name">Rangia Teacher Training College</span>
                <span class="id-card-brand-subtitle">Rangia, Assam</span>
            </div>
        </div>
        <span class="id-card-title"><?= $esc($card['type_label']) ?> ID Card</span>
    </header>
    <div class="id-card-body">
        <div class="id-card-profile">
            <img class="id-card-photo" src="<?= $esc($card['photo_url']) ?>" alt="<?= $esc($card['full_name']) ?> photo">
            <div>
                <h2 class="id-card-name"><?= $esc($card['full_name']) ?></h2>
                <p class="id-card-course"><?= $esc($programme) ?></p>
                <span class="id-card-reference"><?= $esc($card['reference']) ?></span>
            </div>
        </div>
        <dl class="id-card-details">
            <div><dt>C/O</dt><dd><?= $esc($card['care_of']) ?></dd></div>
            <?php if ($isStudent): ?>
                <div><dt>Course</dt><dd><?= $esc($card['course']) ?></dd></div>
                <div><dt>Session</dt><dd><?= $esc($card['academic_session']) ?></dd></div>
                <div><dt>Roll No.</dt><dd><?= $esc($card['roll_number']) ?></dd></div>
                <div><dt>Date of Birth</dt><dd><?= $esc($card['date_of_birth'] ? date('d M Y', strtotime($card['date_of_birth'])) : '') ?></dd></div>
            <?php else: ?>
                <div><dt>Department</dt><dd><?= $esc($card['department']) ?></dd></div>
                <div><dt>Designation</dt><dd><?= $esc($card['designation']) ?></dd></div>
            <?php endif; ?>
            <div><dt>Blood Group</dt><dd><?= $esc($card['blood_group']) ?></dd></div>
            <div><dt>Contact</dt><dd><?= $esc($card['contact_number']) ?></dd></div>
        </dl>
        <div class="id-card-validity">
            <div class="id-card-validity-item"><span class="id-card-validity-label">Issue Date</span><span data-id-card-issue><?= $esc($card['issue_display']) ?></span></div>
            <div class="id-card-validity-item"><span class="id-card-validity-label">Valid Until</span><span data-id-card-valid-until><?= $esc($card['valid_until_display']) ?></span></div>
        </div>
    </div>
    <footer class="id-card-footer"><span class="id-card-footer-note">This card remains the property of RTTC.</span><span class="id-card-signature">Authority Signature</span></footer>
</section>

<section class="id-card-canvas id-card-back" id="id-card-back" style="--id-card-watermark: url('<?= $esc($logo) ?>');" aria-label="ID card back">
    <header class="id-card-back-header"><h2 class="id-card-back-title">Important Instructions</h2><span class="id-card-back-subtitle">Please carry this card while on campus</span></header>
    <img class="id-card-watermark" src="<?= $esc($logo) ?>" alt="" aria-hidden="true">
    <ol class="id-card-rules">
        <li>This card is valid only for the holder named on the front.</li>
        <li>Produce it whenever requested by college authorities.</li>
        <li>Loss of this card must be reported to the college immediately.</li>
        <li>This card is non-transferable and misuse may lead to cancellation.</li>
        <li>Return the card to the college on completion or discontinuation.</li>
    </ol>
    <div class="id-card-authority-zones" aria-label="Authority completion areas">
        <div class="id-card-signature-zone" data-label="Authority Signature"></div>
        <div class="id-card-stamp-zone" data-label="Official Stamp"></div>
        <div class="id-card-principal-zone" data-label="Principal / Authorized Signatory"></div>
    </div>
</section>
