-- ============================================================
-- RTTC 2026 Registration Portal - Database Schema
-- Database: rangiatt_2026
-- Version:  2026-final
--
-- HOW TO USE IN phpMyAdmin:
--   1. Open phpMyAdmin → SQL tab
--   2. Paste this entire script → Click "Go"
--   The database + all tables will be created automatically.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+05:30";

-- ============================================================
-- Create & select database
-- ============================================================
CREATE DATABASE IF NOT EXISTS `rangiatt_2026`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `rangiatt_2026`;

-- ============================================================
-- TABLE: admin_users
-- Columns: id, name, email, password, is_active
-- ============================================================
CREATE TABLE IF NOT EXISTS `admin_users` (
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
-- Change this password immediately after first login!
INSERT IGNORE INTO `admin_users` (`name`, `email`, `password`, `role`) VALUES
('Super Admin', 'admin@rttc.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');

-- ============================================================
-- TABLE: users  (student applicants)
-- Columns: id, username, email, phone, password, is_verified
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
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
-- TABLE: otp_tokens  (DB-backed OTP, not session)
-- ============================================================
CREATE TABLE IF NOT EXISTS `otp_tokens` (
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
-- Columns: user_id, current_step, is_submitted
-- ============================================================
CREATE TABLE IF NOT EXISTS `registration_progress` (
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
-- ============================================================
CREATE TABLE IF NOT EXISTS `personal_details` (
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
-- ============================================================
CREATE TABLE IF NOT EXISTS `academic_details` (
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
  -- GUBEDCET 2026
  `gubedcet_rollno`         VARCHAR(50)            DEFAULT NULL,
  `gubedcet_marks`          DECIMAL(6,2)           DEFAULT NULL,
  `gubedcet_rank`           INT(11)                DEFAULT NULL,
  `gubedcet_correct`        INT(5)                 DEFAULT NULL,
  `gubedcet_wrong`          INT(5)                 DEFAULT NULL,
  `gubedcet_unattempted`    INT(5)                 DEFAULT NULL,
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
-- ============================================================
CREATE TABLE IF NOT EXISTS `documents` (
  `id`                  INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`             INT(11)      NOT NULL,
  -- Required
  `photo`               VARCHAR(500)           DEFAULT NULL,
  `signature`           VARCHAR(500)           DEFAULT NULL,
  `hslc_marksheet`      VARCHAR(500)           DEFAULT NULL,
  `hsslc_marksheet`     VARCHAR(500)           DEFAULT NULL,
  `degree_marksheet`    VARCHAR(500)           DEFAULT NULL,
  -- Optional
  `masters_marksheet`   VARCHAR(500)           DEFAULT NULL,
  `caste_cert`          VARCHAR(500)           DEFAULT NULL,
  `ews_cert`            VARCHAR(500)           DEFAULT NULL,
  `pwd_cert`            VARCHAR(500)           DEFAULT NULL,
  `obc_ncl_cert`        VARCHAR(500)           DEFAULT NULL,
  -- GUBEDCET
  `gubedcet_admit_card`   VARCHAR(500)         DEFAULT NULL,
  `gubedcet_result_sheet` VARCHAR(500)         DEFAULT NULL,
  -- Admin review
  `status`              ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_remarks`       TEXT                   DEFAULT NULL,
  -- Timestamps
  `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_docs_user` (`user_id`),
  CONSTRAINT `fk_docs_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: payment
-- amount stored in PAISE (integer). Rs 500 = 50000 paise.
-- status: 'success' or 'failed' (matches PHP code)
-- ============================================================
CREATE TABLE IF NOT EXISTS `payment` (
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
