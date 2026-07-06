<?php
/**
 * RTTC 2026 - Application Configuration
 */
if (!defined('APP_INIT')) die('Direct access not permitted');

require_once __DIR__ . '/../helpers/EnvHelper.php';
EnvHelper::load(__DIR__ . '/../.env');

// ── Application ──────────────────────────────────────────────
define('APP_NAME',    EnvHelper::get('APP_NAME',  'RTTC 2026 Registration Portal'));
define('APP_ENV',     EnvHelper::get('APP_ENV',   'development'));
define('APP_URL',     EnvHelper::get('APP_URL',   'http://localhost/mycode/PROJECTS/RTTC_2026/'));
define('APP_VERSION', '2026.1.0');
define('BASE_URL',    rtrim(APP_URL, '/'));
define('YEAR_LABEL',  '2026-2027');

// ── Database ─────────────────────────────────────────────────
define('DB_HOST',     EnvHelper::get('DB_HOST',     'localhost'));
define('DB_USERNAME', EnvHelper::get('DB_USERNAME', 'root'));
define('DB_PASSWORD', EnvHelper::get('DB_PASSWORD', ''));
define('DB_NAME',     EnvHelper::get('DB_NAME',     'rangiatt_2026'));
define('DB_CHARSET',  EnvHelper::get('DB_CHARSET',  'utf8mb4'));

// ── Security ─────────────────────────────────────────────────
define('SESSION_LIFETIME',  (int) EnvHelper::get('SESSION_LIFETIME', 3600));
define('CSRF_TOKEN_NAME',   EnvHelper::get('CSRF_TOKEN_NAME', 'csrf_token'));
define('PASSWORD_MIN_LENGTH', (int) EnvHelper::get('PASSWORD_MIN_LENGTH', 8));

// ── OTP ──────────────────────────────────────────────────────
define('OTP_EXPIRY', (int) EnvHelper::get('OTP_EXPIRY', 600)); // seconds

// ── Email ────────────────────────────────────────────────────
define('SMTP_HOST',       EnvHelper::get('SMTP_HOST',       'smtp.gmail.com'));
define('SMTP_PORT',       (int) EnvHelper::get('SMTP_PORT', 587));
define('SMTP_ENCRYPTION', EnvHelper::get('SMTP_ENCRYPTION', 'tls'));
define('SMTP_USERNAME',   EnvHelper::get('SMTP_USERNAME',   ''));
define('SMTP_PASSWORD',   EnvHelper::get('SMTP_PASSWORD',   ''));
define('SMTP_FROM_EMAIL', EnvHelper::get('SMTP_FROM_EMAIL', 'admissionrttc@gmail.com'));
define('SMTP_FROM_NAME',  EnvHelper::get('SMTP_FROM_NAME',  'RTTC Admissions'));

// ── Payment ──────────────────────────────────────────────────
define('RAZORPAY_KEY_ID',     EnvHelper::get('RAZORPAY_KEY_ID',     ''));
define('RAZORPAY_KEY_SECRET', EnvHelper::get('RAZORPAY_KEY_SECRET', ''));
define('RAZORPAY_AMOUNT',     (int) EnvHelper::get('RAZORPAY_AMOUNT', 50000)); // paise (₹500)

// ── File Upload ──────────────────────────────────────────────
define('UPLOAD_DIR',         __DIR__ . '/../storage/uploads/documents/');
define('NOTICE_UPLOAD_DIR',  __DIR__ . '/../storage/uploads/notices/');
define('MAX_FILE_SIZE',      (int) EnvHelper::get('MAX_FILE_SIZE', 5242880));
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf']);

// ── Misc ─────────────────────────────────────────────────────
define('RECORDS_PER_PAGE', 15);
define('CONTACT_PHONE',    '+91 03621-359330');
define('CONTACT_EMAIL',    'admissionrttc@gmail.com');
define('COLLEGE_ADDRESS',  'Ward No. 05, Mahendra Das Path, Rangia, Kamrup, Assam, PIN-781354');

// ── Error Reporting ──────────────────────────────────────────
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../storage/logs/error.log');
}

date_default_timezone_set('Asia/Kolkata');
