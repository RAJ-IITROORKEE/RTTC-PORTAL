<?php
/**
 * RTTC 2026 - Application helper functions.
 */
if (!defined('APP_INIT')) die('Direct access not permitted');

function canDownloadApplicationForm(array $student): bool
{
    return (int)($student['current_step'] ?? 0) >= 4
        && !empty($student['is_submitted'])
        && !empty($student['razorpay_payment_id']);
}
