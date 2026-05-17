<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';
SecurityHelper::requireAdminAuth();
SessionHelper::destroyAdmin();
redirect('admin.login');
