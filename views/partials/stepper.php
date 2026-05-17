<?php
/**
 * RTTC 2026 - Registration Stepper Component
 * $currentStep: 0=not started, 1=personal, 2=academic, 3=documents, 4=payment
 */
$currentStep = $currentStep ?? SessionHelper::getProgress();
$steps = [
    1 => ['label' => 'Personal',  'icon' => 'bi-person-fill'],
    2 => ['label' => 'Academic',  'icon' => 'bi-mortarboard-fill'],
    3 => ['label' => 'Documents', 'icon' => 'bi-folder2-open'],
    4 => ['label' => 'Payment',   'icon' => 'bi-credit-card-fill'],
];
?>
<div class="rttc-stepper" aria-label="Registration Progress">
  <?php foreach ($steps as $num => $step):
    $isDone   = $currentStep >= $num;
    $isActive = $currentStep === ($num - 1); // about to do this step (0-indexed logic)
    // Revised: step is active if it equals the NEXT step to complete
    $isActive = ($currentStep + 1) === $num && $currentStep < 4;
    $isDone   = $currentStep >= $num;
    $cls = $isDone ? 'completed' : ($isActive ? 'active' : '');
  ?>
  <div class="step <?= $cls ?>">
    <div class="step-circle">
      <?php if ($isDone): ?>
        <i class="bi bi-check-lg"></i>
      <?php else: ?>
        <i class="<?= $step['icon'] ?>"></i>
      <?php endif; ?>
    </div>
    <div class="step-label"><?= $step['label'] ?></div>
  </div>
  <?php endforeach; ?>
</div>
