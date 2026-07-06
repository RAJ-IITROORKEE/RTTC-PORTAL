-- Create CORRECT missing tables for RTTC 2026

-- Drop old tables if they exist (to recreate with correct columns)
DROP TABLE IF EXISTS `home_marquee_items`;
DROP TABLE IF EXISTS `notice_documents`;

-- Create home_marquee_items with ALL required columns
CREATE TABLE `home_marquee_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `content` text NOT NULL,
  `link_url` varchar(255),
  `link_label` varchar(100),
  `sort_order` int DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create notice_documents with ALL required columns
CREATE TABLE `notice_documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `doc_key` varchar(100) UNIQUE NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `button_label` varchar(100),
  `file_path` varchar(255) NOT NULL,
  `link_url` varchar(255),
  `sort_order` int DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `doc_key` (`doc_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data for home_marquee_items
INSERT INTO `home_marquee_items` (`content`, `link_url`, `link_label`, `sort_order`, `is_active`) VALUES 
('Welcome to RTTC 2026 B.Ed. Admission Portal', '', '', 1, 1),
('Apply Now for B.Ed. First Year 2026-2027', '', '', 2, 1),
('Last date for application: Extended till further notice', '', '', 3, 1);

-- Insert sample data for notice_documents
INSERT INTO `notice_documents` (`doc_key`, `title`, `button_label`, `file_path`, `link_url`, `sort_order`, `is_active`) VALUES 
('admission_notice', 'Admission Notice 2026-2027', 'Download', '/storage/uploads/notices/admission_notice.pdf', '', 1, 1),
('cutoff_marks', 'Cutoff Marks', 'Download', '/storage/uploads/notices/cutoff_marks.pdf', '', 2, 1),
('merit_list', 'Merit List', 'Download', '/storage/uploads/notices/merit_list.pdf', '', 3, 1);
