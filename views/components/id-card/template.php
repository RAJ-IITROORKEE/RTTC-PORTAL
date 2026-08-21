<?php
if (!defined('APP_INIT') || !isset($card) || !is_array($card)) {
    http_response_code(404);
    exit;
}

$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$isStudent = ($card['application_type'] ?? '') === IdCardHelper::TYPE_STUDENT;
$logo = BASE_URL . '/assets/img/RTTC_logo_blue.png';
$dateOfBirth = !empty($card['date_of_birth']) ? date('d-m-Y', strtotime((string) $card['date_of_birth'])) : '';
$holderDescription = $isStudent ? 'student-teacher' : 'faculty/staff member';
$displayAddress = preg_replace('/\s+/u', ' ', trim((string) ($card['address'] ?? ''))) ?? '';
?>
<div class="id-card-preview-stage">
    <section class="id-card-sheet" id="id-card-sheet" aria-label="Complete ID card">
        <section class="id-card-information" aria-label="Identity card front">
            <header class="id-card-letterhead">
                <div class="id-card-letterhead-logo-wrap">
                    <img class="id-card-letterhead-logo" src="<?= $esc($logo) ?>" alt="RTTC logo">
                </div>
                <div class="id-card-letterhead-copy">
                    <div class="id-card-assamese" lang="as">ৰঙিয়া শিক্ষক প্ৰশিক্ষণ মহাবিদ্যালয়</div>
                    <div class="id-card-college-name">Rangia Teacher Training College</div>
                    <div class="id-card-recognition">Recognized by NCTE, affiliated to Gauhati University and conveyed concurrence by Govt. of Assam</div>
                </div>
            </header>

            <div class="id-card-accent-line" aria-hidden="true"></div>

            <div class="id-card-information-content">
                <div class="id-card-heading-row">
                    <h2><?= $isStudent ? 'Identity Card' : 'Faculty / Staff Identity Card' ?></h2>
                    <span><?= $esc($card['reference']) ?></span>
                </div>

                <div class="id-card-person-block">
                    <img class="id-card-photo" src="<?= $esc($card['photo_url']) ?>" alt="<?= $esc($card['full_name']) ?> photo">
                    <dl class="id-card-details">
                        <div><dt>Name</dt><dd><?= $esc($card['full_name']) ?></dd></div>
                        <div><dt>C/O</dt><dd><?= $esc($card['care_of']) ?></dd></div>
                        <?php if ($isStudent): ?>
                            <div><dt>Course</dt><dd><?= $esc($card['course']) ?></dd></div>
                            <div><dt>Session</dt><dd><?= $esc($card['academic_session']) ?></dd></div>
                            <div><dt>Roll No.</dt><dd><?= $esc($card['roll_number']) ?></dd></div>
                            <div><dt>Date of Birth</dt><dd><?= $esc($dateOfBirth) ?></dd></div>
                        <?php else: ?>
                            <div><dt>Department</dt><dd><?= $esc($card['department']) ?></dd></div>
                            <div><dt>Designation</dt><dd><?= $esc($card['designation']) ?></dd></div>
                        <?php endif; ?>
                        <div><dt>Contact No.</dt><dd><?= $esc($card['contact_number']) ?></dd></div>
                        <div><dt>Blood Group</dt><dd><?= $esc($card['blood_group']) ?></dd></div>
                    </dl>
                </div>

                <div class="id-card-address"><strong>Address :</strong><span><?= $esc($displayAddress) ?></span></div>

                <div class="id-card-dates">
                    <div><strong>Date of Issue:</strong><span data-id-card-issue><?= $esc($card['issue_display']) ?></span></div>
                    <div><strong>Valid up to:</strong><span data-id-card-valid-until><?= $esc($card['valid_until_display']) ?></span></div>
                </div>

                <div class="id-card-authority-zones" aria-label="Blank authority completion areas">
                    <div><span><?= $isStudent ? "Student's Signature" : "Holder's Signature" ?></span></div>
                    <div><span>Official Stamp</span></div>
                    <div><span>Principal</span></div>
                </div>
            </div>
        </section>

        <div class="id-card-divider" aria-hidden="true"></div>

        <section class="id-card-instructions" aria-label="Identity card back instructions">
            <img class="id-card-instruction-watermark" src="<?= $esc($logo) ?>" alt="" aria-hidden="true">
            <h2>Instructions</h2>
            <ul>
                <li>The signatory of this card is a <?= $esc($holderDescription) ?> of this college.</li>
                <li>The card holder must follow the rules and regulations of this institution.</li>
                <li>The card may be produced before any concerned authority for identifying the holder on demand.</li>
                <li>Please keep this card in safe custody. If the card is lost or damaged, the college authority will not be held responsible for any misuse of this card. Inform the college immediately if the card is lost or damaged.</li>
                <li>A charge of Rs. 100/- (one hundred) will be required to issue a duplicate card.</li>
                <li>The card holder must wear the card on his/her neck.</li>
            </ul>
        </section>
    </section>
</div>
