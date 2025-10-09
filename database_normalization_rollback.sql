-- Database Normalization Rollback Script
-- Run this script if you need to rollback the normalization changes
-- WARNING: This will delete all normalized data!

-- ============================================================================
-- IMPORTANT: BACKUP YOUR DATABASE BEFORE RUNNING THIS SCRIPT
-- ============================================================================

-- Step 1: Drop stored procedures
DROP PROCEDURE IF EXISTS `sp_find_item_by_name`;
DROP PROCEDURE IF EXISTS `sp_add_item_variant`;
DROP PROCEDURE IF EXISTS `sp_update_item_hsn`;

-- Step 2: Drop views
DROP VIEW IF EXISTS `v_item_variants_complete`;
DROP VIEW IF EXISTS `v_item_lookup`;
DROP VIEW IF EXISTS `v_purchase_invoice_summary`;

-- Step 3: Drop normalized tables (in reverse order due to foreign keys)
DROP TABLE IF EXISTS `purchase_invoice_line_items`;
DROP TABLE IF EXISTS `purchase_invoice_header`;
DROP TABLE IF EXISTS `item_name_variants`;

-- Step 4: Remove hsn_code column from items table if it was added
-- (Only if you want to completely rollback to original state)
-- SET @dbname = DATABASE();
-- SET @tablename = 'items';
-- SET @columnname = 'hsn_code';
-- SET @preparedStatement = (SELECT IF(
--   (
--     SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
--     WHERE
--       TABLE_SCHEMA = @dbname
--       AND TABLE_NAME = @tablename
--       AND COLUMN_NAME = @columnname
--   ) > 0,
--   CONCAT('ALTER TABLE ', @tablename, ' DROP COLUMN ', @columnname),
--   'SELECT 1' -- Column doesn't exist, do nothing
-- ));
-- PREPARE alterIfExists FROM @preparedStatement;
-- EXECUTE alterIfExists;
-- DEALLOCATE PREPARE alterIfExists;

-- Step 5: Drop indexes that were added
DROP INDEX IF EXISTS `idx_items_hsn` ON `items`;
DROP INDEX IF EXISTS `idx_items_hsn_code` ON `items`;

-- Verification query
SELECT 'Rollback completed. Normalized tables and objects have been removed.' as status;

-- Note: The original staging tables (purchase_invoice_staging, 
-- purchase_invoice_staging_reverse, stg_purchase_invoice_hindi) 
-- remain intact and unchanged.
