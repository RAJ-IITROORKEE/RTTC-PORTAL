-- ============================================================
-- RTTC 2026 - Unpaid reminder email + registration timer
-- Safe incremental migration for an existing live database.
-- Run after the main RTTC schema has been installed.
-- ============================================================

USE `rangiatt_2026`;

-- Global registration timer. Empty value means no active/scheduled deadline.
INSERT INTO `site_settings` (`setting_key`, `setting_value`)
VALUES ('registration_closes_at', '')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- One row per applicant. Only status = 'sent' is displayed as Sent;
-- failed/sending rows remain retryable as Not Sent.
CREATE TABLE IF NOT EXISTS `unpaid_email_log` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11)         NOT NULL,
  `email`       VARCHAR(191)    NOT NULL,
  `status`      ENUM('sending','sent','failed') NOT NULL DEFAULT 'failed',
  `attempts`    INT(11)         NOT NULL DEFAULT 0,
  `last_error`  VARCHAR(500)             DEFAULT NULL,
  `sent_at`     DATETIME                 DEFAULT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_unpaid_email_user` (`user_id`),
  KEY `idx_unpaid_email_status` (`status`),
  KEY `idx_unpaid_email_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
