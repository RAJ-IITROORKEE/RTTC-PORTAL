-- RTTC ID Card module
-- Apply this file only after backing up the selected database and testing on staging.
-- This migration is additive: it does not create/select a database or remove existing data.

CREATE TABLE IF NOT EXISTS `id_card_applications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_type` ENUM('student','faculty_staff') NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `care_of` VARCHAR(150) NOT NULL,
  `course` VARCHAR(100) DEFAULT NULL,
  `academic_session` VARCHAR(20) DEFAULT NULL,
  `roll_number` VARCHAR(50) DEFAULT NULL,
  `date_of_birth` DATE DEFAULT NULL,
  `department` VARCHAR(150) DEFAULT NULL,
  `designation` VARCHAR(150) DEFAULT NULL,
  `blood_group` VARCHAR(10) NOT NULL,
  `contact_number` VARCHAR(20) NOT NULL,
  `address` VARCHAR(500) NOT NULL,
  `photo_path` VARCHAR(500) NOT NULL,
  `declaration_accepted_at` DATETIME NOT NULL,
  `status` ENUM('pending','approved','done') NOT NULL DEFAULT 'pending',
  `approved_at` DATETIME DEFAULT NULL,
  `approved_by` INT(11) DEFAULT NULL,
  `first_downloaded_at` DATETIME DEFAULT NULL,
  `last_downloaded_at` DATETIME DEFAULT NULL,
  `download_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `submission_token_hash` CHAR(64) NOT NULL,
  `submitted_ip_key` CHAR(64) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_id_card_submission_token` (`submission_token_hash`),
  KEY `idx_id_card_status_created` (`status`, `created_at`),
  KEY `idx_id_card_type_status_created` (`application_type`, `status`, `created_at`),
  KEY `idx_id_card_contact` (`contact_number`),
  CONSTRAINT `fk_id_card_applications_approved_by`
    FOREIGN KEY (`approved_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `id_card_action_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id` BIGINT UNSIGNED DEFAULT NULL,
  `application_reference` VARCHAR(32) NOT NULL,
  `action` ENUM('submitted','approved','downloaded','deleted') NOT NULL,
  `admin_user_id` INT(11) DEFAULT NULL,
  `notes` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_id_card_log_application_created` (`application_id`, `created_at`),
  KEY `idx_id_card_log_admin_created` (`admin_user_id`, `created_at`),
  CONSTRAINT `fk_id_card_log_application`
    FOREIGN KEY (`application_id`) REFERENCES `id_card_applications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_id_card_log_admin`
    FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `id_card_submission_attempts` (
  `ip_key` CHAR(64) NOT NULL,
  `bucket_start` DATETIME NOT NULL,
  `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`ip_key`, `bucket_start`),
  KEY `idx_id_card_attempt_bucket` (`bucket_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verification queries
SHOW TABLES LIKE 'id_card_%';
SHOW CREATE TABLE `id_card_applications`;
SHOW CREATE TABLE `id_card_action_log`;
SHOW CREATE TABLE `id_card_submission_attempts`;
