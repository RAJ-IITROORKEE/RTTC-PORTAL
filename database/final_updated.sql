-- ============================================================
-- RTTC 2026 Registration Portal - Complete Database Reset
-- Database: rangiatt_2026
-- Version: 2026-final-complete
--
-- THIS SCRIPT WILL:
--   1. DROP ALL existing tables (fresh start)
--   2. RECREATE all tables with correct schemas
--   3. Include ALL missing GUBEDCET fields
--   4. Include ALL student query & edit access tables
--
-- ⚠️  WARNING: This will DELETE ALL DATA
-- Backup your database before running!
--
-- HOW TO USE IN phpMyAdmin:
--   1. Open phpMyAdmin
--   2. Select database: rangiatt_2026 (or create new if doesn't exist)
--   3. Go to SQL tab
--   4. Copy & paste ENTIRE script below
--   5. Click "Go"
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+05:30";

-- ============================================================
-- Create & select database (safe to run multiple times)
-- ============================================================
CREATE DATABASE IF NOT EXISTS `rangiatt_2026`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `rangiatt_2026`;

-- ============================================================
-- STEP 1: DROP ALL EXISTING TABLES (fresh start)
-- ============================================================
-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Drop all tables if they exist
DROP TABLE IF EXISTS `user_edit_access`;
DROP TABLE IF EXISTS `student_queries`;
DROP TABLE IF EXISTS `payment`;
DROP TABLE IF EXISTS `documents`;
DROP TABLE IF EXISTS `academic_details`;
DROP TABLE IF EXISTS `personal_details`;
DROP TABLE IF EXISTS `registration_progress`;
DROP TABLE IF EXISTS `otp_tokens`;
DROP TABLE IF EXISTS `admin_users`;
DROP TABLE IF EXISTS `users`;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- STEP 2: CREATE ALL TABLES FROM SCRATCH
-- ============================================================

-- ============================================================
-- TABLE: admin_users
-- Purpose: Admin/staff user accounts
-- ============================================================
CREATE TABLE `admin_users` (
  `id`          INT(11)       NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150)  NOT NULL,
  `email`       VARCHAR(191)  NOT NULL,
  `password`    VARCHAR(255)  NOT NULL,
  `role`        ENUM('super_admin','admin','viewer') NOT NULL DEFAULT 'admin',
  `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
  `last_login`  DATETIME               DEFAULT NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin account: admin@rttc.in / password = "password"
-- ⚠️  CHANGE THIS IMMEDIATELY AFTER FIRST LOGIN!
INSERT INTO `admin_users` (`name`, `email`, `password`, `role`) VALUES
('Super Admin', 'admin@rttc.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');

-- ============================================================
-- TABLE: users
-- Purpose: Student applicant accounts
-- ============================================================
CREATE TABLE `users` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `username`     VARCHAR(150) NOT NULL,
  `email`        VARCHAR(191) NOT NULL,
  `phone`        VARCHAR(15)  NOT NULL,
  `password`     VARCHAR(255) NOT NULL,
  `is_verified`  TINYINT(1)   NOT NULL DEFAULT 0
                 COMMENT '1 = email OTP verified',
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  UNIQUE KEY `uq_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: otp_tokens
-- Purpose: Database-backed OTP management (not session)
-- ============================================================
CREATE TABLE `otp_tokens` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `email`       VARCHAR(191) NOT NULL,
  `otp_code`    VARCHAR(10)  NOT NULL,
  `purpose`     ENUM('signup','reset') NOT NULL DEFAULT 'signup',
  `is_used`     TINYINT(1)   NOT NULL DEFAULT 0,
  `expires_at`  DATETIME     NOT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_email_purpose` (`email`, `purpose`),
  KEY `idx_otp_expires`       (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: registration_progress
-- Purpose: Track student progress through 4-step registration
-- ============================================================
CREATE TABLE `registration_progress` (
  `id`            INT(11)    NOT NULL AUTO_INCREMENT,
  `user_id`       INT(11)    NOT NULL,
  `current_step`  TINYINT(1) NOT NULL DEFAULT 0
                  COMMENT '0=not started, 1=personal done, 2=academic done, 3=docs done, 4=payment done',
  `is_submitted`  TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at`    DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_progress_user` (`user_id`),
  CONSTRAINT `fk_progress_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: personal_details
-- Purpose: Step 1 - Student personal & family information
-- ============================================================
CREATE TABLE `personal_details` (
  `id`                INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`           INT(11)      NOT NULL,
  -- Name
  `firstname`         VARCHAR(100) NOT NULL,
  `middlename`        VARCHAR(100) NOT NULL DEFAULT '',
  `lastname`          VARCHAR(100) NOT NULL,
  -- Father
  `fathersname`       VARCHAR(150) NOT NULL,
  `foccupation`       VARCHAR(100) NOT NULL DEFAULT '',
  `fcontact`          VARCHAR(15)  NOT NULL DEFAULT '',
  `fqualifications`   VARCHAR(100) NOT NULL DEFAULT '',
  -- Mother
  `mothersname`       VARCHAR(150) NOT NULL,
  `moccupation`       VARCHAR(100) NOT NULL DEFAULT '',
  `mcontact`          VARCHAR(15)  NOT NULL DEFAULT '',
  `mqualification`    VARCHAR(100) NOT NULL DEFAULT '',
  -- Spouse (optional)
  `spousename`        VARCHAR(150) NOT NULL DEFAULT '',
  `soccupation`       VARCHAR(100) NOT NULL DEFAULT '',
  `scontact`          VARCHAR(15)  NOT NULL DEFAULT '',
  `squalification`    VARCHAR(100) NOT NULL DEFAULT '',
  -- Personal details
  `dob`               DATE         NOT NULL,
  `age`               TINYINT(3)   UNSIGNED NOT NULL DEFAULT 0,
  `gender`            ENUM('Male','Female','Transgender') NOT NULL,
  `blood_group`       VARCHAR(5)   NOT NULL DEFAULT '',
  `religion`          VARCHAR(50)  NOT NULL DEFAULT '',
  `caste`             VARCHAR(50)  NOT NULL DEFAULT 'General',
  `ews`               TINYINT(1)   NOT NULL DEFAULT 0,
  `obc_ncl`           TINYINT(1)   NOT NULL DEFAULT 0,
  `pwd`               TINYINT(1)   NOT NULL DEFAULT 0,
  -- Address
  `permanent_address` TEXT         NOT NULL,
  `present_address`   TEXT         NOT NULL,
  `emergency_contact` VARCHAR(15)  NOT NULL DEFAULT '',
  `income`            VARCHAR(50)  NOT NULL DEFAULT '',
  -- Timestamps
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_personal_user` (`user_id`),
  CONSTRAINT `fk_personal_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: academic_details
-- Purpose: Step 2 - All academic information + GUBEDCET auto-fill
--
-- ✅ INCLUDES ALL 6 MISSING FIELDS:
--    - gu_registered
--    - migrated
--    - other_university
--    - gubedcet_name (auto-filled from data.json)
--    - gubedcet_category (auto-filled from data.json)
--    - academic_declaration
-- ============================================================
CREATE TABLE `academic_details` (
  `id`                      INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`                 INT(11)      NOT NULL,
  
  -- HSLC (Class X)
  `hslc_pass_year`          VARCHAR(4)             DEFAULT NULL,
  `hslc_board`              VARCHAR(150)           DEFAULT NULL,
  `hslc_institute`          VARCHAR(255)           DEFAULT NULL,
  `hslc_division`           VARCHAR(50)            DEFAULT NULL,
  `hslc_total_marks`        DECIMAL(7,2)           DEFAULT NULL,
  `hslc_obtained_marks`     DECIMAL(7,2)           DEFAULT NULL,
  `hslc_percentage`         DECIMAL(5,2)           DEFAULT NULL,
  `hslc_subjects`           VARCHAR(255)           DEFAULT NULL,
  
  -- HSSLC (Class XII)
  `hsslc_pass_year`         VARCHAR(4)             DEFAULT NULL,
  `hsslc_board`             VARCHAR(150)           DEFAULT NULL,
  `hsslc_institute`         VARCHAR(255)           DEFAULT NULL,
  `hsslc_division`          VARCHAR(50)            DEFAULT NULL,
  `hsslc_total_marks`       DECIMAL(7,2)           DEFAULT NULL,
  `hsslc_obtained_marks`    DECIMAL(7,2)           DEFAULT NULL,
  `hsslc_percentage`        DECIMAL(5,2)           DEFAULT NULL,
  `hsslc_subjects`          VARCHAR(255)           DEFAULT NULL,
  
  -- Bachelor's Degree
  `bachelor_pass_year`      VARCHAR(4)             DEFAULT NULL,
  `bachelor_board`          VARCHAR(150)           DEFAULT NULL,
  `bachelor_institute`      VARCHAR(255)           DEFAULT NULL,
  `bachelor_division`       VARCHAR(50)            DEFAULT NULL,
  `bachelor_total_marks`    DECIMAL(7,2)           DEFAULT NULL,
  `bachelor_obtained_marks` DECIMAL(7,2)           DEFAULT NULL,
  `bachelor_percentage`     DECIMAL(5,2)           DEFAULT NULL,
  `bachelor_subjects`       VARCHAR(255)           DEFAULT NULL,
  
  -- Master's Degree (optional)
  `masters_pass_year`       VARCHAR(4)             DEFAULT NULL,
  `masters_board`           VARCHAR(150)           DEFAULT NULL,
  `masters_institute`       VARCHAR(255)           DEFAULT NULL,
  `masters_division`        VARCHAR(50)            DEFAULT NULL,
  `masters_total_marks`     DECIMAL(7,2)           DEFAULT NULL,
  `masters_obtained_marks`  DECIMAL(7,2)           DEFAULT NULL,
  `masters_percentage`      DECIMAL(5,2)           DEFAULT NULL,
  `masters_subjects`        VARCHAR(255)           DEFAULT NULL,
  
  -- Gauhati University
  `gu_reg_no`               VARCHAR(50)            DEFAULT NULL,
  `gu_reg_year`             VARCHAR(4)             DEFAULT NULL,
  `gu_registered`           ENUM('yes','no')       DEFAULT 'no',
  `migrated`                ENUM('yes','no')       DEFAULT 'no',
  `other_university`        VARCHAR(255)           DEFAULT NULL,
  
  -- GUBEDCET 2026 (Auto-filled from data.json)
  `gubedcet_rollno`         VARCHAR(50)            DEFAULT NULL,
  `gubedcet_name`           VARCHAR(255)           DEFAULT NULL COMMENT 'Auto-filled from data.json',
  `gubedcet_category`       VARCHAR(50)            DEFAULT NULL COMMENT 'Auto-filled from data.json',
  `gubedcet_marks`          DECIMAL(6,2)           DEFAULT NULL,
  `gubedcet_rank`           INT(11)                DEFAULT NULL,
  `gubedcet_correct`        INT(5)                 DEFAULT NULL,
  `gubedcet_wrong`          INT(5)                 DEFAULT NULL,
  `gubedcet_unattempted`    INT(5)                 DEFAULT NULL,
  
  -- Declaration
  `academic_declaration`    TINYINT(1)             DEFAULT 0,
  
  -- Timestamps
  `created_at`              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_academic_user` (`user_id`),
  CONSTRAINT `fk_academic_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: documents
-- Purpose: Step 3 - Upload required & optional documents
-- ============================================================
CREATE TABLE `documents` (
  `id`                    INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`               INT(11)      NOT NULL,
  
  -- Required documents
  `photo`                 VARCHAR(500)           DEFAULT NULL,
  `signature`             VARCHAR(500)           DEFAULT NULL,
  `hslc_marksheet`        VARCHAR(500)           DEFAULT NULL,
  `hsslc_marksheet`       VARCHAR(500)           DEFAULT NULL,
  `degree_marksheet`      VARCHAR(500)           DEFAULT NULL,
  
  -- Optional documents
  `masters_marksheet`     VARCHAR(500)           DEFAULT NULL,
  `caste_cert`            VARCHAR(500)           DEFAULT NULL,
  `ews_cert`              VARCHAR(500)           DEFAULT NULL,
  `pwd_cert`              VARCHAR(500)           DEFAULT NULL,
  `obc_ncl_cert`          VARCHAR(500)           DEFAULT NULL,
  
  -- GUBEDCET documents
  `gubedcet_admit_card`   VARCHAR(500)           DEFAULT NULL,
  `gubedcet_result_sheet` VARCHAR(500)           DEFAULT NULL,
  
  -- Admin review
  `status`                ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_remarks`         TEXT                   DEFAULT NULL,
  
  -- Timestamps
  `created_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_docs_user` (`user_id`),
  CONSTRAINT `fk_docs_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: payment
-- Purpose: Step 4 - Razorpay payment tracking
-- Note: amounts stored in PAISE (multiply by 100)
--       Rs 500 = 50000 paise
-- ============================================================
CREATE TABLE `payment` (
  `id`                   INT(11)       NOT NULL AUTO_INCREMENT,
  `user_id`              INT(11)       NOT NULL,
  `razorpay_order_id`    VARCHAR(100)  NOT NULL,
  `razorpay_payment_id`  VARCHAR(100)            DEFAULT NULL,
  `razorpay_signature`   VARCHAR(300)            DEFAULT NULL,
  `amount`               INT(11)       NOT NULL DEFAULT 50000
                         COMMENT 'Amount in paise. 50000 = Rs 500',
  `currency`             VARCHAR(5)    NOT NULL DEFAULT 'INR',
  `status`               ENUM('pending','success','failed','refunded') NOT NULL DEFAULT 'pending',
  `created_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_razorpay_order` (`razorpay_order_id`),
  KEY `idx_payment_user` (`user_id`),
  CONSTRAINT `fk_payment_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: student_queries
-- Purpose: Support ticket system for student queries
-- ============================================================
CREATE TABLE `student_queries` (
  `id`                  INT(11)       NOT NULL AUTO_INCREMENT,
  `user_id`             INT(11)       DEFAULT NULL
                        COMMENT 'Linked users.id if raised by logged-in student; NULL for guests',
  `name`                VARCHAR(150)  NOT NULL,
  `email`               VARCHAR(191)  NOT NULL,
  `phone`               VARCHAR(20)   NOT NULL,
  `issue_subject`       VARCHAR(255)  NOT NULL,
  `message`             TEXT          NOT NULL,
  `status`              ENUM('pending','resolved') NOT NULL DEFAULT 'pending',
  `reply_message`       TEXT                       DEFAULT NULL
                        COMMENT 'Admin reply sent via email',
  `replied_at`          DATETIME                   DEFAULT NULL,
  `edit_access_granted` TINYINT(1)    NOT NULL DEFAULT 0
                        COMMENT '1 if admin granted edit access when resolving',
  `created_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_sq_email`    (`email`),
  KEY `idx_sq_status`   (`status`),
  KEY `idx_sq_user_id`  (`user_id`),
  CONSTRAINT `fk_sq_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Student/visitor queries raised via Raise Query form';

-- ============================================================
-- TABLE: user_edit_access
-- Purpose: Admin-granted temporary edit access for students
-- Note: A student can only have ONE active grant at a time
-- ============================================================
CREATE TABLE `user_edit_access` (
  `id`          INT(11)     NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11)     NOT NULL,
  `granted_by`  INT(11)     DEFAULT NULL
                COMMENT 'admin_users.id who granted access',
  `granted_at`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`  DATETIME    NOT NULL
                COMMENT 'Access auto-expires after this timestamp (default: +1 day)',
  `is_active`   TINYINT(1)  NOT NULL DEFAULT 1
                COMMENT '0 = manually revoked by admin',
  `note`        VARCHAR(255) DEFAULT NULL
                COMMENT 'Optional admin note / query reference',
  `created_at`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_uea_user_id`    (`user_id`),
  KEY `idx_uea_expires_at` (`expires_at`),
  CONSTRAINT `fk_uea_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Temporary edit access grants issued by admin to students';

-- ============================================================
-- ✅ DATABASE SETUP COMPLETE!
-- ============================================================
-- All 10 tables created with correct schemas
-- Including ALL 6 missing GUBEDCET fields:
--   ✓ gu_registered
--   ✓ migrated
--   ✓ other_university
--   ✓ gubedcet_name
--   ✓ gubedcet_category
--   ✓ academic_declaration
--
-- Next steps:
-- 1. Verify all tables exist in phpMyAdmin
-- 2. Test GUBEDCET auto-fill in academics form
-- 3. User can now save academic details successfully
-- ============================================================
