-- ============================================================
-- Migration: Add Missing Academic Detail Fields
-- Purpose: Add fields that the form saves but DB schema doesn't have
--          - Gauhati University registration fields
--          - GUBEDCET auto-fill fields (Name & Category)
--          - Academic declaration checkbox
-- 
-- HOW TO USE IN phpMyAdmin:
--   1. Open phpMyAdmin → Select database "rangiatt_2026"
--   2. Go to SQL tab → Paste this entire script
--   3. Click "Go" to execute
--
-- BACKUP FIRST: This alters the academic_details table
-- ============================================================

USE `rangiatt_2026`;

-- Add 6 missing columns to academic_details table
ALTER TABLE `academic_details` 
ADD COLUMN `gu_registered` ENUM('yes','no') DEFAULT 'no' AFTER `gu_reg_year`,
ADD COLUMN `migrated` ENUM('yes','no') DEFAULT 'no' AFTER `gu_registered`,
ADD COLUMN `other_university` VARCHAR(255) DEFAULT NULL AFTER `migrated`,
ADD COLUMN `gubedcet_name` VARCHAR(255) DEFAULT NULL AFTER `gubedcet_rollno`,
ADD COLUMN `gubedcet_category` VARCHAR(50) DEFAULT NULL AFTER `gubedcet_name`,
ADD COLUMN `academic_declaration` TINYINT(1) DEFAULT 0 AFTER `gubedcet_unattempted`;

-- ============================================================
-- Verification Queries (run these after adding columns):
-- ============================================================
-- Check all GUBEDCET fields:
-- SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME='academic_details' AND COLUMN_NAME LIKE 'gubedcet%' 
-- ORDER BY ORDINAL_POSITION;

-- Check Gauhati University fields:
-- SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME='academic_details' AND (COLUMN_NAME LIKE 'gu_%' OR COLUMN_NAME = 'migrated' OR COLUMN_NAME = 'other_university')
-- ORDER BY ORDINAL_POSITION;

-- Check if academic_declaration exists:
-- SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME='academic_details' AND COLUMN_NAME = 'academic_declaration';
