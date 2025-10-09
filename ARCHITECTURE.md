# Database Normalization Architecture

## Before Normalization

```
┌─────────────────────────────────────────────────────┐
│  purchase_invoice_staging                           │
│  - item_name, hindi_name, invoice_no, supplier...  │
│  - Denormalized, redundant data                    │
│  - No foreign keys                                  │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│  purchase_invoice_staging_reverse                   │
│  - item_name, hindi_name, invoice_no, supplier...  │
│  - Similar data, different format                   │
│  - Calculated fields (GENERATED columns)            │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│  stg_purchase_invoice_hindi                         │
│  - HSN code, Item name, Hindi_Name, Inv No...      │
│  - Contains HSN codes missing from items            │
│  - Inconsistent column names (spaces)               │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│  items                                              │
│  - canonical_name, hsn (NULL for many)             │
│  - No way to search by variant names                │
└─────────────────────────────────────────────────────┘
```

**Problems:**
1. ❌ Data duplication across 3 staging tables
2. ❌ No referential integrity (foreign keys)
3. ❌ Product names inconsistent (Hindi vs English)
4. ❌ HSN codes scattered in staging but missing in master
5. ❌ Can't find items by alternative names

## After Normalization

```
┌──────────────────────┐
│      parties         │
│  - id (PK)          │
│  - name             │
│  - gstin            │
└──────────────────────┘
           │
           │ supplier_id (FK)
           │
           ▼
┌──────────────────────────────────────────┐
│  purchase_invoice_header                 │
│  - id (PK)                              │
│  - invoice_no                            │
│  - invoice_date                          │
│  - supplier_id (FK → parties)           │
│  - supplier_name                         │
│  - supplier_gstin                        │
│  - total_taxable_amount                  │
│  - total_cgst_amount                     │
│  - total_sgst_amount                     │
│  - total_igst_amount                     │
│  - total_amount                          │
│  - status (staging/verified/posted)      │
└──────────────────────────────────────────┘
           │
           │ invoice_header_id (FK)
           │
           ▼
┌──────────────────────────────────────────┐
│  purchase_invoice_line_items             │
│  - id (PK)                              │
│  - invoice_header_id (FK → header)      │
│  - item_id (FK → items)                 │
│  - item_name_variant (original name)    │
│  - hsn_code                              │
│  - batch_no, mfg_date, exp_date         │
│  - quantity, rate                        │
│  - taxable_amount                        │
│  - cgst_rate, sgst_rate, igst_rate      │
│  - cgst_amount, sgst_amount, igst_amt   │
│  - line_total                            │
└──────────────────────────────────────────┘
           │
           │ item_id (FK)
           │
           ▼
┌──────────────────────────────────────────┐
│  items                                   │
│  - id (PK)                              │
│  - canonical_name                        │
│  - hsn (backward compat)                │
│  - hsn_code (NEW - populated)           │
│  - is_active                             │
│  - track_cogs                            │
└──────────────────────────────────────────┘
           │
           │ item_id (FK)
           │
           ▼
┌──────────────────────────────────────────┐
│  item_name_variants                      │
│  - id (PK)                              │
│  - item_id (FK → items)                 │
│  - variant_name                          │
│  - variant_type (hindi/english/alias)   │
│  - source_table                          │
│  - is_primary                            │
└──────────────────────────────────────────┘
```

**Benefits:**
1. ✅ Proper 3NF normalization
2. ✅ Referential integrity enforced
3. ✅ Search by any product name variant
4. ✅ HSN codes centralized and indexed
5. ✅ Clean separation: header ↔ lines
6. ✅ Status tracking for invoices

## Data Flow

### 1. Item Variant Mapping

```
stg_purchase_invoice_hindi       items
┌────────────────────┐         ┌─────────────────┐
│ Hindi_Name:        │         │ canonical_name: │
│ "दूध बिस्किट"     │────┐    │ "दूध बिस्किट"  │
└────────────────────┘    │    └─────────────────┘
                          │              │
purchase_inv_staging      │              │
┌────────────────────┐    │              │
│ item_name:         │    │    ┌─────────▼──────────────┐
│ "Milk Biscuit"     │────┴───▶│ item_name_variants     │
└────────────────────┘         │ - variant_name: "दूध..." │
                               │   type: hindi          │
                               │ - variant_name: "Milk" │
                               │   type: english        │
                               └────────────────────────┘
```

### 2. HSN Code Migration

```
stg_purchase_invoice_hindi
┌──────────────────────────┐
│ Hindi_Name: "दूध बिस्किट"│
│ HSN code: "19059020"     │
└──────────────────────────┘
           │
           │ Match by name
           │ Extract HSN
           ▼
      items
┌──────────────────────────┐
│ canonical_name: "दूध..." │
│ hsn: NULL → "19059020"   │
│ hsn_code: → "19059020"   │
└──────────────────────────┘
```

### 3. Invoice Migration

```
purchase_invoice_staging_reverse
┌───────────────────────────────────────┐
│ invoice_no: "INV001"                  │
│ invoice_date: 2024-04-04              │
│ item_name: "दूध बिस्किट"             │
│ quantity: 100, rate: 7.16             │
│ supplier_name: "KTM"                  │
│ supplier_gstin: "..."                 │
│ calculated_taxable_amount: 716.00     │
│ calculated_cgst_amount: 35.80         │
└───────────────────────────────────────┘
           │
           │ Split into header + lines
           │
           ▼
purchase_invoice_header
┌───────────────────────────────────────┐
│ id: 1                                 │
│ invoice_no: "INV001"                  │
│ invoice_date: 2024-04-04              │
│ supplier_name: "KTM"                  │
│ total_amount: 751.80                  │
└───────────────────────────────────────┘
           │
           │ One header → Many lines
           ▼
purchase_invoice_line_items
┌───────────────────────────────────────┐
│ invoice_header_id: 1                  │
│ item_id: 123                          │
│ item_name_variant: "दूध बिस्किट"     │
│ quantity: 100                          │
│ rate: 7.16                             │
│ taxable_amount: 716.00                │
│ cgst_amount: 35.80                    │
│ sgst_amount: 35.80                    │
│ line_total: 787.60                    │
└───────────────────────────────────────┘
```

## Helper Objects

### Views

```sql
v_item_lookup
├─ Flattens items + variants for search
├─ Returns: item_id, canonical_name, search_name, hsn_code
└─ Use: Fast item search by any variant name

v_item_variants_complete
├─ Shows all variants for all items
├─ Returns: item details + all variant names
└─ Use: Audit and manage product name mappings

v_purchase_invoice_summary
├─ Aggregates header + line item counts/totals
├─ Returns: invoice summary with computed values
└─ Use: Dashboard, reporting, invoice list
```

### Stored Procedures

```sql
sp_find_item_by_name(search_name)
├─ Searches items by canonical or variant name
├─ Returns: matching items with details
└─ Use: Item lookup in forms, autocomplete

sp_add_item_variant(item_id, variant_name, type, source)
├─ Adds new variant to existing item
├─ Handles duplicates gracefully
└─ Use: Manual data cleanup, imports

sp_update_item_hsn(item_id, hsn_code)
├─ Updates both hsn and hsn_code columns
├─ Maintains backward compatibility
└─ Use: Bulk HSN updates, corrections
```

## Query Examples

### Search Item by Any Name
```sql
-- Find by Hindi name
SELECT * FROM v_item_lookup 
WHERE search_name LIKE '%बिस्किट%';

-- Find by English name
SELECT * FROM v_item_lookup 
WHERE search_name LIKE '%Biscuit%';

-- Using procedure
CALL sp_find_item_by_name('बिस्किट');
```

### Get Invoice with Line Items
```sql
-- Summary
SELECT * FROM v_purchase_invoice_summary
WHERE invoice_date >= '2024-01-01';

-- Details
SELECT 
    pih.invoice_no,
    pih.supplier_name,
    pil.item_name_variant,
    i.canonical_name,
    pil.quantity,
    pil.line_total
FROM purchase_invoice_header pih
JOIN purchase_invoice_line_items pil 
    ON pih.id = pil.invoice_header_id
LEFT JOIN items i 
    ON pil.item_id = i.id
WHERE pih.id = 1;
```

### Find Items Missing HSN
```sql
SELECT 
    id,
    canonical_name,
    hsn,
    hsn_code
FROM items
WHERE (hsn IS NULL OR hsn = '')
  AND (hsn_code IS NULL OR hsn_code = '')
LIMIT 20;
```

## Migration Safety

### Non-Destructive Approach
- Original staging tables preserved
- All data copied, not moved
- Foreign keys allow NULL (graceful degradation)
- Rollback script available

### Backward Compatibility
- Old `hsn` column maintained
- Both `hsn` and `hsn_code` populated
- Existing queries continue to work
- New features opt-in

### Data Validation
```sql
-- Verify no data loss
SELECT 
    'Staging' as source,
    COUNT(DISTINCT invoice_no) as count
FROM purchase_invoice_staging_reverse

UNION ALL

SELECT 
    'Normalized' as source,
    COUNT(*) as count
FROM purchase_invoice_header;
```

## Performance Considerations

### Indexes Created
- `idx_items_hsn` on items.hsn
- `idx_items_hsn_code` on items.hsn_code
- `idx_item_id` on item_name_variants.item_id
- `idx_variant_name` on item_name_variants.variant_name
- `idx_invoice_no` on purchase_invoice_header.invoice_no
- `idx_pil_item_name` on purchase_invoice_line_items.item_name_variant

### Query Optimization
- Views use indexed columns
- Procedures use prepared statements
- Foreign keys enforce constraints
- Computed totals avoid repeated calculations
