<?php
/**
 * RTTC 2026 - Security Helper
 */
if (!defined('APP_INIT')) die('Direct access not permitted');

class SecurityHelper
{
    // ── CSRF ────────────────────────────────────────────────
    public static function generateCsrfToken(): string
    {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    /** Alias for generateCsrfToken() */
    public static function generateCsrf(): string
    {
        return self::generateCsrfToken();
    }

    public static function validateCsrfToken(string $token): bool
    {
        return isset($_SESSION[CSRF_TOKEN_NAME])
            && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }

    /**
     * Verify CSRF from POST; redirect back with error on failure.
     */
    public static function verifyCsrf(): void
    {
        $token = $_POST[CSRF_TOKEN_NAME] ?? '';
        if (!self::validateCsrfToken($token)) {
            http_response_code(403);
            die('Invalid CSRF token. Please go back and try again.');
        }
    }

    public static function csrfField(): string
    {
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
    }

    // ── Input Sanitisation ──────────────────────────────────
    public static function sanitize(mixed $data): mixed
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
    }

    // ── Password ────────────────────────────────────────────
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    // ── Auth Gate ────────────────────────────────────────────
    public static function requireAuth(): void
    {
        if (!SessionHelper::isLoggedIn()) {
            SessionHelper::setFlash('error', 'Please login to continue.');
            header('Location: ' . route('login'));
            exit;
        }
    }

    public static function requireAdminAuth(): void
    {
        if (!SessionHelper::isAdminLoggedIn()) {
            header('Location: ' . route('admin.login'));
            exit;
        }
    }

    public static function requireGuest(): void
    {
        if (SessionHelper::isLoggedIn()) {
            header('Location: ' . route('welcome'));
            exit;
        }
    }

    /**
     * Validate and move an uploaded file.
     *
     * @param string $fileKey   Key in $_FILES (e.g. 'photo')
     * @param int    $userId    User ID used as filename prefix
     * @param string $fieldName Document field name used as filename prefix
     * @return array  ['success' => bool, 'path' => string, 'message' => string]
     */
    public static function saveUpload(string $fileKey, int $userId, string $fieldName): array
    {
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'path' => '', 'message' => 'File upload error.'];
        }
        $file = $_FILES[$fileKey];

        if ($file['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'path' => '', 'message' => 'File size exceeds 5 MB limit.'];
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_FILE_TYPES, true)) {
            return ['success' => false, 'path' => '', 'message' => 'Invalid file type. Allowed: ' . implode(', ', ALLOWED_FILE_TYPES)];
        }

        $filename = 'u' . $userId . '_' . $fieldName . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest     = UPLOAD_DIR . $filename;

        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            return ['success' => true, 'path' => 'storage/uploads/documents/' . $filename, 'message' => ''];
        }
        return ['success' => false, 'path' => '', 'message' => 'Failed to save file. Please try again.'];
    }

    /**
     * Legacy: validate upload array without saving (kept for compatibility).
     */
    public static function validateUpload(array $file): array
    {
        $errors = [];
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['File not uploaded.'];
        }
        if ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = 'File size exceeds limit (max 5 MB).';
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_FILE_TYPES, true)) {
            $errors[] = 'Invalid file type. Allowed: ' . implode(', ', ALLOWED_FILE_TYPES);
        }
        return $errors;
    }

}
