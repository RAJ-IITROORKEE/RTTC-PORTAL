<?php
/** Flash message display partial */
$types = ['success', 'error', 'warning', 'info'];
foreach ($types as $type) {
    $msg = SessionHelper::getFlash($type);
    if ($msg) {
        $bsType = $type === 'error' ? 'danger' : $type;
        $icon   = match($type) {
            'success' => 'bi-check-circle-fill',
            'error'   => 'bi-x-circle-fill',
            'warning' => 'bi-exclamation-triangle-fill',
            default   => 'bi-info-circle-fill',
        };
        echo <<<HTML
        <div class="container mt-2">
          <div class="alert alert-{$bsType} alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
            <i class="bi {$icon}"></i>
            <span>{$msg}</span>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
          </div>
        </div>
        HTML;
    }
}
