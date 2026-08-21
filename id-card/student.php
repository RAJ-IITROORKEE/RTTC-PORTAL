<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';

$applicationType = IdCardHelper::TYPE_STUDENT;
$formRoute = 'id-card.student';
require __DIR__ . '/form.php';
