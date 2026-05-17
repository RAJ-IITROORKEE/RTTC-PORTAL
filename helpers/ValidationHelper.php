<?php
/**
 * RTTC 2026 - Validation Helper
 */
if (!defined('APP_INIT')) die('Direct access not permitted');

class ValidationHelper
{
    public static function validateEmail(string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function validatePhone(string $phone): bool
    {
        return (bool) preg_match('/^\d{10}$/', $phone);
    }

    public static function validatePassword(string $password): bool
    {
        // Min 8 chars, 1 uppercase, 1 lowercase, 1 digit, 1 special char
        return (bool) preg_match(
            '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&\-_#])[A-Za-z\d@$!%*?&\-_#]{8,}$/',
            $password
        );
    }

    public static function validateRequired(mixed $value): bool
    {
        return !empty(trim((string)$value));
    }

    public static function validateDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    public static function validateYear(mixed $year): bool
    {
        $y = (int)$year;
        return $y >= 1990 && $y <= (int)date('Y') + 1;
    }

    public static function validateNumeric(mixed $value, float $min = 0, float $max = PHP_FLOAT_MAX): bool
    {
        if (!is_numeric($value)) return false;
        $v = (float)$value;
        return $v >= $min && $v <= $max;
    }

    /**
     * Returns a keyed array of field => error message, empty if valid.
     */
    public static function validateSignup(string $username, string $email, string $phone, string $password, string $cpassword): array
    {
        $errors = [];
        if (!self::validateRequired($username))  $errors['username']  = 'Name is required.';
        if (!self::validateEmail($email))         $errors['email']     = 'Invalid email address.';
        if (!self::validatePhone($phone))         $errors['phone']     = 'Invalid phone number (10 digits required).';
        if (!self::validatePassword($password))   $errors['password']  = 'Password must be at least 8 characters with uppercase, lowercase, number and special character.';
        elseif ($password !== $cpassword)         $errors['cpassword'] = 'Passwords do not match.';
        return $errors;
    }
}
