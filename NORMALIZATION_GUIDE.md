# Database Normalization Guide

## Overview

This guide documents the database normalization improvements made to address the following issues:

1. **Incomplete Normalization**: Tables `purchase_invoice_staging`, `purchase_invoice_staging_reverse`, and `stg_purchase_invoice_hindi` had redundant data and poor normalization
2. **Multiple Product Names**: Products were referenced by multiple names (Hindi, English, variations) without proper mapping
3. **Missing HSN Codes**: The `items` table had many NULL HSN codes while staging tables contained this data

## What Was Fixed

### 1. Item Name Variants Table
**Problem**: Same product referred to by multiple names without a proper mapping table.

**Solution**: Created `item_name_variants` table that:
- Maps all product name variations to canonical items
- Supports different variant types (hindi, english, alias, brand)
- Tracks the source of each variant
- Allows fast lookup of items by any known name

### 2. HSN Code Updates
**Problem**: Items table missing HSN codes while staging tables contained this information.

**Solution**: 
- Added `hsn_code` column to items table (if not exists)
- Created migration script to populate HSN codes from staging tables
- Added indexes for fast HSN lookups
- Preserved original `hsn` column for backward compatibility

### 3. Normalized Purchase Invoice Structure
**Problem**: Staging tables had denormalized, redundant data.

**Solution**: Created two new tables:
- `purchase_invoice_header`: Stores invoice-level information
- `purchase_invoice_line_items`: Stores line-item details with proper foreign keys
- Both tables properly normalized following 3NF principles

### 4. Helper Views and Stored Procedures
Created utility database objects for common operations:
- `v_item_variants_complete`: View all item variants in one place
- `v_item_lookup`: Find items by any name variant
- `v_purchase_invoice_summary`: Invoice summary with aggregated data
- `sp_find_item_by_name`: Search items by any variant name
- `sp_add_item_variant`: Add new product name variants
- `sp_update_item_hsn`: Update item HSN codes

## Database Schema Changes

### New Tables

#### `item_name_variants`
```sql
CREATE TABLE `item_name_variants` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_id` INT NOT NULL,
  `variant_name` VARCHAR(255) NOT NULL,
  `variant_type` ENUM('hindi', 'english', 'alias', 'brand'),
  `source_table` VARCHAR(50),
  `is_primary` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`item_id`) REFERENCES `items`(`id`)
);
```

#### `purchase_invoice_header`
```sql
CREATE TABLE `purchase_invoice_header` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_no` VARCHAR(50) NOT NULL,
  `invoice_date` DATE NOT NULL,
  `supplier_id` INT,
  `supplier_name` VARCHAR(255),
  `supplier_gstin` VARCHAR(15),
  `total_taxable_amount` DECIMAL(14,2),
  `total_cgst_amount` DECIMAL(14,2),
  `total_sgst_amount` DECIMAL(14,2),
  `total_igst_amount` DECIMAL(14,2),
  `total_amount` DECIMAL(14,2),
  `status` ENUM('staging', 'verified', 'posted', 'cancelled'),
  FOREIGN KEY (`supplier_id`) REFERENCES `parties`(`id`)
);
```

#### `purchase_invoice_line_items`
```sql
CREATE TABLE `purchase_invoice_line_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_header_id` INT NOT NULL,
  `item_id` INT,
  `item_name_variant` VARCHAR(255),
  `hsn_code` VARCHAR(10),
  `batch_no` VARCHAR(50),
  `quantity` DECIMAL(12,3),
  `rate` DECIMAL(12,2),
  `taxable_amount` DECIMAL(12,2),
  `cgst_rate` DECIMAL(5,2),
  `sgst_rate` DECIMAL(5,2),
  `igst_rate` DECIMAL(5,2),
  `line_total` DECIMAL(12,2),
  FOREIGN KEY (`invoice_header_id`) REFERENCES `purchase_invoice_header`(`id`),
  FOREIGN KEY (`item_id`) REFERENCES `items`(`id`)
);
```

## Installation Instructions

### Prerequisites
- MariaDB 10.3+ or MySQL 8.0+
- Database user with CREATE, ALTER, INSERT, UPDATE, SELECT privileges
- **CRITICAL**: Backup your database before running any migration scripts

### Step 1: Backup Your Database
```bash
# Full backup
mysqldump -h 217.21.95.103 -u u184420243_gst4 -p u184420243_jayanti_enter4 > backup_before_normalization_$(date +%Y%m%d).sql

# Or use phpMyAdmin export
```

### Step 2: Review the Script
```bash
# Review what will be changed
less database_normalization.sql
```

### Step 3: Run in Test Environment First (Recommended)
```bash
# Test on a copy of the database first
mysql -h localhost -u root -p test_database < database_normalization.sql
```

### Step 4: Apply to Production
```bash
# Apply the normalization
mysql -h 217.21.95.103 -u u184420243_gst4 -p u184420243_jayanti_enter4 < database_normalization.sql
```

### Step 5: Verify the Changes
```sql
-- Check if new tables exist
SHOW TABLES LIKE '%invoice_header%';
SHOW TABLES LIKE '%item_name_variants%';

-- Check data migration
SELECT COUNT(*) FROM purchase_invoice_header;
SELECT COUNT(*) FROM purchase_invoice_line_items;
SELECT COUNT(*) FROM item_name_variants;

-- Check HSN code population
SELECT 
    COUNT(*) as total_items,
    COUNT(hsn_code) as items_with_hsn,
    ROUND(COUNT(hsn_code) * 100.0 / COUNT(*), 2) as percentage
FROM items;
```

## Usage Examples

### Finding an Item by Any Name Variant
```sql
-- Using the stored procedure
CALL sp_find_item_by_name('दूध बिस्किट');

-- Using the view
SELECT * FROM v_item_lookup 
WHERE search_name LIKE '%बिस्किट%';
```

### Adding a New Product Name Variant
```sql
-- Add a new variant for an existing item
CALL sp_add_item_variant(
    123,                -- item_id
    'Milk Biscuit',     -- variant_name
    'english',          -- variant_type
    'manual_entry'      -- source_table
);
```

### Updating HSN Code for an Item
```sql
-- Update HSN code
CALL sp_update_item_hsn(123, '19059020');
```

### Viewing All Variants for Items
```sql
-- See all variants
SELECT * FROM v_item_variants_complete
WHERE canonical_name LIKE '%बिस्किट%';
```

### Working with Normalized Invoices
```sql
-- Get invoice summary
SELECT * FROM v_purchase_invoice_summary
WHERE invoice_date >= '2024-01-01'
ORDER BY invoice_date DESC;

-- Get line items for an invoice
SELECT 
    pil.*,
    i.canonical_name
FROM purchase_invoice_line_items pil
LEFT JOIN items i ON pil.item_id = i.id
WHERE pil.invoice_header_id = 1;
```

## Rollback Instructions

If something goes wrong, you can rollback the changes:

### Option 1: Using Rollback Script
```bash
mysql -h 217.21.95.103 -u u184420243_gst4 -p u184420243_jayanti_enter4 < database_normalization_rollback.sql
```

### Option 2: Restore from Backup
```bash
mysql -h 217.21.95.103 -u u184420243_gst4 -p u184420243_jayanti_enter4 < backup_before_normalization_20250103.sql
```

## Impact on Existing Data

### What is Preserved
- ✅ All original staging tables remain intact
- ✅ All existing items data preserved
- ✅ All parties, invoices, and other tables unchanged
- ✅ Backward compatible - existing queries continue to work

### What is Added
- ✅ New normalized tables for future data entry
- ✅ Item name variants extracted from staging tables
- ✅ HSN codes populated from staging data
- ✅ Helper views and stored procedures

### What is NOT Changed
- ❌ Original staging tables NOT dropped (can be archived later)
- ❌ Existing application code NOT affected
- ❌ No data loss - all operations are additive

## Performance Improvements

After normalization:
- ⚡ Faster item lookups (indexed variants)
- ⚡ Faster HSN code searches (new indexes)
- ⚡ Reduced data redundancy
- ⚡ Better data integrity through foreign keys
- ⚡ Optimized queries with views

## Next Steps

### 1. Update Application Code
Update your PHP application to use the new normalized tables:

```php
// Example: Find item by variant name
$stmt = db()->prepare("CALL sp_find_item_by_name(?)");
$stmt->execute([$search_term]);
$items = $stmt->fetchAll();

// Example: Get invoice with line items
$invoice = fetchOne("SELECT * FROM v_purchase_invoice_summary WHERE id = ?", [$id]);
$lines = fetchAll("
    SELECT pil.*, i.canonical_name 
    FROM purchase_invoice_line_items pil
    LEFT JOIN items i ON pil.item_id = i.id
    WHERE pil.invoice_header_id = ?
", [$id]);
```

### 2. Add UI Components
Create app_components entries for the new tables (see `metadata_update.sql`).

### 3. Archive Old Staging Tables (Optional)
After verifying everything works:
```sql
-- Rename old tables to archive
RENAME TABLE purchase_invoice_staging TO archive_purchase_invoice_staging;
RENAME TABLE purchase_invoice_staging_reverse TO archive_purchase_invoice_staging_reverse;
RENAME TABLE stg_purchase_invoice_hindi TO archive_stg_purchase_invoice_hindi;
```

### 4. Monitor and Optimize
```sql
-- Check for items still missing HSN codes
SELECT id, canonical_name FROM items 
WHERE hsn_code IS NULL OR hsn_code = ''
LIMIT 20;

-- Find items with multiple variants
SELECT item_id, COUNT(*) as variant_count
FROM item_name_variants
GROUP BY item_id
HAVING variant_count > 3
ORDER BY variant_count DESC;
```

## Troubleshooting

### Issue: Foreign key constraint errors
**Solution**: Ensure parent records exist before inserting child records.

### Issue: Duplicate key errors
**Solution**: The script uses `ON DUPLICATE KEY UPDATE` - safe to re-run.

### Issue: Stored procedures not working on shared hosting
**Solution**: Hostinger may restrict procedures. Use direct queries instead.

### Issue: Migration takes too long
**Solution**: Run in batches, add `LIMIT` clauses to large INSERTs.

## Support and Maintenance

### Validation Queries
Run these periodically to ensure data integrity:

```sql
-- Orphaned variants (should be empty)
SELECT * FROM item_name_variants inv
WHERE NOT EXISTS (SELECT 1 FROM items i WHERE i.id = inv.item_id);

-- Orphaned line items (should be empty)
SELECT * FROM purchase_invoice_line_items pil
WHERE NOT EXISTS (
    SELECT 1 FROM purchase_invoice_header pih 
    WHERE pih.id = pil.invoice_header_id
);
```

### Regular Maintenance
```sql
-- Update header totals (run after bulk line item changes)
UPDATE purchase_invoice_header pih
SET total_amount = (
    SELECT SUM(line_total) 
    FROM purchase_invoice_line_items 
    WHERE invoice_header_id = pih.id
);
```

## Credits

This normalization addresses issues raised in the project requirements:
- Incomplete normalization in staging tables
- Multiple product name problem
- Missing HSN codes in items table
- Need for proper master data management

## License

This normalization script is part of the GST Accounting System project.
