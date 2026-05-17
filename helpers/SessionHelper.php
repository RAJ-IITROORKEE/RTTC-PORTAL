<?php
/**
 * RTTC 2026 - Session Helper
 */
if (!defined('APP_INIT')) die('Direct access not permitted');

class SessionHelper
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Lax');
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
        // Regenerate ID every 30 min to prevent session fixation
        if (!isset($_SESSION['_last_regenerated'])) {
            session_regenerate_id(true);
            $_SESSION['_last_regenerated'] = time();
        } elseif (time() - $_SESSION['_last_regenerated'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_last_regenerated'] = time();
        }
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_last_regenerated'] = time();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
    }

    public static function isAdminLoggedIn(): bool
    {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }

    public static function setUserSession(int $userId, string $email, string $phone, string $username): void
    {
        $_SESSION['loggedin']   = true;
        $_SESSION['user_id']    = $userId;
        $_SESSION['email']      = $email;
        $_SESSION['phone']      = $phone;
        $_SESSION['username']   = $username;
    }

    public static function setAdminSession(int $adminId, string $email, string $username, string $role): void
    {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id']        = $adminId;
        $_SESSION['admin_email']     = $email;
        $_SESSION['admin_username']  = $username;
        $_SESSION['admin_role']      = $role;
    }

    public static function destroyUser(): void
    {
        // Keep only admin session if present
        $adminData = [];
        foreach ($_SESSION as $k => $v) {
            if (str_starts_with($k, 'admin_')) $adminData[$k] = $v;
        }
        $_SESSION = $adminData;
    }

    public static function destroyAdmin(): void
    {
        session_unset();
        session_destroy();
    }

    // ── Flash messages ────────────────────────────────────────
    public static function setFlash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type] = $message;
    }

    public static function getFlash(string $type): ?string
    {
        $msg = $_SESSION['_flash'][$type] ?? null;
        unset($_SESSION['_flash'][$type]);
        return $msg;
    }

    public static function hasFlash(string $type): bool
    {
        return isset($_SESSION['_flash'][$type]);
    }

    // ── Progress ──────────────────────────────────────────────
    public static function getProgress(): int
    {
        return (int) ($_SESSION['progress'] ?? 0);
    }

    public static function setProgress(int $step): void
    {
        $_SESSION['progress'] = $step;
    }

    /**
     * Sync progress from DB into session.
     */
    public static function syncProgress(mysqli $conn, int $userId): void
    {
        $stmt = $conn->prepare("SELECT current_step FROM registration_progress WHERE user_id = ?");
        if (!$stmt) return;
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($step);
        if ($stmt->fetch()) {
            $_SESSION['progress'] = (int) $step;
        } else {
            $_SESSION['progress'] = 0;
        }
        $stmt->close();
    }
}
