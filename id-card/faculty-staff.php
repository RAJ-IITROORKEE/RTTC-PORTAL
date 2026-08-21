<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';

$applicationType = IdCardHelper::TYPE_FACULTY_STAFF;
$formRoute = 'id-card.faculty-staff';
require __DIR__ . '/form.php';
