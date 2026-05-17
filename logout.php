<?php
define('APP_INIT', true);
require_once __DIR__ . '/config/init.php';

// Destroy session cleanly
SessionHelper::destroyUser();

redirect(route('login'));
