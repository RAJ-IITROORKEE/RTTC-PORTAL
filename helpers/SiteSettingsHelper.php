<?php
/**
 * RTTC 2026 - Site Settings Helper
 */
if (!defined('APP_INIT')) die('Direct access not permitted');

class SiteSettingsHelper
{
    private const DEFAULT_MARQUEE = [
        [
            'content' => 'Welcome to the Official Admission Portal for B.Ed admission 2026-27 of Rangia Teacher Training College',
            'link_url' => '',
            'link_label' => 'Click Here',
        ],
        [
            'content' => 'Registration fee of Rs 500 is required after form submission. Applications without payment will be rejected.',
            'link_url' => '',
            'link_label' => 'Click Here',
        ],
        [
            'content' => 'While making payment, please use only your registered phone number.',
            'link_url' => '',
            'link_label' => 'Click Here',
        ],
    ];

    private const DEFAULT_NOTICE_DOCS = [
        'terms_conditions' => [
            'doc_key' => 'terms_conditions',
            'title' => 'Terms & Conditions',
            'button_label' => 'Terms & Conditions',
            'file_path' => 'assets/docs/terms_and_condition_2026.pdf',
            'link_url' => '',
        ],
        'instructions' => [
            'doc_key' => 'instructions',
            'title' => 'View Instructions',
            'button_label' => 'View Instructions',
            'file_path' => 'assets/docs/instructions.pdf',
            'link_url' => '',
        ],
        'required_documents' => [
            'doc_key' => 'required_documents',
            'title' => 'Required Documents',
            'button_label' => 'Required Documents',
            'file_path' => 'assets/docs/required_documents.pdf',
            'link_url' => '',
        ],
        'prospectus' => [
            'doc_key' => 'prospectus',
            'title' => 'Prospectus',
            'button_label' => 'Prospectus',
            'file_path' => 'assets/RTTC Prospectus 2026-27-2.pdf',
            'link_url' => '',
        ],
    ];

    public static function isRegistrationOpen(): bool
    {
        if (self::getSetting('registration_open', '1') !== '1') {
            return false;
        }

        $deadline = self::getRegistrationDeadline();
        return $deadline === null || strtotime($deadline) > time();
    }

    public static function setRegistrationOpen(bool $isOpen): bool
    {
        $db = db();
        $value = $isOpen ? '1' : '0';
        if (!$isOpen) {
            return self::saveSetting('registration_open', $value)
                && self::saveSetting('registration_closes_at', '');
        }

        return self::saveSetting('registration_open', $value)
            && self::saveSetting('registration_closes_at', '');
    }

    /**
     * Start a global registration countdown from the current server time.
     */
    public static function startRegistrationTimer(int $days, int $hours): bool
    {
        $days = max(0, $days);
        $hours = max(0, min(23, $hours));
        $totalHours = ($days * 24) + $hours;
        if ($totalHours < 1) {
            return false;
        }

        $deadline = (new DateTimeImmutable('now'))->modify('+' . $totalHours . ' hours')->format('Y-m-d H:i:s');
        return self::saveSetting('registration_open', '1')
            && self::saveSetting('registration_closes_at', $deadline);
    }

    public static function getRegistrationDeadline(): ?string
    {
        $value = trim(self::getSetting('registration_closes_at', ''));
        if ($value === '' || strtotime($value) === false) {
            return null;
        }
        return date('Y-m-d H:i:s', strtotime($value));
    }

    public static function isRegistrationTimerActive(): bool
    {
        $deadline = self::getRegistrationDeadline();
        return self::getSetting('registration_open', '1') === '1'
            && $deadline !== null
            && strtotime($deadline) > time();
    }

    private static function saveSetting(string $key, string $value): bool
    {
        $db = db();
        $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value)
                              VALUES (?, ?)
                              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ss', $key, $value);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    private static function getSetting(string $key, string $default): string
    {
        $db = db();
        $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1");
        if (!$stmt) {
            return $default;
        }
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return isset($row['setting_value']) ? (string)$row['setting_value'] : $default;
    }

    public static function getMarqueeItems(): array
    {
        $db = db();
        $sql = "SELECT id, content, link_url, link_label, sort_order
                FROM home_marquee_items
                WHERE is_active = 1
                ORDER BY sort_order ASC, id ASC";
        $result = $db->query($sql);

        if (!$result) {
            return self::DEFAULT_MARQUEE;
        }

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = [
                'content' => trim((string)($row['content'] ?? '')),
                'link_url' => trim((string)($row['link_url'] ?? '')),
                'link_label' => trim((string)($row['link_label'] ?? '')) ?: 'Click Here',
            ];
        }

        return $items ?: self::DEFAULT_MARQUEE;
    }

    public static function getNoticeDocuments(bool $activeOnly = true): array
    {
        $db = db();
        $where = $activeOnly ? 'WHERE is_active = 1' : '';
        $sql = "SELECT id, doc_key, title, button_label, file_path, link_url, is_active, sort_order
                FROM notice_documents
                $where
                ORDER BY sort_order ASC, id ASC";

        $result = $db->query($sql);
        if (!$result) {
            return array_values(self::DEFAULT_NOTICE_DOCS);
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        if (empty($rows)) {
            return array_values(self::DEFAULT_NOTICE_DOCS);
        }

        return $rows;
    }

    public static function getNoticeDocumentByKey(string $docKey): ?array
    {
        $docKey = trim($docKey);
        if ($docKey === '') {
            return null;
        }

        $db = db();
        $stmt = $db->prepare("SELECT id, doc_key, title, button_label, file_path, link_url, is_active, sort_order
                              FROM notice_documents
                              WHERE doc_key = ? AND is_active = 1
                              LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $docKey);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return $row;
            }
        }

        return self::DEFAULT_NOTICE_DOCS[$docKey] ?? null;
    }

    public static function getDocumentUrl(?array $doc): string
    {
        if (empty($doc)) {
            return '';
        }

        $link = trim((string)($doc['link_url'] ?? ''));
        if ($link !== '') {
            if (str_starts_with($link, 'http://') || str_starts_with($link, 'https://')) {
                return $link;
            }
            return BASE_URL . '/' . ltrim($link, '/');
        }

        $path = trim((string)($doc['file_path'] ?? ''));
        if ($path === '') {
            return '';
        }

        return BASE_URL . '/' . ltrim($path, '/');
    }
}
