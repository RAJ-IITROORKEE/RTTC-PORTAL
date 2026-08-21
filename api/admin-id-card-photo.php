<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';

if (!SessionHelper::isAdminLoggedIn()) { http_response_code(401); exit('Admin authentication is required.'); }
if (!IdCardHelper::canManageRole(SessionHelper::get('admin_role'))) { http_response_code(403); exit('Your role cannot view ID card photos.'); }
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Invalid ID card application.'); }
$stmt = db()->prepare('SELECT photo_path FROM id_card_applications WHERE id = ? LIMIT 1');
if (!$stmt) { http_response_code(500); exit; }
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$path = $row ? IdCardHelper::resolvePhotoPath((string) $row['photo_path']) : null;
if ($path === null) { http_response_code(404); exit('Photo not found.'); }
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
