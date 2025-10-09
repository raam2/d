-- Database Normalization Script for GST Accounting System
-- This script addresses the normalization issues in the database design
-- Run this script AFTER backing up your database

-- ============================================================================
-- SECTION 1: Create Item Name Variants Table
-- ============================================================================
-- This table maps multiple product names (variations) to canonical items
-- Addresses the problem: "ONE PRODUCT REFERED BY MULTIPLE NAMES"

CREATE TABLE IF NOT EXISTS `item_name_variants` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_id` INT NOT NULL COMMENT 'Foreign key to items table',
  `variant_name` VARCHAR(255) NOT NULL COMMENT 'Alternative name for the item',
  `variant_type` ENUM('hindi', 'english', 'alias', 'brand') NOT NULL DEFAULT 'alias' COMMENT 'Type of variant name',
  `source_table` VARCHAR(50) DEFAULT NULL COMMENT 'Source table from which this variant was extracted',
  `is_primary` TINYINT(1) DEFAULT 0 COMMENT 'Whether this is a primary variant',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_variant` (`variant_name`, `variant_type`),
  INDEX `idx_item_id` (`item_id`),
  INDEX `idx_variant_name` (`variant_name`),
  FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Maps product name variants to canonical items';

-- ============================================================================
-- SECTION 2: Add HSN Code Column to Items Table (if not exists)
-- ============================================================================
-- The items table currently has `hsn` column but many values are NULL
-- We'll ensure the column exists and has proper indexing

-- Check if hsn column exists, if not add it
-- (This is idempotent - safe to run multiple times)
SET @dbname = DATABASE();
SET @tablename = 'items';
SET @columnname = 'hsn_code';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  'SELECT 1', -- Column exists, do nothing
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' VARCHAR(10) DEFAULT NULL AFTER canonical_name')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add index on hsn_code for faster lookups
CREATE INDEX IF NOT EXISTS `idx_items_hsn` ON `items`(`hsn`);
CREATE INDEX IF NOT EXISTS `idx_items_hsn_code` ON `items`(`hsn_code`);

-- ============================================================================
-- SECTION 3: Update Items Table with HSN Codes from Staging Tables
-- ============================================================================
-- Populate HSN codes in items table from stg_purchase_invoice_hindi

-- Step 3.1: Update hsn_code from hsn (copy existing hsn to hsn_code if needed)
UPDATE `items` 
SET `hsn_code` = `hsn` 
WHERE `hsn` IS NOT NULL AND (`hsn_code` IS NULL OR `hsn_code` = '');

-- Step 3.2: Create temporary mapping table for HSN extraction
CREATE TEMPORARY TABLE IF NOT EXISTS temp_hsn_mapping AS
SELECT DISTINCT
    TRIM(stg.`Hindi_Name`) as item_name,
    TRIM(stg.`HSN code`) as hsn_code,
    COUNT(*) as occurrence_count
FROM `stg_purchase_invoice_hindi` stg
WHERE stg.`HSN code` IS NOT NULL 
  AND stg.`HSN code` != '' 
  AND stg.`HSN code` != 'Item code'
  AND stg.`Hindi_Name` IS NOT NULL
  AND stg.`Hindi_Name` != ''
  AND stg.`Hindi_Name` != 'Hindi_Name'
GROUP BY TRIM(stg.`Hindi_Name`), TRIM(stg.`HSN code`)
ORDER BY item_name, occurrence_count DESC;

-- Step 3.3: Update items table where canonical_name matches Hindi_Name
UPDATE `items` i
INNER JOIN temp_hsn_mapping t ON TRIM(i.`canonical_name`) = t.`item_name`
SET i.`hsn` = t.`hsn_code`,
    i.`hsn_code` = t.`hsn_code`
WHERE (i.`hsn` IS NULL OR i.`hsn` = '') 
  AND t.`hsn_code` IS NOT NULL 
  AND t.`hsn_code` != '';

-- ============================================================================
-- SECTION 4: Populate Item Name Variants from Staging Tables
-- ============================================================================
-- Extract Hindi names from stg_purchase_invoice_hindi and map to items

-- Step 4.1: Insert Hindi name variants from stg_purchase_invoice_hindi
INSERT INTO `item_name_variants` (`item_id`, `variant_name`, `variant_type`, `source_table`)
SELECT DISTINCT
    i.`id` as item_id,
    TRIM(stg.`Hindi_Name`) as variant_name,
    'hindi' as variant_type,
    'stg_purchase_invoice_hindi' as source_table
FROM `stg_purchase_invoice_hindi` stg
INNER JOIN `items` i ON TRIM(i.`canonical_name`) = TRIM(stg.`Hindi_Name`)
WHERE stg.`Hindi_Name` IS NOT NULL 
  AND stg.`Hindi_Name` != '' 
  AND stg.`Hindi_Name` != 'Hindi_Name'
  AND NOT EXISTS (
    SELECT 1 FROM `item_name_variants` v 
    WHERE v.`item_id` = i.`id` 
      AND v.`variant_name` = TRIM(stg.`Hindi_Name`)
  )
ON DUPLICATE KEY UPDATE variant_name = variant_name; -- Ignore duplicates

-- Step 4.2: Insert item names from purchase_invoice_staging_reverse
INSERT INTO `item_name_variants` (`item_id`, `variant_name`, `variant_type`, `source_table`)
SELECT DISTINCT
    i.`id` as item_id,
    TRIM(psr.`item_name`) as variant_name,
    'english' as variant_type,
    'purchase_invoice_staging_reverse' as source_table
FROM `purchase_invoice_staging_reverse` psr
INNER JOIN `items` i ON (
    TRIM(i.`canonical_name`) = TRIM(psr.`item_name`) 
    OR TRIM(i.`canonical_name`) = TRIM(psr.`hindi_name`)
)
WHERE psr.`item_name` IS NOT NULL 
  AND psr.`item_name` != ''
  AND NOT EXISTS (
    SELECT 1 FROM `item_name_variants` v 
    WHERE v.`item_id` = i.`id` 
      AND v.`variant_name` = TRIM(psr.`item_name`)
  )
ON DUPLICATE KEY UPDATE variant_name = variant_name;

-- Step 4.3: Insert Hindi names from purchase_invoice_staging_reverse
INSERT INTO `item_name_variants` (`item_id`, `variant_name`, `variant_type`, `source_table`)
SELECT DISTINCT
    i.`id` as item_id,
    TRIM(psr.`hindi_name`) as variant_name,
    'hindi' as variant_type,
    'purchase_invoice_staging_reverse' as source_table
FROM `purchase_invoice_staging_reverse` psr
INNER JOIN `items` i ON TRIM(i.`canonical_name`) = TRIM(psr.`hindi_name`)
WHERE psr.`hindi_name` IS NOT NULL 
  AND psr.`hindi_name` != ''
  AND psr.`hindi_name` != TRIM(psr.`item_name`)
  AND NOT EXISTS (
    SELECT 1 FROM `item_name_variants` v 
    WHERE v.`item_id` = i.`id` 
      AND v.`variant_name` = TRIM(psr.`hindi_name`)
  )
ON DUPLICATE KEY UPDATE variant_name = variant_name;

-- ============================================================================
-- SECTION 5: Create Normalized Purchase Invoice Structure
-- ============================================================================
-- Replace the denormalized staging tables with proper normalized structure

-- Create normalized purchase_invoice_header table
CREATE TABLE IF NOT EXISTS `purchase_invoice_header` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_no` VARCHAR(50) NOT NULL,
  `invoice_date` DATE NOT NULL,
  `supplier_id` INT DEFAULT NULL COMMENT 'Foreign key to parties table',
  `supplier_name` VARCHAR(255) DEFAULT NULL COMMENT 'Supplier name if not in parties',
  `supplier_gstin` VARCHAR(15) DEFAULT NULL,
  `data_source` VARCHAR(50) DEFAULT NULL COMMENT 'Source of import (e.g., excel, manual)',
  `total_taxable_amount` DECIMAL(14,2) DEFAULT 0.00,
  `total_cgst_amount` DECIMAL(14,2) DEFAULT 0.00,
  `total_sgst_amount` DECIMAL(14,2) DEFAULT 0.00,
  `total_igst_amount` DECIMAL(14,2) DEFAULT 0.00,
  `total_amount` DECIMAL(14,2) DEFAULT 0.00,
  `status` ENUM('staging', 'verified', 'posted', 'cancelled') DEFAULT 'staging',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_invoice_no` (`invoice_no`),
  INDEX `idx_invoice_date` (`invoice_date`),
  INDEX `idx_supplier_gstin` (`supplier_gstin`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`supplier_id`) REFERENCES `parties`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Normalized purchase invoice headers';

-- Create normalized purchase_invoice_line_items table
CREATE TABLE IF NOT EXISTS `purchase_invoice_line_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_header_id` INT NOT NULL,
  `item_id` INT DEFAULT NULL COMMENT 'Foreign key to items table',
  `item_name_variant` VARCHAR(255) DEFAULT NULL COMMENT 'Original item name from source',
  `hsn_code` VARCHAR(10) DEFAULT NULL,
  `batch_no` VARCHAR(50) DEFAULT NULL,
  `mfg_date` DATE DEFAULT NULL,
  `exp_date` DATE DEFAULT NULL,
  `quantity` DECIMAL(12,3) NOT NULL DEFAULT 1.000,
  `rate` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `mrp` DECIMAL(10,2) DEFAULT NULL,
  `discount_amount` DECIMAL(12,2) DEFAULT 0.00,
  `taxable_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cgst_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `sgst_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `igst_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` DECIMAL(12,2) DEFAULT 0.00,
  `sgst_amount` DECIMAL(12,2) DEFAULT 0.00,
  `igst_amount` DECIMAL(12,2) DEFAULT 0.00,
  `line_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_invoice_header` (`invoice_header_id`),
  INDEX `idx_item_id` (`item_id`),
  INDEX `idx_hsn_code` (`hsn_code`),
  FOREIGN KEY (`invoice_header_id`) REFERENCES `purchase_invoice_header`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Normalized purchase invoice line items';

-- ============================================================================
-- SECTION 6: Create Helper Views for Easy Data Access
-- ============================================================================

-- View to see all item variants in one place
CREATE OR REPLACE VIEW `v_item_variants_complete` AS
SELECT 
    i.id as item_id,
    i.canonical_name,
    i.hsn,
    i.hsn_code,
    inv.variant_name,
    inv.variant_type,
    inv.source_table,
    inv.is_primary
FROM items i
LEFT JOIN item_name_variants inv ON i.id = inv.item_id
ORDER BY i.canonical_name, inv.variant_type;

-- View to find items by any name variant
CREATE OR REPLACE VIEW `v_item_lookup` AS
SELECT 
    i.id as item_id,
    i.canonical_name,
    COALESCE(inv.variant_name, i.canonical_name) as search_name,
    i.hsn,
    i.hsn_code,
    inv.variant_type
FROM items i
LEFT JOIN item_name_variants inv ON i.id = inv.item_id;

-- View to show purchase invoice summary with normalized data
CREATE OR REPLACE VIEW `v_purchase_invoice_summary` AS
SELECT 
    pih.id,
    pih.invoice_no,
    pih.invoice_date,
    pih.supplier_name,
    pih.supplier_gstin,
    COUNT(pil.id) as line_count,
    SUM(pil.quantity) as total_quantity,
    pih.total_taxable_amount,
    pih.total_cgst_amount,
    pih.total_sgst_amount,
    pih.total_igst_amount,
    pih.total_amount,
    pih.status
FROM purchase_invoice_header pih
LEFT JOIN purchase_invoice_line_items pil ON pih.id = pil.invoice_header_id
GROUP BY pih.id, pih.invoice_no, pih.invoice_date, pih.supplier_name, 
         pih.supplier_gstin, pih.total_taxable_amount, pih.total_cgst_amount,
         pih.total_sgst_amount, pih.total_igst_amount, pih.total_amount, pih.status;

-- ============================================================================
-- SECTION 7: Data Migration from Staging Tables
-- ============================================================================
-- Migrate data from old staging tables to new normalized structure

-- Step 7.1: Migrate data from purchase_invoice_staging_reverse to normalized structure
INSERT INTO `purchase_invoice_header` (
    `invoice_no`, `invoice_date`, `supplier_name`, `supplier_gstin`, 
    `data_source`, `status`
)
SELECT DISTINCT
    psr.invoice_no,
    psr.invoice_date,
    psr.supplier_name,
    psr.supplier_gstin,
    COALESCE(psr.data_source, 'purchase_invoice_staging_reverse') as data_source,
    'staging' as status
FROM `purchase_invoice_staging_reverse` psr
WHERE NOT EXISTS (
    SELECT 1 FROM `purchase_invoice_header` pih 
    WHERE pih.invoice_no = psr.invoice_no 
      AND pih.invoice_date = psr.invoice_date
)
ORDER BY psr.invoice_date, psr.invoice_no;

-- Step 7.2: Migrate line items from purchase_invoice_staging_reverse
INSERT INTO `purchase_invoice_line_items` (
    `invoice_header_id`, `item_id`, `item_name_variant`, `hsn_code`,
    `batch_no`, `mfg_date`, `exp_date`, `quantity`, `rate`,
    `taxable_amount`, `cgst_rate`, `sgst_rate`, `igst_rate`,
    `cgst_amount`, `sgst_amount`, `igst_amount`, `line_total`
)
SELECT 
    pih.id as invoice_header_id,
    i.id as item_id,
    psr.item_name as item_name_variant,
    psr.hsn_code,
    psr.batch_no,
    psr.mfg_date,
    psr.exp_date,
    psr.quantity,
    psr.calculated_rate as rate,
    psr.calculated_taxable_amount as taxable_amount,
    psr.cgst_rate,
    psr.sgst_rate,
    psr.igst_rate,
    psr.calculated_cgst_amount as cgst_amount,
    psr.calculated_sgst_amount as sgst_amount,
    psr.calculated_igst_amount as igst_amount,
    psr.calculated_line_total as line_total
FROM `purchase_invoice_staging_reverse` psr
INNER JOIN `purchase_invoice_header` pih 
    ON pih.invoice_no = psr.invoice_no 
    AND pih.invoice_date = psr.invoice_date
LEFT JOIN `items` i 
    ON (TRIM(i.canonical_name) = TRIM(psr.hindi_name) 
        OR TRIM(i.canonical_name) = TRIM(psr.item_name))
WHERE NOT EXISTS (
    SELECT 1 FROM `purchase_invoice_line_items` pil
    WHERE pil.invoice_header_id = pih.id
      AND pil.item_name_variant = psr.item_name
      AND pil.batch_no <=> psr.batch_no
);

-- Step 7.3: Update header totals from line items
UPDATE `purchase_invoice_header` pih
SET 
    pih.total_taxable_amount = (
        SELECT COALESCE(SUM(pil.taxable_amount), 0)
        FROM `purchase_invoice_line_items` pil
        WHERE pil.invoice_header_id = pih.id
    ),
    pih.total_cgst_amount = (
        SELECT COALESCE(SUM(pil.cgst_amount), 0)
        FROM `purchase_invoice_line_items` pil
        WHERE pil.invoice_header_id = pih.id
    ),
    pih.total_sgst_amount = (
        SELECT COALESCE(SUM(pil.sgst_amount), 0)
        FROM `purchase_invoice_line_items` pil
        WHERE pil.invoice_header_id = pih.id
    ),
    pih.total_igst_amount = (
        SELECT COALESCE(SUM(pil.igst_amount), 0)
        FROM `purchase_invoice_line_items` pil
        WHERE pil.invoice_header_id = pih.id
    ),
    pih.total_amount = (
        SELECT COALESCE(SUM(pil.line_total), 0)
        FROM `purchase_invoice_line_items` pil
        WHERE pil.invoice_header_id = pih.id
    );

-- ============================================================================
-- SECTION 8: Create Stored Procedures for Common Operations
-- ============================================================================

DELIMITER $$

-- Procedure to find item by any variant name
DROP PROCEDURE IF EXISTS `sp_find_item_by_name`$$
CREATE PROCEDURE `sp_find_item_by_name`(IN search_name VARCHAR(255))
BEGIN
    SELECT DISTINCT
        i.id,
        i.canonical_name,
        i.hsn,
        i.hsn_code,
        i.is_active
    FROM items i
    LEFT JOIN item_name_variants inv ON i.id = inv.item_id
    WHERE i.canonical_name LIKE CONCAT('%', search_name, '%')
       OR inv.variant_name LIKE CONCAT('%', search_name, '%')
    LIMIT 10;
END$$

-- Procedure to add a new item variant
DROP PROCEDURE IF EXISTS `sp_add_item_variant`$$
CREATE PROCEDURE `sp_add_item_variant`(
    IN p_item_id INT,
    IN p_variant_name VARCHAR(255),
    IN p_variant_type ENUM('hindi', 'english', 'alias', 'brand'),
    IN p_source_table VARCHAR(50)
)
BEGIN
    INSERT INTO item_name_variants (item_id, variant_name, variant_type, source_table)
    VALUES (p_item_id, p_variant_name, p_variant_type, p_source_table)
    ON DUPLICATE KEY UPDATE 
        variant_type = p_variant_type,
        source_table = p_source_table;
END$$

-- Procedure to update item HSN code
DROP PROCEDURE IF EXISTS `sp_update_item_hsn`$$
CREATE PROCEDURE `sp_update_item_hsn`(
    IN p_item_id INT,
    IN p_hsn_code VARCHAR(10)
)
BEGIN
    UPDATE items
    SET hsn = p_hsn_code,
        hsn_code = p_hsn_code
    WHERE id = p_item_id;
END$$

DELIMITER ;

-- ============================================================================
-- SECTION 9: Create Indexes for Performance
-- ============================================================================

-- Indexes on purchase_invoice_header
CREATE INDEX IF NOT EXISTS `idx_pih_supplier` ON `purchase_invoice_header`(`supplier_name`);
CREATE INDEX IF NOT EXISTS `idx_pih_date_status` ON `purchase_invoice_header`(`invoice_date`, `status`);

-- Indexes on purchase_invoice_line_items  
CREATE INDEX IF NOT EXISTS `idx_pil_item_name` ON `purchase_invoice_line_items`(`item_name_variant`);
CREATE INDEX IF NOT EXISTS `idx_pil_batch` ON `purchase_invoice_line_items`(`batch_no`);

-- ============================================================================
-- SECTION 10: Validation Queries
-- ============================================================================
-- Run these queries to verify the migration was successful

-- Count of items with HSN codes (should be higher after migration)
SELECT 
    COUNT(*) as total_items,
    COUNT(hsn) as items_with_hsn_old,
    COUNT(hsn_code) as items_with_hsn_code,
    ROUND(COUNT(hsn) * 100.0 / COUNT(*), 2) as hsn_percentage_old,
    ROUND(COUNT(hsn_code) * 100.0 / COUNT(*), 2) as hsn_percentage_new
FROM items;

-- Count of item name variants by type
SELECT 
    variant_type,
    COUNT(*) as variant_count,
    COUNT(DISTINCT item_id) as unique_items
FROM item_name_variants
GROUP BY variant_type
ORDER BY variant_count DESC;

-- Count of migrated invoices
SELECT 
    'purchase_invoice_header' as table_name,
    COUNT(*) as record_count
FROM purchase_invoice_header
UNION ALL
SELECT 
    'purchase_invoice_line_items' as table_name,
    COUNT(*) as record_count
FROM purchase_invoice_line_items
UNION ALL
SELECT 
    'item_name_variants' as table_name,
    COUNT(*) as record_count
FROM item_name_variants;

-- Items without HSN codes (should investigate these)
SELECT id, canonical_name, hsn, hsn_code
FROM items
WHERE (hsn IS NULL OR hsn = '') 
  AND (hsn_code IS NULL OR hsn_code = '')
LIMIT 20;

-- ============================================================================
-- END OF NORMALIZATION SCRIPT
-- ============================================================================

-- Note: The old staging tables (purchase_invoice_staging, 
-- purchase_invoice_staging_reverse, stg_purchase_invoice_hindi) are NOT dropped
-- to preserve historical data. They can be archived or dropped manually after
-- verifying the migration was successful.
