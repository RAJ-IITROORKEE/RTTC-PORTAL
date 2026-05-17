<?php
/**
 * RTTC 2026 - Bootstrap / Init
 * Include this at the top of every page.
 */
if (!defined('APP_INIT')) define('APP_INIT', true);
define('ROOT_PATH', __DIR__ . '/..');
define('BASE_PATH', __DIR__ . '/..');

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/SecurityHelper.php';
require_once ROOT_PATH . '/helpers/SessionHelper.php';
require_once ROOT_PATH . '/helpers/ValidationHelper.php';
require_once ROOT_PATH . '/helpers/RouteHelper.php';
require_once ROOT_PATH . '/helpers/OTPHelper.php';
require_once ROOT_PATH . '/helpers/SiteSettingsHelper.php';

// Start / resume secure session
SessionHelper::start();

// Make $conn available globally (backward-compat)
$conn = db();
