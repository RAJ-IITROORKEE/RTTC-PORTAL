-- ============================================================
-- RTTC 2026 - Raise Query & Edit Access — UPDATED SCHEMA
-- Database: rangiatt_2026
--
-- HOW TO USE IN phpMyAdmin:
--   1. Open phpMyAdmin → select database rangiatt_2026
--   2. Click the SQL tab
--   3. Paste this entire script → Click "Go"
--   Old tables are dropped first, then recreated correctly.
-- ============================================================

USE `rangiatt_2026`;

-- ============================================================
-- Drop old tables (order matters: child FK first)
-- ============================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `user_edit_access`;
DROP TABLE IF EXISTS `student_queries`;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- TABLE: student_queries
-- ============================================================
CREATE TABLE `student_queries` (
  `id`                  INT(11)       NOT NULL AUTO_INCREMENT,
  `user_id`             INT(11)       DEFAULT NULL
                        COMMENT 'users.id if raised by a logged-in student; NULL for guests',
  `name`                VARCHAR(150)  NOT NULL,
  `email`               VARCHAR(191)  NOT NULL,
  `phone`               VARCHAR(20)   NOT NULL DEFAULT '',
  `issue_subject`       VARCHAR(255)  NOT NULL,
  `message`             TEXT          NOT NULL,
  `status`              ENUM('pending','resolved') NOT NULL DEFAULT 'pending',
  `reply_message`       TEXT          DEFAULT NULL
                        COMMENT 'Admin reply sent via email',
  `replied_at`          DATETIME      DEFAULT NULL,
  `edit_access_granted` TINYINT(1)    NOT NULL DEFAULT 0
                        COMMENT '1 if admin granted edit access when resolving this query',
  `created_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sq_user_id` (`user_id`),
  KEY `idx_sq_email`   (`email`),
  KEY `idx_sq_status`  (`status`),
  CONSTRAINT `fk_sq_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Student/visitor queries raised via the Raise Query form';

-- ============================================================
-- TABLE: user_edit_access
-- ============================================================
CREATE TABLE `user_edit_access` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11)      NOT NULL,
  `granted_by`  INT(11)      DEFAULT NULL
                COMMENT 'admin_users.id who granted access',
  `granted_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`  DATETIME     NOT NULL
                COMMENT 'Access auto-expires after this timestamp',
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1
                COMMENT '0 = manually revoked by admin',
  `note`        VARCHAR(255) DEFAULT NULL
                COMMENT 'Optional admin note / query reference',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_uea_user_id`    (`user_id`),
  KEY `idx_uea_expires_at` (`expires_at`),
  CONSTRAINT `fk_uea_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Temporary edit access grants issued by admin to specific students';

-- ============================================================
-- Done.
-- ============================================================
