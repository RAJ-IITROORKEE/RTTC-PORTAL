<?php
/**
 * RTTC 2026 - Site Settings Helper
 */
if (!defined('APP_INIT')) die('Direct access not permitted');

class SiteSettingsHelper
{
    private const DEFAULT_MARQUEE = [
        [
            'content' => 'Welcome to the Official Admission Portal for the B.Ed. First Year (2025-26) of Rangia Teacher Training College',
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
    ];

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
