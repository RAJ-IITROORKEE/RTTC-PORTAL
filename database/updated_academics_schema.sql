-- ============================================================
-- RTTC 2026 – Academic Details Table Update
-- Run this in phpMyAdmin → SQL tab to add new columns.
-- Safe to run multiple times (IF NOT EXISTS guards).
-- ============================================================

USE `rangiatt_2026`;

ALTER TABLE `academic_details`
  ADD COLUMN IF NOT EXISTS `gu_registered`        VARCHAR(3)   NOT NULL DEFAULT 'no'
    COMMENT 'yes = student is already registered in Gauhati University',

  ADD COLUMN IF NOT EXISTS `migrated`             VARCHAR(3)   NOT NULL DEFAULT 'no'
    COMMENT 'yes = student migrated from another university',

  ADD COLUMN IF NOT EXISTS `other_university`     VARCHAR(255)          DEFAULT NULL
    COMMENT 'Name of the other university (when migrated = yes)',

  ADD COLUMN IF NOT EXISTS `gubedcet_name`        VARCHAR(255)          DEFAULT NULL
    COMMENT 'Candidate name auto-filled from GUBEDCET result JSON',

  ADD COLUMN IF NOT EXISTS `gubedcet_category`    VARCHAR(100)          DEFAULT NULL
    COMMENT 'Category (General/SC/ST/OBC…) from GUBEDCET result JSON',

  ADD COLUMN IF NOT EXISTS `academic_declaration` TINYINT(1)   NOT NULL DEFAULT 0
    COMMENT '1 = student confirmed all academic details are correct';
