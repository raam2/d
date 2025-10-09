# Testing Guide for Database Normalization

This document provides step-by-step instructions for testing the database normalization before deploying to production.

## Prerequisites

- MariaDB 10.3+ or MySQL 8.0+
- Access to a test database (copy of production or sample data)
- `mysql` command-line client
- Bash shell (for automated deployment script)

## Option 1: Quick Test with Docker (Recommended)

### Step 1: Start a Test MySQL Container

```bash
# Pull and start MySQL
docker run --name test-mysql \
  -e MYSQL_ROOT_PASSWORD=testpass \
  -e MYSQL_DATABASE=test_accounting \
  -p 3307:3306 \
  -d mysql:8.0

# Wait for MySQL to be ready
sleep 10
```

### Step 2: Load Sample Database

```bash
# Load the existing database structure
docker exec -i test-mysql mysql -uroot -ptestpass test_accounting < database_already_exit.sql

# Verify tables loaded
docker exec test-mysql mysql -uroot -ptestpass test_accounting \
  -e "SHOW TABLES;" | head -20
```

### Step 3: Run Normalization

```bash
# Run the normalization script
docker exec -i test-mysql mysql -uroot -ptestpass test_accounting \
  < database_normalization.sql

# Check for errors (should be empty or just warnings)
echo $?  # Should return 0 for success
```

### Step 4: Run Metadata Update

```bash
# Update app metadata
docker exec -i test-mysql mysql -uroot -ptestpass test_accounting \
  < metadata_update.sql
```

### Step 5: Validate Results

```bash
# Run validation queries
docker exec -i test-mysql mysql -uroot -ptestpass test_accounting <<'EOF'
-- Check new tables exist
SELECT 
    table_name,
    table_rows
FROM information_schema.tables
WHERE table_schema = 'test_accounting'
  AND table_name IN (
    'item_name_variants',
    'purchase_invoice_header', 
    'purchase_invoice_line_items'
  );

-- Check HSN coverage
SELECT 
    COUNT(*) as total_items,
    COUNT(CASE WHEN hsn_code IS NOT NULL AND hsn_code != '' THEN 1 END) as with_hsn,
    ROUND(COUNT(CASE WHEN hsn_code IS NOT NULL AND hsn_code != '' THEN 1 END) * 100.0 / COUNT(*), 2) as percentage
FROM items;

-- Check variants created
SELECT 
    variant_type,
    COUNT(*) as count
FROM item_name_variants
GROUP BY variant_type;

-- Check invoice migration
SELECT 
    COUNT(*) as header_count
FROM purchase_invoice_header;

SELECT 
    COUNT(*) as line_count
FROM purchase_invoice_line_items;
EOF
```

### Step 6: Test Stored Procedures

```bash
docker exec -i test-mysql mysql -uroot -ptestpass test_accounting <<'EOF'
-- Test item search
CALL sp_find_item_by_name('बिस्किट');

-- Test adding variant
CALL sp_add_item_variant(2, 'Test Variant', 'alias', 'manual_test');

-- Verify variant was added
SELECT * FROM item_name_variants WHERE variant_name = 'Test Variant';
EOF
```

### Step 7: Test Rollback

```bash
# Run rollback script
docker exec -i test-mysql mysql -uroot -ptestpass test_accounting \
  < database_normalization_rollback.sql

# Verify normalized tables are removed
docker exec test-mysql mysql -uroot -ptestpass test_accounting \
  -e "SHOW TABLES LIKE 'item_name_variants';" 
# Should return empty result
```

### Step 8: Cleanup

```bash
# Stop and remove container
docker stop test-mysql
docker rm test-mysql
```

## Option 2: Test on Local MariaDB/MySQL

### Step 1: Create Test Database

```bash
# Create a copy of your database for testing
mysql -u root -p -e "CREATE DATABASE test_accounting;"

# Import existing structure
mysql -u root -p test_accounting < database_already_exit.sql
```

### Step 2: Use Automated Deployment Script

```bash
# Set environment variables
export DB_HOST=localhost
export DB_PORT=3306
export DB_USER=root
export DB_NAME=test_accounting

# Run deployment script in test mode
./deploy_normalization.sh test
```

The script will:
- Create automatic backup
- Run normalization
- Update metadata
- Validate deployment
- Show summary

### Step 3: Review Output

Check the logs in `backups/migration_*.log` for any errors.

### Step 4: Manual Validation

```bash
# Connect to test database
mysql -u root -p test_accounting

# Run validation queries
SELECT * FROM v_item_variants_complete LIMIT 10;
SELECT * FROM v_purchase_invoice_summary LIMIT 10;

# Check procedures
CALL sp_find_item_by_name('test');

# Exit
exit;
```

## Option 3: Manual Step-by-Step Testing

### Step 1: Backup

```bash
mysqldump -u root -p test_accounting > backup_before_test.sql
```

### Step 2: Run Scripts Manually

```bash
# Section by section from database_normalization.sql

# First, just create the tables (SECTION 1-5)
mysql -u root -p test_accounting <<'EOF'
-- Copy SECTION 1 here (CREATE TABLE item_name_variants)
CREATE TABLE IF NOT EXISTS `item_name_variants` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  ...
);
EOF

# Check table was created
mysql -u root -p test_accounting -e "DESC item_name_variants;"

# Continue with other sections...
```

### Step 3: Validate Each Section

After each major section:

```sql
-- After creating item_name_variants
SELECT COUNT(*) FROM item_name_variants;

-- After HSN updates
SELECT COUNT(*) FROM items WHERE hsn_code IS NOT NULL;

-- After invoice migration
SELECT COUNT(*) FROM purchase_invoice_header;
SELECT COUNT(*) FROM purchase_invoice_line_items;
```

## Common Test Scenarios

### Test 1: Item Variant Lookup

```sql
-- Should find items by Hindi name
CALL sp_find_item_by_name('दूध बिस्किट');

-- Should find items by partial match
CALL sp_find_item_by_name('बिस्किट');

-- Should return empty for non-existent
CALL sp_find_item_by_name('nonexistent');
```

### Test 2: HSN Code Updates

```sql
-- Check items that got HSN codes
SELECT 
    i.id,
    i.canonical_name,
    i.hsn,
    i.hsn_code
FROM items i
WHERE i.hsn_code IS NOT NULL
LIMIT 10;

-- Verify HSN came from staging
SELECT 
    i.canonical_name,
    i.hsn_code,
    stg.`HSN code`,
    stg.`Hindi_Name`
FROM items i
INNER JOIN stg_purchase_invoice_hindi stg 
    ON TRIM(i.canonical_name) = TRIM(stg.`Hindi_Name`)
WHERE i.hsn_code = stg.`HSN code`
LIMIT 5;
```

### Test 3: Invoice Data Migration

```sql
-- Compare old vs new structure
SELECT 
    'Old Staging' as source,
    COUNT(DISTINCT invoice_no) as invoice_count,
    COUNT(*) as line_count
FROM purchase_invoice_staging_reverse

UNION ALL

SELECT 
    'New Normalized' as source,
    COUNT(*) as invoice_count,
    (SELECT COUNT(*) FROM purchase_invoice_line_items) as line_count
FROM purchase_invoice_header;
```

### Test 4: View Performance

```sql
-- Test views are working
SELECT * FROM v_item_lookup WHERE search_name LIKE '%बिस्किट%' LIMIT 5;

SELECT * FROM v_purchase_invoice_summary ORDER BY invoice_date DESC LIMIT 5;

SELECT * FROM v_item_variants_complete WHERE canonical_name LIKE 'आस्था%' LIMIT 10;
```

### Test 5: Foreign Key Constraints

```sql
-- Try to delete a parent item (should fail if variants exist)
DELETE FROM items WHERE id = (
    SELECT item_id FROM item_name_variants LIMIT 1
);
-- Should show FK constraint error

-- Delete the variant first, then item
DELETE FROM item_name_variants WHERE item_id = 2;
DELETE FROM items WHERE id = 2;
-- Should succeed
```

## Performance Testing

### Check Index Usage

```sql
-- Explain query plan for variant lookup
EXPLAIN SELECT * FROM item_name_variants WHERE variant_name = 'test';

-- Should use idx_variant_name index

-- Explain query for item lookup
EXPLAIN SELECT * FROM items WHERE hsn_code = '12345678';

-- Should use idx_items_hsn_code index
```

### Check Query Performance

```sql
-- Time variant search
SET profiling = 1;
CALL sp_find_item_by_name('बिस्किट');
SHOW PROFILES;

-- Time invoice summary
SELECT * FROM v_purchase_invoice_summary LIMIT 100;
SHOW PROFILES;
```

## Data Integrity Checks

### Check for Orphaned Records

```sql
-- Orphaned variants (should be empty)
SELECT * FROM item_name_variants inv
WHERE NOT EXISTS (
    SELECT 1 FROM items i WHERE i.id = inv.item_id
);

-- Orphaned line items (should be empty)
SELECT * FROM purchase_invoice_line_items pil
WHERE NOT EXISTS (
    SELECT 1 FROM purchase_invoice_header pih 
    WHERE pih.id = pil.invoice_header_id
);
```

### Check Data Consistency

```sql
-- Verify invoice totals match line items
SELECT 
    pih.id,
    pih.invoice_no,
    pih.total_amount as header_total,
    SUM(pil.line_total) as calculated_total,
    ABS(pih.total_amount - SUM(pil.line_total)) as difference
FROM purchase_invoice_header pih
LEFT JOIN purchase_invoice_line_items pil ON pih.id = pil.invoice_header_id
GROUP BY pih.id, pih.invoice_no, pih.total_amount
HAVING difference > 0.01
ORDER BY difference DESC;
```

## Troubleshooting Test Issues

### Issue: Foreign Key Constraint Fails

**Cause**: Parent record doesn't exist in items table

**Solution**:
```sql
-- Check which items are referenced but don't exist
SELECT DISTINCT pil.item_id
FROM purchase_invoice_line_items pil
LEFT JOIN items i ON pil.item_id = i.id
WHERE i.id IS NULL;

-- Set item_id to NULL for orphaned references
UPDATE purchase_invoice_line_items
SET item_id = NULL
WHERE item_id NOT IN (SELECT id FROM items);
```

### Issue: Duplicate Key Error

**Cause**: Variant already exists

**Solution**: The script uses `ON DUPLICATE KEY UPDATE` - safe to ignore or re-run.

### Issue: Stored Procedures Not Created

**Cause**: Insufficient privileges or MySQL version

**Solution**: 
```sql
-- Grant necessary privileges
GRANT CREATE ROUTINE ON test_accounting.* TO 'your_user'@'localhost';

-- Or skip procedures and use direct queries instead
```

## Success Criteria

After running all tests, verify:

- ✅ All new tables created successfully
- ✅ No orphaned records found
- ✅ HSN coverage improved (should be >50%)
- ✅ Invoice data migrated correctly
- ✅ Variant mappings created
- ✅ Views return expected data
- ✅ Stored procedures work correctly
- ✅ Foreign keys enforce integrity
- ✅ Indexes improve query performance
- ✅ Rollback script works if needed

## Next Steps After Successful Testing

1. Document any test findings
2. Adjust scripts if needed
3. Plan production deployment window
4. Prepare communication for stakeholders
5. Schedule production backup
6. Execute deployment using `./deploy_normalization.sh production`

## Emergency Rollback

If something goes wrong during testing:

```bash
# Option 1: Use rollback script
mysql -u root -p test_accounting < database_normalization_rollback.sql

# Option 2: Restore from backup
mysql -u root -p test_accounting < backup_before_test.sql

# Option 3: Drop and recreate
mysql -u root -p -e "DROP DATABASE test_accounting;"
mysql -u root -p -e "CREATE DATABASE test_accounting;"
mysql -u root -p test_accounting < database_already_exit.sql
```
