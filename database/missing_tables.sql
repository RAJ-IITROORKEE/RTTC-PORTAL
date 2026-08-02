-- Create missing tables for RTTC 2026

CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL DEFAULT '',
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('registration_open', '1')
ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);

CREATE TABLE IF NOT EXISTS `home_marquee_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `content` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notice_documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `document_path` varchar(255) NOT NULL,
  `file_type` varchar(50),
  `upload_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `home_marquee_items`
SET `content` = REPLACE(REPLACE(`content`, 'B.Ed. First Year', 'B.Ed admission'), '2026-2027', '2026-27')
WHERE `content` LIKE '%B.Ed. First Year%' OR `content` LIKE '%2026-2027%';

-- Insert sample data
INSERT INTO `home_marquee_items` (`content`, `is_active`) VALUES 
('Welcome to RTTC 2026 B.Ed. Admission Portal', 1),
('Apply Now for B.Ed admission 2026-27', 1),
('Last date for application: Extended till further notice', 1);
