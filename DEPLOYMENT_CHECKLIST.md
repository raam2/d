# Database Normalization - Deployment Checklist

Use this checklist to ensure a smooth deployment of the database normalization.

## 📋 Pre-Deployment Checklist

### Planning Phase
- [ ] Read `QUICK_REFERENCE.md` (5 minutes)
- [ ] Review `NORMALIZATION_GUIDE.md` for full understanding
- [ ] Understand what will change (see ARCHITECTURE.md)
- [ ] Identify deployment window (recommended: 5-10 minutes)
- [ ] Notify stakeholders of planned maintenance

### Preparation Phase
- [ ] Verify database credentials are correct
  ```bash
  mysql -h 217.21.95.103 -u u184420243_gst4 -p u184420243_jayanti_enter4 -e "SELECT 1;"
  ```
- [ ] Check disk space for backup
  ```bash
  df -h /path/to/backup/directory
  ```
- [ ] Ensure all files are present:
  - [ ] `database_normalization.sql`
  - [ ] `database_normalization_rollback.sql`
  - [ ] `metadata_update.sql`
  - [ ] `deploy_normalization.sh`
- [ ] Make deployment script executable
  ```bash
  chmod +x deploy_normalization.sh
  ```

### Backup Phase
- [ ] Create manual database backup
  ```bash
  mysqldump -h 217.21.95.103 -u u184420243_gst4 -p \
    u184420243_jayanti_enter4 > backup_$(date +%Y%m%d_%H%M%S).sql
  ```
- [ ] Verify backup file was created and has data
  ```bash
  ls -lh backup_*.sql
  head -20 backup_*.sql  # Should show SQL statements
  ```
- [ ] Store backup in safe location (copy to multiple places)
- [ ] Test backup restoration (optional but recommended)
  ```bash
  # On test database
  mysql -h localhost -u root -p test_db < backup_*.sql
  ```

## 🧪 Testing Phase (Recommended)

### Test on Copy of Database
- [ ] Create test database
  ```bash
  mysql -h localhost -u root -p -e "CREATE DATABASE test_accounting;"
  ```
- [ ] Import current production data to test
  ```bash
  mysql -h localhost -u root -p test_accounting < backup_*.sql
  ```
- [ ] Run normalization on test database
  ```bash
  export DB_HOST=localhost
  export DB_NAME=test_accounting
  ./deploy_normalization.sh test
  ```
- [ ] Verify test results
  - [ ] Check new tables exist: `SHOW TABLES LIKE '%variant%';`
  - [ ] Check data migrated: `SELECT COUNT(*) FROM item_name_variants;`
  - [ ] Check HSN codes updated: See validation queries below
  - [ ] Test new UI pages work
- [ ] Run validation queries (see TESTING_GUIDE.md)
- [ ] Document any issues found
- [ ] Fix issues if any, re-test

### Test Rollback (Optional)
- [ ] Run rollback script on test database
  ```bash
  mysql -h localhost -u root -p test_accounting < database_normalization_rollback.sql
  ```
- [ ] Verify normalized tables are removed
- [ ] Verify original data still intact

## 🚀 Production Deployment

### Final Checks
- [ ] All testing completed successfully
- [ ] Backup confirmed and stored safely
- [ ] Stakeholders notified
- [ ] Deployment window scheduled
- [ ] Internet/database connection stable

### Deployment Execution
- [ ] **START DEPLOYMENT TIMER** ⏱️
- [ ] Run automated deployment script
  ```bash
  ./deploy_normalization.sh production
  ```
  **OR** manual deployment:
  ```bash
  # Step 1: Normalization
  mysql -h 217.21.95.103 -u u184420243_gst4 -p \
    u184420243_jayanti_enter4 < database_normalization.sql
  
  # Step 2: Metadata
  mysql -h 217.21.95.103 -u u184420243_gst4 -p \
    u184420243_jayanti_enter4 < metadata_update.sql
  ```
- [ ] Monitor for errors during deployment
- [ ] **DEPLOYMENT COMPLETED** ⏱️ (record time taken)

### Immediate Validation
- [ ] Check deployment log for errors
  ```bash
  less backups/migration_*.log
  ```
- [ ] Verify new tables created
  ```sql
  SHOW TABLES LIKE 'item_name_variants';
  SHOW TABLES LIKE 'purchase_invoice_header';
  SHOW TABLES LIKE 'purchase_invoice_line_items';
  ```
- [ ] Run validation queries (see below)
- [ ] Check application is accessible
  ```bash
  curl -I http://vedanthomestay.co.in/app/main_entry.php
  ```

## ✅ Post-Deployment Validation

### Database Checks
- [ ] Verify table counts
  ```sql
  SELECT 
      'item_name_variants' as table_name,
      COUNT(*) as record_count
  FROM item_name_variants
  UNION ALL
  SELECT 
      'purchase_invoice_header',
      COUNT(*)
  FROM purchase_invoice_header
  UNION ALL
  SELECT 
      'purchase_invoice_line_items',
      COUNT(*)
  FROM purchase_invoice_line_items;
  ```
  Expected: Hundreds of records in each

- [ ] Verify HSN code coverage improved
  ```sql
  SELECT 
      COUNT(*) as total_items,
      COUNT(CASE WHEN hsn_code IS NOT NULL AND hsn_code != '' THEN 1 END) as with_hsn,
      ROUND(COUNT(CASE WHEN hsn_code IS NOT NULL AND hsn_code != '' THEN 1 END) * 100.0 / COUNT(*), 2) as coverage_pct
  FROM items;
  ```
  Expected: coverage_pct > 50%

- [ ] Check variant statistics
  ```sql
  SELECT 
      variant_type,
      COUNT(*) as count
  FROM item_name_variants
  GROUP BY variant_type;
  ```
  Expected: Variants in hindi, english, and possibly alias

- [ ] Verify foreign key integrity
  ```sql
  -- Should return 0 rows (no orphaned records)
  SELECT COUNT(*) FROM item_name_variants inv
  WHERE NOT EXISTS (SELECT 1 FROM items i WHERE i.id = inv.item_id);
  ```

- [ ] Test stored procedures
  ```sql
  CALL sp_find_item_by_name('बिस्किट');
  ```
  Expected: Returns matching items

### UI Validation
- [ ] Access data diagnostics page
  ```
  http://vedanthomestay.co.in/app/main_entry.php?p=data_diagnostics
  ```
  Check: HSN coverage, variant stats, migration status

- [ ] Access item variants page
  ```
  http://vedanthomestay.co.in/app/main_entry.php?p=item_variants
  ```
  Check: Can search items, see variants list

- [ ] Access purchase invoices page
  ```
  http://vedanthomestay.co.in/app/main_entry.php?p=purchase_invoices
  ```
  Check: Invoice list displays, line items show

- [ ] Check items page enhancements
  ```
  http://vedanthomestay.co.in/app/main_entry.php?p=items
  ```
  Check: HSN management components visible

### Functional Testing
- [ ] Search for item by Hindi name
  - Go to `?p=item_variants`
  - Enter Hindi name in search
  - Verify results appear

- [ ] View invoice details
  - Go to `?p=purchase_invoices`
  - Find an invoice
  - Verify line items display correctly

- [ ] Check data quality dashboard
  - Go to `?p=data_diagnostics`
  - Verify statistics make sense
  - Note any items missing HSN codes

## 📊 Success Criteria

Mark deployment as successful if:
- [ ] All new tables created (3 tables)
- [ ] All new views created (3 views)
- [ ] All stored procedures created (3 procedures)
- [ ] HSN coverage improved (should be >50%)
- [ ] Item variants created (should be >100)
- [ ] Invoice data migrated (counts match staging tables)
- [ ] No orphaned records found
- [ ] All UI pages load correctly
- [ ] Search functionality works
- [ ] No errors in deployment log

## 🔄 If Issues Found

### Minor Issues (Non-Critical)
- [ ] Document the issue
- [ ] Create ticket for resolution
- [ ] Continue with deployment
- [ ] Fix in next update

### Major Issues (Critical)
- [ ] Stop using new features
- [ ] Assess impact
- [ ] Decide: Fix forward or rollback
- [ ] If rollback needed, see Rollback section

## 🔙 Rollback Procedure

If deployment fails or critical issues found:

- [ ] **DECISION POINT**: Rollback confirmed by stakeholder
- [ ] Run rollback script
  ```bash
  mysql -h 217.21.95.103 -u u184420243_gst4 -p \
    u184420243_jayanti_enter4 < database_normalization_rollback.sql
  ```
- [ ] Verify normalized tables removed
  ```sql
  SHOW TABLES LIKE 'item_name_variants';  -- Should be empty
  ```
- [ ] Verify original data intact
  ```sql
  SELECT COUNT(*) FROM items;  -- Should match pre-deployment count
  ```
- [ ] Test application functionality
- [ ] Document what went wrong
- [ ] Plan corrective action

**Alternative**: Restore from backup
```bash
mysql -h 217.21.95.103 -u u184420243_gst4 -p \
  u184420243_jayanti_enter4 < backup_YYYYMMDD_HHMMSS.sql
```

## 📝 Post-Deployment Tasks

### Immediate (Day 1)
- [ ] Monitor application for errors
- [ ] Check database performance
- [ ] Verify all features working
- [ ] Document any issues
- [ ] Update team on deployment status

### Short-term (Week 1)
- [ ] Review items still missing HSN codes
  ```sql
  SELECT id, canonical_name FROM items 
  WHERE hsn_code IS NULL OR hsn_code = ''
  LIMIT 20;
  ```
- [ ] Manually add missing HSN codes
- [ ] Add any missing product variants
- [ ] Monitor query performance
- [ ] Collect user feedback

### Long-term (Month 1)
- [ ] Review data quality improvements
- [ ] Archive old staging tables (optional)
  ```sql
  RENAME TABLE purchase_invoice_staging TO archive_purchase_invoice_staging;
  RENAME TABLE purchase_invoice_staging_reverse TO archive_purchase_invoice_staging_reverse;
  RENAME TABLE stg_purchase_invoice_hindi TO archive_stg_purchase_invoice_hindi;
  ```
- [ ] Optimize queries if needed
- [ ] Update documentation based on learnings
- [ ] Plan next improvements

## 📞 Support Resources

If you need help:
1. **Quick answers**: See `QUICK_REFERENCE.md`
2. **Detailed guide**: See `NORMALIZATION_GUIDE.md`
3. **Testing help**: See `TESTING_GUIDE.md`
4. **Design questions**: See `ARCHITECTURE.md`
5. **Logs**: Check `backups/migration_*.log`

## ✅ Final Sign-off

Deployment completed by: _________________ Date: _________

Validation completed by: _________________ Date: _________

Issues found: [ ] None  [ ] Minor  [ ] Major

Action taken: [ ] Deployed successfully  [ ] Rolled back

Notes:
_____________________________________________________________
_____________________________________________________________
_____________________________________________________________

---

**Keep this checklist for your records and future reference.**
