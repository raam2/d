# Catalog normalization & matching rollout

This guide explains how to rerun the updated SQL scripts safely on the production database after the earlier attempt failed mid-way. Follow the sequence to avoid foreign key errors.

## 1. Pre-flight checklist

- Take a full backup of `u184420243_jayanti_enter4` (use `mysqldump` or Hostinger backup tools).
- Confirm you can connect as a user with `ALTER` / `CREATE ROUTINE` privileges.
- If the previous attempt partially ran, keep the existing tables (`product_uoms`, `tax_profiles`, `import_batches`) in place—our scripts are idempotent and will reuse them.

## 2. Run the migration

```sql
SOURCE sql/catalog_normalization_migration.sql;
```

What changed vs. the prior build:
- `hsn_codes` now uses a plain `(hsn_code, valid_from)` unique key that works on MariaDB.
- All foreign keys and indices on `items` are added via dynamic checks, so re-running won’t fail if they already exist.
- Data backfill pulls English descriptions from `invoice_items.description_en` where available and guards against missing dates.

### Validation

After the script completes, run the quick sanity checks below:

```sql
SHOW TABLES LIKE 'product_catalog';
SELECT COUNT(*) AS catalog_rows FROM product_catalog;
SELECT COUNT(*) AS alias_rows FROM product_aliases;
SELECT COUNT(*) AS hsn_rows FROM hsn_codes;
```

If counts are zero, review the staging tables—otherwise continue.

## 3. Run the matching routine package

```sql
SOURCE sql/purchase_matching_routine.sql;
```

Highlights:
- Foreign keys to `product_catalog` / `product_aliases` are added only when those tables exist, so you can safely re-run the script before or after the migration (it will attach the constraints once the catalog is present).
- Stored procedures `sp_match_purchase_lines` and `sp_match_purchase_line` encapsulate the auto-matching logic.
- Views `v_import_unmatched` and `v_import_matches` surface review queues for operators.

## 4. Kick off a test batch

1. Insert or import a small batch header into `import_batches` (status `pending`).
2. Load matching rows into `import_purchase_lines` with `match_status='unmatched'`.
3. Call the matcher:
   ```sql
   CALL sp_match_purchase_lines(<batch_id>, 0.65);
   ```
4. Inspect `v_import_unmatched` for manual follow-up items and resolve aliases as needed.

## 5. Rollback strategy

If any step fails:

- `ROLLBACK;` will undo work inside the current transaction block.
- If failure happens after `COMMIT`, restore from the backup taken in step 1.
- All new tables use `IF NOT EXISTS`, so you can fix the script and re-run without dropping objects.

## 6. Post-deployment housekeeping

- Update the application code to reference the new `product_catalog` IDs for items once data quality looks good.
- Schedule batch imports to feed `import_batches` / `import_purchase_lines` instead of writing directly to legacy staging tables.
- Monitor logs for matcher runtime; adjust `min_confidence` as required.
