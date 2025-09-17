
-- INTEGRATION SCRIPT: Insert data from staging with reverse calculations

-- Step 1: Ensure suppliers exist in parties table
INSERT IGNORE INTO parties (name, gstin, party_type)
SELECT DISTINCT 
    supplier_name,
    supplier_gstin,
    'supplier'
FROM purchase_invoice_staging_reverse
WHERE supplier_name IS NOT NULL;

-- Step 2: Ensure items exist in items table
INSERT IGNORE INTO items (canonical_name, hsn)
SELECT DISTINCT 
    item_name,
    hsn_code
FROM purchase_invoice_staging_reverse
WHERE item_name IS NOT NULL;

-- Step 3: Create purchase invoices
INSERT IGNORE INTO invoices (
    party_id, 
    inv_type, 
    invoice_no, 
    external_supplier_invoice_no,
    invoice_date,
    status
)
SELECT DISTINCT
    p.id as party_id,
    'purchase' as inv_type,
    CONCAT('PI-', s.invoice_no) as invoice_no,
    s.invoice_no as external_supplier_invoice_no,
    s.invoice_date,
    'final' as status
FROM purchase_invoice_staging_reverse s
JOIN parties p ON p.name = s.supplier_name
WHERE NOT EXISTS (
    SELECT 1 FROM invoices i 
    WHERE i.external_supplier_invoice_no = s.invoice_no
);

-- Step 4: Insert invoice items with CORRECT reverse-calculated taxable amounts
INSERT INTO invoice_items (
    invoice_id,
    item_id,
    description,
    description_en,
    hsn,
    quantity,
    rate,                          -- Unit rate (excluding GST)
    discount_percent,
    cgst_rate,
    sgst_rate, 
    igst_rate
    -- taxable_amount, cgst_amount, sgst_amount, igst_amount, line_total 
    -- are auto-calculated by MySQL based on the above values
)
SELECT 
    inv.id as invoice_id,
    itm.id as item_id,
    s.hindi_name as description,
    s.item_name as description_en,
    s.hsn_code as hsn,
    s.quantity,
    s.calculated_rate as rate,     -- This is the CORRECT rate from reverse calculation
    0.00 as discount_percent,      -- No discount in this data
    s.cgst_rate,
    s.sgst_rate,
    s.igst_rate
FROM purchase_invoice_staging_reverse s
JOIN parties p ON p.name = s.supplier_name
JOIN invoices inv ON inv.external_supplier_invoice_no = s.invoice_no AND inv.party_id = p.id
JOIN items itm ON itm.canonical_name = s.item_name
WHERE NOT EXISTS (
    SELECT 1 FROM invoice_items ii
    JOIN invoices i2 ON i2.id = ii.invoice_id
    WHERE i2.external_supplier_invoice_no = s.invoice_no
      AND ii.item_id = itm.id
      AND ABS(ii.quantity - s.quantity) < 0.001
);

-- Step 5: Verification of inserted data
SELECT 'Final Integration Verification' as report_title;

-- Check if taxable amounts match our reverse calculations
SELECT 
  'Taxable amount verification' as check_type,
  COUNT(*) as total_items,
  SUM(CASE WHEN ABS(ii.taxable_amount - s.calculated_taxable_amount) < 0.01 THEN 1 ELSE 0 END) as matching_amounts,
  AVG(ABS(ii.taxable_amount - s.calculated_taxable_amount)) as avg_difference
FROM invoice_items ii
JOIN invoices inv ON inv.id = ii.invoice_id
JOIN purchase_invoice_staging_reverse s ON s.invoice_no = inv.external_supplier_invoice_no 
  AND ii.description_en = s.item_name;

-- Summary of inserted invoice data
SELECT 
  'Total purchase invoices created' as metric,
  COUNT(DISTINCT id) as value
FROM invoices 
WHERE inv_type = 'purchase' 
  AND external_supplier_invoice_no IN (SELECT DISTINCT invoice_no FROM purchase_invoice_staging_reverse);

SELECT 
  'Total invoice items created' as metric,
  COUNT(*) as value
FROM invoice_items ii
JOIN invoices inv ON inv.id = ii.invoice_id
WHERE inv.inv_type = 'purchase' 
  AND inv.external_supplier_invoice_no IN (SELECT DISTINCT invoice_no FROM purchase_invoice_staging_reverse);

SELECT 
  'Total taxable value from reverse calculations' as metric,
  CONCAT('₹', FORMAT(SUM(calculated_taxable_amount), 2)) as value
FROM purchase_invoice_staging_reverse;

SELECT 
  'Total GST from reverse calculations' as metric,
  CONCAT('₹', FORMAT(SUM(calculated_cgst_amount + calculated_sgst_amount + calculated_igst_amount), 2)) as value
FROM purchase_invoice_staging_reverse;
