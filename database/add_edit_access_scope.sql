-- Migration: Add scope column to user_edit_access table
-- This allows granting document-only edit access vs full (all steps) access.
-- Existing rows default to 'all' (backward compatible).

ALTER TABLE `user_edit_access`
ADD COLUMN `scope` ENUM('all', 'documents') NOT NULL DEFAULT 'all'
COMMENT 'Scope of edit access: all steps or documents only'
AFTER `is_active`;
