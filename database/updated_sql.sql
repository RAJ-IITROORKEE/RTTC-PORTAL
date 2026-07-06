-- ============================================================
-- RTTC 2026 - Incremental DB update script for new admin features
-- Safe to run on existing schema (uses IF NOT EXISTS where possible)
-- ============================================================

USE `rangiatt_2026`;

-- 1) Admin-managed home marquee items
CREATE TABLE IF NOT EXISTS `home_marquee_items` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `content`     TEXT         NOT NULL,
  `link_url`    VARCHAR(500)          DEFAULT NULL,
  `link_label`  VARCHAR(100) NOT NULL DEFAULT 'Click Here',
  `sort_order`  INT(11)      NOT NULL DEFAULT 100,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed defaults if empty
INSERT INTO `home_marquee_items` (`content`, `sort_order`, `is_active`)
SELECT seed.`content`, seed.`sort_order`, seed.`is_active`
FROM (
  SELECT 'Welcome to the Official Admission Portal for the B.Ed. First Year (2026-2027) of Rangia Teacher Training College' AS `content`, 10 AS `sort_order`, 1 AS `is_active`
  UNION ALL
  SELECT 'Registration fee of Rs 500 is required after form submission. Applications without payment will be rejected.' AS `content`, 20 AS `sort_order`, 1 AS `is_active`
  UNION ALL
  SELECT 'While making payment, please use only your registered phone number.' AS `content`, 30 AS `sort_order`, 1 AS `is_active`
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `home_marquee_items`);

-- 2) Admin-managed notice documents / important PDFs
CREATE TABLE IF NOT EXISTS `notice_documents` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `doc_key`       VARCHAR(100) NOT NULL,
  `title`         VARCHAR(191) NOT NULL,
  `button_label`  VARCHAR(100) NOT NULL DEFAULT 'View PDF',
  `file_path`     VARCHAR(500)          DEFAULT NULL,
  `link_url`      VARCHAR(500)          DEFAULT NULL,
  `sort_order`    INT(11)      NOT NULL DEFAULT 100,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notice_doc_key` (`doc_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default controlled docs
INSERT INTO `notice_documents` (`doc_key`, `title`, `button_label`, `file_path`, `sort_order`, `is_active`)
SELECT seed.`doc_key`, seed.`title`, seed.`button_label`, seed.`file_path`, seed.`sort_order`, seed.`is_active`
FROM (
  SELECT 'terms_conditions' AS `doc_key`, 'Terms & Conditions' AS `title`, 'Terms & Conditions' AS `button_label`, 'assets/docs/terms_and_condition_2026.pdf' AS `file_path`, 10 AS `sort_order`, 1 AS `is_active`
  UNION ALL
  SELECT 'instructions' AS `doc_key`, 'View Instructions' AS `title`, 'View Instructions' AS `button_label`, 'assets/docs/instructions.pdf' AS `file_path`, 20 AS `sort_order`, 1 AS `is_active`
  UNION ALL
  SELECT 'required_documents' AS `doc_key`, 'Required Documents' AS `title`, 'Required Documents' AS `button_label`, 'assets/docs/required_documents.pdf' AS `file_path`, 30 AS `sort_order`, 1 AS `is_active`
) AS seed
ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `button_label` = VALUES(`button_label`),
  `file_path` = VALUES(`file_path`),
  `sort_order` = VALUES(`sort_order`),
  `is_active` = VALUES(`is_active`);

-- 3) New columns for academic_details: GU registration, migration, GUBEDCET autofill, declaration
ALTER TABLE `academic_details`
  ADD COLUMN IF NOT EXISTS `gu_registered`        VARCHAR(3)   NOT NULL DEFAULT 'no'  COMMENT 'yes/no - already registered in GU',
  ADD COLUMN IF NOT EXISTS `migrated`             VARCHAR(3)   NOT NULL DEFAULT 'no'  COMMENT 'yes/no - migrated from GU',
  ADD COLUMN IF NOT EXISTS `other_university`     VARCHAR(255)          DEFAULT NULL  COMMENT 'Other university name if migrated',
  ADD COLUMN IF NOT EXISTS `gubedcet_name`        VARCHAR(255)          DEFAULT NULL  COMMENT 'Candidate name from GUBEDCET result',
  ADD COLUMN IF NOT EXISTS `gubedcet_category`    VARCHAR(100)          DEFAULT NULL  COMMENT 'Category from GUBEDCET result',
  ADD COLUMN IF NOT EXISTS `academic_declaration` TINYINT(1)   NOT NULL DEFAULT 0     COMMENT '1 = student confirmed details are correct';
