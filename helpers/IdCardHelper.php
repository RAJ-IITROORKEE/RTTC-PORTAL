<?php
/**
 * ID card domain, validation, photo, and throttling operations.
 */
if (!defined('APP_INIT')) die('Direct access not permitted');

class IdCardHelper
{
    public const TYPE_STUDENT = 'student';
    public const TYPE_FACULTY_STAFF = 'faculty_staff';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DONE = 'done';

    private const MAX_NAME_LENGTH = 80;
    private const MAX_CARE_OF_LENGTH = 80;
    private const MAX_COURSE_LENGTH = 40;
    private const MAX_SESSION_LENGTH = 9;
    private const MAX_ROLL_LENGTH = 30;
    private const MAX_DEPARTMENT_LENGTH = 70;
    private const MAX_DESIGNATION_LENGTH = 70;
    private const MAX_ADDRESS_LENGTH = 220;

    public static function bloodGroups(): array
    {
        return ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    }

    public static function applicationTypes(): array
    {
        return [
            self::TYPE_STUDENT => 'Student',
            self::TYPE_FACULTY_STAFF => 'Faculty/Staff',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_DONE => 'Done',
        ];
    }

    public static function isValidType(string $type): bool
    {
        return array_key_exists($type, self::applicationTypes());
    }

    public static function submissionToken(string $type): string
    {
        if (!self::isValidType($type)) {
            throw new InvalidArgumentException('Invalid ID card application type.');
        }
        $key = 'id_card_submission_token_' . $type;
        if (empty($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
            $_SESSION['id_card_submission_started_' . $type] = time();
        }
        return (string) $_SESSION[$key];
    }

    public static function submissionStartedAt(string $type): int
    {
        return (int) ($_SESSION['id_card_submission_started_' . $type] ?? 0);
    }

    public static function clearSubmissionToken(string $type): void
    {
        unset($_SESSION['id_card_submission_token_' . $type], $_SESSION['id_card_submission_started_' . $type]);
    }

    public static function formatReference(string $type, int $id): string
    {
        $prefix = $type === self::TYPE_FACULTY_STAFF ? 'IDC-F-' : 'IDC-S-';
        return $prefix . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    public static function normalizeText(mixed $value): string
    {
        $value = trim((string) $value);
        return preg_replace('/\s+/u', ' ', $value) ?? '';
    }

    public static function normalizeAddress(mixed $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", trim((string) $value));
        $value = preg_replace('/[ \t]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\n{2,}/', "\n", $value) ?? '';
        return trim($value);
    }

    public static function normalizePhone(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        if (str_starts_with($digits, '91') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }
        return $digits;
    }

    /**
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    public static function validateApplication(string $type, array $input): array
    {
        $errors = [];
        $data = [
            'full_name' => self::normalizeText($input['full_name'] ?? ''),
            'care_of' => self::normalizeText($input['care_of'] ?? ''),
            'course' => null,
            'academic_session' => null,
            'roll_number' => null,
            'date_of_birth' => null,
            'department' => null,
            'designation' => null,
            'blood_group' => self::normalizeText($input['blood_group'] ?? ''),
            'contact_number' => self::normalizePhone($input['contact_number'] ?? ''),
            'address' => self::normalizeAddress($input['address'] ?? ''),
            'declaration' => isset($input['declaration']) && (string) $input['declaration'] === '1',
        ];

        if (!self::isValidType($type)) {
            return ['data' => $data, 'errors' => ['application_type' => 'Invalid application type.']];
        }

        self::validateText($data['full_name'], 'full_name', 'Name', 2, self::MAX_NAME_LENGTH, $errors);
        self::validateText($data['care_of'], 'care_of', 'C/O', 2, self::MAX_CARE_OF_LENGTH, $errors);
        self::validateText($data['address'], 'address', 'Address', 5, self::MAX_ADDRESS_LENGTH, $errors);

        if (!in_array($data['blood_group'], self::bloodGroups(), true)) {
            $errors['blood_group'] = 'Select a valid blood group.';
        }
        if (!preg_match('/^[6-9][0-9]{9}$/', $data['contact_number'])) {
            $errors['contact_number'] = 'Enter a valid 10-digit Indian mobile number.';
        }
        if (!$data['declaration']) {
            $errors['declaration'] = 'You must confirm that the submitted information is correct.';
        }

        if ($type === self::TYPE_STUDENT) {
            $data['course'] = self::normalizeText($input['course'] ?? '');
            $data['academic_session'] = self::normalizeText($input['academic_session'] ?? '');
            $data['roll_number'] = self::normalizeText($input['roll_number'] ?? '');
            $data['date_of_birth'] = trim((string) ($input['date_of_birth'] ?? ''));

            self::validateText($data['course'], 'course', 'Course', 2, self::MAX_COURSE_LENGTH, $errors);
            self::validateText($data['roll_number'], 'roll_number', 'Roll number', 1, self::MAX_ROLL_LENGTH, $errors);
            if (!preg_match('/^\d{4}-\d{2}$/', $data['academic_session'])) {
                $errors['academic_session'] = 'Enter the session in YYYY-YY format.';
            }
            if (self::textLength($data['academic_session']) > self::MAX_SESSION_LENGTH) {
                $errors['academic_session'] = 'Session is too long.';
            }
            if (!self::isPastDate($data['date_of_birth'])) {
                $errors['date_of_birth'] = 'Enter a valid date of birth before today.';
            }
        } else {
            $data['department'] = self::normalizeText($input['department'] ?? '');
            $data['designation'] = self::normalizeText($input['designation'] ?? '');
            self::validateText($data['department'], 'department', 'Department', 2, self::MAX_DEPARTMENT_LENGTH, $errors);
            self::validateText($data['designation'], 'designation', 'Designation', 2, self::MAX_DESIGNATION_LENGTH, $errors);
        }

        return ['data' => $data, 'errors' => $errors];
    }

    public static function canTransition(string $from, string $to): bool
    {
        return ($from === self::STATUS_PENDING && $to === self::STATUS_APPROVED)
            || ($from === self::STATUS_APPROVED && $to === self::STATUS_DONE)
            || ($from === self::STATUS_DONE && $to === self::STATUS_DONE);
    }

    public static function canManageRole(mixed $role): bool
    {
        return in_array($role, ['admin', 'super_admin'], true);
    }

    public static function approvalDates(string $approvedAt): array
    {
        $timezone = new DateTimeZone('Asia/Kolkata');
        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $approvedAt, $timezone);
        if (!$date) {
            $date = new DateTimeImmutable($approvedAt, $timezone);
        }
        $validUntil = $date->modify('+1 year');
        return [
            'issue_date' => $date->format('Y-m-d'),
            'valid_until' => $validUntil->format('Y-m-d'),
            'issue_display' => $date->format('d M Y'),
            'valid_until_display' => $validUntil->format('d M Y'),
        ];
    }

    public static function templateData(array $application): array
    {
        $approvedAt = (string) ($application['approved_at'] ?? '');
        $dates = $approvedAt !== '' ? self::approvalDates($approvedAt) : [
            'issue_date' => '',
            'valid_until' => '',
            'issue_display' => 'Pending approval',
            'valid_until_display' => 'Pending approval',
        ];
        $application['reference'] = self::formatReference((string) $application['application_type'], (int) $application['id']);
        $application['type_label'] = self::applicationTypes()[$application['application_type']] ?? 'ID Card';
        $application['photo_url'] = route('api.admin.id-card-photo', ['id' => (int) $application['id']]);
        return array_merge($application, $dates);
    }

    /**
     * @return array{success: bool, path: string, message: string}
     */
    public static function validateAndStorePhoto(string $fileKey, string $applicationType): array
    {
        if (!isset($_FILES[$fileKey]) || !is_array($_FILES[$fileKey])) {
            return self::photoError('A photo is required.');
        }

        $file = $_FILES[$fileKey];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return self::photoError('Photo upload failed. Please try again.');
        }
        if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > ID_CARD_MAX_PHOTO_SIZE) {
            return self::photoError('Photo must be smaller than 2 MB.');
        }
        if (!function_exists('finfo_open') || !function_exists('getimagesize') || !function_exists('imagejpeg')) {
            return self::photoError('The server is not configured to process ID card photos.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
        if ($finfo) finfo_close($finfo);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return self::photoError('Upload a valid JPEG or PNG photo.');
        }

        $imageInfo = @getimagesize($file['tmp_name']);
        if (!is_array($imageInfo) || !isset($imageInfo[0], $imageInfo[1], $imageInfo[2])) {
            return self::photoError('The uploaded file is not a valid image.');
        }
        $width = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];
        $imageMime = image_type_to_mime_type((int) $imageInfo[2]);
        if ($imageMime !== $mime) return self::photoError('The uploaded file is not a valid JPEG or PNG image.');
        if ($width > ID_CARD_MAX_PHOTO_WIDTH || $height > ID_CARD_MAX_PHOTO_HEIGHT || ($width * $height) > ID_CARD_MAX_PHOTO_PIXELS) {
            return self::photoError('The uploaded image is too large to process safely. Choose a smaller image.');
        }

        $source = $mime === 'image/jpeg'
            ? @imagecreatefromjpeg($file['tmp_name'])
            : @imagecreatefrompng($file['tmp_name']);
        if (!$source) {
            return self::photoError('The uploaded image could not be processed.');
        }
        $source = self::correctJpegOrientation($source, $file['tmp_name'], $mime);

        if (!is_dir(ID_CARD_UPLOAD_DIR) && !mkdir(ID_CARD_UPLOAD_DIR, 0755, true) && !is_dir(ID_CARD_UPLOAD_DIR)) {
            imagedestroy($source);
            return self::photoError('Photo storage is unavailable. Please try again later.');
        }

        $filename = 'idc_' . ($applicationType === self::TYPE_FACULTY_STAFF ? 'f' : 's') . '_' . bin2hex(random_bytes(16)) . '.jpg';
        $absolutePath = rtrim(ID_CARD_UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $filename;
        $saved = imagejpeg($source, $absolutePath, 90);
        imagedestroy($source);
        if (!$saved) {
            return self::photoError('Photo storage failed. Please try again.');
        }
        @chmod($absolutePath, 0640);

        return ['success' => true, 'path' => 'id_cards/' . $filename, 'message' => ''];
    }

    public static function resolvePhotoPath(string $relativePath): ?string
    {
        if (!preg_match('#^id_cards/[a-z0-9_-]+\.jpg$#i', $relativePath)) {
            return null;
        }
        $base = realpath(ID_CARD_UPLOAD_DIR);
        $path = realpath(rtrim(ID_CARD_UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . basename($relativePath));
        if ($base === false || $path === false || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)) {
            return null;
        }
        return $path;
    }

    public static function deleteStoredPhoto(string $relativePath): bool
    {
        $path = self::resolvePhotoPath($relativePath);
        return $path === null || @unlink($path);
    }

    /**
     * @return array{allowed: bool, ip_key: string, count: int}
     */
    public static function recordSubmissionAttempt(mysqli $db): array
    {
        if (ID_CARD_IP_HMAC_SECRET === '') {
            throw new RuntimeException('ID card rate-limit secret is not configured.');
        }
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = 'unknown';
        }
        $ipKey = hash_hmac('sha256', $ip, ID_CARD_IP_HMAC_SECRET);
        $bucketSeconds = ID_CARD_RATE_LIMIT_BUCKET_SECONDS;
        $bucketTimestamp = (int) (floor(time() / $bucketSeconds) * $bucketSeconds);
        $bucketStart = date('Y-m-d H:i:s', $bucketTimestamp);
        $now = date('Y-m-d H:i:s');

        $stmt = $db->prepare("INSERT INTO id_card_submission_attempts (ip_key, bucket_start, attempt_count, updated_at)
            VALUES (?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE attempt_count = attempt_count + 1, updated_at = VALUES(updated_at)");
        if (!$stmt) throw new RuntimeException('Could not prepare ID card throttling.');
        $stmt->bind_param('sss', $ipKey, $bucketStart, $now);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Could not record ID card throttling.');
        }
        $stmt->close();

        $countStmt = $db->prepare('SELECT attempt_count FROM id_card_submission_attempts WHERE ip_key = ? AND bucket_start = ?');
        if (!$countStmt) throw new RuntimeException('Could not verify ID card throttling.');
        $countStmt->bind_param('ss', $ipKey, $bucketStart);
        $countStmt->execute();
        $count = (int) (($countStmt->get_result()->fetch_assoc()['attempt_count'] ?? 0));
        $countStmt->close();

        if (random_int(1, 100) === 1) {
            $expiry = date('Y-m-d H:i:s', $bucketTimestamp - (ID_CARD_RATE_LIMIT_BUCKET_SECONDS * 8));
            $cleanupStmt = $db->prepare('DELETE FROM id_card_submission_attempts WHERE bucket_start < ? LIMIT 100');
            if ($cleanupStmt) {
                $cleanupStmt->bind_param('s', $expiry);
                $cleanupStmt->execute();
                $cleanupStmt->close();
            }
        }

        return [
            'allowed' => $count <= ID_CARD_RATE_LIMIT_MAX_ATTEMPTS,
            'ip_key' => $ipKey,
            'count' => $count,
        ];
    }

    private static function validateText(string $value, string $field, string $label, int $min, int $max, array &$errors): void
    {
        $length = self::textLength($value);
        if ($length < $min) {
            $errors[$field] = $label . ' is required.';
        } elseif ($length > $max) {
            $errors[$field] = $label . ' is too long for the printed card.';
        }
    }

    private static function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private static function isPastDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Kolkata'));
        $dateErrors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
            return false;
        }
        return $date->format('Y-m-d') === $value && $date < new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata'));
    }

    private static function correctJpegOrientation(mixed $image, string $path, string $mime): mixed
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) return $image;
        $rotated = imagerotate($image, $angle, 0);
        if ($rotated !== false) {
            imagedestroy($image);
            return $rotated;
        }
        return $image;
    }

    private static function photoError(string $message): array
    {
        return ['success' => false, 'path' => '', 'message' => $message];
    }
}
