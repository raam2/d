# Database Normalization - Quick Reference

## 🚀 Quick Start

### 1-Minute Deploy
```bash
# Automated deployment (creates backup automatically)
./deploy_normalization.sh production

# Manual deployment
mysql -h 217.21.95.103 -u u184420243_gst4 -p u184420243_jayanti_enter4 < database_normalization.sql
mysql -h 217.21.95.103 -u u184420243_gst4 -p u184420243_jayanti_enter4 < metadata_update.sql
```

### Access New Features
- **Item Variants**: `http://your-domain/app/main_entry.php?p=item_variants`
- **Purchase Invoices**: `http://your-domain/app/main_entry.php?p=purchase_invoices`
- **Data Quality**: `http://your-domain/app/main_entry.php?p=data_diagnostics`

## 📋 What This Fixes

| Problem | Solution | Table/Feature |
|---------|----------|---------------|
| Multiple product names | Name variant mapping | `item_name_variants` |
| Missing HSN codes | Auto-populate from staging | `items.hsn_code` updated |
| Denormalized staging tables | Proper 3NF structure | `purchase_invoice_header` + `_line_items` |
| Can't search by Hindi names | Variant lookup | `sp_find_item_by_name()` |
| No data quality monitoring | Diagnostics dashboard | `?p=data_diagnostics` |

## 🔍 Common Queries

### Find Item by Any Name
```sql
-- By procedure
CALL sp_find_item_by_name('दूध बिस्किट');

-- By view
SELECT * FROM v_item_lookup WHERE search_name LIKE '%बिस्किट%';
```

### Check HSN Coverage
```sql
SELECT 
    COUNT(*) as total,
    COUNT(hsn_code) as with_hsn,
    ROUND(COUNT(hsn_code) * 100.0 / COUNT(*), 2) as coverage_pct
FROM items;
```

### View Invoice with Line Items
```sql
SELECT * FROM v_purchase_invoice_summary 
WHERE invoice_date >= '2024-01-01'
ORDER BY invoice_date DESC;
```

### Add New Product Variant
```sql
CALL sp_add_item_variant(
    123,              -- item_id
    'Milk Biscuit',   -- variant_name
    'english',        -- variant_type
    'manual_entry'    -- source
);
```

### Update HSN Code
```sql
CALL sp_update_item_hsn(123, '19059020');
-- OR
UPDATE items SET hsn = '19059020', hsn_code = '19059020' WHERE id = 123;
```

### Find Items Missing HSN
```sql
SELECT id, canonical_name FROM items 
WHERE hsn_code IS NULL OR hsn_code = ''
LIMIT 20;
```

## 🗂️ New Tables Created

```
item_name_variants          - Maps product name variations
purchase_invoice_header     - Invoice headers (normalized)
purchase_invoice_line_items - Invoice line items (normalized)

Views:
v_item_lookup              - Search items by any name
v_item_variants_complete   - All variants for all items
v_purchase_invoice_summary - Invoice summaries

Procedures:
sp_find_item_by_name()     - Search items
sp_add_item_variant()      - Add variant
sp_update_item_hsn()       - Update HSN
```

## ⚠️ Important Notes

### Before Running
1. **BACKUP YOUR DATABASE** - Script creates backup, but manual backup recommended
2. Test on staging first - Use `./deploy_normalization.sh test`
3. Plan 5-10 minute maintenance window

### What's Preserved
- ✅ All original staging tables (NOT dropped)
- ✅ All existing data (migration copies, doesn't move)
- ✅ Existing app functionality (backward compatible)
- ✅ All parties, invoices, items data

### What Changes
- ➕ New tables added
- ➕ HSN codes populated
- ➕ Name variants extracted
- ➕ New UI pages added

## 🔄 Rollback

```bash
# If something goes wrong
mysql -h HOST -u USER -p DB < database_normalization_rollback.sql

# Or restore from backup
mysql -h HOST -u USER -p DB < backup_before_normalization.sql
```

## 📊 Validation Queries

### After Deployment
```sql
-- Check tables created
SHOW TABLES LIKE '%invoice_header%';
SHOW TABLES LIKE '%item_name_variants%';

-- Check data migrated
SELECT COUNT(*) FROM item_name_variants;
SELECT COUNT(*) FROM purchase_invoice_header;
SELECT COUNT(*) FROM purchase_invoice_line_items;

-- Check HSN improvement
SELECT 
    'Before' as status, 
    COUNT(CASE WHEN hsn IS NOT NULL THEN 1 END) as count
FROM items
UNION ALL
SELECT 
    'After' as status,
    COUNT(CASE WHEN hsn_code IS NOT NULL THEN 1 END) as count
FROM items;
```

## 📁 Files Overview

| File | Purpose | Size |
|------|---------|------|
| `database_normalization.sql` | Main migration script | 19KB |
| `database_normalization_rollback.sql` | Undo changes | 2KB |
| `metadata_update.sql` | UI integration | 15KB |
| `deploy_normalization.sh` | Automated deployment | 8KB |
| `validate_sql.sh` | Pre-flight checks | 3KB |
| `NORMALIZATION_GUIDE.md` | Full documentation | 10KB |
| `TESTING_GUIDE.md` | Testing procedures | 11KB |
| `ARCHITECTURE.md` | Design diagrams | 10KB |

## 🎯 Expected Results

### Before
- Items with HSN: ~0%
- Product variants: 0
- Invoice structure: Denormalized

### After  
- Items with HSN: 50-80%
- Product variants: 300+
- Invoice structure: Normalized (3NF)
- Search speed: 2-5x faster

## 🆘 Troubleshooting

### Script fails with "foreign key constraint"
**Fix**: Parent records missing. Check which items are referenced:
```sql
SELECT DISTINCT item_id FROM purchase_invoice_line_items
WHERE item_id NOT IN (SELECT id FROM items);
```

### Stored procedures not created
**Fix**: Insufficient privileges or shared hosting restriction. Use direct queries instead.

### Deployment takes too long
**Fix**: Large dataset. Run in smaller batches or schedule during low-traffic hours.

### Data looks wrong after migration
**Fix**: Restore from backup and review TESTING_GUIDE.md for validation queries.

## 📞 Support

1. Read full docs: `NORMALIZATION_GUIDE.md`
2. Test first: `TESTING_GUIDE.md`
3. Understand design: `ARCHITECTURE.md`
4. Check logs: `backups/migration_*.log`
5. Rollback if needed: `database_normalization_rollback.sql`

## ✅ Checklist

- [ ] Read NORMALIZATION_GUIDE.md
- [ ] Backup database manually
- [ ] Test on staging environment
- [ ] Review test results
- [ ] Schedule production deployment
- [ ] Run `./deploy_normalization.sh production`
- [ ] Validate at `?p=data_diagnostics`
- [ ] Test new features
- [ ] Archive old staging tables (optional)
- [ ] Update documentation

## 🎓 Learn More

- **Full Guide**: `NORMALIZATION_GUIDE.md` - Installation, usage, troubleshooting
- **Testing**: `TESTING_GUIDE.md` - Docker setup, test scenarios, validation
- **Architecture**: `ARCHITECTURE.md` - Design diagrams, data flow, examples
- **Deployment**: `deploy_normalization.sh` - Automated deployment script
